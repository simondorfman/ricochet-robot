ALTER TABLE rounds
  ADD COLUMN state_version INT NOT NULL DEFAULT 0,
  ADD COLUMN bidding_ends_at DATETIME NULL;

SET @room_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_room'
);
SET @sql := IF(@room_idx = 0, 'CREATE INDEX idx_rounds_room ON rounds(room_id);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @status_idx := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'rounds'
    AND index_name = 'idx_rounds_status'
);
SET @sql := IF(@status_idx = 0, 'CREATE INDEX idx_rounds_status ON rounds(status);', 'DO 0;');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
