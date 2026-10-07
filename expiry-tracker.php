<?php
/**
 * FoodSafe Track - Dedicated Expiry Tracker & Alerts Console
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
syncExpiryAlerts($pdo);

$pageTitle = 'Expiry Tracker & Safety Alerts';

// Handle Action: Mark all alerts as read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $pdo->query("UPDATE expiry_alerts SET is_read = 1");
    setFlash('success', 'All system expiry notifications marked as read.');
    header('Location: expiry-tracker.php');
    exit;
}

// Current filter tab
$filter = trim($_GET['filter'] ?? 'all');

// Base query
$whereSql = "f.quantity > 0";
if ($filter === 'expired') {
    $whereSql .= " AND f.expiry_date < CURDATE()";
} elseif ($filter === 'critical') {
    $whereSql .= " AND f.expiry_date >= CURDATE() AND f.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)";
} elseif ($filter === 'near_expiry') {
    $whereSql .= " AND f.expiry_date > DATE_ADD(CURDATE(), INTERVAL 2 DAY) AND f.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'safe') {
    $whereSql .= " AND f.expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
}

$query = "
    SELECT f.*, c.category_name, s.supplier_name
    FROM food_items f
    JOIN categories c ON f.category_id = c.id
    LEFT JOIN suppliers s ON f.supplier_id = s.id
    WHERE {$whereSql}
    ORDER BY f.expiry_date ASC
";
$stmt = $pdo->query($query);
$items = $stmt->fetchAll();

// Count for each category tab
$counts = [
    'all' => (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0")->fetchColumn(),
    'expired' => (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date < CURDATE()")->fetchColumn(),
    'critical' => (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)")->fetchColumn(),
    'near_expiry' => (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 2 DAY) AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
    'safe' => (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
];

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">Expiry Tracker &amp; Food Alerts</h2>
        <p class="text-muted small mb-0">Prioritize food usage, prevent spoilage, and execute prompt wastage logging.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="expiry-tracker.php?action=mark_all_read" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-check-double me-1"></i> Mark Alerts Read
        </a>
        <a href="add-wastage.php" class="btn btn-danger rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-trash-can me-1"></i> Discard Wasted Food
        </a>
    </div>
</div>

<!-- Urgency Classification Tabs -->
<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-2 p-md-3">
        <ul class="nav nav-pills nav-fill gap-2 flex-column flex-sm-row">
            <li class="nav-item">
                <a class="nav-link rounded-pill text-center py-2 px-3 fw-semibold <?= ($filter === 'all') ? 'active bg-dark' : 'text-secondary' ?>" href="expiry-tracker.php?filter=all">
                    All Items <span class="badge bg-secondary ms-1"><?= $counts['all'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill text-center py-2 px-3 fw-semibold <?= ($filter === 'expired') ? 'active bg-danger' : 'text-danger' ?>" href="expiry-tracker.php?filter=expired">
                    <i class="fa-solid fa-circle-xmark me-1"></i> Expired 
                    <span class="badge bg-danger-subtle text-danger ms-1"><?= $counts['expired'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill text-center py-2 px-3 fw-semibold <?= ($filter === 'critical') ? 'active bg-warning text-dark' : 'text-warning-emphasis' ?>" href="expiry-tracker.php?filter=critical">
                    <i class="fa-solid fa-fire me-1"></i> Critical (&le;2d)
                    <span class="badge bg-warning-subtle text-dark ms-1"><?= $counts['critical'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill text-center py-2 px-3 fw-semibold <?= ($filter === 'near_expiry') ? 'active bg-info text-white' : 'text-info-emphasis' ?>" href="expiry-tracker.php?filter=near_expiry">
                    <i class="fa-solid fa-bell me-1"></i> Near Expiry (3-7d)
                    <span class="badge bg-info-subtle text-dark ms-1"><?= $counts['near_expiry'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill text-center py-2 px-3 fw-semibold <?= ($filter === 'safe') ? 'active bg-success' : 'text-success' ?>" href="expiry-tracker.php?filter=safe">
                    <i class="fa-solid fa-shield-halved me-1"></i> Safe Stock
                    <span class="badge bg-success-subtle text-success ms-1"><?= $counts['safe'] ?></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Expiry Priority Monitor
        </h6>
        <!-- Client-Side Instant Search Filter -->
        <div style="max-width: 260px;">
            <input type="text" id="tableSearchInput" class="form-control form-control-sm rounded-pill" placeholder="Instant filter table...">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table custom-table align-middle searchable-table">
            <thead>
                <tr>
                    <th>Food Item &amp; Batch</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Storage Location</th>
                    <th>Expiry Date</th>
                    <th>Countdown Status</th>
                    <th>Recommended Staff Action</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-shield-heart text-success fs-2 mb-2 d-block"></i>
                            No food items found matching the selected expiry filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): 
                        $days = getDaysRemaining($item['expiry_date']);
                    ?>
                        <tr class="<?= ($days < 0) ? 'table-danger-subtle' : (($days <= 2) ? 'table-warning-subtle' : '') ?>">
                            <td>
                                <a href="food-details.php?id=<?= $item['id'] ?>" class="fw-bold text-dark text-decoration-none d-block">
                                    <?= htmlspecialchars($item['food_name']) ?>
                                </a>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($item['batch_number']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($item['category_name']) ?></span>
                            </td>
                            <td>
                                <strong><?= number_format($item['quantity'], 2) ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($item['unit']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid fa-location-dot me-1 text-secondary"></i><?= htmlspecialchars($item['storage_location']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= formatDate($item['expiry_date']) ?></div>
                            </td>
                            <td>
                                <?= getExpiryBadge($item['expiry_date']) ?>
                            </td>
                            <td>
                                <?php if ($days < 0): ?>
                                    <span class="text-danger small fw-bold">
                                        <i class="fa-solid fa-ban me-1"></i> DO NOT CONSUME. Discard immediately.
                                    </span>
                                <?php elseif ($days <= 2): ?>
                                    <span class="text-danger-emphasis small fw-bold">
                                        <i class="fa-solid fa-fire me-1"></i> Priority cooking / Prep today.
                                    </span>
                                <?php elseif ($days <= 7): ?>
                                    <span class="text-warning-emphasis small fw-medium">
                                        <i class="fa-solid fa-calendar-check me-1"></i> Schedule into meal plan this week.
                                    </span>
                                <?php else: ?>
                                    <span class="text-success small">
                                        <i class="fa-solid fa-check me-1"></i> Standard FIFO rotation.
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($days < 0 || $days <= 2): ?>
                                        <a href="add-wastage.php?food_id=<?= $item['id'] ?>" class="btn btn-danger fw-semibold" title="Discard and Log Wastage">
                                            <i class="fa-solid fa-trash-can me-1"></i> Discard
                                        </a>
                                    <?php endif; ?>
                                    <a href="food-details.php?id=<?= $item['id'] ?>" class="btn btn-outline-secondary" title="View Profile">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="edit-food.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary" title="Edit Item">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
