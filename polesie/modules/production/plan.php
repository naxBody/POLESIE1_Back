<?php
/**
 * План выпуска - все заказы и производственные задания
 * Отображение статуса производства по каждому заказу
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

// Получение всех заказов с информацией о производстве
$orders = $pdo->query("
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
    ORDER BY o.order_date DESC, o.created_at DESC
")->fetchAll();

// Для каждого заказа получаем позиции и статус производства
foreach ($orders as &$order) {
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
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll();
    
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
    $stmt->execute([$order['id']]);
    $order['tasks'] = $stmt->fetchAll();
    
    // Этапы выполнения для каждого задания
    foreach ($order['tasks'] as &$task) {
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
}
unset($order);

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
    
    foreach ($order['tasks'] as $task) {
        $stats['total_tasks']++;
        if ($task['status'] === 'in_progress') $stats['tasks_in_progress']++;
        if ($task['status'] === 'completed') $stats['tasks_completed']++;
    }
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
                
                <!-- Заказы -->
                <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-card-header">
                        <div>
                            <strong style="font-size: 16px;"><?= e($order['order_number']) ?></strong>
                            <span class="badge" style="background: <?= e($order['status_color']) ?>20; color: <?= e($order['status_color']) ?>; margin-left: 8px;">
                                <?= e($order['status_name']) ?>
                            </span>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <span style="font-size: 14px; color: var(--text-secondary);">
                                📅 <?= formatDate($order['order_date']) ?>
                                <?php if ($order['delivery_date']): ?>
                                    → <?= formatDate($order['delivery_date']) ?>
                                <?php endif; ?>
                            </span>
                            <a href="<?= pageUrl('modules/orders/list.php') ?>" class="btn btn-sm btn-secondary">Детали</a>
                        </div>
                    </div>
                    
                    <div class="order-card-body">
                        <!-- Информация о заказе -->
                        <div class="order-info-grid">
                            <div class="info-item">
                                <label>Заказчик</label>
                                <value><?= e($order['contractor_name'] ?? '—') ?></value>
                            </div>
                            <div class="info-item">
                                <label>Ответственный</label>
                                <value><?= e($order['responsible_name'] ?? '—') ?></value>
                            </div>
                            <div class="info-item">
                                <label>Сумма</label>
                                <value><?= formatMoney($order['total_amount']) ?></value>
                            </div>
                            <div class="info-item">
                                <label>Позиций</label>
                                <value><?= count($order['items']) ?> шт.</value>
                            </div>
                        </div>
                        
                        <!-- Позиции заказа -->
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Продукция</th>
                                    <th>Артикул</th>
                                    <th>Кол-во</th>
                                    <th>Цена</th>
                                    <th>Сумма</th>
                                    <th>Статус производства</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td><strong><?= e($item['product_name']) ?></strong></td>
                                    <td><?= e($item['product_article']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= formatMoney($item['price']) ?></td>
                                    <td><?= formatMoney($item['total']) ?></td>
                                    <td>
                                        <span class="badge" style="background: <?= e($item['production_status_color']) ?>20; color: <?= e($item['production_status_color']) ?>">
                                            <?= e($item['production_status_name']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Производственные задания -->
                        <?php if (!empty($order['tasks'])): ?>
                        <div class="tasks-section">
                            <h4 style="margin-bottom: 12px; font-size: 14px; color: var(--text-secondary);">🏭 Производственные задания</h4>
                            
                            <?php foreach ($order['tasks'] as $task): ?>
                            <div class="task-card">
                                <div class="task-header">
                                    <div>
                                        <span class="task-title"><?= e($task['task_number']) ?></span>
                                        <span style="margin-left: 12px; color: var(--text-secondary);"><?= e($task['product_name']) ?></span>
                                    </div>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <span class="badge" style="background: <?= e($task['status_color']) ?>20; color: <?= e($task['status_color']) ?>">
                                            <?= e($task['status_name']) ?>
                                        </span>
                                        <button class="expand-btn" onclick="toggleTaskDetails(<?= $task['id'] ?>)">▼</button>
                                    </div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text-secondary);">
                                    <span>План: <?= $task['quantity_plan'] ?> шт.</span>
                                    <span>Факт: <?= $task['quantity_fact'] ?> шт.</span>
                                    <span>
                                        <?php if ($task['planned_start']): ?>
                                            <?= date('d.m.Y', strtotime($task['planned_start'])) ?> - <?= date('d.m.Y', strtotime($task['planned_end'])) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $task['progress_percent'] ?>%"></div>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary); text-align: right;">
                                    Выполнено: <?= $task['progress_percent'] ?>%
                                </div>
                                
                                <!-- Детали этапов -->
                                <div class="task-details" id="task-details-<?= $task['id'] ?>">
                                    <h5 style="margin-bottom: 8px; font-size: 13px;">Этапы производства:</h5>
                                    <div class="stages-list">
                                        <?php foreach ($task['stages'] as $stage): ?>
                                        <span class="stage-badge <?= $stage['status'] ?>" style="<?= $stage['status'] !== 'pending' ? 'border-color: ' . e($stage['stage_color']) . '; border: 1px solid;' : '' ?>">
                                            <?= e($stage['stage_name']) ?>: <?= e($stage['status_name']) ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
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
            </div>
        </div>
    </div>
    
    <script>
        function toggleTaskDetails(taskId) {
            const details = document.getElementById('task-details-' + taskId);
            if (details) {
                details.classList.toggle('show');
            }
        }
    </script>
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
