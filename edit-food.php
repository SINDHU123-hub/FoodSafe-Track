<?php
/**
 * FoodSafe Track - Edit Food Item
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'Invalid food item identifier.');
    header('Location: inventory.php');
    exit;
}

// Fetch existing record
$stmt = $pdo->prepare("SELECT * FROM food_items WHERE id = ?");
$stmt->execute([$id]);
$food = $stmt->fetch();

if (!$food) {
    setFlash('danger', 'The specified food item could not be found.');
    header('Location: inventory.php');
    exit;
}

$pageTitle = 'Edit Food Item – ' . htmlspecialchars($food['food_name']);
$errors = [];

// Fetch categories and suppliers
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_name = trim($_POST['food_name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $quantity = trim($_POST['quantity'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $purchase_date = trim($_POST['purchase_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $storage_location = trim($_POST['storage_location'] ?? '');
    $batch_number = trim($_POST['batch_number'] ?? '');
    $food_safety_notes = trim($_POST['food_safety_notes'] ?? '');

    // Validation
    if (empty($food_name)) $errors[] = 'Food item name is required.';
    if (empty($category_id)) $errors[] = 'Please select a food category.';
    if ($quantity === '' || !is_numeric($quantity) || (float)$quantity < 0) $errors[] = 'Please specify a valid quantity.';
    if (empty($unit)) $errors[] = 'Measurement unit is required.';
    if (empty($purchase_date)) $errors[] = 'Purchase date is required.';
    if (empty($expiry_date)) $errors[] = 'Expiry date is required.';
    if (empty($storage_location)) $errors[] = 'Storage location is required.';
    if (empty($batch_number)) $errors[] = 'Batch number is required.';

    if (empty($errors)) {
        try {
            $sql = "
                UPDATE food_items 
                SET food_name = ?, category_id = ?, supplier_id = ?, quantity = ?, unit = ?, 
                    purchase_date = ?, expiry_date = ?, storage_location = ?, batch_number = ?, 
                    food_safety_notes = ?, updated_at = NOW()
                WHERE id = ?
            ";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute([
                $food_name,
                (int)$category_id,
                $supplier_id,
                (float)$quantity,
                $unit,
                $purchase_date,
                $expiry_date,
                $storage_location,
                $batch_number,
                $food_safety_notes,
                $id
            ]);

            // Re-sync alerts
            syncExpiryAlerts($pdo);

            setFlash('success', 'Food item "' . htmlspecialchars($food_name) . '" updated successfully!');
            header('Location: food-details.php?id=' . $id);
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error updating item: ' . $e->getMessage();
        }
    }
} else {
    // Fill from database record
    $food_name = $food['food_name'];
    $category_id = $food['category_id'];
    $supplier_id = $food['supplier_id'];
    $quantity = $food['quantity'];
    $unit = $food['unit'];
    $purchase_date = $food['purchase_date'];
    $expiry_date = $food['expiry_date'];
    $storage_location = $food['storage_location'];
    $batch_number = $food['batch_number'];
    $food_safety_notes = $food['food_safety_notes'];
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 small text-muted mb-2">
        <a href="inventory.php" class="text-decoration-none text-muted">Inventory</a>
        <i class="fa-solid fa-chevron-right fs-6"></i>
        <a href="food-details.php?id=<?= $id ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($food['food_name']) ?></a>
        <i class="fa-solid fa-chevron-right fs-6"></i>
        <span>Edit</span>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">Edit Food Item</h2>
            <p class="text-muted small mb-0">Modify batch information, stock quantities, and expiry dates.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="food-details.php?id=<?= $id ?>" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-eye me-1"></i> View Item
            </a>
            <a href="inventory.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
        </div>
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
        <form method="POST" action="edit-food.php?id=<?= $id ?>" autocomplete="off">
            <div class="row g-4">
                
                <div class="col-12 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-tag text-success me-2"></i>1. Food Details &amp; Category
                    </h5>
                </div>

                <div class="col-md-6">
                    <label for="food_name" class="form-label">Food Item Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="food_name" name="food_name" value="<?= htmlspecialchars($food_name) ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="category_id" class="form-label">Food Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="supplier_id" class="form-label">Supplier / Distributor</label>
                    <select class="form-select" id="supplier_id" name="supplier_id">
                        <option value="">None / Direct Purchase</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= ($supplier_id == $sup['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sup['supplier_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 border-bottom pb-2 pt-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>2. Quantity, Batch &amp; Storage
                    </h5>
                </div>

                <div class="col-md-3">
                    <label for="quantity" class="form-label">Quantity in Stock <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" id="quantity" name="quantity" value="<?= htmlspecialchars($quantity) ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="unit" class="form-label">Measurement Unit <span class="text-danger">*</span></label>
                    <select class="form-select" id="unit" name="unit" required>
                        <?php 
                        $units = ['kg', 'grams', 'liters', 'ml', 'pcs', 'packs', 'boxes', 'cans', 'bags', 'cartons'];
                        foreach ($units as $u):
                        ?>
                            <option value="<?= $u ?>" <?= ($unit === $u) ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="storage_location" class="form-label">Storage Location <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="storage_location" name="storage_location" value="<?= htmlspecialchars($storage_location) ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="batch_number" class="form-label">Batch Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace" id="batch_number" name="batch_number" value="<?= htmlspecialchars($batch_number) ?>" required>
                </div>

                <div class="col-12 border-bottom pb-2 pt-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-calendar-days text-danger me-2"></i>3. Dates &amp; Food Safety Compliance
                    </h5>
                </div>

                <div class="col-md-4">
                    <label for="purchase_date" class="form-label">Purchase Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?= htmlspecialchars($purchase_date) ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?= htmlspecialchars($expiry_date) ?>" required>
                    <div id="expiryFeedback" class="small mt-1"></div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Current Expiry Status</label>
                    <div class="pt-1">
                        <?= getExpiryBadge($expiry_date) ?>
                    </div>
                </div>

                <div class="col-12">
                    <label for="food_safety_notes" class="form-label">Food Safety &amp; HACCP Handling Notes</label>
                    <textarea class="form-control" id="food_safety_notes" name="food_safety_notes" rows="3"><?= htmlspecialchars($food_safety_notes) ?></textarea>
                </div>

                <div class="col-12 pt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="fa-solid fa-check me-2"></i> Update Food Item
                    </button>
                    <a href="food-details.php?id=<?= $id ?>" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
