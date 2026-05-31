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

// $user и $pdo уже определены в api_execute.php при подключении
$pageTitle = 'Исполнение производства';

// Проверка AJAX-запроса
$isAjaxRequest = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                 || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

// Получение выбранного заказа из GET параметра (для перехода из заказов)
$selectedOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

// Получение выбранного задания из GET параметра
$selectedTaskId = isset($_GET['task']) ? (int)$_GET['task'] : null;

// Если выбран заказ, но не выбрано задание, выбираем первое доступное задание для этого заказа
if ($selectedOrderId && !$selectedTaskId) {
    $firstTaskSql = "SELECT pt.id 
                     FROM production_tasks pt
                     WHERE pt.order_id = ? AND pt.status IN ('planned', 'in_progress')
                     ORDER BY pt.planned_start ASC
                     LIMIT 1";
    $stmt = $pdo->prepare($firstTaskSql);
    $stmt->execute([$selectedOrderId]);
    $firstTask = $stmt->fetch();
    if ($firstTask) {
        $selectedTaskId = $firstTask['id'];
    }
}

// AJAX запрос для получения товаров заказа
if ($isAjaxRequest && (isset($_GET['order']) || $selectedOrderId)) {
    $orderId = (int)($_GET['order'] ?? $selectedOrderId);
    
    // Получаем товары и задания для заказа
    $sql = "SELECT pt.*,
                   o.order_number,
                   o.id as order_id,
                   p.name as product_name,
                   p.article as product_article,
                   p.id as product_id,
                   u.symbol as unit_name,
                   oi.quantity as order_item_quantity,
                   pt.quantity_plan as quantity_plan,
                   pt.quantity_fact as quantity_fact,
                   pt.priority
            FROM production_tasks pt
            JOIN orders o ON pt.order_id = o.id
            LEFT JOIN order_items oi ON pt.order_item_id = oi.id
            JOIN products p ON pt.product_id = p.id
            LEFT JOIN base_units u ON p.base_unit_id = u.id
            WHERE o.id = ? AND pt.status IN ('planned', 'in_progress')
            ORDER BY p.name ASC, pt.planned_start ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);
    $tasks = $stmt->fetchAll();
    
    // Группировка по продуктам
    $products = [];
    foreach ($tasks as $task) {
        $productId = $task['product_id'];
        
        if (!isset($products[$productId])) {
            $products[$productId] = [
                'product_id' => $productId,
                'product_name' => $task['product_name'],
                'product_article' => $task['product_article'],
                'order_item_quantity' => $task['order_item_quantity'] ?? 0,
                'unit_name' => $task['unit_name'],
                'tasks' => []
            ];
        }
        
        $products[$productId]['tasks'][] = $task;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['products' => array_values($products)]);
    exit;
}

// Если это AJAX-запрос на задание, отдаем только содержимое рабочей области
if ($isAjaxRequest && $selectedTaskId) {
    // Находим задание в базе
    $stmt = $pdo->prepare("SELECT pt.*, 
               o.order_number, 
               o.id as order_id,
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
               pt.actual_end as actual_end,
               oi.id as order_item_id,
               oi.production_status as item_production_status
        FROM production_tasks pt
        JOIN orders o ON pt.order_id = o.id
        LEFT JOIN order_items oi ON pt.order_item_id = oi.id
        JOIN products p ON pt.product_id = p.id
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN base_units u ON p.base_unit_id = u.id
        LEFT JOIN users u2 ON pt.responsible_id = u2.id
        LEFT JOIN users u3 ON pt.worker_id = u3.id
        WHERE pt.id = ?");
    $stmt->execute([$selectedTaskId]);
    $selectedTask = $stmt->fetch();
    
    if ($selectedTask) {
        // Если этапы не найдены, но есть маршрутная карта у продукта, создаем их автоматически
        // Получаем route_card_id из таблицы products, если в production_tasks его нет
        $routeCardId = null;
        
        if (!empty($selectedTask['product_id'])) {
            $prodStmt = $pdo->prepare("SELECT rc.id FROM route_cards rc WHERE rc.product_id = ? AND rc.is_active = 1 ORDER BY rc.created_at DESC LIMIT 1");
            $prodStmt->execute([$selectedTask['product_id']]);
            $prodRoute = $prodStmt->fetch();
            if ($prodRoute) {
                $routeCardId = $prodRoute['id'];
            }
        }
        
        // Сначала проверяем, нужно ли пересоздать этапы (если только один этап "Заготовка", но в маршрутной карте больше операций)
        if (!empty($routeCardId)) {
            $checkOpsStmt = $pdo->prepare("SELECT COUNT(*) as op_count FROM route_card_operations WHERE route_card_id = ?");
            $checkOpsStmt->execute([$routeCardId]);
            $opCount = $checkOpsStmt->fetch();
            
            // Получаем текущие этапы для проверки
            $stagesStmt = $pdo->prepare("
                SELECT pts.id, pts.task_id, pts.stage_id, pts.status, pts.started_at, pts.completed_at,
                       pts.worker_id, pts.time_spent_hours, pts.quantity_passed, pts.quantity_rejected, pts.notes,
                       ps.name as stage_name, ps.code as stage_code, ps.color as stage_color, ps.sort_order,
                       rco.operation_number, rco.name as operation_name, rco.description, rco.time_norm_hours,
                       rco.work_center, rco.equipment
                FROM production_task_stages pts
                JOIN production_stages ps ON pts.stage_id = ps.id
                LEFT JOIN route_card_operations rco ON pts.operation_id = rco.id
                WHERE pts.task_id = ?
                ORDER BY COALESCE(rco.sort_order, ps.sort_order), COALESCE(rco.operation_number, '0')
            ");
            $stagesStmt->execute([$selectedTask['id']]);
            $currentStages = $stagesStmt->fetchAll();
            
            // Если этапов нет или только один "Заготовка", но в маршрутной карте больше операций - пересоздаем
            if (empty($currentStages) || (count($currentStages) === 1 && $opCount && $opCount['op_count'] > 1)) {
                // Удаляем старые этапы если они есть
                if (!empty($currentStages)) {
                    $deleteStmt = $pdo->prepare("DELETE FROM production_task_stages WHERE task_id = ?");
                    $deleteStmt->execute([$selectedTask['id']]);
                }
                
                createStagesForTask($pdo, $selectedTask['id'], $routeCardId);
            }
        }
        
        // Этапы выполнения - получаем после возможного пересоздания
        $stagesStmt = $pdo->prepare("
            SELECT pts.id, pts.task_id, pts.stage_id, pts.status, pts.started_at, pts.completed_at,
                   pts.worker_id, pts.time_spent_hours, pts.quantity_passed, pts.quantity_rejected, pts.notes,
                   ps.name as stage_name, ps.code as stage_code, ps.color as stage_color, ps.sort_order,
                   rco.operation_number, rco.name as operation_name, rco.description, rco.time_norm_hours,
                   rco.work_center, rco.equipment
            FROM production_task_stages pts
            JOIN production_stages ps ON pts.stage_id = ps.id
            LEFT JOIN route_card_operations rco ON pts.operation_id = rco.id
            WHERE pts.task_id = ?
            ORDER BY COALESCE(rco.sort_order, ps.sort_order), COALESCE(rco.operation_number, '0')
        ");
        $stagesStmt->execute([$selectedTask['id']]);
        $selectedTask['stages'] = $stagesStmt->fetchAll();
        
        // Материалы для задания - с категориями для группировки (как в паспортах)
        $materialsStmt = $pdo->prepare("
            SELECT ptm.id, ptm.task_id, ptm.material_id, ptm.quantity_required, 
                   ptm.quantity_reserved, ptm.quantity_used, ptm.unit_cost, ptm.total_cost, ptm.status as material_status,
                   m.name_full as material_name, m.name_short as material_short, m.code as material_code,
                   mu.symbol as unit_symbol,
                   COALESCE(m.current_stock, 0) as current_stock,
                   mc.name as material_category,
                   CASE 
                       WHEN COALESCE(m.current_stock, 0) >= ptm.quantity_required THEN 'sufficient'
                       WHEN COALESCE(m.current_stock, 0) > 0 THEN 'partial'
                       ELSE 'insufficient'
                   END as availability
            FROM production_tasks_materials ptm
            JOIN materials m ON ptm.material_id = m.id
            LEFT JOIN base_units mu ON m.base_unit_id = mu.id
            LEFT JOIN material_categories mc ON m.category_id = mc.id
            WHERE ptm.task_id = ?
            ORDER BY mc.name, m.name_full
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
        <div class="work-area-header">
            <div class="work-area-title">
                <h3><?= e($selectedTask['product_name']) ?></h3>
                <p class="work-area-subtitle">
                    Артикул: <?= e($selectedTask['product_article']) ?> • 
                    Заказ: <a href="<?= pageUrl('modules/orders/view.php?id=' . $selectedTask['order_id']) ?>" style="color: var(--primary-color); text-decoration: underline;" onclick="event.stopPropagation();"><?= e($selectedTask['order_number']) ?></a> • 
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
                <div class="stat-value"><?= number_format((float)$selectedTask['quantity_plan'], 0, '.', ' ') ?></div>
                <div class="stat-label">План</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--info-color);"><?= number_format((float)$selectedTask['quantity_fact'], 0, '.', ' ') ?></div>
                <div class="stat-label">Факт</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success-color);"><?= number_format((float)$selectedTask['quantity_good'], 0, '.', ' ') ?></div>
                <div class="stat-label">Годные</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--error-color);"><?= number_format((float)$selectedTask['quantity_defect'], 0, '.', ' ') ?></div>
                <div class="stat-label">Брак</div>
            </div>
        </div>
        
        <!-- Вкладки -->
        <div class="tabs-container">
            <button class="tab-button active" onclick="switchTab('stages')" data-tab="stages">Этапы</button>
            <button class="tab-button" onclick="switchTab('materials')" data-tab="materials">Материалы</button>
            <button class="tab-button" onclick="switchTab('serial')" data-tab="serial">Серийные номера</button>
            <button class="tab-button" onclick="switchTab('info')" data-tab="info">Информация</button>
        </div>
        
        <!-- Содержимое вкладок -->
        <div id="tab-stages" class="tab-content active" data-tab="stages" data-task-id="<?= $selectedTask['id'] ?>">
            <?php if (!empty($selectedTask['stages'])): ?>
                <div class="stages-grid">
                    <?php foreach ($selectedTask['stages'] as $stage): ?>
                        <div class="stage-card <?= e($stage['status']) ?>" data-stage-id="<?= $stage['id'] ?>">
                            <div class="stage-header">
                                <div class="stage-name"><?= e($stage['operation_name'] ?? $stage['stage_name']) ?></div>
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
        
        <div id="tab-materials" class="tab-content" data-tab="materials" data-task-id="<?= $selectedTask['id'] ?>">
            <?php if (!empty($selectedTask['materials'])): ?>
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">№</th>
                            <th>Артикул</th>
                            <th>Наименование</th>
                            <th>Категория</th>
                            <th>Характеристики</th>
                            <th style="width: 120px; text-align: right;">Требуется</th>
                            <th style="width: 120px; text-align: right;">Использовано</th>
                            <th style="width: 120px; text-align: right;">На складе</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($selectedTask['materials'] as $idx => $material): 
                            // Декодируем спецификации материала
                            $materialSpecs = [];
                            if (!empty($material['material_specifications'])) {
                                $decodedSpecs = json_decode($material['material_specifications'], true);
                                if (is_array($decodedSpecs)) {
                                    $materialSpecs = $decodedSpecs;
                                }
                            }
                            
                            // Основные характеристики для отображения
                            $displaySpecLabels = [
                                'grade' => 'Марка',
                                'type' => 'Стандарт',
                                'gost' => 'ГОСТ',
                                'diameter_mm' => 'Диаметр',
                                'length_m' => 'Длина',
                                'thickness_mm' => 'Толщина',
                                'width_mm' => 'Ширина',
                                'strength_class' => 'Кл. прочности',
                                'coating' => 'Покрытие',
                                'material_type' => 'Тип'
                            ];
                        ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <span class="material-code"><?= e($material['material_code']) ?></span>
                            </td>
                            <td>
                                <strong><?= e($material['material_name']) ?></strong>
                                <?php if (!empty($material['material_description'])): ?>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                                    <?= e(mb_substr($material['material_description'], 0, 60)) ?><?= mb_strlen($material['material_description']) > 60 ? '...' : '' ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--text-secondary);">
                                    <?= e($material['material_category'] ?? '—') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($materialSpecs)): ?>
                                    <?php 
                                    $shownSpecs = 0;
                                    foreach ($displaySpecLabels as $key => $label): 
                                        if (isset($materialSpecs[$key]) && !empty($materialSpecs[$key]) && $shownSpecs < 3): 
                                            $shownSpecs++;
                                    ?>
                                    <span class="material-spec-badge">
                                        <strong><?= e($label) ?>:</strong> <?= e(is_array($materialSpecs[$key]) ? implode(', ', $materialSpecs[$key]) : $materialSpecs[$key]) ?>
                                    </span>
                                    <?php 
                                        endif;
                                    endforeach;
                                    ?>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary); font-size: 13px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <strong><?= number_format($material['quantity_required'], 2) ?></strong> <?= e($material['unit_symbol']) ?>
                            </td>
                            <td style="text-align: right;">
                                <?= number_format($material['quantity_used'], 2) ?> <?= e($material['unit_symbol']) ?>
                            </td>
                            <td style="text-align: right;">
                                <?= number_format($material['current_stock'], 2) ?> <?= e($material['unit_symbol']) ?>
                            </td>
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
        
        <div id="tab-serial" class="tab-content" data-tab="serial" data-task-id="<?= $selectedTask['id'] ?>">
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
        
        <div id="tab-info" class="tab-content" data-tab="info" data-task-id="<?= $selectedTask['id'] ?>">
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

// Получение всех активных заданий с группировкой по заказам и товарам
$sql = "SELECT pt.*, 
               o.order_number, 
               o.id as order_id,
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
               pt.actual_end as actual_end,
               oi.id as order_item_id,
               oi.production_status as item_production_status,
               oi.quantity as order_item_quantity
        FROM production_tasks pt
        JOIN orders o ON pt.order_id = o.id
        LEFT JOIN order_items oi ON pt.order_item_id = oi.id
        JOIN products p ON pt.product_id = p.id
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN base_units u ON p.base_unit_id = u.id
        LEFT JOIN users u2 ON pt.responsible_id = u2.id
        LEFT JOIN users u3 ON pt.worker_id = u3.id
        WHERE pt.status IN ('planned', 'in_progress')
        " . ($selectedOrderId ? "AND o.id = " . (int)$selectedOrderId . " " : "") . "
        ORDER BY 
            o.order_number ASC,
            p.name ASC,
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

// Получение списка всех заказов для фильтра
$ordersListStmt = $pdo->query("SELECT DISTINCT o.id, o.order_number, COALESCE(c.name, 'Не указан') as customer_name, o.status,
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
                               INNER JOIN production_tasks pt ON o.id = pt.order_id
                               LEFT JOIN contractors c ON o.customer_id = c.id
                               WHERE pt.status IN ('planned', 'in_progress')
                               ORDER BY o.order_number DESC");
$ordersList = $ordersListStmt->fetchAll();

// Группировка заданий по заказам и товарам
$ordersGrouped = [];
foreach ($allTasks as $task) {
    $orderNumber = $task['order_number'];
    $orderId = $task['order_id'];
    
    if (!isset($ordersGrouped[$orderNumber])) {
        $ordersGrouped[$orderNumber] = [
            'order_number' => $orderNumber,
            'order_id' => $orderId,
            'products' => []
        ];
    }
    
    $productId = $task['product_id'];
    if (!isset($ordersGrouped[$orderNumber]['products'][$productId])) {
        $ordersGrouped[$orderNumber]['products'][$productId] = [
            'product_id' => $productId,
            'product_name' => $task['product_name'],
            'product_article' => $task['product_article'],
            'order_item_quantity' => $task['order_item_quantity'] ?? 0,
            'unit_name' => $task['unit_name'],
            'tasks' => []
        ];
    }
    
    $ordersGrouped[$orderNumber]['products'][$productId]['tasks'][] = $task;
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
               ps.name as stage_name, ps.code as stage_code, ps.color as stage_color, ps.sort_order,
               rco.operation_number, rco.name as operation_name, rco.description, rco.time_norm_hours,
               rco.work_center, rco.equipment
        FROM production_task_stages pts
        JOIN production_stages ps ON pts.stage_id = ps.id
        LEFT JOIN route_card_operations rco ON pts.operation_id = rco.id
        WHERE pts.task_id = ?
        ORDER BY COALESCE(rco.sort_order, ps.sort_order), COALESCE(rco.operation_number, '0')
    ");
    $stagesStmt->execute([$selectedTask['id']]);
    $selectedTask['stages'] = $stagesStmt->fetchAll();
    
    // Если этапы не найдены, но есть маршрутная карта у продукта, создаем их автоматически
    // Получаем route_card_id из таблицы products, если в production_tasks его нет
    $routeCardId = null;
    
    // Если в задании нет route_card_id, пытаемся найти его через продукт
    if (!empty($selectedTask['product_id'])) {
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
            grid-template-columns: 300px 350px minmax(0, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        
        /* Когда заказ не выбран - скрываем панель продуктов и расширяем рабочую область */
        .production-dashboard.no-order-selected {
            grid-template-columns: 300px minmax(0, 1fr);
        }
        
        .production-dashboard.no-order-selected #productsPanel {
            display: none !important;
        }
        
        .production-dashboard.no-order-selected #workArea {
            grid-column: 2 / -1;
            width: 100%;
            max-width: 100%;
        }
        
        .tasks-panel {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
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
        
        .order-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all var(--transition-fast);
            background: white;
        }
        
        .order-item:hover {
            background: var(--gray-50) !important;
            transform: translateX(4px);
        }
        
        .order-item.active {
            background: linear-gradient(90deg, rgba(37, 99, 235, 0.08) 0%, transparent 100%);
            border-left: 4px solid var(--primary-color);
        }
        
        .order-item-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all var(--transition-fast);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        }
        
        .order-item-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.03) 0%, var(--bg-secondary) 100%);
        }
        
        .order-item-card.active {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, var(--bg-secondary) 100%);
        }
        
        .order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .order-number-badge {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.08);
            padding: 6px 12px;
            border-radius: 8px;
        }
        
        .order-status-badge {
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .order-customer {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .order-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .order-tasks-count {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--gray-100);
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        /* Модальное окно списка заказов */
        .orders-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            animation: fadeIn 0.2s ease-in-out;
        }
        
        .orders-modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .orders-modal {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 1200px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .orders-modal-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-50);
        }
        
        .orders-modal-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .orders-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text-secondary);
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all var(--transition-fast);
        }
        
        .orders-modal-close:hover {
            background: var(--gray-200);
            color: var(--text-primary);
        }
        
        .orders-modal-filters {
            padding: 20px 32px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            background: var(--bg-secondary);
        }
        
        .orders-search-input {
            flex: 1;
            min-width: 250px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
            transition: all var(--transition-fast);
        }
        
        .orders-search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .orders-filter-select {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        
        .orders-modal-body {
            padding: 24px 32px;
            overflow-y: auto;
            flex: 1;
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--gray-50);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .orders-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .orders-table tr:hover {
            background: var(--gray-50);
        }
        
        .orders-table tr.selected {
            background: rgba(37, 99, 235, 0.08);
        }
        
        /* Стили для блока всех заказов */
        .all-orders-section {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 28px;
        }
        
        .all-orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        /* Стили для таблицы заказов */
        #allOrdersTableContainer {
            overflow-x: auto;
        }
        
        #allOrdersTableContainer table {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .order-card-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            cursor: pointer;
            transition: all var(--transition-fast);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        }
        
        .order-card-item:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.03) 0%, var(--bg-secondary) 100%);
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
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .priority-urgent { background: #fee2e2; color: #dc2626; }
        .priority-high { background: #ffedd5; color: #ea580c; }
        .priority-normal { background: #dbeafe; color: #2563eb; }
        .priority-low { background: #f1f5f9; color: #64748b; }
        
        /* Статусы заказов */
        .status-new { background: #3498db; color: white; }
        .status-processing { background: #f39c12; color: white; }
        .status-ready { background: #27ae60; color: white; }
        .status-shipped { background: #9b59b6; color: white; }
        .status-cancelled { background: #e74c3c; color: white; }
        
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
            padding: 28px;
            opacity: 1;
            pointer-events: auto;
            min-height: 650px;
            transition: all var(--transition-base);
            width: 100%;
            box-sizing: border-box;
            overflow-x: auto;
        }
        
        .work-area.loading {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Стили для рабочей области когда задание не выбрано */
        .work-area-empty {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 40px;
            min-height: 650px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            box-sizing: border-box;
        }
        
        .work-area-empty .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-in-out;
            width: 100%;
            max-width: 600px;
        }
        
        .work-area-empty .empty-state-icon {
            font-size: 72px;
            margin-bottom: 24px;
            opacity: 0.6;
            filter: grayscale(0.2);
        }
        
        .work-area-empty .empty-state h3 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 14px;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
        }
        
        .work-area-empty .empty-state p {
            font-size: 16px;
            max-width: 500px;
            line-height: 1.7;
            color: var(--text-secondary);
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
        }
        
        .work-area-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
            gap: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .work-area-title {
            flex: 1;
            min-width: 0;
            max-width: 100%;
        }
        
        .work-area-title h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
            max-width: 100%;
        }
        
        .work-area-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
            max-width: 100%;
        }
        
        .work-area-actions {
            flex-shrink: 0;
        }
        
        .tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 28px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
            position: relative;
            z-index: 10;
            flex-wrap: wrap;
            width: 100%;
            box-sizing: border-box;
        }
        
        .tab-button {
            padding: 16px 24px;
            background: transparent;
            border: none;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            position: relative;
            transition: all var(--transition-fast);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            z-index: 1;
            border-radius: 6px 6px 0 0;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .tab-button:hover {
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.06);
            z-index: 2;
        }
        
        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.1);
            z-index: 2;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
            position: relative;
            z-index: 0;
            width: 100%;
            box-sizing: border-box;
            min-width: 0;
        }
        
        .tab-content.active {
            display: block;
            z-index: 1;
            width: 100%;
            box-sizing: border-box;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            width: 100%;
            box-sizing: border-box;
            min-width: 0;
        }
        
        .stage-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 24px 18px;
            border: 1px solid var(--border-color);
            transition: all var(--transition-fast);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
            min-width: 0;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
        }
        
        .stage-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
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
            margin-bottom: 14px;
            gap: 8px;
            min-width: 0;
        }
        
        .stage-name {
            font-weight: 600;
            font-size: 15px;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            flex-shrink: 1;
            min-width: 0;
            white-space: normal;
        }
        
        .stage-status {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 500;
            white-space: nowrap;
            flex-shrink: 0;
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
        
        .materials-grouped {
            margin-bottom: 24px;
            background: #fff;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .material-category-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        
        .materials-table tbody tr:hover {
            background: var(--gray-50);
        }
        
        .materials-table tbody tr:last-child td {
            border-bottom: none;
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
            max-width: 700px;
        }
        
        .form-group {
            margin-bottom: 20px;
            background: var(--bg-secondary);
            padding: 16px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: all var(--transition-fast);
            background: #fff;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-input[readonly], .form-input[disabled] {
            background: var(--gray-50);
            color: var(--text-secondary);
            cursor: not-allowed;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            min-height: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .empty-state-icon {
            font-size: 72px;
            margin-bottom: 24px;
            opacity: 0.6;
            filter: grayscale(0.2);
        }
        
        .empty-state h3 {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
            line-height: 1.3;
        }
        
        .empty-state p {
            font-size: 15px;
            max-width: 450px;
            line-height: 1.6;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
            color: var(--text-secondary);
        }
        
        /* Стили для рабочей области когда задание не выбрано */
        .work-area-empty {
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 40px;
            min-height: 650px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            box-sizing: border-box;
        }
        
        .work-area-empty .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-in-out;
            width: 100%;
        }
        
        .work-area-empty .empty-state-icon {
            font-size: 72px;
            margin-bottom: 24px;
            opacity: 0.6;
            filter: grayscale(0.2);
        }
        
        .work-area-empty .empty-state h3 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 14px;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
        }
        
        .work-area-empty .empty-state p {
            font-size: 16px;
            max-width: 500px;
            line-height: 1.7;
            color: var(--text-secondary);
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
            white-space: normal;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .serial-numbers-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .serial-badge {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 16px;
            text-align: center;
            transition: all var(--transition-fast);
        }
        
        .serial-badge:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            transform: translateY(-2px);
        }
        
        .serial-badge strong {
            display: block;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 8px;
            word-break: break-all;
        }
        
        .serial-badge small {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #d1fae5;
            color: #059669;
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
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 20px 16px;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
            transition: all var(--transition-fast);
            min-width: 0;
            box-sizing: border-box;
        }
        
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
            border-color: var(--primary-color);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
            word-wrap: normal;
            overflow-wrap: normal;
            word-break: normal;
            hyphens: none;
        }
        
        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
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

                    <!-- Полный список всех заказов - большой блок в начале страницы -->
                    <div class="all-orders-section" style="margin-bottom: 32px;">
                        <div class="all-orders-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="font-size: 20px; font-weight: 700; color: var(--text-primary);">📋 Все активные заказы в производстве</h3>
                            <button class="btn btn-sm btn-outline" onclick="toggleAllOrdersList()" style="padding: 8px 16px; font-size: 14px;">
                                <span id="toggleAllOrdersText">Свернуть</span>
                            </button>
                        </div>
                        
                        <div id="allOrdersTableContainer">
                            <table class="orders-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 12px 16px; background: var(--gray-50); font-weight: 600; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color);">Номер заказа</th>
                                        <th style="text-align: left; padding: 12px 16px; background: var(--gray-50); font-weight: 600; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color);">Клиент</th>
                                        <th style="text-align: left; padding: 12px 16px; background: var(--gray-50); font-weight: 600; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color);">Статус</th>
                                        <th style="text-align: center; padding: 12px 16px; background: var(--gray-50); font-weight: 600; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color);">Товаров</th>
                                        <th style="text-align: center; padding: 12px 16px; background: var(--gray-50); font-weight: 600; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color);">Заданий</th>
                                        <th style="text-align: center; padding: 12px 16px; background: var(--gray-50); font-weight: 600; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color);">В работе</th>
                                        <th style="text-align: right; padding: 12px 16px; background: var(--gray-50); font-weight: 600; color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color);">Действие</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ordersList as $order): 
                                        // Считаем количество заданий для этого заказа
                                        $tasksCount = 0;
                                        $productsCount = 0;
                                        $inProgressCount = 0;
                                        foreach ($ordersGrouped as $og) {
                                            if ($og['order_id'] == $order['id']) {
                                                $productsCount = count($og['products']);
                                                foreach ($og['products'] as $product) {
                                                    foreach ($product['tasks'] as $task) {
                                                        $tasksCount += count($product['tasks']);
                                                        if ($task['task_status'] === 'in_progress') {
                                                            $inProgressCount++;
                                                        }
                                                    }
                                                    $tasksCount = count($product['tasks']);
                                                    break;
                                                }
                                                break;
                                            }
                                        }
                                        // Пересчитываем правильно
                                        $tasksCount = 0;
                                        $productsCount = 0;
                                        $inProgressCount = 0;
                                        foreach ($ordersGrouped as $og) {
                                            if ($og['order_id'] == $order['id']) {
                                                $productsCount = count($og['products']);
                                                foreach ($og['products'] as $product) {
                                                    $tasksCount += count($product['tasks']);
                                                    foreach ($product['tasks'] as $task) {
                                                        if ($task['task_status'] === 'in_progress') {
                                                            $inProgressCount++;
                                                        }
                                                    }
                                                }
                                                break;
                                            }
                                        }
                                    ?>
                                    <tr data-order-id="<?= $order['id'] ?>" 
                                        data-order-number="<?= e($order['order_number']) ?>" 
                                        data-customer="<?= e(strtolower($order['customer_name'])) ?>"
                                        data-status="<?= e($order['status']) ?>"
                                        style="<?= ($selectedOrderId == $order['id']) ? 'background: rgba(37, 99, 235, 0.08);' : '' ?>"
                                        onmouseover="this.style.background='var(--gray-50)'"
                                        onmouseout="this.style.background='<?= ($selectedOrderId == $order['id']) ? 'rgba(37, 99, 235, 0.08)' : 'transparent' ?>'">
                                        <td style="padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                            <span class="order-number-badge" style="font-size: 15px; padding: 6px 14px;"><?= e($order['order_number']) ?></span>
                                        </td>
                                        <td style="padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                            <strong style="color: var(--text-primary);"><?= !empty($order['customer_name']) ? e($order['customer_name']) : '<span style="color: var(--text-secondary);">—</span>' ?></strong>
                                        </td>
                                        <td style="padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle;">
                                            <span class="order-status-badge status-<?= e($order['status']) ?>" style="background: <?= e($order['status_color']) ?>; color: white; padding: 6px 14px; font-size: 12px; border-radius: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <?= e($order['status_name']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; text-align: center;">
                                            <span style="font-size: 16px; font-weight: 700; color: var(--primary-color);"><?= $productsCount ?></span>
                                        </td>
                                        <td style="padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; text-align: center;">
                                            <span style="font-size: 16px; font-weight: 700; color: var(--info-color);"><?= $tasksCount ?></span>
                                        </td>
                                        <td style="padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; text-align: center;">
                                            <span style="font-size: 16px; font-weight: 700; color: var(--success-color);"><?= $inProgressCount ?></span>
                                        </td>
                                        <td style="padding: 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; text-align: right;">
                                            <button class="btn btn-sm btn-outline" onclick="openOrderDetailModal(<?= $order['id'] ?>)" style="padding: 8px 16px; font-size: 13px;">
                                                ℹ️ Подробнее
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="selectOrderFromAllOrders(<?= $order['id'] ?>, '<?= e($order['order_number']) ?>')" style="padding: 8px 16px; font-size: 13px; margin-left: 8px;">
                                                📦 Открыть
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="production-dashboard<?php if (!$selectedTask): ?> no-order-selected<?php endif; ?>">
                        <!-- Модальное окно со списком всех заказов -->
                        <div class="orders-modal-overlay" id="ordersModalOverlay" onclick="if(event.target === this) closeOrdersModal()">
                            <div class="orders-modal">
                                <div class="orders-modal-header">
                                    <h2 class="orders-modal-title">📋 Выбор заказа для исполнения</h2>
                                    <button class="orders-modal-close" onclick="closeOrdersModal()">×</button>
                                </div>
                                
                                <div class="orders-modal-filters">
                                    <input type="text" 
                                           class="orders-search-input" 
                                           id="modalOrderSearch" 
                                           placeholder="Поиск по номеру заказа или клиенту..."
                                           onkeyup="filterModalOrders()">
                                    
                                    <select class="orders-filter-select" id="modalOrderStatusFilter" onchange="filterModalOrders()">
                                        <option value="">Все статусы</option>
                                        <option value="new">Новый</option>
                                        <option value="processing">В работе</option>
                                        <option value="ready">Готов</option>
                                        <option value="shipped">Отгружен</option>
                                        <option value="cancelled">Отменен</option>
                                    </select>
                                </div>
                                
                                <div class="orders-modal-body">
                                    <table class="orders-table">
                                        <thead>
                                            <tr>
                                                <th>Номер заказа</th>
                                                <th>Клиент</th>
                                                <th>Статус</th>
                                                <th>Заданий</th>
                                                <th>Действие</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modalOrdersTableBody">
                                            <?php foreach ($ordersList as $order): 
                                                // Считаем количество заданий для этого заказа
                                                $tasksCount = 0;
                                                foreach ($ordersGrouped as $og) {
                                                    if ($og['order_id'] == $order['id']) {
                                                        foreach ($og['products'] as $product) {
                                                            $tasksCount += count($product['tasks']);
                                                        }
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <tr data-order-id="<?= $order['id'] ?>" 
                                                data-order-number="<?= e($order['order_number']) ?>" 
                                                data-customer="<?= e(strtolower($order['customer_name'])) ?>"
                                                data-status="<?= e($order['status']) ?>"
                                                class="<?= ($selectedOrderId == $order['id']) ? 'selected' : '' ?>">
                                                <td>
                                                    <span class="order-number-badge"><?= e($order['order_number']) ?></span>
                                                </td>
                                                <td><?= !empty($order['customer_name']) ? e($order['customer_name']) : '<span style="color: var(--text-secondary);">—</span>' ?></td>
                                                <td>
                                                    <span class="order-status-badge status-<?= e($order['status']) ?>" style="background: <?= e($order['status_color']) ?>; color: white;">
                                                        <?= e($order['status_name']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="order-tasks-count">
                                                        📦 <?= $tasksCount ?> зад.
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="selectOrderFromModal(<?= $order['id'] ?>, '<?= e($order['order_number']) ?>')">
                                                        Выбрать
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Панель списка заказов с фильтрами -->
                        <div class="tasks-panel">
                            <!-- Фильтры заказов -->
                            <div style="padding: 20px; border-bottom: 1px solid var(--border-color); background: var(--gray-50);">
                                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">📋 Выбор заказа</h3>
                                
                                <!-- Поиск по заказам -->
                                <div style="margin-bottom: 12px;">
                                    <input type="text" id="orderSearch" placeholder="Поиск заказа..." 
                                           style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;"
                                           onkeyup="filterOrders()">
                                </div>
                                
                                <!-- Фильтр по статусу -->
                                <div style="margin-bottom: 12px;">
                                    <select id="orderStatusFilter" 
                                            style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;"
                                            onchange="filterOrders()">
                                        <option value="">Все статусы</option>
                                        <option value="new">Новый</option>
                                        <option value="in_progress">В работе</option>
                                        <option value="completed">Завершен</option>
                                        <option value="cancelled">Отменен</option>
                                    </select>
                                </div>
                                
                                <div style="font-size: 13px; color: var(--text-secondary);">
                                    <strong><?= count($ordersList) ?></strong> заказов с активными заданиями
                                </div>
                            </div>
                            
                            <!-- Список заказов -->
                            <div class="tasks-list" id="ordersListContainer">
                                <?php if (empty($ordersList)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📋</div>
                                        <h4>Нет активных заказов</h4>
                                        <p style="font-size: 13px;">Все заказы выполнены или отсутствуют</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($ordersList as $order): ?>
                                        <div class="order-item" data-order-id="<?= $order['id'] ?>" data-order-number="<?= e($order['order_number']) ?>" data-order-status="<?= e($order['status']) ?>"
                                             onclick="selectOrder(<?= $order['id'] ?>, '<?= e($order['order_number']) ?>')"
                                             style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: all var(--transition-fast);"
                                             onmouseover="this.style.background='var(--gray-50)'" 
                                             onmouseout="this.style.background='white'">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <strong style="color: var(--primary-color); font-size: 15px;">Заказ <?= e($order['order_number']) ?></strong>
                                                <span class="task-priority status-<?= e($order['status']) ?>"><?= e($order['status_name'] ?? $order['status'] ?? 'В работе') ?></span>
                                            </div>
                                            <?php if (!empty($order['customer_name'])): ?>
                                                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px;">
                                                    👤 <?= e($order['customer_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                Нажмите для просмотра товаров и заданий
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Панель товаров выбранного заказа -->
                        <div class="tasks-panel" id="productsPanel" style="display: none;">
                            <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3 style="font-size: 16px; font-weight: 600;" id="selectedOrderTitle">Товары заказа</h3>
                                    <p style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;" id="selectedOrderSubtitle"></p>
                                </div>
                                <button onclick="closeProductsPanel()" style="background: none; border: none; cursor: pointer; font-size: 20px; color: var(--text-secondary);">✕</button>
                            </div>
                            
                            <div class="tasks-list" id="productsListContainer">
                                <!-- Товары будут загружены здесь через JS -->
                            </div>
                        </div>
                        
                        <!-- Рабочая область -->
                        <div class="work-area" id="workArea">
                            <?php if ($selectedTask): ?>
                                <div class="work-area-header">
                                    <div class="work-area-title">
                                        <h3><?= e($selectedTask['product_name']) ?></h3>
                                        <p class="work-area-subtitle">
                                            Артикул: <?= e($selectedTask['product_article']) ?> • 
                                            Заказ: <a href="<?= pageUrl('modules/orders/view.php?id=' . $selectedTask['order_id']) ?>" style="color: var(--primary-color); text-decoration: underline;"><?= e($selectedTask['order_number']) ?></a> • 
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
                                        <div class="stat-value"><?= number_format((float)$selectedTask['quantity_plan'], 0, '.', ' ') ?></div>
                                        <div class="stat-label">План</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value" style="color: var(--info-color);"><?= number_format((float)$selectedTask['quantity_fact'], 0, '.', ' ') ?></div>
                                        <div class="stat-label">Факт</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value" style="color: var(--success-color);"><?= number_format((float)$selectedTask['quantity_good'], 0, '.', ' ') ?></div>
                                        <div class="stat-label">Годные</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value" style="color: var(--danger-color);"><?= number_format((float)$selectedTask['quantity_defect'], 0, '.', ' ') ?></div>
                                        <div class="stat-label">Брак</div>
                                    </div>
                                </div>
                                
                                <div class="tabs-container">
                                    <button class="tab-button active" onclick="switchTab('stages')" data-tab="stages">Этапы</button>
                                    <button class="tab-button" onclick="switchTab('materials')" data-tab="materials">Материалы</button>
                                    <button class="tab-button" onclick="switchTab('serial')" data-tab="serial">Серийные номера</button>
                                    <button class="tab-button" onclick="switchTab('info')" data-tab="info">Информация</button>
                                </div>
                                
                                <!-- Вкладка этапы -->
                                <div class="tab-content active" id="tab-stages" data-tab="stages" data-task-id="<?= $selectedTask['id'] ?>">
                                    <h4 style="margin-bottom: 16px;">Этапы производства</h4>
                                    <?php if (!empty($selectedTask['stages'])): ?>
                                    <div class="stages-grid">
                                        <?php foreach ($selectedTask['stages'] as $stage): ?>
                                            <div class="stage-card <?= $stage['status'] ?>">
                                                <div class="stage-header">
                                                    <span class="stage-name"><?= e($stage['operation_name'] ?? $stage['stage_name']) ?></span>
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
                                <div class="tab-content" id="tab-materials" data-tab="materials" data-task-id="<?= $selectedTask['id'] ?>">
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
                                <div class="tab-content" id="tab-serial" data-tab="serial" data-task-id="<?= $selectedTask['id'] ?>">
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
                                <div class="tab-content" id="tab-info" data-tab="info" data-task-id="<?= $selectedTask['id'] ?>">
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
                                <!-- Когда задание не выбрано - показываем красивое пустое состояние с вкладками -->
                                <div class="work-area-header">
                                    <div class="work-area-title">
                                        <h3>Производственное задание не выбрано</h3>
                                        <p class="work-area-subtitle">
                                            Выберите задание из списка слева для просмотра информации
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="tabs-container">
                                    <button class="tab-button active" onclick="switchTab('stages')" data-tab="stages">Этапы</button>
                                    <button class="tab-button" onclick="switchTab('materials')" data-tab="materials">Материалы</button>
                                    <button class="tab-button" onclick="switchTab('serial')" data-tab="serial">Серийные номера</button>
                                    <button class="tab-button" onclick="switchTab('info')" data-tab="info">Информация</button>
                                </div>
                                
                                <!-- Пустое состояние для вкладки Этапы -->
                                <div class="tab-content active" id="tab-stages" data-tab="stages" data-task-id="">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📋</div>
                                        <h3>Этапы производства</h3>
                                        <p>Выберите производственное задание из списка слева чтобы увидеть этапы производства</p>
                                    </div>
                                </div>
                                
                                <!-- Пустое состояние для вкладки Материалы -->
                                <div class="tab-content" id="tab-materials" data-tab="materials" data-task-id="">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📦</div>
                                        <h3>Материалы</h3>
                                        <p>Выберите производственное задание из списка слева чтобы увидеть список необходимых материалов</p>
                                    </div>
                                </div>
                                
                                <!-- Пустое состояние для вкладки Серийные номера -->
                                <div class="tab-content" id="tab-serial" data-tab="serial" data-task-id="">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🏷️</div>
                                        <h3>Серийные номера</h3>
                                        <p>Выберите производственное задание из списка слева чтобы управлять серийными номерами</p>
                                    </div>
                                </div>
                                
                                <!-- Пустое состояние для вкладки Информация -->
                                <div class="tab-content" id="tab-info" data-tab="info" data-task-id="">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">ℹ️</div>
                                        <h3>Информация о задании</h3>
                                        <p>Выберите производственное задание из списка слева чтобы просмотреть подробную информацию</p>
                                    </div>
                                </div>
                            <?php endif; ?>
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
        // Делаем функцию switchTab глобальной для доступа из onclick
        window.switchTab = function(tabName) {
            console.log('Переключение на вкладку:', tabName);

            // Работаем только с элементами внутри #workArea
            const workArea = document.getElementById('workArea');
            if (!workArea) {
                console.error('Рабочая область #workArea не найдена');
                return;
            }

            // Скрываем ВСЕ вкладки только в рабочей области
            const allTabs = workArea.querySelectorAll('.tab-content');
            console.log('Найдено вкладок для скрытия:', allTabs.length);
            
            allTabs.forEach(function(tab) {
                tab.classList.remove('active');
                tab.style.display = 'none';
                console.log('Скрыта вкладка:', tab.id);
            });

            // Находим кнопку которая соответствует этой вкладке и делаем её активной (только в workArea)
            const buttons = workArea.querySelectorAll('.tab-button');
            buttons.forEach(function(btn) {
                btn.classList.remove('active');
                const dataTab = btn.getAttribute('data-tab');
                if (dataTab === tabName) {
                    btn.classList.add('active');
                    console.log('Активирована кнопка:', dataTab);
                }
            });

            // Показываем выбранную вкладку - ищем по ID
            const targetTabContent = document.getElementById('tab-' + tabName);
            if (targetTabContent && workArea.contains(targetTabContent)) {
                console.log('Показываем вкладку:', targetTabContent.id);
                targetTabContent.classList.add('active');
                targetTabContent.style.display = 'block';
                console.log('Вкладка ' + targetTabContent.id + ' активирована');
            } else {
                console.error('Не удалось найти вкладку #tab-' + tabName);
            }
        };

        let currentTaskId = <?= $selectedTask ? $selectedTask['id'] : 0 ?>;
        let currentOrderId = null;
        
        // Открытие модального окна заказов
        function openOrdersModal() {
            document.getElementById('ordersModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        // Закрытие модального окна заказов
        function closeOrdersModal() {
            document.getElementById('ordersModalOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Выбор заказа из модального окна
        function selectOrderFromModal(orderId, orderNumber) {
            closeOrdersModal();
            selectOrder(orderId, orderNumber);
            
            // Подсветка выбранного заказа в таблице
            document.querySelectorAll('#modalOrdersTableBody tr').forEach(row => {
                row.classList.remove('selected');
            });
            const selectedRow = document.querySelector(`#modalOrdersTableBody tr[data-order-id="${orderId}"]`);
            if (selectedRow) {
                selectedRow.classList.add('selected');
            }
        }
        
        // Фильтрация заказов в модальном окне
        function filterModalOrders() {
            const searchText = document.getElementById('modalOrderSearch').value.toLowerCase();
            const statusFilter = document.getElementById('modalOrderStatusFilter').value;
            
            document.querySelectorAll('#modalOrdersTableBody tr').forEach(row => {
                const orderNumber = row.getAttribute('data-order-number').toLowerCase();
                const customer = row.getAttribute('data-customer');
                const status = row.getAttribute('data-status');
                
                const matchesSearch = orderNumber.includes(searchText) || (customer && customer.includes(searchText));
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Фильтрация заказов в таблице в начале страницы
        function filterAllOrdersTable() {
            const searchText = document.getElementById('allOrdersSearch').value.toLowerCase();
            const statusFilter = document.getElementById('allOrdersStatusFilter').value;
            
            document.querySelectorAll('#allOrdersTableContainer tbody tr').forEach(row => {
                const orderNumber = row.getAttribute('data-order-number').toLowerCase();
                const customer = row.getAttribute('data-customer');
                const status = row.getAttribute('data-status');
                
                const matchesSearch = orderNumber.includes(searchText) || (customer && customer.includes(searchText));
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Фильтрация заказов в боковой панели
        function filterOrders() {
            const searchText = document.getElementById('orderSearch').value.toLowerCase();
            const statusFilter = document.getElementById('orderStatusFilter').value;
            
            document.querySelectorAll('.order-item').forEach(item => {
                const orderNumber = item.getAttribute('data-order-number').toLowerCase();
                const orderStatus = item.getAttribute('data-order-status');
                
                const matchesSearch = orderNumber.includes(searchText);
                const matchesStatus = !statusFilter || orderStatus === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // Выбор заказа из блока всех заказов в начале страницы
        function selectOrderFromAllOrders(orderId, orderNumber) {
            // Прокрутка к панели управления и выбор заказа
            selectOrder(orderId, orderNumber);
            
            // Подсветка выбранного заказа в таблице
            document.querySelectorAll('#allOrdersTableContainer tbody tr').forEach(row => {
                row.style.background = '';
            });
            const selectedRow = document.querySelector(`#allOrdersTableContainer tbody tr[data-order-id="${orderId}"]`);
            if (selectedRow) {
                selectedRow.style.background = 'rgba(37, 99, 235, 0.08)';
            }
            
            // Плавная прокрутка к рабочей области
            setTimeout(() => {
                const workArea = document.getElementById('workArea');
                if (workArea) {
                    workArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 300);
        }
        
        // Сворачивание/разворачивание списка всех заказов
        function toggleAllOrdersList() {
            const container = document.getElementById('allOrdersTableContainer');
            const toggleText = document.getElementById('toggleAllOrdersText');
            
            if (container && container.style.display === 'none') {
                container.style.display = 'block';
                toggleText.textContent = 'Свернуть';
            } else if (container) {
                container.style.display = 'none';
                toggleText.textContent = 'Развернуть';
            }
        }
        
        // Выбор заказа
        function selectOrder(orderId, orderNumber) {
            currentOrderId = orderId;
            
            // Убираем класс no-order-selected когда заказ выбран
            const dashboard = document.querySelector('.production-dashboard');
            if (dashboard) {
                dashboard.classList.remove('no-order-selected');
            }
            
            // Показываем панель товаров
            document.getElementById('productsPanel').style.display = 'block';
            document.getElementById('selectedOrderTitle').textContent = 'Товары заказа ' + orderNumber;
            document.getElementById('selectedOrderSubtitle').textContent = 'Заказ #' + orderId;
            
            // Загружаем товары заказа через AJAX
            fetch('?ajax=1&order=' + orderId, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('productsListContainer');
                
                if (data.products && data.products.length > 0) {
                    let html = '';
                    
                    data.products.forEach(product => {
                        // Находим первое активное или первое доступное задание для авто-выбора
                        let firstTaskId = null;
                        if (product.tasks && product.tasks.length > 0) {
                            const activeTask = product.tasks.find(t => t.id == currentTaskId);
                            if (activeTask) {
                                firstTaskId = activeTask.id;
                            } else {
                                firstTaskId = product.tasks[0].id;
                            }
                        }
                        
                        html += `
                            <div class="product-group" style="border-bottom: 1px solid var(--border-color);">
                                <div class="product-header" onclick="selectFirstTask(${firstTaskId})" 
                                     style="padding: 16px 20px; background: #f9fafb; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div>
                                            <div style="font-weight: 500; color: var(--text-primary);">${product.product_name}</div>
                                            <div style="font-size: 11px; color: var(--text-secondary);">Арт. ${product.product_article} • План: ${product.order_item_quantity} ${product.unit_name}</div>
                                        </div>
                                    </div>
                                    <span style="font-size: 11px; color: var(--primary-color); font-weight: 500;">▶ Открыть</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">📦</div>
                            <h4>Товары не найдены</h4>
                            <p style="font-size: 13px;">В этом заказе нет товаров с производственными заданиями</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки товаров:', error);
                container.innerHTML = '<div style="padding: 20px; color: var(--error-color);">Ошибка загрузки товаров</div>';
            });
        }
        
        // Закрытие панели товаров
        function closeProductsPanel() {
            document.getElementById('productsPanel').style.display = 'none';
            currentOrderId = null;
            
            // Добавляем класс no-order-selected когда заказ закрыт
            const dashboard = document.querySelector('.production-dashboard');
            if (dashboard) {
                dashboard.classList.add('no-order-selected');
            }
        }
        
        // Выбор первого задания при клике на товар
        function selectFirstTask(taskId) {
            if (!taskId) return;
            selectTask(taskId);
        }
        
        // Выбор задачи без перезагрузки страницы (через AJAX)
        // Сворачивание/разворачивание группы заказа
        function toggleOrderGroup(orderId) {
            const group = document.getElementById(orderId);
            const icon = document.getElementById('icon-' + orderId);
            if (group && icon) {
                if (group.style.display === 'none') {
                    group.style.display = 'block';
                    icon.textContent = '▼';
                } else {
                    group.style.display = 'none';
                    icon.textContent = '▶';
                }
            }
        }

        // Сворачивание/разворачивание группы товара
        function toggleProductGroup(productId) {
            const group = document.getElementById(productId);
            const icon = document.getElementById('icon-' + productId);
            if (group && icon) {
                if (group.style.display === 'none') {
                    group.style.display = 'block';
                    icon.textContent = '▼';
                } else {
                    group.style.display = 'none';
                    icon.textContent = '▶';
                }
            }
        }

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
            
            // Загружаем данные задачи через AJAX
            fetch('?task=' + taskId + '&ajax=1', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                // Находим контейнер рабочей области и обновляем его содержимое
                const workArea = document.getElementById('workArea');
                
                if (workArea) {
                    // Полностью заменяем innerHTML содержимого workArea
                    // Это гарантирует, что все старые обработчики событий будут удалены
                    workArea.innerHTML = html;
                    
                    // Обновляем URL без перезагрузки
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?task=' + taskId;
                    window.history.pushState({taskId: taskId}, '', newUrl);
                    
                    currentTaskId = taskId;
                    
                    // Инициализируем вкладки заново - даем DOM время на обновление
                    setTimeout(function() {
                        // Принудительно скрываем все вкладки перед инициализацией
                        const allTabs = workArea.querySelectorAll('.tab-content');
                        const allButtons = workArea.querySelectorAll('.tab-button');
                        allTabs.forEach(tab => {
                            tab.classList.remove('active');
                            tab.style.display = 'none';
                        });
                        allButtons.forEach(btn => btn.classList.remove('active'));

                        // Активируем первую вкладку (Этапы)
                        const firstTabButton = workArea.querySelector('.tab-button[data-tab="stages"]');
                        const firstTabContent = workArea.querySelector('#tab-stages');
                        if (firstTabButton && firstTabContent) {
                            firstTabButton.classList.add('active');
                            firstTabContent.classList.add('active');
                            firstTabContent.style.display = 'block';
                        }

                        initTabs();

                        console.log('Вкладки инициализированы для задачи #' + taskId);
                    }, 50);
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
            console.log('Инициализация вкладок...');
            
            // Работаем только с кнопками внутри workArea
            const tabButtons = document.querySelectorAll('#workArea .tab-button');
            console.log('Найдено кнопок вкладок:', tabButtons.length);
            
            if (tabButtons.length === 0) {
                console.warn('Кнопки вкладок не найдены в #workArea');
                return;
            }
            
            tabButtons.forEach((btn) => {
                // Удаляем все существующие обработчики click через cloneNode (если они есть)
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
            });
            
            // Активируем первую вкладку (Этапы) по умолчанию при инициализации
            const firstTabButton = document.querySelector('#workArea .tab-button[data-tab="stages"]');
            const hasActiveContent = document.querySelector('#workArea .tab-content.active');
            if (firstTabButton && !hasActiveContent) {
                console.log('Активация вкладки "Этапы" по умолчанию');
                switchTab('stages');
            }
        }
        
        // Вызываем после загрузки DOM
        document.addEventListener('DOMContentLoaded', function() {
            // Если заказ выбран через GET параметр, автоматически загружаем его
            <?php if ($selectedOrderId): ?>
            selectOrder(<?= $selectedOrderId ?>, 'Заказ #<?= $selectedOrderId ?>');
            <?php else: ?>
            // Добавляем класс no-order-selected при загрузке если заказ не выбран
            const dashboard = document.querySelector('.production-dashboard');
            if (dashboard && !currentOrderId) {
                dashboard.classList.add('no-order-selected');
            }
            <?php endif; ?>
            
            // Небольшая задержка для гарантии полной загрузки DOM
            setTimeout(initTabs, 10);
        });
        
        // Также вызываем сразу при загрузке скрипта (для случаев, когда DOM уже готов)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(initTabs, 10);
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
        
        // Открытие модального окна с подробной информацией о заказе
        function openOrderDetailModal(orderId) {
            // Получаем информацию о заказе через AJAX
            fetch('api_execute.php?action=get_order_details&order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        const html = `
                            <div class="order-detail-modal-overlay" id="orderDetailModalOverlay" onclick="if(event.target === this) closeOrderDetailModal()">
                                <div class="order-detail-modal">
                                    <div class="order-detail-modal-header">
                                        <h2>📦 Заказ ${order.order_number}</h2>
                                        <button class="order-detail-modal-close" onclick="closeOrderDetailModal()">×</button>
                                    </div>
                                    <div class="order-detail-modal-body">
                                        <div class="order-info-section">
                                            <h3>Основная информация</h3>
                                            <div class="order-info-grid">
                                                <div class="order-info-item">
                                                    <span class="order-info-label">Клиент:</span>
                                                    <span class="order-info-value">${order.customer_name || '—'}</span>
                                                </div>
                                                <div class="order-info-item">
                                                    <span class="order-info-label">Статус:</span>
                                                    <span class="order-info-value" style="color: ${order.status_color}; font-weight: 600;">${order.status_name}</span>
                                                </div>
                                                <div class="order-info-item">
                                                    <span class="order-info-label">Дата создания:</span>
                                                    <span class="order-info-value">${order.created_at || '—'}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="order-info-section">
                                            <h3>Товары в заказе</h3>
                                            <table class="order-items-table">
                                                <thead>
                                                    <tr>
                                                        <th>Продукция</th>
                                                        <th>Артикул</th>
                                                        <th>Количество</th>
                                                        <th>Ед. изм.</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${order.items.map(item => `
                                                        <tr>
                                                            <td>${item.product_name}</td>
                                                            <td>${item.article || '—'}</td>
                                                            <td>${item.quantity}</td>
                                                            <td>${item.unit_name || '—'}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="order-info-section">
                                            <h3>Производственные задания</h3>
                                            <table class="order-tasks-table">
                                                <thead>
                                                    <tr>
                                                        <th>Задание</th>
                                                        <th>Продукция</th>
                                                        <th>План</th>
                                                        <th>Факт</th>
                                                        <th>Статус</th>
                                                        <th>Материалы</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${order.tasks.map(task => `
                                                        <tr>
                                                            <td>#${task.id}</td>
                                                            <td>${task.product_name}</td>
                                                            <td>${task.quantity_plan}</td>
                                                            <td>${task.quantity_fact || 0}</td>
                                                            <td><span class="task-status-badge status-${task.status}">${task.status_name}</span></td>
                                                            <td>
                                                                <ul class="materials-list">
                                                                    ${task.materials ? task.materials.map(m => `
                                                                        <li>${m.material_name}: ${m.quantity_required} ${m.unit_symbol}</li>
                                                                    `).join('') : '<li>—</li>'}
                                                                </ul>
                                                            </td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="order-detail-modal-footer">
                                        <button class="btn btn-outline" onclick="closeOrderDetailModal()">Закрыть</button>
                                        <button class="btn btn-primary" onclick="selectOrderFromDetailModal(${order.id}, '${order.order_number}')">Перейти к исполнению</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        document.body.insertAdjacentHTML('beforeend', html);
                        document.getElementById('orderDetailModalOverlay').classList.add('active');
                        document.body.style.overflow = 'hidden';
                    } else {
                        showNotification(data.error || 'Ошибка получения данных', 'error');
                    }
                })
                .catch(err => {
                    showNotification('Ошибка сети', 'error');
                });
        }
        
        // Закрытие модального окна с деталями заказа
        function closeOrderDetailModal() {
            const overlay = document.getElementById('orderDetailModalOverlay');
            if (overlay) {
                overlay.remove();
                document.body.style.overflow = '';
            }
        }
        
        // Выбор заказа из модального окна с деталями
        function selectOrderFromDetailModal(orderId, orderNumber) {
            closeOrderDetailModal();
            selectOrderFromAllOrders(orderId, orderNumber);
        }
    </script>
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
