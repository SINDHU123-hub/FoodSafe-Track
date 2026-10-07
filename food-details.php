<?php
/**
 * FoodSafe Track - Food Item Detailed Profile
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'Invalid food item requested.');
    header('Location: inventory.php');
    exit;
}

// Fetch item with category and supplier
$stmt = $pdo->prepare("
    SELECT f.*, c.category_name, s.supplier_name, s.contact_person, s.phone AS supplier_phone, s.email AS supplier_email
    FROM food_items f
    JOIN categories c ON f.category_id = c.id
    LEFT JOIN suppliers s ON f.supplier_id = s.id
    WHERE f.id = ?
");
$stmt->execute([$id]);
$food = $stmt->fetch();

if (!$food) {
    setFlash('danger', 'Food item record not found.');
    header('Location: inventory.php');
    exit;
}

$pageTitle = 'Details – ' . htmlspecialchars($food['food_name']);

// Expiry calculations
$daysRemaining = getDaysRemaining($food['expiry_date']);
$totalShelfDays = max(1, (strtotime($food['expiry_date']) - strtotime($food['purchase_date'])) / 86400);
$daysElapsed = max(0, (strtotime(date('Y-m-d')) - strtotime($food['purchase_date'])) / 86400);
$percentElapsed = min(100, max(0, round(($daysElapsed / $totalShelfDays) * 100)));

// Fetch wastage history for this food item
$wasteStmt = $pdo->prepare("SELECT * FROM wastage WHERE food_item_id = ? ORDER BY wastage_date DESC, id DESC");
$wasteStmt->execute([$id]);
$wastageHistory = $wasteStmt->fetchAll();

// Determine recommended food safety protocols by category
$safetyGuidelines = [
    'Dairy' => 'Maintain steady chilling between 1°C and 4°C. Check package seals daily for bloating or odor.',
    'Meat' => 'Strict HACCP cold chain below 2°C. Prevent cross-contamination with cooked foods. Cook to 74°C internal.',
    'Vegetables' => 'Maintain high humidity at 4°C - 7°C. Inspect for condensation and wilting daily.',
    'Fruits' => 'Store at 10°C - 15°C depending on variety. Separate ethylene-producing fruits from greens.',
    'Grains' => 'Dry storage room off floor on pallets. Keep ambient temp at 15°C - 21°C and humidity < 60%.',
    'Beverages' => 'Store out of direct sunlight. Chilled beverages must stay below 5°C after opening.',
    'Frozen' => 'Continuous deep freeze at -18°C or lower. Never refreeze thawed portions.',
    'Bakery' => 'Cool, dry, insect-free ambient storage (18°C - 22°C). Seal tight against moisture.',
    'Other' => 'Follow manufacturer label guidance and general food safety FIFO (First-In, First-Out) standards.'
];
$recommendedNote = $safetyGuidelines[$food['category_name']] ?? $safetyGuidelines['Other'];

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header & Nav -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 small text-muted mb-2">
        <a href="inventory.php" class="text-decoration-none text-muted">Inventory</a>
        <i class="fa-solid fa-chevron-right fs-6"></i>
        <span>Food Item Profile</span>
    </div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-3">
                <h2 class="h3 fw-bold text-dark mb-0"><?= htmlspecialchars($food['food_name']) ?></h2>
                <?= getExpiryBadge($food['expiry_date']) ?>
            </div>
            <p class="text-muted small mb-0 mt-1">Batch: <span class="font-monospace fw-semibold text-dark"><?= htmlspecialchars($food['batch_number']) ?></span> &bull; Storage: <strong><?= htmlspecialchars($food['storage_location']) ?></strong></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="edit-food.php?id=<?= $id ?>" class="btn btn-outline-primary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Item
            </a>
            <a href="add-wastage.php?food_id=<?= $id ?>" class="btn btn-outline-danger rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-trash-can me-1"></i> Record Wastage
            </a>
            <a href="javascript:void(0)" onclick="confirmAction('Permanently remove this food batch from the system?', 'delete-food.php?id=<?= $id ?>')" class="btn btn-outline-danger rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-trash me-1"></i> Delete
            </a>
            <a href="inventory.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Inventory
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Left Column: Core Data Cards -->
    <div class="col-lg-8">
        
        <!-- Shelf Life & Urgency Meter Card -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-hourglass-half text-warning me-2"></i> Shelf-Life Lifespan Progress
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <span class="text-muted small">Receiving Date</span>
                        <div class="fw-bold"><?= formatDate($food['purchase_date']) ?></div>
                    </div>
                    <div class="text-center">
                        <span class="text-muted small">Days Remaining</span>
                        <div class="h4 fw-bold mb-0 <?= $daysRemaining <= 2 ? 'text-danger' : ($daysRemaining <= 7 ? 'text-warning' : 'text-success') ?>">
                            <?= $daysRemaining < 0 ? abs($daysRemaining) . ' Days Expired' : $daysRemaining . ' Days Left' ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small">Expiry Date</span>
                        <div class="fw-bold"><?= formatDate($food['expiry_date']) ?></div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <?php 
                    $progressColor = 'bg-success';
                    if ($daysRemaining < 0) $progressColor = 'bg-danger';
                    elseif ($daysRemaining <= 2) $progressColor = 'bg-danger';
                    elseif ($daysRemaining <= 7) $progressColor = 'bg-warning';
                ?>
                <div class="progress rounded-pill my-3" style="height: 12px;">
                    <div class="progress-bar <?= $progressColor ?>" role="progressbar" style="width: <?= $percentElapsed ?>%;" aria-valuenow="<?= $percentElapsed ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Batch Lifespan: <?= (int)$totalShelfDays ?> days total</span>
                    <span><strong><?= $percentElapsed ?>%</strong> of shelf life consumed</span>
                </div>
            </div>
        </div>

        <!-- Comprehensive Details Grid -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-circle-info text-primary me-2"></i> Inventory Specifications
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Food Category</label>
                        <div class="fw-semibold text-dark fs-6">
                            <span class="badge bg-light text-dark border px-3 py-2"><?= htmlspecialchars($food['category_name']) ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Current Stock on Hand</label>
                        <div class="fw-bold text-dark fs-5">
                            <?= number_format($food['quantity'], 2) ?> <span class="fs-6 text-muted fw-normal"><?= htmlspecialchars($food['unit']) ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Storage Location Facility</label>
                        <div class="fw-semibold text-dark">
                            <i class="fa-solid fa-warehouse text-secondary me-1"></i> <?= htmlspecialchars($food['storage_location']) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small mb-1">Batch / Lot Tracking Number</label>
                        <div class="font-monospace fw-bold text-dark">
                            <?= htmlspecialchars($food['batch_number']) ?>
                        </div>
                    </div>
                    <div class="col-12 pt-2 border-top">
                        <label class="text-muted small mb-1">Staff Food Safety &amp; Handling Notes</label>
                        <div class="p-3 bg-light rounded-3 text-secondary small">
                            <?= !empty($food['food_safety_notes']) ? nl2br(htmlspecialchars($food['food_safety_notes'])) : '<em>No custom notes recorded for this batch.</em>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Associated Wastage History -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-trash-can text-danger me-2"></i> Wastage History for this Batch
                </h6>
                <a href="add-wastage.php?food_id=<?= $id ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                    <i class="fa-solid fa-plus me-1"></i> Log Wastage
                </a>
            </div>
            <div class="table-responsive">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th>Wasted Qty</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($wastageHistory)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">
                                    No wastage has been logged for this item.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($wastageHistory as $w): ?>
                                <tr>
                                    <td>
                                        <strong class="text-danger"><?= number_format($w['quantity'], 2) ?></strong> <?= htmlspecialchars($food['unit']) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= htmlspecialchars($w['reason']) ?></span>
                                    </td>
                                    <td class="small text-muted"><?= formatDate($w['wastage_date']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($w['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Right Column: Compliance & Supplier -->
    <div class="col-lg-4">
        
        <!-- Food Safety Compliance Advisory -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-shield-halved text-success me-2"></i> HACCP Food Safety Standard
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="bg-success-subtle text-success p-2 rounded-3 fs-5">
                        <i class="fa-solid fa-temperature-arrow-down"></i>
                    </div>
                    <div>
                        <strong class="text-dark small d-block"><?= htmlspecialchars($food['category_name']) ?> Protocol:</strong>
                        <p class="text-muted small mb-0 mt-1"><?= $recommendedNote ?></p>
                    </div>
                </div>
                <div class="alert alert-light border small text-muted mb-0">
                    <strong>FIFO Rule:</strong> Ensure this batch is utilized or prepped before newer batches with later expiry dates.
                </div>
            </div>
        </div>

        <!-- Supplier Info Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-truck-field text-primary me-2"></i> Supplier Information
                </h6>
            </div>
            <div class="card-body p-4">
                <?php if ($food['supplier_name']): ?>
                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($food['supplier_name']) ?></h6>
                    <div class="text-muted small mb-3">Contact: <?= htmlspecialchars($food['contact_person'] ?? 'Account Rep') ?></div>
                    
                    <ul class="list-unstyled small mb-3">
                        <li class="mb-2 text-muted">
                            <i class="fa-solid fa-phone text-secondary me-2"></i>
                            <?= htmlspecialchars($food['supplier_phone'] ?? 'N/A') ?>
                        </li>
                        <li class="mb-2 text-muted">
                            <i class="fa-solid fa-envelope text-secondary me-2"></i>
                            <?= htmlspecialchars($food['supplier_email'] ?? 'N/A') ?>
                        </li>
                    </ul>
                    <a href="suppliers.php" class="btn btn-sm btn-outline-secondary w-100 rounded-pill">
                        Manage Supplier Directory
                    </a>
                <?php else: ?>
                    <div class="text-center py-3 text-muted small">
                        <i class="fa-solid fa-circle-question fs-3 mb-2 d-block"></i>
                        No external supplier linked. Purchased directly or prepared in-house.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
