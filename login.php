<?php
session_start();
require_once 'config/database.php';
require_once 'includes/classes/ApiHandler.php';
require_once 'includes/utils/api_response.php';

// Check if this is an API request
if (isset($_POST['is_api']) || isset($_GET['is_api'])) {
    header('Content-Type: application/json');
    
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? null, FILTER_VALIDATE_EMAIL);
        $password = $input['password'] ?? null;
        
        if (!$email || !$password) {
            send_error('Email and password are required', 400);
        }

        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("SELECT id, username, email, password, role, 
                             COALESCE(status, 'active') as status 
                             FROM users 
                             WHERE email = ? 
                             AND (status = 'active' OR status IS NULL)");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            send_error('Invalid credentials', 401);
        }

        // Generate API token
        $token = bin2hex(random_bytes(32));
        
        // Update user's token in database
        $updateStmt = $db->prepare("UPDATE users SET api_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?");
        $updateStmt->execute([$token, $user['id']]);

        send_success('Login successful', [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'token' => $token
            ]
        ]);
    } catch (Exception $e) {
        send_error($e->getMessage(), 500);
    }
}

// Regular web login process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['is_api'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id, email, password, role FROM users WHERE email = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['email'] = $user['email'];
        $_SESSION['id'] = $user['id'];
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = $user['role'];
        
        switch ($user['role']) {
            case 'admin':
                header("Location: index.php");
                break;
            case 'finance_manager':
                header("Location: Financedashboard.php");
                break;
            case 'dispatch_manager':
                header("Location: dispatch.php");
                break;
                case 'inventory_manager':
                    header("Location: placeholder.php");
                    break;
            // Add other cases as needed
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
            <form method="POST">
                <h1>Log in</h1>
                <p class="muted">Enter your email & password to access your account.</p>
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Password" required />
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <a href="">Forgot Password?</a>
                <button type="submit">Sign In</button>
            </form>
        </div>
          </div>
    <script src="assets/js/sign_up.js"></script>
</body>

</html>
