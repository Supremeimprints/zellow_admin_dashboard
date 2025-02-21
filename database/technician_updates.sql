
-- Create technicians table if not exists
CREATE TABLE IF NOT EXISTS technicians (
    technician_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    specialization ENUM('engraving', 'printing', 'both') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create technician assignments table if not exists
CREATE TABLE IF NOT EXISTS technician_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    technician_id INT NOT NULL,
    customization_type ENUM('engraving', 'printing') NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (technician_id) REFERENCES technicians(technician_id)
);

-- Add customization fields to orders table
ALTER TABLE orders
ADD COLUMN customization_type ENUM('engraving', 'printing') NULL,
ADD COLUMN customization_details TEXT NULL;

-- Add indexes for better performance
CREATE INDEX idx_tech_assignments_status ON technician_assignments(status);
CREATE INDEX idx_tech_assignments_technician ON technician_assignments(technician_id);
