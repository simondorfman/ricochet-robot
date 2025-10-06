<?php

declare(strict_types=1);

require __DIR__ . '/../api/db.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, 'Unable to connect to the database: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$statements = [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  host_player_id INT NULL,
  settings_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS rounds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  status ENUM('bidding','countdown','verifying','complete') NOT NULL,
  countdown_started_at DATETIME NULL,
  bidding_ends_at DATETIME NULL,
  countdown_secs INT DEFAULT 60,
  current_low_bid INT NULL,
  current_low_bidder_player_id INT NULL,
  winner_player_id INT NULL,
  verifying_queue_json JSON NULL,
  verifying_current_index INT NOT NULL DEFAULT 0,
  state_version INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  INDEX idx_rounds_room (room_id),
  INDEX idx_rounds_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS bids (
  id INT AUTO_INCREMENT PRIMARY KEY,
  round_id INT NOT NULL,
  player_id INT NOT NULL,
  value INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
  INDEX idx_bids_round (round_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  display_name VARCHAR(64) NOT NULL,
  color VARCHAR(16) NOT NULL,
  tokens_won INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_room_name (room_id, display_name),
  INDEX idx_players_room (room_id),
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS room_players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  player_id INT NOT NULL,
  name VARCHAR(255) NULL,
  points INT NOT NULL DEFAULT 0,
  tokens_won INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_room_player (room_id, player_id),
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS target_chips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  symbol VARCHAR(10) NOT NULL,
  row_pos INT NOT NULL,
  col_pos INT NOT NULL,
  is_drawn BOOLEAN NOT NULL DEFAULT FALSE,
  drawn_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
];

foreach ($statements as $sql) {
    $pdo->exec($sql);
}

// Add missing columns to existing tables
$alterStatements = [
    // Add robot_positions_json to rounds table if it doesn't exist
    "ALTER TABLE rounds ADD COLUMN robot_positions_json JSON NULL",
    // Add target_chip_id to rounds table if it doesn't exist
    "ALTER TABLE rounds ADD COLUMN target_chip_id INT NULL",
    // Add drawn_at to target_chips table if it doesn't exist  
    "ALTER TABLE target_chips ADD COLUMN drawn_at TIMESTAMP NULL",
];

foreach ($alterStatements as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // Ignore "Duplicate column name" errors - column already exists
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e;
        }
    }
}

// Database schema ensured - output handled by run_migration.php
