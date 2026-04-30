-- Add API credentials to users table
ALTER TABLE users ADD COLUMN api_client_id VARCHAR(32) UNIQUE AFTER updated_at;
ALTER TABLE users ADD COLUMN api_key VARCHAR(64) UNIQUE AFTER api_client_id;

-- Index for faster API authentication
CREATE INDEX idx_user_api ON users(api_client_id, api_key);
