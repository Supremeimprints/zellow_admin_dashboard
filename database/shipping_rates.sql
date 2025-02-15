CREATE TABLE shipping_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shipping_method VARCHAR(50) NOT NULL,
    base_rate DECIMAL(10,2) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO shipping_rates (shipping_method, base_rate, description) VALUES 
('Standard', 300.00, '3-5 business days'),
('Express', 500.00, '2 business days'),
('Next Day', 800.00, 'Next business day delivery');
