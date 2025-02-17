ALTER TABLE shipping_zones
MODIFY COLUMN zone_regions TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
DROP CONSTRAINT IF EXISTS `shipping_zones.zone_regions`;
