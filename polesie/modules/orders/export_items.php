<?php
/**
 * Экспорт позиций заказа в CSV
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    die('Unauthorized');
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    die('Заказ не найден');
}

$pdo = getDbConnection();

// Получение информации о заказе
$orderSql = "SELECT order_number FROM orders WHERE id = ?";
$stmt = $pdo->prepare($orderSql);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die('Заказ не найден');
}

// Получение позиций заказа
$itemsSql = "
    SELECT oi.*, p.name as product_name, p.article, 
           bu.symbol as unit_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN base_units bu ON p.base_unit_id = bu.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
";
$stmt = $pdo->prepare($itemsSql);
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

// Формирование CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="order_' . $order['order_number'] . '_items.csv"');

$output = fopen('php://output', 'w');

// BOM для корректного отображения кириллицы в Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Заголовки
fputcsv($output, [
    '№',
    'Артикул',
    'Наименование',
    'Ед. изм.',
    'Количество',
    'Цена',
    'Сумма'
], ';');

// Данные
foreach ($items as $index => $item) {
    fputcsv($output, [
        $index + 1,
        $item['article'] ?? '',
        $item['product_name'] ?? '',
        $item['unit_name'] ?? 'шт.',
        $item['quantity'],
        $item['price'],
        $item['amount']
    ], ';');
}

fclose($output);
exit;
