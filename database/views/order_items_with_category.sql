-- First check the categories table structure
DESC categories;

CREATE VIEW order_items_with_category AS
SELECT 
    oi.*,
    c.category_id,
    c.category_name,  -- Check if this matches your categories table column name
    p.product_name,
    p.category_id as product_category_id
FROM order_items oi
JOIN products p ON oi.product_id = p.product_id
LEFT JOIN categories c ON p.category_id = c.category_id;

-- If the above fails, try this version (uncomment after checking categories table structure):
/*
CREATE VIEW order_items_with_category AS
SELECT 
    oi.*,
    p.product_name,
    p.category_id
FROM order_items oi
JOIN products p ON oi.product_id = p.product_id;
*/
