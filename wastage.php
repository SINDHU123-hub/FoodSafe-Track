<?php
/**
 * FoodSafe Track - Food Wastage Management
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Food Wastage Log & Analysis';

// Handle Filters
$filterReason = trim($_GET['reason'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($filterReason)) {
    $where[] = "w.reason = ?";
    $params[] = $filterReason;
}
if (!empty($startDate)) {
    $where[] = "w.wastage_date >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "w.wastage_date <= ?";
    $params[] = $endDate;
}

$whereSql = implode(" AND ", $where);

// Query wastage records
$query = "
    SELECT w.*, f.food_name, f.batch_number, f.unit, c.category_name, s.supplier_name
    FROM wastage w
    JOIN food_items f ON w.food_item_id = f.id
    JOIN categories c ON f.category_id = c.id
    LEFT JOIN suppliers s ON f.supplier_id = s.id
    WHERE {$whereSql}
    ORDER BY w.wastage_date DESC, w.id DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$wastageRecords = $stmt->fetchAll();

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=FoodSafe_Wastage_Report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');

    fputcsv($output, ['ID', 'Food Item', 'Batch Number', 'Category', 'Quantity Wasted', 'Unit', 'Reason', 'Wastage Date', 'Notes', 'Logged At']);
    foreach ($wastageRecords as $r) {
        fputcsv($output, [
            $r['id'],
            $r['food_name'],
            $r['batch_number'],
            $r['category_name'],
            $r['quantity'],
            $r['unit'],
            $r['reason'],
            $r['wastage_date'],
            $r['notes'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Summary Metrics
$totalWastedQty = (float)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM wastage")->fetchColumn();
$totalIncidents = (int)$pdo->query("SELECT COUNT(*) FROM wastage")->fetchColumn();

// Most common reason
$topReasonRow = $pdo->query("SELECT reason, COUNT(*) as c FROM wastage GROUP BY reason ORDER BY c DESC LIMIT 1")->fetch();
$topReason = $topReasonRow['reason'] ?? 'None';

// This month's wastage
$monthWastedQty = (float)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM wastage WHERE MONTH(wastage_date) = MONTH(CURDATE()) AND YEAR(wastage_date) = YEAR(CURDATE())")->fetchColumn();

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">Food Wastage Management</h2>
        <p class="text-muted small mb-0">Record and audit discarded stock to reduce environmental impact and business costs.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="wastage.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export Log
        </a>
        <a href="add-wastage.php" class="btn btn-danger rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Log Food Wastage
        </a>
    </div>
</div>

<!-- Wastage Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Total Wasted Qty</span>
                <div class="stat-icon danger">
                    <i class="fa-solid fa-scale-unbalanced"></i>
                </div>
            </div>
            <h3 class="fw-bold text-dark mb-1"><?= number_format($totalWastedQty, 2) ?></h3>
            <span class="text-muted small">Lifetime units discarded</span>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Wastage Incidents</span>
                <div class="stat-icon warning">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
            </div>
            <h3 class="fw-bold text-dark mb-1"><?= number_format($totalIncidents) ?></h3>
            <span class="text-muted small">Total logged discard events</span>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">This Month's Loss</span>
                <div class="stat-icon info">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
            </div>
            <h3 class="fw-bold text-dark mb-1"><?= number_format($monthWastedQty, 2) ?></h3>
            <span class="text-muted small">Units in <?= date('F Y') ?></span>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Leading Cause</span>
                <div class="stat-icon primary">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
            </div>
            <h4 class="fw-bold text-danger text-truncate mb-1" title="<?= htmlspecialchars($topReason) ?>">
                <?= htmlspecialchars($topReason) ?>
            </h4>
            <span class="text-muted small">Primary driver of disposal</span>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-3">
        <form method="GET" action="wastage.php" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Disposal Reason</label>
                <select name="reason" class="form-select form-select-sm">
                    <option value="">All Reasons</option>
                    <?php 
                    $reasons = ['Expired', 'Spoiled/Rotten', 'Damaged Packaging', 'Contaminated', 'Preparation Waste', 'Temperature Abuse', 'Other'];
                    foreach ($reasons as $r):
                    ?>
                        <option value="<?= $r ?>" <?= ($filterReason === $r) ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="wastage.php" class="btn btn-sm btn-outline-secondary" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Wastage Table -->
<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-trash-can text-danger me-2"></i> Food Wastage Audit Log
        </h6>
        <span class="badge bg-secondary-subtle text-secondary rounded-pill">
            <?= count($wastageRecords) ?> Records Found
        </span>
    </div>
    <div class="table-responsive">
        <table class="table custom-table align-middle">
            <thead>
                <tr>
                    <th>Food Item &amp; Batch</th>
                    <th>Category</th>
                    <th>Quantity Discarded</th>
                    <th>Reason for Wastage</th>
                    <th>Wastage Date</th>
                    <th>Staff Disposal Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wastageRecords)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-circle-check text-success fs-2 mb-2 d-block"></i>
                            No food wastage entries recorded under current filter parameters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($wastageRecords as $w): ?>
                        <tr>
                            <td>
                                <a href="food-details.php?id=<?= $w['food_item_id'] ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                    <?= htmlspecialchars($w['food_name']) ?>
                                </a>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($w['batch_number']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($w['category_name']) ?></span>
                            </td>
                            <td>
                                <strong class="text-danger fs-6"><?= number_format($w['quantity'], 2) ?></strong>
                                <span class="small text-muted"><?= htmlspecialchars($w['unit']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($w['reason']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-medium text-dark"><?= formatDate($w['wastage_date']) ?></span>
                            </td>
                            <td>
                                <span class="small text-muted"><?= htmlspecialchars($w['notes'] ?? 'None recorded') ?></span>
                            </td>
                            <td class="text-end">
                                <a href="javascript:void(0)" onclick="confirmAction('Delete this wastage entry? (Note: will not revert stock deductions automatically)', 'delete-wastage.php?id=<?= $w['id'] ?>')" class="btn btn-sm btn-outline-danger" title="Delete Log Entry">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
