
-- Create supplier statistics table
CREATE TABLE supplier_stats (
    supplier_id INT PRIMARY KEY,
    active_orders INT DEFAULT 0,
    total_ordered INT DEFAULT 0,
    total_received INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
);

-- Initialize stats for existing suppliers
INSERT INTO supplier_stats (supplier_id, active_orders, total_ordered, total_received)
SELECT 
    s.supplier_id,
    COUNT(DISTINCT CASE WHEN po.is_fulfilled = FALSE THEN po.purchase_order_id END) as active_orders,
    COALESCE(SUM(CASE WHEN po.is_fulfilled = FALSE THEN poi.quantity END), 0) as total_ordered,
    COALESCE(SUM(CASE WHEN po.is_fulfilled = FALSE THEN poi.received_quantity END), 0) as total_received
FROM suppliers s
LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
LEFT JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
GROUP BY s.supplier_id;

-- Create trigger to maintain supplier stats
DELIMITER //

CREATE TRIGGER after_po_item_update
AFTER UPDATE ON purchase_order_items
FOR EACH ROW
BEGIN
    DECLARE v_supplier_id INT;
    
    -- Get supplier ID from purchase order
    SELECT supplier_id INTO v_supplier_id
    FROM purchase_orders
    WHERE purchase_order_id = NEW.purchase_order_id;
    
    -- Update supplier stats
    INSERT INTO supplier_stats (supplier_id, active_orders, total_ordered, total_received)
    VALUES (v_supplier_id, 0, 0, 0)
    ON DUPLICATE KEY UPDATE
        active_orders = (
            SELECT COUNT(DISTINCT po.purchase_order_id)
            FROM purchase_orders po
            WHERE po.supplier_id = v_supplier_id AND po.is_fulfilled = FALSE
        ),
        total_ordered = (
            SELECT COALESCE(SUM(poi.quantity), 0)
            FROM purchase_orders po
            JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
            WHERE po.supplier_id = v_supplier_id AND po.is_fulfilled = FALSE
        ),
        total_received = (
            SELECT COALESCE(SUM(poi.received_quantity), 0)
            FROM purchase_orders po
            JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
            WHERE po.supplier_id = v_supplier_id AND po.is_fulfilled = FALSE
        );
END;
//

CREATE TRIGGER after_po_status_update
AFTER UPDATE ON purchase_orders
FOR EACH ROW
BEGIN
    -- Update supplier stats when order status changes
    INSERT INTO supplier_stats (supplier_id, active_orders, total_ordered, total_received)
    VALUES (NEW.supplier_id, 0, 0, 0)
    ON DUPLICATE KEY UPDATE
        active_orders = (
            SELECT COUNT(DISTINCT purchase_order_id)
            FROM purchase_orders
            WHERE supplier_id = NEW.supplier_id AND is_fulfilled = FALSE
        ),
        total_ordered = (
            SELECT COALESCE(SUM(poi.quantity), 0)
            FROM purchase_orders po
            JOIN purchase_order_items poi ON po.purchase_order_id = po.purchase_order_id
            WHERE po.supplier_id = NEW.supplier_id AND po.is_fulfilled = FALSE
        ),
        total_received = (
            SELECT COALESCE(SUM(poi.received_quantity), 0)
            FROM purchase_orders po
            JOIN purchase_order_items poi ON po.purchase_order_id = po.purchase_order_id
            WHERE po.supplier_id = NEW.supplier_id AND po.is_fulfilled = FALSE
        );
END;
//

DELIMITER ;
