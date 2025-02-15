
-- System Settings Table
CREATE TABLE `system_settings` (
    `setting_id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `setting_group` varchar(50) NOT NULL DEFAULT 'general',
    `is_public` tinyint(1) DEFAULT 0,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_id`),
    UNIQUE KEY `unique_setting` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment Gateways Configuration
CREATE TABLE `payment_gateways` (
    `gateway_id` int(11) NOT NULL AUTO_INCREMENT,
    `gateway_name` varchar(50) NOT NULL,
    `gateway_code` varchar(50) NOT NULL,
    `is_enabled` tinyint(1) DEFAULT 0,
    `config` json,
    `sandbox_mode` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gateway_id`),
    UNIQUE KEY `unique_gateway` (`gateway_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shipping Zones Table
CREATE TABLE `shipping_zones` (
    `zone_id` int(11) NOT NULL AUTO_INCREMENT,
    `zone_name` varchar(100) NOT NULL,
    `zone_regions` json NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key to existing shipping_rates table
ALTER TABLE `shipping_rates` 
ADD COLUMN `zone_id` int(11) DEFAULT NULL AFTER `id`,
ADD CONSTRAINT `fk_shipping_zone` 
FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`zone_id`) 
ON DELETE SET NULL;

-- Insert default data
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('google_ads_id', '', 'marketing'),
('google_analytics_id', '', 'marketing'),
('facebook_pixel_id', '', 'marketing'),
('currency', 'KSH', 'general'),
('store_email', '', 'general'),
('store_phone', '', 'general');

INSERT INTO `payment_gateways` (`gateway_name`, `gateway_code`, `config`) VALUES
('Stripe', 'stripe', '{"public_key": "", "secret_key": "", "webhook_secret": ""}'),
('M-Pesa', 'mpesa', '{"consumer_key": "", "consumer_secret": "", "passkey": "", "shortcode": ""}');

INSERT INTO `shipping_zones` (`zone_name`, `zone_regions`) VALUES
('Nairobi', '["Nairobi CBD", "Westlands", "Eastleigh"]'),
('Rest of Kenya', '["Other Regions"]');
