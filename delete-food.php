<?php
/**
 * FoodSafe Track - Delete Food Item Handler
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

try {
    // Check if item exists
    $stmt = $pdo->prepare("SELECT food_name FROM food_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if ($item) {
        $delStmt = $pdo->prepare("DELETE FROM food_items WHERE id = ?");
        $delStmt->execute([$id]);

        setFlash('success', 'Food item "' . htmlspecialchars($item['food_name']) . '" and associated records were removed from inventory.');
    } else {
        setFlash('warning', 'Food item not found or already deleted.');
    }
} catch (PDOException $e) {
    setFlash('danger', 'Unable to delete item: ' . $e->getMessage());
}

header('Location: inventory.php');
exit;
