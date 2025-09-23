<?php

declare(strict_types=1);

require_once __DIR__ . '/bid_validation.php';

/**
 * Apply a validated bid to the current round using the provided callbacks.
 *
 * @param array{room_id:mixed,id:mixed,current_low_bid?:mixed} $round
 * @param callable $ensureRoomPlayer function (int $roomId, int $playerId, ?string $name): void
 * @param callable $setNewLowBid function (int $roundId, int $playerId, int $value): void
 * @param callable $insertBid function (int $roundId, int $playerId, int $value): void
 * @param callable $bumpVersion function (int $roundId): void
 *
 * @return array{updatedLow:bool,currentLow:?int}
 */
function processBidForRound(
    array $round,
    int $playerId,
    int $bidValue,
    callable $ensureRoomPlayer,
    callable $setNewLowBid,
    callable $insertBid,
    callable $bumpVersion
): array {
    if ($bidValue < 2) {
        throw new InvalidArgumentException('Bid value must be at least 2.');
    }

    $roomId = isset($round['room_id']) ? (int) $round['room_id'] : 0;
    $roundId = isset($round['id']) ? (int) $round['id'] : 0;

    $ensureRoomPlayer($roomId, $playerId, null);

    $currentLow = null;
    if (array_key_exists('current_low_bid', $round) && $round['current_low_bid'] !== null) {
        $currentLow = (int) $round['current_low_bid'];
    }

    $updatedLow = false;
    if ($currentLow === null || $bidValue < $currentLow) {
        $setNewLowBid($roundId, $playerId, $bidValue);
        $currentLow = $bidValue;
        $updatedLow = true;
    }

    $insertBid($roundId, $playerId, $bidValue);
    $bumpVersion($roundId);

    return [
        'updatedLow' => $updatedLow,
        'currentLow' => $currentLow,
    ];
}
