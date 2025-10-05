<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respondJson(405, ['error' => 'Method not allowed.']);
}

$code = $_GET['code'] ?? null;
$action = $_GET['action'] ?? null;

if ($code === null || $code === '') {
    respondJson(400, ['error' => 'Missing room code.']);
}

$action = is_string($action) ? strtolower($action) : '';
if ($action !== 'pass' && $action !== 'fail') {
    respondJson(400, ['error' => 'Invalid verify action.']);
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
    respondJson(400, ['error' => 'playerId is required.']);
}

$hostPlayerId = (int) $payload['playerId'];
if ($hostPlayerId <= 0) {
    respondJson(400, ['error' => 'playerId must be a positive integer.']);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $round = lockRoundForUpdateByRoomCode($code);
    if ($round === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Room not found or no active round.']);
    }

    if (($round['status'] ?? '') !== 'verifying') {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Round is not in verifying status.']);
    }

    // Get the current player being verified
    $queueJson = $round['verifying_queue_json'] ?? '[]';
    $decoded = json_decode((string) $queueJson, true);
    $queue = [];
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $entry) {
            if (!is_array($entry) || !array_key_exists('playerId', $entry)) {
                continue;
            }

            $queue[] = [
                'playerId' => (int) $entry['playerId'],
                'value'    => isset($entry['value']) ? (int) $entry['value'] : null,
            ];
        }
    }

    $currentIndex = isset($round['verifying_current_index']) ? (int) $round['verifying_current_index'] : 0;
    if ($currentIndex < 0) {
        $currentIndex = 0;
    }

    $queueCount = count($queue);
    $currentEntry = $queue[$currentIndex] ?? null;
    if ($currentIndex >= $queueCount) {
        $currentEntry = null;
    }

    // Check if the requesting player is the current player being verified
    $currentPlayerId = $currentEntry ? (int) $currentEntry['playerId'] : null;
    if ($currentPlayerId === null || $currentPlayerId !== $hostPlayerId) {
        $pdo->rollBack();
        respondJson(403, ['error' => 'Only the current player being verified may update verification.']);
    }

    if ($action === 'pass') {
        if ($currentEntry === null || !isset($currentEntry['playerId'])) {
            $pdo->rollBack();
            respondJson(409, ['error' => 'No verifier available to pass.']);
        }

        $winnerId = (int) $currentEntry['playerId'];

        $update = $pdo->prepare(
            "UPDATE rounds SET status = 'complete', winner_player_id = :winner_id, verifying_current_index = :current_index, ended_at = UTC_TIMESTAMP() WHERE id = :id"
        );
        $update->execute([
            'winner_id'     => $winnerId,
            'current_index' => $currentIndex,
            'id'            => (int) $round['id'],
        ]);

        awardTokenToPlayer((int) $round['room_id'], $winnerId);
        
        // Check win conditions after awarding token
        $winCondition = checkWinCondition((int) $round['room_id'], $winnerId);
        
        bumpVersion((int) $round['id']);

        $pdo->commit();
        respondJson(200, [
            'ok' => true, 
            'result' => 'pass', 
            'winnerPlayerId' => $winnerId,
            'gameWon' => $winCondition['gameWon'],
            'winReason' => $winCondition['reason'] ?? null
        ]);
    }

    // action === 'fail'
    if ($queueCount > 0 && $currentEntry === null) {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Unable to locate current verifier.']);
    }

    $nextIndex = $queueCount === 0 ? 0 : $currentIndex + 1;

    if ($queueCount === 0 || $nextIndex >= $queueCount) {
        $finalIndex = $queueCount === 0 ? 0 : $nextIndex;
        
        // Check if we need to reshuffle target chips (no one succeeded)
        $roomId = (int) $round['room_id'];
        $targetChipStats = getTargetChipStats($roomId);
        
        // If no chips remaining, reshuffle for next round
        if ($targetChipStats['remaining'] <= 0) {
            reshuffleTargetChips($roomId);
        }
        
        $update = $pdo->prepare(
            "UPDATE rounds SET status = 'complete', verifying_current_index = :current_index, winner_player_id = NULL, ended_at = UTC_TIMESTAMP() WHERE id = :id"
        );
        $update->execute([
            'current_index' => $finalIndex,
            'id'            => (int) $round['id'],
        ]);
    } else {
        $update = $pdo->prepare(
            'UPDATE rounds SET verifying_current_index = :current_index WHERE id = :id'
        );
        $update->execute([
            'current_index' => $nextIndex,
            'id'            => (int) $round['id'],
        ]);
    }

    bumpVersion((int) $round['id']);

    $pdo->commit();
    $reportedIndex = $queueCount === 0 ? 0 : min($nextIndex, $queueCount);
    $queueExhausted = $queueCount === 0 || $nextIndex >= $queueCount;

    respondJson(200, [
        'ok' => true, 
        'result' => 'fail', 
        'currentIndex' => $reportedIndex,
        'queueExhausted' => $queueExhausted,
        'queueLength' => $queueCount
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respondJson(500, ['error' => 'Unable to update verification status.']);
}

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
