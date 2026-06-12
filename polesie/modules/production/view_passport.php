<?php
/**
 * Просмотр и печать паспорта продукта по серийному номеру
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Паспорт продукта';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('Не указан ID серийного номера');
}

// Получение данных о серийном номере
$stmt = $pdo->prepare("
    SELECT sn.*, p.name as product_name, p.article as product_article, 
           p.description as product_description, 
           p.specifications as product_template_specs,
           COALESCE(sn.product_specifications, p.specifications) as product_specifications,
           c.name as category_name, u.symbol as unit_name,
           po.production_number
    FROM product_serial_numbers sn
    JOIN products p ON sn.product_id = p.id
    LEFT JOIN product_categories c ON p.category_id = c.id
    LEFT JOIN base_units u ON p.base_unit_id = u.id
    LEFT JOIN production_tasks po ON sn.task_id = po.id
    WHERE sn.id = ?
");
$stmt->execute([$id]);
$serialData = $stmt->fetch();

if (!$serialData) {
    die('Серийный номер не найден');
}

// Явно загружаем технические характеристики из JSON полей
$technicalSpecs = [];
$productSpecs = [];
$passportDataArr = [];

if (!empty($serialData['technical_specs'])) {
    $decoded = json_decode($serialData['technical_specs'], true);
    if (is_array($decoded)) {
        $technicalSpecs = $decoded;
    }
}

if (!empty($serialData['product_specifications'])) {
    $decoded = json_decode($serialData['product_specifications'], true);
    if (is_array($decoded)) {
        $productSpecs = $decoded;
    }
}

if (!empty($serialData['passport_data'])) {
    $decoded = json_decode($serialData['passport_data'], true);
    if (is_array($decoded)) {
        $passportDataArr = $decoded;
    }
}

// Функция для разбора ключа с суффиксом единицы измерения (вынесена наверх)
function parseSpecKey($key) {
    // Массив известных суффиксов единиц измерения (от длинных к коротким)
    $unitSuffixes = [
        '_л_мин' => 'л/мин',
        '_об_мин' => 'об/мин',
        '_квт' => 'кВт',
        '_вт' => 'Вт',
        '_Гц' => 'Гц',
        '_дБ' => 'дБ',
        '_проц' => '%',
        '_кг' => 'кг',
        '_мм' => 'мм',
        '_С' => '°C',
        '_в' => 'В',
        '_м' => 'м',
    ];
    
    $unit = '';
    $baseKey = $key;
    
    foreach ($unitSuffixes as $suffix => $unitValue) {
        if (substr($key, -strlen($suffix)) === $suffix) {
            $unit = $unitValue;
            $baseKey = substr($key, 0, -strlen($suffix));
            break;
        }
    }
    
    return ['base_key' => $baseKey, 'unit' => $unit];
}

// Получение документов
$docsStmt = $pdo->prepare("SELECT * FROM product_documents WHERE serial_number_id = ? ORDER BY uploaded_at DESC");
$docsStmt->execute([$id]);
$documents = $docsStmt->fetchAll();

// Получение версий паспорта
$versionsStmt = $pdo->prepare("SELECT * FROM product_passport_versions WHERE serial_number_id = ? ORDER BY version_number DESC");
$versionsStmt->execute([$id]);
$versions = $versionsStmt->fetchAll();

// Получение динамических данных паспорта
$dynamicPassportData = null;
$dynStmt = $pdo->prepare("SELECT * FROM passport_dynamic_data WHERE serial_number_id = ?");
$dynStmt->execute([$id]);
$dynamicPassportData = $dynStmt->fetch();

// Получение материалов для продукта из паспорта продукта
$materialsStmt = $pdo->prepare("
    SELECT 
        ppm.quantity,
        ppm.unit,
        ppm.sort_order,
        m.id as material_id,
        m.code as material_code,
        m.name_full as material_name,
        m.name_short as material_short,
        m.description as material_description,
        m.specifications as material_specifications,
        mc.name as material_category,
        m.material_type
    FROM product_passport_materials ppm
    JOIN materials m ON ppm.material_id = m.id
    LEFT JOIN material_categories mc ON m.category_id = mc.id
    WHERE ppm.passport_id = (
        SELECT id FROM product_passports WHERE product_id = ?
    )
    ORDER BY ppm.sort_order, m.name_full
");
$materialsStmt->execute([$serialData['product_id']]);
$materials = $materialsStmt->fetchAll();

// Получение этапов производства из маршрутной карты продукта
$stagesStmt = $pdo->prepare("
    SELECT 
        rco.operation_number,
        rco.name as operation_name,
        rco.description,
        rco.time_norm_hours,
        rco.sort_order,
        ps.name as stage_name,
        ps.color as stage_color,
        rco.work_center,
        rco.equipment,
        rco.required_skills
    FROM route_card_operations rco
    LEFT JOIN production_stages ps ON rco.stage_id = ps.id
    WHERE rco.route_card_id IN (
        SELECT id FROM route_cards WHERE product_id = ? AND is_active = TRUE
    )
    ORDER BY rco.sort_order, rco.operation_number
");
$stagesStmt->execute([$serialData['product_id']]);
$stages = $stagesStmt->fetchAll();

// Если есть динамические данные, используем их
if ($dynamicPassportData) {
    // Переопределяем данные если заданы в динамическом паспорте
    if (!empty($dynamicPassportData['warranty_start'])) {
        $serialData['warranty_start'] = $dynamicPassportData['warranty_start'];
    }
    if (!empty($dynamicPassportData['warranty_end'])) {
        $serialData['warranty_end'] = $dynamicPassportData['warranty_end'];
    }
    if (!empty($dynamicPassportData['warranty_months'])) {
        $serialData['warranty_months'] = $dynamicPassportData['warranty_months'];
    }
    if (!empty($dynamicPassportData['manufacture_date'])) {
        $serialData['manufacture_date'] = $dynamicPassportData['manufacture_date'];
    }
    // Также переопределяем технические характеристики из динамического паспорта
    if (!empty($dynamicPassportData['technical_specs_custom'])) {
        $decodedTech = json_decode($dynamicPassportData['technical_specs_custom'], true);
        if (is_array($decodedTech)) {
            $technicalSpecs = $decodedTech;
        }
    }
    if (!empty($dynamicPassportData['product_name_custom'])) {
        $serialData['product_name'] = $dynamicPassportData['product_name_custom'];
    }
    if (!empty($dynamicPassportData['product_description'])) {
        $serialData['product_description'] = $dynamicPassportData['product_description'];
    }
}

// Режим печати
$isPrint = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($serialData['serial_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <?php if (!$isPrint): ?>
    <style>
        .passport-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 24px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        .passport-number {
            font-family: 'Courier New', monospace;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .passport-section {
            background: white;
            padding: 16px;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 16px;
        }
        .passport-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }
        .specs-list {
            display: flex;
            flex-direction: column;
        }
        .spec-row {
            display: flex;
            align-items: baseline;
            padding: 8px 0;
            border-bottom: 1px solid #e5e5e5;
            gap: 16px;
        }
        .spec-row:last-child {
            border-bottom: none;
        }
        .spec-label {
            font-size: 13px;
            color: #666;
            min-width: 220px;
            font-weight: 500;
            flex-shrink: 0;
        }
        .spec-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        .document-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .document-row {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e5e5;
            gap: 12px;
        }
        .document-row:last-child {
            border-bottom: none;
        }
        .document-info {
            flex: 1;
        }
        .document-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }
        .document-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        .document-actions {
            display: flex;
            gap: 6px;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-icon:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        .btn-print {
            background: var(--success-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit-passport {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .actions-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        /* Стили для этапов производства */
        .stages-timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .stage-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            background: var(--bg-tertiary);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }
        .stage-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        .stage-content {
            flex: 1;
        }
        .stage-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .stage-description {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        .stage-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .stage-meta-item {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Стили для материалов */
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .materials-table th {
            text-align: left;
            padding: 12px;
            background: var(--bg-secondary);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }
        .materials-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .materials-table tr:hover {
            background: var(--bg-tertiary);
        }
        .material-code {
            font-family: 'Courier New', monospace;
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }
        .material-code:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .material-name-link {
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .material-name-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        .material-card-item {
            background: var(--bg-tertiary);
            border-radius: var(--border-radius);
            padding: 16px;
            margin-bottom: 12px;
            border-left: 3px solid var(--primary-color);
            transition: all 0.2s;
            cursor: pointer;
        }
        .material-card-item:hover {
            background: var(--primary-light);
            transform: translateX(4px);
        }
        .material-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .material-card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .material-card-code {
            font-family: 'Courier New', monospace;
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .material-card-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
            margin-top: 12px;
        }
        .material-spec-item {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .material-spec-label {
            font-weight: 500;
            color: var(--text-primary);
        }
        .material-row-clickable {
            cursor: pointer;
            transition: all 0.2s;
        }
        .material-row-clickable:hover {
            background: var(--primary-light);
        }
        .material-spec-badge {
            display: inline-block;
            background: var(--bg-secondary);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            #printableArea, #printableArea * {
                visibility: visible;
            }
            #printableArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .passport-header {
                background: white;
                color: black;
                border: 2px solid black;
                padding: 20px;
            }
            .passport-section {
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 12px;
            }
            .passport-section-title {
                border-bottom: 1px solid #333;
            }
            .spec-row {
                background: white;
                border-bottom: 1px solid #eee;
                padding: 6px 0;
            }
            .spec-row:last-child {
                border-bottom: none;
            }
            .spec-label {
                color: #333;
                font-weight: 600;
            }
            .document-row {
                background: white;
                border-bottom: 1px solid #eee;
            }
            .document-row:last-child {
                border-bottom: none;
            }
            .document-name {
                color: #000;
            }
            .btn-icon {
                display: none;
            }
            .actions-bar {
                display: none;
            }
            .stage-item {
                background: white;
                border: 1px solid #ddd;
                padding: 12px;
            }
            .stage-number {
                background: #333 !important;
                color: white;
            }
            .stage-name {
                color: #000;
                font-weight: 600;
            }
            .stage-description {
                color: #333;
            }
            .stage-meta-item {
                color: #333;
            }
            .materials-table {
                background: white;
            }
            .materials-table th {
                background: #f5f5f5;
                color: #000;
                border-bottom: 1px solid #333;
            }
            .materials-table td {
                border-bottom: 1px solid #eee;
                color: #000;
            }
            .material-code {
                background: #f5f5f5;
                color: #000;
            }
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if (!$isPrint): ?>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="content">
    <?php endif; ?>
    
    <div id="printableArea">
        <?php if (!$isPrint): ?>
        <div class="page-header">
            <div class="page-header-title">
                <h2>📄 Паспорт продукта</h2>
                <p>Серийный номер: <?= e($serialData['serial_number']) ?></p>
            </div>
            <div class="page-header-actions">
                <div class="actions-bar">
                    <button class="btn-print" onclick="openPrintPreviewModal()">🖨️ Печать</button>
                    <?php if (hasPermission('production.edit')): ?>
                        <button class="btn-edit-passport" onclick="openEditPassportModal()">✏️ Редактировать паспорт</button>
                    <?php endif; ?>
                    <a href="serial_numbers.php" class="btn btn-outline">← Назад</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Шапка паспорта -->
        <div class="passport-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="font-size: 14px; opacity: 0.9;">ПАСПОРТ ПРОДУКТА</div>
                    <div class="passport-number" style="margin-top: 8px;"><?= e($serialData['serial_number']) ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 20px; font-weight: 700;"><?= e($serialData['product_name']) ?></div>
                    <div style="opacity: 0.9; margin-top: 4px;">Артикул: <?= e($serialData['product_article']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Основная информация -->
        <div class="passport-section">
            <div class="passport-section-title">📋 Основная информация</div>
            <div class="specs-list">
                <div class="spec-row">
                    <div class="spec-label">Категория</div>
                    <div class="spec-value"><?= e($serialData['category_name'] ?? '—') ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Ед. измерения</div>
                    <div class="spec-value"><?= e($serialData['unit_name'] ?? '—') ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Дата выпуска</div>
                    <div class="spec-value"><?= date('d.m.Y', strtotime($serialData['manufacture_date'])) ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Производство</div>
                    <div class="spec-value"><?= e($serialData['production_number'] ?? '—') ?></div>
                </div>
                <div class="spec-row">
                    <div class="spec-label">Статус</div>
                    <div class="spec-value"><?= e($serialData['status']) ?></div>
                </div>
                <?php if ($serialData['warranty_end']): ?>
                <div class="spec-row">
                    <div class="spec-label">Гарантия до</div>
                    <div class="spec-value"><?= date('d.m.Y', strtotime($serialData['warranty_end'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Технические характеристики продукта -->
        <?php if (!empty($productSpecs)): ?>
        <div class="passport-section">
            <div class="passport-section-title">⚙️ Технические характеристики</div>
            <div class="specs-list">
                <?php 
                $specLabels = [
                    'explosion_protection' => 'Вид взрывозащиты',
                    'housing_material' => 'Материал корпуса',
                    'frame_material' => 'Материал корпуса',
                    'shaft_material' => 'Материал вала',
                    'winding_material' => 'Материал обмотки',
                    'power_kw' => 'Мощность',
                    'потребляемая_мощность' => 'Потребляемая мощность',
                    'rpm' => 'Частота вращения',
                    'voltage_v' => 'Напряжение питания',
                    'напряжение' => 'Напряжение',
                    'frequency_hz' => 'Частота тока',
                    'protection_class' => 'Степень защиты',
                    'efficiency_class' => 'Класс энергоэффективности',
                    'climate_versions' => 'Климатическое исполнение',
                    'mounting_versions' => 'Исполнение по монтажу',
                    'weight_kg' => 'Масса',
                    'ip_rating' => 'Степень защиты IP',
                    'производительность' => 'Производительность',
                    'номинальный_напор' => 'Номинальный напор',
                    'макс_высота_всасывания' => 'Макс. высота всасывания'
                ];
                
                $specUnits = [
                    'power_kw' => 'кВт',
                    'потребляемая_мощность' => 'Вт',
                    'rpm' => 'об/мин',
                    'voltage_v' => 'В',
                    'напряжение' => 'В',
                    'frequency_hz' => 'Гц',
                    'weight_kg' => 'кг',
                    'shaft_height_mm' => 'мм',
                    'ambient_temp' => '°C',
                    'noise_level' => 'дБ',
                    'efficiency' => '%',
                    'power_factor' => 'cos φ',
                    'производительность' => 'л/мин',
                    'номинальный_напор' => 'м',
                    'макс_высота_всасывания' => 'м'
                ];
                
                foreach ($productSpecs as $key => $value): 
                    // Разбираем ключ на базовое имя и единицу измерения
                    $parsed = parseSpecKey($key);
                    $baseKey = $parsed['base_key'];
                    $parsedUnit = $parsed['unit'];
                    
                    // Определяем label и unit
                    $label = $specLabels[$baseKey] ?? ucfirst(str_replace('_', ' ', $baseKey));
                    $unit = !empty($parsedUnit) ? $parsedUnit : ($specUnits[$baseKey] ?? '');
                    
                    // Форматируем значение
                    if (is_array($value)) {
                        $displayValue = $value['value'] ?? json_encode($value, JSON_UNESCAPED_UNICODE);
                        $displayUnit = $value['unit'] ?? $unit;
                    } else {
                        $displayValue = $value;
                        $displayUnit = $unit;
                    }
                ?>
                <div class="spec-row">
                    <div class="spec-label"><?= e($label) ?></div>
                    <div class="spec-value">
                        <span><?= e($displayValue) ?></span>
                        <?php if (!empty($displayUnit)): ?>
                            <span class="spec-unit"> <?= e($displayUnit) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Индивидуальные технические характеристики -->
        <?php if (!empty($technicalSpecs)): ?>
        <div class="passport-section">
            <div class="passport-section-title">🔧 Индивидуальные параметры</div>
            <div class="specs-list">
                <?php 
                $techSpecLabels = [
                    'explosion_protection' => 'Вид взрывозащиты',
                    'housing_material' => 'Материал корпуса',
                    'frame_material' => 'Материал корпуса',
                    'shaft_material' => 'Материал вала',
                    'winding_material' => 'Материал обмотки',
                    'power_kw' => 'Мощность',
                    'rpm' => 'Частота вращения',
                    'voltage_v' => 'Напряжение питания',
                    'напряжение' => 'Напряжение',
                    'frequency_hz' => 'Частота тока',
                    'protection_class' => 'Степень защиты',
                    'efficiency_class' => 'Класс энергоэффективности',
                    'climate_versions' => 'Климатическое исполнение',
                    'mounting_versions' => 'Исполнение по монтажу',
                    'weight_kg' => 'Масса',
                    'ip_rating' => 'Степень защиты IP',
                    'производительность' => 'Производительность',
                    'номинальный_напор' => 'Номинальный напор',
                    'макс_высота_всасывания' => 'Макс. высота всасывания'
                ];
                
                $techSpecUnits = [
                    'power_kw' => 'кВт',
                    'rpm' => 'об/мин',
                    'voltage_v' => 'В',
                    'напряжение' => 'В',
                    'frequency_hz' => 'Гц',
                    'weight_kg' => 'кг',
                    'shaft_height_mm' => 'мм',
                    'ambient_temp' => '°C',
                    'noise_level' => 'дБ',
                    'efficiency' => '%',
                    'power_factor' => 'cos φ',
                    'производительность' => 'л/мин',
                    'номинальный_напор' => 'м',
                    'макс_высота_всасывания' => 'м'
                ];
                
                foreach ($technicalSpecs as $key => $value): 
                    // Разбираем ключ на базовое имя и единицу измерения
                    $parsed = parseSpecKey($key);
                    $baseKey = $parsed['base_key'];
                    $parsedUnit = $parsed['unit'];
                    
                    // Определяем label и unit
                    $label = $techSpecLabels[$baseKey] ?? ucfirst(str_replace('_', ' ', $baseKey));
                    $unit = !empty($parsedUnit) ? $parsedUnit : ($techSpecUnits[$baseKey] ?? '');
                    
                    // Форматируем значение
                    if (is_array($value)) {
                        $displayValue = $value['value'] ?? json_encode($value, JSON_UNESCAPED_UNICODE);
                        $displayUnit = $value['unit'] ?? $unit;
                    } else {
                        $displayValue = $value;
                        $displayUnit = $unit;
                    }
                ?>
                <div class="spec-row">
                    <div class="spec-label"><?= e($label) ?></div>
                    <div class="spec-value">
                        <span><?= e($displayValue) ?></span>
                        <?php if (!empty($displayUnit)): ?>
                            <span class="spec-unit"> <?= e($displayUnit) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Описание -->
        <?php if (!empty($serialData['product_description'])): ?>
        <div class="passport-section">
            <div class="passport-section-title">📝 Описание</div>
            <div style="line-height: 1.6;"><?= nl2br(e($serialData['product_description'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Этапы производства -->
        <?php if (!empty($stages)): ?>
        <div class="passport-section">
            <div class="passport-section-title">🏭 Этапы производства</div>
            <div class="stages-timeline">
                <?php foreach ($stages as $index => $stage): ?>
                <div class="stage-item" style="<?= !empty($stage['stage_color']) ? 'border-left-color: ' . e($stage['stage_color']) : '' ?>">
                    <div class="stage-number" style="<?= !empty($stage['stage_color']) ? 'background: ' . e($stage['stage_color']) : '' ?>">
                        <?= $index + 1 ?>
                    </div>
                    <div class="stage-content">
                        <div class="stage-name"><?= e($stage['operation_name']) ?></div>
                        <?php if (!empty($stage['description'])): ?>
                        <div class="stage-description"><?= nl2br(e($stage['description'])) ?></div>
                        <?php endif; ?>
                        <div class="stage-meta">
                            <?php if (!empty($stage['stage_name'])): ?>
                            <span class="stage-meta-item">📍 <?= e($stage['stage_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($stage['work_center'])): ?>
                            <span class="stage-meta-item">🏢 <?= e($stage['work_center']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($stage['equipment'])): ?>
                            <span class="stage-meta-item">🔧 <?= e($stage['equipment']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($stage['time_norm_hours'])): ?>
                            <span class="stage-meta-item">⏱️ <?= number_format($stage['time_norm_hours'], 1) ?> ч</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Материалы для производства -->
        <?php if (!empty($materials)): ?>
        <div class="passport-section">
            <div class="passport-section-title">📦 Материалы для производства (<?= count($materials) ?> поз.)</div>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Артикул</th>
                        <th>Наименование</th>
                        <th>Категория</th>
                        <th>Характеристики</th>
                        <th style="width: 120px; text-align: right;">Количество</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $index => $material): 
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
                    <tr class="material-row-clickable" onclick="openMaterialModal(<?= htmlspecialchars(json_encode([
                        'id' => $material['material_id'],
                        'code' => $material['material_code'],
                        'name_full' => $material['material_name'],
                        'name_short' => $material['material_short'] ?? '',
                        'description' => $material['material_description'] ?? '',
                        'specifications' => $materialSpecs,
                        'category' => $material['material_category'] ?? '',
                        'quantity' => $material['quantity'],
                        'unit' => $material['unit']
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">
                        <td><?= $index + 1 ?></td>
                        <td>
                            <span class="material-code" title="Нажмите для просмотра информации о материале"><?= e($material['material_code']) ?></span>
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
                            <strong><?= number_format($material['quantity'], 3, ',', ' ') ?></strong> 
                            <span style="color: var(--text-secondary); font-size: 13px;"><?= e($material['unit']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="passport-section">
            <div class="passport-section-title">📦 Материалы для производства</div>
            <p style="color: var(--text-secondary); padding: 20px; text-align: center; background: var(--bg-tertiary); border-radius: var(--border-radius);">
                ℹ️ Материалы не указаны в паспорте продукта
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Документы -->
        <div class="passport-section">
            <div class="passport-section-title">📎 Прикреплённые документы</div>
            <?php if (empty($documents)): ?>
                <p style="color: var(--text-secondary);">Документы не прикреплены</p>
            <?php else: ?>
                <ul class="document-list">
                    <?php foreach ($documents as $doc): ?>
                    <?php
                    $typeLabels = [
                        'manual' => '📘 Руководство',
                        'certificate' => '📜 Сертификат',
                        'test_report' => '📊 Отчёт о тестировании',
                        'warranty_card' => '🛡️ Гарантийный талон',
                        'other' => '📄 Другое'
                    ];
                    ?>
                    <li class="document-row">
                        <div class="document-info">
                            <div class="document-name"><?= $typeLabels[$doc['document_type']] ?? '📄 Документ' ?></div>
                            <div class="document-meta">
                                <?= e($doc['file_name']) ?> 
                                <?php if ($doc['file_size']): ?>
                                    (<?= round($doc['file_size'] / 1024, 1) ?> KB)
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="document-actions">
                            <a href="<?= e($doc['file_path']) ?>" download class="btn-icon" title="Скачать">⬇️</a>
                            <?php if ($doc['mime_type'] && strpos($doc['mime_type'], 'pdf') !== false): ?>
                                <a href="<?= e($doc['file_path']) ?>" target="_blank" class="btn-icon" title="Открыть">👁️</a>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <!-- Примечания -->
        <?php if (!empty($serialData['notes'])): ?>
        <div class="passport-section">
            <div class="passport-section-title">📌 Примечания</div>
            <div style="line-height: 1.6;"><?= nl2br(e($serialData['notes'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Подвал -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid var(--border-color); display: flex; justify-content: space-between;">
            <div>
                <div style="font-size: 12px; color: var(--text-secondary);">Создан</div>
                <div style="font-size: 14px;"><?= date('d.m.Y H:i', strtotime($serialData['created_at'])) ?></div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 12px; color: var(--text-secondary);">Последнее обновление</div>
                <div style="font-size: 14px;"><?= date('d.m.Y H:i', strtotime($serialData['updated_at'])) ?></div>
            </div>
        </div>
    </div>
    
    <?php if (!$isPrint): ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно предпросмотра печати с редактированием -->
    <div id="printPreviewModal" class="product-modal-overlay" onclick="closePrintPreviewModal(event)" style="display: none;">
        <div class="product-modal" style="max-width: 900px;" onclick="event.stopPropagation()">
            <div class="product-modal-header">
                <h3 class="product-modal-title">🖨️ Предпросмотр паспорта - <?= e($serialData['serial_number']) ?></h3>
                <button class="product-modal-close" onclick="closePrintPreviewModalDirect()">×</button>
            </div>
            <div class="product-modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form method="POST" action="api_passport.php" id="printPreviewForm">
                    <input type="hidden" name="action" value="update_passport">
                    <input type="hidden" name="serial_id" value="<?= $id ?>">
                    
                    <div style="background: #f8f9fa; padding: 16px; border-radius: var(--border-radius); margin-bottom: 20px;">
                        <p style="margin: 0; color: var(--text-secondary); font-size: 14px;">
                            ℹ️ Отредактируйте данные при необходимости, затем нажмите "Печать" для открытия готового документа.
                        </p>
                    </div>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color);">📅 Даты и гарантия</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Дата изготовления</label>
                            <input type="date" name="manufacture_date" 
                                   value="<?= e($dynamicPassportData['manufacture_date'] ?? $serialData['manufacture_date']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Гарантийный срок (мес.)</label>
                            <input type="number" name="warranty_months" 
                                   value="<?= e($dynamicPassportData['warranty_months'] ?? $serialData['warranty_months'] ?? 12) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Начало гарантии</label>
                            <input type="date" name="warranty_start" 
                                   value="<?= e($dynamicPassportData['warranty_start'] ?? $serialData['warranty_start']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Окончание гарантии</label>
                            <input type="date" name="warranty_end" 
                                   value="<?= e($dynamicPassportData['warranty_end'] ?? $serialData['warranty_end']) ?>"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <?php
                    // Значения по умолчанию для организации (для printPreviewModal)
                    $printDefaultCompanyName = 'ОАО «Полесьеэлектромаш»';
                    $printDefaultCompanyAddress = '225644, Брестская область, г. Лунинец, ул. Красная, 179';
                    $printDefaultCompanyPhone = '+375 1647 2-78-09';
                    $printDefaultCompanyEmail = 'polesie@polesieelectromash.by';
                    ?>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color); margin-top: 20px;">🏢 Данные организации</h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Название организации</label>
                        <input type="text" name="company_name" 
                               value="<?= e($dynamicPassportData['company_name'] ?? $printDefaultCompanyName) ?>"
                               placeholder="ОАО «Полесьеэлектромаш»"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Адрес</label>
                        <input type="text" name="company_address" 
                               value="<?= e($dynamicPassportData['company_address'] ?? $printDefaultCompanyAddress) ?>"
                               placeholder="225644, Брестская область, г. Лунинец, ул. Красная, 179"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">Телефон</label>
                            <input type="text" name="company_phone" 
                                   value="<?= e($dynamicPassportData['company_phone'] ?? $printDefaultCompanyPhone) ?>"
                                   placeholder="+375 1647 2-78-09"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 6px; font-weight: 500;">E-mail</label>
                            <input type="email" name="company_email" 
                                   value="<?= e($dynamicPassportData['company_email'] ?? $printDefaultCompanyEmail) ?>"
                                   placeholder="polesie@polesieelectromash.by"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        </div>
                    </div>
                    
                    <h4 style="margin-bottom: 12px; color: var(--primary-color); margin-top: 20px;">📦 Информация об изделии</h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Наименование изделия (кастомное)</label>
                        <input type="text" name="product_name_custom" 
                               value="<?= e($dynamicPassportData['product_name_custom'] ?? '') ?>"
                               placeholder="Оставьте пустым для использования стандартного названия"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" onclick="closePrintPreviewModalDirect()" class="btn btn-outline">Отмена</button>
                        <button type="button" class="btn btn-primary" id="saveAndPrintBtn">💾 Сохранить и печатать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно информации о материале -->
    <div id="materialInfoModal" class="product-modal-overlay" onclick="closeMaterialModal(event)" style="display: none;">
        <div class="product-modal" style="max-width: 700px;" onclick="event.stopPropagation()">
            <div class="product-modal-header">
                <h3 class="product-modal-title">📦 Информация о материале</h3>
                <button class="product-modal-close" onclick="closeMaterialModalDirect()">×</button>
            </div>
            <div class="product-modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                        <div style="flex: 1;">
                            <h4 id="modalMaterialName" style="margin: 0 0 8px 0; font-size: 18px; color: var(--text-primary);"></h4>
                            <div style="font-size: 14px; color: var(--text-secondary);">
                                <span id="modalMaterialCategory"></span>
                            </div>
                        </div>
                        <span class="material-code" id="modalMaterialCode"></span>
                    </div>
                    
                    <div style="background: var(--bg-secondary); padding: 16px; border-radius: var(--border-radius); margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 500; color: var(--text-secondary);">Требуемое количество:</span>
                            <span style="font-size: 18px; font-weight: 700; color: var(--primary-color);" id="modalMaterialQuantity"></span>
                        </div>
                    </div>
                    
                    <h5 style="margin: 20px 0 12px 0; color: var(--primary-color); font-size: 14px;">📝 Описание</h5>
                    <p id="modalMaterialDescription" style="color: var(--text-secondary); line-height: 1.6;"></p>
                    
                    <h5 style="margin: 20px 0 12px 0; color: var(--primary-color); font-size: 14px;">⚙️ Характеристики</h5>
                    <div id="modalMaterialSpecs" class="specs-list">
                    </div>
                </div>
            </div>
            <div class="product-modal-footer" style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeMaterialModalDirect()" class="btn btn-outline">Закрыть</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно редактирования паспорта -->
    <div id="editPassportModal" class="product-modal-overlay" onclick="closeEditPassportModal(event)" style="display: none;">
        <div class="product-modal" onclick="event.stopPropagation()">
            <div class="product-modal-header">
                <h3 class="product-modal-title">Редактировать паспорт</h3>
                <button class="product-modal-close" onclick="closeEditPassportModalDirect()">×</button>
            </div>
            <div class="product-modal-body">
                <style>
                    .passport-edit-grid {
                        display: grid;
                        grid-template-columns: 220px 1fr;
                        gap: 16px;
                        align-items: start;
                        margin-bottom: 16px;
                    }
                    .passport-edit-label {
                        display: block;
                        font-size: 13px;
                        font-weight: 600;
                        color: var(--text-primary);
                        padding-top: 10px;
                    }
                    .passport-edit-control {
                        width: 100%;
                        padding: 12px 14px;
                        border: 1px solid var(--border-color);
                        border-radius: var(--border-radius);
                        font-size: 14px;
                        background: var(--bg-primary);
                        transition: all var(--transition-fast);
                        box-sizing: border-box;
                    }
                    .passport-edit-control:focus {
                        outline: none !important;
                        border-color: var(--border-color) !important;
                        box-shadow: none !important;
                    }
                    textarea.passport-edit-control {
                        min-height: 100px;
                        resize: vertical;
                        font-family: monospace;
                        font-size: 12px;
                    }
                    .passport-edit-section {
                        margin-top: 24px;
                        padding-top: 24px;
                        border-top: 1px solid var(--border-color);
                    }
                    .passport-edit-section-title {
                        font-size: 15px;
                        font-weight: 600;
                        color: var(--primary-color);
                        margin-bottom: 16px;
                        grid-column: 1 / -1;
                    }
                    .passport-edit-full {
                        grid-column: 1 / -1;
                    }
                </style>
                
                <form id="editPassportForm">
                    <input type="hidden" name="action" value="update_passport">
                    <input type="hidden" name="serial_id" value="<?= $id ?>">
                    
                    <div class="passport-edit-grid">
                        <span class="passport-edit-section-title">📅 Даты и гарантия</span>
                        
                        <label class="passport-edit-label">Дата изготовления</label>
                        <input type="date" name="manufacture_date" 
                               value="<?= e($dynamicPassportData['manufacture_date'] ?? $serialData['manufacture_date']) ?>"
                               class="passport-edit-control">
                        
                        <label class="passport-edit-label">Гарантийный срок (мес.)</label>
                        <input type="number" name="warranty_months" 
                               value="<?= e($dynamicPassportData['warranty_months'] ?? $serialData['warranty_months'] ?? 12) ?>"
                               class="passport-edit-control">
                        
                        <label class="passport-edit-label">Начало гарантии</label>
                        <input type="date" name="warranty_start" 
                               value="<?= e($dynamicPassportData['warranty_start'] ?? $serialData['warranty_start']) ?>"
                               class="passport-edit-control">
                        
                        <label class="passport-edit-label">Окончание гарантии</label>
                        <input type="date" name="warranty_end" 
                               value="<?= e($dynamicPassportData['warranty_end'] ?? $serialData['warranty_end']) ?>"
                               class="passport-edit-control">
                    </div>
                    
                    <?php
                    // Значения по умолчанию для организации (для editPassportModal)
                    $editDefaultCompanyName = 'ОАО «Полесьеэлектромаш»';
                    $editDefaultCompanyAddress = '225644, Брестская область, г. Лунинец, ул. Красная, 179';
                    $editDefaultCompanyPhone = '+375 1647 2-78-09';
                    $editDefaultCompanyEmail = 'polesie@polesieelectromash.by';
                    ?>
                    
                    <div class="passport-edit-grid passport-edit-section">
                        <span class="passport-edit-section-title">🏢 Данные организации</span>
                        
                        <label class="passport-edit-label">Название организации</label>
                        <input type="text" name="company_name" 
                               value="<?= e($dynamicPassportData['company_name'] ?? $editDefaultCompanyName) ?>"
                               placeholder="ОАО «Полесьеэлектромаш»"
                               class="passport-edit-control passport-edit-full">
                        
                        <label class="passport-edit-label">Адрес</label>
                        <input type="text" name="company_address" 
                               value="<?= e($dynamicPassportData['company_address'] ?? $editDefaultCompanyAddress) ?>"
                               placeholder="225644, Брестская область, г. Лунинец, ул. Красная, 179"
                               class="passport-edit-control passport-edit-full">
                        
                        <label class="passport-edit-label">Телефон</label>
                        <input type="text" name="company_phone" 
                               value="<?= e($dynamicPassportData['company_phone'] ?? $editDefaultCompanyPhone) ?>"
                               placeholder="+375 1647 2-78-09"
                               class="passport-edit-control">
                        
                        <label class="passport-edit-label">E-mail</label>
                        <input type="email" name="company_email" 
                               value="<?= e($dynamicPassportData['company_email'] ?? $editDefaultCompanyEmail) ?>"
                               placeholder="polesie@polesieelectromash.by"
                               class="passport-edit-control">
                    </div>
                    
                    <div class="passport-edit-grid passport-edit-section">
                        <span class="passport-edit-section-title">📦 Информация об изделии</span>
                        
                        <label class="passport-edit-label">Наименование изделия (кастомное)</label>
                        <input type="text" name="product_name_custom" 
                               value="<?= e($dynamicPassportData['product_name_custom'] ?? '') ?>"
                               placeholder="Оставьте пустым для использования стандартного названия"
                               class="passport-edit-control passport-edit-full">
                        
                        <label class="passport-edit-label">Технические характеристики продукта (JSON)</label>
                        <textarea name="product_specifications" rows="8" 
                                  id="editProductSpecs"
                                  class="passport-edit-control passport-edit-full"
                        ><?= e(json_encode($productSpecs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea>
                    </div>
                    
                    <div class="passport-edit-grid passport-edit-section">
                        <span class="passport-edit-section-title">⚙️ Технические характеристики</span>
                        
                        <label class="passport-edit-label">Индивидуальные технические характеристики (JSON)</label>
                        <textarea name="technical_specs" rows="8" 
                                  id="editTechnicalSpecs"
                                  class="passport-edit-control passport-edit-full"
                        ><?= e(json_encode($technicalSpecs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea>
                        
                        <label class="passport-edit-label">Примечания</label>
                        <textarea name="notes" rows="3" 
                                  class="passport-edit-control passport-edit-full"><?= e($serialData['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                        <button type="button" onclick="closeEditPassportModalDirect()" class="btn btn-outline">Отмена</button>
                        <button type="submit" class="btn btn-primary">💾 Сохранить изменения</button>
                    </div>
                </form>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border-color);">
                
                <h4 style="margin-bottom: 12px;">Загрузить документ</h4>
                <form method="POST" action="api_passport.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_document">
                    <input type="hidden" name="serial_id" value="<?= $id ?>">
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Тип документа</label>
                        <select name="document_type" required 
                                style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 14px;">
                            <option value="manual">Руководство по эксплуатации</option>
                            <option value="certificate">Сертификат</option>
                            <option value="test_report">Отчёт о тестировании</option>
                            <option value="warranty_card">Гарантийный талон</option>
                            <option value="other">Другое</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Файл</label>
                        <input type="file" name="document_file" required 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                               style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 14px;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Загрузить</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Функция для открытия модального окна материала
        function openMaterialModal(materialData) {
            const modal = document.getElementById('materialInfoModal');
            
            // Заполняем данные в модальном окне
            document.getElementById('modalMaterialCode').textContent = materialData.code || '—';
            document.getElementById('modalMaterialName').textContent = materialData.name_full || '—';
            document.getElementById('modalMaterialCategory').textContent = materialData.category || '—';
            document.getElementById('modalMaterialQuantity').textContent = 
                (materialData.quantity ? parseFloat(materialData.quantity).toFixed(3) : '0') + ' ' + (materialData.unit || '');
            document.getElementById('modalMaterialDescription').textContent = materialData.description || 'Нет описания';
            
            // Заполняем спецификации
            const specsContainer = document.getElementById('modalMaterialSpecs');
            specsContainer.innerHTML = '';
            
            if (materialData.specifications && Object.keys(materialData.specifications).length > 0) {
                const specLabels = {
                    'grade': 'Марка',
                    'type': 'Стандарт',
                    'gost': 'ГОСТ',
                    'diameter_mm': 'Диаметр',
                    'length_m': 'Длина',
                    'thickness_mm': 'Толщина',
                    'width_mm': 'Ширина',
                    'strength_class': 'Кл. прочности',
                    'coating': 'Покрытие',
                    'material_type': 'Тип',
                    'потребляемая_мощность': 'Потребляемая мощность',
                    'напряжение_в': 'Напряжение',
                    'мощность_квт': 'Мощность',
                    'частота_гц': 'Частота',
                    'обороты_об_мин': 'Обороты'
                };
                
                const specUnits = {
                    'diameter_mm': 'мм',
                    'length_m': 'м',
                    'thickness_mm': 'мм',
                    'width_mm': 'мм',
                    'потребляемая_мощность': 'Вт',
                    'напряжение_в': 'В',
                    'мощность_квт': 'кВт',
                    'частота_гц': 'Гц',
                    'обороты_об_мин': 'об/мин'
                };
                
                for (const [key, value] of Object.entries(materialData.specifications)) {
                    if (value && value !== '') {
                        const label = specLabels[key] || key;
                        const unit = specUnits[key] || '';
                        const displayValue = Array.isArray(value) ? value.join(', ') : value;
                        
                        const specItem = document.createElement('div');
                        specItem.className = 'spec-row';
                        specItem.innerHTML = `
                            <span class="spec-label">${label}:</span>
                            <span class="spec-value">${displayValue}${unit ? ' ' + unit : ''}</span>
                        `;
                        specsContainer.appendChild(specItem);
                    }
                }
            } else {
                specsContainer.innerHTML = '<p style="color: var(--text-secondary); padding: 12px 0;">Спецификации не указаны</p>';
            }
            
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        
        function closeMaterialModal(event) {
            if (event.target === event.currentTarget) {
                const modal = document.getElementById('materialInfoModal');
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function closeMaterialModalDirect() {
            const modal = document.getElementById('materialInfoModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        function openEditPassportModal() {
            const modal = document.getElementById('editPassportModal');
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        
        function closeEditPassportModal(event) {
            if (event.target === event.currentTarget) {
                const modal = document.getElementById('editPassportModal');
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function closeEditPassportModalDirect() {
            const modal = document.getElementById('editPassportModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Функции для модального окна предпросмотра печати
        function openPrintPreviewModal() {
            const modal = document.getElementById('printPreviewModal');
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        
        function closePrintPreviewModal(event) {
            if (event.target === event.currentTarget) {
                const modal = document.getElementById('printPreviewModal');
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function closePrintPreviewModalDirect() {
            const modal = document.getElementById('printPreviewModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Обработка кнопки "Сохранить и печатать"
        document.addEventListener('DOMContentLoaded', function() {
            // Обработчик для формы редактирования паспорта
            const editPassportForm = document.getElementById('editPassportForm');
            if (editPassportForm) {
                editPassportForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(editPassportForm);
                    
                    // Сохраняем данные через AJAX
                    fetch('api_passport.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Паспорт успешно обновлён!');
                            closeEditPassportModalDirect();
                            // Перезагружаем страницу для отображения изменений
                            location.reload();
                        } else {
                            alert('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        alert('Ошибка соединения с сервером: ' + error.message);
                    });
                });
            }
            
            // Обработчик для кнопки "Сохранить и печатать"
            const saveAndPrintBtn = document.getElementById('saveAndPrintBtn');
            if (saveAndPrintBtn) {
                saveAndPrintBtn.addEventListener('click', function() {
                    const printForm = document.getElementById('printPreviewForm');
                    if (!printForm) return;
                    
                    const formData = new FormData(printForm);
                    const serialId = formData.get('serial_id');
                    
                    // Сохраняем данные через AJAX
                    fetch('api_passport.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Закрываем модальное окно
                            closePrintPreviewModalDirect();
                            // Открываем печать в новой вкладке
                            window.open('?id=' + serialId + '&print=1', '_blank');
                        } else {
                            alert('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        alert('Ошибка: ' + error.message);
                    });
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
