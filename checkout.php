<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || !isset($_GET['order_id'])) {
    header('Location: orders.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Update query to use order_id instead of id
$query = "SELECT o.*, u.email, u.username FROM orders o 
          LEFT JOIN users u ON o.id = u.id 
          WHERE o.order_id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $_GET['order_id']);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || $order['payment_status'] === 'Paid') {
    header('Location: orders.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Order #<?php echo htmlspecialchars($_GET['order_id']); ?></title>
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        h2, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            font-family: 'Montserrat', sans-serif;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    
    <div class="main-content">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Checkout - Order #<?php echo htmlspecialchars($_GET['order_id']); ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Order Details</h5>
                                    <p>Total Amount: Ksh. <?php echo number_format($order['total_amount'], 2); ?></p>
                                    <p>Payment Method: <?php echo htmlspecialchars($order['payment_method']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Customer Details</h5>
                                    <p>Name: <?php echo htmlspecialchars($order['username']); ?></p>
                                    <p>Email: <?php echo htmlspecialchars($order['email']); ?></p>
                                </div>
                            </div>

                            <?php if ($order['payment_method'] === 'Mpesa'): ?>
                                <div class="alert alert-info">
                                    <h5>Mpesa Payment Instructions</h5>
                                    <p>1. Go to M-PESA on your phone</p>
                                    <p>2. Select Pay Bill</p>
                                    <p>3. Enter Business No: <strong>123456</strong></p>
                                    <p>4. Enter Account No: <strong><?php echo $_GET['order_id']; ?></strong></p>
                                    <p>5. Enter Amount: <strong>Ksh. <?php echo number_format($order['total_amount'], 2); ?></strong></p>
                                    <p>6. Enter your M-PESA PIN and confirm payment</p>
                                </div>
                            <?php elseif ($order['payment_method'] === 'Airtel Money'): ?>
                                <div class="alert alert-info">
                                    <h5>Airtel Money Payment Instructions</h5>
                                    <p>1. Dial *144#</p>
                                    <p>2. Select Make Payments</p>
                                    <p>3. Enter Business No: <strong>123456</strong></p>
                                    <p>4. Enter Reference No: <strong><?php echo $_GET['order_id']; ?></strong></p>
                                    <p>5. Enter Amount: <strong>Ksh. <?php echo number_format($order['total_amount'], 2); ?></strong></p>
                                    <p>6. Confirm payment with your PIN</p>
                                </div>
                            <?php endif; ?>

                            <form id="payment-form" class="mt-4">
                                <input type="hidden" name="order_id" value="<?php echo $_GET['order_id']; ?>">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        Confirm Payment
                                    </button>
                                    <a href="update_order.php?id=<?php echo $_GET['order_id']; ?>" class="btn btn-outline-danger">
                                        Back to Order
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('payment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('ajax/process_payment.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment processed successfully!');
            window.location.href = 'orders.php';
        } else {
            alert(data.message || 'Error processing payment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error processing payment');
    });
});

feather.replace();
</script>

<?php include 'includes/nav/footer.php'; ?>
</body>
</html>
