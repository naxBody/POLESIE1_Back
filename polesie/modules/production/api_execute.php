<?php
/**
 * API для исполнения производства
 * Обработка действий: начало/завершение этапов, списание материалов, завершение производства
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Проверяем, это API запрос или обычная страница
// API запросом считаем только явные запросы с action или api=1
$isApiRequest = isset($_POST['action']) || 
                (isset($_GET['api']) && $_GET['api'] == '1') ||
                (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
                 (isset($_POST['action']) || isset($_GET['task_id'])));

if ($isApiRequest) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Не устанавливаем заголовок Content-Type для GET запросов с task_id
    // чтобы можно было использовать этот файл и для обычного рендеринга
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['task_id'])) {
        header('Content-Type: application/json');
    }
    
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Необходима авторизация']);
        exit;
    }
    
    $user = getCurrentUser();
    $pdo = getDbConnection();
    
    // Обработка GET запросов для получения данных задания (не требует action)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['task_id'])) {
        try {
            $taskId = (int)$_GET['task_id'];
            
            // Получаем информацию о задании
            $stmt = $pdo->prepare("
                SELECT pt.*, p.name as product_name, p.article, o.order_number
                FROM production_tasks pt
                JOIN orders o ON pt.order_id = o.id
                JOIN products p ON pt.product_id = p.id
                WHERE pt.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                echo json_encode(['error' => 'Задание не найдено']);
                exit;
            }
            
            // Получаем этапы
            $stmt = $pdo->prepare("
                SELECT pts.*, ps.name as stage_name, ps.code as stage_code, ps.color as stage_color, ps.sort_order
                FROM production_task_stages pts
                JOIN production_stages ps ON pts.stage_id = ps.id
                WHERE pts.task_id = ?
                ORDER BY ps.sort_order
            ");
            $stmt->execute([$taskId]);
            $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Получаем материалы
            $stmt = $pdo->prepare("
                SELECT ptm.*, m.name_full as material_name, m.current_stock
                FROM production_tasks_materials ptm
                JOIN materials m ON ptm.material_id = m.id
                WHERE ptm.task_id = ?
                ORDER BY ptm.id
            ");
            $stmt->execute([$taskId]);
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'task' => $task,
                'stages' => $stages,
                'materials' => $materials
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? ($_POST['action'] ?? '');
        
        if (!$action) {
            throw new Exception('Не указано действие');
        }
        
        // Проверка прав
        if (!hasPermission('production.edit') && !hasPermission('production.execute')) {
            throw new Exception('Нет прав доступа к исполнению производства');
        }
        
        if ($action === 'start_stage') {
            // Начало этапа производства
            $stageId = (int)($input['stage_id'] ?? 0);
            $taskId = (int)($input['task_id'] ?? 0);
            
            if (!$stageId || !$taskId) {
                throw new Exception('Не указаны ID этапа или задания');
            }
            
            // Проверяем что этап принадлежит заданию
            $checkStmt = $pdo->prepare("
                SELECT pts.*, pt.status as task_status
                FROM production_task_stages pts
                JOIN production_tasks pt ON pts.task_id = pt.id
                WHERE pts.id = ? AND pts.task_id = ?
            ");
        $checkStmt->execute([$stageId, $taskId]);
        $stage = $checkStmt->fetch();
        
        if (!$stage) {
            throw new Exception('Этап не найден или не принадлежит заданию');
        }
        
        if ($stage['status'] !== 'pending') {
            throw new Exception('Этап уже начат или завершен');
        }
        
        // Обновляем статус этапа
        $updateStmt = $pdo->prepare("
            UPDATE production_task_stages
            SET status = 'in_progress',
                started_at = NOW(),
                worker_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$user['id'], $stageId]);
        
        // Если задание еще не в работе, переводим его в этот статус
        if ($stage['task_status'] === 'planned') {
            $taskUpdateStmt = $pdo->prepare("
                UPDATE production_tasks
                SET status = 'in_progress',
                    actual_start = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $taskUpdateStmt->execute([$taskId]);
        }
        
        // Резервируем материалы если еще не зарезервированы
        reserveMaterialsForTask($pdo, $taskId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Этап начат',
            'stage_id' => $stageId
        ]);
        
    } elseif ($action === 'complete_stage') {
        // Завершение этапа производства
        $stageId = (int)($input['stage_id'] ?? 0);
        $taskId = (int)($input['task_id'] ?? 0);
        
        if (!$stageId || !$taskId) {
            throw new Exception('Не указаны ID этапа или задания');
        }
        
        // Проверяем что этап принадлежит заданию и находится в работе
        $checkStmt = $pdo->prepare("
            SELECT pts.*, pt.quantity_plan
            FROM production_task_stages pts
            JOIN production_tasks pt ON pts.task_id = pt.id
            WHERE pts.id = ? AND pts.task_id = ?
        ");
        $checkStmt->execute([$stageId, $taskId]);
        $stage = $checkStmt->fetch();
        
        if (!$stage) {
            throw new Exception('Этап не найден или не принадлежит заданию');
        }
        
        if ($stage['status'] !== 'in_progress') {
            throw new Exception('Этап не находится в работе');
        }
        
        // Используем плановое количество как прошедшее, если не указано иное
        $quantityPassed = isset($input['quantity_passed']) ? floatval($input['quantity_passed']) : floatval($stage['quantity_plan']);
        $quantityRejected = isset($input['quantity_rejected']) ? floatval($input['quantity_rejected']) : 0;
        
        // Обновляем статус этапа
        $updateStmt = $pdo->prepare("
            UPDATE production_task_stages
            SET status = 'completed',
                completed_at = NOW(),
                quantity_passed = ?,
                quantity_rejected = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$quantityPassed, $quantityRejected, $stageId]);
        
        // Проверяем все ли этапы завершены
        $allCompletedStmt = $pdo->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'completed' OR status = 'skipped' THEN 1 ELSE 0 END) as completed
            FROM production_task_stages
            WHERE task_id = ?
        ");
        $allCompletedStmt->execute([$taskId]);
        $stagesCount = $allCompletedStmt->fetch();
        
        if ($stagesCount['total'] == $stagesCount['completed']) {
            // Все этапы завершены - завершаем задание
            $taskCompleteStmt = $pdo->prepare("
                UPDATE production_tasks
                SET status = 'completed',
                    actual_end = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $taskCompleteStmt->execute([$taskId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Этап завершен. Производственное задание выполнено!',
                'task_completed' => true
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Этап завершен',
                'task_completed' => false
            ]);
        }
        
    } elseif ($action === 'consume_materials') {
        // Списание материалов для задания
        $taskId = (int)($input['task_id'] ?? 0);
        
        if (!$taskId) {
            throw new Exception('Не указан ID задания');
        }
        
        // Получаем материалы задания
        $materialsStmt = $pdo->prepare("
            SELECT ptm.id, ptm.material_id, ptm.quantity_required, ptm.quantity_used,
                   m.current_stock, m.name_full
            FROM production_tasks_materials ptm
            JOIN materials m ON ptm.material_id = m.id
            WHERE ptm.task_id = ? AND ptm.status IN ('reserved', 'issued')
        ");
        $materialsStmt->execute([$taskId]);
        $materials = $materialsStmt->fetchAll();
        
        if (empty($materials)) {
            throw new Exception('Нет материалов для списания');
        }
        
        $pdo->beginTransaction();
        
        try {
            foreach ($materials as $mat) {
                $quantityToConsume = $mat['quantity_required'] - $mat['quantity_used'];
                
                if ($quantityToConsume <= 0) {
                    continue;
                }
                
                // Проверяем наличие на складе
                if ($mat['current_stock'] < $quantityToConsume) {
                    throw new Exception("Недостаточно материала '{$mat['name_full']}' на складе");
                }
                
                // Списываем со склада
                $stockUpdateStmt = $pdo->prepare("
                    UPDATE materials
                    SET current_stock = current_stock - ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stockUpdateStmt->execute([$quantityToConsume, $mat['material_id']]);
                
                // Обновляем статус материала в задании
                $materialUpdateStmt = $pdo->prepare("
                    UPDATE production_tasks_materials
                    SET quantity_used = quantity_used + ?,
                        status = 'consumed',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $materialUpdateStmt->execute([$quantityToConsume, $mat['id']]);
                
                // Логируем движение материала
                $logStmt = $pdo->prepare("
                    INSERT INTO material_movements 
                    (material_id, movement_type, quantity, reference_type, reference_id, user_id, created_at)
                    VALUES (?, 'consumption', ?, 'production_task', ?, ?, NOW())
                ");
                $logStmt->execute([$mat['material_id'], -$quantityToConsume, $taskId, $user['id']]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Материалы успешно списаны'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'create_serial') {
        // Создание серийного номера
        $taskId = (int)($input['task_id'] ?? 0);
        $productId = (int)($input['product_id'] ?? 0);
        $serialNumber = trim($input['serial_number'] ?? '');
        
        if (!$taskId || !$productId || !$serialNumber) {
            throw new Exception('Не указаны параметры');
        }
        
        // Проверяем уникальность
        $checkStmt = $pdo->prepare("SELECT id FROM product_serial_numbers WHERE serial_number = ?");
        $checkStmt->execute([$serialNumber]);
        if ($checkStmt->fetch()) {
            throw new Exception('Серийный номер уже существует');
        }
        
        // Создаем серийный номер
        $insertStmt = $pdo->prepare("
            INSERT INTO product_serial_numbers 
            (serial_number, product_id, task_id, status, created_at)
            VALUES (?, ?, ?, 'active', NOW())
        ");
        $insertStmt->execute([$serialNumber, $productId, $taskId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Серийный номер создан',
            'serial_number' => $serialNumber
        ]);
        
    } elseif ($action === 'complete_production') {
        // Завершение производства с указанием количества
        $taskId = (int)($input['task_id'] ?? 0);
        $quantityGood = floatval($input['quantity_good'] ?? 0);
        $quantityDefect = floatval($input['quantity_defect'] ?? 0);
        $comment = trim($input['comment'] ?? '');
        $autoSerial = !empty($input['auto_serial']);
        
        if (!$taskId) {
            throw new Exception('Не указан ID задания');
        }
        
        if ($quantityGood <= 0 && $quantityDefect <= 0) {
            throw new Exception('Укажите количество продукции');
        }
        
        // Получаем данные о задании
        $taskStmt = $pdo->prepare("
            SELECT pt.*, p.id as product_id
            FROM production_tasks pt
            JOIN products p ON pt.product_id = p.id
            WHERE pt.id = ?
        ");
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch();
        
        if (!$task) {
            throw new Exception('Задание не найдено');
        }
        
        $pdo->beginTransaction();
        
        try {
            // Обновляем задание
            $totalQuantity = $quantityGood + $quantityDefect;
            $updateStmt = $pdo->prepare("
                UPDATE production_tasks
                SET status = 'completed',
                    quantity_fact = ?,
                    quantity_good = ?,
                    quantity_defect = ?,
                    actual_end = NOW(),
                    notes = CONCAT(IFNULL(notes, ''), '\\n', ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$totalQuantity, $quantityGood, $quantityDefect, 
                                  "Завершено " . date('Y-m-d H:i:s') . ": " . $comment, $taskId]);
            
            // Обновляем статус позиции заказа
            if ($task['order_item_id']) {
                $orderItemStmt = $pdo->prepare("
                    UPDATE order_items
                    SET production_status = 'completed',
                        quantity_completed = GREATEST(quantity_completed, ?),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $orderItemStmt->execute([$quantityGood, $task['order_item_id']]);
            }
            
            // Автоматическое создание серийных номеров если запрошено
            if ($autoSerial && $quantityGood > 0) {
                for ($i = 0; $i < $quantityGood; $i++) {
                    // Генерируем префикс из артикула
                    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $task['product_article']), 0, 3));
                    
                    // Получаем следующий номер
                    $stmt = $pdo->prepare("
                        SELECT serial_number
                        FROM product_serial_numbers
                        WHERE serial_number LIKE ?
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$prefix . '%']);
                    $lastSerial = $stmt->fetchColumn();
                    
                    if ($lastSerial) {
                        $lastNumber = (int)substr($lastSerial, strlen($prefix));
                        $nextNumber = $lastNumber + 1;
                    } else {
                        $nextNumber = 1;
                    }
                    
                    $newSerialNumber = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                    
                    // Создаем серийный номер
                    $serialStmt = $pdo->prepare("
                        INSERT INTO product_serial_numbers 
                        (serial_number, product_id, task_id, status, created_at)
                        VALUES (?, ?, ?, 'active', NOW())
                    ");
                    $serialStmt->execute([$newSerialNumber, $task['product_id'], $taskId]);
                }
            }
            
            // Списываем материалы автоматически
            $materialsStmt = $pdo->prepare("
                SELECT ptm.id, ptm.material_id, ptm.quantity_required, ptm.quantity_used,
                       m.current_stock, m.name_full
                FROM production_tasks_materials ptm
                JOIN materials m ON ptm.material_id = m.id
                WHERE ptm.task_id = ? AND ptm.status IN ('reserved', 'issued')
            ");
            $materialsStmt->execute([$taskId]);
            $materials = $materialsStmt->fetchAll();
            
            foreach ($materials as $mat) {
                $quantityToConsume = $mat['quantity_required'] - $mat['quantity_used'];
                
                if ($quantityToConsume <= 0) {
                    continue;
                }
                
                if ($mat['current_stock'] >= $quantityToConsume) {
                    // Списываем со склада
                    $stockUpdateStmt = $pdo->prepare("
                        UPDATE materials
                        SET current_stock = current_stock - ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stockUpdateStmt->execute([$quantityToConsume, $mat['material_id']]);
                    
                    // Обновляем статус материала
                    $materialUpdateStmt = $pdo->prepare("
                        UPDATE production_tasks_materials
                        SET quantity_used = quantity_used + ?,
                            status = 'consumed',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $materialUpdateStmt->execute([$quantityToConsume, $mat['id']]);
                    
                    // Логируем
                    $logStmt = $pdo->prepare("
                        INSERT INTO material_movements 
                        (material_id, movement_type, quantity, reference_type, reference_id, user_id, created_at)
                        VALUES (?, 'consumption', ?, 'production_task', ?, ?, NOW())
                    ");
                    $logStmt->execute([$mat['material_id'], -$quantityToConsume, $taskId, $user['id']]);
                }
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Производство завершено. Создано серийных номеров: ' . ($autoSerial ? (int)$quantityGood : 0)
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'update_worker') {
        // Обновление исполнителя задания
        $taskId = (int)($input['task_id'] ?? 0);
        $workerId = !empty($input['worker_id']) ? (int)$input['worker_id'] : null;
        
        if (!$taskId) {
            throw new Exception('Не указан ID задания');
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE production_tasks
            SET worker_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$workerId, $taskId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Исполнитель обновлен'
        ]);
        
    } elseif ($action === 'update_priority') {
        // Обновление приоритета задания
        $taskId = (int)($input['task_id'] ?? 0);
        $priority = $input['priority'] ?? 'normal';
        
        if (!$taskId) {
            throw new Exception('Не указан ID задания');
        }
        
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities)) {
            throw new Exception('Неверный приоритет');
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE production_tasks
            SET priority = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$priority, $taskId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Приоритет обновлен'
        ]);
        
    } elseif ($action === 'save_notes') {
        // Сохранение заметок к заданию
        $taskId = (int)($input['task_id'] ?? 0);
        $notes = trim($input['notes'] ?? '');
        
        if (!$taskId) {
            throw new Exception('Не указан ID задания');
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE production_tasks
            SET notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$notes, $taskId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Заметки сохранены'
        ]);
        
    } else {
        throw new Exception('Неизвестное действие');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    exit;
}


/**
 * Создание этапов для задания на основе маршрутной карты
 */
function createStagesForTask($pdo, $taskId, $routeCardId) {
    // Получаем операции из маршрутной карты с информацией об этапах
    $operationsStmt = $pdo->prepare("
        SELECT rco.id as operation_id, rco.stage_id, rco.operation_number, rco.name,
               ps.name as stage_name, ps.code as stage_code, ps.color as stage_color, ps.sort_order as stage_sort,
               rco.sort_order as operation_sort
        FROM route_card_operations rco
        JOIN production_stages ps ON rco.stage_id = ps.id
        WHERE rco.route_card_id = ?
        ORDER BY COALESCE(rco.sort_order, rco.operation_number)
    ");
    $operationsStmt->execute([$routeCardId]);
    $operations = $operationsStmt->fetchAll();
    
    if (empty($operations)) {
        return false;
    }
    
    // Создаем этапы для задания
    $insertStmt = $pdo->prepare("
        INSERT INTO production_task_stages 
        (task_id, stage_id, operation_id, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    
    foreach ($operations as $op) {
        $insertStmt->execute([$taskId, $op['stage_id'], $op['operation_id']]);
    }
    
    return true;
}

/**
 * Резервирование материалов для задания
 */
function reserveMaterialsForTask($pdo, $taskId) {
    // Получаем материалы которые еще не зарезервированы
    $materialsStmt = $pdo->prepare("
        SELECT ptm.id, ptm.material_id, ptm.quantity_required, ptm.quantity_reserved,
               m.current_stock, m.name_full
        FROM production_tasks_materials ptm
        JOIN materials m ON ptm.material_id = m.id
        WHERE ptm.task_id = ? AND ptm.status = 'pending'
    ");
    $materialsStmt->execute([$taskId]);
    $materials = $materialsStmt->fetchAll();
    
    foreach ($materials as $mat) {
        $quantityToReserve = $mat['quantity_required'] - $mat['quantity_reserved'];
        
        if ($quantityToReserve <= 0) {
            continue;
        }
        
        // Проверяем наличие
        if ($mat['current_stock'] >= $quantityToReserve) {
            // Резервируем
            $updateStmt = $pdo->prepare("
                UPDATE production_tasks_materials
                SET quantity_reserved = quantity_reserved + ?,
                    status = 'reserved',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$quantityToReserve, $mat['id']]);
        }
    }
}

// Если это не API запрос, выходим - функции выше доступны для execute.php
if (!$isApiRequest) {
    return;
}

// Дополнительная обработка API запросов (GET для получения данных задания)
// Эта часть выполняется только если $pdo доступен (т.е. мы внутри if ($isApiRequest))
if (isset($pdo) && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['task_id'])) {
    try {
        $taskId = (int)$_GET['task_id'];
        
        // Получаем информацию о задании
        $stmt = $pdo->prepare("
            SELECT pt.*, p.name as product_name, p.article, o.number as order_number
            FROM production_tasks pt
            JOIN products p ON pt.product_id = p.id
            JOIN orders o ON pt.order_id = o.id
            WHERE pt.id = ?
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            echo json_encode(['error' => 'Задание не найдено']);
            exit;
        }
        
        // Получаем этапы
        $stmt = $pdo->prepare("
            SELECT * FROM production_task_stages 
            WHERE task_id = ? 
            ORDER BY sort_order, id
        ");
        $stmt->execute([$taskId]);
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Получаем материалы
        $stmt = $pdo->prepare("
            SELECT ptm.*, m.name as material_name, m.unit, m.current_stock
            FROM production_tasks_materials ptm
            JOIN materials m ON ptm.material_id = m.id
            WHERE ptm.task_id = ?
            ORDER BY ptm.id
        ");
        $stmt->execute([$taskId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'task' => $task,
            'stages' => $stages,
            'materials' => $materials
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
}
exit;
