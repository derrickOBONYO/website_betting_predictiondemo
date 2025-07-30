<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to home if already logged in
if (isUserLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    // Validate inputs
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        try {
            // Get user by username or email
            $stmt = $pdo->prepare("
                SELECT user_id, username, password, verified 
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                if ($user['verified']) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['last_login'] = time();
                    
                    // Set remember me cookie if selected
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + 60 * 60 * 24 * 30; // 30 days
                        
                        // Store token in database
                        $stmt = $pdo->prepare("
                            INSERT INTO user_sessions (user_id, token, expires_at) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$user['user_id'], $token, date('Y-m-d H:i:s', $expiry)]);
                        
                        // Set cookie
                        setcookie('remember_token', $token, $expiry, '/', '', true, true);
                    }
                    
                    // Redirect to intended page or home
                    $redirect = isset($_SESSION['redirect_to']) ? $_SESSION['redirect_to'] : 'index.php';
                    unset($_SESSION['redirect_to']);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $errors['general'] = 'Account not verified. Please check your email.';
                }
            } else {
                $errors['general'] = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Login failed. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check for remember me cookie
if (empty($_POST) && !empty($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.username 
            FROM users u
            JOIN user_sessions s ON u.user_id = s.user_id
            WHERE s.token = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['last_login'] = time();
            
            // Redirect to intended page or home
            $redirect = isset($_SESSION['redirect_to']) ? $_SESSION['redirect_to'] : 'index.php';
            unset($_SESSION['redirect_to']);
            header('Location: ' . $redirect);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="auth-form">
            <h1>Login to Your Account</h1>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert success"><?= $_SESSION['message'] ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert error"><?= $errors['general'] ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
                    <?php if (isset($errors['username'])): ?>
                        <span class="error"><?= $errors['username'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error"><?= $errors['password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <button type="submit" class="btn btn-primary">Login</button>
                
                <div class="auth-links">
                    <a href="forgot_password.php">Forgot password?</a>
                    <span>Don't have an account? <a href="register.php">Register here</a></span>
                </div>
            </form>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/login.js"></script>
</body>
</html>