
function createOrder($db, $orderData) {
    try {
        $db->beginTransaction();

        // Insert order
        $orderQuery = "INSERT INTO orders (
            id, email, total_amount, status, shipping_address, 
            payment_status, payment_method, shipping_method_id
        ) VALUES (
            :id, :email, :total_amount, :status, :shipping_address,
            :payment_status, :payment_method, :shipping_method_id
        )";
        
        // ... order insertion code ...

        // Insert order items - Add category information through products join
        $itemQuery = "INSERT INTO order_items (
            order_id, product_id, quantity, unit_price, subtotal
        ) SELECT 
            :order_id,
            p.product_id,
            :quantity,
            p.price as unit_price,
            (p.price * :quantity) as subtotal
        FROM products p
        WHERE p.product_id = :product_id";

        // ... order items insertion code ...

        $db->commit();
        return true;

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Order creation error: ' . $e->getMessage());
        return false;
    }
}
