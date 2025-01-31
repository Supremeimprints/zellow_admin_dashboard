<?php
session_start();
require_once 'config/database.php';

$error = ''; // Initialize error as empty
$success = ''; // Initialize success message
//handles login part
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } else {
        $database = new Database();
        $db = $database->getConnection();

        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_user) {
            $error = "This email is already taken.";
        } else {
            // Hash password and insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password, $role]);

            $success = "Employee registered successfully!";
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
    <link rel="stylesheet" href="assets/css/sign_up.css">
</head>
<body>
    <div class="container" id="container">
        <div class="form-container sign-up-container">
            <form action="signup.php" method="POST">
                <h1>Create Account</h1>
                <input type="text" name="name" placeholder="Name" required />
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Password" required />
                    <select class="form-control" id="role" name="role" placeholder="Role" required>
                        <option value="admin">Admin</option>
                        <option value="finance_manager">Finance Manager</option>
                        <option value="supply_manager">Supply Manager</option>
                        <option value="dispatch_manager">Dispatch Manager</option>
                        <option value="service_manager">Service Manager</option>
                        <option value="inventory_manager">Inventory Manager</option>
                    </select>
                <button type="submit">Sign Up</button>
            </form>
        </div>
        <div class="form-container sign-in-container">
            <form method="POST">
                <h1>Sign in</h1>
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Password" required />
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <button type="submit">Sign In</button>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>Welcome Back!</h1>
                    <p>To keep connected with us please login with your personal info</p>
                    <button class="ghost" id="signIn">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>Hello, Friend!</h1>
                    <p>Enter your personal details and start your journey with us</p>
                    <button class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/sign_up.js"></script>
</body>
</html>
