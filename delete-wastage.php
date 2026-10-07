<?php
/**
 * FoodSafe Track - Delete Wastage Log Entry
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('danger', 'Invalid wastage record identifier.');
    header('Location: wastage.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM wastage WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        setFlash('success', 'Wastage record removed successfully.');
    } else {
        setFlash('warning', 'Wastage record not found or already deleted.');
    }
} catch (PDOException $e) {
    setFlash('danger', 'Database error removing record: ' . $e->getMessage());
}

header('Location: wastage.php');
exit;
