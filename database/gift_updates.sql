
-- Add gift-related fields to orders table
ALTER TABLE orders
ADD COLUMN is_gift BOOLEAN DEFAULT FALSE,
ADD COLUMN gift_wrap_style VARCHAR(50) NULL,
ADD COLUMN gift_message TEXT NULL,
ADD COLUMN gift_wrap_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN recipient_email VARCHAR(255) NULL,
ADD COLUMN notify_recipient BOOLEAN DEFAULT FALSE;

-- Create gift occasions table
CREATE TABLE gift_occasions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert some default occasions
INSERT INTO gift_occasions (name, description) VALUES
('Birthday', 'Birthday celebration gift'),
('Anniversary', 'Anniversary celebration gift'),
('Wedding', 'Wedding celebration gift'),
('Christmas', 'Christmas holiday gift'),
('Valentine''s Day', 'Valentine''s Day gift'),
('Mother''s Day', 'Mother''s Day gift'),
('Father''s Day', 'Father''s Day gift'),
('Graduation', 'Graduation celebration gift'),
('Thank You', 'Thank you gift'),
('Other', 'Other occasion');

-- Create gift_wrap_styles table
CREATE TABLE gift_wrap_styles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default gift wrap styles
INSERT INTO gift_wrap_styles (name, description, price) VALUES
('Basic', 'Simple gift wrapping with basic paper', 100.00),
('Premium', 'Premium wrapping with ribbon and bow', 200.00),
('Luxury', 'Luxury gift box with premium wrapping and accessories', 500.00);

-- Create order_gifts table for more detailed gift information
CREATE TABLE order_gifts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    occasion_id INT NULL,
    gift_wrap_style_id INT NULL,
    gift_message TEXT NULL,
    recipient_name VARCHAR(255) NULL,
    recipient_email VARCHAR(255) NULL,
    notify_recipient BOOLEAN DEFAULT FALSE,
    gift_status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (occasion_id) REFERENCES gift_occasions(id),
    FOREIGN KEY (gift_wrap_style_id) REFERENCES gift_wrap_styles(id)
);

-- Add index for faster gift-related queries
CREATE INDEX idx_orders_is_gift ON orders(is_gift);
CREATE INDEX idx_order_gifts_order_id ON order_gifts(order_id);

-- Add gift notification templates table
CREATE TABLE gift_notification_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    occasion_id INT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (occasion_id) REFERENCES gift_occasions(id)
);

-- Insert default notification template
INSERT INTO gift_notification_templates (subject, body, is_active) VALUES
('You''ve Received a Gift!', 'Dear {{recipient_name}},\n\nYou have received a gift from {{sender_name}}!\n\nMessage: {{gift_message}}\n\nYour gift will be delivered to: {{shipping_address}}\n\nTracking Number: {{tracking_number}}\n\nBest regards,\nThe Gift Team', TRUE);

-- Optional: Create a view for gift orders
CREATE VIEW view_gift_orders AS
SELECT 
    o.order_id,
    o.id AS customer_id,
    o.email,
    o.username,
    o.is_gift,
    o.gift_wrap_style,
    o.gift_wrap_cost,
    og.occasion_id,
    og.gift_message,
    og.recipient_email,
    og.notify_recipient,
    og.gift_status,
    go.name AS occasion_name,
    gws.name AS wrap_style_name,
    gws.price AS wrap_style_price
FROM orders o
LEFT JOIN order_gifts og ON o.order_id = og.order_id
LEFT JOIN gift_occasions go ON og.occasion_id = go.id
LEFT JOIN gift_wrap_styles gws ON og.gift_wrap_style_id = gws.id
WHERE o.is_gift = TRUE;

-- Add triggers to maintain gift costs
DELIMITER //
CREATE TRIGGER before_order_gift_insert
BEFORE INSERT ON order_gifts
FOR EACH ROW
BEGIN
    DECLARE wrap_cost DECIMAL(10,2);
    
    IF NEW.gift_wrap_style_id IS NOT NULL THEN
        SELECT price INTO wrap_cost
        FROM gift_wrap_styles
        WHERE id = NEW.gift_wrap_style_id;
        
        UPDATE orders 
        SET gift_wrap_cost = wrap_cost
        WHERE order_id = NEW.order_id;
    END IF;
END;
//
DELIMITER ;
