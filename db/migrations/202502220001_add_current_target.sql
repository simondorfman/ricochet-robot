-- Add current target to rounds table
ALTER TABLE rounds ADD COLUMN current_target_json JSON NULL;
