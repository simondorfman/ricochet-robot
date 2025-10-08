<?php

declare(strict_types=1);

// Removed excessive logging for performance

require __DIR__ . '/db.php';
require_once __DIR__ . '/lib/robot_positions.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respondJson(405, ['error' => 'Method not allowed.']);
}

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
if ($code === '') {
    respondJson(400, ['error' => 'Missing room code.']);
}

$raw = file_get_contents('php://input');
if ($raw === false) {
    respondJson(400, ['error' => 'Missing request body.']);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    respondJson(400, ['error' => 'Invalid JSON payload.']);
}

if (!array_key_exists('playerId', $payload)) {
    respondJson(422, ['error' => 'playerId is required.']);
}

$playerId = is_int($payload['playerId']) ? $payload['playerId'] : (int) $payload['playerId'];
if ($playerId <= 0) {
    respondJson(422, ['error' => 'playerId must be a positive integer.']);
}

$pdo = db();

try {
    // Removed excessive logging for performance
    $pdo->beginTransaction();

    $round = lockRoundForUpdateByRoomCode($code);
    if ($round === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Room not found or no active round.']);
    }

    $roomId = isset($round['room_id']) ? (int) $round['room_id'] : 0;
    $hostPlayerId = array_key_exists('host_player_id', $round) && $round['host_player_id'] !== null
        ? (int) $round['host_player_id']
        : null;

    if ($hostPlayerId === null) {
        $pdo->rollBack();
        respondJson(403, ['error' => 'No host assigned for this room.']);
    }

    $status = $round['status'] ?? '';
    
    // Check if this is a "Start Game" request (no target exists yet)
    $hasTarget = !empty($round['target_chip_id']) || 
                 (array_key_exists('current_target_json', $round) && !empty($round['current_target_json']));
    
    if (!$hasTarget) {
        // This is a "Start Game" request - any player can start the game
        // No additional permission checks needed
        // Allow starting game regardless of current status
        // We'll UPDATE the existing round instead of creating a new one
    } else {
        // This is a regular "Next Round" request - only host can advance
        if ($hostPlayerId !== $playerId) {
            $pdo->rollBack();
            respondJson(403, ['error' => 'Only the host may start the next round.']);
        }
        
        if ($status !== 'complete') {
            $pdo->rollBack();
            respondJson(409, ['error' => 'Current round is not complete yet.']);
        }
    }

    // Get current robot positions from the previous round (preserve robot positions)
    $currentRobotPositions = null;
    if (array_key_exists('robot_positions_json', $round) && !empty($round['robot_positions_json'])) {
        $currentRobotPositions = json_decode((string) $round['robot_positions_json'], true);
    }
    
    // If no current positions, generate new ones (should only happen for first round)
    if ($currentRobotPositions === null) {
        $robotPositions = generateRobotPositions();
    } else {
        $robotPositions = $currentRobotPositions;
    }
    
    $robotPositionsJson = json_encode($robotPositions);
    
    // Draw a target chip for this round
    $targetChip = drawTargetChip($roomId);
    if ($targetChip === null) {
        // Check if all chips are drawn - if so, reshuffle them
        $stats = getTargetChipStats($roomId);
        if ($stats['total'] > 0 && $stats['remaining'] == 0) {
            reshuffleTargetChips($roomId);
            $targetChip = drawTargetChip($roomId);
        } else {
            // No chips available - initialize them for this room
            initializeTargetChips($roomId);
            $targetChip = drawTargetChip($roomId);
        }
        
        // If still no chips after initialization/reshuffle, something is wrong
        if ($targetChip === null) {
            $pdo->rollBack();
            respondJson(500, ['error' => 'Unable to get target chip for this room.']);
        }
    }
    
    if (!$hasTarget) {
        // This is a "Start Game" request - UPDATE the existing round
        $update = $pdo->prepare(
            "UPDATE rounds SET status = 'bidding', state_version = 0, robot_positions_json = :robot_positions, target_chip_id = :target_chip_id WHERE id = :round_id"
        );
        
        $update->execute([
            'robot_positions' => $robotPositionsJson,
            'target_chip_id' => $targetChip['id'],
            'round_id' => $round['id'],
        ]);
        $newRoundId = (int) $round['id'];
    } else {
        // This is a "Next Round" request - INSERT a new round
        $insert = $pdo->prepare(
            "INSERT INTO rounds (room_id, status, state_version, robot_positions_json, target_chip_id, created_at) VALUES (:room_id, 'bidding', 0, :robot_positions, :target_chip_id, UTC_TIMESTAMP())"
        );
        
        $insert->execute([
            'room_id' => $roomId,
            'robot_positions' => $robotPositionsJson,
            'target_chip_id' => $targetChip['id'],
        ]);
        $newRoundId = (int) $pdo->lastInsertId();
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respondJson(500, ['error' => 'Unable to start next round.']);
}

respondJson(200, ['ok' => true, 'roundId' => $newRoundId]);


function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
