<?php
session_start();
require_once 'config/database.php';
require_once 'includes/classes/ApiHandler.php';
require_once 'includes/utils/api_response.php';

// Initialize variables
$error = '';
$email = '';

// Check if this is an API request
if (isset($_POST['is_api']) && $_POST['is_api'] === '1') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (!$email || !$password || !$role) {
        send_error('Missing required fields');
    }

    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("SELECT id, username, password, role, 
                             COALESCE(status, 'active') as status 
                             FROM users 
                             WHERE email = ? 
                             AND (status = 'active' OR status IS NULL)
                             AND role = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            send_error('Invalid credentials', 401);
        }

        // Generate API token
        $token = bin2hex(random_bytes(32));
        $user_data = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'token' => $token
        ];

        send_success('Login successful', ['user' => $user_data]);
    } catch (Exception $e) {
        send_error('Server error: ' . $e->getMessage(), 500);
    }
}

// Regular web login process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['is_api'])) {
    try {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || empty($password)) {
            throw new Exception('Please enter both email and password.');
        }

        // First try local database authentication
        $database = new Database();
        $db = $database->getConnection();

        // First try with status check
        $stmt = $db->prepare("SELECT id, username, password, role, 
                             COALESCE(status, 'active') as status 
                             FROM users 
                             WHERE email = ? 
                             AND (status = 'active' OR status IS NULL)");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Local authentication successful
            $_SESSION['id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header('Location: ' . ($user['role'] === 'admin' ? 'index.php' : 'dashboard.php'));
            exit();
        }

        // If local auth fails, try API authentication
        $api = new ApiHandler();
        $response = $api->request('auth/login', 'POST', [
            'email' => $email,
            'password' => $password
        ]);

        if (isset($response['success']) && $response['success']) {
            // API authentication successful
            $_SESSION['id'] = $response['user']['id'];
            $_SESSION['username'] = $response['user']['username'];
            $_SESSION['role'] = $response['user']['role'];
            $_SESSION['api_token'] = $response['token'];

            header('Location: ' . ($response['user']['role'] === 'admin' ? 'index.php' : 'dashboard.php'));
            exit();
        } else {
            throw new Exception($response['message'] ?? 'Invalid email or password.');
        }

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = $e->getMessage();
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
