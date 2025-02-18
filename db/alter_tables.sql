
-- Add received_quantity to purchase_order_items
ALTER TABLE purchase_order_items
ADD COLUMN received_quantity int(11) DEFAULT 0,
ADD COLUMN last_received_date datetime DEFAULT NULL;

-- Add amount_paid to invoices
ALTER TABLE invoices
ADD COLUMN amount_paid decimal(10,2) DEFAULT 0.00,
ADD COLUMN purchase_order_id int(11) DEFAULT NULL,
ADD FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(purchase_order_id);

-- Add payment tracking to purchase_orders
ALTER TABLE purchase_orders 
MODIFY COLUMN status ENUM('pending', 'partial', 'received', 'cancelled') DEFAULT 'pending',
ADD COLUMN payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid';
