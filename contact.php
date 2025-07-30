<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$name = $email = $message = '';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $message = sanitizeInput($_POST['message']);
    
    // Validation
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($message)) {
        $errors['message'] = 'Message is required';
    } elseif (strlen($message) < 10) {
        $errors['message'] = 'Message should be at least 10 characters';
    }
    
    if (empty($errors)) {
        try {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO contacts (name, email, message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $email, $message]);
            
            // Send email
            $subject = "New Contact Message from " . SITE_NAME;
            $body = "
                <h2>New Contact Message</h2>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Message:</strong></p>
                <p>$message</p>
            ";
            
            if (sendEmail(ADMIN_EMAIL, $subject, $body)) {
                $success = "Thank you for your message! We'll get back to you soon.";
                $name = $email = $message = ''; // Clear form
            } else {
                $errors['general'] = "Failed to send your message. Please try again.";
            }
        } catch (PDOException $e) {
            $errors['general'] = "Error submitting your message. Please try again.";
            error_log("Contact form error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="contact-page">
            <h1>Contact Us</h1>
            <p>Have questions or feedback? Reach out to our support team.</p>
            
            <?php if ($success): ?>
                <div class="alert success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert error"><?= $errors['general'] ?></div>
            <?php endif; ?>
            
            <div class="contact-container">
                <form method="POST" class="contact-form">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <span class="error"><?= $errors['name'] ?></span>
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
                        <label for="message">Your Message</label>
                        <textarea id="message" name="message" rows="5" required><?= htmlspecialchars($message) ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                            <span class="error"><?= $errors['message'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
                
                <div class="contact-info">
                    <h3>Other Ways to Reach Us</h3>
                    
                    <div class="contact-method">
                        <i class="fas fa-envelope"></i>
                        <h4>Email</h4>
                        <p><?= SITE_EMAIL ?></p>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-phone"></i>
                        <h4>Phone</h4>
                        <p>+254 700 123 456</p>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Address</h4>
                        <p>Nairobi, Kenya</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>