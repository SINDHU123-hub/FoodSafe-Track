<?php
/**
 * FoodSafe Track - Safety & Inventory Analytics Reports
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
syncExpiryAlerts($pdo);

$pageTitle = 'Food Safety & Expiry Reports';

// -----------------------------------------------------------------------------
// Date Filters
// -----------------------------------------------------------------------------
$range = trim($_GET['range'] ?? '30days');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$today = date('Y-m-d');
if ($range === '7days') {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = $today;
} elseif ($range === '30days') {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = $today;
} elseif ($range === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = $today;
} elseif ($range === 'this_year') {
    $startDate = date('Y-01-01');
    $endDate = $today;
} elseif ($range === 'all') {
    $startDate = '2000-01-01';
    $endDate = $today;
}

if (empty($startDate)) $startDate = date('Y-m-d', strtotime('-30 days'));
if (empty($endDate)) $endDate = $today;

// -----------------------------------------------------------------------------
// 1. Overall Expiry Compliance Matrix
// -----------------------------------------------------------------------------
$totalItemsInStock = (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0")->fetchColumn();
$totalExpiredStock = (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date < CURDATE()")->fetchColumn();
$totalCriticalStock = (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)")->fetchColumn();
$totalNearExpiryStock = (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 2 DAY) AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$totalSafeStock = (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE quantity > 0 AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$complianceRate = ($totalItemsInStock > 0) ? round((($totalSafeStock + $totalNearExpiryStock) / $totalItemsInStock) * 100) : 100;

// -----------------------------------------------------------------------------
// 2. Wastage Summary in Selected Date Range
// -----------------------------------------------------------------------------
$wasteRangeStmt = $pdo->prepare("
    SELECT COUNT(*) as incidents, COALESCE(SUM(quantity), 0) as total_qty
    FROM wastage
    WHERE wastage_date BETWEEN ? AND ?
");
$wasteRangeStmt->execute([$startDate, $endDate]);
$wasteRangeStats = $wasteRangeStmt->fetch();
$rangeWasteIncidents = (int)($wasteRangeStats['incidents'] ?? 0);
$rangeWasteQty = (float)($wasteRangeStats['total_qty'] ?? 0);

// Wastage reasons breakdown
$reasonStmt = $pdo->prepare("
    SELECT reason, COUNT(*) as count, SUM(quantity) as qty
    FROM wastage
    WHERE wastage_date BETWEEN ? AND ?
    GROUP BY reason
    ORDER BY qty DESC
");
$reasonStmt->execute([$startDate, $endDate]);
$wastageByReason = $reasonStmt->fetchAll();

// Top wasted items
$topWastedStmt = $pdo->prepare("
    SELECT f.food_name, f.unit, SUM(w.quantity) as total_qty, COUNT(w.id) as events
    FROM wastage w
    JOIN food_items f ON w.food_item_id = f.id
    WHERE w.wastage_date BETWEEN ? AND ?
    GROUP BY f.food_name, f.unit
    ORDER BY total_qty DESC
    LIMIT 5
");
$topWastedStmt->execute([$startDate, $endDate]);
$topWastedItems = $topWastedStmt->fetchAll();

// -----------------------------------------------------------------------------
// 3. Category Breakdown
// -----------------------------------------------------------------------------
$catStmt = $pdo->query("
    SELECT c.category_name, COUNT(f.id) as total_batches, COALESCE(SUM(f.quantity), 0) as total_qty
    FROM categories c
    LEFT JOIN food_items f ON c.id = f.category_id AND f.quantity > 0
    GROUP BY c.id, c.category_name
    ORDER BY total_qty DESC
");
$categoryReport = $catStmt->fetchAll();

// -----------------------------------------------------------------------------
// 4. Supplier Distribution
// -----------------------------------------------------------------------------
$supStmt = $pdo->query("
    SELECT s.supplier_name, s.contact_person, COUNT(f.id) as batches_count, COALESCE(SUM(f.quantity), 0) as total_units
    FROM suppliers s
    LEFT JOIN food_items f ON s.id = f.supplier_id AND f.quantity > 0
    GROUP BY s.id, s.supplier_name
    ORDER BY batches_count DESC
");
$supplierReport = $supStmt->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<!-- Print-Only Header -->
<div class="print-only-header">
    <h2 class="fw-bold mb-1">FoodSafe Track &ndash; Food Safety &amp; Expiry Report</h2>
    <p class="text-muted small mb-0">Generated on <?= date('F d, Y - h:i A') ?> &bull; Period: <?= formatDate($startDate) ?> to <?= formatDate($endDate) ?></p>
</div>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">Safety &amp; Expiry Analytics</h2>
        <p class="text-muted small mb-0">Audit inventory shelf-life compliance, food wastage causes, and supplier metrics.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print / PDF Report
        </button>
        <a href="inventory.php?export=csv" class="btn btn-outline-success rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export Inventory CSV
        </a>
        <a href="wastage.php?export=csv" class="btn btn-outline-danger rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export Wastage CSV
        </a>
    </div>
</div>

<!-- Date Filter Ribbon -->
<div class="card shadow-sm border-0 mb-4 rounded-3 no-print">
    <div class="card-body p-3">
        <form method="GET" action="reports.php" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label class="form-label small text-muted mb-1">Quick Date Range</label>
                <select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="7days" <?= ($range === '7days') ? 'selected' : '' ?>>Past 7 Days</option>
                    <option value="30days" <?= ($range === '30days') ? 'selected' : '' ?>>Past 30 Days</option>
                    <option value="this_month" <?= ($range === 'this_month') ? 'selected' : '' ?>>This Month (<?= date('F') ?>)</option>
                    <option value="this_year" <?= ($range === 'this_year') ? 'selected' : '' ?>>This Year (<?= date('Y') ?>)</option>
                    <option value="all" <?= ($range === 'all') ? 'selected' : '' ?>>All Time</option>
                    <option value="custom" <?= ($range === 'custom') ? 'selected' : '' ?>>Custom Dates</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-3">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
            </div>

            <div class="col-sm-6 col-md-3">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
            </div>

            <div class="col-sm-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fa-solid fa-filter me-1"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Section 1: KPI Metrics -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <span class="text-muted small fw-bold text-uppercase">Safety Compliance Index</span>
            <div class="d-flex align-items-baseline justify-content-between mt-2">
                <h3 class="fw-bold <?= $complianceRate >= 90 ? 'text-success' : ($complianceRate >= 75 ? 'text-warning' : 'text-danger') ?> mb-0">
                    <?= $complianceRate ?>%
                </h3>
                <span class="badge <?= $complianceRate >= 90 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
                    <?= $complianceRate >= 90 ? 'Optimal' : 'Needs Review' ?>
                </span>
            </div>
            <span class="text-muted small mt-2 d-block">Percentage of safe &amp; active items</span>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <span class="text-muted small fw-bold text-uppercase">Items at Risk</span>
            <div class="d-flex align-items-baseline justify-content-between mt-2">
                <h3 class="fw-bold text-danger mb-0">
                    <?= $totalExpiredStock + $totalCriticalStock ?>
                </h3>
                <span class="badge bg-danger-subtle text-danger">Immediate Action</span>
            </div>
            <span class="text-muted small mt-2 d-block"><?= $totalExpiredStock ?> expired, <?= $totalCriticalStock ?> critical</span>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <span class="text-muted small fw-bold text-uppercase">Period Wastage Volume</span>
            <div class="d-flex align-items-baseline justify-content-between mt-2">
                <h3 class="fw-bold text-dark mb-0">
                    <?= number_format($rangeWasteQty, 2) ?>
                </h3>
                <span class="badge bg-danger-subtle text-danger"><?= $rangeWasteIncidents ?> Events</span>
            </div>
            <span class="text-muted small mt-2 d-block">Units discarded in selected range</span>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <span class="text-muted small fw-bold text-uppercase">Active Storage Batches</span>
            <div class="d-flex align-items-baseline justify-content-between mt-2">
                <h3 class="fw-bold text-primary mb-0">
                    <?= $totalItemsInStock ?>
                </h3>
                <span class="badge bg-primary-subtle text-primary">In Storage</span>
            </div>
            <span class="text-muted small mt-2 d-block">Monitored across all locations</span>
        </div>
    </div>
</div>

<!-- Section 2: Charts Row -->
<div class="row g-4 mb-4">
    <!-- Wastage Reasons Doughnut Chart -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-header bg-white">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-chart-pie text-danger me-2"></i> Food Wastage by Disposal Reason
                </h6>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                <?php if (empty($wastageByReason)): ?>
                    <p class="text-muted small py-5 text-center">No food wastage logged during this period.</p>
                <?php else: ?>
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="wastageReasonChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Distribution Bar Chart -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-header bg-white">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-chart-column text-primary me-2"></i> Stock Volume by Category
                </h6>
            </div>
            <div class="card-body p-4">
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="categoryVolumeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section 3: Tables Detail Grid -->
<div class="row g-4 mb-4">
    <!-- Top Wasted Food Items -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-header bg-white">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Top Discarded Food Items
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th>Food Item</th>
                            <th>Total Discarded</th>
                            <th>Incidents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topWastedItems)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted small">No items discarded in this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topWastedItems as $tw): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($tw['food_name']) ?></td>
                                    <td><strong class="text-danger"><?= number_format($tw['total_qty'], 2) ?></strong> <small class="text-muted"><?= htmlspecialchars($tw['unit']) ?></small></td>
                                    <td><span class="badge bg-light text-dark border"><?= $tw['events'] ?> times</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Category Breakdown Table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-header bg-white">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-boxes-stacked text-success me-2"></i> Inventory Status by Category
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Active Batches</th>
                            <th>Total Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryReport as $cr): ?>
                            <tr>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($cr['category_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= $cr['total_batches'] ?> batches</span></td>
                                <td><strong><?= number_format($cr['total_qty'], 2) ?></strong> units</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Section 4: Supplier Performance Table -->
<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-white">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-truck-field text-primary me-2"></i> Supplier Volume Distribution
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table custom-table align-middle">
            <thead>
                <tr>
                    <th>Supplier / Distributor</th>
                    <th>Representative</th>
                    <th>Active Batches Supplied</th>
                    <th>Total Units in Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($supplierReport)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted small">No supplier data available.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($supplierReport as $sr): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($sr['supplier_name']) ?></td>
                            <td class="text-secondary small"><?= htmlspecialchars($sr['contact_person'] ?? 'Account Rep') ?></td>
                            <td><span class="badge bg-light text-primary border"><?= $sr['batches_count'] ?> active batches</span></td>
                            <td><strong><?= number_format($sr['total_units'], 2) ?></strong> units</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Wastage Reasons Doughnut
    <?php if (!empty($wastageByReason)): ?>
    const wCtx = document.getElementById('wastageReasonChart').getContext('2d');
    new Chart(wCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_column($wastageByReason, 'reason')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('floatval', array_column($wastageByReason, 'qty'))) ?>,
                backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b', '#06b6d4'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Inter', size: 11 } } }
            }
        }
    });
    <?php endif; ?>

    // 2. Category Volume Bar
    const cCtx = document.getElementById('categoryVolumeChart').getContext('2d');
    new Chart(cCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($categoryReport, 'category_name')) ?>,
            datasets: [{
                label: 'Stock Quantity',
                data: <?= json_encode(array_map('floatval', array_column($categoryReport, 'total_qty'))) ?>,
                backgroundColor: '#10b981',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10 } } }
            }
        }
    });
});
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
