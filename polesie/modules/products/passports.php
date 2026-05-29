<?php
/**
 * Паспорта продуктов - просмотр спецификаций и материалов из БД
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Паспорта продуктов';

// Получение всех активных продуктов с паспортами (или без них)
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$query = "
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
    FROM products p
    LEFT JOIN product_passports pp ON pp.product_id = p.id
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.is_active = TRUE
";

$params = [];

if ($search) {
    $query .= " AND (p.article LIKE :search OR p.name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($categoryFilter) {
    $query .= " AND pc.code = :category";
    $params[':category'] = $categoryFilter;
}

$query .= " ORDER BY pc.name, p.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$passports = $stmt->fetchAll();

// Для продуктов без паспорта создаем запись
foreach ($passports as &$passport) {
    if ($passport['passport_id'] === null) {
        // Создаем паспорт для продукта
        $insertStmt = $pdo->prepare("
            INSERT INTO product_passports (product_id, total_weight_kg, warranty_months, is_serial_tracked)
            VALUES (:product_id, 0, 12, FALSE)
        ");
        $insertStmt->execute([':product_id' => $passport['product_id']]);
        
        // Получаем ID созданного паспорта
        $passport['passport_id'] = $pdo->lastInsertId();
        $passport['total_weight_kg'] = 0;
        $passport['warranty_months'] = 12;
        $passport['is_serial_tracked'] = false;
        $passport['production_notes'] = null;
        $passport['quality_requirements'] = null;
    }
}
unset($passport);

// Получение уникальных категорий для фильтра
$catQuery = "SELECT DISTINCT pc.code, pc.name 
             FROM product_categories pc
             JOIN products p ON p.category_id = pc.id
             WHERE p.is_active = TRUE
             ORDER BY pc.name";
$categories = $pdo->query($catQuery)->fetchAll();

$totalProducts = count($passports);
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
        .passport-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .passport-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .passport-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .passport-sku {
            font-family: 'Courier New', monospace;
            background: var(--primary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        .passport-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 8px 0 4px;
        }
        .passport-category {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .passport-card-body {
            padding: 16px 20px;
        }
        .materials-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .material-tag {
            background: var(--bg-tertiary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        .material-tag strong {
            color: var(--text-primary);
        }
        .stats-bar {
            display: flex;
            gap: 24px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-item {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 16px 24px;
            border-radius: var(--border-radius);
            min-width: 150px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 4px;
        }
        .passport-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .passport-modal-overlay.active {
            display: flex;
        }
        .passport-modal {
            background: white;
            border-radius: var(--border-radius);
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .passport-modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        .passport-modal-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .passport-modal-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        .passport-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 28px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.2s;
        }
        .passport-modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        .passport-modal-body {
            padding: 24px;
        }
        .passport-section {
            margin-bottom: 24px;
        }
        .passport-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .production-flow {
            position: relative;
            padding-left: 20px;
        }
        .production-flow::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary-color), var(--success-color));
            border-radius: 3px;
        }
        .stage-item {
            position: relative;
            padding: 16px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stage-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .stage-item::before {
            content: attr(data-step);
            position: absolute;
            left: -34px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .stage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .stage-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .stage-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .stage-description {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .stage-details {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            padding-top: 12px;
            border-top: 1px dashed var(--border-color);
        }
        .stage-detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        .materials-grouped {
            margin-bottom: 20px;
        }
        .material-category-header {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            padding: 10px 12px;
            background: var(--bg-secondary);
            border-radius: 8px 8px 0 0;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .material-category-header:first-child {
            margin-top: 0;
        }
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }
        .materials-table th {
            text-align: left;
            padding: 12px;
            background: var(--bg-secondary);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .materials-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .materials-table tr:hover {
            background: var(--bg-tertiary);
        }
        .notes-list, .requirements-list {
            list-style: none;
            padding: 0;
        }
        .notes-list li, .requirements-list li {
            padding: 8px 12px;
            background: var(--bg-tertiary);
            margin-bottom: 8px;
            border-radius: 6px;
            font-size: 14px;
        }
        .notes-list li::before {
            content: '📋 ';
        }
        .requirements-list li::before {
            content: '✓ ';
        }
        .specs-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .spec-item {
            background: var(--bg-tertiary);
            padding: 12px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .spec-name {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .spec-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            text-align: right;
        }
        .weight-badge {
            background: var(--success-color);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-primary);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .empty-state p {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="content">
                    <div class="page-header">
                        <div class="page-header-title">
                            <h2>📄 Паспорта продуктов</h2>
                            <p>Спецификации и материалы для производства</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <!-- Статистика -->
                            <div class="stats-bar">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $totalProducts ?></div>
                                    <div class="stat-label">Всего продуктов</div>
                                </div>
                                <div class="stat-item" style="background: linear-gradient(135deg, #10b981, #059669);">
                                    <div class="stat-value"><?= count($categories) ?></div>
                                    <div class="stat-label">Категорий</div>
                                </div>
                            </div>

                            <!-- Фильтры -->
                            <form method="GET" class="filter-form">
                                <div class="filter-row">
                                    <input type="text" name="search" placeholder="Поиск по SKU или названию..." value="<?= e($search) ?>" 
                                           style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                    <select name="category" style="width: 250px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                                        <option value="">Все категории</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= e($cat['code']) ?>" <?= $categoryFilter == $cat['code'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-secondary">Фильтр</button>
                                    <a href="passports.php" class="btn btn-outline">Сброс</a>
                                </div>
                            </form>

                            <!-- Список паспортов -->
                            <div style="margin-top: 20px;">
                                <?php if (empty($passports)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📄</div>
                                        <h3>Паспорта не найдены</h3>
                                        <p>Паспорта продуктов создаются в базе данных или измените параметры поиска</p>
                                    </div>
                                <?php else: ?>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 16px;">
                                        <?php foreach ($passports as $index => $passport): ?>
                                            <?php
                                            // Получение материалов для паспорта
                                            $matStmt = $pdo->prepare("
                                                SELECT 
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
                                            $matStmt->execute([':passport_id' => $passport['passport_id']]);
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
                                            $productionNotes = $passport['production_notes'] ? json_decode($passport['production_notes'], true) : [];
                                            $qualityRequirements = $passport['quality_requirements'] ? json_decode($passport['quality_requirements'], true) : [];
                                            $specifications = $passport['specifications'] ? json_decode($passport['specifications'], true) : [];
                                            ?>
                                            <div class="passport-card" data-passport-id="<?= $passport['passport_id'] ?>" onclick="openPassportModal(this)">
                                                <div class="passport-card-header">
                                                    <div>
                                                        <span class="passport-sku"><?= e($passport['sku']) ?></span>
                                                        <div class="passport-title"><?= e($passport['product_name']) ?></div>
                                                        <div class="passport-category"><?= e($passport['category_name'] ?? 'Без категории') ?></div>
                                                    </div>
                                                </div>
                                                <div class="passport-card-body">
                                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                                        <span class="weight-badge">⚖️ <?= number_format($passport['total_weight_kg'] ?? 0, 2, ',', ' ') ?> кг</span>
                                                        <span style="font-size: 13px; color: var(--text-secondary);">
                                                            📦 <?= count($materials) ?> материалов
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="materials-preview">
                                                        <?php 
                                                        $previewMaterials = array_slice($materials, 0, 3);
                                                        foreach ($previewMaterials as $mat): 
                                                        ?>
                                                            <span class="material-tag">
                                                                <strong><?= number_format($mat['quantity'], 1) ?></strong> <?= e($mat['unit']) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($materials) > 3): ?>
                                                            <span class="material-tag">+ ещё <?= count($materials) - 3 ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно паспорта -->
    <div id="passportModalOverlay" class="passport-modal-overlay" onclick="closePassportModal(event)">
        <div class="passport-modal">
            <div class="passport-modal-header">
                <div>
                    <div class="passport-modal-title" id="modalPassportTitle">Название продукта</div>
                    <div class="passport-modal-subtitle" id="modalPassportSKU">SKU: —</div>
                </div>
                <button class="passport-modal-close" onclick="closePassportModalDirect()">×</button>
            </div>
            <div class="passport-modal-body" id="modalPassportBody">
                <!-- Контент заполняется через JS -->
            </div>
        </div>
    </div>

    <script>
        // Данные для паспортов передаем через PHP в JS
        console.log('=== PASSPORTS DEBUG ===');
        console.log('Passports count:', <?= count($passports) ?>);
        
        // Функция открытия модального окна с данными из БД
        function openPassportModal(element) {
            var passportId = element.getAttribute('data-passport-id');
            if (!passportId) {
                console.error('passport_id не найден');
                return;
            }
            
            // Загружаем данные паспорта через AJAX
            fetch('api_passport.php?id=' + passportId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderPassportModal(data.passport);
                    } else {
                        alert('Ошибка загрузки паспорта: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ошибка загрузки данных паспорта');
                });
        }
        
        function renderPassportModal(passport) {
            console.log('Opening passport:', passport.sku);
            console.log('Full passport data:', passport);
            
            document.getElementById('modalPassportTitle').textContent = passport.product_name || 'Без названия';
            document.getElementById('modalPassportSKU').textContent = 'SKU: ' + (passport.sku || '—');
            
            var html = '';
            
            // Основная информация
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📊 Основная информация</div>';
            html += '<div class="specs-list">';
            html += '<div class="spec-item"><div class="spec-name">Артикул</div><div class="spec-value">' + escapeHtml(passport.sku) + '</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Категория</div><div class="spec-value">' + escapeHtml(passport.category_name || '—') + '</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Общий вес</div><div class="spec-value">' + (passport.total_weight_kg || '0') + ' кг</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Гарантия</div><div class="spec-value">' + (passport.warranty_months || 24) + ' мес.</div></div>';
            html += '<div class="spec-item"><div class="spec-name">Серийный учёт</div><div class="spec-value">' + (passport.is_serial_tracked ? '✅ Да' : '❌ Нет') + '</div></div>';
            html += '</div>';
            html += '</div>';
            
            // Характеристики
            if (passport.specifications && Object.keys(passport.specifications).length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">⚙️ Технические характеристики</div>';
                html += '<div class="specs-list">';
                for (var key in passport.specifications) {
                    if (passport.specifications.hasOwnProperty(key)) {
                        var value = passport.specifications[key];
                        if (Array.isArray(value)) {
                            value = value.join(', ');
                        }
                        html += '<div class="spec-item">';
                        html += '<div class="spec-name">' + escapeHtml(formatSpecName(key)) + '</div>';
                        html += '<div class="spec-value">' + escapeHtml(String(value)) + '</div>';
                        html += '</div>';
                    }
                }
                html += '</div>';
                html += '</div>';
            }
            
            // Этапы производства из БД - красивый поток производства
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">🏭 Карта производства</div>';
            if (passport.stages && passport.stages.length > 0) {
                html += '<div class="production-flow">';
                for (var i = 0; i < passport.stages.length; i++) {
                    var stage = passport.stages[i];
                    var stepNum = i + 1;
                    html += '<div class="stage-item" data-step="' + stepNum + '">';
                    html += '<div class="stage-header">';
                    html += '<div class="stage-name">' + escapeHtml(stage.operation_name || 'Операция ' + stepNum) + '</div>';
                    if (stage.stage_color) {
                        html += '<span class="stage-badge" style="background: ' + stage.stage_color + '; color: white;">' + escapeHtml(stage.stage_name || 'Этап') + '</span>';
                    } else if (stage.stage_name) {
                        html += '<span class="stage-badge" style="background: #3498db; color: white;">' + escapeHtml(stage.stage_name) + '</span>';
                    }
                    html += '</div>';
                    if (stage.description) {
                        html += '<div class="stage-description">' + escapeHtml(stage.description) + '</div>';
                    }
                    html += '<div class="stage-details">';
                    if (stage.work_center) {
                        html += '<div class="stage-detail-item">📍 ' + escapeHtml(stage.work_center) + '</div>';
                    }
                    if (stage.equipment) {
                        html += '<div class="stage-detail-item">🔧 ' + escapeHtml(stage.equipment) + '</div>';
                    }
                    if (stage.time_norm_hours) {
                        html += '<div class="stage-detail-item">⏱️ ' + parseFloat(stage.time_norm_hours).toFixed(1) + ' ч</div>';
                    }
                    if (stage.operation_number) {
                        html += '<div class="stage-detail-item">№ ' + escapeHtml(stage.operation_number) + '</div>';
                    }
                    html += '</div>';
                    html += '</div>';
                }
                html += '</div>';
            } else {
                html += '<div style="padding: 20px; text-align: center; color: var(--text-secondary); background: var(--bg-tertiary); border-radius: 8px;">';
                html += 'ℹ️ Этапы производства не заданы в маршрутной карте';
                html += '</div>';
            }
            html += '</div>';
            
            // Материалы из БД - сгруппированные по категориям
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📦 Материалы для производства</div>';
            if (passport.materials && passport.materials.length > 0) {
                // Группируем материалы по категориям
                var materialsByCategory = {};
                for (var i = 0; i < passport.materials.length; i++) {
                    var mat = passport.materials[i];
                    var catName = mat.material_category || 'Прочее';
                    if (!materialsByCategory[catName]) {
                        materialsByCategory[catName] = [];
                    }
                    materialsByCategory[catName].push(mat);
                }
                
                var categoryIcons = {
                    'Металлы': '🔩',
                    'Крепеж': '🔧',
                    'Электроника': '⚡',
                    'Пластик': '🧪',
                    'Резина': '⭕',
                    'Упаковка': '📦',
                    'Прочее': '📋'
                };
                
                for (var catName in materialsByCategory) {
                    if (materialsByCategory.hasOwnProperty(catName)) {
                        var icon = categoryIcons[catName] || '📋';
                        html += '<div class="materials-grouped">';
                        html += '<div class="material-category-header">' + icon + ' ' + escapeHtml(catName) + ' (' + materialsByCategory[catName].length + ' поз.)</div>';
                        html += '<table class="materials-table">';
                        html += '<thead><tr><th>№</th><th>Материал</th><th>Код</th><th>Количество</th><th>Ед.</th></tr></thead>';
                        html += '<tbody>';
                        for (var j = 0; j < materialsByCategory[catName].length; j++) {
                            var mat = materialsByCategory[catName][j];
                            var globalIdx = i + j + 1;
                            html += '<tr>';
                            html += '<td>' + (j + 1) + '</td>';
                            html += '<td><strong>' + escapeHtml(mat.material_name) + '</strong></td>';
                            html += '<td><code>' + escapeHtml(mat.material_code) + '</code></td>';
                            html += '<td>' + Number(mat.quantity).toFixed(2).replace('.', ',') + '</td>';
                            html += '<td>' + escapeHtml(mat.unit) + '</td>';
                            html += '</tr>';
                        }
                        html += '</tbody>';
                        html += '</table>';
                        html += '</div>';
                    }
                }
            } else {
                html += '<div style="padding: 20px; text-align: center; color: var(--text-secondary); background: var(--bg-tertiary); border-radius: 8px;">';
                html += 'ℹ️ Материалы не указаны в паспорте';
                html += '</div>';
            }
            html += '</div>';
            
            // Фактически использованные материалы
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">✅ Фактически использовано в производстве</div>';
            if (passport.used_materials && passport.used_materials.length > 0) {
                html += '<div style="margin-bottom: 16px; padding: 12px; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">';
                html += '<div style="font-size: 13px; color: #2e7d32;">';
                html += '💡 Данные получены из производственных заданий и показывают реальный расход материалов. ';
                html += 'При запуске производства материалы автоматически списываются со склада.';
                html += '</div>';
                html += '</div>';
                
                html += '<table class="materials-table">';
                html += '<thead><tr><th>Материал</th><th>Код</th><th>План</th><th>Факт</th><th>Отклонение</th><th>Ед.</th><th>Задания</th></tr></thead>';
                html += '<tbody>';
                for (var i = 0; i < passport.used_materials.length; i++) {
                    var um = passport.used_materials[i];
                    var planQty = Number(um.total_required).toFixed(2).replace('.', ',');
                    var factQty = Number(um.total_used).toFixed(2).replace('.', ',');
                    var deviation = um.total_required > 0 ? ((um.total_used - um.total_required) / um.total_required * 100).toFixed(1) : 0;
                    var deviationClass = Math.abs(deviation) > 10 ? 'color: #f44336; font-weight: 600;' : 'color: #4caf50;';
                    var deviationSign = deviation > 0 ? '+' : '';
                    var tasksList = um.tasks.slice(0, 3).join(', ') + (um.tasks.length > 3 ? '...' : '');
                    
                    html += '<tr>';
                    html += '<td><strong>' + escapeHtml(um.material_name) + '</strong></td>';
                    html += '<td><code>' + escapeHtml(um.material_code) + '</code></td>';
                    html += '<td>' + planQty + '</td>';
                    html += '<td style="font-weight: 600; color: #1976d2;">' + factQty + '</td>';
                    html += '<td style="' + deviationClass + '">' + deviationSign + deviation + '%</td>';
                    html += '<td>' + escapeHtml(um.unit_name) + '</td>';
                    html += '<td style="font-size: 12px; color: var(--text-secondary);">' + escapeHtml(tasksList) + ' (всего: ' + um.tasks_count + ')</td>';
                    html += '</tr>';
                }
                html += '</tbody>';
                html += '</table>';
            } else {
                html += '<div style="padding: 20px; text-align: center; color: var(--text-secondary); background: var(--bg-tertiary); border-radius: 8px;">';
                html += 'ℹ️ Производство еще не запускалось или материалы не были списаны';
                html += '</div>';
            }
            html += '</div>';
            
            // Примечания к производству
            if (passport.production_notes && passport.production_notes.length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📋 Примечания к производству</div>';
                html += '<ul class="notes-list">';
                for (var i = 0; i < passport.production_notes.length; i++) {
                    html += '<li>' + escapeHtml(passport.production_notes[i]) + '</li>';
                }
                html += '</ul>';
                html += '</div>';
            }
            
            // Требования к качеству
            if (passport.quality_requirements && passport.quality_requirements.length > 0) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">✅ Требования к качеству</div>';
                html += '<ul class="requirements-list">';
                for (var i = 0; i < passport.quality_requirements.length; i++) {
                    html += '<li>' + escapeHtml(passport.quality_requirements[i]) + '</li>';
                }
                html += '</ul>';
                html += '</div>';
            }
            
            document.getElementById('modalPassportBody').innerHTML = html;
            document.getElementById('passportModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function formatSpecName(key) {
            var names = {
                'power_kw_min': 'Мощность мин., кВт',
                'power_kw_max': 'Мощность макс., кВт',
                'rpm': 'Обороты, об/мин',
                'shaft_height_mm': 'Высота оси, мм',
                'voltage_v': 'Напряжение, В',
                'frequency_hz': 'Частота, Гц',
                'climate_versions': 'Климатическое исполнение',
                'mounting_versions': 'Варианты монтажа',
                'protection_class': 'Класс защиты',
                'power_kw': 'Мощность, кВт',
                'diameter_mm': 'Диаметр, мм',
                'material': 'Материал',
                'application': 'Применение',
                'type': 'Тип'
            };
            return names[key] || key;
        }
        
        function closePassportModal(event) {
            if (event.target === document.getElementById('passportModalOverlay')) {
                closePassportModalDirect();
            }
        }
        
        function closePassportModalDirect() {
            document.getElementById('passportModalOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('passportModalOverlay');
                if (modal.classList.contains('active')) {
                    closePassportModalDirect();
                }
            }
        });
    </script>
</body>
</html>
