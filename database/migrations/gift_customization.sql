
-- Occasions table
CREATE TABLE IF NOT EXISTS occasions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Gift categories table
CREATE TABLE IF NOT EXISTS gift_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT,
    icon_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES gift_categories(id) ON DELETE SET NULL
);

-- Customization options table
CREATE TABLE IF NOT EXISTS customization_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('text', 'color', 'font', 'image', 'size') NOT NULL,
    description TEXT,
    additional_cost DECIMAL(10,2) DEFAULT 0.00,
    max_length INT,
    allowed_values JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Product customization options mapping
CREATE TABLE IF NOT EXISTS product_customization_options (
    product_id INT NOT NULL,
    option_id INT NOT NULL,
    is_required BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, option_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES customization_options(id) ON DELETE CASCADE
);

-- Product occasions mapping
CREATE TABLE IF NOT EXISTS product_occasions (
    product_id INT NOT NULL,
    occasion_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, occasion_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (occasion_id) REFERENCES occasions(id) ON DELETE CASCADE
);

-- Order customizations table
CREATE TABLE IF NOT EXISTS order_customizations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_item_id INT NOT NULL,
    option_id INT NOT NULL,
    value TEXT NOT NULL,
    additional_cost DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES customization_options(id) ON DELETE RESTRICT
);

-- Add new columns to products table
ALTER TABLE products
ADD COLUMN is_gift BOOLEAN DEFAULT FALSE,
ADD COLUMN gift_category_id INT,
ADD FOREIGN KEY (gift_category_id) REFERENCES gift_categories(id) ON DELETE SET NULL;

-- Add new columns to orders table
ALTER TABLE orders
ADD COLUMN occasion_id INT,
ADD COLUMN gift_message TEXT,
ADD COLUMN is_gift_wrapped BOOLEAN DEFAULT FALSE,
ADD COLUMN gift_wrap_cost DECIMAL(10,2) DEFAULT 0.00,
ADD FOREIGN KEY (occasion_id) REFERENCES occasions(id) ON DELETE SET NULL;
