<?php

declare(strict_types=1);


/**
 * Load the Ricochet Robot configuration array.
 */
function rr_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $paths = [];

    $envPath = $_SERVER['RR_CONFIG_PATH'] ?? getenv('RR_CONFIG_PATH') ?: null;
    if (is_string($envPath) && $envPath !== '') {
        $paths[] = $envPath;
    }

    $rootDir = dirname(__DIR__);
    $paths[] = $rootDir . '/config.php';
    $paths[] = $rootDir . '/config.sample.php';

    foreach ($paths as $path) {
        if (!is_string($path) || $path === '') {
            continue;
        }

        if (is_file($path) && is_readable($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = $loaded;
                return $config;
            }
        }
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DB config not found. Set RR_CONFIG_PATH or provide config.php.']);
    exit;
}

/**
 * Returns a shared PDO instance using the resolved configuration array.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = rr_config();

    $host = $config['db_host'] ?? 'localhost';
    $dbname = $config['db_name'] ?? 'ricochet_robot';
    $user = $config['db_user'] ?? 'root';
    $pass = $config['db_pass'] ?? '';
    $port = isset($config['db_port']) ? (int) $config['db_port'] : 3306;
    if ($port <= 0) {
        $port = 3306;
    }
    $charset = $config['db_charset'] ?? 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;dbname=%s;port=%d;charset=%s', $host, $dbname, $port, $charset);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}

function fetchCurrentRoundByRoomCode(string $code): ?array
{
    $sql = <<<SQL
SELECT r.*
FROM rooms rm
JOIN rounds r ON r.room_id = rm.id
WHERE rm.code = :code
ORDER BY r.id DESC
LIMIT 1
SQL;
    $stmt = db()->prepare($sql);
    $stmt->execute(['code' => $code]);
    $round = $stmt->fetch();

    return $round !== false ? $round : null;
}

function lockRoundForUpdateByRoomCode(string $code): ?array
{
    $sql = <<<SQL
SELECT r.*
FROM rooms rm
JOIN rounds r ON r.room_id = rm.id
WHERE rm.code = :code
ORDER BY r.id DESC
LIMIT 1
FOR UPDATE
SQL;
    $stmt = db()->prepare($sql);
    $stmt->execute(['code' => $code]);
    $round = $stmt->fetch();

    return $round !== false ? $round : null;
}

function lockRoundForUpdateById(int $roundId): ?array
{
    $sql = 'SELECT * FROM rounds WHERE id = :id FOR UPDATE';
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $roundId]);
    $round = $stmt->fetch();

    return $round !== false ? $round : null;
}

function startCountdown(int $roundId, int $seconds = 60): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $endsAt = $now->modify(sprintf('+%d seconds', $seconds));

    $sql = <<<SQL
UPDATE rounds
SET status = 'countdown',
    bidding_ends_at = :ends_at
WHERE id = :id
SQL;
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'id'      => $roundId,
        'ends_at' => $endsAt->format('Y-m-d H:i:s'),
    ]);

    return $endsAt->format('Y-m-d H:i:s');
}

function setRoundStatus(int $roundId, string $status): void
{
    $sql = 'UPDATE rounds SET status = :status WHERE id = :id';
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'id'     => $roundId,
        'status' => $status,
    ]);
}

function setNewLowBid(int $roundId, int $playerId, int $value): void
{
    $sql = <<<SQL
UPDATE rounds
SET current_low_bid = :value,
    current_low_bidder_player_id = :player_id
WHERE id = :id
SQL;
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'id'        => $roundId,
        'player_id' => $playerId,
        'value'     => $value,
    ]);
}

function insertBid(int $roundId, int $playerId, int $value): void
{
    $sql = <<<SQL
INSERT INTO bids (round_id, player_id, value, created_at)
VALUES (:round_id, :player_id, :value, UTC_TIMESTAMP())
SQL;
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'round_id'  => $roundId,
        'player_id' => $playerId,
        'value'     => $value,
    ]);
}

function bumpVersion(int $roundId): void
{
    $sql = 'UPDATE rounds SET state_version = state_version + 1 WHERE id = :id';
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $roundId]);
}

function fetchRecentBids(int $roundId): array
{
    $sql = <<<SQL
SELECT player_id, value, created_at
FROM bids
WHERE round_id = :round_id
ORDER BY created_at ASC, id ASC
SQL;
    $stmt = db()->prepare($sql);
    $stmt->execute(['round_id' => $roundId]);

    $rows = $stmt->fetchAll();

    $bids = [];
    foreach ($rows as $row) {
        $createdAt = null;
        if (!empty($row['created_at'])) {
            try {
                $createdAt = (new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
            } catch (Exception $e) {
                $createdAt = null;
            }
        }

        $bids[] = [
            'playerId'  => isset($row['player_id']) ? (int) $row['player_id'] : null,
            'value'     => isset($row['value']) ? (int) $row['value'] : null,
            'createdAt' => $createdAt,
        ];
    }

    return $bids;
}

function fetchLeaderboard(int $roomId): array
{
    $sql = <<<SQL
SELECT player_id, name, points
FROM room_players
WHERE room_id = :room_id
ORDER BY points DESC, name ASC
SQL;
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute(['room_id' => $roomId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }

    $leaderboard = [];
    foreach ($rows as $row) {
        $points = isset($row['points']) ? (int) $row['points'] : 0;
        $leaderboard[] = [
            'playerId'   => isset($row['player_id']) ? (int) $row['player_id'] : null,
            'name'       => $row['name'] ?? null,
            'points'     => $points,
            'tokensWon'  => $points,
        ];
    }

    return $leaderboard;
}

function fetchLowestValueBids(int $roundId): array
{
    $sql = <<<SQL
SELECT
    b.player_id,
    b.value,
    MIN(b.created_at) AS first_created_at,
    MIN(b.id) AS first_bid_id
FROM bids b
WHERE b.round_id = :round_id
GROUP BY b.player_id, b.value
ORDER BY b.value ASC, first_created_at ASC, first_bid_id ASC
SQL;

    $stmt = db()->prepare($sql);
    $stmt->execute(['round_id' => $roundId]);

    $rows = $stmt->fetchAll();
    if (!$rows) {
        return [];
    }

    $lowestValue = null;
    $results = [];

    foreach ($rows as $row) {
        if (!isset($row['value'])) {
            continue;
        }

        $value = (int) $row['value'];
        if ($lowestValue === null) {
            $lowestValue = $value;
        }

        if ($value !== $lowestValue) {
            break;
        }

        $createdAt = null;
        if (!empty($row['first_created_at'])) {
            try {
                $createdAt = (new DateTimeImmutable($row['first_created_at'], new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
            } catch (Exception $e) {
                $createdAt = null;
            }
        }

        $results[] = [
            'playerId'    => isset($row['player_id']) ? (int) $row['player_id'] : null,
            'value'       => $value,
            'createdAt'   => $createdAt,
            'firstBidId'  => isset($row['first_bid_id']) ? (int) $row['first_bid_id'] : null,
        ];
    }

    return $results;
}
