<?php

declare(strict_types=1);

require __DIR__ . '/db.php';
require_once __DIR__ . '/lib/bid_logic.php';
require_once __DIR__ . '/lib/bid_validation.php';

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
if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
    respondJson(422, ['error' => 'Invalid JSON payload.']);
}

try {
    $normalized = normalizeBidPayload($payload);
} catch (BidValidationException $e) {
    respondJson(422, ['error' => $e->getMessage()]);
}

$playerId = $normalized['playerId'];
$bidValue = $normalized['value'];

$pdo = db();

try {
    $pdo->beginTransaction();

    $round = lockRoundForUpdateByRoomCode($code);
    if ($round === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Room not found or no active round. Create one via POST /api/rooms/{code}/create.']);
    }

    if ($round['status'] === 'bidding' && empty($round['bidding_ends_at'])) {
        $endsAt = startCountdown((int) $round['id']);
        $round['status'] = 'countdown';
        $round['bidding_ends_at'] = $endsAt;
    }

    if ($round['status'] !== 'countdown') {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Bidding is closed for the current round.']);
    }

    processBidForRound(
        $round,
        $playerId,
        $bidValue,
        'ensureRoomPlayer',
        'setNewLowBid',
        'insertBid',
        'bumpVersion'
    );

    $pdo->commit();
} catch (PlayerAccessException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respondJson(422, ['error' => $e->getMessage()]);
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
