-- Add active column to orders table
ALTER TABLE orders ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;

-- Add active column to products table
ALTER TABLE products ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;

-- Add active column to gift_wrap_styles table if not exists
ALTER TABLE gift_wrap_styles ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;

-- Add active column to customization_options table if not exists
ALTER TABLE customization_options ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
