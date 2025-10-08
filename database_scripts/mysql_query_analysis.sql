-- Query Analysis Script for Ricochet Robot
-- Identify slow queries and optimization opportunities

-- 1. Enable query profiling (temporary)
SET profiling = 1;

-- 2. Common queries to analyze (run these one by one and check results)

-- Query 1: Get room by code (most frequent)
-- SELECT * FROM rooms WHERE code = 'TEST123';

-- Query 2: Get current round for room
-- SELECT r.*, p.display_name as host_name 
-- FROM rounds r 
-- LEFT JOIN players p ON r.host_player_id = p.id 
-- WHERE r.room_id = 1 
-- ORDER BY r.created_at DESC 
-- LIMIT 1;

-- Query 3: Get all players in room
-- SELECT * FROM players WHERE room_id = 1 ORDER BY created_at;

-- Query 4: Get bids for current round
-- SELECT b.*, p.display_name, p.color 
-- FROM bids b 
-- JOIN players p ON b.player_id = p.id 
-- WHERE b.round_id = 1 
-- ORDER BY b.value ASC;

-- Query 5: Get verification queue
-- SELECT * FROM rounds WHERE room_id = 1 AND status = 'verifying';

-- Query 6: Count rounds for room (for puzzle counter)
-- SELECT COUNT(*) FROM rounds WHERE room_id = 1;

-- Query 7: Get target chips for room
-- SELECT * FROM target_chips WHERE room_id = 1 AND is_drawn = FALSE;

-- 3. After running queries, check profiling results
-- SHOW PROFILES;
-- SHOW PROFILE FOR QUERY 1;
-- SHOW PROFILE FOR QUERY 2;
-- etc.

-- 4. Check for missing indexes
-- EXPLAIN SELECT * FROM rooms WHERE code = 'TEST123';
-- EXPLAIN SELECT * FROM rounds WHERE room_id = 1 AND status = 'bidding';
-- EXPLAIN SELECT * FROM bids WHERE round_id = 1 ORDER BY value ASC;

-- 5. Check for table scans
-- EXPLAIN SELECT * FROM rounds WHERE room_id = 1;
-- EXPLAIN SELECT * FROM players WHERE room_id = 1;

-- 6. Disable profiling
-- SET profiling = 0;
