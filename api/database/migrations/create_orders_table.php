<?php
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Create orders table with combined fields
    $query = "CREATE TABLE IF NOT EXISTS orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(50) UNIQUE NOT NULL,
        id INT NOT NULL,  // Changed from user_id
        driver_id INT,
        technician_id INT,
        service_id INT,
        product_id INT,
        quantity INT,
        email VARCHAR(255) NOT NULL,
        username VARCHAR(50),
        total_amount DECIMAL(10,2) NOT NULL,
        discount_amount DECIMAL(10,2) DEFAULT 0.00,
        shipping_fee DECIMAL(10,2) DEFAULT 0.00,
        status ENUM(
            'Pending',
            'Processing',
            'Approved',
            'Assigned_Technician',
            'In_Progress',
            'Completed',
            'Approved_Completion',
            'Assigned_Driver',
            'Out_For_Delivery',
            'Delivered',
            'Cancelled'
        ) NOT NULL DEFAULT 'Pending',
        shipping_address TEXT NOT NULL,
        tracking_number VARCHAR(255),
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        delivery_date TIMESTAMP,
        scheduled_date DATE,
        scheduled_time TIME,
        payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') DEFAULT 'Pending',
        payment_method ENUM('Mpesa', 'Airtel money', 'Credit Card', 'Cash On Delivery') DEFAULT 'Mpesa',
        transaction_id VARCHAR(100),
        shipping_method VARCHAR(255),
        shipping_method_id INT,
        shipping_region_id INT,
        coupon_id INT,
        occasion_id INT,
        is_gift TINYINT(1) DEFAULT 0,
        gift_message TEXT,
        is_gift_wrapped TINYINT(1) DEFAULT 0,
        gift_wrap_cost DECIMAL(10,2) DEFAULT 0.00,
        gift_wrap_style VARCHAR(50),
        recipient_email VARCHAR(255),
        recipient_name VARCHAR(255),
        notify_recipient TINYINT(1) DEFAULT 0,
        customization_type ENUM('engraving', 'printing'),
        customization_details TEXT,
        customization_cost DECIMAL(10,2) DEFAULT 0.00,
        notes TEXT,
        created_by INT NOT NULL,
        updated_by INT,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id) REFERENCES users(id),  // Updated foreign key
        FOREIGN KEY (driver_id) REFERENCES users(id),
        FOREIGN KEY (technician_id) REFERENCES users(id),
        FOREIGN KEY (created_by) REFERENCES users(id),
        FOREIGN KEY (updated_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($query);

    // Update order_status_history table
    $query = "CREATE TABLE IF NOT EXISTS order_status_history (
        history_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        status ENUM(
            'Pending',
            'Processing',
            'Approved',
            'Assigned_Technician',
            'In_Progress',
            'Completed',
            'Approved_Completion',
            'Assigned_Driver',
            'Out_For_Delivery',
            'Delivered',
            'Cancelled'
        ) NOT NULL,
        payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL,
        updated_by INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(order_id),
        FOREIGN KEY (updated_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($query);

    echo "Tables created successfully";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
