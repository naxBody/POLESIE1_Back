<?php
/**
 * Удаление продукции
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();

// Проверка прав
if (!hasPermission('products.delete')) {
    redirect(pageUrl('modules/products/list.php'));
}

$pdo = getDbConnection();
$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Можно добавить логирование ошибки
    }
}

redirect(pageUrl('modules/products/list.php'));
