<?php

// Set the config path for DreamHost
putenv('RR_CONFIG_PATH=/home/dh_uh9fn5/secure/rr-config.php');

require __DIR__ . '/api/db.php';

try {
    $pdo = db();
    
    echo "=== Checking target_chips table structure ===\n\n";
    
    // Check if target_chips table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'target_chips'");
    if ($stmt->rowCount() === 0) {
        echo "❌ target_chips table does not exist!\n";
        echo "Run migration: https://rr.simondorfman.com/run_migration.php?run=migrate\n";
        exit(1);
    }
    
    echo "✅ target_chips table exists\n";
    
    // Check table structure
    $stmt = $pdo->query('DESCRIBE target_chips');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nTable structure:\n";
    $hasDrawnAt = false;
    foreach ($columns as $col) {
        echo "- {$col['Field']}: {$col['Type']}\n";
        if ($col['Field'] === 'drawn_at') {
            $hasDrawnAt = true;
        }
    }
    
    if (!$hasDrawnAt) {
        echo "\n❌ Missing 'drawn_at' column!\n";
        echo "Run migration: https://rr.simondorfman.com/run_migration.php?run=migrate\n";
        exit(1);
    }
    
    echo "\n✅ All required columns exist\n";
    echo "The 'Start Game' button should work now!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
