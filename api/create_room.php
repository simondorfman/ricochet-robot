<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respondJson(405, ['error' => 'Method not allowed.']);
}

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
if ($code === '') {
    respondJson(400, ['error' => 'Missing room code.']);
}

$raw = file_get_contents('php://input');
$hostPlayerName = null;

if ($raw !== false) {
    $rawTrimmed = trim($raw);
    if ($rawTrimmed !== '') {
        $payload = json_decode($rawTrimmed, true);
        if (!is_array($payload)) {
            respondJson(400, ['error' => 'Invalid JSON payload.']);
        }

        if (array_key_exists('hostPlayerName', $payload) && is_string($payload['hostPlayerName'])) {
            $hostPlayerName = trim($payload['hostPlayerName']);
            if ($hostPlayerName === '') {
                $hostPlayerName = null;
            }
        }
    }
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT id FROM rooms WHERE code = :code LIMIT 1');
    $check->execute(['code' => $code]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if ($existing !== false) {
        $pdo->rollBack();
        respondJson(409, ['error' => 'Room exists']);
    }

    $insertRoom = $pdo->prepare('INSERT INTO rooms (code, created_at) VALUES (:code, UTC_TIMESTAMP())');
    $insertRoom->execute(['code' => $code]);
    $roomId = (int) $pdo->lastInsertId();

    $roundStmt = $pdo->prepare("INSERT INTO rounds (room_id, status, state_version, created_at) VALUES (:room_id, 'bidding', 0, UTC_TIMESTAMP())");
    $roundStmt->execute(['room_id' => $roomId]);
    $roundId = (int) $pdo->lastInsertId();

    if ($hostPlayerName !== null) {
        $maxPlayerId = (int) min(PHP_INT_MAX, 2147483647);
        $hostPlayerId = random_int(1, $maxPlayerId);

        $playerStmt = $pdo->prepare('INSERT INTO room_players (room_id, player_id, name, points, tokens_won) VALUES (:room_id, :player_id, :name, 0, 0)');
        $playerStmt->execute([
            'room_id'    => $roomId,
            'player_id'  => $hostPlayerId,
            'name'       => $hostPlayerName,
        ]);

        $updateHost = $pdo->prepare('UPDATE rooms SET host_player_id = :player_id WHERE id = :room_id');
        $updateHost->execute([
            'player_id' => $hostPlayerId,
            'room_id'   => $roomId,
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        respondJson(409, ['error' => 'Room exists']);
    }

    respondJson(500, ['error' => 'Unable to create room.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respondJson(500, ['error' => 'Unable to create room.']);
}

respondJson(200, [
    'ok'       => true,
    'roomCode' => $code,
    'roomId'   => $roomId,
    'roundId'  => $roundId,
]);

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
