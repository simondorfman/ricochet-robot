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
if ($action !== 'update' && $action !== 'start' && $action !== 'stop') {
    respondJson(400, ['error' => 'Invalid demonstration action.']);
}

$raw = file_get_contents('php://input');
if ($raw === false) {
    respondJson(400, ['error' => 'Missing request body.']);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    respondJson(400, ['error' => 'Invalid JSON payload.']);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $round = lockRoundForUpdateByRoomCode($code);
    if ($round === null) {
        $pdo->rollBack();
        respondJson(404, ['error' => 'Room not found or no active round.']);
    }

    if ($action === 'start') {
        // Start demonstration - store moves and reset index
        if (!array_key_exists('demonstrationMoves', $payload) || !is_array($payload['demonstrationMoves'])) {
            $pdo->rollBack();
            respondJson(400, ['error' => 'demonstrationMoves is required for start action.']);
        }

        $demonstrationMovesJson = json_encode($payload['demonstrationMoves']);
        $demonstrationPlayerId = isset($payload['playerId']) ? (int) $payload['playerId'] : null;

        $update = $pdo->prepare(
            "UPDATE rounds SET demonstration_moves_json = :demonstration_moves, demonstration_current_move_index = 0, demonstration_player_id = :demonstration_player_id WHERE id = :id"
        );
        $update->execute([
            'demonstration_moves' => $demonstrationMovesJson,
            'demonstration_player_id' => $demonstrationPlayerId,
            'id' => (int) $round['id'],
        ]);

        bumpVersion((int) $round['id']);
        $pdo->commit();

        respondJson(200, [
            'ok' => true,
            'result' => 'started',
            'totalMoves' => count($payload['demonstrationMoves'])
        ]);
    }

    if ($action === 'update') {
        // Add a new move to the demonstration
        if (!array_key_exists('move', $payload) || !is_array($payload['move'])) {
            $pdo->rollBack();
            respondJson(400, ['error' => 'move is required for update action.']);
        }

        $playerId = isset($payload['playerId']) ? (int) $payload['playerId'] : null;
        $moveIndex = isset($payload['moveIndex']) ? (int) $payload['moveIndex'] : 0;
        
        // Get current demonstration moves
        $currentMovesJson = $round['demonstration_moves_json'] ?? '[]';
        $currentMoves = json_decode($currentMovesJson, true);
        if (!is_array($currentMoves)) {
            $currentMoves = [];
        }
        
        // Add the new move
        $currentMoves[] = $payload['move'];
        $newMovesJson = json_encode($currentMoves);

        $update = $pdo->prepare(
            "UPDATE rounds SET demonstration_moves_json = :demonstration_moves, demonstration_current_move_index = :move_index, demonstration_player_id = :demonstration_player_id WHERE id = :id"
        );
        $update->execute([
            'demonstration_moves' => $newMovesJson,
            'move_index' => $moveIndex,
            'demonstration_player_id' => $playerId,
            'id' => (int) $round['id'],
        ]);

        bumpVersion((int) $round['id']);
        $pdo->commit();

        respondJson(200, [
            'ok' => true,
            'result' => 'updated',
            'moveIndex' => $moveIndex,
            'totalMoves' => count($currentMoves)
        ]);
    }

    if ($action === 'stop') {
        // Stop demonstration - clear moves
        $update = $pdo->prepare(
            "UPDATE rounds SET demonstration_moves_json = NULL, demonstration_current_move_index = 0, demonstration_player_id = NULL WHERE id = :id"
        );
        $update->execute([
            'id' => (int) $round['id'],
        ]);

        bumpVersion((int) $round['id']);
        $pdo->commit();

        respondJson(200, [
            'ok' => true,
            'result' => 'stopped'
        ]);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respondJson(500, ['error' => 'Unable to update demonstration status.']);
}

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
