<?php
/**
 * План выпуска - все заказы и производственные задания
 * Отображение статуса производства по каждому заказу
 * Компактная таблица с детальной информацией по клику
 */

// API endpoint для получения детальной информации о заказе (должен быть В САМОМ НАЧАЛЕ до любых подключений и вывода)
if (isset($_GET['api_order_detail'])) {
    // Сначала включаем буферизацию чтобы перехватить любой вывод
    ob_start();
    
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/auth.php';
    
    // Очищаем буфер от любого случайного вывода при подключении файлов
    ob_end_clean();
    
    if (!isLoggedIn()) {
        http_response_code(403);
        $jsonError = json_encode(['error' => 'Доступ запрещен'], JSON_UNESCAPED_UNICODE);
        if (ob_get_level()) {
            ob_end_clean();
        }
        echo $jsonError;
        exit;
    }
    
    header('Content-Type: application/json');
    $pdo = getDbConnection();
    $orderId = (int)$_GET['order_id'];
    
    // Основная информация о заказе
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as contractor_name, u.full_name as responsible_name,
            CASE o.status
                WHEN 'new' THEN 'Новый'
                WHEN 'processing' THEN 'В работе'
                WHEN 'ready' THEN 'Готов'
                WHEN 'shipped' THEN 'Отгружен'
                WHEN 'cancelled' THEN 'Отменен'
                ELSE o.status
            END as status_name,
            CASE o.status
                WHEN 'new' THEN '#3498db'
                WHEN 'processing' THEN '#f39c12'
                WHEN 'ready' THEN '#27ae60'
                WHEN 'shipped' THEN '#9b59b6'
                WHEN 'cancelled' THEN '#e74c3c'
                ELSE '#95a5a6'
            END as status_color
        FROM orders o
        LEFT JOIN contractors c ON o.customer_id = c.id
        LEFT JOIN users u ON o.responsible_user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $orderInfo = $stmt->fetch();
    
    if (!$orderInfo) {
        http_response_code(404);
        $jsonError = json_encode(['error' => 'Заказ не найден'], JSON_UNESCAPED_UNICODE);
        if (ob_get_level()) {
            ob_end_clean();
        }
        echo $jsonError;
        exit;
    }
    
    // Позиции заказа
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.article as product_article,
            CASE oi.production_status
                WHEN 'not_started' THEN 'Не начато'
                WHEN 'in_progress' THEN 'В работе'
                WHEN 'completed' THEN 'Готово'
                WHEN 'packed' THEN 'Упаковано'
                ELSE 'Нет данных'
            END as production_status_name,
            CASE oi.production_status
                WHEN 'not_started' THEN '#95a5a6'
                WHEN 'in_progress' THEN '#f39c12'
                WHEN 'completed' THEN '#27ae60'
                WHEN 'packed' THEN '#9b59b6'
                ELSE '#95a5a6'
            END as production_status_color
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    
    // Производственные задания по заказу
    $stmt = $pdo->prepare("
        SELECT pt.*, p.name as product_name,
            CASE pt.status
                WHEN 'planned' THEN 'Запланировано'
                WHEN 'in_progress' THEN 'В работе'
                WHEN 'completed' THEN 'Завершено'
                WHEN 'cancelled' THEN 'Отменено'
                ELSE pt.status
            END as status_name,
            CASE pt.status
                WHEN 'planned' THEN '#3498db'
                WHEN 'in_progress' THEN '#f39c12'
                WHEN 'completed' THEN '#27ae60'
                WHEN 'cancelled' THEN '#e74c3c'
                ELSE '#95a5a6'
            END as status_color
        FROM production_tasks pt
        JOIN products p ON pt.product_id = p.id
        WHERE pt.order_id = ?
        ORDER BY pt.created_at DESC
    ");
    $stmt->execute([$orderId]);
    $tasks = $stmt->fetchAll();
    
    // Материалы по заданиям (что хватает/не хватает)
    foreach ($tasks as &$task) {
        $stmt = $pdo->prepare("
            SELECT ptm.id, ptm.task_id, ptm.material_id, ptm.quantity_required, ptm.quantity_used,
                   m.name_full as material_name, m.code as material_article, mu.symbol as unit_name,
                   COALESCE(m.current_stock, 0) as quantity_available,
                   CASE 
                       WHEN COALESCE(m.current_stock, 0) >= ptm.quantity_required THEN 'sufficient'
                       ELSE 'insufficient'
                   END as availability_status
            FROM production_tasks_materials ptm
            JOIN materials m ON ptm.material_id = m.id
            LEFT JOIN base_units mu ON m.base_unit_id = mu.id
            WHERE ptm.task_id = ?
        ");
        $stmt->execute([$task['id']]);
        $task['materials'] = $stmt->fetchAll();
        
        // Этапы выполнения для каждого задания
        $stmt = $pdo->prepare("
            SELECT pts.id, pts.task_id, pts.stage_id, pts.status, ps.name as stage_name, ps.color as stage_color, ps.sort_order,
                CASE pts.status
                    WHEN 'pending' THEN 'Ожидает'
                    WHEN 'in_progress' THEN 'В работе'
                    WHEN 'completed' THEN 'Завершено'
                    WHEN 'skipped' THEN 'Пропущено'
                    ELSE pts.status
                END as status_name
            FROM production_task_stages pts
            JOIN production_stages ps ON pts.stage_id = ps.id
            WHERE pts.task_id = ?
            ORDER BY ps.sort_order
        ");
        $stmt->execute([$task['id']]);
        $task['stages'] = $stmt->fetchAll();
        
        // Прогресс выполнения задания
        $totalStages = count($task['stages']);
        $completedStages = 0;
        foreach ($task['stages'] as $stage) {
            if ($stage['status'] === 'completed') {
                $completedStages++;
            }
        }
        $task['progress_percent'] = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
    }
    unset($task);
    
    $jsonResult = json_encode([
        'order' => $orderInfo,
        'items' => $items, 
        'tasks' => $tasks
    ], JSON_UNESCAPED_UNICODE);
    
    // Очищаем буфер и выводим только JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    echo $jsonResult;
    exit;
}

// Основной код страницы (только если это не API запрос)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'План выпуска';

// Получение фильтров
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$materialIssueFilter = isset($_GET['material_issues']) ? true : false;
$overdueFilter = isset($_GET['overdue']) ? true : false;

// Построение WHERE clause для основного запроса
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(o.order_number LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $whereConditions[] = "o.status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter) {
    $whereConditions[] = "pt.priority = ?";
    $params[] = $priorityFilter;
}

// Добавляем фильтр просроченных в основной SQL
if ($overdueFilter) {
    $whereConditions[] = "o.delivery_date < CURDATE()";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Получение всех заказов с основной информацией (компактно)
$ordersQuery = "
    SELECT o.*, c.name as contractor_name, u.full_name as responsible_name,
        CASE o.status
            WHEN 'new' THEN 'Новый'
            WHEN 'processing' THEN 'В работе'
            WHEN 'ready' THEN 'Готов'
            WHEN 'shipped' THEN 'Отгружен'
            WHEN 'cancelled' THEN 'Отменен'
            ELSE o.status
        END as status_name,
        CASE o.status
            WHEN 'new' THEN '#3498db'
            WHEN 'processing' THEN '#f39c12'
            WHEN 'ready' THEN '#27ae60'
            WHEN 'shipped' THEN '#9b59b6'
            WHEN 'cancelled' THEN '#e74c3c'
            ELSE '#95a5a6'
        END as status_color,
        DATEDIFF(o.delivery_date, CURDATE()) as days_until_delivery,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
        (SELECT COUNT(*) FROM production_tasks WHERE order_id = o.id) as tasks_count,
        (SELECT GROUP_CONCAT(DISTINCT pt.status SEPARATOR ',') FROM production_tasks pt WHERE pt.order_id = o.id) as tasks_statuses
    FROM orders o
    LEFT JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    $whereClause
    ORDER BY 
        CASE WHEN o.delivery_date < CURDATE() THEN 0 ELSE 1 END,
        o.delivery_date ASC,
        o.created_at DESC
";

$stmt = $pdo->prepare($ordersQuery);
$stmt->execute($params);
$allOrders = $stmt->fetchAll();

// Фильтрация заказов с проблемами материалов
$orders = [];
foreach ($allOrders as $order) {
    // Проверяем наличие проблем с материалами для каждого заказа
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM production_tasks pt
        JOIN production_tasks_materials ptm ON ptm.task_id = pt.id
        JOIN materials m ON ptm.material_id = m.id
        WHERE pt.order_id = ? AND ptm.quantity_required > COALESCE(m.current_stock, 0)
    ");
    $stmt->execute([$order['id']]);
    $materialCount = $stmt->fetchColumn();
    
    if ($materialCount > 0) {
        $order['material_shortages'] = $materialCount;
    }
    
    // Если включен фильтр проблем с материалами - пропускаем заказы без проблем
    if ($materialIssueFilter && $materialCount == 0) {
        continue;
    }
    
    $orders[] = $order;
}

// Если нет фильтра проблем с материалами, берем все заказы
if (!$materialIssueFilter) {
    $orders = $allOrders;
}

// Общая статистика
$stats = [
    'total_orders' => count($orders),
    'new_orders' => 0,
    'in_production' => 0,
    'ready' => 0,
    'total_tasks' => 0,
    'tasks_in_progress' => 0,
    'tasks_completed' => 0
];

foreach ($orders as $order) {
    if ($order['status'] === 'new') $stats['new_orders']++;
    if ($order['status'] === 'processing') $stats['in_production']++;
    if ($order['status'] === 'ready') $stats['ready']++;
}

// Подсчет статистики по задачам
if (count($orders) > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN pt.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN pt.status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM production_tasks pt
        JOIN orders o ON pt.order_id = o.id
        WHERE o.id IN (" . implode(',', array_fill(0, count($orders), '?')) . ")
    ");
    $stmt->execute(array_map(fn($o) => $o['id'], $orders));
    $tasksStats = $stmt->fetch();
    $stats['total_tasks'] = $tasksStats['total'] ?? 0;
    $stats['tasks_in_progress'] = $tasksStats['in_progress'] ?? 0;
    $stats['tasks_completed'] = $tasksStats['completed'] ?? 0;
}

// ============================================================================
// ПОДГОТОВКА ДАННЫХ ДЛЯ МОДАЛЬНЫХ ОКОН (Прямо из БД, без JSON API)
// ============================================================================
$ordersData = [];
$productMaterialsCache = []; // Кэш материалов для продуктов

if (!empty($orders)) {
    $orderIds = array_map(fn($o) => (int)$o['id'], $orders);
    $orderIdsPlaceholder = implode(',', array_fill(0, count($orderIds), '?'));

    // 0. Загружаем материалы для всех продуктов из паспортов (для кэша)
    $sqlProductMaterials = "SELECT 
                                pp.product_id,
                                m.id as material_id,
                                m.name_full as material_name,
                                m.code as material_article,
                                ppm.quantity as quantity_per_unit,
                                COALESCE(m.current_stock, 0) as current_stock,
                                COALESCE(m.last_price, 0) as last_price,
                                mu.symbol as unit_name
                            FROM product_passport_materials ppm
                            JOIN materials m ON ppm.material_id = m.id
                            JOIN product_passports pp ON ppm.passport_id = pp.id
                            LEFT JOIN base_units mu ON m.base_unit_id = mu.id
                            WHERE pp.product_id IN ($orderIdsPlaceholder)
                            ORDER BY pp.product_id, ppm.sort_order";
    $stmtProductMaterials = $pdo->prepare($sqlProductMaterials);
    $stmtProductMaterials->execute($orderIds);
    $resProductMaterials = $stmtProductMaterials->fetchAll();
    
    // Группируем по product_id
    foreach ($resProductMaterials as $row) {
        $pid = (string)$row['product_id'];
        if (!isset($productMaterialsCache[$pid])) {
            $productMaterialsCache[$pid] = [];
        }
        $productMaterialsCache[$pid][] = [
            'material_id' => $row['material_id'],
            'material_name' => $row['material_name'],
            'material_article' => $row['material_article'],
            'quantity_per_unit' => (float)$row['quantity_per_unit'],
            'current_stock' => (float)$row['current_stock'],
            'last_price' => (float)$row['last_price'],
            'unit_name' => $row['unit_name'] ?? 'шт.'
        ];
    }

    // 1. Основная информация о заказах
    $sqlOrders = "SELECT o.id, o.order_number, o.status, 
                         COALESCE(c.name, 'Не указан') as client_name, 
                         COALESCE(u.full_name, 'Не назначен') as manager_name, 
                         o.delivery_date 
                  FROM orders o 
                  LEFT JOIN contractors c ON o.customer_id = c.id
                  LEFT JOIN users u ON o.responsible_user_id = u.id
                  WHERE o.id IN ($orderIdsPlaceholder)";
    $stmtOrders = $pdo->prepare($sqlOrders);
    $stmtOrders->execute($orderIds);
    $resOrders = $stmtOrders->fetchAll();
    
    foreach ($resOrders as $row) {
        $orderId = (string)$row['id'];
        $ordersData[$orderId] = [
            'info' => $row,
            'items' => [],
            'tasks' => []
        ];
    }

    // 2. Состав заказов (продукция)
    if (!empty($ordersData)) {
        $sqlItems = "SELECT oi.id, oi.order_id, oi.product_id,
                            p.name as product_name, 
                            p.article as article, 
                            oi.quantity, oi.price 
                     FROM order_items oi 
                     JOIN products p ON oi.product_id = p.id
                     WHERE oi.order_id IN ($orderIdsPlaceholder)";
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute($orderIds);
        $resItems = $stmtItems->fetchAll();
        foreach ($resItems as $row) {
            $oid = (string)$row['order_id'];
            if (isset($ordersData[$oid])) {
                // Добавляем материалы для каждой позиции заказа сразу из кэша
                $pid = (string)$row['product_id'];
                $itemMaterials = [];
                if (isset($productMaterialsCache[$pid])) {
                    foreach ($productMaterialsCache[$pid] as $mat) {
                        $requiredTotal = $mat['quantity_per_unit'] * $row['quantity'];
                        $itemMaterials[] = [
                            'material_id' => $mat['material_id'],
                            'name' => $mat['material_name'],
                            'article' => $mat['material_article'],
                            'norm' => $mat['quantity_per_unit'],
                            'required' => $requiredTotal,
                            'stock' => $mat['current_stock'],
                            'price' => $mat['last_price'],
                            'cost' => $requiredTotal * $mat['last_price'],
                            'unit' => $mat['unit_name']
                        ];
                    }
                }
                $row['materials'] = $itemMaterials;
                $ordersData[$oid]['items'][] = $row;
            }
        }
    }

    // 3. Производственные задания для этих заказов
    if (!empty($ordersData)) {
        $sqlTasks = "SELECT pt.id, pt.order_id, pt.product_id,
                            p.name as product_name, 
                            pt.quantity_plan as plan_qty, pt.quantity_fact as done_qty, pt.status as task_status, pt.start_date, pt.end_date 
                     FROM production_tasks pt 
                     JOIN products p ON pt.product_id = p.id
                     WHERE pt.order_id IN ($orderIdsPlaceholder)";
        $stmtTasks = $pdo->prepare($sqlTasks);
        $stmtTasks->execute($orderIds);
        $resTasks = $stmtTasks->fetchAll();
        foreach ($resTasks as $row) {
            $oid = (string)$row['order_id'];
            if (isset($ordersData[$oid])) {
                $row['materials'] = [];
                $row['stages'] = [];
                $ordersData[$oid]['tasks'][] = $row;
            }
        }
    }

    // 4. Материалы и этапы для заданий
    foreach ($ordersData as &$ord) {
        foreach ($ord['tasks'] as &$task) {
            $tid = (int)$task['id'];
            
            // Материалы с полной информацией - берем из паспорта продукта или из задания
            // Сначала пробуем получить материалы из производственного задания
            $sqlMat = "SELECT m.name_full as material_name, m.code as material_article, 
                              ptm.quantity_required as required_qty, 
                              COALESCE(m.current_stock, 0) as available_qty,
                              mu.symbol as unit_name,
                              COALESCE(m.last_price, 0) as price_per_unit
                       FROM production_tasks_materials ptm
                       JOIN materials m ON ptm.material_id = m.id
                       LEFT JOIN base_units mu ON m.base_unit_id = mu.id
                       WHERE ptm.task_id = ?
                       ORDER BY m.name_full";
            $stmtMat = $pdo->prepare($sqlMat);
            $stmtMat->execute([$tid]);
            $resMat = $stmtMat->fetchAll();
            
            // Если в задании нет материалов, берем из паспорта продукта
            if (empty($resMat) && !empty($task['product_id'])) {
                $sqlPassportMat = "SELECT 
                                          ppm.quantity as required_qty,
                                          'шт.' as unit_name,
                                          m.name_full as material_name, 
                                          m.code as material_article,
                                          COALESCE(m.current_stock, 0) as available_qty,
                                          COALESCE(m.last_price, 0) as price_per_unit
                                   FROM product_passport_materials ppm
                                   JOIN materials m ON ppm.material_id = m.id
                                   WHERE ppm.passport_id IN (
                                       SELECT id FROM product_passports WHERE product_id = ?
                                   )
                                   ORDER BY ppm.sort_order, m.name_full";
                $stmtPassportMat = $pdo->prepare($sqlPassportMat);
                $stmtPassportMat->execute([$task['product_id']]);
                $resMat = $stmtPassportMat->fetchAll();
                
                // Добавляем информацию о складе для материалов из паспорта
                foreach ($resMat as &$matRow) {
                    if (empty($matRow['unit_name'])) {
                        $matRow['unit_name'] = 'шт.';
                    }
                    if (!isset($matRow['price_per_unit'])) {
                        $matRow['price_per_unit'] = 0;
                    }
                }
                unset($matRow);
            }
            
            foreach ($resMat as $row) {
                $task['materials'][] = $row;
            }

            // Этапы
            $sqlStages = "SELECT ps.name as stage_name, pts.status, NULL as date_completed, ps.sort_order 
                          FROM production_task_stages pts
                          JOIN production_stages ps ON pts.stage_id = ps.id
                          WHERE pts.task_id = ?
                          ORDER BY ps.sort_order ASC";
            $stmtStages = $pdo->prepare($sqlStages);
            $stmtStages->execute([$tid]);
            $resStages = $stmtStages->fetchAll();
            foreach ($resStages as $row) {
                $task['stages'][] = $row;
            }
        }
    }
}

// Отладка: выводим количество загруженных заказов
error_log('DEBUG: Loaded ' . count($ordersData) . ' orders into ordersData');
foreach ($ordersData as $oid => $data) {
    error_log('DEBUG: Order ' . $oid . ' has ' . count($data['items']) . ' items and ' . count($data['tasks']) . ' tasks');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <style>
        .plan-header { margin-bottom: 24px; }
        .plan-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-box-value { font-size: 28px; font-weight: 700; color: #2c3e50; }
        .stat-box-label { font-size: 13px; color: #7f8c8d; margin-top: 4px; }
        .order-card { background: #fff; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .order-card-header { padding: 16px 20px; background: #f8f9fa; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
        .order-card-body { padding: 20px; }
        .order-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .info-item label { font-size: 12px; color: #7f8c8d; display: block; margin-bottom: 4px; }
        .info-item value { font-size: 14px; color: #2c3e50; font-weight: 500; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
        .items-table th { background: #f8f9fa; font-weight: 600; font-size: 13px; color: #495057; }
        .items-table td { font-size: 14px; }
        .tasks-section { margin-top: 20px; }
        .task-card { background: #fafbfc; border: 1px solid #e9ecef; border-radius: 6px; padding: 16px; margin-bottom: 12px; }
        .task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .task-title { font-weight: 600; color: #2c3e50; }
        .progress-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); transition: width 0.3s; }
        .stages-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .stage-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; background: #e9ecef; color: #495057; }
        .stage-badge.completed { background: #d4edda; color: #155724; }
        .stage-badge.in_progress { background: #fff3cd; color: #856404; }
        .stage-badge.pending { background: #e9ecef; color: #495057; }
        .expand-btn { background: none; border: none; cursor: pointer; font-size: 18px; color: #7f8c8d; }
        .task-details { display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e9ecef; }
        .task-details.show { display: block; }
        
        /* Стили для модального окна деталей заказа */
        .passport-modal-overlay { 
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
            background: rgba(0, 0, 0, 0.5); 
            display: flex; align-items: center; justify-content: center; 
            z-index: 10000;
        }
        .passport-modal { 
            background: #fff; 
            border-radius: 8px; 
            max-width: 1100px; 
            width: 95%; 
            max-height: 90vh; 
            overflow: hidden; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .passport-modal-header { 
            padding: 16px 20px; 
            background: #f8f9fa; 
            border-bottom: 1px solid #e9ecef; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        .passport-modal-title { font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 4px; }
        .passport-modal-subtitle { font-size: 13px; color: #7f8c8d; }
        .passport-modal-close { 
            background: none; 
            border: none; 
            color: #7f8c8d; 
            font-size: 24px; 
            width: 32px; 
            height: 32px; 
            cursor: pointer; 
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .passport-modal-close:hover { color: #2c3e50; background: #e9ecef; border-radius: 4px; }
        .passport-modal-body { 
            padding: 20px; 
            overflow-y: auto; 
            max-height: calc(90vh - 80px);
        }
        .passport-section { 
            margin-bottom: 20px; 
            background: #f8f9fa; 
            border-radius: 6px; 
            padding: 16px;
        }
        .passport-section-title { 
            font-size: 14px; 
            font-weight: 600; 
            color: #2c3e50; 
            margin-bottom: 12px; 
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .specs-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .spec-row { display: flex; flex-direction: column; }
        .spec-label { font-size: 11px; color: #7f8c8d; margin-bottom: 4px; }
        .spec-value { font-size: 13px; color: #2c3e50; font-weight: 500; }
        
        /* Таблицы в модальном окне */
        .materials-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .materials-table th, .materials-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e9ecef; }
        .materials-table th { background: #f8f9fa; font-weight: 600; color: #495057; font-size: 12px; }
        .materials-table tr:hover { background: #f8f9fa; }
        
        /* Карточка задания в модальном окне */
        .modal-task-card { 
            background: white; 
            border: 1px solid #e9ecef; 
            border-radius: 6px; 
            padding: 16px; 
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .modal-task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .modal-task-title { font-size: 14px; font-weight: 600; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
        .modal-task-progress { margin: 12px 0; }
        .modal-progress-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .modal-progress-fill { height: 100%; background: #3498db; transition: width 0.3s; border-radius: 4px; }
        
        /* Этапы производства - timeline */
        .production-timeline { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 8px; 
            margin-top: 12px;
            padding: 12px;
            background: #fff;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        .timeline-stage { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 8px 12px;
            border-radius: 6px;
            min-width: 100px;
            border: 1px solid transparent;
        }
        .timeline-stage.completed { background: #d4edda; border-color: #28a745; }
        .timeline-stage.in_progress { background: #fff3cd; border-color: #ffc107; }
        .timeline-stage.pending { background: #f8f9fa; border-color: #dee2e6; }
        .timeline-stage-icon { font-size: 20px; margin-bottom: 4px; }
        .timeline-stage-name { font-size: 12px; font-weight: 600; color: #2c3e50; text-align: center; margin-bottom: 2px; }
        .timeline-stage-status { font-size: 10px; color: #7f8c8d; text-transform: uppercase; }
        .timeline-stage.completed .timeline-stage-status { color: #155724; }
        .timeline-stage.in_progress .timeline-stage-status { color: #856404; }
        
        /* Материалы - индикаторы */
        .material-status-indicator { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
        .material-status-indicator.sufficient { background: #28a745; }
        .material-status-indicator.insufficient { background: #dc3545; }
        .material-shortage-warning { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 10px 12px; 
            border-radius: 4px; 
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 3px solid #dc3545;
            font-size: 13px;
        }
        
        /* Бейджи */
        .badge { 
            padding: 4px 10px; 
            border-radius: 12px; 
            font-size: 11px; 
            font-weight: 600; 
            display: inline-block;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-primary { background: #cce5ff; color: #004085; }
        
        /* Кнопка раскрытия */
        .toggle-details-btn { 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            color: #495057; 
            padding: 6px 12px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 12px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .toggle-details-btn:hover { background: #e9ecef; }
        .toggle-details-btn.active { background: #3498db; color: white; border-color: #3498db; }
        
        /* Анимация появления контента */
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        /* Подсветка строк с проблемами */
        tr.row-overdue { background-color: #fff5f5 !important; }
        tr.row-material-issue { background-color: #fff9e6 !important; }
        tr.row-overdue.row-material-issue { background: linear-gradient(90deg, #fff5f5 0%, #fff9e6 50%, #fff5f5 100%) !important; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php require_once BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="plan-header">
                    <h2 style="font-size: 24px; font-weight: 600; margin-bottom: 8px;">📋 План выпуска</h2>
                    <p style="color: var(--text-secondary);">Все заказы и статус производства</p>
                </div>
                
                <!-- Статистика -->
                <div class="plan-stats">
                    <div class="stat-box">
                        <div class="stat-box-value"><?= $stats['total_orders'] ?></div>
                        <div class="stat-box-label">Всего заказов</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-value"><?= $stats['new_orders'] ?></div>
                        <div class="stat-box-label">Новые</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-value"><?= $stats['in_production'] ?></div>
                        <div class="stat-box-label">В производстве</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-value"><?= $stats['ready'] ?></div>
                        <div class="stat-box-label">Готовы</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-value"><?= $stats['tasks_in_progress'] ?></div>
                        <div class="stat-box-label">Заданий в работе</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-value"><?= $stats['tasks_completed'] ?></div>
                        <div class="stat-box-label">Заданий завершено</div>
                    </div>
                </div>
                
                <!-- Фильтры -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Поиск</label>
                                <input type="text" name="search" class="form-control" placeholder="№ заказа или заказчик" value="<?= e($search) ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Статус</label>
                                <select name="status" class="form-control">
                                    <option value="">Все статусы</option>
                                    <option value="new" <?= $statusFilter == 'new' ? 'selected' : '' ?>>Новый</option>
                                    <option value="processing" <?= $statusFilter == 'processing' ? 'selected' : '' ?>>В работе</option>
                                    <option value="ready" <?= $statusFilter == 'ready' ? 'selected' : '' ?>>Готов</option>
                                    <option value="shipped" <?= $statusFilter == 'shipped' ? 'selected' : '' ?>>Отгружен</option>
                                    <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" title="Заказы с нехваткой материалов">📦 Проблемы с материалами</label>
                                <input type="checkbox" name="material_issues" value="1" <?= $materialIssueFilter ? 'checked' : '' ?> style="width: 20px; height: 20px;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" title="Просроченные заказы">⏰ Просроченные</label>
                                <input type="checkbox" name="overdue" value="1" <?= $overdueFilter ? 'checked' : '' ?> style="width: 20px; height: 20px;">
                            </div>
                            
                            <div style="display: flex; gap: 8px;">
                                <button type="submit" class="btn btn-primary">Фильтр</button>
                                <a href="" class="btn btn-secondary">Сброс</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Заказы (компактная таблица) -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body" style="padding: 0;">
                        <table class="items-table" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th>№ заказа</th>
                                    <th>Статус</th>
                                    <th>Заказчик</th>
                                    <th>Дата доставки</th>
                                    <th>Позиций</th>
                                    <th>Заданий</th>
                                    <th>Ответственный</th>
                                    <th style="width: 80px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): 
                                    $isOverdue = isset($order['days_until_delivery']) && $order['days_until_delivery'] < 0;
                                    $hasMaterialIssues = isset($order['material_shortages']) && $order['material_shortages'] > 0;
                                ?>
                                <tr style="cursor: pointer;" onclick="openOrderDetailModal('<?= $order['id'] ?>')" 
                                    class="<?= $isOverdue ? 'row-overdue' : '' ?> <?= $hasMaterialIssues ? 'row-material-issue' : '' ?>">
                                    <td><strong><?= e($order['order_number']) ?></strong></td>
                                    <td>
                                        <span class="badge" style="background: <?= e($order['status_color']) ?>20; color: <?= e($order['status_color']) ?>">
                                            <?= e($order['status_name']) ?>
                                        </span>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge" style="background: #e74c3c; color: white; font-size: 10px;">⚠️</span>
                                        <?php endif; ?>
                                        <?php if ($hasMaterialIssues): ?>
                                            <span class="badge" style="background: #f39c12; color: white; font-size: 10px;">📦</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($order['contractor_name'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($order['delivery_date']): ?>
                                            <?= formatDate($order['delivery_date']) ?>
                                            <span style="color: <?= $order['days_until_delivery'] < 0 ? '#e74c3c' : ($order['days_until_delivery'] <= 3 ? '#f39c12' : '#27ae60') ?>; font-size: 12px;">
                                                (<?= $order['days_until_delivery'] ?> дн.)
                                            </span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $order['items_count'] ?? 0 ?></td>
                                    <td><?= $order['tasks_count'] ?? 0 ?></td>
                                    <td><?= e($order['responsible_name'] ?? '—') ?></td>
                                    <td onclick="event.stopPropagation();">
                                        <button class="btn btn-sm btn-primary" onclick="openOrderDetailModal('<?= $order['id'] ?>')">👁️</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if (empty($orders)): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 60px 20px;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                        <h3>Заказов нет</h3>
                        <p style="color: var(--text-secondary);">Создайте первый заказ для начала работы</p>
                        <a href="<?= pageUrl('modules/orders/create.php') ?>" class="btn btn-primary" style="margin-top: 16px;">
                            ➕ Создать заказ
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Модальное окно деталей заказа -->
                <div id="orderDetailModalOverlay" class="passport-modal-overlay" onclick="closeOrderDetailModal(event)" style="display: none;">
                    <div class="passport-modal">
                        <div class="passport-modal-header">
                            <div>
                                <div class="passport-modal-title" id="modalOrderNumber">Заказ №—</div>
                                <div class="passport-modal-subtitle" id="modalOrderStatus">Статус: —</div>
                            </div>
                            <button class="passport-modal-close" onclick="closeOrderDetailModalDirect()">×</button>
                        </div>
                        <div class="passport-modal-body" id="modalOrderBody">
                            <div style="text-align: center; padding: 40px;">
                                <p>Загрузка...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Данные из базы уже загружены в PHP массив ordersData
        // Преобразуем его в JS объект для использования
        var ordersData = <?php echo json_encode($ordersData, JSON_UNESCAPED_UNICODE); ?>;
        var productMaterialsCache = <?php echo json_encode($productMaterialsCache, JSON_UNESCAPED_UNICODE); ?>;
        
        console.log('Orders data loaded:', Object.keys(ordersData).length, 'orders');
        console.log('Product materials cache loaded:', Object.keys(productMaterialsCache).length, 'products');
        
        // Отладка: выводим детали первого заказа
        var firstOrderId = Object.keys(ordersData)[0];
        if (firstOrderId) {
            console.log('First order details:', ordersData[firstOrderId]);
            if (ordersData[firstOrderId].items && ordersData[firstOrderId].items.length > 0) {
                console.log('First item materials:', ordersData[firstOrderId].items[0].materials);
            }
        }
        
        // Открытие модального окна с деталями заказа (без AJAX, данные уже загружены)
        function openOrderDetailModal(orderId) {
            const modal = document.getElementById('orderDetailModalOverlay');
            const body = document.getElementById('modalOrderBody');
            
            modal.style.display = 'flex';
            
            // Получаем данные из заранее загруженного массива
            var orderIdStr = String(orderId);
            var data = ordersData[orderIdStr];
            
            console.log('Opening order:', orderIdStr, 'Data found:', !!data);
            
            if (!data || !data.info) {
                body.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Данные о заказе не найдены</div>';
                return;
            }
            
            renderOrderDetailModal(data, orderIdStr);
        }
        
        function renderOrderDetailModal(data, orderId) {
            const body = document.getElementById('modalOrderBody');
            // data - это объект заказа с полями: info, items, tasks
            
            console.log('Rendering order:', orderId, 'Data:', data);
            
            const order = data.info || {};
            let html = '';
            
            // Обновляем заголовок модального окна
            document.getElementById('modalOrderNumber').textContent = 'Заказ №' + (order.order_number || '—');
            document.getElementById('modalOrderStatus').textContent = 'Статус: ' + (order.status || '—');
            
            // Проверка на проблемы с материалами
            let hasMaterialShortages = false;
            if (data.tasks) {
                data.tasks.forEach(function(task) {
                    if (task.materials) {
                        task.materials.forEach(function(mat) {
                            if (mat.available_qty < mat.required_qty) {
                                hasMaterialShortages = true;
                            }
                        });
                    }
                });
            }
            
            // Предупреждение о нехватке материалов
            if (hasMaterialShortages) {
                html += '<div class="material-shortage-warning fade-in">';
                html += '<span style="font-size: 20px;">⚠️</span>';
                html += '<div><strong>Внимание! По этому заказу есть нехватка материалов!</strong><br>Проверьте информацию в производственных заданиях ниже</div>';
                html += '</div>';
            }
            
            // Основная информация о заказе
            html += '<div class="passport-section fade-in">';
            html += '<div class="passport-section-title">Информация о заказе</div>';
            html += '<div class="specs-list">';
            html += '<div class="spec-row"><div class="spec-label">№ заказа</div><div class="spec-value"><strong>' + escapeHtml(order.order_number || '—') + '</strong></div></div>';
            html += '<div class="spec-row"><div class="spec-label">Статус</div><div class="spec-value"><span class="badge badge-primary">' + escapeHtml(order.status || '—') + '</span></div></div>';
            html += '<div class="spec-row"><div class="spec-label">Заказчик</div><div class="spec-value">' + escapeHtml(order.client_name || '—') + '</div></div>';
            html += '<div class="spec-row"><div class="spec-label">Ответственный</div><div class="spec-value">' + escapeHtml(order.manager_name || '—') + '</div></div>';
            if (order.delivery_date) {
                html += '<div class="spec-row"><div class="spec-label">Дата доставки</div><div class="spec-value">' + formatDate(order.delivery_date) + '</div></div>';
            }
            html += '</div></div>';
            
            // Позиции заказа с материалами
            if (data.items && data.items.length > 0) {
                // Считаем общую сумму и прогресс
                var totalSum = 0;
                var totalQty = 0;
                var completedQty = 0;
                
                // Загружаем материалы для каждого товара из паспорта
                data.items.forEach(function(item) {
                    totalSum += (item.quantity * item.price);
                    totalQty += parseFloat(item.quantity || 0);
                    
                    // Ищем выполненное количество из заданий по product_id
                    if (data.tasks) {
                        data.tasks.forEach(function(task) {
                            if (task.product_id === item.product_id) {
                                completedQty += parseFloat(task.done_qty || 0);
                            }
                        });
                    }
                    
                    // Материалы уже загружены на сервере, используем их
                    if (!item.materials || item.materials.length === 0) {
                        item.materials = [];
                        if (window.productMaterialsCache && window.productMaterialsCache[item.product_id]) {
                            var norms = window.productMaterialsCache[item.product_id];
                            norms.forEach(function(norm) {
                                var requiredTotal = norm.quantity_per_unit * item.quantity;
                                var cost = requiredTotal * (norm.last_price || 0);
                                item.materials.push({
                                    name: norm.material_name,
                                    article: norm.material_article,
                                    norm: norm.quantity_per_unit,
                                    required: requiredTotal,
                                    stock: norm.current_stock || 0,
                                    price: norm.last_price || 0,
                                    cost: cost,
                                    unit: norm.unit_name || 'шт.',
                                    material_id: norm.material_id
                                });
                            });
                        }
                    }
                    
                    console.log('Item', item.product_id, 'materials:', item.materials);
                });
                
                // Создаем сводную таблицу материалов по всему заказу
                var materialSummary = {};
                data.items.forEach(function(item) {
                    if (item.materials) {
                        item.materials.forEach(function(mat) {
                            if (!materialSummary[mat.material_id]) {
                                materialSummary[mat.material_id] = {
                                    name: mat.name,
                                    article: mat.article,
                                    required: 0,
                                    stock: mat.stock,
                                    price: mat.price,
                                    unit: mat.unit
                                };
                            }
                            materialSummary[mat.material_id].required += mat.required;
                        });
                    }
                });
                
                console.log('Material summary:', materialSummary);
                
                // Считаем итоги по сводной таблице
                var totalMaterialsNeeded = Object.keys(materialSummary).length;
                var materialsOk = 0;
                var materialsShort = 0;
                var totalMaterialsCost = 0;
                
                for (var mid in materialSummary) {
                    var ms = materialSummary[mid];
                    ms.is_enough = (ms.stock >= ms.required);
                    ms.shortage = Math.max(0, ms.required - ms.stock);
                    ms.total_cost = ms.required * ms.price;
                    totalMaterialsCost += ms.total_cost;
                    
                    if (ms.is_enough) {
                        materialsOk++;
                    } else {
                        materialsShort++;
                    }
                }
                
                var orderProgress = totalQty > 0 ? Math.round((completedQty / totalQty) * 100) : 0;
                if (orderProgress > 100) orderProgress = 100;
                
                html += '<div class="passport-section fade-in">';
                html += '<div class="passport-section-title" style="display: flex; justify-content: space-between; align-items: center;">';
                html += '<span>📦 Состав заказа (' + data.items.length + ' поз.)</span>';
                html += '</div>';
                
                // Прогресс выполнения заказа
                html += '<div style="margin-bottom: 16px; padding: 12px; background: #fff; border-radius: 6px; border: 1px solid #e9ecef;">';
                html += '<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">';
                html += '<span style="font-size: 13px; color: #7f8c8d;">Выполнено:</span>';
                html += '<span style="font-size: 13px; font-weight: 600; color: #2c3e50;">' + orderProgress + '% (' + completedQty + ' из ' + totalQty + ' шт.)</span>';
                html += '</div>';
                html += '<div class="modal-progress-bar" style="height: 12px;">';
                html += '<div class="modal-progress-fill" style="width: ' + orderProgress + '%; background: linear-gradient(90deg, #3498db, #2ecc71);"></div>';
                html += '</div>';
                html += '</div>';
                
                // Таблица с позициями
                html += '<table class="materials-table">';
                html += '<thead><tr><th>Продукция</th><th>Артикул</th><th style="text-align: center;">Кол-во</th><th style="text-align: right;">Цена</th><th style="text-align: right;">Сумма</th></tr></thead>';
                html += '<tbody>';
                data.items.forEach(function(item) {
                    var itemSum = item.quantity * item.price;
                    html += '<tr>';
                    html += '<td><strong>' + escapeHtml(item.product_name) + '</strong></td>';
                    html += '<td><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-size: 11px;">' + escapeHtml(item.article) + '</code></td>';
                    html += '<td style="text-align: center; font-weight: 600;">' + item.quantity + '</td>';
                    html += '<td style="text-align: right;">' + formatMoney(item.price) + '</td>';
                    html += '<td style="text-align: right;"><strong>' + formatMoney(itemSum) + '</strong></td>';
                    html += '</tr>';
                });
                html += '</tbody>';
                html += '<tfoot>';
                html += '<tr style="background: #f8f9fa; font-weight: 700;">';
                html += '<td colspan="4" style="text-align: right; padding: 12px;">Итого:</td>';
                html += '<td style="text-align: right; padding: 12px; color: #2c3e50; font-size: 15px;">' + formatMoney(totalSum) + '</td>';
                html += '</tr>';
                html += '</tfoot>';
                html += '</table>';
                
                // Детализация материалов по каждому товару
                data.items.forEach(function(item, itemIndex) {
                    if (item.materials && item.materials.length > 0) {
                        html += '<div style="margin-top: 16px; border: 1px solid #e9ecef; border-radius: 6px; overflow: hidden;">';
                        html += '<div style="background: #f8f9fa; padding: 12px 16px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center;">';
                        html += '<div style="font-weight: 600; color: #2c3e50;">' + escapeHtml(item.product_name) + ' (' + item.quantity + ' шт.)</div>';
                        html += '<button class="toggle-details-btn" onclick="toggleItemMaterials(' + itemIndex + ', this)">';
                        html += '<span>📋</span><span class="btn-text">Материалы</span>';
                        html += '</button>';
                        html += '</div>';
                        html += '<div class="item-materials-section" id="item-materials-' + itemIndex + '" style="display: block; padding: 12px 16px; background: white;">';
                        html += '<table class="materials-table" style="font-size: 12px;">';
                        html += '<thead><tr><th>Материал</th><th>Артикул</th><th style="text-align: center;">Норма</th><th style="text-align: center;">Требуется</th><th style="text-align: center;">На складе</th><th style="text-align: right;">Цена</th><th style="text-align: right;">Сумма</th><th>Статус</th></tr></thead>';
                        html += '<tbody>';
                        var itemMatCost = 0;
                        item.materials.forEach(function(mat) {
                            var isSufficient = mat.stock >= mat.required;
                            var statusHtml = isSufficient 
                                ? '<span class="badge badge-success"><span class="material-status-indicator sufficient"></span>Хватает</span>'
                                : '<span class="badge badge-danger"><span class="material-status-indicator insufficient"></span>Не хватает (' + (mat.required - mat.stock) + ' ' + mat.unit + ')</span>';
                            itemMatCost += mat.cost;
                            html += '<tr style="' + (!isSufficient ? 'background: #fff5f5;' : '') + '">';
                            html += '<td><strong>' + escapeHtml(mat.name) + '</strong></td>';
                            html += '<td><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-size: 10px;">' + escapeHtml(mat.article) + '</code></td>';
                            html += '<td style="text-align: center;">' + mat.norm + ' ' + mat.unit + '</td>';
                            html += '<td style="text-align: center; font-weight: 600;">' + mat.required + ' ' + mat.unit + '</td>';
                            html += '<td style="text-align: center;">' + mat.stock + ' ' + mat.unit + '</td>';
                            html += '<td style="text-align: right;">' + formatMoney(mat.price) + '</td>';
                            html += '<td style="text-align: right;"><strong>' + formatMoney(mat.cost) + '</strong></td>';
                            html += '<td>' + statusHtml + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody>';
                        html += '<tfoot>';
                        html += '<tr style="background: #f8f9fa; font-weight: 700;">';
                        html += '<td colspan="6" style="text-align: right; padding: 10px;">Итого на позицию:</td>';
                        html += '<td colspan="2" style="text-align: right; padding: 10px; color: #2c3e50;">' + formatMoney(itemMatCost) + '</td>';
                        html += '</tr>';
                        html += '</tfoot>';
                        html += '</table></div></div>';
                    }
                });
                
                // Сводная таблица материалов по всему заказу
                if (Object.keys(materialSummary).length > 0) {
                    html += '<div style="margin-top: 20px; border: 1px solid #e9ecef; border-radius: 6px; overflow: hidden;">';
                    html += '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 12px 16px; color: white;">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
                    html += '<div style="font-weight: 600; font-size: 14px;">📊 Сводная потребность в материалах по заказу</div>';
                    html += '<div style="display: flex; gap: 12px; font-size: 12px;">';
                    html += '<span style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 12px;">Всего: <strong>' + totalMaterialsNeeded + '</strong></span>';
                    html += '<span style="background: rgba(40,167,69,0.3); padding: 4px 10px; border-radius: 12px;">✅ Хватает: <strong>' + materialsOk + '</strong></span>';
                    html += '<span style="background: rgba(220,53,69,0.3); padding: 4px 10px; border-radius: 12px;">⚠️ Не хватает: <strong>' + materialsShort + '</strong></span>';
                    html += '</div>';
                    html += '</div>';
                    html += '<table class="materials-table">';
                    html += '<thead><tr><th>Материал</th><th>Артикул</th><th style="text-align: center;">Требуется всего</th><th style="text-align: center;">На складе</th><th style="text-align: right;">Цена</th><th style="text-align: right;">Общая сумма</th><th>Статус</th></tr></thead>';
                    html += '<tbody>';
                    for (var mid in materialSummary) {
                        var ms = materialSummary[mid];
                        var statusHtml = ms.is_enough 
                            ? '<span class="badge badge-success"><span class="material-status-indicator sufficient"></span>Хватает</span>'
                            : '<span class="badge badge-danger"><span class="material-status-indicator insufficient"></span>Не хватает (' + ms.shortage + ' ' + ms.unit + ')</span>';
                        html += '<tr style="' + (!ms.is_enough ? 'background: #fff5f5;' : '') + '">';
                        html += '<td><strong>' + escapeHtml(ms.name) + '</strong></td>';
                        html += '<td><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-size: 10px;">' + escapeHtml(ms.article) + '</code></td>';
                        html += '<td style="text-align: center; font-weight: 600;">' + ms.required + ' ' + ms.unit + '</td>';
                        html += '<td style="text-align: center;">' + ms.stock + ' ' + ms.unit + '</td>';
                        html += '<td style="text-align: right;">' + formatMoney(ms.price) + '</td>';
                        html += '<td style="text-align: right;"><strong>' + formatMoney(ms.total_cost) + '</strong></td>';
                        html += '<td>' + statusHtml + '</td>';
                        html += '</tr>';
                    }
                    html += '</tbody>';
                    html += '<tfoot>';
                    html += '<tr style="background: #f8f9fa; font-weight: 700;">';
                    html += '<td colspan="5" style="text-align: right; padding: 12px;">Итого стоимость материалов заказа:</td>';
                    html += '<td colspan="2" style="text-align: right; padding: 12px; color: #2c3e50; font-size: 15px;">' + formatMoney(totalMaterialsCost) + '</td>';
                    html += '</tr>';
                    html += '</tfoot>';
                    html += '</table></div>';
                }
                
                html += '</div>';
            } else {
                html += '<div class="passport-section fade-in"><div class="passport-section-title">Состав заказа</div><p style="color: #95a5a6;">Нет данных</p></div>';
            }
            
            // Производственные задания
            if (data.tasks && data.tasks.length > 0) {
                html += '<div class="passport-section fade-in">';
                html += '<div class="passport-section-title">Производственные задания (' + data.tasks.length + ')</div>';
                
                data.tasks.forEach(function(task, index) {
                    var hasShortage = false;
                    if (task.materials) {
                        task.materials.forEach(function(mat) {
                            if (mat.available_qty < mat.required_qty) hasShortage = true;
                        });
                    }
                    
                    // Рассчитываем процент выполнения
                    var percent = 0;
                    if (task.plan_qty && task.plan_qty > 0) {
                        percent = Math.round((task.done_qty / task.plan_qty) * 100);
                    }
                    if (percent > 100) percent = 100;
                    
                    html += '<div class="modal-task-card fade-in">';
                    
                    // Заголовок задания
                    html += '<div class="modal-task-header">';
                    html += '<div class="modal-task-title">';
                    html += '<div>';
                    html += '<div style="font-size: 12px; color: #7f8c8d;">Задание #' + escapeHtml(task.id) + '</div>';
                    html += '<div style="font-size: 14px; font-weight: 600;">' + escapeHtml(task.product_name) + '</div>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div style="display: flex; align-items: center; gap: 8px;">';
                    var taskStatusNames = {
                        'planned': 'Запланировано',
                        'in_progress': 'В работе',
                        'completed': 'Завершено',
                        'cancelled': 'Отменено'
                    };
                    var statusName = taskStatusNames[task.task_status] || task.task_status || '—';
                    html += '<span class="badge">' + escapeHtml(statusName) + '</span>';
                    html += '<button class="toggle-details-btn" onclick="toggleTaskDetails(' + task.id + ', this)" data-task-id="' + task.id + '">';
                    html += '<span>📋</span><span class="btn-text">Подробнее</span>';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
                    
                    // Прогресс
                    html += '<div class="modal-task-progress">';
                    html += '<div style="display: flex; justify-content: space-between; font-size: 12px; color: #7f8c8d; margin-bottom: 6px;">';
                    html += '<span>План: <strong>' + (task.plan_qty || '—') + ' шт.</strong></span>';
                    html += '<span>Факт: <strong>' + (task.done_qty || '0') + ' шт.</strong></span>';
                    if (task.start_date) {
                        html += '<span>' + formatDate(task.start_date) + ' - ' + formatDate(task.end_date) + '</span>';
                    }
                    html += '</div>';
                    
                    html += '<div class="modal-progress-bar">';
                    html += '<div class="modal-progress-fill" style="width: ' + percent + '%;"></div>';
                    html += '</div>';
                    html += '<div style="font-size: 12px; color: #7f8c8d; text-align: right; margin-top: 4px;">Выполнено: <strong>' + percent + '%</strong></div>';
                    html += '</div>';
                    
                    // Предупреждение о нехватке материалов для этого задания
                    if (hasShortage) {
                        html += '<div class="material-shortage-warning" style="margin-top: 10px; padding: 8px 12px; font-size: 12px;">';
                        html += '<span>⚠️</span>';
                        html += '<div><strong>Есть нехватка материалов!</strong></div>';
                        html += '</div>';
                    }
                    
                    // Детали (материалы и этапы)
                    html += '<div class="task-details" id="task-details-' + task.id + '" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e9ecef;">';
                    
                    // Материалы
                    if (task.materials && task.materials.length > 0) {
                        var totalMaterialsCost = 0;
                        
                        html += '<div style="margin-bottom: 16px;">';
                        html += '<h5 style="margin-bottom: 10px; font-size: 13px; color: #2c3e50; font-weight: 600;">📦 Материалы для задания:</h5>';
                        html += '<table class="materials-table">';
                        html += '<thead><tr><th>Материал</th><th>Артикул</th><th style="text-align: center;">Требуется</th><th style="text-align: center;">На складе</th><th style="text-align: right;">Цена</th><th style="text-align: right;">Сумма</th><th>Статус</th></tr></thead>';
                        html += '<tbody>';
                        task.materials.forEach(function(mat) {
                            var isSufficient = mat.available_qty >= mat.required_qty;
                            var unit = mat.unit_name || 'шт.';
                            var pricePerUnit = mat.price_per_unit || 0;
                            var totalCost = mat.required_qty * pricePerUnit;
                            totalMaterialsCost += totalCost;
                            
                            var statusHtml = isSufficient 
                                ? '<span class="badge badge-success"><span class="material-status-indicator sufficient"></span>Хватает</span>'
                                : '<span class="badge badge-danger"><span class="material-status-indicator insufficient"></span>Не хватает (' + (mat.required_qty - mat.available_qty) + ' ' + unit + ')</span>';
                            html += '<tr style="' + (!isSufficient ? 'background: #fff5f5;' : '') + '">';
                            html += '<td><strong>' + escapeHtml(mat.material_name) + '</strong></td>';
                            html += '<td><code style="background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-size: 11px;">' + escapeHtml(mat.material_article) + '</code></td>';
                            html += '<td style="text-align: center;">' + mat.required_qty + ' ' + unit + '</td>';
                            html += '<td style="text-align: center;">' + mat.available_qty + ' ' + unit + '</td>';
                            html += '<td style="text-align: right;">' + formatMoney(pricePerUnit) + '</td>';
                            html += '<td style="text-align: right;"><strong>' + formatMoney(totalCost) + '</strong></td>';
                            html += '<td>' + statusHtml + '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody>';
                        html += '<tfoot>';
                        html += '<tr style="background: #f8f9fa; font-weight: 700;">';
                        html += '<td colspan="5" style="text-align: right; padding: 12px;">Итого материалы:</td>';
                        html += '<td colspan="2" style="text-align: right; padding: 12px; color: #2c3e50;">' + formatMoney(totalMaterialsCost) + '</td>';
                        html += '</tr>';
                        html += '</tfoot>';
                        html += '</table></div>';
                    }
                    
                    // Этапы производства - Timeline
                    if (task.stages && task.stages.length > 0) {
                        html += '<div style="margin-top: 16px;">';
                        html += '<h5 style="margin-bottom: 10px; font-size: 13px; color: #2c3e50; font-weight: 600;">🔄 Этапы производства:</h5>';
                        html += '<div class="production-timeline">';
                        
                        // Определяем иконки для этапов
                        var stageIcons = {
                            'Раскрой': '✂️',
                            'Сварка': '🔥',
                            'Сборка': '🔧',
                            'Покраска': '🎨',
                            'Упаковка': '📦',
                            'Контроль качества': '✅',
                            'Готово': '✔️'
                        };
                        
                        task.stages.forEach(function(stage) {
                            var stageClass = stage.status === 'completed' ? 'completed' : (stage.status === 'in_progress' ? 'in_progress' : 'pending');
                            var icon = stageIcons[stage.stage_name] || '📍';
                            var statusName = stage.status === 'completed' ? 'Завершено' : (stage.status === 'in_progress' ? 'В работе' : 'Ожидает');
                            
                            html += '<div class="timeline-stage ' + stageClass + '">';
                            html += '<div class="timeline-stage-icon">' + icon + '</div>';
                            html += '<div class="timeline-stage-name">' + escapeHtml(stage.stage_name) + '</div>';
                            html += '<div class="timeline-stage-status">' + statusName + '</div>';
                            html += '</div>';
                        });
                        
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    html += '</div></div>';
                });
                
                // Добавляем сводку по материалам для всего заказа
                var allMaterials = [];
                var totalShortages = 0;
                data.tasks.forEach(function(t) {
                    if (t.materials) {
                        t.materials.forEach(function(m) {
                            allMaterials.push(m);
                            if (m.available_qty < m.required_qty) {
                                totalShortages++;
                            }
                        });
                    }
                });
                
                if (allMaterials.length > 0) {
                    html += '<div class="passport-section fade-in" style="margin-top: 20px;">';
                    html += '<div class="passport-section-title">📊 Общая потребность в материалах:</div>';
                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px;">';
                    html += '<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e9ecef;">';
                    html += '<div style="font-size: 12px; color: #7f8c8d;">Всего материалов:</div>';
                    html += '<div style="font-size: 20px; font-weight: 700; color: #2c3e50;">' + allMaterials.length + '</div>';
                    html += '</div>';
                    html += '<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e9ecef;">';
                    html += '<div style="font-size: 12px; color: #7f8c8d;">Хватает на складе:</div>';
                    html += '<div style="font-size: 20px; font-weight: 700; color: #27ae60;">' + (allMaterials.length - totalShortages) + '</div>';
                    html += '</div>';
                    html += '<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e9ecef;">';
                    html += '<div style="font-size: 12px; color: #7f8c8d;">Не хватает:</div>';
                    html += '<div style="font-size: 20px; font-weight: 700; color: #e74c3c;">' + totalShortages + '</div>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                }
                
                html += '</div>';
            }
            
            body.innerHTML = html;
        }
        
        function toggleTaskDetails(taskId, btn) {
            const details = document.getElementById('task-details-' + taskId);
            if (details) {
                const isHidden = details.style.display === 'none';
                details.style.display = isHidden ? 'block' : 'none';
                
                // Обновляем кнопку
                if (btn) {
                    btn.classList.toggle('active', isHidden);
                    var btnText = btn.querySelector('.btn-text');
                    if (btnText) {
                        btnText.textContent = isHidden ? 'Свернуть' : 'Подробнее';
                    }
                }
            }
        }
        
        function toggleItemMaterials(itemIndex, btn) {
            const details = document.getElementById('item-materials-' + itemIndex);
            if (details) {
                const isHidden = details.style.display === 'none';
                details.style.display = isHidden ? 'block' : 'none';
                
                // Обновляем кнопку
                if (btn) {
                    btn.classList.toggle('active', isHidden);
                    var btnText = btn.querySelector('.btn-text');
                    if (btnText) {
                        btnText.textContent = isHidden ? 'Свернуть' : 'Материалы';
                    }
                }
            }
        }
        
        function closeOrderDetailModal(event) {
            if (event.target === document.getElementById('orderDetailModalOverlay')) {
                closeOrderDetailModalDirect();
            }
        }
        
        function closeOrderDetailModalDirect() {
            document.getElementById('orderDetailModalOverlay').style.display = 'none';
        }
        
        function formatMoney(amount) {
            if (!amount) return '—';
            return Number(amount).toFixed(2).replace('.', ',') + ' BYN';
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '—';
            var date = new Date(dateStr);
            return date.toLocaleDateString('ru-RU');
        }
        
        function escapeHtml(text) {
            if (!text && text !== 0) return '';
            var div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }
        
        // Проверка данных при загрузке страницы
        window.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded. Orders data keys:', Object.keys(ordersData));
            console.log('Sample order data:', ordersData[Object.keys(ordersData)[0]]);
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('orderDetailModalOverlay');
                if (modal && modal.style.display === 'flex') {
                    closeOrderDetailModalDirect();
                }
            }
        });
    </script>
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
