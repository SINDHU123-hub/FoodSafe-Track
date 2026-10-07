<?php
/**
 * FoodSafe Track - Record Food Wastage
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Record Food Wastage';
$errors = [];

// Pre-selected item if passed via URL
$preSelectedFoodId = filter_input(INPUT_GET, 'food_id', FILTER_VALIDATE_INT);

// Fetch all food items that have stock or were recently active
$stmt = $pdo->query("
    SELECT f.id, f.food_name, f.batch_number, f.quantity, f.unit, f.expiry_date, c.category_name
    FROM food_items f
    JOIN categories c ON f.category_id = c.id
    ORDER BY f.expiry_date ASC, f.food_name ASC
");
$foodItems = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_item_id = filter_input(INPUT_POST, 'food_item_id', FILTER_VALIDATE_INT);
    $quantity = trim($_POST['quantity'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $wastage_date = trim($_POST['wastage_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');
    $deduct_stock = isset($_POST['deduct_stock']);

    // Validation
    if (!$food_item_id) {
        $errors[] = 'Please select a valid food item from inventory.';
    }
    if ($quantity === '' || !is_numeric($quantity) || (float)$quantity <= 0) {
        $errors[] = 'Please enter a valid wastage quantity greater than 0.';
    }
    if (empty($reason)) {
        $errors[] = 'Please specify the reason for wastage.';
    }
    if (empty($wastage_date)) {
        $errors[] = 'Wastage date is required.';
    }

    // Check item exists
    if ($food_item_id && empty($errors)) {
        $checkStmt = $pdo->prepare("SELECT * FROM food_items WHERE id = ?");
        $checkStmt->execute([$food_item_id]);
        $targetItem = $checkStmt->fetch();

        if (!$targetItem) {
            $errors[] = 'The selected food item was not found in the database.';
        } elseif ((float)$quantity > (float)$targetItem['quantity']) {
            $errors[] = 'Discard quantity (' . (float)$quantity . ') cannot exceed current stock on hand (' . (float)$targetItem['quantity'] . ' ' . $targetItem['unit'] . ').';
        }
    }

    if (empty($errors) && isset($targetItem)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert Wastage Record
            $insStmt = $pdo->prepare("
                INSERT INTO wastage (food_item_id, quantity, reason, wastage_date, notes, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insStmt->execute([
                $food_item_id,
                (float)$quantity,
                $reason,
                $wastage_date,
                $notes
            ]);

            // 2. Deduct from current inventory stock if requested
            if ($deduct_stock) {
                $newStock = max(0.00, (float)$targetItem['quantity'] - (float)$quantity);
                $upStmt = $pdo->prepare("UPDATE food_items SET quantity = ?, updated_at = NOW() WHERE id = ?");
                $upStmt->execute([$newStock, $food_item_id]);
            }

            $pdo->commit();

            // Re-sync alerts
            syncExpiryAlerts($pdo);

            setFlash('success', 'Wastage of ' . (float)$quantity . ' ' . htmlspecialchars($targetItem['unit']) . ' of "' . htmlspecialchars($targetItem['food_name']) . '" logged successfully.');
            header('Location: wastage.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Database transaction failed: ' . $e->getMessage();
        }
    }
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs & Header -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 small text-muted mb-2">
        <a href="wastage.php" class="text-decoration-none text-muted">Wastage</a>
        <i class="fa-solid fa-chevron-right fs-6"></i>
        <span>Log Waste</span>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">Record Food Wastage</h2>
            <p class="text-muted small mb-0">Log discarded food items to maintain accurate inventory and analyze loss patterns.</p>
        </div>
        <a href="wastage.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Log
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">
        <div class="fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-2"></i>Please resolve the following issues:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4 p-md-5">
        <form method="POST" action="add-wastage.php" autocomplete="off">
            <div class="row g-4">
                
                <div class="col-12 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-trash-can text-danger me-2"></i> Food Wastage Entry Details
                    </h5>
                    <p class="text-muted small mb-0">Select the batch and specify the exact quantity to discard.</p>
                </div>

                <!-- Food Item Selection -->
                <div class="col-md-8">
                    <label for="food_item_id" class="form-label">Select Food Item / Batch <span class="text-danger">*</span></label>
                    <select class="form-select" id="food_item_id" name="food_item_id" required>
                        <option value="">Choose item from stock...</option>
                        <?php foreach ($foodItems as $f): 
                            $isSelected = ($preSelectedFoodId == $f['id']) || (($_POST['food_item_id'] ?? '') == $f['id']);
                            $daysLeft = getDaysRemaining($f['expiry_date']);
                            $statusLabel = $daysLeft < 0 ? 'EXPIRED' : ($daysLeft <= 2 ? 'CRITICAL' : 'Stock: ' . number_format($f['quantity'], 2));
                        ?>
                            <option value="<?= $f['id'] ?>" 
                                    data-stock="<?= $f['quantity'] ?>" 
                                    data-unit="<?= htmlspecialchars($f['unit']) ?>"
                                    <?= $isSelected ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['food_name']) ?> &bull; Batch: <?= htmlspecialchars($f['batch_number']) ?> (<?= $statusLabel ?>, In Stock: <?= number_format($f['quantity'], 2) ?> <?= htmlspecialchars($f['unit']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Current Stock Badge Indicator -->
                <div class="col-md-4">
                    <label class="form-label text-muted">Current Stock on Hand</label>
                    <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                        <span class="small text-muted">Available:</span>
                        <span class="fw-bold text-primary fs-6" id="stockDisplay">-</span>
                    </div>
                </div>

                <!-- Quantity to Discard -->
                <div class="col-md-4">
                    <label for="wastage_quantity" class="form-label">Quantity to Discard <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0.01" class="form-control" id="wastage_quantity" name="quantity" placeholder="0.00" value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" required>
                        <span class="input-group-text bg-light text-muted" id="wastageUnitDisplay">units</span>
                    </div>
                </div>

                <!-- Reason for Wastage -->
                <div class="col-md-4">
                    <label for="reason" class="form-label">Reason for Wastage <span class="text-danger">*</span></label>
                    <select class="form-select" id="reason" name="reason" required>
                        <option value="">Select Reason...</option>
                        <?php 
                        $reasons = [
                            'Expired' => 'Past Expiry Date',
                            'Spoiled/Rotten' => 'Spoiled / Rotten / Mold',
                            'Damaged Packaging' => 'Torn / Broken Packaging',
                            'Contaminated' => 'Contaminated / Foreign Matter',
                            'Preparation Waste' => 'Kitchen Prep / Cooking Burn',
                            'Temperature Abuse' => 'Left Unrefrigerated / Thawed',
                            'Other' => 'Other Operational Disposal'
                        ];
                        foreach ($reasons as $val => $desc):
                        ?>
                            <option value="<?= $val ?>" <?= (($_POST['reason'] ?? '') === $val) ? 'selected' : '' ?>><?= $desc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Wastage Date -->
                <div class="col-md-4">
                    <label for="wastage_date" class="form-label">Disposal Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="wastage_date" name="wastage_date" value="<?= htmlspecialchars($_POST['wastage_date'] ?? date('Y-m-d')) ?>" required>
                </div>

                <!-- Deduct from Inventory Checkbox -->
                <div class="col-12">
                    <div class="form-check form-switch p-3 bg-light rounded-3 border">
                        <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="deduct_stock" name="deduct_stock" value="1" checked>
                        <label class="form-check-label fw-semibold text-dark" for="deduct_stock">
                            Automatically deduct discarded quantity from current inventory stock
                        </label>
                        <div class="small text-muted ms-5">
                            Recommended: Keeps physical kitchen inventory and electronic stock synchronized.
                        </div>
                    </div>
                </div>

                <!-- Notes / Action Taken -->
                <div class="col-12">
                    <label for="notes" class="form-label">Staff Disposal Notes &amp; Method</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="e.g. Disposed via municipal organic waste. Cooler temperature had spiked to 12°C."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>

                <!-- Actions -->
                <div class="col-12 pt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-danger px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="fa-solid fa-trash-can me-2"></i> Confirm &amp; Log Wastage
                    </button>
                    <a href="wastage.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
