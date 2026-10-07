<?php
/**
 * FoodSafe Track - Add New Supplier
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'Add New Supplier';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($supplier_name)) {
        $errors[] = 'Supplier company name is required.';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$supplier_name, $contact_person, $phone, $email, $address]);

            setFlash('success', 'Supplier "' . htmlspecialchars($supplier_name) . '" registered successfully!');
            header('Location: suppliers.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error creating supplier: ' . $e->getMessage();
        }
    }
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs & Header -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 small text-muted mb-2">
        <a href="suppliers.php" class="text-decoration-none text-muted">Suppliers</a>
        <i class="fa-solid fa-chevron-right fs-6"></i>
        <span>Add Supplier</span>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">Add New Food Supplier</h2>
            <p class="text-muted small mb-0">Register a new distributor or local farm to link with incoming food items.</p>
        </div>
        <a href="suppliers.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Directory
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
        <form method="POST" action="add-supplier.php" autocomplete="off">
            <div class="row g-4">
                
                <div class="col-12 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-truck-field text-primary me-2"></i> Vendor Contact &amp; Business Profile
                    </h5>
                </div>

                <div class="col-md-6">
                    <label for="supplier_name" class="form-label">Company / Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="supplier_name" name="supplier_name" placeholder="e.g. Apex Farm Produce, Valley Meat Packers" value="<?= htmlspecialchars($_POST['supplier_name'] ?? '') ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="contact_person" class="form-label">Contact Person / Representative</label>
                    <input type="text" class="form-control" id="contact_person" name="contact_person" placeholder="e.g. David Harrison" value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="orders@supplier.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label for="address" class="form-label">Physical Warehouse / Dispatch Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" placeholder="Street, City, State, ZIP code..."><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>

                <div class="col-12 pt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="fa-solid fa-plus me-2"></i> Register Supplier
                    </button>
                    <a href="suppliers.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
