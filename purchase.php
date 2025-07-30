<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/mpesa.php';
require_once 'includes/sms.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: predictions.php');
    exit;
}

$prediction_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Check if user already has access
$stmt = $pdo->prepare("SELECT * FROM user_predictions WHERE user_id = ? AND prediction_id = ?");
$stmt->execute([$user_id, $prediction_id]);
if ($stmt->fetch()) {
    header('Location: my_account.php');
    exit;
}

// Get prediction details
$stmt = $pdo->prepare("SELECT * FROM predictions WHERE prediction_id = ?");
$stmt->execute([$prediction_id]);
$prediction = $stmt->fetch();

if (!$prediction) {
    header('Location: predictions.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitizeInput($_POST['phone']);
    
    // Validate phone number
    if (strlen($phone) != 12 || !preg_match('/^254[0-9]{9}$/', $phone)) {
        $error = "Please enter a valid M-Pesa phone number in format 2547XXXXXXXX";
    } else {
        // Initiate STK push
        $mpesa = new Mpesa();
        $accountRef = "PRED" . time();
        $response = $mpesa->stkPush($phone, $prediction['price'], $accountRef, "Payment for " . $prediction['title']);
        
        if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
            // Save transaction
            $stmt = $pdo->prepare("INSERT INTO transactions 
                (user_id, prediction_id, mpesa_code, phone_number, amount, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([
                $user_id,
                $prediction_id,
                $response['CheckoutRequestID'],
                $phone,
                $prediction['price']
            ]);
            
            $transaction_id = $pdo->lastInsertId();
            $_SESSION['pending_transaction'] = $transaction_id;
            
            $success = "Payment request sent to your phone. Please complete the M-Pesa payment to access the predictions.";
        } else {
            $error = "Failed to initiate payment. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Prediction</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <h1>Purchase Prediction: <?= htmlspecialchars($prediction['title']) ?></h1>
        
        <?php if ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?= $success ?></div>
            <div id="payment-status">
                <p>Waiting for payment confirmation...</p>
                <div class="loader"></div>
                <p>This page will automatically update once payment is confirmed.</p>
            </div>
            <script>
                // Check payment status every 5 seconds
                setInterval(() => {
                    fetch('payment_status.php?transaction=<?= $_SESSION['pending_transaction'] ?>')
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'completed') {
                                window.location.href = 'my_account.php';
                            }
                        });
                }, 5000);
            </script>
        <?php else: ?>
            <div class="prediction-details">
                <h3>Prediction Details</h3>
                <p><strong>Type:</strong> <?= ucfirst(str_replace('_', ' ', $prediction['prediction_type'])) ?></p>
                <p><strong>Price:</strong> KES <?= number_format($prediction['price'], 2) ?></p>
                <p><strong>Description:</strong> <?= htmlspecialchars($prediction['description']) ?></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="phone">M-Pesa Phone Number (2547XXXXXXXX)</label>
                    <input type="text" id="phone" name="phone" required 
                           pattern="254[0-9]{9}" title="Format: 2547XXXXXXXX">
                </div>
                
                <button type="submit" class="btn">Pay KES <?= number_format($prediction['price'], 2) ?></button>
            </form>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>