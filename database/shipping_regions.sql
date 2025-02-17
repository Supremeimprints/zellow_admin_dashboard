CREATE TABLE IF NOT EXISTS `shipping_regions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `region_shipping_rates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `region_id` int(11) NOT NULL,
    `shipping_method_id` int(11) NOT NULL,
    `base_rate` decimal(10,2) NOT NULL,
    `per_item_fee` decimal(10,2) DEFAULT 0.00,
    PRIMARY KEY (`id`),
    UNIQUE KEY `region_method` (`region_id`, `shipping_method_id`),
    FOREIGN KEY (`region_id`) REFERENCES `shipping_regions` (`id`),
    FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`)
);
