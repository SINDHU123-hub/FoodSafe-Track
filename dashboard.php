<?php
/**
 * FoodSafe Track - Main Operational Dashboard
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
syncExpiryAlerts($pdo);

$pageTitle = 'Safety Dashboard';

// -----------------------------------------------------------------------------
// Fetch Metrics from Database
// -----------------------------------------------------------------------------
try {
    // 1. Total Food Items
    $stmt = $pdo->query("SELECT COUNT(*) AS total_items, COALESCE(SUM(quantity), 0) AS total_qty FROM food_items WHERE quantity > 0");
    $invStats = $stmt->fetch();
    $totalItems = (int)($invStats['total_items'] ?? 0);
    $totalQuantity = (float)($invStats['total_qty'] ?? 0);

    // 2. Expired Items
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM food_items WHERE expiry_date < CURDATE() AND quantity > 0");
    $expiredCount = (int)($stmt->fetch()['total'] ?? 0);

    // 3. Critical Items (0 to 2 days remaining)
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM food_items WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY) AND quantity > 0");
    $criticalCount = (int)($stmt->fetch()['total'] ?? 0);

    // 4. Near-Expiry Items (3 to 7 days remaining)
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM food_items WHERE expiry_date > DATE_ADD(CURDATE(), INTERVAL 2 DAY) AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND quantity > 0");
    $nearExpiryCount = (int)($stmt->fetch()['total'] ?? 0);

    // 5. Safe Items (> 7 days remaining)
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM food_items WHERE expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND quantity > 0");
    $safeCount = (int)($stmt->fetch()['total'] ?? 0);

    // 6. Wastage Metrics
    $stmt = $pdo->query("SELECT COUNT(*) AS total_records, COALESCE(SUM(quantity), 0) AS total_waste FROM wastage");
    $wasteStats = $stmt->fetch();
    $totalWasteRecords = (int)($wasteStats['total_records'] ?? 0);
    $totalWasteQty = (float)($wasteStats['total_waste'] ?? 0);

    // Month wastage
    $stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) AS month_waste FROM wastage WHERE MONTH(wastage_date) = MONTH(CURDATE()) AND YEAR(wastage_date) = YEAR(CURDATE())");
    $monthWasteQty = (float)($stmt->fetch()['month_waste'] ?? 0);

    // 7. Suppliers Count
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM suppliers");
    $supplierCount = (int)($stmt->fetch()['total'] ?? 0);

    // 8. Urgent Attention Watchlist (Expired & Critical <= 7 days)
    $stmt = $pdo->query("
        SELECT f.*, c.category_name, s.supplier_name 
        FROM food_items f
        JOIN categories c ON f.category_id = c.id
        LEFT JOIN suppliers s ON f.supplier_id = s.id
        WHERE f.quantity > 0 AND f.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY f.expiry_date ASC
        LIMIT 6
    ");
    $urgentItems = $stmt->fetchAll();

    // 9. Recent Wastage Events
    $stmt = $pdo->query("
        SELECT w.*, f.food_name, f.batch_number, f.unit, c.category_name
        FROM wastage w
        JOIN food_items f ON w.food_item_id = f.id
        JOIN categories c ON f.category_id = c.id
        ORDER BY w.wastage_date DESC, w.id DESC
        LIMIT 5
    ");
    $recentWastage = $stmt->fetchAll();

    // 10. Category Breakdown for Chart
    $stmt = $pdo->query("
        SELECT c.category_name, COUNT(f.id) AS item_count
        FROM categories c
        LEFT JOIN food_items f ON c.id = f.category_id AND f.quantity > 0
        GROUP BY c.id, c.category_name
        ORDER BY item_count DESC
    ");
    $categoryData = $stmt->fetchAll();

    // 11. Wastage Reasons Breakdown for Chart
    $stmt = $pdo->query("
        SELECT reason, COUNT(*) as count, SUM(quantity) as qty
        FROM wastage
        GROUP BY reason
        ORDER BY qty DESC
    ");
    $wastageReasonData = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error fetching dashboard statistics: " . $e->getMessage());
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- Dashboard Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">Safety &amp; Expiry Overview</h2>
        <p class="text-muted small mb-0">Live status of your storage inventory, expiry countdowns, and safety compliance.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="add-food.php" class="btn btn-primary shadow-sm rounded-pill px-3">
            <i class="fa-solid fa-plus me-1"></i> Add Food Item
        </a>
        <a href="add-wastage.php" class="btn btn-outline-danger shadow-sm rounded-pill px-3">
            <i class="fa-solid fa-trash-can me-1"></i> Log Wastage
        </a>
        <a href="reports.php" class="btn btn-outline-secondary shadow-sm rounded-pill px-3">
            <i class="fa-solid fa-chart-column me-1"></i> View Reports
        </a>
    </div>
</div>

<!-- Critical Warning Banner (If expired or critical items exist) -->
<?php if ($expiredCount > 0 || $criticalCount > 0): ?>
<div class="card border-danger border-2 bg-danger-subtle mb-4 rounded-3 shadow-sm">
    <div class="card-body p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center">
            <div class="bg-danger text-white rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                <i class="fa-solid fa-triangle-exclamation fs-5"></i>
            </div>
            <div>
                <h5 class="fw-bold text-danger mb-0">Food Safety Action Required!</h5>
                <p class="text-danger-emphasis small mb-0">
                    You have <strong><?= $expiredCount ?> expired item(s)</strong> and <strong><?= $criticalCount ?> item(s) expiring within 48 hours</strong> in your storage.
                </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="expiry-tracker.php?filter=expired" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold">
                View Expired Items
            </a>
            <a href="expiry-tracker.php?filter=critical" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold">
                View Critical Stock
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Primary Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Total Active Inventory -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase">Total Inventory</span>
                <div class="stat-icon primary">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
            </div>
            <h3 class="fw-bold text-dark mb-1"><?= number_format($totalItems) ?></h3>
            <div class="text-muted small">
                <span class="text-success fw-semibold"><i class="fa-solid fa-layer-group"></i> Active Batches</span>
                &bull; <?= number_format($totalQuantity, 1) ?> units in stock
            </div>
        </div>
    </div>

    <!-- Near Expiry Items (<= 7 days) -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase">Near Expiry (&le; 7d)</span>
                <div class="stat-icon warning">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>
            <h3 class="fw-bold text-dark mb-1"><?= number_format($nearExpiryCount + $criticalCount) ?></h3>
            <div class="text-muted small">
                <span class="text-danger fw-semibold"><?= $criticalCount ?> Critical (&le; 2d)</span>
                &bull; <?= $nearExpiryCount ?> Warning (3-7d)
            </div>
        </div>
    </div>

    <!-- Expired Stock -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase">Expired Food</span>
                <div class="stat-icon danger">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <h3 class="fw-bold <?= $expiredCount > 0 ? 'text-danger' : 'text-dark' ?> mb-1">
                <?= number_format($expiredCount) ?>
            </h3>
            <div class="text-muted small">
                <?php if ($expiredCount > 0): ?>
                    <span class="text-danger fw-bold"><i class="fa-solid fa-bell"></i> Immediate removal needed</span>
                <?php else: ?>
                    <span class="text-success fw-semibold"><i class="fa-solid fa-check"></i> Zero expired food</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Total Wastage -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-bold text-uppercase">Wastage Logged</span>
                <div class="stat-icon info">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
            </div>
            <h3 class="fw-bold text-dark mb-1"><?= number_format($totalWasteQty, 1) ?></h3>
            <div class="text-muted small">
                <span class="text-primary fw-semibold"><?= number_format($monthWasteQty, 1) ?> this month</span>
                &bull; <?= $totalWasteRecords ?> incidents
            </div>
        </div>
    </div>
</div>

<!-- Visual Analytics Charts Row -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Expiry Status Breakdown -->
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-chart-pie text-success me-2"></i> Expiry Health Status
                </h6>
                <a href="expiry-tracker.php" class="text-primary small text-decoration-none fw-semibold">View Matrix &rarr;</a>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                <div style="position: relative; height: 230px; width: 100%;">
                    <canvas id="expiryStatusChart"></canvas>
                </div>
                <div class="d-flex justify-content-around w-100 pt-3 border-top mt-3 text-center small">
                    <div>
                        <div class="fw-bold text-success"><?= $safeCount ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Safe (&gt;7d)</div>
                    </div>
                    <div>
                        <div class="fw-bold text-warning"><?= $nearExpiryCount ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Near (3-7d)</div>
                    </div>
                    <div>
                        <div class="fw-bold text-danger"><?= $criticalCount ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Critical (&le;2d)</div>
                    </div>
                    <div>
                        <div class="fw-bold text-danger-emphasis"><?= $expiredCount ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Expired</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Inventory by Category -->
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-chart-simple text-primary me-2"></i> Inventory Items by Category
                </h6>
                <a href="inventory.php" class="text-primary small text-decoration-none fw-semibold">Full Inventory &rarr;</a>
            </div>
            <div class="card-body p-4">
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row: Watchlist & Recent Wastage -->
<div class="row g-4">
    <!-- Watchlist Table -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Priority Expiry Watchlist
                </h6>
                <a href="expiry-tracker.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All Tracker</a>
            </div>
            <div class="table-responsive">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th>Food Item &amp; Batch</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Expiry Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($urgentItems)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-shield-heart text-success fs-3 mb-2 d-block"></i>
                                    All inventory items are currently well within safe shelf-life dates.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($urgentItems as $item): ?>
                                <tr>
                                    <td>
                                        <a href="food-details.php?id=<?= $item['id'] ?>" class="text-decoration-none fw-semibold text-dark d-block">
                                            <?= htmlspecialchars($item['food_name']) ?>
                                        </a>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($item['batch_number']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($item['category_name']) ?></span>
                                    </td>
                                    <td>
                                        <?= number_format($item['quantity'], 2) ?> <span class="small text-muted"><?= htmlspecialchars($item['unit']) ?></span>
                                    </td>
                                    <td>
                                        <?= getExpiryBadge($item['expiry_date']) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="add-wastage.php?food_id=<?= $item['id'] ?>" class="btn btn-outline-danger" title="Record Waste">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                            <a href="food-details.php?id=<?= $item['id'] ?>" class="btn btn-outline-secondary" title="Details">
                                                <i class="fa-solid fa-eye"></i>
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
    </div>

    <!-- Recent Wastage Events Table -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-clock-rotate-left text-danger me-2"></i> Recent Wastage Activity
                </h6>
                <a href="wastage.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">All Logs</a>
            </div>
            <div class="table-responsive">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Reason</th>
                            <th class="text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentWastage)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-leaf text-success fs-3 mb-2 d-block"></i>
                                    No food wastage recorded. Great job conserving resources!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentWastage as $waste): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($waste['food_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($waste['batch_number']) ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-danger"><?= number_format($waste['quantity'], 2) ?></span>
                                        <small class="text-muted"><?= htmlspecialchars($waste['unit']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <?= htmlspecialchars($waste['reason']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end small text-muted">
                                        <?= formatDate($waste['wastage_date'], 'M d') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script Initializer -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Expiry Status Doughnut Chart
    const expiryCtx = document.getElementById('expiryStatusChart').getContext('2d');
    new Chart(expiryCtx, {
        type: 'doughnut',
        data: {
            labels: ['Safe (>7 Days)', 'Near Expiry (3-7 Days)', 'Critical (<=2 Days)', 'Expired'],
            datasets: [{
                data: [<?= $safeCount ?>, <?= $nearExpiryCount ?>, <?= $criticalCount ?>, <?= $expiredCount ?>],
                backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444'],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { family: 'Inter', size: 11 } }
                }
            },
            cutout: '70%'
        }
    });

    // 2. Category Bar Chart
    const catLabels = <?= json_encode(array_column($categoryData, 'category_name')) ?>;
    const catCounts = <?= json_encode(array_column($categoryData, 'item_count')) ?>;
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: [{
                label: 'Batches in Stock',
                data: catCounts,
                backgroundColor: '#059669',
                borderRadius: 6,
                maxBarThickness: 32
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, font: { family: 'Inter' } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Inter', size: 11 } }
                }
            }
        }
    });
});
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
