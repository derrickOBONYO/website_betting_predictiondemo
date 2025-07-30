<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get featured predictions
$stmt = $pdo->query("SELECT * FROM predictions WHERE expiry_date > NOW() ORDER BY created_at DESC LIMIT 3");
$featuredPredictions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetPro - Professional Betting Predictions</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <section class="hero">
        <div class="hero-content">
            <h1>Professional Betting Predictions</h1>
            <p>Get expert predictions for SportPesa, Betika jackpots and live games with high accuracy</p>
            <div class="cta-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="predictions.php" class="btn btn-primary">View Predictions</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                    <a href="login.php" class="btn btn-secondary">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <section class="features">
        <div class="container">
            <h2>Why Choose BetPro?</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>High Accuracy</h3>
                    <p>Our predictions are based on thorough analysis and statistics</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-bolt"></i>
                    <h3>Quick Delivery</h3>
                    <p>Get predictions instantly after payment via SMS and your account</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure Payments</h3>
                    <p>Safe and secure M-Pesa payments with instant verification</p>
                </div>
            </div>
        </div>
    </section>
    
    <?php if (!empty($featuredPredictions)): ?>
    <section class="predictions">
        <div class="container">
            <h2>Featured Predictions</h2>
            <div class="prediction-grid">
                <?php foreach ($featuredPredictions as $prediction): ?>
                <div class="prediction-card">
                    <div class="prediction-header">
                        <span class="prediction-type <?= $prediction['prediction_type'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $prediction['prediction_type'])) ?>
                        </span>
                        <span class="prediction-price">KES <?= number_format($prediction['price'], 2) ?></span>
                    </div>
                    <h3><?= htmlspecialchars($prediction['title']) ?></h3>
                    <p><?= htmlspecialchars(substr($prediction['description'], 0, 100)) ?>...</p>
                    <div class="prediction-footer">
                        <span class="expiry">Expires: <?= date('M j, H:i', strtotime($prediction['expiry_date'])) ?></span>
                        <a href="<?= isset($_SESSION['user_id']) ? 'purchase.php?id='.$prediction['prediction_id'] : 'login.php' ?>" 
                           class="btn btn-small">
                            <?= isset($_SESSION['user_id']) ? 'Purchase' : 'Login to Purchase' ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center">
                <a href="predictions.php" class="btn btn-secondary">View All Predictions</a>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <section class="how-it-works">
        <div class="container">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Register Account</h3>
                    <p>Create your free account to browse available predictions</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Choose Prediction</h3>
                    <p>Select the jackpot or live game prediction you want</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Make Payment</h3>
                    <p>Pay via M-Pesa using our secure payment system</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Get Predictions</h3>
                    <p>Receive predictions via SMS and in your account</p>
                </div>
            </div>
        </div>
    </section>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>