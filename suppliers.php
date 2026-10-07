<?php
/**
 * FoodSafe Track - Suppliers Directory
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Suppliers & Distributors Directory';

// Search
$search = trim($_GET['search'] ?? '');
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where = "(s.supplier_name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $wildcard = "%{$search}%";
    $params = [$wildcard, $wildcard, $wildcard, $wildcard];
}

$query = "
    SELECT s.*, COUNT(f.id) AS supplied_items_count
    FROM suppliers s
    LEFT JOIN food_items f ON s.id = f.supplier_id AND f.quantity > 0
    WHERE {$where}
    GROUP BY s.id
    ORDER BY s.supplier_name ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">Food Suppliers Directory</h2>
        <p class="text-muted small mb-0">Manage food distributors, wholesale vendors, and supply chain contacts.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="add-supplier.php" class="btn btn-primary rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Add New Supplier
        </a>
    </div>
</div>

<!-- Search Bar Card -->
<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-3">
        <form method="GET" action="suppliers.php" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by vendor name, contact person, or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="suppliers.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-truck-field text-primary me-2"></i> Registered Vendors
        </h6>
        <span class="badge bg-secondary-subtle text-secondary rounded-pill"><?= count($suppliers) ?> Vendors</span>
    </div>
    <div class="table-responsive">
        <table class="table custom-table align-middle">
            <thead>
                <tr>
                    <th>Supplier Name</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email Address</th>
                    <th>Facility Address</th>
                    <th>Active Items</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-truck-ramp-box fs-2 mb-2 text-secondary d-block"></i>
                            No suppliers found matching your query.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($s['supplier_name']) ?></strong>
                                <small class="text-muted">Added: <?= formatDate($s['created_at'], 'M d, Y') ?></small>
                            </td>
                            <td>
                                <span class="fw-semibold text-secondary">
                                    <i class="fa-regular fa-user me-1 text-muted"></i><?= htmlspecialchars($s['contact_person'] ?? 'Account Rep') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($s['phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($s['phone']) ?>" class="text-decoration-none text-dark small">
                                        <i class="fa-solid fa-phone me-1 text-success"></i><?= htmlspecialchars($s['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($s['email']) ?>" class="text-decoration-none text-dark small">
                                        <i class="fa-regular fa-envelope me-1 text-primary"></i><?= htmlspecialchars($s['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;" title="<?= htmlspecialchars($s['address'] ?? '') ?>">
                                    <?= htmlspecialchars($s['address'] ?? 'Not provided') ?>
                                </small>
                            </td>
                            <td>
                                <a href="inventory.php?supplier=<?= $s['id'] ?>" class="badge bg-light text-primary border text-decoration-none" title="Filter inventory by this supplier">
                                    <i class="fa-solid fa-boxes-stacked me-1"></i><?= $s['supplied_items_count'] ?> Batches
                                </a>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="edit-supplier.php?id=<?= $s['id'] ?>" class="btn btn-outline-primary" title="Edit Vendor">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="javascript:void(0)" onclick="confirmAction('Delete supplier \'<?= addslashes(htmlspecialchars($s['supplier_name'])) ?>\'? (Associated food items will remain with supplier set to None)', 'delete-supplier.php?id=<?= $s['id'] ?>')" class="btn btn-outline-danger" title="Delete Vendor">
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
