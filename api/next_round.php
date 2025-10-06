<?php

declare(strict_types=1);

error_log("DEBUG: next_round.php file loaded at " . date('Y-m-d H:i:s'));

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
    error_log("Starting next_round.php for room: $code, player: $playerId");
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
    
    if (!$hasTarget && $status === 'bidding') {
        // This is a "Start Game" request - any player can start the game
        // No additional permission checks needed
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

    // Generate robot positions for the new round
    error_log("Generating robot positions...");
    $robotPositions = generateRobotPositions();
    $robotPositionsJson = json_encode($robotPositions);
    error_log("Robot positions generated: " . $robotPositionsJson);
    
    // Draw a target chip for this round
    error_log("Drawing target chip for room: $roomId");
    $targetChip = drawTargetChip($roomId);
    if ($targetChip === null) {
        error_log("No target chips available, initializing for room: $roomId");
        // No chips available - initialize them for this room
        initializeTargetChips($roomId);
        $targetChip = drawTargetChip($roomId);
        
        // If still no chips after initialization, something is wrong
        if ($targetChip === null) {
            error_log("Failed to initialize target chips for room: $roomId");
            $pdo->rollBack();
            respondJson(500, ['error' => 'Unable to initialize target chips for this room.']);
        }
    }
    error_log("Target chip drawn: " . json_encode($targetChip));
    
    error_log("Inserting new round into database...");
    $insert = $pdo->prepare(
        "INSERT INTO rounds (room_id, status, state_version, robot_positions_json, target_chip_id, created_at) VALUES (:room_id, 'bidding', 0, :robot_positions, :target_chip_id, UTC_TIMESTAMP())"
    );
    
    try {
        $insert->execute([
            'room_id' => $roomId,
            'robot_positions' => $robotPositionsJson,
            'target_chip_id' => $targetChip['id'],
        ]);
        error_log("New round inserted successfully");
    } catch (PDOException $e) {
        error_log("Database insert failed: " . $e->getMessage());
        error_log("SQL error code: " . $e->getCode());
        error_log("SQL state: " . $e->errorInfo[0]);
        throw $e;
    }

    $newRoundId = (int) $pdo->lastInsertId();

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
