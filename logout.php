<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Unset all session variables
$_SESSION = [];

// Delete remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    try {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE token = ?");
        $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log("Logout error: " . $e->getMessage());
    }
    
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>