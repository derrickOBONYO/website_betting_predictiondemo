<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="about-page">
            <h1>About BetPro Predictions</h1>
            
            <section class="about-section">
                <h2>Who We Are</h2>
                <p>BetPro Predictions is a leading sports betting prediction service with a team of experienced analysts dedicated to providing accurate and reliable betting tips. Our mission is to help both novice and experienced bettors make informed decisions and maximize their winning potential.</p>
            </section>
            
            <section class="about-section">
                <h2>Our Expertise</h2>
                <p>With over 5 years in the industry, our team of experts analyzes hundreds of matches daily, considering factors like team form, head-to-head records, injuries, and other crucial statistics to deliver high-probability predictions.</p>
                
                <div class="expertise-grid">
                    <div class="expertise-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Data Analysis</h3>
                        <p>We use advanced statistical models to analyze match data and identify value bets.</p>
                    </div>
                    
                    <div class="expertise-card">
                        <i class="fas fa-users"></i>
                        <h3>Team of Experts</h3>
                        <p>Our team includes former bookmakers, statisticians, and sports analysts.</p>
                    </div>
                    
                    <div class="expertise-card">
                        <i class="fas fa-trophy"></i>
                        <h3>Proven Track Record</h3>
                        <p>Consistent 75%+ accuracy rate across major leagues and competitions.</p>
                    </div>
                </div>
            </section>
            
            <section class="about-section">
                <h2>Our Success</h2>
                <div class="success-stats">
                    <div class="stat-item">
                        <h3>10,000+</h3>
                        <p>Happy Customers</p>
                    </div>
                    
                    <div class="stat-item">
                        <h3>85%</h3>
                        <p>Average Accuracy</p>
                    </div>
                    
                    <div class="stat-item">
                        <h3>24/7</h3>
                        <p>Customer Support</p>
                    </div>
                </div>
            </section>
            
            <section class="about-section">
                <h2>Why Choose Us</h2>
                <ul class="benefits-list">
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Detailed analysis for each prediction</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Coverage of all major leagues and competitions</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Transparent performance tracking</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Secure and instant payment methods</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Responsive customer support</span>
                    </li>
                </ul>
            </section>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>