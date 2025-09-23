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
];

foreach ($statements as $sql) {
    $pdo->exec($sql);
}

fwrite(STDOUT, "Database schema ensured.\n");
