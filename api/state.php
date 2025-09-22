<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

$code = $_GET['code'] ?? null;
if ($code === null || $code === '') {
    respondJson(400, ['error' => 'Missing room code.']);
}

$since = isset($_GET['since']) ? (int) $_GET['since'] : -1;
$timeoutAt = microtime(true) + 25.0;

while (microtime(true) < $timeoutAt) {
    try {
        $round = fetchCurrentRoundByRoomCode($code);
    } catch (Throwable $e) {
        respondJson(500, ['error' => 'Failed to load round.']);
    }

    if ($round === null) {
        respondJson(404, ['error' => 'Room not found or no active round.']);
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    if (!empty($round['bidding_ends_at']) && $round['status'] === 'countdown') {
        try {
            $endsAt = new DateTimeImmutable($round['bidding_ends_at'], new DateTimeZone('UTC'));
        } catch (Exception $e) {
            $endsAt = null;
        }

        if ($endsAt !== null && $now >= $endsAt) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $locked = lockRoundForUpdateById((int) $round['id']);
                if ($locked !== null && $locked['status'] === 'countdown' && !empty($locked['bidding_ends_at'])) {
                    try {
                        $lockedEnds = new DateTimeImmutable($locked['bidding_ends_at'], new DateTimeZone('UTC'));
                    } catch (Exception $e) {
                        $lockedEnds = null;
                    }

                    if ($lockedEnds !== null && $now >= $lockedEnds) {
                        setRoundStatus((int) $locked['id'], 'verifying');
                        bumpVersion((int) $locked['id']);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                respondJson(500, ['error' => 'Failed to advance round state.']);
            }

            // Refresh the state immediately after an automatic transition.
            usleep(20000);
            continue;
        }
    }

    $currentVersion = isset($round['state_version']) ? (int) $round['state_version'] : 0;

    if ($currentVersion !== $since) {
        $remaining = null;
        if (!empty($round['bidding_ends_at'])) {
            try {
                $endsAt = new DateTimeImmutable($round['bidding_ends_at'], new DateTimeZone('UTC'));
                $diff = $endsAt->getTimestamp() - $now->getTimestamp();
                $remaining = max(0, $diff);
            } catch (Exception $e) {
                $remaining = null;
            }
        }

        $currentLow = array_key_exists('current_low_bid', $round) && $round['current_low_bid'] !== null
            ? (int) $round['current_low_bid']
            : null;
        $currentLowBy = array_key_exists('current_low_bidder_player_id', $round) && $round['current_low_bidder_player_id'] !== null
            ? (int) $round['current_low_bidder_player_id']
            : null;

        $bids = [];
        try {
            $bids = fetchRecentBids((int) $round['id']);
        } catch (Throwable $e) {
            $bids = [];
        }

        $leaderboard = [];
        try {
            $leaderboard = fetchLeaderboard((int) $round['room_id']);
        } catch (Throwable $e) {
            $leaderboard = [];
        }

        $payload = [
            'stateVersion' => $currentVersion,
            'status'       => $round['status'],
            'remaining'    => $remaining,
            'currentLow'   => $currentLow,
            'currentLowBy' => $currentLowBy,
            'bids'         => $bids,
            'leaderboard'  => $leaderboard,
            'serverNow'    => $now->format(DateTimeInterface::ATOM),
        ];

        respondJson(200, $payload);
    }

    usleep(200000);
}

http_response_code(204);
exit;

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
