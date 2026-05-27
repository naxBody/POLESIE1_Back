<?php
/**
 * API для получения данных паспорта продукта из БД
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
            pc.name_ru as category_name,
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
            ppm.quantity,
            ppm.unit,
            m.code as material_code,
            m.name_full as material_name,
            mc.name_ru as material_category
        FROM product_passport_materials ppm
        JOIN materials m ON ppm.material_id = m.id
        LEFT JOIN material_categories mc ON m.category_id = mc.id
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
    
    // Декодирование JSON полей
    $passport['production_notes'] = $passport['production_notes'] ? json_decode($passport['production_notes'], true) : [];
    $passport['quality_requirements'] = $passport['quality_requirements'] ? json_decode($passport['quality_requirements'], true) : [];
    $passport['specifications'] = $passport['specifications'] ? json_decode($passport['specifications'], true) : [];
    $passport['materials'] = $materials;
    $passport['stages'] = $stages;
    
    echo json_encode([
        'success' => true,
        'passport' => $passport
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
}
