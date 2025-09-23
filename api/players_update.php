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

$displayName = null;
if (array_key_exists('displayName', $payload)) {
    if ($payload['displayName'] === null) {
        $displayName = null;
    } elseif (is_string($payload['displayName'])) {
        $displayName = trim($payload['displayName']);
        if ($displayName === '') {
            respondJson(422, ['error' => 'displayName cannot be empty.']);
        }
        if (mb_strlen($displayName) > 64) {
            respondJson(422, ['error' => 'displayName must be 64 characters or fewer.']);
        }
    } else {
        respondJson(422, ['error' => 'displayName must be a string.']);
    }
}

$color = null;
if (array_key_exists('color', $payload)) {
    if ($payload['color'] === null) {
        $color = null;
    } elseif (is_string($payload['color'])) {
        $color = $payload['color'];
    } else {
        respondJson(422, ['error' => 'color must be a string.']);
    }
}

if ($displayName === null && $color === null) {
    respondJson(422, ['error' => 'Provide displayName and/or color to update.']);
}

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
        $updated = updatePlayerDetails($roomId, $playerId, $displayName, $color);
    } catch (PlayerAccessException $e) {
        $pdo->rollBack();
        respondJson(404, ['error' => $e->getMessage()]);
    } catch (InvalidArgumentException $e) {
        $pdo->rollBack();
        respondJson(422, ['error' => $e->getMessage()]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respondJson(500, ['error' => 'Unable to update player.']);
}

respondJson(200, [
    'ok'          => true,
    'playerId'    => $playerId,
    'displayName' => $updated['display_name'] ?? $displayName,
    'color'       => $updated['color'] ?? ($color !== null ? canonicalPlayerColor($color) : null),
]);

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
