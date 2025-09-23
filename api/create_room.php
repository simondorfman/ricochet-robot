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
if ($raw !== false && trim($raw) !== '') {
    $payload = json_decode(trim($raw), true);
    if (!is_array($payload) && $payload !== null) {
        respondJson(400, ['error' => 'Invalid JSON payload.']);
    }
    // hostPlayerName support removed; join endpoint now handles host assignment.
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
