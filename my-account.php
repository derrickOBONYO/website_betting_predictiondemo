<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not authenticated
if (!isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get user details
$user = $pdo->prepare("SELECT * FROM users WHERE user_id = ?")->execute([$userId])->fetch();

// Get user's purchased predictions
$purchasedPredictions = $pdo->prepare("
    SELECT p.*, up.access_granted_at 
    FROM user_predictions up
    JOIN predictions p ON up.prediction_id = p.prediction_id
    WHERE up.user_id = ?
    ORDER BY up.access_granted_at DESC
")->execute([$userId])->fetchAll();

// Get user's transactions
$transactions = $pdo->prepare("
    SELECT t.*, p.title 
    FROM transactions t
    JOIN predictions p ON t.prediction_id = p.prediction_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 5
")->execute([$userId])->fetchAll();

// Handle profile update
$profileErrors = [];
$profileSuccess = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    
    // Validate inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileErrors['email'] = 'Invalid email format';
    }
    
    if (!validateMpesaPhone($phone)) {
        $profileErrors['phone'] = 'Please use MPESA format (2547XXXXXXXX)';
    }
    
    if (empty($profileErrors)) {
        try {
            $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE user_id = ?")
               ->execute([$email, $phone, $userId]);
            $profileSuccess = "Profile updated successfully!";
            // Refresh user data
            $user = $pdo->prepare("SELECT * FROM users WHERE user_id = ?")->execute([$userId])->fetch();
        } catch (PDOException $e) {
            $profileErrors['general'] = "Error updating profile. Please try again.";
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

// Handle password change
$passwordErrors = [];
$passwordSuccess = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate current password
    if (!verifyPassword($currentPassword, $user['password'])) {
        $passwordErrors['current_password'] = 'Current password is incorrect';
    }
    
    // Validate new password
    if (strlen($newPassword) < 8) {
        $passwordErrors['new_password'] = 'Password must be at least 8 characters';
    }
    
    if ($newPassword !== $confirmPassword) {
        $passwordErrors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($passwordErrors)) {
        try {
            $hashedPassword = hashPassword($newPassword);
            $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
               ->execute([$hashedPassword, $userId]);
            $passwordSuccess = "Password changed successfully!";
        } catch (PDOException $e) {
            $passwordErrors['general'] = "Error changing password. Please try again.";
            error_log("Password change error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="account-dashboard">
            <h1>My Account</h1>
            
            <div class="account-sections">
                <!-- Profile Section -->
                <section class="account-section">
                    <h2><i class="fas fa-user-circle"></i> Profile Information</h2>
                    
                    <?php if ($profileSuccess): ?>
                        <div class="alert success"><?= $profileSuccess ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($profileErrors['general'])): ?>
                        <div class="alert error"><?= $profileErrors['general'] ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                            <small>Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                            <?php if (isset($profileErrors['email'])): ?>
                                <span class="error"><?= $profileErrors['email'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($user['phone']) ?>" required>
                            <?php if (isset($profileErrors['phone'])): ?>
                                <span class="error"><?= $profileErrors['phone'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            Update Profile
                        </button>
                    </form>
                </section>
                
                <!-- Password Section -->
                <section class="account-section">
                    <h2><i class="fas fa-lock"></i> Change Password</h2>
                    
                    <?php if ($passwordSuccess): ?>
                        <div class="alert success"><?= $passwordSuccess ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($passwordErrors['general'])): ?>
                        <div class="alert error"><?= $passwordErrors['general'] ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                            <?php if (isset($passwordErrors['current_password'])): ?>
                                <span class="error"><?= $passwordErrors['current_password'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <?php if (isset($passwordErrors['new_password'])): ?>
                                <span class="error"><?= $passwordErrors['new_password'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <?php if (isset($passwordErrors['confirm_password'])): ?>
                                <span class="error"><?= $passwordErrors['confirm_password'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        
                        <button type="submit" name="change_password" class="btn btn-primary">
                            Change Password
                        </button>
                    </form>
                </section>
                
                <!-- Purchased Predictions Section -->
                <section class="account-section">
                    <h2><i class="fas fa-trophy"></i> My Predictions</h2>
                    
                    <?php if (empty($purchasedPredictions)): ?>
                        <div class="alert info">You haven't purchased any predictions yet.</div>
                    <?php else: ?>
                        <div class="predictions-list">
                            <?php foreach ($purchasedPredictions as $prediction): ?>
                                <div class="prediction-item">
                                    <div class="prediction-header">
                                        <h3><?= htmlspecialchars($prediction['title']) ?></h3>
                                        <span class="prediction-type <?= $prediction['prediction_type'] ?>">
                                            <?= getPredictionTypeName($prediction['prediction_type']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="prediction-meta">
                                        <span>
                                            <i class="fas fa-calendar-alt"></i> 
                                            Purchased: <?= date('M j, Y', strtotime($prediction['access_granted_at'])) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i> 
                                            <?= isPredictionExpired($prediction['expiry_date']) ? 'Expired' : 'Expires' ?>: 
                                            <?= date('M j, Y', strtotime($prediction['expiry_date'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="prediction-actions">
                                        <a href="view_prediction.php?id=<?= $prediction['prediction_id'] ?>" class="btn btn-small">
                                            View Prediction
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center">
                            <a href="my_predictions.php" class="btn btn-secondary">View All Predictions</a>
                        </div>
                    <?php endif; ?>
                </section>
                
                <!-- Recent Transactions Section -->
                <section class="account-section">
                    <h2><i class="fas fa-receipt"></i> Recent Transactions</h2>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="alert info">No transactions found.</div>
                    <?php else: ?>
                        <div class="transactions-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Prediction</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></td>
                                            <td><?= htmlspecialchars($transaction['title']) ?></td>
                                            <td>KES <?= number_format($transaction['amount'], 2) ?></td>
                                            <td>
                                                <span class="status-badge <?= $transaction['status'] ?>">
                                                    <?= ucfirst($transaction['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center">
                            <a href="transaction_history.php" class="btn btn-secondary">View All Transactions</a>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/my-account.js"></script>
</body>
</html>