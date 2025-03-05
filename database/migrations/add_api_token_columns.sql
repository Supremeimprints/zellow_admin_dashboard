ALTER TABLE users
ADD COLUMN api_token VARCHAR(255) NULL,
ADD COLUMN token_expiry DATETIME NULL,
ADD COLUMN last_login DATETIME NULL,
ADD INDEX idx_api_token (api_token),
ADD INDEX idx_token_expiry (token_expiry);
