<?php
session_start();
require_once 'config/database.php';

$error = ''; // Initialize error as empty

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $database = new Database();
    $db = $database->getConnection();

    // Correct query to include email
    $query = "SELECT id, email, password, role FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['email'] = $user['email'];
        $_SESSION['id'] = $user['id'];
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role
        switch ($user['role']) {
            case 'admin':
                header("Location: index.php");
                break;
            case 'finance_manager':
                header("Location: Financedashboard.php");
                break;
            case 'supply_manager':
                header("Location: placeholder.php");
                break;
            case 'dispatch_manager':
                header("Location: dispatch.php");
                break;
            case 'service_manager':
                header("Location: placeholder.php");
                break;
            case 'inventory_manager':
                header("Location: placeholder.php");
                break;
            default:
                header("Location: placeholder.php");
                break;
        }
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>            
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Admin Login</h1>
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</body>
<?php include 'footer.php'; ?>
</html>