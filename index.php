<?php
/**
 * FoodSafe Track - Landing Page / Portal Entry
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Welcome to FoodSafe Track';
$isPublicPage = true;
$bodyClass = 'bg-white';
include_once __DIR__ . '/includes/header.php';
?>

<!-- Public Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold fs-4 text-dark" href="index.php">
            <span class="p-2 rounded-3 bg-success-subtle text-success me-2">
                <i class="fa-solid fa-shield-halved"></i>
            </span>
            FoodSafe<span class="text-success">Track</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="login.php" class="btn btn-outline-success px-3 rounded-pill fw-semibold">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
            </a>
            <a href="login.php" class="btn btn-success px-4 rounded-pill fw-semibold shadow-sm">
                Access System
            </a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<header class="py-5 bg-light border-bottom">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-success-subtle text-success small fw-bold mb-3">
                    <i class="fa-solid fa-check-double"></i> HACCP &amp; Food Safety Standards Compliant
                </div>
                <h1 class="display-4 fw-extrabold text-dark tracking-tight mb-3">
                    Food Safety &amp; Expiry Management System
                </h1>
                <p class="lead text-secondary mb-4">
                    The modern, end-to-end inventory tracking and food safety platform tailored for 
                    <strong>Restaurants, Hotels, Cafeterias, Supermarkets, and Storage Facilities</strong>.
                    Eliminate expired stock, maintain compliance, and reduce costly food waste.
                </p>
                <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
                    <a href="login.php" class="btn btn-primary btn-lg rounded-pill px-4 shadow">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Launch Staff Console
                    </a>
                    <a href="#features" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                        <i class="fa-solid fa-circle-info me-2"></i> Explore Features
                    </a>
                </div>
                
                <!-- Quick Demo Credentials Box for College Evaluators -->
                <div class="card border-0 bg-white shadow-sm p-3 rounded-3" style="max-width: 500px;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold text-dark text-uppercase tracking-wider">
                            <i class="fa-solid fa-key text-warning me-1"></i> Quick Test Credentials
                        </span>
                        <span class="badge bg-success-subtle text-success">Import Ready</span>
                    </div>
                    <div class="row g-2 small text-muted">
                        <div class="col-6">
                            <strong>Admin Role:</strong><br>
                            <code>admin@foodsafe.com</code><br>
                            Password: <code>admin123</code>
                        </div>
                        <div class="col-6">
                            <strong>Staff Role:</strong><br>
                            <code>staff@foodsafe.com</code><br>
                            Password: <code>staff123</code>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="card shadow-lg border-0 rounded-4 p-4 bg-white position-relative overflow-hidden">
                    <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-chart-line text-success me-2"></i> Real-time Safety Matrix
                        </h6>
                        <span class="badge bg-danger rounded-pill">Active Live Alerts</span>
                    </div>
                    
                    <div class="p-3 bg-danger-subtle rounded-3 mb-3 border border-danger-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-danger d-block">Expired Stock Detected</strong>
                                <small class="text-danger-emphasis">Batch BAT-MK-2026-001 Past Shelf Life</small>
                            </div>
                            <span class="badge bg-danger">Action: Wastage Log</span>
                        </div>
                    </div>

                    <div class="p-3 bg-warning-subtle rounded-3 mb-3 border border-warning-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-dark d-block">Critical Near-Expiry (<= 2 Days)</strong>
                                <small class="text-secondary">Fresh Chicken Breast Fillets</small>
                            </div>
                            <span class="badge bg-warning text-dark">Priority Cook</span>
                        </div>
                    </div>

                    <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-success d-block">Fresh &amp; Safe Inventory</strong>
                                <small class="text-success-emphasis">92% Compliance Index</small>
                            </div>
                            <i class="fa-solid fa-circle-check text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Features Grid -->
<section class="py-5" id="features">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">End-to-End Workflow</span>
            <h2 class="display-6 fw-bold mt-2">Comprehensive Food Safety Architecture</h2>
            <p class="text-muted">Designed to address every step of professional food storage and tracking.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 p-4 border rounded-4 shadow-sm">
                    <div class="stat-icon primary mb-3">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <h5 class="fw-bold">1. Inventory Tracking</h5>
                    <p class="text-muted small">
                        Log food items with batch numbers, storage locations (freezers, chillers, dry pantries), units, and purchase dates.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border rounded-4 shadow-sm">
                    <div class="stat-icon warning mb-3">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <h5 class="fw-bold">2. Smart Expiry Alerts</h5>
                    <p class="text-muted small">
                        Automated categorization into Safe, Near-Expiry (3-7 days), Critical (&lt;= 2 days), and Expired with live countdowns.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border rounded-4 shadow-sm">
                    <div class="stat-icon danger mb-3">
                        <i class="fa-solid fa-trash-can"></i>
                    </div>
                    <h5 class="fw-bold">3. Wastage Auditing</h5>
                    <p class="text-muted small">
                        Record discarded quantities, pinpoint root causes (spoilage, temperature abuse, broken seals), and automatically deduct stock.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border rounded-4 shadow-sm">
                    <div class="stat-icon info mb-3">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                    <h5 class="fw-bold">4. Supplier Governance</h5>
                    <p class="text-muted small">
                        Manage vendor contact directories and link ingredients directly to primary distributors for quality tracing.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border rounded-4 shadow-sm">
                    <div class="stat-icon primary mb-3">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                    <h5 class="fw-bold">5. Analytical Reports</h5>
                    <p class="text-muted small">
                        Generate actionable food waste analytics, category breakdowns, and print-ready compliance audit sheets.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 p-4 border rounded-4 shadow-sm">
                    <div class="stat-icon warning mb-3">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <h5 class="fw-bold">6. Role-Based Access</h5>
                    <p class="text-muted small">
                        Granular control separating Administrator management and Staff kitchen floor operations with secure PDO sessions.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white py-4 border-top">
    <div class="container text-center">
        <p class="mb-1 fw-semibold">FoodSafe Track &ndash; Food Safety &amp; Expiry Management System</p>
        <p class="text-secondary small mb-3">College Web Application Development Project &bull; Built with PHP 8, MySQL, Bootstrap 5</p>
        <a href="login.php" class="btn btn-outline-light btn-sm rounded-pill px-4">Staff Login Portal</a>
    </div>
</footer>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
