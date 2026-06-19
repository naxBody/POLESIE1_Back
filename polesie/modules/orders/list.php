<?php
/**
 * Список заказов
 * Модуль управления заказами
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

// Получение фильтров
$search = $_GET['search'] ?? '';
$statusId = $_GET['status'] ?? '';
$contractorId = $_GET['contractor'] ?? '';
$responsibleId = $_GET['responsible'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$createdDateFrom = $_GET['created_from'] ?? '';
$createdDateTo = $_GET['created_to'] ?? '';
$quickFilter = $_GET['quick_filter'] ?? '';

// Обработка быстрых фильтров
$today = date('Y-m-d');
if ($quickFilter === 'today') {
    $dateFrom = $today;
    $dateTo = $today;
} elseif ($quickFilter === 'week') {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = $today;
} elseif ($quickFilter === 'month') {
    $dateFrom = date('Y-m-01');
    $dateTo = $today;
}

// Построение запроса
$sql = "
    SELECT o.*, c.name as contractor_name, 
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
           u.full_name as responsible_name,
           COUNT(oi.id) as items_count
    FROM orders o
    JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE 1=1
";

$params = [];

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusId) {
    $sql .= " AND o.status = ?";
    $params[] = $statusId;
}

if ($contractorId) {
    $sql .= " AND o.customer_id = ?";
    $params[] = $contractorId;
}

if ($responsibleId) {
    $sql .= " AND o.responsible_user_id = ?";
    $params[] = $responsibleId;
}

if ($dateFrom) {
    $sql .= " AND o.order_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND o.order_date <= ?";
    $params[] = $dateTo;
}

if ($createdDateFrom) {
    $sql .= " AND o.created_at >= ?";
    $params[] = $createdDateFrom . ' 00:00:00';
}

if ($createdDateTo) {
    $sql .= " AND o.created_at <= ?";
    $params[] = $createdDateTo . ' 23:59:59';
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Пагинация
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Общее количество
$countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o 
             JOIN contractors c ON o.customer_id = c.id 
             WHERE 1=1" . 
             ($search ? " AND (o.order_number LIKE ? OR c.name LIKE ?)" : "") .
             ($statusId ? " AND o.status = ?" : "") .
             ($contractorId ? " AND o.customer_id = ?" : "") .
             ($responsibleId ? " AND o.responsible_user_id = ?" : "") .
             ($dateFrom ? " AND o.order_date >= ?" : "") .
             ($dateTo ? " AND o.order_date <= ?" : "") .
             ($createdDateFrom ? " AND o.created_at >= ?" : "") .
             ($createdDateTo ? " AND o.created_at <= ?" : "");

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Получение данных
$sql .= " LIMIT $offset, $perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Статусы заказов с современными иконками SVG
$statuses = [
    ['status' => 'new', 'name' => 'Новый', 'color' => '#3498db', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'],
    ['status' => 'processing', 'name' => 'В работе', 'color' => '#f39c12', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/><path d="M12 1V3M12 21V23M23 12H21M3 12H1M20.66 3.34L19.07 4.93M4.93 19.07L3.34 20.66M20.66 20.66L19.07 19.07M4.93 4.93L3.34 3.34" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'],
    ['status' => 'ready', 'name' => 'Готов', 'color' => '#27ae60', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
    ['status' => 'shipped', 'name' => 'Отгружен', 'color' => '#9b59b6', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 21V15C16 13.8954 15.1046 13 14 13H10C8.89543 13 8 13.8954 8 15V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'],
    ['status' => 'cancelled', 'name' => 'Отменен', 'color' => '#e74c3c', 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>']
];

// Подсчет заказов по статусам
$statusCounts = [];
foreach ($statuses as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $stmt->execute([$status['status']]);
    $statusCounts[$status['status']] = $stmt->fetchColumn();
}

// Получение контрагентов для фильтра
$contractors = $pdo->query("SELECT id, name FROM contractors WHERE type IN ('customer', 'both') ORDER BY name LIMIT 100")->fetchAll();

// Получение ответственных пользователей для фильтра
$responsibleUsers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// Статистика
$newOrdersCount = $statusCounts['new'] ?? 0;
$inWorkCount = $statusCounts['processing'] ?? 0;
$readyCount = $statusCounts['ready'] ?? 0;
$shippedCount = $statusCounts['shipped'] ?? 0;
$cancelledCount = $statusCounts['cancelled'] ?? 0;
$totalActive = $newOrdersCount + $inWorkCount;

// Дополнительная статистика для KPI
// Заказы сегодня
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$todayOrdersCount = $stmt->fetchColumn();

// Заказы за неделю
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$weekOrdersCount = $stmt->fetchColumn();

// Просроченные заказы (в работе больше 7 дней)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status IN ('new', 'processing') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$overdueOrdersCount = $stmt->fetchColumn();

// Готовые к отгрузке (статус ready)
$readyForShipment = $readyCount;

// Сумма активных заказов (примерная - по последним данным)
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE status IN ('new', 'processing', 'ready')");
$stmt->execute();
$activeOrdersAmount = $stmt->fetchColumn() ?? 0;

// Распределение по статусам для прогресс-баров
$totalAllOrders = array_sum($statusCounts);
$statusPercentages = [];
foreach ($statusCounts as $status => $count) {
    $statusPercentages[$status] = $totalAllOrders > 0 ? round(($count / $totalAllOrders) * 100) : 0;
}

// === ЗАКАЗЫ ТРЕБУЮЩИЕ ВНИМАНИЯ (с проблемами) ===
// Получаем заказы с проблемами материалов
$problemOrdersSql = "
    SELECT DISTINCT 
        o.id,
        o.order_number,
        c.name as contractor_name,
        pt.id as task_id,
        pt.task_number,
        pt.status as task_status,
        GROUP_CONCAT(
            CONCAT(
                m.name_full, 
                '|',
                (ptm.quantity_required - COALESCE(ptm.quantity_reserved, 0) - COALESCE(ptm.quantity_used, 0)),
                '|',
                m.unit
            ) 
            SEPARATOR '; '
        ) as material_issues,
        COUNT(DISTINCT ptm.id) as material_issues_count
    FROM orders o
    JOIN contractors c ON o.customer_id = c.id
    JOIN production_tasks pt ON pt.order_id = o.id
    JOIN production_tasks_materials ptm ON ptm.task_id = pt.id
    JOIN materials m ON ptm.material_id = m.id
    WHERE o.status IN ('new', 'processing')
      AND pt.status IN ('planned', 'in_progress')
      AND ptm.status IN ('pending', 'reserved')
      AND (ptm.quantity_required > COALESCE(ptm.quantity_reserved, 0) + COALESCE(ptm.quantity_used, 0))
    GROUP BY o.id, pt.id
    ORDER BY pt.priority DESC, o.created_at DESC
    LIMIT 10
";
$problemOrders = $pdo->query($problemOrdersSql)->fetchAll();

// Подсчет общего количества проблемных заказов
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id) 
    FROM orders o
    JOIN production_tasks pt ON pt.order_id = o.id
    JOIN production_tasks_materials ptm ON ptm.task_id = pt.id
    WHERE o.status IN ('new', 'processing')
      AND pt.status IN ('planned', 'in_progress')
      AND ptm.status IN ('pending', 'reserved')
      AND (ptm.quantity_required > COALESCE(ptm.quantity_reserved, 0) + COALESCE(ptm.quantity_used, 0))
");
$stmt->execute();
$problemOrdersTotal = $stmt->fetchColumn();

$pageTitle = 'Заказы';
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
/* Buttons */
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--gray-100);
    border: 1px solid var(--border-color);
    cursor: pointer;
    text-decoration: none;
    color: var(--text-secondary);
    transition: all var(--transition-fast);
}

.btn-icon:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: scale(1.1);
}

.btn-icon.btn-danger:hover {
    background: var(--danger-color);
    border-color: var(--danger-color);
}

/* Table action buttons - larger icons */
.table-actions .btn-icon {
    width: 40px;
    height: 40px;
}

.table-actions .btn-icon svg {
    width: 20px;
    height: 20px;
}
</style>
</head>
<body>
    <div class="app-container">
        <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php require_once BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <!-- Заголовок страницы с основной кнопкой -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h1 style="font-size: 28px; font-weight: 700; margin: 0 0 8px 0; color: var(--text-primary);">Управление заказами</h1>
                        <p style="color: var(--text-secondary); font-size: 14px; margin: 0;">Контролируйте статусы, отслеживайте выполнение и управляйте отгрузками</p>
                    </div>
                    <a href="<?= pageUrl('modules/orders/create.php') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; font-weight: 500;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Создать заказ
                    </a>
                </div>

                <!-- Секция: Заказы требующие внимания (с проблемами) -->
                <?php if (!empty($problemOrders)): ?>
                <div class="card" style="margin-bottom: 24px; border-left: 3px solid #f39c12;">
                    <div class="card-body" style="padding: 15px 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                            <h3 style="font-size: 16px; font-weight: 600; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2">
                                    <path d="M12 9V11M12 15H12.01M5.07183 19H18.9282C19.5306 19 20.0223 18.5415 19.9369 17.9462L18.0001 4.41602C17.9086 3.77896 17.3621 3.30485 16.7188 3.30485H7.2812C6.63795 3.30485 6.09138 3.77896 6.00002 4.41602L4.06316 17.9462C3.97783 18.5415 4.46948 19 5.07183 19Z"/>
                                </svg>
                                Требуют внимания: <?= count($problemOrders) ?> из <?= $problemOrdersTotal ?> заказов с проблемами материалов
                            </h3>
                        </div>
                        
                        <div style="display: grid; gap: 10px;">
                            <?php foreach ($problemOrders as $order): ?>
                            <div style="display: flex; align-items: flex-start; gap: 15px; padding: 12px; background: #fff; border: 1px solid #e0e0e0; border-radius: 6px;">
                                <!-- Информация о заказе -->
                                <div style="min-width: 200px; flex-shrink: 0;">
                                    <div style="margin-bottom: 4px;">
                                        <a href="view.php?id=<?= $order['id'] ?>" style="font-size: 14px; font-weight: 600; color: var(--primary-color); text-decoration: none;">
                                            <?= e($order['order_number']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-secondary);"><?= e($order['contractor_name']) ?></div>
                                </div>
                                
                                <!-- Проблемы с материалами -->
                                <div style="flex: 1;">
                                    <?php 
                                    $issues = explode('; ', $order['material_issues']);
                                    foreach ($issues as $issue): 
                                        if (trim($issue)):
                                            $parts = explode('|', $issue);
                                            $materialName = $parts[0] ?? '';
                                            $missingQty = isset($parts[1]) ? floatval($parts[1]) : 0;
                                            $unit = $parts[2] ?? 'шт';
                                            
                                            // Форматируем число: без десятичных, если целое
                                            $formattedQty = ($missingQty == floor($missingQty)) 
                                                ? number_format($missingQty, 0, '.', ' ') 
                                                : number_format($missingQty, 2, '.', ' ');
                                    ?>
                                    <div style="font-size: 13px; color: var(--text-primary); margin-bottom: 3px;">
                                        <span style="display: inline-block; width: 6px; height: 6px; background: #f39c12; border-radius: 50%; margin-right: 6px;"></span>
                                        <?= e($materialName) ?> — не хватает <strong><?= $formattedQty ?> <?= e($unit) ?></strong>
                                    </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                                
                                <!-- Кнопка действия -->
                                <div style="flex-shrink: 0;">
                                    <a href="<?= pageUrl('modules/warehouse/materials.php') ?>?order=<?= $order['id'] ?>&task=<?= $order['task_id'] ?>" 
                                       class="btn btn-sm btn-secondary"
                                       style="font-size: 12px; padding: 6px 12px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; vertical-align: middle;">
                                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                        </svg>
                                        Решить
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Фильтры -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <!-- Быстрые фильтры -->
                        <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
                            <span style="font-size: 13px; color: var(--text-secondary); align-self: center;">Быстрые фильтры:</span>
                            <a href="?quick_filter=today" class="btn btn-sm <?= $quickFilter === 'today' ? 'btn-primary' : 'btn-secondary' ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: middle;">
                                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M16 2V6M8 2V6M3 10H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                Сегодня
                            </a>
                            <a href="?quick_filter=week" class="btn btn-sm <?= $quickFilter === 'week' ? 'btn-primary' : 'btn-secondary' ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: middle;">
                                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M16 2V6M8 2V6M3 10H21M9 16L11 18L15 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Эта неделя
                            </a>
                            <a href="?quick_filter=month" class="btn btn-sm <?= $quickFilter === 'month' ? 'btn-primary' : 'btn-secondary' ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: middle;">
                                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M16 2V6M8 2V6M3 10H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <circle cx="12" cy="16" r="2" fill="currentColor"/>
                                </svg>
                                Этот месяц
                            </a>
                            <?php if ($statusId): ?>
                            <a href="?status=<?= e($statusId) ?>" class="btn btn-sm btn-secondary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: middle;">
                                    <path d="M4 4V20H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M20 4L10 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Только этот статус
                            </a>
                            <?php endif; ?>
                            <?php if ($responsibleId): ?>
                            <a href="?responsible=<?= e($responsibleId) ?>" class="btn btn-sm btn-secondary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: middle;">
                                    <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
                                    <path d="M6 20C6 17.5 9 15 12 15C15 15 18 17.5 18 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                Мой фильтр
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Поиск</label>
                                <input type="text" name="search" class="form-control" placeholder="№ заказа или заказчик" value="<?= e($search) ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Статус</label>
                                <select name="status" class="form-control">
                                    <option value="">Все статусы</option>
                                    <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s['status'] ?>" <?= $statusId == $s['status'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Заказчик</label>
                                <select name="contractor" class="form-control">
                                    <option value="">Все заказчики</option>
                                    <?php foreach ($contractors as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $contractorId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Ответственный</label>
                                <select name="responsible" class="form-control">
                                    <option value="">Все сотрудники</option>
                                    <?php foreach ($responsibleUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $responsibleId == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Дата заказа с</label>
                                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Дата заказа по</label>
                                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Создан с</label>
                                <input type="date" name="created_from" class="form-control" value="<?= e($createdDateFrom) ?>">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Создан по</label>
                                <input type="date" name="created_to" class="form-control" value="<?= e($createdDateTo) ?>">
                            </div>
                            
                            <div style="display: flex; gap: 8px;">
                                <button type="submit" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: middle;">
                                        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                                        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    Применить
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: middle;">
                                        <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    Сброс
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Заголовок и действия -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="font-size: 24px; font-weight: 600;">Заказы (<?= $totalRecords ?>)</h2>
                    <div style="display: flex; gap: 12px;">
                        <a href="create.php" class="btn btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                                <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Новый заказ
                        </a>
                    </div>
                </div>
                
                <!-- Таблица заказов -->
                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="table" id="ordersTable">
                                <thead>
                                    <tr>
                                        <th>Номер</th>
                                        <th>Дата</th>
                                        <th>Заказчик</th>
                                        <th>Позиций</th>
                                        <th>Сумма</th>
                                        <th>Статус</th>
                                        <th>Ответственный</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                            Заказы не найдены
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong><a href="view.php?id=<?= $order['id'] ?>" style="color: var(--primary-color);"><?= e($order['order_number']) ?></a></strong>
                                            </td>
                                            <td><?= formatDate($order['order_date']) ?></td>
                                            <td><?= e($order['contractor_name']) ?></td>
                                            <td><?= $order['items_count'] ?> шт.</td>
                                            <td><?= formatMoney($order['total_amount']) ?></td>
                                            <td>
                                                <span class="badge" style="background: <?= e($order['status_color']) ?>20; color: <?= e($order['status_color']) ?>">
                                                    <?= e($order['status_name']) ?>
                                                </span>
                                            </td>
                                            <td><?= e($order['responsible_name'] ?? '—') ?></td>
                                            <td class="table-actions">
                                                <div style="display: flex; gap: 8px;">
                                                    <?php if (hasPermission('orders.view')): ?>
                                                        <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-icon" title="Просмотр">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (hasPermission('orders.edit')): ?>
                                                        <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-icon" title="Редактировать">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (hasPermission('orders.delete')): ?>
                                                        <a href="delete.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-icon btn-danger" title="Удалить" onclick="return confirm('Вы уверены, что хотите удалить заказ <?= e($order['order_number']) ?>?')">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Пагинация -->
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 13px; color: var(--text-secondary);">
                                Показано <?= $offset + 1 ?> - <?= min($offset + $perPage, $totalRecords) ?> из <?= $totalRecords ?>
                            </span>
                            <div style="display: flex; gap: 4px;">
                                <?php 
                                $filterParams = [];
                                if ($search) $filterParams[] = 'search=' . urlencode($search);
                                if ($statusId) $filterParams[] = 'status=' . urlencode($statusId);
                                if ($contractorId) $filterParams[] = 'contractor=' . urlencode($contractorId);
                                if ($responsibleId) $filterParams[] = 'responsible=' . urlencode($responsibleId);
                                if ($dateFrom) $filterParams[] = 'date_from=' . urlencode($dateFrom);
                                if ($dateTo) $filterParams[] = 'date_to=' . urlencode($dateTo);
                                if ($createdDateFrom) $filterParams[] = 'created_from=' . urlencode($createdDateFrom);
                                if ($createdDateTo) $filterParams[] = 'created_to=' . urlencode($createdDateTo);
                                if ($quickFilter) $filterParams[] = 'quick_filter=' . urlencode($quickFilter);
                                $filterString = !empty($filterParams) ? '&' . implode('&', $filterParams) : '';
                                ?>
                                
                                <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $filterString ?>" class="btn btn-sm btn-secondary">← Назад</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?><?= $filterString ?>" 
                                   class="btn btn-sm <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?>">
                                    <?= $i ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $filterString ?>" class="btn btn-sm btn-secondary">Вперед →</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
