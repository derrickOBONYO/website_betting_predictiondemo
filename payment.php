<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/mpesa.php';
require_once 'includes/functions.php';

if (!is_user_logged_in()) {
    header("Location: login.php?redirect=payment");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get prediction details
if (isset($_GET['prediction_id'])) {
    $prediction_id = intval($_GET['prediction_id']);
    $stmt = $pdo->prepare("SELECT p.*, pt.name as type_name, pt.price 
                          FROM predictions p 
                          JOIN prediction_types pt ON p.type_id = pt.type_id
                          WHERE p.prediction_id = ?");
    $stmt->execute([$prediction_id]);
    $prediction = $stmt->fetch();
    
    if (!$prediction) {
        $error = "Prediction not found";
    }
} else {
    $error = "No prediction specified";
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay'])) {
    $phone = sanitize_phone($_POST['phone']);
    $amount = $prediction['price'];
    
    // Initiate M-Pesa payment
    $mpesa = new Mpesa();
    $response = $mpesa->stkPush(
        $phone, 
        $amount, 
        "PRED" . $prediction_id . "USER" . $user_id,
        "Payment for " . $prediction['type_name'] . " predictions"
    );
    
    if (isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
        // Save payment record
        $stmt = $pdo->prepare("INSERT INTO payments 
                              (user_id, prediction_id, type_id, amount, mpesa_code, phone, status) 
                              VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([
            $user_id,
            $prediction_id,
            $prediction['type_id'],
            $amount,
            $response['CheckoutRequestID'],
            $phone
        ]);
        
        $success = "Payment initiated successfully. Please complete the payment on your phone.";
    } else {
        $error = "Failed to initiate payment. Please try again.";
    }
}

include 'header.php';
?>

<div class="container">
    <h2>Purchase Predictions</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php else: ?>
        <?php if (isset($prediction)): ?>
            <div class="prediction-details">
                <h3><?= htmlspecialchars($prediction['title']) ?></h3>
                <p><strong>Type:</strong> <?= htmlspecialchars($prediction['type_name']) ?></p>
                <p><strong>Price:</strong> KES <?= number_format($prediction['price'], 2) ?></p>
                <p><strong>Date:</strong> <?= date('jS F Y', strtotime($prediction['date'])) ?></p>
            </div>
            
            <form method="POST" class="payment-form">
                <div class="form-group">
                    <label for="phone">M-Pesa Phone Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" 
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" 
                           placeholder="e.g. 254712345678" required>
                </div>
                
                <button type="submit" name="pay" class="btn btn-primary">
                    Pay KES <?= number_format($prediction['price'], 2) ?> via M-Pesa
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>