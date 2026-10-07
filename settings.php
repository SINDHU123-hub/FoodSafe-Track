<?php
/**
 * FoodSafe Track - System & User Profile Settings
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$pageTitle = 'System & Profile Settings';
$currentUserId = $_SESSION['user_id'];

// Fetch current user record
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$userRecord = $stmt->fetch();

$profileErrors = [];
$categoryErrors = [];

// -----------------------------------------------------------------------------
// Handle Profile Update POST
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_pass = trim($_POST['current_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    if (empty($name)) $profileErrors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $profileErrors[] = 'A valid email address is required.';

    // Check email uniqueness if changed
    if ($email !== $userRecord['email']) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $currentUserId]);
        if ($chk->fetch()) {
            $profileErrors[] = 'That email address is already registered by another account.';
        }
    }

    // Password change validation
    $updatePassword = false;
    if (!empty($new_pass)) {
        if (empty($current_pass)) {
            $profileErrors[] = 'Current password is required to set a new password.';
        } elseif (!password_verify($current_pass, $userRecord['password']) && $current_pass !== $userRecord['password']) {
            $profileErrors[] = 'Current password does not match.';
        } elseif (strlen($new_pass) < 6) {
            $profileErrors[] = 'New password must be at least 6 characters long.';
        } elseif ($new_pass !== $confirm_pass) {
            $profileErrors[] = 'New password and confirmation do not match.';
        } else {
            $updatePassword = true;
        }
    }

    if (empty($profileErrors)) {
        try {
            if ($updatePassword) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                $up->execute([$name, $email, $hashed, $currentUserId]);
            } else {
                $up = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $up->execute([$name, $email, $currentUserId]);
            }

            // Update session cache
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            setFlash('success', 'Profile and security credentials updated successfully!');
            header('Location: settings.php');
            exit;
        } catch (PDOException $e) {
            $profileErrors[] = 'Database error updating profile: ' . $e->getMessage();
        }
    }
}

// -----------------------------------------------------------------------------
// Handle Add Category POST
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $category_name = trim($_POST['category_name'] ?? '');

    if (empty($category_name)) {
        $categoryErrors[] = 'Category name cannot be empty.';
    } else {
        try {
            $chkCat = $pdo->prepare("SELECT id FROM categories WHERE category_name = ?");
            $chkCat->execute([$category_name]);
            if ($chkCat->fetch()) {
                $categoryErrors[] = 'Category "' . htmlspecialchars($category_name) . '" already exists.';
            } else {
                $insCat = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
                $insCat->execute([$category_name]);

                setFlash('success', 'Category "' . htmlspecialchars($category_name) . '" added successfully!');
                header('Location: settings.php');
                exit;
            }
        } catch (PDOException $e) {
            $categoryErrors[] = 'Database error adding category: ' . $e->getMessage();
        }
    }
}

// Fetch all categories with item counts
$allCategories = $pdo->query("
    SELECT c.*, COUNT(f.id) as item_count 
    FROM categories c 
    LEFT JOIN food_items f ON c.id = f.category_id 
    GROUP BY c.id 
    ORDER BY c.category_name ASC
")->fetchAll();

include_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">System &amp; Account Settings</h2>
        <p class="text-muted small mb-0">Configure user credentials, manage classification categories, and view server diagnostics.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: User Profile & Security -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-user-shield text-success me-2"></i> Account Profile &amp; Security
                </h6>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if (!empty($profileErrors)): ?>
                    <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($profileErrors as $pe): ?>
                                <li><?= htmlspecialchars($pe) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="settings.php" autocomplete="off">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($userRecord['name']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userRecord['email']) ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">System Role</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($userRecord['role']) ?>" disabled>
                            <small class="text-muted">Account permission tier managed by system database administrator.</small>
                        </div>

                        <div class="col-12 border-top pt-3 mt-3">
                            <h6 class="fw-bold text-dark mb-1">Change Account Password</h6>
                            <p class="text-muted small">Leave blank if you do not wish to modify your current password.</p>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="••••••••">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password">
                        </div>

                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="fa-solid fa-floppy-disk me-2"></i> Save Profile Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Food Categories Management -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-tags text-primary me-2"></i> Food Categories Directory
                </h6>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill"><?= count($allCategories) ?> Categories</span>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($categoryErrors)): ?>
                    <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-3">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($categoryErrors as $ce): ?>
                                <li><?= htmlspecialchars($ce) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Add New Category Form -->
                <form method="POST" action="settings.php" class="mb-4">
                    <input type="hidden" name="action" value="add_category">
                    <label class="form-label small fw-semibold">Add New Category</label>
                    <div class="input-group">
                        <input type="text" name="category_name" class="form-control" placeholder="e.g. Seafood, Condiments, Prepared Meals..." required>
                        <button type="submit" class="btn btn-primary px-3">
                            <i class="fa-solid fa-plus me-1"></i> Add
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table custom-table align-middle">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Active Items</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allCategories as $c): ?>
                                <tr>
                                    <td class="fw-semibold text-dark">
                                        <i class="fa-solid fa-tag text-muted me-2"></i><?= htmlspecialchars($c['category_name']) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border"><?= $c['item_count'] ?> items</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: System Diagnostics & Project Info -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-server text-info me-2"></i> Environment Diagnostics
                </h6>
            </div>
            <div class="card-body p-4">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Application Name:</span>
                        <strong class="text-dark">FoodSafe Track</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Target Platform:</span>
                        <span class="badge bg-light text-dark border">Windows / XAMPP</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Database Engine:</span>
                        <span class="badge bg-success-subtle text-success">MySQL / PDO</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Database Name:</span>
                        <code class="text-primary">foodsafe_track</code>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">PHP Version:</span>
                        <span class="font-monospace fw-semibold text-dark"><?= phpversion() ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Server Time:</span>
                        <span class="text-dark"><?= date('Y-m-d H:i:s') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Timezone:</span>
                        <span class="text-dark"><?= date_default_timezone_get() ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-graduation-cap text-warning me-2"></i> Academic Project Submission Info
                </h6>
            </div>
            <div class="card-body p-4 text-secondary small">
                <p>
                    <strong>Project Name:</strong> FoodSafe Track – Food Safety &amp; Expiry Management System<br>
                    <strong>Course:</strong> Web Application Development
                </p>
                <div class="p-3 bg-light rounded-3 border">
                    <h6 class="fw-bold text-dark small mb-1">Architecture Highlights:</h6>
                    <ul class="mb-0 ps-3">
                        <li>Native PHP 8+ procedural &amp; OOP hybrid with PDO prepared statements</li>
                        <li>Zero external node_modules or heavy framework dependencies</li>
                        <li>Robust relational schema with foreign key constraints &amp; cascading triggers</li>
                        <li>Responsive Bootstrap 5 CDN &amp; Chart.js visual analytics</li>
                        <li>Ready for standard deployment in <code>C:\xampp\htdocs\FoodSafe-Track</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
