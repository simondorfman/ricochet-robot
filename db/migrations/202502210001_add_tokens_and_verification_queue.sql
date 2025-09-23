ALTER TABLE room_players
  ADD COLUMN tokens_won INT NOT NULL DEFAULT 0 AFTER points;

UPDATE room_players
SET tokens_won = points
WHERE tokens_won = 0;

ALTER TABLE rounds
  ADD COLUMN verifying_queue_json JSON NULL AFTER winner_player_id,
  ADD COLUMN verifying_current_index INT NOT NULL DEFAULT 0 AFTER verifying_queue_json;
