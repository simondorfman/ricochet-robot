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
        respondJson(404, ['error' => 'Room not found or no active round. Create one via POST /api/rooms/{code}/create.']);
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
                        $queueForStorage = [];
                        try {
                            $bestBids = fetchBestBidsPerPlayer((int) $locked['id'], (int) $locked['room_id']);
                            $orderedQueue = buildVerificationQueue($bestBids);
                            foreach ($orderedQueue as $entry) {
                                if (!isset($entry['playerId'], $entry['value'])) {
                                    continue;
                                }

                                $queueForStorage[] = [
                                    'playerId' => (int) $entry['playerId'],
                                    'value'    => (int) $entry['value'],
                                ];
                            }
                        } catch (Throwable $e) {
                            $queueForStorage = [];
                        }

                        $queueJson = json_encode($queueForStorage);
                        if ($queueJson === false) {
                            $queueJson = '[]';
                        }

                        $update = $pdo->prepare(
                            "UPDATE rounds SET status = 'verifying', verifying_queue_json = :queue_json, verifying_current_index = 0 WHERE id = :id"
                        );
                        $update->execute([
                            'queue_json' => $queueJson,
                            'id'         => (int) $locked['id'],
                        ]);
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
        if ($currentLow !== null && $currentLow < 2) {
            $currentLow = null;
        }
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

        foreach ($leaderboard as &$entry) {
            if (isset($entry['playerId'])) {
                $entry['playerId'] = (int) $entry['playerId'];
            }
            if (isset($entry['tokensWon'])) {
                $entry['tokensWon'] = (int) $entry['tokensWon'];
            }
        }
        unset($entry);

        $playersById = [];
        try {
            $playersById = fetchPlayersForRoom((int) $round['room_id']);
        } catch (Throwable $e) {
            $playersById = [];
        }

        $tokensByPlayer = [];
        foreach ($playersById as $playerId => $playerInfo) {
            $tokensByPlayer[$playerId] = isset($playerInfo['tokensWon']) ? (int) $playerInfo['tokensWon'] : 0;
        }

        foreach ($leaderboard as $entry) {
            if (!isset($entry['playerId'])) {
                continue;
            }

            $tokensByPlayer[(int) $entry['playerId']] = isset($entry['tokensWon']) ? (int) $entry['tokensWon'] : 0;
        }

        $bestBids = [];
        try {
            $bestBids = fetchBestBidsPerPlayer((int) $round['id'], (int) $round['room_id']);
        } catch (Throwable $e) {
            $bestBids = [];
        }

        $bestBidsByPlayer = [];
        foreach ($bestBids as $entry) {
            if (!isset($entry['playerId'])) {
                continue;
            }

            $playerId = (int) $entry['playerId'];
            $bestBidsByPlayer[$playerId] = $entry;
            if (!array_key_exists($playerId, $tokensByPlayer)) {
                $tokensByPlayer[$playerId] = isset($entry['tokensWon']) ? (int) $entry['tokensWon'] : 0;
            }
        }

        $storedQueue = [];
        $storedQueueSeen = [];
        $verifyingIndex = isset($round['verifying_current_index']) ? (int) $round['verifying_current_index'] : 0;
        if ($verifyingIndex < 0) {
            $verifyingIndex = 0;
        }

        if (!empty($round['verifying_queue_json'])) {
            $decodedQueue = json_decode((string) $round['verifying_queue_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedQueue)) {
                foreach ($decodedQueue as $entry) {
                    if (!is_array($entry) || !array_key_exists('playerId', $entry)) {
                        continue;
                    }

                    $playerId = (int) $entry['playerId'];
                    if ($playerId <= 0) {
                        continue;
                    }

                    $details = $bestBidsByPlayer[$playerId] ?? null;
                    $value = isset($entry['value']) ? (int) $entry['value'] : null;
                    if ($value !== null && $value < 2) {
                        $value = null;
                    }
                    if ($value === null && isset($details['value'])) {
                        $value = (int) $details['value'];
                        if ($value < 2) {
                            $value = null;
                        }
                    }

                    $storedQueue[] = [
                        'playerId'      => $playerId,
                        'value'         => $value,
                        'tokensWon'     => $details['tokensWon'] ?? ($tokensByPlayer[$playerId] ?? 0),
                        'createdAt'     => $details['createdAt'] ?? null,
                        'createdAtSort' => $details['createdAtSort'] ?? null,
                        'firstBidId'    => $details['firstBidId'] ?? null,
                        'displayName'   => $details['displayName'] ?? ($playersById[$playerId]['displayName'] ?? null),
                        'color'         => $details['color'] ?? ($playersById[$playerId]['color'] ?? null),
                    ];
                    $storedQueueSeen[$playerId] = true;
                }
            }
        }

        $orderedQueue = [];
        if ($round['status'] === 'verifying' && !empty($storedQueue)) {
            $orderedQueue = $storedQueue;
            $extras = [];
            foreach ($bestBids as $entry) {
                $playerId = $entry['playerId'] ?? null;
                if ($playerId === null || isset($storedQueueSeen[(int) $playerId])) {
                    continue;
                }
                $extras[] = $entry;
            }

            if (!empty($extras)) {
                $orderedQueue = array_merge($orderedQueue, buildVerificationQueue($extras));
            }
        } else {
            $orderedQueue = buildVerificationQueue($bestBids);
        }

        $tiesAtCurrentLow = [];
        $currentLeader = null;

        $queueCount = count($orderedQueue);
        $startIndex = 0;
        if ($round['status'] === 'verifying' && $verifyingIndex > 0) {
            $startIndex = min($verifyingIndex, $queueCount);
        }

        if ($queueCount > $startIndex) {
            $firstCandidate = null;
            for ($i = $startIndex; $i < $queueCount; $i++) {
                $entry = $orderedQueue[$i];
                if (isset($entry['playerId'], $entry['value'])) {
                    $firstCandidate = $entry;
                    break;
                }
            }

            if ($firstCandidate !== null) {
                $minValue = (int) $firstCandidate['value'];
                $currentLow = $minValue;

                for ($i = $startIndex; $i < $queueCount; $i++) {
                    $entry = $orderedQueue[$i];
                    if (!isset($entry['playerId'], $entry['value'])) {
                        continue;
                    }

                    $value = (int) $entry['value'];
                    if ($value !== $minValue) {
                        break;
                    }

                    $playerId = (int) $entry['playerId'];
                    $tokensWon = isset($entry['tokensWon']) ? (int) $entry['tokensWon'] : ($tokensByPlayer[$playerId] ?? 0);
                    $tiesAtCurrentLow[] = [
                        'playerId'    => $playerId,
                        'value'       => $value,
                        'tokensWon'   => $tokensWon,
                        'createdAt'   => $entry['createdAt'] ?? null,
                        'displayName' => $entry['displayName'] ?? ($playersById[$playerId]['displayName'] ?? null),
                        'color'       => $entry['color'] ?? ($playersById[$playerId]['color'] ?? null),
                    ];
                }

                if (!empty($tiesAtCurrentLow)) {
                    $tieCount = count($tiesAtCurrentLow);
                    $leaderEntry = $tiesAtCurrentLow[0];
                    $leaderPlayerId = $leaderEntry['playerId'];
                    $leaderTokens = isset($leaderEntry['tokensWon']) ? (int) $leaderEntry['tokensWon'] : 0;
                    $leaderDetails = $bestBidsByPlayer[$leaderPlayerId] ?? [];
                    $leaderCreatedAt = $leaderDetails['createdAt'] ?? ($leaderEntry['createdAt'] ?? null);

                    $leaderReason = [
                        'kind'            => 'new_low',
                        'leaderTokens'    => $leaderTokens,
                        'tieCountAtValue' => $tieCount,
                    ];

                    if ($tieCount > 1) {
                        $otherBestTokens = null;
                        $otherCreatedAt = null;
                        foreach (array_slice($tiesAtCurrentLow, 1) as $otherEntry) {
                            if (!isset($otherEntry['tokensWon'])) {
                                continue;
                            }

                            $candidateTokens = (int) $otherEntry['tokensWon'];
                            if ($otherBestTokens === null || $candidateTokens < $otherBestTokens) {
                                $otherBestTokens = $candidateTokens;
                                $otherPlayerId = $otherEntry['playerId'] ?? null;
                                if ($otherPlayerId !== null && isset($bestBidsByPlayer[$otherPlayerId]['createdAt'])) {
                                    $otherCreatedAt = $bestBidsByPlayer[$otherPlayerId]['createdAt'];
                                } else {
                                    $otherCreatedAt = $otherEntry['createdAt'] ?? null;
                                }
                            }
                        }

                        if ($otherBestTokens === null) {
                            $otherBestTokens = $leaderTokens;
                        }

                        $leaderReason['otherBestTokens'] = $otherBestTokens;

                        if ($leaderTokens < $otherBestTokens) {
                            $leaderReason['kind'] = 'fewer_tokens';
                        } else {
                            $leaderReason['kind'] = 'earlier_bid';
                            if ($leaderCreatedAt !== null) {
                                $leaderReason['leaderCreatedAt'] = $leaderCreatedAt;
                            }
                            if ($otherCreatedAt !== null) {
                                $leaderReason['otherCreatedAt'] = $otherCreatedAt;
                            }
                        }
                    }

                    $leaderDisplayName = $leaderDetails['displayName'] ?? ($playersById[$leaderPlayerId]['displayName'] ?? null);
                    $leaderColor = $leaderDetails['color'] ?? ($playersById[$leaderPlayerId]['color'] ?? null);

                    $currentLeader = [
                        'playerId'     => $leaderPlayerId,
                        'value'        => $leaderEntry['value'],
                        'tokensWon'    => $leaderTokens,
                        'createdAt'    => $leaderCreatedAt,
                        'leaderReason' => $leaderReason,
                        'displayName'  => $leaderDisplayName,
                        'color'        => $leaderColor,
                    ];

                    $currentLowBy = $leaderPlayerId;
                }
            }
        }

        $verifyingPayload = null;
        if ($round['status'] === 'verifying') {
            $queueForState = [];
            foreach ($storedQueue as $entry) {
                $queueForState[] = [
                    'playerId'  => $entry['playerId'],
                    'value'     => $entry['value'],
                    'tokensWon' => $entry['tokensWon'],
                    'createdAt' => $entry['createdAt'],
                    'displayName' => $entry['displayName'] ?? ($playersById[$entry['playerId']]['displayName'] ?? null),
                    'color'       => $entry['color'] ?? ($playersById[$entry['playerId']]['color'] ?? null),
                ];
            }

            $queueLength = count($queueForState);
            $currentIndex = $verifyingIndex;
            if ($currentIndex < 0) {
                $currentIndex = 0;
            }
            if ($currentIndex > $queueLength) {
                $currentIndex = $queueLength;
            }

            $verifyingPayload = [
                'queue'        => $queueForState,
                'currentIndex' => $currentIndex,
            ];
        }

        $playersState = [];
        foreach ($playersById as $playerId => $playerInfo) {
            $playersState[(string) $playerId] = [
                'displayName' => $playerInfo['displayName'] ?? null,
                'color'       => $playerInfo['color'] ?? null,
                'tokensWon'   => isset($playerInfo['tokensWon']) ? (int) $playerInfo['tokensWon'] : 0,
            ];
        }

        $hostPlayerId = null;
        if (array_key_exists('host_player_id', $round) && $round['host_player_id'] !== null) {
            $hostPlayerId = (int) $round['host_player_id'];
        }

        $payload = [
            'stateVersion' => $currentVersion,
            'status'       => $round['status'],
            'remaining'    => $remaining,
            'currentLow'   => $currentLow,
            'currentLowBy' => $currentLowBy,
            'bids'         => $bids,
            'leaderboard'  => $leaderboard,
            'players'      => $playersState,
            'hostPlayerId' => $hostPlayerId,
            'serverNow'    => $now->format(DateTimeInterface::ATOM),
        ];

        if ($currentLeader !== null) {
            $payload['currentLeader'] = $currentLeader;
        }

        if (!empty($tiesAtCurrentLow)) {
            $payload['tiesAtCurrentLow'] = $tiesAtCurrentLow;
        }

        if ($verifyingPayload !== null) {
            $payload['verifying'] = $verifyingPayload;
        }

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
