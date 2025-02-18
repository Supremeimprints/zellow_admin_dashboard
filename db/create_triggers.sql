
-- Trigger to update inventory stats when receiving goods
DELIMITER //

CREATE TRIGGER after_receive_goods
AFTER UPDATE ON purchase_order_items
FOR EACH ROW
BEGIN
    -- Update inventory quantity
    IF NEW.received_quantity > OLD.received_quantity THEN
        UPDATE inventory
        SET 
            stock_quantity = stock_quantity + (NEW.received_quantity - OLD.received_quantity),
            last_restocked = NOW()
        WHERE product_id = NEW.product_id;
    END IF;
    
    -- Update order fulfillment status
    IF (
        SELECT SUM(received_quantity) >= SUM(quantity)
        FROM purchase_order_items
        WHERE purchase_order_id = NEW.purchase_order_id
    ) THEN
        UPDATE purchase_orders
        SET 
            status = 'received',
            is_fulfilled = TRUE
        WHERE purchase_order_id = NEW.purchase_order_id;
    END IF;
END;
//

DELIMITER ;
