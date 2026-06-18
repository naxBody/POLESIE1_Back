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
                    </div>
                </div>
                
                <!-- Информация о заказчике и заказе -->
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-card-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px; vertical-align: middle;">
                                <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="10 9 9 9 8 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Информация о заказе
                        </div>
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
                        <div class="info-card-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px; vertical-align: middle;">
                                <path d="M3 21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M5 21V7L8 4H16L19 7V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 21V13H16V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 11V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Заказчик
                        </div>
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
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                                <path d="M21 16V8C20.9996 7.64927 20.9071 7.30481 20.7315 7.00116C20.556 6.69751 20.3037 6.44536 20 6.27L13 2.27C12.696 2.09446 12.3511 2.00205 12 2.00205C11.6489 2.00205 11.304 2.09446 11 2.27L4 6.27C3.69626 6.44536 3.44398 6.69751 3.26846 7.00116C3.09294 7.30481 3.00036 7.64927 3 8V16C3.00036 16.3507 3.09294 16.6952 3.26846 16.9988C3.44398 17.3025 3.69626 17.5546 4 17.73L11 21.73C11.304 21.9055 11.6489 21.9979 12 21.9979C12.3511 21.9979 12.696 21.9055 13 21.73L20 17.73C20.3037 17.5546 20.556 17.3025 20.7315 16.9988C20.9071 16.6952 20.9996 16.3507 21 16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="12" y1="22.08" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Товары заказа
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
                                        <td colspan="6" style="text-align: right; padding: 12px;">Итого:</td>
                                        <td style="text-align: right; padding: 12px; color: var(--primary-color);"><?= formatMoney($order['total_amount']) ?></td>
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
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                                <rect x="2" y="7" width="20" height="13" rx="2" ry="2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 21V5C16 4.46957 15.7893 3.96086 15.4142 3.58579C15.0391 3.21071 14.5304 3 14 3H10C9.46957 3 8.96086 3.21071 8.58579 3.58579C8.21071 3.96086 8 4.46957 8 5V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="6" y1="11" x2="18" y2="11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="6" y1="15" x2="18" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Производственные задания
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
                    <a href="print_order.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-success">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V8C2 7.46957 2.21071 6.96086 2.58579 6.58579C2.96086 6.21071 3.46957 6 4 6H20C20.5304 6 21.0391 6.21071 21.4142 6.58579C21.7893 6.96086 22 7.46957 22 8V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Печать заказа
                    </a>
                    <a href="../production/execute.php?order_id=<?= $order['id'] ?>" class="btn btn-success">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Перейти к производству
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
