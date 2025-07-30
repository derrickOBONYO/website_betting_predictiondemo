<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isUserLoggedIn()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration
                
                // Store token in database
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET reset_token = ?, reset_expires = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$token, $expires, $user['user_id']]);
                
                // Send reset email
                $resetLink = BASE_URL . "/reset_password.php?token=$token";
                $subject = "Password Reset Request";
                $body = "
                    <h2>Password Reset</h2>
                    <p>We received a request to reset your password. Click the link below to proceed:</p>
                    <p><a href='$resetLink'>Reset Password</a></p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <p>This link will expire in 1 hour.</p>
                ";
                
                if (sendEmail($email, $subject, $body)) {
                    $message = "Password reset link sent to your email";
                } else {
                    $error = "Failed to send reset email";
                }
            } else {
                $message = "If an account exists with that email, a reset link has been sent";
            }
        } catch (PDOException $e) {
            $error = "Error processing your request";
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="auth-form">
            <h1>Forgot Password</h1>
            
            <?php if ($message): ?>
                <div class="alert success"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <button type="submit" class="btn btn-primary">Reset Password</button>
                
                <div class="auth-links">
                    <a href="login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>