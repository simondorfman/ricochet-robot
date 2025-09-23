<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

$code = $_GET['code'] ?? null;
if ($code === null || $code === '') {
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

if (!array_key_exists('playerId', $payload) || !array_key_exists('value', $payload)) {
    respondJson(400, ['error' => 'playerId and value are required.']);
}

$playerId = (int) $payload['playerId'];
$bidValue = (int) $payload['value'];

if ($playerId <= 0 || $bidValue <= 0) {
    respondJson(400, ['error' => 'playerId and value must be positive integers.']);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $round = lockRoundForUpdateByRoomCode($code);
    if ($round === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Room not found or no active round. Create one via POST /api/rooms/{code}/create.']);
    }

    $updated = false;

    if ($round['status'] === 'bidding' && empty($round['bidding_ends_at'])) {
        $endsAt = startCountdown((int) $round['id']);
        $round['status'] = 'countdown';
        $round['bidding_ends_at'] = $endsAt;
        $updated = true;
    }

    if ($round['status'] !== 'countdown') {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Bidding is closed for the current round.']);
    }

    $currentLow = array_key_exists('current_low_bid', $round) ? $round['current_low_bid'] : null;
    $shouldUpdateLow = $currentLow === null || $bidValue < (int) $currentLow;

    if ($shouldUpdateLow) {
        setNewLowBid((int) $round['id'], $playerId, $bidValue);
        $updated = true;
    }

    insertBid((int) $round['id'], $playerId, $bidValue);

    if ($updated) {
        bumpVersion((int) $round['id']);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respondJson(500, ['error' => 'Unable to record bid.']);
}

respondJson(200, ['ok' => true]);

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
