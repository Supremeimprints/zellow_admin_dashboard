
-- Add gift-related columns to orders table
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS is_gift TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS gift_wrap_style VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS gift_message TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS gift_wrap_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS notify_recipient TINYINT(1) DEFAULT 0;

-- Add customization-related columns to orders table
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS customization_type VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS customization_details TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS customization_cost DECIMAL(10,2) DEFAULT 0.00;

-- Add customization columns to order_items table
ALTER TABLE order_items
ADD COLUMN IF NOT EXISTS customization_type VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS customization_details TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS customization_cost DECIMAL(10,2) DEFAULT 0.00;

-- Create service_requests table if not exists
CREATE TABLE IF NOT EXISTS service_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    service_type VARCHAR(50) NOT NULL,
    details TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
);

-- Create technicians table if not exists
CREATE TABLE IF NOT EXISTS technicians (
    technician_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    specialization ENUM('engraving', 'printing', 'both') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create technician_assignments table if not exists
CREATE TABLE IF NOT EXISTS technician_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    service_request_id INT,
    technician_id INT NOT NULL,
    order_id INT NOT NULL,
    customization_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (service_request_id) REFERENCES service_requests(id),
    FOREIGN KEY (technician_id) REFERENCES technicians(technician_id),
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
);
