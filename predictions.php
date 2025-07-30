<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to login if not authenticated
if (!isUserLoggedIn()) {
    $_SESSION['redirect_to'] = 'predictions.php';
    header('Location: login.php');
    exit;
}

// Get current user ID
$userId = $_SESSION['user_id'];

// Handle filters
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build WHERE conditions
$where = [];
$params = [];

if (!empty($type)) {
    $where[] = "prediction_type = ?";
    $params[] = $type;
}

if ($status === 'available') {
    $where[] = "expiry_date > NOW()";
} elseif ($status === 'expired') {
    $where[] = "expiry_date <= NOW()";
}

// Get prediction types for filter
$predictionTypes = $pdo->query("SELECT DISTINCT prediction_type FROM predictions")->fetchAll();

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Get total count
$countQuery = "SELECT COUNT(*) FROM predictions";
if (!empty($where)) {
    $countQuery .= " WHERE " . implode(" AND ", $where);
}
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Get predictions
$query = "
    SELECT p.*, 
           (SELECT COUNT(*) FROM user_predictions up 
            WHERE up.prediction_id = p.prediction_id AND up.user_id = ?) as purchased
    FROM predictions p
";

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY expiry_date DESC LIMIT $perPage OFFSET $offset";

$params = array_merge([$userId], $params);
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$predictions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predictions - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="predictions-header">
            <h1>Available Predictions</h1>
            
            <!-- Prediction Filters -->
            <form method="GET" class="prediction-filters">
                <div class="filter-group">
                    <label for="type">Prediction Type:</label>
                    <select name="type" id="type">
                        <option value="">All Types</option>
                        <?php foreach ($predictionTypes as $pt): ?>
                            <option value="<?= $pt['prediction_type'] ?>" <?= $type === $pt['prediction_type'] ? 'selected' : '' ?>>
                                <?= getPredictionTypeName($pt['prediction_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status">
                        <option value="">All</option>
                        <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </form>
        </div>
        
        <?php if (empty($predictions)): ?>
            <div class="alert info">No predictions found matching your criteria.</div>
        <?php else: ?>
            <div class="predictions-grid">
                <?php foreach ($predictions as $prediction): ?>
                    <div class="prediction-card <?= $prediction['purchased'] ? 'purchased' : '' ?>">
                        <div class="prediction-header">
                            <span class="prediction-type <?= $prediction['prediction_type'] ?>">
                                <?= getPredictionTypeName($prediction['prediction_type']) ?>
                            </span>
                            <span class="prediction-price">KES <?= number_format($prediction['price'], 2) ?></span>
                        </div>
                        
                        <h3><?= htmlspecialchars($prediction['title']) ?></h3>
                        
                        <p><?= htmlspecialchars(substr($prediction['description'], 0, 100)) ?>...</p>
                        
                        <div class="prediction-meta">
                            <span class="expiry">
                                <i class="fas fa-clock"></i> 
                                <?= isPredictionExpired($prediction['expiry_date']) ? 'Expired' : 'Expires' ?>: 
                                <?= date('M j, H:i', strtotime($prediction['expiry_date'])) ?>
                            </span>
                            
                            <span class="games-count">
                                <i class="fas fa-list-ol"></i> 
                                <?= count(json_decode($prediction['games'], true)) ?> games
                            </span>
                        </div>
                        
                        <div class="prediction-footer">
                            <?php if ($prediction['purchased']): ?>
                                <span class="purchased-badge">
                                    <i class="fas fa-check-circle"></i> Purchased
                                </span>
                                <a href="view_prediction.php?id=<?= $prediction['prediction_id'] ?>" class="btn btn-small">
                                    View Prediction
                                </a>
                            <?php elseif (isPredictionExpired($prediction['expiry_date'])): ?>
                                <span class="expired-badge">Expired</span>
                            <?php else: ?>
                                <a href="purchase.php?id=<?= $prediction['prediction_id'] ?>" class="btn btn-small btn-primary">
                                    Purchase Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&type=<?= $type ?>&status=<?= $status ?>" class="btn btn-small">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span>Page <?= $page ?> of <?= $totalPages ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&type=<?= $type ?>&status=<?= $status ?>" class="btn btn-small">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/predictions.js"></script>
</body>
</html>