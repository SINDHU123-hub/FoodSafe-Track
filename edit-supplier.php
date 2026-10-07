<?php
/**
 * FoodSafe Track - Edit Supplier Information
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'Invalid supplier identifier.');
    header('Location: suppliers.php');
    exit;
}

// Fetch existing record
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    setFlash('danger', 'The specified supplier was not found.');
    header('Location: suppliers.php');
    exit;
}

$pageTitle = 'Edit Supplier – ' . htmlspecialchars($supplier['supplier_name']);
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
                UPDATE suppliers 
                SET supplier_name = ?, contact_person = ?, phone = ?, email = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([$supplier_name, $contact_person, $phone, $email, $address, $id]);

            setFlash('success', 'Supplier "' . htmlspecialchars($supplier_name) . '" updated successfully!');
            header('Location: suppliers.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error updating supplier: ' . $e->getMessage();
        }
    }
} else {
    $supplier_name = $supplier['supplier_name'];
    $contact_person = $supplier['contact_person'];
    $phone = $supplier['phone'];
    $email = $supplier['email'];
    $address = $supplier['address'];
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs & Header -->
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 small text-muted mb-2">
        <a href="suppliers.php" class="text-decoration-none text-muted">Suppliers</a>
        <i class="fa-solid fa-chevron-right fs-6"></i>
        <span>Edit Supplier</span>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">Edit Supplier Information</h2>
            <p class="text-muted small mb-0">Update contact records and distribution details.</p>
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
        <form method="POST" action="edit-supplier.php?id=<?= $id ?>" autocomplete="off">
            <div class="row g-4">
                
                <div class="col-12 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-truck-field text-primary me-2"></i> Vendor Contact &amp; Business Profile
                    </h5>
                </div>

                <div class="col-md-6">
                    <label for="supplier_name" class="form-label">Company / Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="supplier_name" name="supplier_name" value="<?= htmlspecialchars($supplier_name) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="contact_person" class="form-label">Contact Person / Representative</label>
                    <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= htmlspecialchars($contact_person) ?>">
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>">
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>">
                </div>

                <div class="col-12">
                    <label for="address" class="form-label">Physical Warehouse / Dispatch Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($address) ?></textarea>
                </div>

                <div class="col-12 pt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                        <i class="fa-solid fa-check me-2"></i> Save Supplier Changes
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
