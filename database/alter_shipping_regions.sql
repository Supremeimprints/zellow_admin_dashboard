ALTER TABLE shipping_regions
ADD COLUMN zone_regions TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER description;
