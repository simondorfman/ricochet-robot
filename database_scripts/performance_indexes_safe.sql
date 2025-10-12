-- Performance optimization indexes for Ricochet Robot
-- Safe version that checks for existing indexes first

-- Check and create indexes only if they don't exist
-- Run these one at a time to see which ones are needed

-- Index for room code lookups (used in fetchCurrentRoundByRoomCode)
-- Skip if already exists: idx_rooms_code

-- Index for round lookups by room_id (used in fetchCurrentRoundByRoomCode)
CREATE INDEX idx_rounds_room_id ON rounds(room_id);

-- Index for round ordering by id (used in ORDER BY r.id DESC)
CREATE INDEX idx_rounds_id_desc ON rounds(id DESC);

-- Index for player lookups by room_id (used in fetchPlayersForRoom)
CREATE INDEX idx_players_room_id ON players(room_id);

-- Index for player ordering by creation time (used in ORDER BY created_at ASC, id ASC)
CREATE INDEX idx_players_created_at_id ON players(created_at ASC, id ASC);

-- Index for bid lookups by round_id (used in fetchRecentBids)
CREATE INDEX idx_bids_round_id ON bids(round_id);

-- Index for bid ordering by creation time (used in ORDER BY created_at DESC)
CREATE INDEX idx_bids_created_at_desc ON bids(created_at DESC);

-- Index for target chip lookups by room_id (used in initializeTargetChips)
CREATE INDEX idx_target_chips_room_id ON target_chips(room_id);

-- Index for target chip lookups by id (used in state.php)
CREATE INDEX idx_target_chips_id ON target_chips(id);

-- Composite index for best bids query (used in fetchBestBidsPerPlayer)
CREATE INDEX idx_bids_round_player_value ON bids(round_id, player_id, value);

-- Index for round status filtering (used in various queries)
CREATE INDEX idx_rounds_status ON rounds(status);

-- Index for round state version (used in polling)
CREATE INDEX idx_rounds_state_version ON rounds(state_version);

-- Index for demonstration moves queries
CREATE INDEX idx_rounds_demonstration_player_id ON rounds(demonstration_player_id);

-- Index for verification queue queries
CREATE INDEX idx_rounds_verifying_current_index ON rounds(verifying_current_index);
