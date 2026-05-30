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

// Получение статусов для фильтра и статистики
$statuses = [
    ['status' => 'new', 'name' => 'Новый', 'color' => '#3498db', 'icon' => '🆕'],
    ['status' => 'processing', 'name' => 'В работе', 'color' => '#f39c12', 'icon' => '⚙️'],
    ['status' => 'ready', 'name' => 'Готов', 'color' => '#27ae60', 'icon' => '✅'],
    ['status' => 'shipped', 'name' => 'Отгружен', 'color' => '#9b59b6', 'icon' => '📦'],
    ['status' => 'cancelled', 'name' => 'Отменен', 'color' => '#e74c3c', 'icon' => '❌']
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
$totalActive = $newOrdersCount + $inWorkCount;

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
</head>
<body>
    <div class="app-container">
        <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php require_once BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <!-- Статистика по заказам -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <a href="?status=new" class="stat-card-link" style="text-decoration: none;">
                        <div class="stat-card" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 24px; border-radius: 12px; text-align: center; cursor: pointer;">
                            <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;"><?= $newOrdersCount ?></div>
                            <div style="font-size: 14px; opacity: 0.9;">🆕 Новые заказы</div>
                        </div>
                    </a>
                    <a href="?status=processing" class="stat-card-link" style="text-decoration: none;">
                        <div class="stat-card" style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white; padding: 24px; border-radius: 12px; text-align: center; cursor: pointer;">
                            <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;"><?= $inWorkCount ?></div>
                            <div style="font-size: 14px; opacity: 0.9;">⚙️ В работе</div>
                        </div>
                    </a>
                    <a href="?status=ready" class="stat-card-link" style="text-decoration: none;">
                        <div class="stat-card" style="background: linear-gradient(135deg, #27ae60, #229954); color: white; padding: 24px; border-radius: 12px; text-align: center; cursor: pointer;">
                            <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;"><?= $readyCount ?></div>
                            <div style="font-size: 14px; opacity: 0.9;">✅ Готовы к отгрузке</div>
                        </div>
                    </a>
                    <a href="?" class="stat-card-link" style="text-decoration: none;">
                        <div class="stat-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 24px; border-radius: 12px; text-align: center; cursor: pointer;">
                            <div style="font-size: 36px; font-weight: 700; margin-bottom: 8px;"><?= $totalRecords ?></div>
                            <div style="font-size: 14px; opacity: 0.9;">📋 Всего заказов</div>
                        </div>
                    </a>
                </div>
                
                <!-- Фильтры -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body">
                        <!-- Быстрые фильтры -->
                        <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
                            <span style="font-size: 13px; color: var(--text-secondary); align-self: center;">Быстрые фильтры:</span>
                            <a href="?quick_filter=today" class="btn btn-sm <?= $quickFilter === 'today' ? 'btn-primary' : 'btn-secondary' ?>">📅 Сегодня</a>
                            <a href="?quick_filter=week" class="btn btn-sm <?= $quickFilter === 'week' ? 'btn-primary' : 'btn-secondary' ?>">📆 Эта неделя</a>
                            <a href="?quick_filter=month" class="btn btn-sm <?= $quickFilter === 'month' ? 'btn-primary' : 'btn-secondary' ?>">📅 Этот месяц</a>
                            <?php if ($statusId): ?>
                            <a href="?status=<?= e($statusId) ?>" class="btn btn-sm btn-secondary">🔄 Только этот статус</a>
                            <?php endif; ?>
                            <?php if ($responsibleId): ?>
                            <a href="?responsible=<?= e($responsibleId) ?>" class="btn btn-sm btn-secondary">👤 Мой фильтр</a>
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
                                <button type="submit" class="btn btn-primary">🔍 Применить</button>
                                <a href="?" class="btn btn-secondary">✖ Сброс</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Заголовок и действия -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="font-size: 24px; font-weight: 600;">Заказы (<?= $totalRecords ?>)</h2>
                    <div style="display: flex; gap: 12px;">
                        <button class="btn btn-secondary" onclick="exportTableToCSV('ordersTable', 'orders.csv')">
                            📥 Экспорт CSV
                        </button>
                        <a href="create.php" class="btn btn-primary">➕ Новый заказ</a>
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
                                                <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary" title="Просмотр">👁️ Подробнее</a>
                                                <a href="print.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary" title="Печать" target="_blank">🖨️</a>
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
