<?php
/**
 * Просмотр заказа
 * Модуль управления заказами
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

// Получение ID заказа
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    die('Заказ не найден');
}

// Получение информации о заказе
$sql = "
    SELECT o.*, c.name as contractor_name, c.inn, c.address, c.phone, c.email,
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
           u.full_name as responsible_name
    FROM orders o
    JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    WHERE o.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die('Заказ не найден');
}

// Получение позиций заказа
$itemsSql = "
    SELECT oi.*, p.id as product_id, p.name as product_name, p.article,
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

// Получение производственных заданий по заказу
$tasksSql = "
    SELECT pt.*, 
           CASE pt.status
               WHEN 'pending' THEN 'Ожидает'
               WHEN 'in_progress' THEN 'В работе'
               WHEN 'completed' THEN 'Завершено'
               ELSE pt.status
           END as task_status_name,
           CASE pt.status
               WHEN 'pending' THEN '#95a5a6'
               WHEN 'in_progress' THEN '#f39c12'
               WHEN 'completed' THEN '#27ae60'
               ELSE '#95a5a6'
           END as task_status_color
    FROM production_tasks pt
    WHERE pt.order_item_id IN (
        SELECT id FROM order_items WHERE order_id = ?
    )
    ORDER BY pt.created_at DESC
";
$stmt = $pdo->prepare($tasksSql);
$stmt->execute([$orderId]);
$tasks = $stmt->fetchAll();

// Статистика по заказу
$totalItems = count($items);
$totalQuantity = array_sum(array_column($items, 'quantity'));
$totalAmount = $order['total_amount'];

$pageTitle = 'Заказ №' . e($order['order_number']);
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
        .order-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .order-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .order-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .meta-item {
            background: rgba(255,255,255,0.15);
            padding: 12px 16px;
            border-radius: 8px;
            min-width: 150px;
        }
        .meta-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        .meta-value {
            font-size: 16px;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        .info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .info-card-header {
            background: #f8f9fa;
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            font-size: 16px;
        }
        .info-card-body {
            padding: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: var(--text-secondary);
        }
        .info-value {
            font-weight: 500;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .task-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 16px;
            overflow: hidden;
        }
        .task-header {
            padding: 16px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }
        .task-body {
            padding: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php require_once BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <!-- Заголовок заказа -->
                <div class="order-header">
                    <div class="order-number">Заказ №<?= e($order['order_number']) ?></div>
                    <div style="font-size: 16px; opacity: 0.9;">от <?= formatDate($order['order_date']) ?></div>
                    
                    <div class="order-meta">
                        <div class="meta-item">
                            <div class="meta-label">Статус</div>
                            <div class="meta-value">
                                <span class="badge-status" style="background: rgba(255,255,255,0.25);">
                                    <?= e($order['status_name']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Сумма</div>
                            <div class="meta-value"><?= formatMoney($order['total_amount']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Ответственный</div>
                            <div class="meta-value"><?= e($order['responsible_name'] ?? '—') ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Позиций</div>
                            <div class="meta-value"><?= $totalItems ?> шт.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Статистика -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $totalItems ?></div>
                        <div class="stat-label">Позиций в заказе</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($totalQuantity, 0, ',', ' ') ?></div>
                        <div class="stat-label">Общее количество</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($tasks) ?></div>
                        <div class="stat-label">Производственных заданий</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= formatMoney($totalAmount) ?></div>
                        <div class="stat-label">Общая сумма</div>
                    </div>
                </div>
                
                <!-- Информация о заказчике и заказе -->
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-card-header">📋 Информация о заказе</div>
                        <div class="info-card-body">
                            <div class="info-row">
                                <span class="info-label">Дата заказа</span>
                                <span class="info-value"><?= formatDate($order['order_date']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Дата доставки</span>
                                <span class="info-value"><?= formatDate($order['delivery_date']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Статус</span>
                                <span class="info-value"><?= e($order['status_name']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Комментарий</span>
                                <span class="info-value"><?= e($order['comments'] ?? '—') ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-card-header">🏢 Заказчик</div>
                        <div class="info-card-body">
                            <div class="info-row">
                                <span class="info-label">Наименование</span>
                                <span class="info-value"><?= e($order['contractor_name']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">ИНН</span>
                                <span class="info-value"><?= e($order['inn'] ?? '—') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Адрес</span>
                                <span class="info-value"><?= e($order['address'] ?? '—') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Телефон</span>
                                <span class="info-value"><?= e($order['phone'] ?? '—') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= e($order['email'] ?? '—') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Товары заказа -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body" style="padding: 0;">
                        <h3 style="padding: 20px 20px 0; margin: 0;" class="section-title">
                            📦 Товары заказа
                        </h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>№</th>
                                        <th>Артикул</th>
                                        <th>Наименование</th>
                                        <th>Ед. изм.</th>
                                        <th style="text-align: right;">Кол-во</th>
                                        <th style="text-align: right;">Цена</th>
                                        <th style="text-align: right;">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                            Товары не найдены
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $index => $item): 
                                            $hasPassport = !empty($item['product_id']);
                                        ?>
                                        <tr style="<?= $hasPassport ? 'cursor: pointer;' : '' ?>" 
                                            onclick="<?= $hasPassport ? "window.location.href='" . pageUrl('modules/products/passports.php?product=' . $item['product_id']) . "'" : '' ?>">
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <?php if ($hasPassport): ?>
                                                    <a href="<?= pageUrl('modules/products/passports.php?product=' . $item['product_id']) ?>" 
                                                       style="color: var(--primary-color); text-decoration: none; font-weight: 600;"
                                                       onmouseover="this.style.textDecoration='underline'" 
                                                       onmouseout="this.style.textDecoration='none'">
                                                        <?= e($item['article'] ?? '—') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= e($item['article'] ?? '—') ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= e($item['product_name']) ?></strong>
                                                <?php if (!empty($item['description'])): ?>
                                                <div style="font-size: 12px; color: var(--text-secondary);"><?= e($item['description']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e($item['unit_name'] ?? 'шт.') ?></td>
                                            <td style="text-align: right;"><strong><?= number_format($item['quantity'], 0, ',', ' ') ?></strong></td>
                                            <td style="text-align: right;"><?= formatMoney($item['price']) ?></td>
                                            <td style="text-align: right;"><strong><?= formatMoney($item['total']) ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot style="background: #f8f9fa; font-weight: 600;">
                                    <tr>
                                        <td colspan="4" style="text-align: right; padding: 12px;">Итого:</td>
                                        <td style="text-align: right;"><?= number_format($totalQuantity, 0, ',', ' ') ?></td>
                                        <td></td>
                                        <td style="text-align: right; color: var(--primary-color);"><?= formatMoney($totalAmount) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Производственные задания -->
                <?php if (!empty($tasks)): ?>
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-body" style="padding: 0;">
                        <h3 style="padding: 20px 20px 0; margin: 0;" class="section-title">
                            🏭 Производственные задания
                        </h3>
                        <div style="padding: 20px;">
                            <?php foreach ($tasks as $task): ?>
                            <div class="task-card">
                                <div class="task-header">
                                    <div>
                                        <strong>Задание №<?= $task['id'] ?></strong>
                                        <span style="margin-left: 12px; color: var(--text-secondary);">
                                            от <?= formatDate($task['created_at']) ?>
                                        </span>
                                    </div>
                                    <span class="badge" style="background: <?= e($task['task_status_color']) ?>20; color: <?= e($task['task_status_color']) ?>">
                                        <?= e($task['task_status_name']) ?>
                                    </span>
                                </div>
                                <div class="task-body">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                                        <div>
                                            <div class="meta-label">Продукция</div>
                                            <div class="meta-value"><?= e($task['product_name'] ?? '—') ?></div>
                                        </div>
                                        <div>
                                            <div class="meta-label">Количество</div>
                                            <div class="meta-value"><?= $task['quantity'] ?? '—' ?> <?= e($task['unit_name'] ?? 'шт.') ?></div>
                                        </div>
                                        <div>
                                            <div class="meta-label">Срок выполнения</div>
                                            <div class="meta-value"><?= e($task['due_date'] ?? '—') ?></div>
                                        </div>
                                        <div>
                                            <div class="meta-label">Цех/Участок</div>
                                            <div class="meta-value"><?= e($task['work_center'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Кнопки действий -->
                <div class="action-buttons">
                    <a href="list.php" class="btn btn-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                            <path d="M19 12H5M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Назад к списку
                    </a>
                    <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                            <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M18.5 2.5002C18.8978 2.10239 19.4374 1.87891 20 1.87891C20.5626 1.87891 21.1022 2.10239 21.5 2.5002C21.8978 2.89801 22.1213 3.43762 22.1213 4.0002C22.1213 4.56278 21.8978 5.10239 21.5 5.5002L12 15.0002L8 16.0002L9 12.0002L18.5 2.5002Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Редактировать
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
