-- Add demonstration moves tracking to rounds table
ALTER TABLE rounds 
ADD COLUMN demonstration_moves_json JSON NULL COMMENT 'Stores the sequence of moves for the current demonstration',
ADD COLUMN demonstration_current_move_index INT NOT NULL DEFAULT 0 COMMENT 'Current move index being demonstrated',
ADD COLUMN demonstration_player_id INT NULL COMMENT 'Player ID of the current demonstrator';
