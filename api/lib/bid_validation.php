<?php

declare(strict_types=1);

final class BidValidationException extends InvalidArgumentException
{
}

/**
 * Normalize and validate an incoming bid payload array.
 *
 * @param mixed $payload
 *
 * @throws BidValidationException if the payload is invalid
 */
function normalizeBidPayload($payload): array
{
    if (!is_array($payload)) {
        throw new BidValidationException('Invalid JSON payload.');
    }

    if (!array_key_exists('playerId', $payload) || !array_key_exists('value', $payload)) {
        throw new BidValidationException('playerId and value are required.');
    }

    $playerId = normalizeIntegerField($payload['playerId'], 'playerId', 1);
    $bidValue = normalizeIntegerField($payload['value'], 'value', 2);

    return [
        'playerId' => $playerId,
        'value'    => $bidValue,
    ];
}

/**
 * Validate that a payload field contains an integer no smaller than $min.
 *
 * @param mixed $value
 * @param non-empty-string $field
 * @param int $min
 *
 * @throws BidValidationException
 */
function normalizeIntegerField($value, string $field, int $min): int
{
    $trimmed = null;
    if (is_int($value)) {
        $normalized = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/^\d+$/', $trimmed) !== 1) {
            throw new BidValidationException(normalizeIntegerErrorMessage($field, $min));
        }
        $normalized = (int) $trimmed;
    } else {
        throw new BidValidationException(normalizeIntegerErrorMessage($field, $min));
    }

    if ($normalized < $min) {
        throw new BidValidationException(normalizeIntegerErrorMessage($field, $min));
    }

    return $normalized;
}

function normalizeIntegerErrorMessage(string $field, int $min): string
{
    if ($field === 'value' && $min >= 2) {
        return 'value must be an integer ≥ 2.';
    }

    if ($field === 'playerId') {
        return 'playerId must be a positive integer.';
    }

    if ($min > 1) {
        return sprintf('%s must be an integer ≥ %d.', $field, $min);
    }

    if ($min === 1) {
        return sprintf('%s must be a positive integer.', $field);
    }

    return sprintf('%s must be an integer.', $field);
}
