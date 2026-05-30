<?php
/**
 * Создание производственного задания с автоматическими этапами
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещен'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json');
$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Метод не разрешен'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $orderId = (int)($data['order_id'] ?? 0);
    $orderItemId = (int)($data['order_item_id'] ?? 0);
    $productId = (int)($data['product_id'] ?? 0);
    $quantity = (float)($data['quantity'] ?? 0);
    $priority = $data['priority'] ?? 'normal';
    $startDate = $data['start_date'] ?? null;
    $endDate = $data['end_date'] ?? null;
    $notes = $data['notes'] ?? '';
    $responsibleId = (int)($data['responsible_id'] ?? getCurrentUserId());
    
    if (!$orderId || !$productId || !$quantity) {
        throw new Exception('Не указаны обязательные параметры');
    }
    
    // Получаем продукт
    $stmt = $pdo->prepare("SELECT article, name FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Продукт не найден');
    }
    
    // Генерируем номер задания
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(task_number, 11) AS UNSIGNED)) as max_num FROM production_tasks WHERE task_number LIKE 'TASK-$year-%'");
    $stmt->execute();
    $result = $stmt->fetch();
    $nextNum = ($result['max_num'] ?? 0) + 1;
    $taskNumber = sprintf('TASK-%d-%03d', $year, $nextNum);
    
    // Находим активную маршрутную карту для продукта
    $routeCardId = null;
    $stmt = $pdo->prepare("SELECT id FROM route_cards WHERE product_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$productId]);
    $route = $stmt->fetch();
    if ($route) {
        $routeCardId = $route['id'];
    }
    
    // Создаем задание
    $stmt = $pdo->prepare("
        INSERT INTO production_tasks 
        (task_number, order_id, order_item_id, product_id, route_card_id, quantity_plan, status, priority, start_date, end_date, planned_start, planned_end, responsible_id, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'planned', ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $taskNumber, $orderId, $orderItemId, $productId, $routeCardId, $quantity,
        $priority, $startDate, $endDate,
        $startDate ? $startDate . ' 08:00:00' : null,
        $endDate ? $endDate . ' 17:00:00' : null,
        $responsibleId, $notes
    ]);
    
    $taskId = $pdo->lastInsertId();
    
    // Если есть маршрутная карта, создаем этапы
    if ($routeCardId) {
        require_once __DIR__ . '/api_execute.php';
        
        // Временно делаем это API запросом чтобы вызвать функцию
        $originalRequest = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        
        createStagesForTask($pdo, $taskId, $routeCardId);
        
        // Восстанавливаем
        if ($originalRequest) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = $originalRequest;
        } else {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }
    }
    
    // Обновляем статус позиции заказа
    if ($orderItemId) {
        $stmt = $pdo->prepare("UPDATE order_items SET production_status = 'in_progress' WHERE id = ? AND production_status = 'not_started'");
        $stmt->execute([$orderItemId]);
    }
    
    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'task_number' => $taskNumber,
        'message' => 'Задание создано успешно',
        'stages_created' => $routeCardId ? true : false
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
