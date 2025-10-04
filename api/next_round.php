<?php

declare(strict_types=1);

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

    if ($hostPlayerId !== $playerId) {
        $pdo->rollBack();
        respondJson(403, ['error' => 'Only the host may start the next round.']);
    }

    $status = $round['status'] ?? '';
    if ($status !== 'complete') {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Current round is not complete yet.']);
    }

    // Generate robot positions for the new round
    $robotPositions = generateRobotPositions();
    $robotPositionsJson = json_encode($robotPositions);
    
    $insert = $pdo->prepare(
        "INSERT INTO rounds (room_id, status, state_version, robot_positions_json, created_at) VALUES (:room_id, 'bidding', 0, :robot_positions, UTC_TIMESTAMP())"
    );
    $insert->execute([
        'room_id' => $roomId,
        'robot_positions' => $robotPositionsJson,
    ]);

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
