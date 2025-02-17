ALTER TABLE orders
ADD COLUMN shipping_method_id int(11) AFTER shipping_method,
ADD COLUMN shipping_region_id int(11) AFTER shipping_method_id,
ADD FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods(id),
ADD FOREIGN KEY (shipping_region_id) REFERENCES shipping_regions(id);

-- Update existing orders with default values if needed
UPDATE orders
SET shipping_method_id = (SELECT id FROM shipping_methods WHERE name = 'standard' LIMIT 1),
    shipping_region_id = (SELECT id FROM shipping_regions WHERE name = 'Nairobi CBD' LIMIT 1)
WHERE shipping_method_id IS NULL;
