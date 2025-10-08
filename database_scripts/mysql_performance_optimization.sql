-- Performance Optimization Script for Ricochet Robot
-- Add indexes for common query patterns

-- 1. Add composite indexes for common queries
-- For room lookups by code (most frequent)
SET @room_code_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rooms'
    AND index_name = 'idx_rooms_code'
);
SET @sql := IF(@room_code_idx = 0, 'CREATE INDEX idx_rooms_code ON rooms(code);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For rounds by room and status (very common)
SET @rounds_room_status_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_room_status'
);
SET @sql := IF(@rounds_room_status_idx = 0, 'CREATE INDEX idx_rounds_room_status ON rounds(room_id, status);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For rounds by room and creation time (for ordering)
SET @rounds_room_created_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_room_created'
);
SET @sql := IF(@rounds_room_created_idx = 0, 'CREATE INDEX idx_rounds_room_created ON rounds(room_id, created_at);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For bids by round and player (common lookups)
SET @bids_round_player_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'bids'
    AND index_name = 'idx_bids_round_player'
);
SET @sql := IF(@bids_round_player_idx = 0, 'CREATE INDEX idx_bids_round_player ON bids(round_id, player_id);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For players by room and name (unique constraint already exists, but add for performance)
SET @players_room_name_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'players'
    AND index_name = 'idx_players_room_name'
);
SET @sql := IF(@players_room_name_idx = 0, 'CREATE INDEX idx_players_room_name ON players(room_id, display_name);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For room_players by room (common lookups)
SET @room_players_room_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'room_players'
    AND index_name = 'idx_room_players_room'
);
SET @sql := IF(@room_players_room_idx = 0, 'CREATE INDEX idx_room_players_room ON room_players(room_id);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For target_chips by room and drawn status (common filtering)
SET @target_chips_room_drawn_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'target_chips'
    AND index_name = 'idx_target_chips_room_drawn'
);
SET @sql := IF(@target_chips_room_drawn_idx = 0, 'CREATE INDEX idx_target_chips_room_drawn ON target_chips(room_id, is_drawn);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add indexes for JSON field queries (if supported)
-- Note: These may not work on all MySQL versions
-- CREATE INDEX IF NOT EXISTS idx_rounds_verifying_queue ON rounds((CAST(verifying_queue_json AS CHAR(255) ARRAY)));

-- 3. Add indexes for datetime queries
SET @rounds_bidding_ends_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_bidding_ends'
);
SET @sql := IF(@rounds_bidding_ends_idx = 0, 'CREATE INDEX idx_rounds_bidding_ends ON rounds(bidding_ends_at);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @rounds_countdown_started_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_countdown_started'
);
SET @sql := IF(@rounds_countdown_started_idx = 0, 'CREATE INDEX idx_rounds_countdown_started ON rounds(countdown_started_at);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @rounds_ended_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_ended'
);
SET @sql := IF(@rounds_ended_idx = 0, 'CREATE INDEX idx_rounds_ended ON rounds(ended_at);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add indexes for foreign key lookups
-- Note: host_player_id is in rooms table, not rounds table

-- For rounds winner_player_id (exists in rounds table)
SET @rounds_winner_player_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_winner_player'
);
SET @sql := IF(@rounds_winner_player_idx = 0, 'CREATE INDEX idx_rounds_winner_player ON rounds(winner_player_id);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For rounds current_low_bidder_player_id (exists in rounds table)
SET @rounds_current_bidder_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_current_bidder'
);
SET @sql := IF(@rounds_current_bidder_idx = 0, 'CREATE INDEX idx_rounds_current_bidder ON rounds(current_low_bidder_player_id);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- For rooms host_player_id (exists in rooms table)
SET @rooms_host_player_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rooms'
    AND index_name = 'idx_rooms_host_player'
);
SET @sql := IF(@rooms_host_player_idx = 0, 'CREATE INDEX idx_rooms_host_player ON rooms(host_player_id);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Optimize InnoDB settings (run these separately if you have admin access)
-- SET GLOBAL innodb_buffer_pool_size = 128M;  -- Adjust based on available memory
-- SET GLOBAL innodb_log_file_size = 32M;      -- Adjust based on write load
-- SET GLOBAL innodb_flush_log_at_trx_commit = 2; -- Faster commits, less safe

-- 6. Analyze tables to update statistics
ANALYZE TABLE rooms;
ANALYZE TABLE rounds;
ANALYZE TABLE players;
ANALYZE TABLE room_players;
ANALYZE TABLE bids;
ANALYZE TABLE target_chips;

-- 7. Show final index status
SELECT 
    table_name,
    index_name,
    column_name,
    seq_in_index,
    non_unique,
    index_type
FROM information_schema.statistics 
WHERE table_schema = DATABASE()
    AND table_name IN ('rooms', 'rounds', 'players', 'room_players', 'bids', 'target_chips')
ORDER BY table_name, index_name, seq_in_index;
