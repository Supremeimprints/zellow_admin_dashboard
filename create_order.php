<?php
// Include database connection
include 'db_connection.php';

// Initialize messages array
$messages = [];

// Check if form is submitted to create an order
if (isset($_POST['create_order'])) {
    try {
        // Retrieve form inputs
        $username = $_POST['username'];
        $product_id = $_POST['product_id'];
        $total_amount = $_POST['total_amount'];
        $shippingAddress = $_POST['shipping_address'];
        $countyName = $_POST['county'];
        $townName = $_POST['town'];
        $villageName = $_POST['village'];
        $order_date = date('Y-m-d H:i:s');
        $status = 'pending';

        // Validate that all fields are filled
        if (empty($username) || empty($total_amount) || empty($shippingAddress) || empty($countyName) || empty($townName) || empty($villageName)) {
            throw new Exception("All fields are required.");
        }

        // Fetch county, town, and village names
        $countyQuery = "SELECT name FROM counties WHERE id = ?";
        $stmt = $pdo->prepare($countyQuery);
        $stmt->execute([$county]);
        $countyName = $stmt->fetchColumn();

        $townQuery = "SELECT name FROM towns WHERE id = ?";
        $stmt = $pdo->prepare($townQuery);
        $stmt->execute([$town]);
        $townName = $stmt->fetchColumn();

        $villageQuery = "SELECT name FROM villages WHERE id = ?";
        $stmt = $pdo->prepare($villageQuery);
        $stmt->execute([$village]);
        $villageName = $stmt->fetchColumn();

        // Combine them into one shipping address
        $shippingAddress = "$countyName, $townName, $villageName";

        // Fetch user ID from username
        $user_query = "SELECT id FROM users WHERE username = :username";
        $stmt = $pdo->prepare($user_query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("User not found. Please check the username.");
        }

        // Check if the product is available in stock
        $stock_query = "SELECT stock FROM products WHERE id = :product_id";
        $stmt = $pdo->prepare($stock_query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product && $product['stock'] > 0) {
            // Insert the order into the database
            $insert_query = "INSERT INTO orders (user_id, product_id, total_amount, shipping_address, order_date, status)
                           VALUES (:user_id, :product_id, :total_amount, :shipping_address, :order_date, :status)";
            $stmt = $pdo->prepare($insert_query);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':shipping_address', $shippingAddress);
            $stmt->bindParam(':order_date', $order_date);
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $messages[] = ["type" => "success", "text" => "Order successfully placed!"];
                
                // Update product stock
                $update_stock = "UPDATE products SET stock = stock - 1 WHERE id = :product_id";
                $stmt = $pdo->prepare($update_stock);
                $stmt->bindParam(':product_id', $product_id);
                $stmt->execute();
            } else {
                throw new Exception("Failed to place the order.");
            }
        } else {
            throw new Exception("The selected product is out of stock. Please choose another product.");
        }
    } catch (Exception $e) {
        $messages[] = ["type" => "error", "text" => $e->getMessage()];
    }
}

// Display all orders (Admin View)
$query = "SELECT o.id, u.username, o.total_amount, o.status, o.shipping_address, 
                 o.order_date, o.tracking_number, o.delivery_date, p.name AS product_name
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          LEFT JOIN products p ON o.product_id = p.id
          ORDER BY o.order_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle order update
if (isset($_POST['update_order'])) {
    try {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        $tracking_number = $_POST['tracking_number'];
        $delivery_date = $_POST['delivery_date'] ?: null;

        $update_query = "UPDATE orders 
                        SET status = :status, 
                            tracking_number = :tracking_number, 
                            delivery_date = :delivery_date 
                        WHERE id = :order_id";
        $stmt = $pdo->prepare($update_query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':tracking_number', $tracking_number);
        $stmt->bindParam(':delivery_date', $delivery_date);
        $stmt->bindParam(':order_id', $order_id);

        if ($stmt->execute()) {
            $messages[] = ["type" => "success", "text" => "Order updated successfully!"];
        } else {
            throw new Exception("Failed to update the order.");
        }
    } catch (Exception $e) {
        $messages[] = ["type" => "error", "text" => $e->getMessage()];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?php echo $message['type'] === 'error' ? 'danger' : 'success'; ?>" role="alert">
                <?php echo htmlspecialchars($message['text']); ?>
            </div>
        <?php endforeach; ?>

        <h2>Create Order</h2>
        <form action="orders.php" method="POST" class="mb-4">
            <div class="mb-3">
                <label for="username" class="form-label">Username:</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="product_id" class="form-label">Product:</label>
                <select name="product_id" id="product_id" class="form-select" required>
                    <?php
                    $query = "SELECT id, name, stock FROM products WHERE stock > 0";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='" . htmlspecialchars($row['id']) . "'>" 
                            . htmlspecialchars($row['name']) 
                            . " (Stock: " . htmlspecialchars($row['stock']) . ")"
                            . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="county" class="form-label">County:</label>
                <select name="county" id="county" class="form-select" required>
                    <!-- Fetch and display counties -->
                    <?php
                    $query = "SELECT id, name FROM counties";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="town" class="form-label">Town:</label>
                <select name="town" id="town" class="form-select" required>
                    <!-- Town options populated dynamically based on county selection -->
                </select>
            </div>

            <div class="mb-3">
                <label for="village" class="form-label">Village:</label>
                <select name="village" id="village" class="form-select" required>
                    <!-- Village options populated dynamically based on town selection -->
                </select>
            </div>

            <div class="mb-3">
                <label for="total_amount" class="form-label">Total Amount:</label>
                <input type="number" name="total_amount" id="total_amount" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="shipping_address" class="form-label">Shipping Address:</label>
                <input type="text" name="shipping_address" id="shipping_address" class="form-control" required readonly>
            </div>

            <button type="submit" name="create_order" class="btn btn-primary">Create Order</button>
        </form>

        <h2>All Orders</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Username</th>
                    <th>Product</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Shipping Address</th>
                    <th>Order Date</th>
                    <th>Tracking Number</th>
                    <th>Delivery Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['id']); ?></td>
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['total_amount']); ?></td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                        <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                        <td><?php echo htmlspecialchars($order['tracking_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['delivery_date']); ?></td>
                        <td>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $order['id']; ?>">Update</button>

                            <!-- Update Modal -->
                            <div class="modal fade" id="updateModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="updateModalLabel">Update Order</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="orders.php" method="POST">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                        <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="tracking_number" class="form-label">Tracking Number</label>
                                                    <input type="text" name="tracking_number" class="form-control" value="<?php echo htmlspecialchars($order['tracking_number']); ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label for="delivery_date" class="form-label">Delivery Date</label>
                                                    <input type="date" name="delivery_date" class="form-control" value="<?php echo htmlspecialchars($order['delivery_date']); ?>">
                                                </div>

                                                <button type="submit" name="update_order" class="btn btn-primary">Update</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
