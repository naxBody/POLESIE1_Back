<?php
/**
 * API для получения данных паспорта продукта из БД и сохранения изменений
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$pdo = getDbConnection();

// Обработка POST запроса на сохранение паспорта
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Проверяем наличие passport_id для сохранения
    if (!$input || !isset($input['passport_id'])) {
        echo json_encode(['success' => false, 'error' => 'Неверный формат запроса']);
        exit;
    }
    
    try {
        $passportId = isset($input['passport_id']) ? (int)$input['passport_id'] : null;
        $productId = isset($input['product_id']) ? (int)$input['product_id'] : null;
        
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Не указан ID продукта']);
            exit;
        }
        
        $totalWeight = isset($input['total_weight_kg']) ? floatval($input['total_weight_kg']) : 0;
        $warrantyMonths = isset($input['warranty_months']) ? intval($input['warranty_months']) : 12;
        $isSerialTracked = isset($input['is_serial_tracked']) ? ($input['is_serial_tracked'] ? 1 : 0) : 0;
        
        // Обработка спецификаций
        $specifications = !empty($input['specifications']) && is_array($input['specifications'])
            ? json_encode($input['specifications'])
            : null;
        
        // Обработка примечаний и требований как JSON массивов
        $productionNotesRaw = $input['production_notes'] ?? '';
        if (is_array($input['production_notes'])) {
            $productionNotes = !empty($input['production_notes'])
                ? json_encode(array_values(array_filter($input['production_notes'])))
                : null;
        } else {
            // Если пришла строка, разбиваем на массив по строкам
            $notesArray = array_filter(array_map('trim', explode("\n", $productionNotesRaw)));
            $productionNotes = !empty($notesArray) ? json_encode($notesArray) : null;
        }
        
        $qualityRequirementsRaw = $input['quality_requirements'] ?? '';
        if (is_array($input['quality_requirements'])) {
            $qualityRequirements = !empty($input['quality_requirements'])
                ? json_encode(array_values(array_filter($input['quality_requirements'])))
                : null;
        } else {
            // Если пришла строка, разбиваем на массив по строкам
            $reqsArray = array_filter(array_map('trim', explode("\n", $qualityRequirementsRaw)));
            $qualityRequirements = !empty($reqsArray) ? json_encode($reqsArray) : null;
        }
        
        if ($passportId) {
            // Обновление существующего паспорта
            $stmt = $pdo->prepare("
                UPDATE product_passports 
                SET 
                    total_weight_kg = :total_weight_kg,
                    warranty_months = :warranty_months,
                    is_serial_tracked = :is_serial_tracked,
                    production_notes = :production_notes,
                    quality_requirements = :quality_requirements,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :passport_id
            ");
            $stmt->execute([
                ':total_weight_kg' => $totalWeight,
                ':warranty_months' => $warrantyMonths,
                ':is_serial_tracked' => $isSerialTracked,
                ':production_notes' => $productionNotes,
                ':quality_requirements' => $qualityRequirements,
                ':passport_id' => $passportId
            ]);
            
            // Обновление спецификаций в таблице products
            if ($specifications !== null) {
                $specStmt = $pdo->prepare("
                    UPDATE products 
                    SET specifications = :specifications
                    WHERE id = :product_id
                ");
                $specStmt->execute([
                    ':specifications' => $specifications,
                    ':product_id' => $productId
                ]);
            }
            
            echo json_encode(['success' => true, 'passport_id' => $passportId]);
            exit;
        } else {
            // Создание нового паспорта
            $stmt = $pdo->prepare("
                INSERT INTO product_passports 
                (product_id, total_weight_kg, warranty_months, is_serial_tracked, production_notes, quality_requirements)
                VALUES (:product_id, :total_weight_kg, :warranty_months, :is_serial_tracked, :production_notes, :quality_requirements)
            ");
            $stmt->execute([
                ':product_id' => $productId,
                ':total_weight_kg' => $totalWeight,
                ':warranty_months' => $warrantyMonths,
                ':is_serial_tracked' => $isSerialTracked,
                ':production_notes' => $productionNotes,
                ':quality_requirements' => $qualityRequirements
            ]);
            $passportId = $pdo->lastInsertId();
            
            // Обновление спецификаций в таблице products
            if ($specifications !== null) {
                $specStmt = $pdo->prepare("
                    UPDATE products 
                    SET specifications = :specifications
                    WHERE id = :product_id
                ");
                $specStmt->execute([
                    ':specifications' => $specifications,
                    ':product_id' => $productId
                ]);
            }
            
            echo json_encode(['success' => true, 'passport_id' => $passportId]);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
    }
    exit;
}

// GET запрос - получение данных паспорта
$passportId = $_GET['id'] ?? null;

if (!$passportId) {
    echo json_encode(['success' => false, 'error' => 'Не указан ID паспорта']);
    exit;
}

try {
    // Получение основных данных паспорта
    $stmt = $pdo->prepare("
        SELECT 
            pp.id as passport_id,
            p.id as product_id,
            p.article as sku,
            p.name as product_name,
            pc.name as category_name,
            pc.code as category_code,
            pp.total_weight_kg,
            pp.warranty_months,
            pp.is_serial_tracked,
            pp.production_notes,
            pp.quality_requirements,
            p.specifications
        FROM product_passports pp
        JOIN products p ON pp.product_id = p.id
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        WHERE pp.id = :passport_id AND p.is_active = TRUE
    ");
    $stmt->execute([':passport_id' => $passportId]);
    $passport = $stmt->fetch();
    
    if (!$passport) {
        echo json_encode(['success' => false, 'error' => 'Паспорт не найден']);
        exit;
    }
    
    // Получение материалов для паспорта
    $matStmt = $pdo->prepare("
        SELECT 
            m.id as material_id,
            ppm.quantity,
            bu.name as unit,
            m.code as material_code,
            m.name_full as material_name,
            mc.name as material_category
        FROM product_passport_materials ppm
        JOIN materials m ON ppm.material_id = m.id
        LEFT JOIN material_categories mc ON m.category_id = mc.id
        LEFT JOIN base_units bu ON ppm.unit_id = bu.id
        WHERE ppm.passport_id = :passport_id
        ORDER BY ppm.sort_order, m.name_full
    ");
    $matStmt->execute([':passport_id' => $passportId]);
    $materials = $matStmt->fetchAll();
    
    // Получение этапов производства из маршрутной карты
    $stageStmt = $pdo->prepare("
        SELECT 
            rco.operation_number,
            rco.name as operation_name,
            rco.description,
            rco.time_norm_hours,
            ps.name as stage_name,
            ps.color as stage_color,
            rco.work_center,
            rco.equipment
        FROM route_card_operations rco
        LEFT JOIN production_stages ps ON rco.stage_id = ps.id
        WHERE rco.route_card_id IN (
            SELECT id FROM route_cards WHERE product_id = :product_id AND is_active = TRUE
        )
        ORDER BY rco.sort_order, rco.operation_number
    ");
    $stageStmt->execute([':product_id' => $passport['product_id']]);
    $stages = $stageStmt->fetchAll();
    
    // Получение фактически использованных материалов из производственных заданий
    $usedMatStmt = $pdo->prepare("
        SELECT 
            ptm.quantity_used,
            ptm.quantity_required,
            ptm.status,
            m.code as material_code,
            m.name_full as material_name,
            mc.name as material_category,
            mu.name as unit_name,
            pt.task_number,
            pt.status as task_status
        FROM production_tasks_materials ptm
        JOIN materials m ON ptm.material_id = m.id
        LEFT JOIN material_categories mc ON m.category_id = mc.id
        LEFT JOIN base_units mu ON m.base_unit_id = mu.id
        JOIN production_tasks pt ON ptm.task_id = pt.id
        WHERE pt.product_id = :product_id AND ptm.quantity_used > 0
        ORDER BY pt.created_at DESC, ptm.id
        LIMIT 50
    ");
    $usedMatStmt->execute([':product_id' => $passport['product_id']]);
    $usedMaterials = $usedMatStmt->fetchAll();
    
    // Группировка использованных материалов по наименованиям
    $usedMaterialsGrouped = [];
    foreach ($usedMaterials as $um) {
        $key = $um['material_code'];
        if (!isset($usedMaterialsGrouped[$key])) {
            $usedMaterialsGrouped[$key] = [
                'material_code' => $um['material_code'],
                'material_name' => $um['material_name'],
                'material_category' => $um['material_category'],
                'unit_name' => $um['unit_name'],
                'total_used' => 0,
                'total_required' => 0,
                'tasks_count' => 0,
                'tasks' => []
            ];
        }
        $usedMaterialsGrouped[$key]['total_used'] += floatval($um['quantity_used']);
        $usedMaterialsGrouped[$key]['total_required'] += floatval($um['quantity_required']);
        $usedMaterialsGrouped[$key]['tasks_count']++;
        if (!in_array($um['task_number'], $usedMaterialsGrouped[$key]['tasks'])) {
            $usedMaterialsGrouped[$key]['tasks'][] = $um['task_number'];
        }
    }
    
    // Декодирование JSON полей
    $passport['production_notes'] = $passport['production_notes'] ? json_decode($passport['production_notes'], true) : [];
    $passport['quality_requirements'] = $passport['quality_requirements'] ? json_decode($passport['quality_requirements'], true) : [];
    $passport['specifications'] = $passport['specifications'] ? json_decode($passport['specifications'], true) : [];
    $passport['materials'] = $materials;
    $passport['stages'] = $stages;
    $passport['used_materials'] = array_values($usedMaterialsGrouped);
    
    echo json_encode([
        'success' => true,
        'passport' => $passport
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
}
