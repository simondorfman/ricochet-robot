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
    
    // Initialize target chips for this room
    initializeTargetChips($roomId);

    // Generate robot positions
    try {
        $robotPositions = generateRobotPositions();
        $robotPositionsJson = json_encode($robotPositions);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to encode robot positions: ' . json_last_error_msg());
        }
        
        error_log("Generated robot positions: " . $robotPositionsJson);
    } catch (Exception $e) {
        error_log("Error generating robot positions: " . $e->getMessage());
        $robotPositionsJson = null;
    }
    
    $roundStmt = $pdo->prepare("INSERT INTO rounds (room_id, status, state_version, robot_positions_json, created_at) VALUES (:room_id, 'bidding', 0, :robot_positions, UTC_TIMESTAMP())");
    $roundStmt->execute([
        'room_id' => $roomId,
        'robot_positions' => $robotPositionsJson
    ]);
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

function generateRobotPositions(): array
{
    // Robot names in order (matching client-side ROBOT_ORDER)
    $robotOrder = ['Purple', 'Cyan', 'Lime', 'Yellow'];
    
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
