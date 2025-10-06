<?php

declare(strict_types=1);

/**
 * Generate random robot positions for a new game round.
 * 
 * @return array<string, array{row: int, col: int}>
 */
function generateRobotPositions(): array
{
    // Robot names in order (matching client-side ROBOT_ORDER)
    $robotOrder = ['Purple', 'Cyan', 'Lime', 'Yellow'];
    
    // Board size (16x16)
    $boardSize = 16;
    
    // Symbol positions (these would be the same for all games)
    // In a real implementation, these would be the actual symbol positions
    $symbolPositions = [
        // Add known symbol positions here - for now using empty array
        // TODO: Load actual symbol positions from game configuration
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
