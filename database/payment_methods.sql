CREATE TABLE payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    method_type ENUM('Mpesa', 'Airtel money', 'Credit Card', 'Cash On Delivery') NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    account_number VARCHAR(50),
    phone_number VARCHAR(15),
    provider_reference VARCHAR(100),
    card_last_four VARCHAR(4),
    expiry_date DATE,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    usage_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_default_per_user (user_id, is_default),
    UNIQUE KEY unique_method_per_user (user_id, method_type, account_number)
);

ALTER TABLE orders 
ADD COLUMN payment_method_id INT,
ADD FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id);
