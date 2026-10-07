<?php
/**
 * FoodSafe Track - Food Inventory Management
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
syncExpiryAlerts($pdo);

$pageTitle = 'Food Inventory Management';

// Fetch filter options
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll();

// -----------------------------------------------------------------------------
// Handle Search, Filters, and Sorting
// -----------------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterSupplier = trim($_GET['supplier'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$sortBy = trim($_GET['sort'] ?? 'expiry_asc');

$whereConditions = ["f.quantity > 0"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(f.food_name LIKE ? OR f.batch_number LIKE ? OR f.storage_location LIKE ?)";
    $searchWildcard = "%{$search}%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if (!empty($filterCategory)) {
    $whereConditions[] = "f.category_id = ?";
    $params[] = (int)$filterCategory;
}

if (!empty($filterSupplier)) {
    $whereConditions[] = "f.supplier_id = ?";
    $params[] = (int)$filterSupplier;
}

if (!empty($filterStatus)) {
    if ($filterStatus === 'expired') {
        $whereConditions[] = "f.expiry_date < CURDATE()";
    } elseif ($filterStatus === 'critical') {
        $whereConditions[] = "f.expiry_date >= CURDATE() AND f.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)";
    } elseif ($filterStatus === 'near_expiry') {
        $whereConditions[] = "f.expiry_date > DATE_ADD(CURDATE(), INTERVAL 2 DAY) AND f.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($filterStatus === 'safe') {
        $whereConditions[] = "f.expiry_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
}

$whereSql = implode(" AND ", $whereConditions);

// Order by clause
$orderSql = "f.expiry_date ASC";
if ($sortBy === 'expiry_desc') {
    $orderSql = "f.expiry_date DESC";
} elseif ($sortBy === 'name_asc') {
    $orderSql = "f.food_name ASC";
} elseif ($sortBy === 'qty_desc') {
    $orderSql = "f.quantity DESC";
} elseif ($sortBy === 'purchased_desc') {
    $orderSql = "f.purchase_date DESC";
}

$query = "
    SELECT f.*, c.category_name, s.supplier_name 
    FROM food_items f
    JOIN categories c ON f.category_id = c.id
    LEFT JOIN suppliers s ON f.supplier_id = s.id
    WHERE {$whereSql}
    ORDER BY {$orderSql}
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$foodItems = $stmt->fetchAll();

// -----------------------------------------------------------------------------
// CSV Export Handler
// -----------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=FoodSafe_Inventory_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');

    // CSV header row
    fputcsv($output, ['ID', 'Food Name', 'Batch Number', 'Category', 'Supplier', 'Quantity', 'Unit', 'Storage Location', 'Purchase Date', 'Expiry Date', 'Days Remaining', 'Status', 'Safety Notes']);

    foreach ($foodItems as $row) {
        $days = getDaysRemaining($row['expiry_date']);
        $statusText = $days < 0 ? 'Expired' : ($days <= 2 ? 'Critical' : ($days <= 7 ? 'Near Expiry' : 'Safe'));
        fputcsv($output, [
            $row['id'],
            $row['food_name'],
            $row['batch_number'],
            $row['category_name'],
            $row['supplier_name'] ?? 'None',
            $row['quantity'],
            $row['unit'],
            $row['storage_location'],
            $row['purchase_date'],
            $row['expiry_date'],
            $days,
            $statusText,
            $row['food_safety_notes']
        ]);
    }
    fclose($output);
    exit;
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">Food Inventory Management</h2>
        <p class="text-muted small mb-0">Track all stored food batches, quantities, locations, and shelf-life compliance.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="inventory.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print View
        </button>
        <a href="add-food.php" class="btn btn-primary rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Add Food Item
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-3">
        <form method="GET" action="inventory.php" class="row g-2 align-items-end">
            <!-- Search Keyword -->
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Search Keywords</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Name, batch, or location..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <!-- Filter Category -->
            <div class="col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($filterCategory == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Supplier -->
            <div class="col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">Supplier</label>
                <select name="supplier" class="form-select form-select-sm">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= ($filterSupplier == $sup['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sup['supplier_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Status -->
            <div class="col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">Expiry Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="expired" <?= ($filterStatus === 'expired') ? 'selected' : '' ?>>Expired (&lt; 0d)</option>
                    <option value="critical" <?= ($filterStatus === 'critical') ? 'selected' : '' ?>>Critical (&le; 2d)</option>
                    <option value="near_expiry" <?= ($filterStatus === 'near_expiry') ? 'selected' : '' ?>>Near Expiry (3-7d)</option>
                    <option value="safe" <?= ($filterStatus === 'safe') ? 'selected' : '' ?>>Safe (&gt; 7d)</option>
                </select>
            </div>

            <!-- Sort By -->
            <div class="col-sm-6 col-md-2">
                <label class="form-label small text-muted mb-1">Sort By</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="expiry_asc" <?= ($sortBy === 'expiry_asc') ? 'selected' : '' ?>>Expiry: Earliest First</option>
                    <option value="expiry_desc" <?= ($sortBy === 'expiry_desc') ? 'selected' : '' ?>>Expiry: Latest First</option>
                    <option value="name_asc" <?= ($sortBy === 'name_asc') ? 'selected' : '' ?>>Food Name (A-Z)</option>
                    <option value="qty_desc" <?= ($sortBy === 'qty_desc') ? 'selected' : '' ?>>Quantity (High-Low)</option>
                    <option value="purchased_desc" <?= ($sortBy === 'purchased_desc') ? 'selected' : '' ?>>Purchase Date (Newest)</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                    <i class="fa-solid fa-filter"></i>
                </button>
                <a href="inventory.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory List Table Card -->
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-boxes-stacked text-success me-2"></i> Current Inventory List
        </h6>
        <span class="badge bg-secondary-subtle text-secondary rounded-pill">
            <?= count($foodItems) ?> <?= count($foodItems) === 1 ? 'Item' : 'Items' ?> Found
        </span>
    </div>
    
    <div class="table-responsive">
        <table class="table custom-table align-middle">
            <thead>
                <tr>
                    <th>Food Item &amp; Batch</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th>In Stock</th>
                    <th>Storage Location</th>
                    <th>Expiry Date</th>
                    <th>Expiry Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($foodItems)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fs-2 mb-2 text-secondary d-block"></i>
                            No food items match the current search or filter criteria.
                            <div class="mt-2">
                                <a href="inventory.php" class="btn btn-sm btn-outline-secondary rounded-pill me-2">Clear Filters</a>
                                <a href="add-food.php" class="btn btn-sm btn-primary rounded-pill">Add New Item</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($foodItems as $item): ?>
                        <tr>
                            <td>
                                <a href="food-details.php?id=<?= $item['id'] ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                    <?= htmlspecialchars($item['food_name']) ?>
                                </a>
                                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($item['batch_number']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <?= htmlspecialchars($item['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="small text-muted">
                                    <?= htmlspecialchars($item['supplier_name'] ?? 'Direct / N/A') ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-dark"><?= number_format($item['quantity'], 2) ?></strong>
                                <span class="small text-muted"><?= htmlspecialchars($item['unit']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                    <i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($item['storage_location']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><?= formatDate($item['expiry_date']) ?></div>
                                <small class="text-muted" style="font-size: 0.75rem;">Pur: <?= formatDate($item['purchase_date'], 'M d, y') ?></small>
                            </td>
                            <td>
                                <?= getExpiryBadge($item['expiry_date']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="food-details.php?id=<?= $item['id'] ?>" class="btn btn-outline-secondary" title="View Full Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="edit-food.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary" title="Edit Item">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="add-wastage.php?food_id=<?= $item['id'] ?>" class="btn btn-outline-warning" title="Record Wastage">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                    <a href="javascript:void(0)" onclick="confirmAction('Are you sure you want to permanently delete \'<?= addslashes(htmlspecialchars($item['food_name'])) ?>\'?', 'delete-food.php?id=<?= $item['id'] ?>')" class="btn btn-outline-danger" title="Delete Food Item">
                                        <i class="fa-solid fa-trash"></i>
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
