
-- Add is_fulfilled flag to purchase_orders to track completion
ALTER TABLE purchase_orders 
ADD COLUMN is_fulfilled BOOLEAN DEFAULT FALSE;

-- Add indexes for better performance
CREATE INDEX idx_po_status ON purchase_orders(status, is_fulfilled);
CREATE INDEX idx_poi_quantities ON purchase_order_items(purchase_order_id, quantity, received_quantity);
