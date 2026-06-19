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

// Функция для перевода категории материала на русский язык
function getCategoryName($category) {
    $translations = [
        'raw' => 'Сырьё',
        'packaging' => 'Упаковка',
        'label' => 'Этикетка',
        'box' => 'Коробка',
        'component' => 'Компонент',
        'other' => 'Прочее'
    ];
    return $translations[$category] ?? ($category ?: '—');
}

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

// Получение позиций заказа с материалами
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

// Получение материалов для каждой позиции заказа
$itemsWithMaterials = [];
$allMaterials = [];
$totalMaterialRows = 0;
$productsForFilter = [];

foreach ($items as $item) {
    $itemData = $item;
    $itemData['materials'] = [];
    
    if (!empty($item['product_id'])) {
        // Добавляем товар в список для фильтра
        $productsForFilter[] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'article' => $item['article'],
            'quantity' => $item['quantity'],
            'unit_name' => $item['unit_name']
        ];
        
        // Получаем материалы из паспорта продукта
        $matStmt = $pdo->prepare("
            SELECT 
                ppm.quantity * oi.quantity as total_quantity,
                ppm.quantity as unit_quantity,
                bu.symbol as unit,
                m.id as material_id,
                m.code as material_code,
                m.name_full as material_name,
                m.name_short as material_short,
                mc.name as material_category,
                oi.product_id,
                p.name as product_name,
                p.article as product_article
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN product_passport_materials ppm ON ppm.passport_id = (
                SELECT id FROM product_passports WHERE product_id = oi.product_id
            )
            JOIN materials m ON ppm.material_id = m.id
            LEFT JOIN material_categories mc ON m.category_id = mc.id
            LEFT JOIN base_units bu ON ppm.unit_id = bu.id
            WHERE oi.order_id = ? AND oi.product_id = ?
            ORDER BY ppm.sort_order, m.name_full
        ");
        $matStmt->execute([$orderId, $item['product_id']]);
        $materials = $matStmt->fetchAll();
        $itemData['materials'] = $materials;
        
        // Собираем все материалы для общей таблицы
        foreach ($materials as $mat) {
            $allMaterials[] = $mat;
            $totalMaterialRows++;
        }
    }
    
    $itemsWithMaterials[] = $itemData;
}

// Группировка материалов по коду для суммарной таблицы
$groupedMaterials = [];
foreach ($allMaterials as $mat) {
    $key = $mat['material_id'];
    if (!isset($groupedMaterials[$key])) {
        $groupedMaterials[$key] = [
            'material_id' => $mat['material_id'],
            'material_code' => $mat['material_code'],
            'material_name' => $mat['material_name'],
            'material_short' => $mat['material_short'],
            'material_category' => $mat['material_category'],
            'unit' => $mat['unit'],
            'total_quantity' => 0
        ];
    }
    $groupedMaterials[$key]['total_quantity'] += $mat['total_quantity'];
}

$pageTitle = 'Заказ №' . e($order['order_number']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        .materials-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .materials-section-header {
            background: #f8f9fa;
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .product-materials-block {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin: 16px 20px;
            overflow: hidden;
        }
        .product-materials-header {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            padding: 14px 16px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .product-materials-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }
        .product-materials-info {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .materials-table-wrapper {
            padding: 0;
            overflow-x: auto;
        }
        .materials-table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .materials-table-custom th {
            background: #fafbfc;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
        }
        .materials-table-custom td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }
        .materials-table-custom tr:last-child td {
            border-bottom: none;
        }
        .materials-table-custom tr:hover {
            background: #f8f9fa;
        }
        .material-code-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .material-code-badge:hover {
            background: #1976d2;
            color: white;
        }
        .material-name-cell {
            font-weight: 500;
            color: var(--text-primary);
        }
        .material-category-cell {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .material-qty-cell {
            text-align: right;
            font-weight: 600;
            color: var(--primary-color);
        }
        .material-unit-cell {
            text-align: left;
            color: var(--text-secondary);
            font-size: 12px;
        }
        .no-materials-notice {
            padding: 20px;
            text-align: center;
            color: var(--text-secondary);
            background: #f8f9fa;
            border-radius: 8px;
            margin: 16px 20px;
        }
        .materials-filter-section {
            padding: 16px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .materials-filter-label {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }
        .materials-filter-select {
            min-width: 250px;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        .materials-filter-select:hover {
            border-color: var(--primary-color);
        }
        .materials-filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .total-materials-summary {
            margin-left: auto;
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .summary-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .material-code-cell {
            white-space: nowrap;
        }
        .material-code-badge-inline {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }
        .material-code-badge-inline:hover {
            background: #1976d2;
            color: white;
        }
        .product-selector-btn {
            background: white;
            border: 1px solid #ced4da;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        .product-selector-btn:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
        }
        .product-selector-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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
                            <i class="bi bi-file-text-fill" style="margin-right: 8px; vertical-align: middle;"></i>
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
                            <i class="bi bi-building-fill" style="margin-right: 8px; vertical-align: middle;"></i>
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
                            <i class="bi bi-box-seam-fill" style="margin-right: 8px;"></i>
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
                
                <!-- Материалы для производства -->
                <div class="materials-section">
                    <div class="materials-filter-section">
                        <span class="materials-filter-label"><i class="bi bi-funnel" style="margin-right: 8px; font-size: 1.2em;"></i> Показать материалы для:</span>
                        <select class="materials-filter-select" id="productFilter" onchange="filterMaterials()">
                            <option value="all">Все товары (суммарно)</option>
                            <?php foreach ($productsForFilter as $idx => $product): ?>
                                <option value="<?= $idx ?>">
                                    <?= e($product['product_name']) ?> (арт. <?= e($product['article'] ?? '—') ?>) - <?= number_format($product['quantity'], 0, ',', ' ') ?> <?= e($product['unit_name'] ?? 'шт.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if (count($groupedMaterials) > 0): ?>
                        <div class="total-materials-summary">
                            <span class="summary-badge"><i class="bi bi-box-seam" style="margin-right: 6px;"></i>Всего уникальных материалов: <?= count($groupedMaterials) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="materials-section-header" style="border-bottom: none;">
                        <i class="bi bi-pie-chart-fill" style="margin-right: 8px;"></i>
                        Расход материалов
                    </div>
                    
                    <?php if (count($groupedMaterials) > 0): ?>
                    <div class="materials-table-wrapper" style="padding: 0 20px;">
                        <table class="materials-table-custom">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">№</th>
                                    <th style="width: 120px;">Артикул</th>
                                    <th>Наименование материала</th>
                                    <th style="width: 150px;">Категория</th>
                                    <th style="width: 100px; text-align: right;">Всего</th>
                                    <th style="width: 60px;">Ед.</th>
                                </tr>
                            </thead>
                            <tbody id="materialsTableBody">
                                <!-- Суммарная таблица (все товары) -->
                                <tr class="material-row" data-product-index="all">
                                    <td colspan="6" style="background: #e8f5e9; font-weight: 600; color: #2e7d32; text-align: center; padding: 12px;">
                                        📊 Общие затраты материалов на весь заказ
                                    </td>
                                </tr>
                                <?php 
                                $globalIndex = 0;
                                foreach ($groupedMaterials as $material): 
                                    $globalIndex++;
                                ?>
                                <tr class="material-row" data-product-index="all">
                                    <td><?= $globalIndex ?></td>
                                    <td class="material-code-cell">
                                        <span class="material-code-badge-inline" onclick="event.stopPropagation(); window.location.href='../warehouse/materials.php?material=<?= $material['material_id'] ?>'"><?= e($material['material_code']) ?></span>
                                    </td>
                                    <td>
                                        <div class="material-name-cell"><?= e($material['material_name']) ?></div>
                                        <?php if (!empty($material['material_short'])): ?>
                                        <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;"><?= e($material['material_short']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="material-category-cell"><?= getCategoryName($material['material_category'] ?? '') ?></span>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: var(--primary-color);">
                                        <?= rtrim(rtrim(number_format($material['total_quantity'], 3, ',', ' '), '0'), ',') ?>
                                    </td>
                                    <td class="material-unit-cell">
                                        <?= e($material['unit'] ?? 'шт.') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <!-- Таблица по конкретным товарам -->
                                <?php foreach ($productsForFilter as $productIdx => $product): ?>
                                    <?php 
                                    // Находим материалы для этого продукта
                                    $productMaterials = [];
                                    foreach ($itemsWithMaterials as $item) {
                                        if ($item['product_id'] == $product['product_id']) {
                                            $productMaterials = $item['materials'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php if (!empty($productMaterials)): ?>
                                    <tr class="material-row" data-product-index="<?= $productIdx ?>" style="display: none;">
                                        <td colspan="6" style="background: #fff3e0; font-weight: 600; color: #ef6c00; text-align: center; padding: 12px;">
                                            📦 Материалы для: <?= e($product['product_name']) ?> (<?= rtrim(rtrim(number_format($product['quantity'], 0, ',', ' '), '0'), ',') ?> <?= e($product['unit_name'] ?? 'шт.') ?>)
                                        </td>
                                    </tr>
                                    <?php 
                                    $prodIndex = 0;
                                    foreach ($productMaterials as $material): 
                                        $prodIndex++;
                                    ?>
                                    <tr class="material-row" data-product-index="<?= $productIdx ?>" style="display: none;">
                                        <td><?= $prodIndex ?></td>
                                        <td class="material-code-cell">
                                            <span class="material-code-badge-inline" onclick="event.stopPropagation(); window.location.href='../warehouse/materials.php?material=<?= $material['material_id'] ?>'"><?= e($material['material_code']) ?></span>
                                        </td>
                                        <td>
                                            <div class="material-name-cell"><?= e($material['material_name']) ?></div>
                                            <?php if (!empty($material['material_short'])): ?>
                                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;"><?= e($material['material_short']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="material-category-cell"><?= getCategoryName($material['material_category'] ?? '') ?></span>
                                        </td>
                                        <td style="text-align: right; font-weight: 700; color: var(--primary-color);">
                                            <?= rtrim(rtrim(number_format($material['total_quantity'], 3, ',', ' '), '0'), ',') ?>
                                        </td>
                                        <td class="material-unit-cell">
                                            <?= e($material['unit'] ?? 'шт.') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="no-materials-notice">
                        ℹ️ Для товаров в этом заказе не указаны материалы в паспортах продукции
                    </div>
                    <?php endif; ?>
                </div>
                
                <script>
                function filterMaterials() {
                    const filterValue = document.getElementById('productFilter').value;
                    const rows = document.querySelectorAll('.material-row');
                    
                    rows.forEach(row => {
                        const productIndex = row.dataset.productIndex;
                        if (filterValue === 'all') {
                            // Показываем только строки с data-product-index="all"
                            if (productIndex === 'all') {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        } else {
                            // Скрываем общую таблицу и показываем только строки выбранного продукта
                            if (productIndex === filterValue) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        }
                    });
                }
                </script>
                
                <!-- Кнопки действий -->
                <div class="action-buttons">
                    <a href="list.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left" style="margin-right: 6px;"></i>
                        Назад к списку
                    </a>
                    <a href="print_order.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-success">
                        <i class="bi bi-file-earmark-arrow-down-fill" style="margin-right: 6px;"></i>
                        Экспорт
                    </a>
                    <a href="../production/execute.php?order_id=<?= $order['id'] ?>" class="btn btn-success">
                        <i class="bi bi-tools" style="margin-right: 6px;"></i>
                        Перейти к производству
                    </a>
                    <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-primary">
                        <i class="bi bi-pencil-fill" style="margin-right: 6px;"></i>
                        Редактировать
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
