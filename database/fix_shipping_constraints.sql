
-- First, remove orphaned records from region_shipping_rates
DELETE rsr FROM region_shipping_rates rsr
LEFT JOIN shipping_regions sr ON rsr.region_id = sr.id
WHERE sr.id IS NULL;

-- Drop existing foreign key if it exists
ALTER TABLE region_shipping_rates
DROP FOREIGN KEY IF EXISTS region_shipping_rates_ibfk_1;

-- Now add the CASCADE constraint
ALTER TABLE region_shipping_rates
ADD CONSTRAINT region_shipping_rates_ibfk_1
FOREIGN KEY (region_id) REFERENCES shipping_regions(id)
ON DELETE CASCADE;

-- Also fix orders table
UPDATE orders 
SET shipping_region_id = NULL
WHERE shipping_region_id NOT IN (SELECT id FROM shipping_regions);

-- Update orders constraint
ALTER TABLE orders
DROP FOREIGN KEY IF EXISTS orders_ibfk_2;

ALTER TABLE orders
ADD CONSTRAINT orders_ibfk_2
FOREIGN KEY (shipping_region_id) REFERENCES shipping_regions(id)
ON DELETE SET NULL;
