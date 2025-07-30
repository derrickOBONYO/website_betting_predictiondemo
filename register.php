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
$success = '';
$username = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'Username must be at least 4 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Username can only contain letters, numbers and underscores';
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Username already taken';
        }
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already registered';
        }
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!validateMpesaPhone($phone)) {
        $errors['phone'] = 'Please use MPESA format (2547XXXXXXXX)';
    } else {
        // Check if phone exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors['phone'] = 'Phone number already registered';
        }
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // If no errors, register user
    if (empty($errors)) {
        $hashedPassword = hashPassword($password);
        $verificationCode = generateRandomString(32);
        
        try {
            $pdo->beginTransaction();
            
            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, phone, password, verification_code, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $email, $phone, $hashedPassword, $verificationCode]);
            
            // Get new user ID
            $userId = $pdo->lastInsertId();
            
            // Send verification email
            $verificationLink = BASE_URL . "/verify.php?code=$verificationCode";
            $subject = "Verify Your BetPro Account";
            $body = "
                <h2>Welcome to BetPro Predictions!</h2>
                <p>Thank you for registering. Please verify your email by clicking the link below:</p>
                <p><a href='$verificationLink'>Verify My Account</a></p>
                <p>If you didn't create an account, please ignore this email.</p>
            ";
            
            if (sendEmail($email, $subject, $body)) {
                $pdo->commit();
                $success = "Registration successful! Please check your email to verify your account.";
                // Clear form
                $username = $email = $phone = '';
            } else {
                throw new Exception("Failed to send verification email");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="auth-form">
            <h1>Create Your Account</h1>
            
            <?php if ($success): ?>
                <div class="alert success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert error"><?= $errors['general'] ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
                    <?php if (isset($errors['username'])): ?>
                        <span class="error"><?= $errors['username'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error"><?= $errors['email'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number (MPESA)</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" 
                           placeholder="2547XXXXXXXX" required>
                    <?php if (isset($errors['phone'])): ?>
                        <span class="error"><?= $errors['phone'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error"><?= $errors['password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="error"><?= $errors['confirm_password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
            
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/register.js"></script>
</body>
</html>