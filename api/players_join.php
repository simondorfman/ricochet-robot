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

if (!array_key_exists('displayName', $payload) || !is_string($payload['displayName'])) {
    respondJson(422, ['error' => 'displayName is required.']);
}

$displayName = trim($payload['displayName']);
if ($displayName === '') {
    respondJson(422, ['error' => 'displayName cannot be empty.']);
}
if (mb_strlen($displayName) > 64) {
    respondJson(422, ['error' => 'displayName must be 64 characters or fewer.']);
}

if (!array_key_exists('color', $payload) || !is_string($payload['color'])) {
    respondJson(422, ['error' => 'color is required.']);
}

$colorInput = $payload['color'];

$pdo = db();

try {
    $pdo->beginTransaction();

    $room = lockRoomByCode($code);
    if ($room === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Room not found.']);
    }

    $roomId = (int) $room['id'];

    try {
        $player = createOrUpdatePlayer($roomId, $displayName, $colorInput);
    } catch (InvalidArgumentException $e) {
        $pdo->rollBack();
        respondJson(422, ['error' => $e->getMessage()]);
    }

    $playerId = isset($player['id']) ? (int) $player['id'] : null;
    if ($playerId === null || $playerId <= 0) {
        $pdo->rollBack();
        respondJson(500, ['error' => 'Failed to register player.']);
    }

    if (empty($room['host_player_id'])) {
        $updateHost = $pdo->prepare('UPDATE rooms SET host_player_id = :player_id WHERE id = :room_id');
        $updateHost->execute([
            'player_id' => $playerId,
            'room_id'   => $roomId,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respondJson(500, ['error' => 'Unable to join room.']);
}

respondJson(200, [
    'playerId'    => (int) $player['id'],
    'displayName' => $player['display_name'] ?? $displayName,
    'color'       => $player['color'] ?? canonicalPlayerColor($colorInput),
    'tokensWon'   => isset($player['tokens_won']) ? (int) $player['tokens_won'] : 0,
]);

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
