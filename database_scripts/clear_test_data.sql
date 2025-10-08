-- Clear all test data from Ricochet Robot tables
-- WARNING: This will delete ALL data! Only run on development/test database

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Clear all tables in dependency order (using DELETE instead of TRUNCATE)
DELETE FROM bids;
DELETE FROM target_chips;
DELETE FROM rounds;
DELETE FROM room_players;
DELETE FROM players;
DELETE FROM rooms;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Reset auto-increment counters
ALTER TABLE rooms AUTO_INCREMENT = 1;
ALTER TABLE rounds AUTO_INCREMENT = 1;
ALTER TABLE players AUTO_INCREMENT = 1;
ALTER TABLE room_players AUTO_INCREMENT = 1;
ALTER TABLE bids AUTO_INCREMENT = 1;
ALTER TABLE target_chips AUTO_INCREMENT = 1;

-- Verify tables are empty
SELECT 'rooms' as table_name, COUNT(*) as row_count FROM rooms
UNION ALL
SELECT 'rounds', COUNT(*) FROM rounds
UNION ALL
SELECT 'players', COUNT(*) FROM players
UNION ALL
SELECT 'room_players', COUNT(*) FROM room_players
UNION ALL
SELECT 'bids', COUNT(*) FROM bids
UNION ALL
SELECT 'target_chips', COUNT(*) FROM target_chips;
