
-- Create customer_details table if it doesn't exist
CREATE TABLE IF NOT EXISTS customer_details (
    id INT NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Kenya',
    postal_code VARCHAR(20),
    phone_alternative VARCHAR(15),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better performance
CREATE INDEX idx_customer_city ON customer_details(city);
CREATE INDEX idx_customer_country ON customer_details(country);
CREATE INDEX idx_customer_created ON customer_details(created_at);

-- Add customer role to users table if not exists
INSERT IGNORE INTO roles (role_name, description) 
VALUES ('customer', 'Regular customer account');

-- Update users table to ensure customer role support
ALTER TABLE users
ADD COLUMN IF NOT EXISTS customer_type ENUM('retail', 'wholesale', 'corporate') DEFAULT 'retail',
ADD COLUMN IF NOT EXISTS last_login DATETIME NULL,
ADD COLUMN IF NOT EXISTS login_count INT DEFAULT 0;
