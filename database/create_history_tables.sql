
-- Order Status History Table
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `history_id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL,
    `payment_status` enum('Pending','Paid','Failed','Refunded') NOT NULL,
    `updated_by` int(11) NOT NULL,
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`history_id`),
    KEY `order_id` (`order_id`),
    KEY `updated_by` (`updated_by`),
    CONSTRAINT `status_history_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `status_history_user_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shipping Status History Table
CREATE TABLE IF NOT EXISTS `shipping_status_history` (
    `history_id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL,
    `driver_id` int(11) DEFAULT NULL,
    `tracking_number` varchar(255) DEFAULT NULL,
    `updated_by` int(11) NOT NULL,
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`history_id`),
    KEY `order_id` (`order_id`),
    KEY `driver_id` (`driver_id`),
    KEY `updated_by` (`updated_by`),
    CONSTRAINT `shipping_history_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `shipping_history_driver_fk` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`),
    CONSTRAINT `shipping_history_user_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dispatch History Table
CREATE TABLE IF NOT EXISTS `dispatch_history` (
    `history_id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `driver_id` int(11) NOT NULL,
    `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL,
    `tracking_number` varchar(255) DEFAULT NULL,
    `assigned_by` int(11) NOT NULL,
    `scheduled_delivery` timestamp NULL DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`history_id`),
    KEY `order_id` (`order_id`),
    KEY `driver_id` (`driver_id`),
    KEY `assigned_by` (`assigned_by`),
    CONSTRAINT `dispatch_history_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
    CONSTRAINT `dispatch_history_driver_fk` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`),
    CONSTRAINT `dispatch_history_user_fk` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
