
CREATE TABLE IF NOT EXISTS `shipping_methods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `base_rate` decimal(10,2) NOT NULL,
    `per_item_fee` decimal(10,2) DEFAULT 0.00,
    `free_shipping_threshold` decimal(10,2) DEFAULT NULL,
    `estimated_days` varchar(20) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default shipping methods
INSERT INTO `shipping_methods` 
    (name, display_name, base_rate, per_item_fee, free_shipping_threshold, estimated_days)
VALUES 
    ('standard', 'Standard Delivery (3-5 days)', 250.00, 50.00, 5000.00, '3-5'),
    ('express', 'Express Delivery (1-2 days)', 450.00, 75.00, 7500.00, '1-2'),
    ('next_day', 'Next Day Delivery', 750.00, 100.00, 10000.00, '1');
