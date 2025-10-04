-- Add robot positions to rounds table
ALTER TABLE rounds ADD COLUMN robot_positions_json JSON NULL;

-- Add index for better performance
CREATE INDEX idx_rounds_robot_positions ON rounds(robot_positions_json);
