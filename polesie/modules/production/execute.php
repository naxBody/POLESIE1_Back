<?php
/**
 * Страница исполнения производства
 * Фиксация производства товаров, списание материалов, управление этапами
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключаем API файл для доступа к функциям обработки
require_once __DIR__ . '/api_execute.php';

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Исполнение производства';

// Проверка AJAX-запроса
$isAjaxRequest = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                 || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

// Получение выбранного задания из GET параметра
$selectedTaskId = isset($_GET['task']) ? (int)$_GET['task'] : null;

// Если это AJAX-запрос, отдаем только содержимое рабочей области
if ($isAjaxRequest && $selectedTaskId) {
    // Находим задание в базе
    $stmt = $pdo->prepare("SELECT pt.*, 
               o.order_number, 
               p.name as product_name, 
               p.article as product_article,
               p.id as product_id,
               p.route_card_id,
               c.name as category_name, 
               u.symbol as unit_name,
               u2.full_name as responsible_name, 
               u3.full_name as worker_name,
               pt.quantity_plan as quantity_plan,
               pt.quantity_fact as quantity_fact,
               pt.quantity_good as quantity_good,
               pt.quantity_defect as quantity_defect,
               pt.status as task_status,
               pt.planned_start as planned_date,
               pt.actual_start as actual_start,
               pt.actual_end as actual_end
        FROM production_tasks pt
        JOIN orders o ON pt.order_id = o.id
        JOIN products p ON pt.product_id = p.id
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN base_units u ON p.base_unit_id = u.id
        LEFT JOIN users u2 ON pt.responsible_id = u2.id
        LEFT JOIN users u3 ON pt.worker_id = u3.id
        WHERE pt.id = ?");
    $stmt->execute([$selectedTaskId]);
    $selectedTask = $stmt->fetch();
    
    if ($selectedTask) {
        // Этапы выполнения
        $stagesStmt = $pdo->prepare("
            SELECT pts.id, pts.task_id, pts.stage_id, pts.status, pts.started_at, pts.completed_at,
                   pts.worker_id, pts.time_spent_hours, pts.quantity_passed, pts.quantity_rejected, pts.notes,
                   ps.name as stage_name, ps.code as stage_code, ps.color as stage_color, ps.sort_order
            FROM production_task_stages pts
            JOIN production_stages ps ON pts.stage_id = ps.id
            WHERE pts.task_id = ?
            ORDER BY ps.sort_order
        ");
        $stagesStmt->execute([$selectedTask['id']]);
        $selectedTask['stages'] = $stagesStmt->fetchAll();
        
        // Если этапы не найдены, но есть маршрутная карта у продукта, создаем их автоматически
        $routeCardId = $selectedTask['route_card_id'] ?? null;
        
        if (empty($routeCardId) && !empty($selectedTask['product_id'])) {
            $prodStmt = $pdo->prepare("SELECT rc.id FROM route_cards rc WHERE rc.product_id = ? AND rc.is_active = 1 ORDER BY rc.created_at DESC LIMIT 1");
            $prodStmt->execute([$selectedTask['product_id']]);
            $prodRoute = $prodStmt->fetch();
            if ($prodRoute) {
                $routeCardId = $prodRoute['id'];
            }
        }
        
        if (empty($selectedTask['stages']) && !empty($routeCardId)) {
            createStagesForTask($pdo, $selectedTask['id'], $routeCardId);
            
            // Повторно получаем этапы
            $stagesStmt->execute([$selectedTask['id']]);
            $selectedTask['stages'] = $stagesStmt->fetchAll();
        } elseif (!empty($selectedTask['stages']) && count($selectedTask['stages']) === 1) {
            // Если только один этап "Заготовка", проверяем маршрутную карту на наличие других операций
            if (!empty($routeCardId)) {
                $checkOpsStmt = $pdo->prepare("SELECT COUNT(*) as op_count FROM route_card_operations WHERE route_card_id = ?");
                $checkOpsStmt->execute([$routeCardId]);
                $opCount = $checkOpsStmt->fetch();
                
                if ($opCount && $opCount['op_count'] > 1) {
                    // В маршрутной карте больше одной операции, но в задании только одна - пересоздаем этапы
                    $deleteStmt = $pdo->prepare("DELETE FROM production_task_stages WHERE task_id = ?");
                    $deleteStmt->execute([$selectedTask['id']]);
                    
                    createStagesForTask($pdo, $selectedTask['id'], $routeCardId);
                    
                    // Повторно получаем этапы
                    $stagesStmt->execute([$selectedTask['id']]);
                    $selectedTask['stages'] = $stagesStmt->fetchAll();
                }
            }
        }
        
        // Материалы для задания
        $materialsStmt = $pdo->prepare("
            SELECT ptm.id, ptm.task_id, ptm.material_id, ptm.quantity_required, 
                   ptm.quantity_reserved, ptm.quantity_used, ptm.unit_cost, ptm.total_cost, ptm.status as material_status,
                   m.name_full as material_name, m.name_short as material_short, m.code as material_code,
                   mu.symbol as unit_symbol,
                   COALESCE(m.current_stock, 0) as current_stock,
                   CASE 
                       WHEN COALESCE(m.current_stock, 0) >= ptm.quantity_required THEN 'sufficient'
                       WHEN COALESCE(m.current_stock, 0) > 0 THEN 'partial'
                       ELSE 'insufficient'
                   END as availability
            FROM production_tasks_materials ptm
            JOIN materials m ON ptm.material_id = m.id
            LEFT JOIN base_units mu ON m.base_unit_id = mu.id
            WHERE ptm.task_id = ?
            ORDER BY m.name_full
        ");
        $materialsStmt->execute([$selectedTask['id']]);
        $selectedTask['materials'] = $materialsStmt->fetchAll();
        
        // Серийные номера для задания
        $serialStmt = $pdo->prepare("
            SELECT sn.id, sn.serial_number, sn.status, sn.created_at,
                   p.name as product_name, p.article as product_article
            FROM product_serial_numbers sn
            JOIN products p ON sn.product_id = p.id
            WHERE sn.task_id = ?
            ORDER BY sn.created_at DESC
        ");
        $serialStmt->execute([$selectedTask['id']]);
        $selectedTask['serial_numbers'] = $serialStmt->fetchAll();
        
        // Рендерим только содержимое рабочей области (без полного HTML)
        ?>
        <div id="work-area-content">
        <div class="work-area-header">
            <div class="work-area-title">
                <h3><?= e($selectedTask['product_name']) ?></h3>
                <p class="work-area-subtitle">
                    Артикул: <?= e($selectedTask['product_article']) ?> • 
                    Заказ: <?= e($selectedTask['order_number']) ?> • 
                    Задание #<?= $selectedTask['id'] ?>
                </p>
            </div>
            <div class="work-area-actions">
                <button class="btn btn-primary" onclick="openProductionModal(<?= $selectedTask['id'] ?>)">
                    ✅ Завершить производство
                </button>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?= (int)$selectedTask['quantity_plan'] ?></div>
                <div class="stat-label">План</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--info-color);"><?= (int)$selectedTask['quantity_fact'] ?></div>
                <div class="stat-label">Факт</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success-color);"><?= (int)$selectedTask['quantity_good'] ?></div>
                <div class="stat-label">Годные</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--error-color);"><?= (int)$selectedTask['quantity_defect'] ?></div>
                <div class="stat-label">Брак</div>
            </div>
        </div>
        
        <!-- Вкладки -->
        <div class="tabs-container">
            <button class="tab-button active" data-tab="stages">Этапы</button>
            <button class="tab-button" data-tab="materials">Материалы</button>
            <button class="tab-button" data-tab="serial">Серийные номера</button>
            <button class="tab-button" data-tab="info">Информация</button>
        </div>
        
        <!-- Содержимое вкладок -->
        <div id="tab-stages" class="tab-content active">
            <?php if (!empty($selectedTask['stages'])): ?>
                <div class="stages-grid">
                    <?php foreach ($selectedTask['stages'] as $stage): ?>
                        <div class="stage-card <?= e($stage['status']) ?>" data-stage-id="<?= $stage['id'] ?>">
                            <div class="stage-header">
                                <div class="stage-name"><?= e($stage['stage_name']) ?></div>
                                <span class="stage-status status-<?= e($stage['status']) ?>">
                                    <?= $stage['status'] === 'pending' ? 'Ожидает' : 
                                        ($stage['status'] === 'in_progress' ? 'В работе' : 
                                        ($stage['status'] === 'completed' ? 'Завершен' : 'Пропущен')) ?>
                                </span>
                            </div>
                            
                            <?php if ($stage['status'] !== 'completed'): ?>
                                <div class="stage-actions">
                                    <?php if ($stage['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="startStage(<?= $stage['id'] ?>, <?= $selectedTask['id'] ?>)">
                                            ▶ Начать
                                        </button>
                                    <?php elseif ($stage['status'] === 'in_progress'): ?>
                                        <button class="btn btn-sm btn-success" onclick="completeStage(<?= $stage['id'] ?>, <?= $selectedTask['id'] ?>)">
                                            ✓ Завершить
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                    Завершен: <?= date('d.m.Y H:i', strtotime($stage['completed_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>Этапы не найдены</h3>
                    <p>Для данного продукта не настроена маршрутная карта</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="tab-materials" class="tab-content">
            <?php if (!empty($selectedTask['materials'])): ?>
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th>Материал</th>
                            <th>Требуется</th>
                            <th>Использовано</th>
                            <th>На складе</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($selectedTask['materials'] as $material): ?>
                            <tr>
                                <td>
                                    <strong><?= e($material['material_name']) ?></strong><br>
                                    <small style="color: var(--text-secondary);"><?= e($material['material_code']) ?></small>
                                </td>
                                <td><?= number_format($material['quantity_required'], 2) ?> <?= e($material['unit_symbol']) ?></td>
                                <td><?= number_format($material['quantity_used'], 2) ?> <?= e($material['unit_symbol']) ?></td>
                                <td><?= number_format($material['current_stock'], 2) ?> <?= e($material['unit_symbol']) ?></td>
                                <td>
                                    <span class="availability-badge availability-<?= e($material['availability']) ?>">
                                        <?= $material['availability'] === 'sufficient' ? 'Достаточно' : 
                                            ($material['availability'] === 'partial' ? 'Частично' : 'Недостаточно') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>Материалы не найдены</h3>
                    <p>Для данного задания не указаны материалы</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="tab-serial" class="tab-content">
            <?php if (!empty($selectedTask['serial_numbers'])): ?>
                <div class="serial-numbers-list">
                    <?php foreach ($selectedTask['serial_numbers'] as $sn): ?>
                        <div class="serial-badge">
                            <strong><?= e($sn['serial_number']) ?></strong><br>
                            <small><?= $sn['status'] === 'active' ? 'Активен' : 'Архив' ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏷️</div>
                    <h3>Серийные номера не найдены</h3>
                    <p>Серийные номера будут созданы при завершении производства</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="tab-info" class="tab-content">
            <div class="production-form">
                <div class="form-group">
                    <label class="form-label">Ответственный</label>
                    <input type="text" class="form-input" value="<?= e($selectedTask['responsible_name']) ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Исполнитель</label>
                    <select class="form-select" id="workerSelect" onchange="updateWorker(<?= $selectedTask['id'] ?>)">
                        <option value="">Не назначен</option>
                        <?php
                        $workersStmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('worker', 'engineer', 'admin') ORDER BY full_name");
                        while ($worker = $workersStmt->fetch()) {
                            $selected = ($worker['id'] == $selectedTask['worker_id']) ? 'selected' : '';
                            echo "<option value='{$worker['id']}' $selected>" . e($worker['full_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Плановая дата начала</label>
                    <input type="text" class="form-input" value="<?= date('d.m.Y', strtotime($selectedTask['planned_date'])) ?>" readonly>
                </div>
                
                <?php if ($selectedTask['actual_start']): ?>
                <div class="form-group">
                    <label class="form-label">Фактическая дата начала</label>
                    <input type="text" class="form-input" value="<?= date('d.m.Y H:i', strtotime($selectedTask['actual_start'])) ?>" readonly>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Приоритет</label>
                    <span class="task-priority priority-<?= e($selectedTask['priority']) ?>">
                        <?= $selectedTask['priority'] === 'urgent' ? 'Срочно' : 
                            ($selectedTask['priority'] === 'high' ? 'Высокий' : 
                            ($selectedTask['priority'] === 'normal' ? 'Нормальный' : 'Низкий')) ?>
                    </span>
                </div>
            </div>
        </div>
        </div>
        <?php
        exit;
    }
}

// Получение всех активных заданий с группировкой по заказам
$sql = "SELECT pt.*, 
               o.order_number, 
               p.name as product_name, 
               p.article as product_article,
               p.id as product_id,
               c.name as category_name, 
               u.symbol as unit_name,
               u2.full_name as responsible_name, 
               u3.full_name as worker_name,
               pt.quantity_plan as quantity_plan,
               pt.quantity_fact as quantity_fact,
               pt.quantity_good as quantity_good,
               pt.quantity_defect as quantity_defect,
               pt.status as task_status,
               pt.planned_start as planned_date,
               pt.actual_start as actual_start,
               pt.actual_end as actual_end
        FROM production_tasks pt
        JOIN orders o ON pt.order_id = o.id
        JOIN products p ON pt.product_id = p.id
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN base_units u ON p.base_unit_id = u.id
        LEFT JOIN users u2 ON pt.responsible_id = u2.id
        LEFT JOIN users u3 ON pt.worker_id = u3.id
        WHERE pt.status IN ('planned', 'in_progress')
        ORDER BY 
            o.order_number ASC,
            CASE pt.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'normal' THEN 3 
                WHEN 'low' THEN 4 
            END,
            pt.planned_start ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$allTasks = $stmt->fetchAll();

// Группировка заданий по заказам
$ordersGrouped = [];
foreach ($allTasks as $task) {
    $orderNumber = $task['order_number'];
    if (!isset($ordersGrouped[$orderNumber])) {
        $ordersGrouped[$orderNumber] = [
            'order_number' => $orderNumber,
            'tasks' => []
        ];
    }
    $ordersGrouped[$orderNumber]['tasks'][] = $task;
}

// Если выбрано задание, используем его, иначе первое задание из первого заказа
$tasks = $allTasks;
$selectedTask = null;
if ($selectedTaskId) {
    foreach ($allTasks as $task) {
        if ($task['id'] == $selectedTaskId) {
            $selectedTask = $task;
            break;
        }
    }
}
if (!$selectedTask && !empty($allTasks)) {
    $selectedTask = $allTasks[0];
}

// Получение этапов для выбранного задания
if ($selectedTask) {
    // Этапы выполнения
    $stagesStmt = $pdo->prepare("
        SELECT pts.id, pts.task_id, pts.stage_id, pts.status, pts.started_at, pts.completed_at,
               pts.worker_id, pts.time_spent_hours, pts.quantity_passed, pts.quantity_rejected, pts.notes,
               ps.name as stage_name, ps.code as stage_code, ps.color as stage_color, ps.sort_order
        FROM production_task_stages pts
        JOIN production_stages ps ON pts.stage_id = ps.id
        WHERE pts.task_id = ?
        ORDER BY ps.sort_order
    ");
    $stagesStmt->execute([$selectedTask['id']]);
    $selectedTask['stages'] = $stagesStmt->fetchAll();
    
    // Если этапы не найдены, но есть маршрутная карта у продукта, создаем их автоматически
    $routeCardId = $selectedTask['route_card_id'] ?? null;
    
    // Если в задании нет route_card_id, пытаемся найти его через продукт
    if (empty($routeCardId) && !empty($selectedTask['product_id'])) {
        $prodStmt = $pdo->prepare("SELECT rc.id FROM route_cards rc WHERE rc.product_id = ? AND rc.is_active = 1 ORDER BY rc.created_at DESC LIMIT 1");
        $prodStmt->execute([$selectedTask['product_id']]);
        $prodRoute = $prodStmt->fetch();
        if ($prodRoute) {
            $routeCardId = $prodRoute['id'];
        }
    }
    
    if (empty($selectedTask['stages']) && !empty($routeCardId)) {
        createStagesForTask($pdo, $selectedTask['id'], $routeCardId);
        
        // Повторно получаем этапы
        $stagesStmt->execute([$selectedTask['id']]);
        $selectedTask['stages'] = $stagesStmt->fetchAll();
    }
    
    // Материалы для задания
    $materialsStmt = $pdo->prepare("
        SELECT ptm.id, ptm.task_id, ptm.material_id, ptm.quantity_required, 
               ptm.quantity_reserved, ptm.quantity_used, ptm.unit_cost, ptm.total_cost, ptm.status as material_status,
               m.name_full as material_name, m.name_short as material_short, m.code as material_code,
               mu.symbol as unit_symbol,
               COALESCE(m.current_stock, 0) as current_stock,
               CASE 
                   WHEN COALESCE(m.current_stock, 0) >= ptm.quantity_required THEN 'sufficient'
                   WHEN COALESCE(m.current_stock, 0) > 0 THEN 'partial'
                   ELSE 'insufficient'
               END as availability
        FROM production_tasks_materials ptm
        JOIN materials m ON ptm.material_id = m.id
        LEFT JOIN base_units mu ON m.base_unit_id = mu.id
        WHERE ptm.task_id = ?
        ORDER BY m.name_full
    ");
    $materialsStmt->execute([$selectedTask['id']]);
    $selectedTask['materials'] = $materialsStmt->fetchAll();
    
    // Серийные номера для задания
    $serialStmt = $pdo->prepare("
        SELECT sn.id, sn.serial_number, sn.status, sn.created_at,
               p.name as product_name, p.article as product_article
        FROM product_serial_numbers sn
        JOIN products p ON sn.product_id = p.id
        WHERE sn.task_id = ?
        ORDER BY sn.created_at DESC
    ");
    $serialStmt->execute([$selectedTask['id']]);
    $selectedTask['serial_numbers'] = $serialStmt->fetchAll();
}

// Получение этапов для всех остальных заданий (для отображения в списке)
foreach ($allTasks as &$task) {
    if (!isset($task['stages']) || !$task['stages']) {
        $stagesStmt = $pdo->prepare("
            SELECT COUNT(*) as stages_count
            FROM production_task_stages pts
            WHERE pts.task_id = ?
        ");
        $stagesStmt->execute([$task['id']]);
        $task['stages_count'] = $stagesStmt->fetch()['stages_count'];
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
        /* Специфичные стили для страницы исполнения */
        .production-dashboard {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .tasks-panel {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .tasks-list {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }
        
        .task-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .task-item:hover {
            background: var(--gray-50);
        }
        
        .task-item.active {
            background: linear-gradient(90deg, rgba(37, 99, 235, 0.08) 0%, transparent 100%);
            border-left: 4px solid var(--primary-color);
        }
        
        .task-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .task-number {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .task-priority {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .priority-urgent { background: #fee2e2; color: #dc2626; }
        .priority-high { background: #ffedd5; color: #ea580c; }
        .priority-normal { background: #dbeafe; color: #2563eb; }
        .priority-low { background: #f1f5f9; color: #64748b; }
        
        .task-product-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .task-order-info {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .task-progress {
            margin-top: 12px;
        }
        
        .progress-bar-container {
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 6px;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 4px;
            transition: width var(--transition-base);
        }
        
        .work-area {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 24px;
            transition: opacity 0.3s ease;
        }
        
        .work-area.loading {
            opacity: 1;
            pointer-events: auto;
        }
        
        .work-area-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .work-area-title h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .work-area-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .tabs-container {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }
        
        .tab-button {
            padding: 12px 20px;
            background: transparent;
            border: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            position: relative;
            transition: all var(--transition-fast);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        
        .tab-button:hover {
            color: var(--primary-color);
        }
        
        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .stages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stage-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 16px;
            border: 1px solid var(--border-color);
            transition: all var(--transition-fast);
        }
        
        .stage-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
        }
        
        .stage-card.completed {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(16, 185, 129, 0.1) 100%);
            border-color: var(--success-color);
        }
        
        .stage-card.in_progress {
            border-color: var(--info-color);
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.05) 0%, rgba(6, 182, 212, 0.1) 100%);
        }
        
        .stage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .stage-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .stage-status {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .status-pending { background: #f1f5f9; color: #64748b; }
        .status-in_progress { background: #e0f2fe; color: #0284c7; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-skipped { background: #fef3c7; color: #d97706; }
        
        .stage-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        
        .materials-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--gray-50);
            font-weight: 600;
            font-size: 13px;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }
        
        .materials-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        .availability-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .availability-sufficient { background: #d1fae5; color: #059669; }
        .availability-partial { background: #fef3c7; color: #d97706; }
        .availability-insufficient { background: #fee2e2; color: #dc2626; }
        
        .production-form {
            max-width: 600px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: all var(--transition-fast);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .serial-numbers-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .serial-badge {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 12px;
            text-align: center;
        }
        
        .serial-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .serial-status {
            font-size: 11px;
            margin-top: 6px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 16px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Боковая панель -->
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <!-- Основной контент -->
        <div class="main-content">
            <!-- Верхняя панель -->
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <!-- Контентная область -->
            <div class="content-area">
                <div class="content">
                    <div class="page-header">
                        <div class="page-header-title">
                            <h2>🏭 Исполнение производства</h2>
                            <p>Управление производственными заданиями и контроль выполнения</p>
                        </div>
                    </div>

                    <div class="production-dashboard">
                        <!-- Панель списка заданий -->
                        <div class="tasks-panel">
                            <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                                <h3 style="font-size: 16px; font-weight: 600;">Активные задания</h3>
                                <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                    <?= count($tasks) ?> заданий в работе
                                </p>
                            </div>
                            
                            <div class="tasks-list">
                                <?php if (empty($tasks)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📋</div>
                                        <h4>Нет активных заданий</h4>
                                        <p style="font-size: 13px;">Все задания выполнены или отсутствуют</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($tasks as $idx => $task): ?>
                                        <div class="task-item <?= $selectedTask && $task['id'] == $selectedTask['id'] ? 'active' : '' ?>" 
                                             data-task-id="<?= $task['id'] ?>"
                                             onclick="selectTask(<?= $task['id'] ?>)">
                                            <div class="task-item-header">
                                                <span class="task-number">#<?= $task['id'] ?></span>
                                                <span class="task-priority priority-<?= $task['priority'] ?>">
                                                    <?= $task['priority'] === 'urgent' ? 'Срочно' : 
                                                        ($task['priority'] === 'high' ? 'Высокий' : 
                                                        ($task['priority'] === 'low' ? 'Низкий' : 'Нормальный')) ?>
                                                </span>
                                            </div>
                                            
                                            <div class="task-product-name"><?= e($task['product_name']) ?></div>
                                            <div class="task-order-info">
                                                Заказ: <?= e($task['order_number']) ?> • 
                                                План: <?= (int)$task['quantity_plan'] ?> <?= e($task['unit_name']) ?>
                                            </div>
                                            
                                            <div class="task-progress">
                                                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary);">
                                                    <span>Прогресс</span>
                                                    <span><?= $task['quantity_fact'] > 0 ? round(($task['quantity_fact'] / $task['quantity_plan']) * 100) : 0 ?>%</span>
                                                </div>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar-fill" style="width: <?= $task['quantity_fact'] > 0 ? min(100, ($task['quantity_fact'] / $task['quantity_plan']) * 100) : 0 ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Рабочая область -->
                        <div class="work-area" id="workArea">
                            <div id="work-area-content">
                            <?php if ($selectedTask): ?>
                                <div class="work-area-header">
                                    <div class="work-area-title">
                                        <h3><?= e($selectedTask['product_name']) ?></h3>
                                        <p class="work-area-subtitle">
                                            Артикул: <?= e($selectedTask['product_article']) ?> • 
                                            Заказ: <?= e($selectedTask['order_number']) ?> • 
                                            Задание #<?= $selectedTask['id'] ?>
                                        </p>
                                    </div>
                                    <div class="work-area-actions">
                                        <button class="btn btn-primary" onclick="openProductionModal(<?= $selectedTask['id'] ?>)">
                                            ✅ Завершить производство
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="stats-row">
                                    <div class="stat-card">
                                        <div class="stat-value"><?= (int)$selectedTask['quantity_plan'] ?></div>
                                        <div class="stat-label">План</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value" style="color: var(--info-color);"><?= (int)$selectedTask['quantity_fact'] ?></div>
                                        <div class="stat-label">Факт</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value" style="color: var(--success-color);"><?= (int)$selectedTask['quantity_good'] ?></div>
                                        <div class="stat-label">Годные</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value" style="color: var(--danger-color);"><?= (int)$selectedTask['quantity_defect'] ?></div>
                                        <div class="stat-label">Брак</div>
                                    </div>
                                </div>
                                
                                <div class="tabs-container">
                                    <button class="tab-button active" data-tab="stages">Этапы</button>
                                    <button class="tab-button" data-tab="materials">Материалы</button>
                                    <button class="tab-button" data-tab="serial">Серийные номера</button>
                                    <button class="tab-button" data-tab="info">Информация</button>
                                </div>
                                
                                <!-- Вкладка этапы -->
                                <div class="tab-content active" id="tab-stages">
                                    <h4 style="margin-bottom: 16px;">Этапы производства</h4>
                                    <?php if (!empty($selectedTask['stages'])): ?>
                                    <div class="stages-grid">
                                        <?php foreach ($selectedTask['stages'] as $stage): ?>
                                            <div class="stage-card <?= $stage['status'] ?>">
                                                <div class="stage-header">
                                                    <span class="stage-name"><?= e($stage['stage_name']) ?></span>
                                                    <span class="stage-status status-<?= $stage['status'] ?>">
                                                        <?= $stage['status'] === 'pending' ? 'Ожидает' : 
                                                            ($stage['status'] === 'in_progress' ? 'В работе' : 
                                                            ($stage['status'] === 'completed' ? 'Завершено' : 'Пропущено')) ?>
                                                    </span>
                                                </div>
                                                <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px;">
                                                    Код: <?= e($stage['stage_code']) ?>
                                                </p>
                                                <?php if ($stage['status'] !== 'completed' && $stage['status'] !== 'skipped'): ?>
                                                    <div class="stage-actions">
                                                        <?php if ($stage['status'] === 'pending'): ?>
                                                            <button class="btn btn-sm btn-primary" 
                                                                    onclick="startStage(<?= $stage['id'] ?>, <?= $selectedTask['id'] ?>)">
                                                                ▶ Начать
                                                            </button>
                                                        <?php elseif ($stage['status'] === 'in_progress'): ?>
                                                            <button class="btn btn-sm btn-success" 
                                                                    onclick="completeStage(<?= $stage['id'] ?>, <?= $selectedTask['id'] ?>)">
                                                                ✓ Завершить
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                                        <?php if ($stage['completed_at']): ?>
                                                            Завершено: <?= date('d.m.Y H:i', strtotime($stage['completed_at'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                        <p style="color: var(--text-secondary);">Этапы не назначены</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Вкладка материалы -->
                                <div class="tab-content" id="tab-materials">
                                    <h4 style="margin-bottom: 16px;">Материалы для производства</h4>
                                    <?php if (!empty($selectedTask['materials'])): ?>
                                    <table class="materials-table">
                                        <thead>
                                            <tr>
                                                <th>Материал</th>
                                                <th>Артикул</th>
                                                <th>Требуется</th>
                                                <th>Использовано</th>
                                                <th>На складе</th>
                                                <th>Статус</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($selectedTask['materials'] as $mat): ?>
                                                <tr>
                                                    <td><?= e($mat['material_name']) ?></td>
                                                    <td><code><?= e($mat['material_code']) ?></code></td>
                                                    <td><?= (int)$mat['quantity_required'] ?> <?= e($mat['unit_symbol']) ?></td>
                                                    <td><?= (int)$mat['quantity_used'] ?> <?= e($mat['unit_symbol']) ?></td>
                                                    <td><?= (int)$mat['current_stock'] ?> <?= e($mat['unit_symbol']) ?></td>
                                                    <td>
                                                        <span class="availability-badge availability-<?= $mat['availability'] ?>">
                                                            <?= $mat['availability'] === 'sufficient' ? '✓ Достаточно' : 
                                                                ($mat['availability'] === 'partial' ? '⚠ Частично' : '✗ Недостаточно') ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    
                                    <?php if (!empty($selectedTask['materials'])): ?>
                                        <div class="btn-group">
                                            <button class="btn btn-primary" onclick="consumeMaterials(<?= $selectedTask['id'] ?>)">
                                                📦 Списать материалы
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                        <p style="color: var(--text-secondary);">Материалы не назначены</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Вкладка серийные номера -->
                                <div class="tab-content" id="tab-serial">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                        <h4>Серийные номера</h4>
                                        <button class="btn btn-primary" onclick="generateSerialNumber(<?= $selectedTask['id'] ?>, <?= $selectedTask['product_id'] ?>)">
                                            + Добавить серийный номер
                                        </button>
                                    </div>
                                    
                                    <?php if (empty($selectedTask['serial_numbers'])): ?>
                                        <div class="empty-state" style="padding: 40px 20px;">
                                            <div class="empty-state-icon">🏷️</div>
                                            <h5>Серийные номера не созданы</h5>
                                            <p style="font-size: 13px;">Создайте серийные номера для произведенной продукции</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="serial-numbers-list">
                                            <?php foreach ($selectedTask['serial_numbers'] as $sn): ?>
                                                <div class="serial-badge">
                                                    <div class="serial-number"><?= e($sn['serial_number']) ?></div>
                                                    <div class="serial-status">
                                                        <span class="badge badge-<?= $sn['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <?= $sn['status'] === 'active' ? 'Активен' : $sn['status'] ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Вкладка информация -->
                                <div class="tab-content" id="tab-info">
                                    <div class="production-form">
                                        <div class="form-group">
                                            <label class="form-label">Ответственный</label>
                                            <input type="text" class="form-input" value="<?= e($selectedTask['responsible_name'] ?? '—') ?>" disabled>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Исполнитель</label>
                                            <select class="form-select" id="workerSelect" onchange="updateWorker(<?= $selectedTask['id'] ?>)">
                                                <option value="">Не назначен</option>
                                                <?php
                                                $workersStmt = $pdo->query("SELECT id, full_name FROM users WHERE is_active = TRUE ORDER BY full_name");
                                                foreach ($workersStmt->fetchAll() as $worker):
                                                ?>
                                                    <option value="<?= $worker['id'] ?>" <?= $selectedTask['worker_id'] == $worker['id'] ? 'selected' : '' ?>>
                                                        <?= e($worker['full_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Плановая дата начала</label>
                                            <input type="text" class="form-input" value="<?= !empty($selectedTask['planned_date']) ? date('d.m.Y H:i', strtotime($selectedTask['planned_date'])) : '—' ?>" disabled>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Фактическая дата начала</label>
                                            <input type="text" class="form-input" value="<?= !empty($selectedTask['actual_start']) ? date('d.m.Y H:i', strtotime($selectedTask['actual_start'])) : '—' ?>" disabled>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Приоритет</label>
                                            <select class="form-select" onchange="updatePriority(<?= $selectedTask['id'] ?>, this.value)">
                                                <option value="low" <?= $selectedTask['priority'] === 'low' ? 'selected' : '' ?>>Низкий</option>
                                                <option value="normal" <?= $selectedTask['priority'] === 'normal' ? 'selected' : '' ?>>Нормальный</option>
                                                <option value="high" <?= $selectedTask['priority'] === 'high' ? 'selected' : '' ?>>Высокий</option>
                                                <option value="urgent" <?= $selectedTask['priority'] === 'urgent' ? 'selected' : '' ?>>Срочный</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Заметки</label>
                                            <textarea class="form-textarea" id="taskNotes" placeholder="Добавьте заметки к заданию..."><?= e($selectedTask['notes'] ?? '') ?></textarea>
                                            <button class="btn btn-secondary" style="margin-top: 8px;" onclick="saveNotes(<?= $selectedTask['id'] ?>)">
                                                💾 Сохранить заметки
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">🎯</div>
                                    <h3>Выберите задание</h3>
                                    <p>Выберите производственное задание из списка слева для начала работы</p>
                                </div>
                            <?php endif; ?>
                            </div> <!-- конец work-area-content -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно завершения производства -->
    <div id="productionModalOverlay" class="product-modal-overlay" style="display: none;" onclick="closeProductionModal(event)">
        <div class="product-modal" style="max-width: 600px;">
            <div class="product-modal-header">
                <h3 class="product-modal-title">✅ Завершение производства</h3>
                <button class="product-modal-close" onclick="closeProductionModalDirect()">×</button>
            </div>
            <div class="product-modal-body">
                <input type="hidden" id="modalTaskId">
                
                <div class="form-group">
                    <label class="form-label">Произведено (годных)</label>
                    <input type="number" class="form-input" id="quantityGood" step="0.001" placeholder="Количество годной продукции">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Брак</label>
                    <input type="number" class="form-input" id="quantityDefect" step="0.001" placeholder="Количество бракованной продукции">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Комментарий</label>
                    <textarea class="form-textarea" id="completionComment" placeholder="Комментарий к завершению производства..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="autoSerialGenerate"> 
                        Автоматически создать серийные номера
                    </label>
                </div>
                
                <div class="btn-group" style="justify-content: flex-end;">
                    <button class="btn btn-outline" onclick="closeProductionModalDirect()">Отмена</button>
                    <button class="btn btn-success" onclick="completeProduction()">Завершить производство</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentTaskId = <?= $selectedTask ? $selectedTask['id'] : 0 ?>;
        
        // Выбор задачи без перезагрузки страницы (через AJAX)
        function selectTask(taskId) {
            if (taskId === currentTaskId) return;
            
            // Обновляем визуальное выделение задач
            document.querySelectorAll('.task-item').forEach(item => {
                item.classList.remove('active');
            });
            const activeItem = document.querySelector(`.task-item[data-task-id="${taskId}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
            
            // Показываем индикатор загрузки
            const workArea = document.getElementById('workArea');
            if (workArea) {
                // Не добавляем класс loading, чтобы не затемнять блок
                // workArea.classList.add('loading');
            }
            
            // Загружаем данные задачи через AJAX
            fetch('?task=' + taskId + '&ajax=1', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Находим контейнер рабочей области и обновляем его содержимое
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newWorkAreaContent = doc.querySelector('#work-area-content');
                
                if (newWorkAreaContent && workArea) {
                    // Полностью заменяем содержимое workArea
                    workArea.innerHTML = newWorkAreaContent.innerHTML;
                    
                    // Обновляем URL без перезагрузки
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?task=' + taskId;
                    window.history.pushState({taskId: taskId}, '', newUrl);
                    
                    currentTaskId = taskId;
                    
                    // Инициализируем вкладки заново
                    initTabs();
                    
                    // Активируем первую вкладку по умолчанию
                    switchTab('stages');
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки задачи:', error);
                // Если AJAX не сработал, делаем обычную перезагрузку
                window.location.href = '?task=' + taskId;
            });
        }
        
        // Инициализация вкладок
        function initTabs() {
            // Добавляем обработчики событий на кнопки вкладок
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    if (tabName) {
                        switchTab(tabName);
                    }
                });
            });
        }
        
        // Вызываем после загрузки DOM
        document.addEventListener('DOMContentLoaded', function() {
            initTabs();
        });
        
        function switchTab(tabName) {
            // Скрываем все вкладки
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Показываем выбранную
            const tabContent = document.getElementById('tab-' + tabName);
            if (tabContent) {
                tabContent.classList.add('active');
            }
            
            // Находим кнопку которая соответствует этой вкладке и делаем её активной
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => {
                const dataTab = btn.getAttribute('data-tab');
                if (dataTab === tabName) {
                    btn.classList.add('active');
                }
            });
        }
        
        function startStage(stageId, taskId) {
            if (!confirm('Начать этот этап производства?')) return;
            
            fetch('api_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'start_stage',
                    stage_id: stageId,
                    task_id: taskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Этап начат', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error || 'Ошибка', 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка сети', 'error');
            });
        }
        
        function completeStage(stageId, taskId) {
            // Завершаем этап без запроса количества - используем плановое количество
            fetch('api_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'complete_stage',
                    stage_id: stageId,
                    task_id: taskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.task_completed) {
                        // Все этапы завершены - открываем модальное окно завершения производства
                        showNotification('Все этапы завершены! Укажите количество готовой продукции.', 'success');
                        // Не перезагружаем сразу, даем пользователю ввести данные
                        openProductionModal(taskId);
                    } else {
                        showNotification('Этап завершен. Переход к следующему этапу...', 'success');
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    showNotification(data.error || 'Ошибка', 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка сети', 'error');
            });
        }
        
        function consumeMaterials(taskId) {
            if (!confirm('Списать материалы для этого задания? Убедитесь, что производство началось.')) return;
            
            fetch('api_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'consume_materials',
                    task_id: taskId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Материалы списаны', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error || 'Ошибка', 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка сети', 'error');
            });
        }
        
        function generateSerialNumber(taskId, productId) {
            fetch('api_serial.php?action=generate&product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.serial_number) {
                    if (confirm('Сгенерирован серийный номер: ' + data.serial_number + '\n\nСоздать его?')) {
                        fetch('api_execute.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'create_serial',
                                task_id: taskId,
                                product_id: productId,
                                serial_number: data.serial_number
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showNotification('Серийный номер создан', 'success');
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showNotification(result.error || 'Ошибка', 'error');
                            }
                        });
                    }
                } else {
                    showNotification(data.error || 'Ошибка генерации', 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка сети', 'error');
            });
        }
        
        function openProductionModal(taskId) {
            document.getElementById('modalTaskId').value = taskId;
            document.getElementById('productionModalOverlay').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeProductionModal(event) {
            if (event.target === document.getElementById('productionModalOverlay')) {
                closeProductionModalDirect();
            }
        }
        
        function closeProductionModalDirect() {
            document.getElementById('productionModalOverlay').style.display = 'none';
            document.body.style.overflow = '';
        }
        
        function completeProduction() {
            const taskId = document.getElementById('modalTaskId').value;
            const quantityGood = parseFloat(document.getElementById('quantityGood').value) || 0;
            const quantityDefect = parseFloat(document.getElementById('quantityDefect').value) || 0;
            const comment = document.getElementById('completionComment').value;
            const autoSerial = document.getElementById('autoSerialGenerate').checked;
            
            if (quantityGood === 0 && quantityDefect === 0) {
                showNotification('Укажите количество продукции', 'warning');
                return;
            }
            
            fetch('api_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'complete_production',
                    task_id: taskId,
                    quantity_good: quantityGood,
                    quantity_defect: quantityDefect,
                    comment: comment,
                    auto_serial: autoSerial
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Производство завершено. Продукция добавлена на склад.', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.error || 'Ошибка', 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка сети', 'error');
            });
        }
        
        function updateWorker(taskId) {
            const workerId = document.getElementById('workerSelect').value;
            
            fetch('api_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_worker',
                    task_id: taskId,
                    worker_id: workerId || null
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Исполнитель обновлен', 'success');
                } else {
                    showNotification(data.error || 'Ошибка', 'error');
                }
            });
        }
        
        function updatePriority(taskId, priority) {
            fetch('api_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_priority',
                    task_id: taskId,
                    priority: priority
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Приоритет обновлен', 'success');
                } else {
                    showNotification(data.error || 'Ошибка', 'error');
                }
            });
        }
        
        function saveNotes(taskId) {
            const notes = document.getElementById('taskNotes').value;
            
            fetch('api_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_notes',
                    task_id: taskId,
                    notes: notes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Заметки сохранены', 'success');
                } else {
                    showNotification(data.error || 'Ошибка', 'error');
                }
            });
        }
        
        // Закрытие модального окна по ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProductionModalDirect();
            }
        });
    </script>
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
