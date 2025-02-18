
-- Add received_quantity to purchase_order_items
ALTER TABLE purchase_order_items
ADD COLUMN received_quantity int(11) DEFAULT 0,
ADD COLUMN notes text DEFAULT NULL;

-- Update purchase_orders status enum to include partial
ALTER TABLE purchase_orders 
MODIFY COLUMN status ENUM('pending', 'approved', 'partial', 'received', 'cancelled') 
DEFAULT 'pending';
