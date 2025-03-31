ALTER TABLE coupons
ADD COLUMN is_public BOOLEAN DEFAULT FALSE,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Create index for better performance
CREATE INDEX idx_coupon_code ON coupons(code);
CREATE INDEX idx_coupon_status_date ON coupons(status, expiration_date);
