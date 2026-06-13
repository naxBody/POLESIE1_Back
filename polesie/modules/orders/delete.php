<?php
/**
 * Удаление заказа (AJAX endpoint)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$user = getCurrentUser();

// Проверка прав на удаление заказов
if (!hasPermission('orders.delete')) {
    echo json_encode(['success' => false, 'error' => 'Недостаточно прав для удаления заказов']);
    exit;
}

$pdo = getDbConnection();
$id = $_GET['id'] ?? 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID заказа']);
    exit;
}

try {
    // Проверяем существование заказа
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Заказ не найден']);
        exit;
    }
    
    // Удаляем связанные позиции заказа
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$id]);
    
    // Удаляем заказ
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Ошибка при удалении заказа: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
}
