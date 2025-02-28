<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions/badge_functions.php';

$database = new Database();
$db = $database->getConnection();

// Initialize filters
$dateFilter = $_GET['date'] ?? '';
$ratingFilter = $_GET['rating'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build the query
$query = "
    SELECT f.*, u.username, o.order_id as order_reference 
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    LEFT JOIN orders o ON f.order_id = o.order_id
    WHERE 1=1";

$params = [];

if ($dateFilter) {
    $query .= " AND DATE(f.created_at) = ?";
    $params[] = $dateFilter;
}

if ($ratingFilter !== '') {
    $query .= " AND f.rating = ?";
    $params[] = $ratingFilter;
}

if ($searchQuery) {
    $query .= " AND (u.username LIKE ? OR f.comment LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Get total count
$countStmt = $db->prepare(str_replace("SELECT f.*", "SELECT COUNT(*)", $query));
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Add pagination
$query .= " ORDER BY f.created_at DESC LIMIT $offset, $perPage";

$stmt = $db->prepare($query);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rating statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive,
        COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative
    FROM feedback";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Zellow Admin</title>
    <script src="https://unpkg.com/feather-icons"></script>

    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .rating-star {
            color: #ffc107;
        }
        .feedback-stats {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            padding: 15px;
            border-radius: 6px;
            background-color: --bs-light;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/theme.php'; ?>
        <nav class="navbar">
            <?php include 'includes/nav/collapsed.php'; ?>
        </nav>

        <div class="main-content">
            <div class="container mt-5">
                <h2 class="mb-4">Customer Feedback Management</h2>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Total Feedback</h6>
                            <h3><?= number_format($stats['total']) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Average Rating</h6>
                            <h3>
                                <?= number_format($stats['avg_rating'], 1) ?>
                                <small class="rating-star">★</small>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Positive Feedback</h6>
                            <h3 class="text-success">
                                <?= $stats['total'] ? round(($stats['positive'] / $stats['total']) * 100) : 0 ?>%
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Negative Feedback</h6>
                            <h3 class="text-danger">
                                <?= $stats['total'] ? round(($stats['negative'] / $stats['total']) * 100) : 0 ?>%
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by username or comment" 
                                       value="<?= htmlspecialchars($searchQuery) ?>">
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" 
                                       value="<?= htmlspecialchars($dateFilter) ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="rating" class="form-control">
                                    <option value="">All Ratings</option>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <option value="<?= $i ?>" <?= $ratingFilter == $i ? 'selected' : '' ?>>
                                            <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Feedback List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Order ID</th>
                                        <th>Rating</th>
                                        <th>Comment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($feedbacks)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">No feedback found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($feedbacks as $feedback): ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i', strtotime($feedback['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($feedback['username']) ?></td>
                                                <td>
                                                    <?php if ($feedback['order_reference']): ?>
                                                        <a href="orders.php?id=<?= $feedback['order_reference'] ?>">#<?= $feedback['order_reference'] ?></a>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="rating-star"><?= $i <= $feedback['rating'] ? '★' : '☆' ?></span>
                                                    <?php endfor; ?>
                                                </td>
                                                <td><?= htmlspecialchars($feedback['comment']) ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewFeedbackDetails(<?= $feedback['id'] ?>)">
                                                        View Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page-1 ?>&date=<?= $dateFilter ?>&rating=<?= $ratingFilter ?>&search=<?= $searchQuery ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&date=<?= $dateFilter ?>&rating=<?= $ratingFilter ?>&search=<?= $searchQuery ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page+1 ?>&date=<?= $dateFilter ?>&rating=<?= $ratingFilter ?>&search=<?= $searchQuery ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFeedbackDetails(id) {
            // Implement feedback details view functionality
            // Could open a modal or redirect to a details page
            console.log('View feedback details for ID:', id);
        }
    </script>
</body>
</html>
