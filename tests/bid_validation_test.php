<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/lib/bid_logic.php';
require_once __DIR__ . '/../api/lib/bid_validation.php';

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectValidationError(callable $callback, string $expected): void
{
    try {
        $callback();
        ensure(false, 'Expected BidValidationException to be thrown.');
    } catch (BidValidationException $e) {
        ensure(strpos($e->getMessage(), $expected) !== false, 'Unexpected validation message: ' . $e->getMessage());
    }
}

expectValidationError(function () {
    normalizeBidPayload(['playerId' => 10, 'value' => 1]);
}, '≥ 2');

expectValidationError(function () {
    normalizeBidPayload(['playerId' => 10, 'value' => 9.5]);
}, 'integer');

$valid = normalizeBidPayload(['playerId' => 7, 'value' => 3]);
ensure($valid['playerId'] === 7, 'playerId did not normalize to integer.');
ensure($valid['value'] === 3, 'value did not normalize to integer.');

$validStrings = normalizeBidPayload(['playerId' => '15', 'value' => '12']);
ensure($validStrings['playerId'] === 15, 'playerId string did not normalize correctly.');
ensure($validStrings['value'] === 12, 'value string did not normalize correctly.');

$round = [
    'id' => 1,
    'room_id' => 1,
    'current_low_bid' => null,
];

$records = [];
$ensureRoomPlayer = static function (int $roomId, int $playerId, ?string $name) use (&$records): void {
    $records[] = ['type' => 'ensure', 'roomId' => $roomId, 'playerId' => $playerId];
};
$setNewLowBid = static function (int $roundId, int $playerId, int $value) use (&$records): void {
    $records[] = ['type' => 'low', 'roundId' => $roundId, 'playerId' => $playerId, 'value' => $value];
};
$insertBid = static function (int $roundId, int $playerId, int $value) use (&$records): void {
    $records[] = ['type' => 'insert', 'roundId' => $roundId, 'playerId' => $playerId, 'value' => $value];
};
$bumpVersion = static function (int $roundId) use (&$records): void {
    $records[] = ['type' => 'version', 'roundId' => $roundId];
};

$resultA = processBidForRound($round, 21, 5, $ensureRoomPlayer, $setNewLowBid, $insertBid, $bumpVersion);
$round['current_low_bid'] = $resultA['currentLow'];
$resultB = processBidForRound($round, 22, 5, $ensureRoomPlayer, $setNewLowBid, $insertBid, $bumpVersion);
$round['current_low_bid'] = $resultB['currentLow'];

$inserts = array_values(array_filter($records, static function (array $entry): bool {
    return $entry['type'] === 'insert';
}));

ensure(count($inserts) === 2, 'Expected two insert operations for equal bids.');
ensure($inserts[0]['playerId'] === 21, 'First insert stored wrong player.');
ensure($inserts[1]['playerId'] === 22, 'Second insert stored wrong player.');

if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, "All bid validation tests passed.\n");
}
