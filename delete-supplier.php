<?php
/**
 * FoodSafe Track - Delete Supplier Handler
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

try {
    $stmt = $pdo->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch();

    if ($supplier) {
        // Delete supplier (foreign keys set supplier_id to NULL on food_items)
        $delStmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $delStmt->execute([$id]);

        setFlash('success', 'Supplier "' . htmlspecialchars($supplier['supplier_name']) . '" was removed.');
    } else {
        setFlash('warning', 'Supplier not found or already deleted.');
    }
} catch (PDOException $e) {
    setFlash('danger', 'Could not delete supplier: ' . $e->getMessage());
}

header('Location: suppliers.php');
exit;
