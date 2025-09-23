<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

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

    $room = lockRoomByCode($code);
    if ($room === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Room not found.']);
    }

    if (!empty($room['host_player_id'])) {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Host already assigned.']);
    }

    $roomId = (int) $room['id'];
    $player = fetchPlayerByRoomAndId($roomId, $playerId);
    if ($player === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Player not found in this room.']);
    }

    $update = $pdo->prepare('UPDATE rooms SET host_player_id = :player_id WHERE id = :room_id AND host_player_id IS NULL');
    $update->execute([
        'player_id' => $playerId,
        'room_id'   => $roomId,
    ]);

    if ($update->rowCount() === 0) {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Host already claimed.']);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respondJson(500, ['error' => 'Unable to claim host role.']);
}

respondJson(200, ['ok' => true]);

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
