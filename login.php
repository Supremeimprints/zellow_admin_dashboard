<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$error = '';
$email = '';

// Only process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and validate form data
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Prepare query
            $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: index.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred during login. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In & Sign Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success">Registration successful! Please log in.</div>
<?php endif; ?>
    <div class="container" id="container">
        <div class="form-container sign-in-container">
            <form method="POST" action="">
                <h1>Log in</h1>
                <p class="muted">Enter your email & password to access your account.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <input type="email" 
                       name="email" 
                       placeholder="Email" 
                       value="<?= htmlspecialchars($email) ?>"
                       required />
                       
                <input type="password" 
                       name="password" 
                       placeholder="Password" 
                       required />
                       
                <a href="forgot_password.php">Forgot Password?</a>
                <button type="submit">Sign In</button>
            </form>
        </div>
    </div>
    <script src="assets/js/sign_up.js"></script>
</body>
</html>
