-- Add target chip management system
-- This migration adds support for target chip stacks and drawing

-- Target chips table to store all available target chips
CREATE TABLE IF NOT EXISTS target_chips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    row_pos INT NOT NULL,
    col_pos INT NOT NULL,
    is_drawn BOOLEAN DEFAULT FALSE,
    drawn_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_room_drawn (room_id, is_drawn)
);

-- Add target chip fields to rounds table
ALTER TABLE rounds 
ADD COLUMN target_chip_id INT NULL,
ADD COLUMN target_chips_drawn INT DEFAULT 0,
ADD FOREIGN KEY (target_chip_id) REFERENCES target_chips(id);

-- Add index for target chip lookups
CREATE INDEX idx_rounds_target_chip ON rounds(target_chip_id);
