<?php
/**
 * FoodSafe Track - Add Food Item
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Add Food Item';
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
    $purchase_date = trim($_POST['purchase_date'] ?? date('Y-m-d'));
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $storage_location = trim($_POST['storage_location'] ?? '');
    $batch_number = trim($_POST['batch_number'] ?? '');
    $food_safety_notes = trim($_POST['food_safety_notes'] ?? '');

    // Validation
    if (empty($food_name)) {
        $errors[] = 'Food item name is required.';
    }
    if (empty($category_id)) {
        $errors[] = 'Please select a food category.';
    }
    if ($quantity === '' || !is_numeric($quantity) || (float)$quantity < 0) {
        $errors[] = 'Please specify a valid quantity (0 or greater).';
    }
    if (empty($unit)) {
        $errors[] = 'Measurement unit is required.';
    }
    if (empty($purchase_date)) {
        $errors[] = 'Purchase date is required.';
    }
    if (empty($expiry_date)) {
        $errors[] = 'Expiry date is required.';
    }
    if (empty($storage_location)) {
        $errors[] = 'Storage location is required.';
    }
    if (empty($batch_number)) {
        $errors[] = 'Batch number is required.';
    }

    if (empty($errors)) {
        try {
            $sql = "
                INSERT INTO food_items 
                (food_name, category_id, supplier_id, quantity, unit, purchase_date, expiry_date, storage_location, batch_number, food_safety_notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $food_name,
                (int)$category_id,
                $supplier_id,
                (float)$quantity,
                $unit,
                $purchase_date,
                $expiry_date,
                $storage_location,
                $batch_number,
                $food_safety_notes
            ]);

            // Sync alerts
            syncExpiryAlerts($pdo);

            setFlash('success', 'Food item "' . htmlspecialchars($food_name) . '" registered successfully!');
            header('Location: inventory.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Failed to save food item: ' . $e->getMessage();
        }
    }
}

// Generate default batch suggestion
$defaultBatch = 'BAT-' . date('Ym') . '-' . rand(1000, 9999);

include_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs & Header -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 small text-muted mb-2">
        <a href="inventory.php" class="text-decoration-none text-muted">Inventory</a>
        <i class="fa-solid fa-chevron-right fs-6"></i>
        <span>New Food Item</span>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">Add New Food Item</h2>
            <p class="text-muted small mb-0">Record a new food batch into inventory with shelf-life and safety monitoring.</p>
        </div>
        <a href="inventory.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
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
        <form method="POST" action="add-food.php" autocomplete="off">
            <div class="row g-4">
                
                <!-- Section 1: Item Basic Info -->
                <div class="col-12 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-tag text-success me-2"></i>1. Food Details &amp; Category
                    </h5>
                    <p class="text-muted small mb-0">Specify the food identification, classification, and distributor.</p>
                </div>

                <div class="col-md-6">
                    <label for="food_name" class="form-label">Food Item Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="food_name" name="food_name" placeholder="e.g. Fresh Whole Milk 1L, Raw Chicken Breast" value="<?= htmlspecialchars($_POST['food_name'] ?? '') ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="category_id" class="form-label">Food Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
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
                            <option value="<?= $sup['id'] ?>" <?= (($_POST['supplier_id'] ?? '') == $sup['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sup['supplier_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section 2: Batch & Storage Details -->
                <div class="col-12 border-bottom pb-2 pt-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>2. Quantity, Batch &amp; Storage
                    </h5>
                    <p class="text-muted small mb-0">Identify storage location and stock measurement units.</p>
                </div>

                <div class="col-md-3">
                    <label for="quantity" class="form-label">Quantity in Stock <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" id="quantity" name="quantity" placeholder="0.00" value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="unit" class="form-label">Measurement Unit <span class="text-danger">*</span></label>
                    <select class="form-select" id="unit" name="unit" required>
                        <option value="">Select Unit...</option>
                        <?php 
                        $units = ['kg', 'grams', 'liters', 'ml', 'pcs', 'packs', 'boxes', 'cans', 'bags', 'cartons'];
                        foreach ($units as $u):
                        ?>
                            <option value="<?= $u ?>" <?= (($_POST['unit'] ?? '') === $u) ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="storage_location" class="form-label">Storage Location <span class="text-danger">*</span></label>
                    <select class="form-select" id="storage_location" name="storage_location" required>
                        <option value="">Select Location...</option>
                        <?php
                        $locations = [
                            'Walk-in Chiller A',
                            'Walk-in Chiller B',
                            'Deep Freezer A',
                            'Deep Freezer B',
                            'Meat Cold Room',
                            'Dairy Chiller Shelf 2',
                            'Vegetable Crisper 2',
                            'Dry Pantry Section C',
                            'Bakery Dry Shelf A',
                            'Beverage Chiller 3',
                            'Fruit Display Stand 1'
                        ];
                        foreach ($locations as $loc):
                        ?>
                            <option value="<?= $loc ?>" <?= (($_POST['storage_location'] ?? '') === $loc) ? 'selected' : '' ?>><?= $loc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="batch_number" class="form-label d-flex justify-content-between align-items-center">
                        <span>Batch Number <span class="text-danger">*</span></span>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnGenBatch">
                            <i class="fa-solid fa-rotate me-1"></i> Auto
                        </button>
                    </label>
                    <input type="text" class="form-control font-monospace" id="batch_number" name="batch_number" value="<?= htmlspecialchars($_POST['batch_number'] ?? $defaultBatch) ?>" required>
                </div>

                <!-- Section 3: Dates & Safety Notes -->
                <div class="col-12 border-bottom pb-2 pt-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-calendar-days text-danger me-2"></i>3. Dates &amp; Food Safety Compliance
                    </h5>
                    <p class="text-muted small mb-0">Record receiving and expiration dates to trigger automated alerts.</p>
                </div>

                <div class="col-md-4">
                    <label for="purchase_date" class="form-label">Purchase / Receiving Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?= htmlspecialchars($_POST['purchase_date'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="expiry_date" class="form-label">Expiry / Best-Before Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>" required>
                    <div id="expiryFeedback" class="small mt-1"></div>
                </div>

                <div class="col-md-4">
                    <div class="alert alert-light border small h-100 mb-0 d-flex flex-column justify-content-center">
                        <div class="fw-bold text-secondary"><i class="fa-solid fa-shield-halved text-success me-1"></i> HACCP Expiry Rules:</div>
                        <span class="text-muted">Critical alert triggers at &le;2 days. Near-expiry warning triggers at &le;7 days.</span>
                    </div>
                </div>

                <div class="col-12">
                    <label for="food_safety_notes" class="form-label">Food Safety &amp; Temperature Notes</label>
                    <textarea class="form-control" id="food_safety_notes" name="food_safety_notes" rows="3" placeholder="e.g. Keep chilled below 4°C. Check vacuum seal before cooking. Allergen warning: Contains dairy."><?= htmlspecialchars($_POST['food_safety_notes'] ?? '') ?></textarea>
                </div>

                <!-- Form Controls -->
                <div class="col-12 pt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Save Food Item to Inventory
                    </button>
                    <a href="inventory.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
