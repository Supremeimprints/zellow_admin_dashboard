
-- First create shipping_regions table
CREATE TABLE IF NOT EXISTS `shipping_regions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Then create shipping_methods table
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

-- Create region_shipping_rates table
CREATE TABLE IF NOT EXISTS `region_shipping_rates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `region_id` int(11) NOT NULL,
    `shipping_method_id` int(11) NOT NULL,
    `base_rate` decimal(10,2) NOT NULL,
    `per_item_fee` decimal(10,2) DEFAULT 0.00,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_region_method` (`region_id`, `shipping_method_id`),
    FOREIGN KEY (`region_id`) REFERENCES `shipping_regions`(`id`),
    FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some default data
INSERT INTO `shipping_regions` (`name`, `description`) VALUES
('Nairobi CBD', 'Central Business District'),
('Westlands', 'Westlands and surrounding areas'),
('Eastlands', 'Eastern Nairobi regions');

INSERT INTO `shipping_methods` 
(`name`, `display_name`, `base_rate`, `per_item_fee`, `free_shipping_threshold`, `estimated_days`) VALUES
('standard', 'Standard Delivery (3-5 days)', 250.00, 50.00, 5000.00, '3-5'),
('express', 'Express Delivery (1-2 days)', 450.00, 75.00, 7500.00, '1-2'),
('next_day', 'Next Day Delivery', 750.00, 100.00, 10000.00, '1');

-- Insert shipping rates for each region
INSERT INTO `region_shipping_rates` 
(`region_id`, `shipping_method_id`, `base_rate`, `per_item_fee`) 
SELECT 
    r.id as region_id,
    m.id as shipping_method_id,
    m.base_rate,
    m.per_item_fee
FROM shipping_regions r
CROSS JOIN shipping_methods m;

-- Now alter orders table to add shipping method and region columns
ALTER TABLE orders
ADD COLUMN shipping_method_id int(11) NOT NULL AFTER shipping_method,
ADD COLUMN shipping_region_id int(11) NOT NULL AFTER shipping_method_id,
ADD FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods(id),
ADD FOREIGN KEY (shipping_region_id) REFERENCES shipping_regions(id);
