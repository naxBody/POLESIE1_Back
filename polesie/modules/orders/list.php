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

                <!-- KPI метрики и дашборд -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
                    <!-- Карточка: Сегодня -->
                    <div class="card" style="border-left: 4px solid #3498db; margin: 0;">
                        <div class="card-body" style="padding: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Заказов сегодня</span>
                                <div style="width: 40px; height: 40px; background: rgba(52, 152, 219, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="#3498db" stroke-width="2"/>
                                        <path d="M16 2V6M8 2V6M3 10H21" stroke="#3498db" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </div>
                            <div style="font-size: 32px; font-weight: 700; color: var(--text-primary); line-height: 1.2;"><?= $todayOrdersCount ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                За неделю: <strong style="color: var(--text-primary);"><?= $weekOrdersCount ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Карточка: Активные заказы -->
                    <div class="card" style="border-left: 4px solid #f39c12; margin: 0;">
                        <div class="card-body" style="padding: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">В работе</span>
                                <div style="width: 40px; height: 40px; background: rgba(243, 156, 18, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="12" cy="12" r="3" stroke="#f39c12" stroke-width="2"/>
                                        <path d="M12 1V3M12 21V23M23 12H21M3 12H1" stroke="#f39c12" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </div>
                            <div style="font-size: 32px; font-weight: 700; color: var(--text-primary); line-height: 1.2;"><?= $inWorkCount ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                Новый: <strong style="color: #3498db;"><?= $newOrdersCount ?></strong> · Готов: <strong style="color: #27ae60;"><?= $readyCount ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Карточка: Просроченные -->
                    <div class="card" style="border-left: 4px solid #e74c3c; margin: 0;">
                        <div class="card-body" style="padding: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Требуют внимания</span>
                                <div style="width: 40px; height: 40px; background: rgba(231, 76, 60, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 9V11M12 15H12.01M5.07183 19H18.9282C19.5306 19 20.0223 18.5415 19.9369 17.9462L18.0001 4.41602C17.9086 3.77896 17.3621 3.30485 16.7188 3.30485H7.2812C6.63795 3.30485 6.09138 3.77896 6.00002 4.41602L4.06316 17.9462C3.97783 18.5415 4.46948 19 5.07183 19Z" stroke="#e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                            <div style="font-size: 32px; font-weight: 700; color: #e74c3c; line-height: 1.2;"><?= $overdueOrdersCount ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                В работе > 7 дней
                            </div>
                        </div>
                    </div>

                    <!-- Карточка: Готовы к отгрузке -->
                    <div class="card" style="border-left: 4px solid #27ae60; margin: 0;">
                        <div class="card-body" style="padding: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Готовы к отгрузке</span>
                                <div style="width: 40px; height: 40px; background: rgba(39, 174, 96, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 6L9 17L4 12" stroke="#27ae60" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                            <div style="font-size: 32px; font-weight: 700; color: var(--text-primary); line-height: 1.2;"><?= $readyForShipment ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                Отгружено: <strong style="color: #9b59b6;"><?= $shippedCount ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Прогресс бар распределения по статусам -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body" style="padding: 20px;">
                        <h3 style="font-size: 16px; font-weight: 600; margin: 0 0 16px 0; color: var(--text-primary);">Распределение заказов по статусам</h3>
                        <div style="display: flex; gap: 4px; height: 12px; border-radius: 6px; overflow: hidden; background: #ecf0f1; margin-bottom: 16px;">
                            <?php if ($totalAllOrders > 0): ?>
                                <div style="flex: <?= $statusPercentages['new'] ?? 0 ?>%; background: #3498db;" title="Новые: <?= $statusCounts['new'] ?? 0 ?>"></div>
                                <div style="flex: <?= $statusPercentages['processing'] ?? 0 ?>%; background: #f39c12;" title="В работе: <?= $statusCounts['processing'] ?? 0 ?>"></div>
                                <div style="flex: <?= $statusPercentages['ready'] ?? 0 ?>%; background: #27ae60;" title="Готовы: <?= $statusCounts['ready'] ?? 0 ?>"></div>
                                <div style="flex: <?= $statusPercentages['shipped'] ?? 0 ?>%; background: #9b59b6;" title="Отгружены: <?= $statusCounts['shipped'] ?? 0 ?>"></div>
                                <div style="flex: <?= $statusPercentages['cancelled'] ?? 0 ?>%; background: #e74c3c;" title="Отменены: <?= $statusCounts['cancelled'] ?? 0 ?>"></div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 12px; height: 12px; border-radius: 3px; background: #3498db;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);">Новые: <strong><?= $statusCounts['new'] ?? 0 ?></strong></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 12px; height: 12px; border-radius: 3px; background: #f39c12;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);">В работе: <strong><?= $statusCounts['processing'] ?? 0 ?></strong></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 12px; height: 12px; border-radius: 3px; background: #27ae60;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);">Готовы: <strong><?= $statusCounts['ready'] ?? 0 ?></strong></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 12px; height: 12px; border-radius: 3px; background: #9b59b6;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);">Отгружены: <strong><?= $statusCounts['shipped'] ?? 0 ?></strong></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 12px; height: 12px; border-radius: 3px; background: #e74c3c;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);">Отменены: <strong><?= $statusCounts['cancelled'] ?? 0 ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>
                
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
