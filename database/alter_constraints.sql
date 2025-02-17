
-- Drop existing foreign key constraints
ALTER TABLE region_shipping_rates
DROP FOREIGN KEY region_shipping_rates_ibfk_1;

-- Recreate the foreign key with CASCADE
ALTER TABLE region_shipping_rates
ADD CONSTRAINT region_shipping_rates_ibfk_1
FOREIGN KEY (region_id) REFERENCES shipping_regions(id)
ON DELETE CASCADE;

-- Also update the orders table constraints
ALTER TABLE orders
DROP FOREIGN KEY orders_ibfk_2;

ALTER TABLE orders
ADD CONSTRAINT orders_ibfk_2
FOREIGN KEY (shipping_region_id) REFERENCES shipping_regions(id)
ON DELETE SET NULL;
