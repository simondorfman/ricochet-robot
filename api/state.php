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
        error_log("Fetching round for code: " . $code);
        $round = fetchCurrentRoundByRoomCode($code);
        error_log("Round fetched: " . ($round ? 'success' : 'null'));
    } catch (Throwable $e) {
        error_log("Error fetching round: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        respondJson(500, ['error' => 'Failed to load round: ' . $e->getMessage()]);
    }

    if ($round === null) {
        respondJson(404, ['error' => 'Room not found or no active round. Create one via POST /api/rooms/{code}/create.']);
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    
    error_log("Round status: " . $round['status'] . ", bidding_ends_at: " . ($round['bidding_ends_at'] ?? 'null'));

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

                    error_log("Timer check: now=" . $now->format('Y-m-d H:i:s') . ", lockedEnds=" . ($lockedEnds ? $lockedEnds->format('Y-m-d H:i:s') : 'null') . ", expired=" . ($lockedEnds !== null && $now >= $lockedEnds ? 'YES' : 'NO'));
                    
                    if ($lockedEnds !== null && $now >= $lockedEnds) {
                        $queueForStorage = [];
                        try {
                            $bestBids = fetchBestBidsPerPlayer((int) $locked['id'], (int) $locked['room_id']);
                            error_log("Best bids fetched: " . json_encode($bestBids));
                            $orderedQueue = buildVerificationQueue($bestBids);
                            error_log("Ordered queue: " . json_encode($orderedQueue));
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
                            error_log("Exception building verification queue: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
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
        try {
        error_log("Processing state for room: " . $code . ", version: " . $currentVersion);
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
            error_log("Verification queue JSON: " . $round['verifying_queue_json']);
            error_log("Decoded queue: " . json_encode($decodedQueue));
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
            
            error_log("Verification payload: " . json_encode($verifyingPayload));
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

        // Parse robot positions from database
        $robotPositions = [];
        error_log("Available round columns: " . implode(', ', array_keys($round)));
        if (array_key_exists('robot_positions_json', $round) && !empty($round['robot_positions_json'])) {
            try {
                $decoded = json_decode((string) $round['robot_positions_json'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $robotPositions = $decoded;
                }
            } catch (Exception $e) {
                error_log("Error parsing robot positions: " . $e->getMessage());
                $robotPositions = [];
            }
        }

        // Get target from target chip system
        $currentTarget = null;
        if (!empty($round['target_chip_id'])) {
            // Get target chip information
            $targetChipSql = 'SELECT symbol, row_pos, col_pos FROM target_chips WHERE id = :id';
            $targetChipStmt = db()->prepare($targetChipSql);
            $targetChipStmt->execute(['id' => $round['target_chip_id']]);
            $targetChip = $targetChipStmt->fetch();
            
            if ($targetChip !== false) {
                $currentTarget = [
                    'symbol' => $targetChip['symbol'],
                    'row' => (int) $targetChip['row_pos'],
                    'col' => (int) $targetChip['col_pos']
                ];
            }
        } elseif (array_key_exists('current_target_json', $round) && !empty($round['current_target_json'])) {
            // Fallback to old JSON format for existing rounds
            $decoded = json_decode((string) $round['current_target_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $currentTarget = $decoded;
            }
        }
        
        // If no target exists, generate one and save it (fallback for old rounds)
        if ($currentTarget === null) {
            try {
                $currentTarget = generateRandomTarget();
                $targetJson = json_encode($currentTarget);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Failed to encode target: ' . json_last_error_msg());
                }
                
                error_log("Generated fallback target: " . $targetJson);
                
                // Update the round with the new target
                $pdo = db();
                $updateTarget = $pdo->prepare("UPDATE rounds SET current_target_json = :target_json WHERE id = :id");
                $updateTarget->execute([
                    'target_json' => $targetJson,
                    'id' => (int) $round['id']
                ]);
            } catch (Exception $e) {
                error_log("Error generating fallback target: " . $e->getMessage());
                // Don't fail the entire request if target generation fails
                $currentTarget = null;
            }
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
            'robotPositions' => $robotPositions,
            'currentTarget' => $currentTarget,
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
        } catch (Throwable $e) {
            error_log("Error generating state: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Failed to generate state: ' . $e->getMessage()]);
            exit;
        }
    }

    usleep(200000);
}

http_response_code(204);
exit;

function generateRandomTarget(): array
{
    // Available symbol positions (matching client-side SYMBOL_POSITIONS)
    $symbolPositions = [
        // Quadrant 1: Top-Left (Rows 0-7, Cols 0-7)
        ['row' => 1, 'col' => 1, 'symbol' => 'DI'], // Diamond Indigo
        ['row' => 2, 'col' => 6, 'symbol' => 'HL'], // Heart Lime
        ['row' => 4, 'col' => 2, 'symbol' => 'CC'], // Club Cyan
        ['row' => 5, 'col' => 7, 'symbol' => 'SY'], // Spade Yellow
        
        // Quadrant 2: Top-Right (Rows 0-7, Cols 8-15)
        ['row' => 1, 'col' => 9, 'symbol' => 'DY'], // Diamond Yellow
        ['row' => 3, 'col' => 11, 'symbol' => 'CI'], // Club Indigo
        ['row' => 5, 'col' => 14, 'symbol' => 'HC'], // Heart Cyan
        ['row' => 6, 'col' => 10, 'symbol' => 'SL'], // Spade Lime
        
        // Quadrant 3: Bottom-Left (Rows 8-15, Cols 0-7)
        ['row' => 9, 'col' => 3, 'symbol' => 'CY'], // Club Yellow
        ['row' => 11, 'col' => 1, 'symbol' => 'HI'], // Heart Indigo
        ['row' => 12, 'col' => 6, 'symbol' => 'SC'], // Spade Cyan
        ['row' => 14, 'col' => 2, 'symbol' => 'DL'], // Diamond Lime
        
        // Quadrant 4: Bottom-Right (Rows 8-15, Cols 8-15)
        ['row' => 7, 'col' => 13, 'symbol' => 'QUAD'], // Special 4-color square
        ['row' => 9, 'col' => 9, 'symbol' => 'DC'], // Diamond Cyan
        ['row' => 11, 'col' => 15, 'symbol' => 'HY'], // Heart Yellow
        ['row' => 13, 'col' => 11, 'symbol' => 'SI'], // Spade Indigo
        ['row' => 15, 'col' => 8, 'symbol' => 'CL']  // Club Lime
    ];
    
    // Select a random symbol position
    $randomIndex = array_rand($symbolPositions);
    $selectedSymbol = $symbolPositions[$randomIndex];
    
    return [
        'row' => $selectedSymbol['row'],
        'col' => $selectedSymbol['col'],
        'symbol' => $selectedSymbol['symbol']
    ];
}

function generateRobotPositions(): array
{
    // Robot names in order (matching client-side ROBOT_ORDER)
    $robotOrder = ['Purple', 'Blue', 'Green', 'Yellow'];
    
    // Board size (16x16)
    $boardSize = 16;
    
    // Symbol positions (these would be the same for all games)
    $symbolPositions = [
        // Add known symbol positions here - for now using empty array
        // In a real implementation, these would be the actual symbol positions
    ];
    
    $occupiedPositions = [];
    foreach ($symbolPositions as $pos) {
        $occupiedPositions[] = $pos['row'] . ',' . $pos['col'];
    }
    
    $robotPositions = [];
    
    foreach ($robotOrder as $robotName) {
        $position = null;
        $tries = 0;
        
        while ($position === null && $tries < 200) {
            $row = random_int(0, $boardSize - 1);
            $col = random_int(0, $boardSize - 1);
            $key = $row . ',' . $col;
            
            // Skip if position is occupied by symbol, other robot, or center area
            if (!in_array($key, $occupiedPositions) && 
                !(($row === 7 || $row === 8) && ($col === 7 || $col === 8))) {
                $position = ['row' => $row, 'col' => $col];
                $occupiedPositions[] = $key;
            }
            $tries++;
        }
        
        // Fallback to default position if random placement fails
        if ($position === null) {
            $position = ['row' => 0, 'col' => 0];
        }
        
        $robotPositions[$robotName] = $position;
    }
    
    return $robotPositions;
}

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
