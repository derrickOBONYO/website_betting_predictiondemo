<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isset($_GET['code'])) {
    $code = sanitizeInput($_GET['code']);
    
    try {
        // Check if verification code exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE verification_code = ? AND verified = 0");
        $stmt->execute([$code]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Mark as verified
            $update = $pdo->prepare("UPDATE users SET verified = 1, verification_code = NULL WHERE user_id = ?");
            $update->execute([$user['user_id']]);
            
            $_SESSION['message'] = "Account verified successfully! You can now login.";
        } else {
            $_SESSION['error'] = "Invalid or expired verification link.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error verifying account. Please try again.";
        error_log("Verification error: " . $e->getMessage());
    }
}

header('Location: login.php');
exit;
?>