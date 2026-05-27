<?php
/**
 * План выпуска - все заказы и производственные задания
 * Отображение статуса производства по каждому заказу
 * Компактная таблица с детальной информацией по клику
 */

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

// Построение WHERE clause
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

// Фильтрация заказов с проблемами материалов и просроченных
$orders = [];
foreach ($allOrders as $order) {
    // Если включен фильтр проблем с материалами
    if ($materialIssueFilter) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM production_tasks pt
            JOIN production_tasks_materials ptm ON ptm.task_id = pt.id
            WHERE pt.order_id = ? AND ptm.quantity_required > ptm.quantity_available
        ");
        $stmt->execute([$order['id']]);
        $materialCount = $stmt->fetchColumn();
        if ($materialCount == 0) continue;
        $order['material_shortages'] = $materialCount;
    }
    
    // Если включен фильтр просроченных
    if ($overdueFilter && $order['days_until_delivery'] >= 0) {
        continue;
    }
    
    $orders[] = $order;
}

// Если нет специальных фильтров, берем все заказы
if (!$materialIssueFilter && !$overdueFilter) {
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

// API endpoint для получения детальной информации о заказе
if (isset($_GET['api_order_detail'])) {
    header('Content-Type: application/json');
    $orderId = (int)$_GET['order_id'];
    
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
    
    // Этапы выполнения для каждого задания
    foreach ($tasks as &$task) {
        $stmt = $pdo->prepare("
            SELECT pts.*, ps.name as stage_name, ps.color as stage_color, ps.sort_order,
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
    
    echo json_encode(['items' => $items, 'tasks' => $tasks], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подсчет статистики по задачам
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM production_tasks pt
    JOIN orders o ON pt.order_id = o.id
    WHERE o.id IN (" . implode(',', array_fill(0, count($orders), '?')) . ")
");
$stmt->execute(array_map(fn($o) => $o['id'], $orders));
$tasksStats = $stmt->fetch();
$stats['total_tasks'] = $tasksStats['total'] ?? 0;
$stats['tasks_in_progress'] = $tasksStats['in_progress'] ?? 0;
$stats['tasks_completed'] = $tasksStats['completed'] ?? 0;
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
                                <tr style="cursor: pointer;" onclick="openOrderDetailModal(<?= $order['id'] ?>)" 
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
                                        <button class="btn btn-sm btn-primary" onclick="openOrderDetailModal(<?= $order['id'] ?>)">👁️</button>
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
                    <div class="passport-modal" style="max-width: 1100px;">
                        <div class="passport-modal-header">
                            <div>
                                <div class="passport-modal-title" id="modalOrderNumber">Заказ №—</div>
                                <div class="passport-modal-subtitle" id="modalOrderStatus">Статус: —</div>
                            </div>
                            <button class="passport-modal-close" onclick="closeOrderDetailModalDirect()">×</button>
                        </div>
                        <div class="passport-modal-body" id="modalOrderBody" style="max-height: 70vh; overflow-y: auto;">
                            <div style="text-align: center; padding: 40px;">
                                <div style="font-size: 32px; margin-bottom: 16px;">⏳</div>
                                <p>Загрузка информации...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Открытие модального окна с деталями заказа
        function openOrderDetailModal(orderId) {
            const modal = document.getElementById('orderDetailModalOverlay');
            const body = document.getElementById('modalOrderBody');
            
            modal.style.display = 'flex';
            body.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="font-size: 32px; margin-bottom: 16px;">⏳</div><p>Загрузка информации...</p></div>';
            
            fetch('?api_order_detail=1&order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    renderOrderDetailModal(data, orderId);
                })
                .catch(error => {
                    console.error('Error:', error);
                    body.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Ошибка загрузки данных</div>';
                });
        }
        
        function renderOrderDetailModal(data, orderId) {
            const body = document.getElementById('modalOrderBody');
            let html = '';
            
            // Основная информация о заказе
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📋 Информация о заказе</div>';
            html += '<div class="specs-list">';
            html += '<div class="spec-item"><div class="spec-name">Позиций</div><div class="spec-value">' + (data.items ? data.items.length : 0) + ' шт.</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Производственных заданий</div><div class="spec-value">' + (data.tasks ? data.tasks.length : 0) + '</div></div>';
            html += '</div>';
            html += '</div>';
            
            // Позиции заказа
            if (data.items && data.items.length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📦 Позиции заказа</div>';
                html += '<table class="materials-table">';
                html += '<thead><tr><th>Продукция</th><th>Артикул</th><th>Кол-во</th><th>Цена</th><th>Сумма</th><th>Статус</th></tr></thead>';
                html += '<tbody>';
                data.items.forEach(function(item) {
                    html += '<tr>';
                    html += '<td><strong>' + escapeHtml(item.product_name) + '</strong></td>';
                    html += '<td><code>' + escapeHtml(item.product_article) + '</code></td>';
                    html += '<td>' + item.quantity + '</td>';
                    html += '<td>' + formatMoney(item.price) + '</td>';
                    html += '<td>' + formatMoney(item.total) + '</td>';
                    html += '<td><span class="badge" style="background: ' + item.production_status_color + '20; color: ' + item.production_status_color + '">' + escapeHtml(item.production_status_name) + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '</div>';
            }
            
            // Производственные задания
            if (data.tasks && data.tasks.length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">🏭 Производственные задания</div>';
                data.tasks.forEach(function(task, index) {
                    html += '<div class="task-card" style="background: #fafbfc; border: 1px solid #e9ecef; border-radius: 6px; padding: 16px; margin-bottom: 12px;">';
                    html += '<div class="task-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">';
                    html += '<div>';
                    html += '<span class="task-title" style="font-weight: 600; color: #2c3e50;">' + escapeHtml(task.task_number) + '</span>';
                    html += '<span style="margin-left: 12px; color: #7f8c8d;">' + escapeHtml(task.product_name) + '</span>';
                    html += '</div>';
                    html += '<div style="display: flex; gap: 8px; align-items: center;">';
                    html += '<span class="badge" style="background: ' + task.status_color + '20; color: ' + task.status_color + '">' + escapeHtml(task.status_name) + '</span>';
                    html += '<button class="expand-btn" onclick="toggleTaskDetails(' + task.id + ')" style="background: none; border: none; cursor: pointer; font-size: 18px; color: #7f8c8d;">▼</button>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div style="display: flex; justify-content: space-between; font-size: 13px; color: #7f8c8d;">';
                    html += '<span>План: ' + task.quantity_plan + ' шт.</span>';
                    html += '<span>Факт: ' + task.quantity_fact + ' шт.</span>';
                    if (task.planned_start) {
                        html += '<span>' + formatDate(task.planned_start) + ' - ' + formatDate(task.planned_end) + '</span>';
                    }
                    html += '</div>';
                    
                    html += '<div class="progress-bar" style="height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; margin: 10px 0;">';
                    html += '<div class="progress-fill" style="height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); width: ' + task.progress_percent + '%;"></div>';
                    html += '</div>';
                    html += '<div style="font-size: 12px; color: #7f8c8d; text-align: right;">Выполнено: ' + task.progress_percent + '%</div>';
                    
                    // Этапы
                    if (task.stages && task.stages.length > 0) {
                        html += '<div class="task-details" id="task-details-' + task.id + '" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e9ecef;">';
                        html += '<h5 style="margin-bottom: 8px; font-size: 13px;">Этапы производства:</h5>';
                        html += '<div class="stages-list" style="display: flex; flex-wrap: wrap; gap: 8px;">';
                        task.stages.forEach(function(stage) {
                            var badgeClass = stage.status === 'completed' ? 'completed' : (stage.status === 'in_progress' ? 'in_progress' : 'pending');
                            var borderStyle = stage.status !== 'pending' ? 'border-color: ' + stage.stage_color + '; border: 1px solid;' : '';
                            html += '<span class="stage-badge ' + badgeClass + '" style="padding: 4px 10px; border-radius: 12px; font-size: 12px; background: ' + (stage.status === 'completed' ? '#d4edda' : (stage.status === 'in_progress' ? '#fff3cd' : '#e9ecef')) + '; color: ' + (stage.status === 'completed' ? '#155724' : (stage.status === 'in_progress' ? '#856404' : '#495057')) + '; ' + borderStyle + '">' + escapeHtml(stage.stage_name) + ': ' + escapeHtml(stage.status_name) + '</span>';
                        });
                        html += '</div></div>';
                    }
                    
                    html += '</div>';
                });
                html += '</div>';
            }
            
            body.innerHTML = html;
        }
        
        function toggleTaskDetails(taskId) {
            const details = document.getElementById('task-details-' + taskId);
            if (details) {
                details.style.display = details.style.display === 'none' ? 'block' : 'none';
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
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
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
