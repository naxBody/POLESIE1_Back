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

// Параметры фильтрации и сортировки
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$weightFilter = $_GET['weight'] ?? '';
$warrantyFilter = $_GET['warranty'] ?? '';
$serialFilter = $_GET['serial'] ?? '';
$sortBy = $_GET['sort'] ?? 'category';
$sortOrder = $_GET['order'] ?? 'asc';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;

// Проверка параметра product для прямого перехода к конкретному товару
$productIdFilter = isset($_GET['product']) ? (int)$_GET['product'] : null;
if ($productIdFilter) {
    $pageTitle = 'Паспорт продукта';
}

// Построение основного запроса
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

$countQuery = "
    SELECT COUNT(*) as total
    FROM products p
    LEFT JOIN product_passports pp ON pp.product_id = p.id
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.is_active = TRUE
";

$params = [];
$countParams = [];

// Фильтр по поиску
if ($search) {
    $query .= " AND (p.article LIKE :search1 OR p.name LIKE :search2)";
    $countQuery .= " AND (p.article LIKE :search1 OR p.name LIKE :search2)";
    $params['search1'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $countParams['search1'] = "%{$search}%";
    $countParams['search2'] = "%{$search}%";
}

// Фильтр по категории
if ($categoryFilter) {
    $query .= " AND pc.code = :category";
    $countQuery .= " AND pc.code = :category";
    $params['category'] = $categoryFilter;
    $countParams['category'] = $categoryFilter;
}

// Фильтр по весу
if ($weightFilter) {
    switch ($weightFilter) {
        case 'light':
            $query .= " AND pp.total_weight_kg < 5";
            $countQuery .= " AND pp.total_weight_kg < 5";
            break;
        case 'medium':
            $query .= " AND pp.total_weight_kg >= 5 AND pp.total_weight_kg < 20";
            $countQuery .= " AND pp.total_weight_kg >= 5 AND pp.total_weight_kg < 20";
            break;
        case 'heavy':
            $query .= " AND pp.total_weight_kg >= 20";
            $countQuery .= " AND pp.total_weight_kg >= 20";
            break;
    }
}

// Фильтр по гарантии
if ($warrantyFilter) {
    switch ($warrantyFilter) {
        case 'short':
            $query .= " AND pp.warranty_months <= 12";
            $countQuery .= " AND pp.warranty_months <= 12";
            break;
        case 'standard':
            $query .= " AND pp.warranty_months > 12 AND pp.warranty_months <= 36";
            $countQuery .= " AND pp.warranty_months > 12 AND pp.warranty_months <= 36";
            break;
        case 'long':
            $query .= " AND pp.warranty_months > 36";
            $countQuery .= " AND pp.warranty_months > 36";
            break;
    }
}

// Фильтр по серийному учёту
if ($serialFilter !== '') {
    if ($serialFilter === 'yes') {
        $query .= " AND pp.is_serial_tracked = TRUE";
        $countQuery .= " AND pp.is_serial_tracked = TRUE";
    } elseif ($serialFilter === 'no') {
        $query .= " AND pp.is_serial_tracked = FALSE";
        $countQuery .= " AND pp.is_serial_tracked = FALSE";
    }
}

// Фильтр по конкретному продукту (при переходе из заказа)
if ($productIdFilter) {
    $query .= " AND p.id = :product_id";
    $countQuery .= " AND p.id = :product_id";
    $params['product_id'] = $productIdFilter;
    $countParams['product_id'] = $productIdFilter;
}

// Сортировка
$allowedSorts = ['category', 'name', 'sku', 'weight', 'warranty'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'category';
}
$sortOrder = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';

switch ($sortBy) {
    case 'name':
        $query .= " ORDER BY p.name {$sortOrder}";
        break;
    case 'sku':
        $query .= " ORDER BY p.article {$sortOrder}";
        break;
    case 'weight':
        $query .= " ORDER BY pp.total_weight_kg {$sortOrder}";
        break;
    case 'warranty':
        $query .= " ORDER BY pp.warranty_months {$sortOrder}";
        break;
    case 'category':
    default:
        $query .= " ORDER BY pc.name {$sortOrder}, p.name ASC";
        break;
}

// Получение общего количества для пагинации (без LIMIT/OFFSET)
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$totalProducts = (int)$countStmt->fetch()['total'];
$totalPages = ceil($totalProducts / $perPage);
$page = min($page, $totalPages ?: 1);

// Добавление лимита и оффсета к основному запросу
$query .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)(($page - 1) * $perPage), PDO::PARAM_INT);
$stmt->execute();
$passports = $stmt->fetchAll();

// Для продуктов без паспорта создаем запись
foreach ($passports as &$passport) {
    if ($passport['passport_id'] === null) {
        // Создаем паспорт для продукта
        $insertStmt = $pdo->prepare("
            INSERT INTO product_passports (product_id, total_weight_kg, warranty_months, is_serial_tracked)
            VALUES (:product_id, 0, 12, FALSE)
        ");
        $insertStmt->execute(['product_id' => $passport['product_id']]);
        
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 12px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .passport-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: var(--primary-color);
        }
        .passport-card-header {
            padding: 12px 16px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .passport-sku {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .passport-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 6px 0 3px;
            line-height: 1.3;
        }
        .passport-category {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .passport-category::before {
            content: '📁';
            font-size: 11px;
        }
        .passport-card-body {
            padding: 12px 16px;
        }
        .card-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .weight-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }
        .materials-count {
            font-size: 12px;
            color: var(--text-secondary);
            background: var(--bg-tertiary);
            padding: 5px 10px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .materials-count::before {
            content: '📦';
            font-size: 11px;
        }
        .materials-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--border-color);
        }
        .material-tag {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            color: var(--text-secondary);
            border: 1px solid #cbd5e1;
            transition: all 0.2s;
        }
        .material-tag:hover {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            transform: scale(1.05);
        }
        .material-tag strong {
            color: var(--text-primary);
            font-weight: 600;
        }
        .stats-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .stat-item {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 14px 20px;
            border-radius: var(--border-radius);
            min-width: 140px;
            flex: 1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 26px;
            font-weight: 700;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.95;
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
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .materials-table th {
            text-align: left;
            padding: 10px 12px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--primary-color);
        }
        .materials-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 13px;
            vertical-align: middle;
        }
        .materials-table tr:last-child td {
            border-bottom: none;
        }
        .materials-table tr:hover {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
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
    <style>
        /* Стили для фильтров */
        .filters-panel {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        .filters-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .filters-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed var(--border-color);
        }
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .filter-chip-remove {
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .filter-chip-remove:hover {
            opacity: 1;
        }
        /* Сортировка */
        .sort-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
        }
        .sort-info {
            font-size: 14px;
            color: var(--text-secondary);
        }
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sort-select {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 13px;
            background: white;
            cursor: pointer;
        }
        .sort-order-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .sort-order-btn:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }
        /* Пагинация */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            padding: 16px;
        }
        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.2s;
        }
        .pagination a:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }
        .pagination .current {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-color: var(--primary-color);
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        /* Улучшенные карточки */
        .passport-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 12px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .passport-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: var(--primary-color);
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
                                <div class="stat-item" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <div class="stat-value"><?= $totalPages ?></div>
                                    <div class="stat-label">Страниц</div>
                                </div>
                            </div>

                            <!-- Панель фильтров -->
                            <div class="filters-panel">
                                <div class="filters-header">
                                    <span style="font-size: 20px;">🔍</span>
                                    <h3>Фильтры и поиск</h3>
                                </div>
                                
                                <form method="GET" id="filterForm">
                                    <div class="filters-grid">
                                        <!-- Поиск -->
                                        <div class="filter-group">
                                            <label for="search">🔎 Поиск</label>
                                            <input type="text" id="search" name="search" placeholder="SKU или название..." value="<?= e($search) ?>">
                                        </div>
                                        
                                        <!-- Категория -->
                                        <div class="filter-group">
                                            <label for="category">📁 Категория</label>
                                            <select id="category" name="category">
                                                <option value="">Все категории</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= e($cat['code']) ?>" <?= $categoryFilter == $cat['code'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Вес -->
                                        <div class="filter-group">
                                            <label for="weight">⚖️ Вес</label>
                                            <select id="weight" name="weight">
                                                <option value="">Любой вес</option>
                                                <option value="light" <?= $weightFilter === 'light' ? 'selected' : '' ?>>Лёгкие (&lt; 5 кг)</option>
                                                <option value="medium" <?= $weightFilter === 'medium' ? 'selected' : '' ?>>Средние (5-20 кг)</option>
                                                <option value="heavy" <?= $weightFilter === 'heavy' ? 'selected' : '' ?>>Тяжёлые (&gt; 20 кг)</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Гарантия -->
                                        <div class="filter-group">
                                            <label for="warranty">🛡️ Гарантия</label>
                                            <select id="warranty" name="warranty">
                                                <option value="">Любая</option>
                                                <option value="short" <?= $warrantyFilter === 'short' ? 'selected' : '' ?>>Короткая (≤ 1 год)</option>
                                                <option value="standard" <?= $warrantyFilter === 'standard' ? 'selected' : '' ?>>Стандарт (1-3 года)</option>
                                                <option value="long" <?= $warrantyFilter === 'long' ? 'selected' : '' ?>>Длинная (&gt; 3 лет)</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Серийный учёт -->
                                        <div class="filter-group">
                                            <label for="serial">📋 Серийный учёт</label>
                                            <select id="serial" name="serial">
                                                <option value="">Все</option>
                                                <option value="yes" <?= $serialFilter === 'yes' ? 'selected' : '' ?>>Требуется</option>
                                                <option value="no" <?= $serialFilter === 'no' ? 'selected' : '' ?>>Не требуется</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Сортировка -->
                                        <div class="filter-group">
                                            <label for="sort">🔀 Сортировка</label>
                                            <select id="sort" name="sort">
                                                <option value="category" <?= $sortBy === 'category' ? 'selected' : '' ?>>По категории</option>
                                                <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>По названию</option>
                                                <option value="sku" <?= $sortBy === 'sku' ? 'selected' : '' ?>>По артикулу</option>
                                                <option value="weight" <?= $sortBy === 'weight' ? 'selected' : '' ?>>По весу</option>
                                                <option value="warranty" <?= $sortBy === 'warranty' ? 'selected' : '' ?>>По гарантии</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Порядок сортировки -->
                                        <div class="filter-group">
                                            <label for="order">📊 Порядок</label>
                                            <select id="order" name="order">
                                                <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : '' ?>>⬆️ По возрастанию</option>
                                                <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>⬇️ По убыванию</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="filter-actions">
                                        <button type="submit" class="btn btn-primary">🔍 Применить фильтры</button>
                                        <a href="passports.php" class="btn btn-outline">🔄 Сбросить всё</a>
                                    </div>
                                    
                                    <!-- Активные фильтры -->
                                    <?php 
                                    $hasActiveFilters = $search || $categoryFilter || $weightFilter || $warrantyFilter || $serialFilter !== '';
                                    if ($hasActiveFilters): 
                                    ?>
                                    <div class="active-filters">
                                        <span style="font-size: 12px; color: var(--text-secondary); align-self: center;">Активные фильтры:</span>
                                        <?php if ($search): ?>
                                            <span class="filter-chip">
                                                🔎 «<?= e($search) ?>»
                                                <a href="?<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'search'; }, ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($categoryFilter): ?>
                                            <span class="filter-chip">
                                                📁 <?= e($categories[array_search($categoryFilter, array_column($categories, 'code'))]['name'] ?? $categoryFilter) ?>
                                                <a href="?<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'category'; }, ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($weightFilter): ?>
                                            <span class="filter-chip">
                                                ⚖️ <?= ['light' => 'Лёгкие', 'medium' => 'Средние', 'heavy' => 'Тяжёлые'][$weightFilter] ?>
                                                <a href="?<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'weight'; }, ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($warrantyFilter): ?>
                                            <span class="filter-chip">
                                                🛡️ <?= ['short' => 'Короткая', 'standard' => 'Стандарт', 'long' => 'Длинная'][$warrantyFilter] ?>
                                                <a href="?<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'warranty'; }, ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($serialFilter !== ''): ?>
                                            <span class="filter-chip">
                                                📋 <?= $serialFilter === 'yes' ? 'Требуется' : 'Не требуется' ?>
                                                <a href="?<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'serial'; }, ARRAY_FILTER_USE_KEY)) ?>" class="filter-chip-remove">✕</a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                            
                            <!-- Строка сортировки и информации -->
                            <div class="sort-bar">
                                <div class="sort-info">
                                    Показано <strong><?= count($passports) ?></strong> из <strong><?= $totalProducts ?></strong> продуктов
                                </div>
                                <div class="sort-controls">
                                    <span style="font-size: 13px; color: var(--text-secondary);">Сортировать:</span>
                                    <select class="sort-select" onchange="window.location.href=this.value">
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'category', 'order' => 'asc'])) ?>" <?= $sortBy === 'category' && $sortOrder === 'asc' ? 'selected' : '' ?>>По категории ⬆️</option>
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'category', 'order' => 'desc'])) ?>" <?= $sortBy === 'category' && $sortOrder === 'desc' ? 'selected' : '' ?>>По категории ⬇️</option>
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'name', 'order' => 'asc'])) ?>" <?= $sortBy === 'name' && $sortOrder === 'asc' ? 'selected' : '' ?>>По названию ⬆️</option>
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'name', 'order' => 'desc'])) ?>" <?= $sortBy === 'name' && $sortOrder === 'desc' ? 'selected' : '' ?>>По названию ⬇️</option>
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'sku', 'order' => 'asc'])) ?>" <?= $sortBy === 'sku' && $sortOrder === 'asc' ? 'selected' : '' ?>>По артикулу ⬆️</option>
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'sku', 'order' => 'desc'])) ?>" <?= $sortBy === 'sku' && $sortOrder === 'desc' ? 'selected' : '' ?>>По артикулу ⬇️</option>
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'weight', 'order' => 'asc'])) ?>" <?= $sortBy === 'weight' && $sortOrder === 'asc' ? 'selected' : '' ?>>По весу ⬆️</option>
                                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'weight', 'order' => 'desc'])) ?>" <?= $sortBy === 'weight' && $sortOrder === 'desc' ? 'selected' : '' ?>>По весу ⬇️</option>
                                    </select>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['order' => $sortOrder === 'asc' ? 'desc' : 'asc'])) ?>" class="sort-order-btn" title="<?= $sortOrder === 'asc' ? 'По убыванию' : 'По возрастанию' ?>">
                                        <?= $sortOrder === 'asc' ? '⬆️' : '⬇️' ?>
                                    </a>
                                </div>
                            </div>

                            <!-- Список паспортов -->
                            <div style="margin-top: 20px;">
                                <?php if (empty($passports)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📄</div>
                                        <h3>Паспорта не найдены</h3>
                                        <p>Измените параметры поиска или сбросьте фильтры</p>
                                        <a href="passports.php" class="btn btn-primary" style="margin-top: 16px;">🔄 Сбросить фильтры</a>
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
                                            $matStmt->execute(['passport_id' => $passport['passport_id']]);
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
                                            $stageStmt->execute(['product_id' => $passport['product_id']]);
                                            $stages = $stageStmt->fetchAll();
                                            
                                            // Декодирование JSON полей
                                            $productionNotes = $passport['production_notes'] ? json_decode($passport['production_notes'], true) : [];
                                            $qualityRequirements = $passport['quality_requirements'] ? json_decode($passport['quality_requirements'], true) : [];
                                            $specifications = $passport['specifications'] ? json_decode($passport['specifications'], true) : [];
                                            ?>
                                            <div class="passport-card" data-passport-id="<?= $passport['passport_id'] ?>" onclick="openPassportModal(this)">
                                                <div class="passport-card-header">
                                                    <div style="flex: 1;">
                                                        <span class="passport-sku"><?= e($passport['sku']) ?></span>
                                                        <div class="passport-title"><?= e($passport['product_name']) ?></div>
                                                        <div class="passport-category"><?= e($passport['category_name'] ?? 'Без категории') ?></div>
                                                    </div>
                                                </div>
                                                <div class="passport-card-body">
                                                    <div class="card-info-row">
                                                        <span class="weight-badge">⚖️ <?= number_format($passport['total_weight_kg'] ?? 0, 2, ',', ' ') ?> кг</span>
                                                        <span class="materials-count"><?= count($materials) ?> поз.</span>
                                                    </div>
                                                    
                                                    <?php if (count($stages) > 0): ?>
                                                        <div style="margin-top: 8px; font-size: 11px; color: var(--text-secondary); display: flex; align-items: center; gap: 4px;">
                                                            <span>🏭</span>
                                                            <span><?= count($stages) ?> этапов производства</span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="materials-preview">
                                                        <?php 
                                                        $previewMaterials = array_slice($materials, 0, 3);
                                                        foreach ($previewMaterials as $mat): 
                                                        ?>
                                                            <span class="material-tag">
                                                                <strong><?= number_format($mat['quantity'], 3, ',', ' ') ?></strong> <?= e($mat['unit'] ?? 'шт') ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($materials) > 3): ?>
                                                            <span class="material-tag">+ ещё <?= count($materials) - 3 ?></span>
                                                        <?php endif; ?>
                                                        <?php if (count($materials) === 0): ?>
                                                            <span class="material-tag" style="color: var(--text-secondary);">Материалы не указаны</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Пагинация -->
                                    <?php if ($totalPages > 1): ?>
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">⏮ Первая</a>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Назад</a>
                                        <?php else: ?>
                                            <span class="disabled">⏮ Первая</span>
                                            <span class="disabled">← Назад</span>
                                        <?php endif; ?>
                                        
                                        <span class="current"><?= $page ?> из <?= $totalPages ?></span>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Вперёд →</a>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">Последняя ⏭</a>
                                        <?php else: ?>
                                            <span class="disabled">Вперёд →</span>
                                            <span class="disabled">Последняя ⏭</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
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
            
            // Материалы из БД - единая таблица без группировки
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📦 Материалы для производства</div>';
            if (passport.materials && passport.materials.length > 0) {
                html += '<table class="materials-table">';
                html += '<thead><tr><th>№</th><th>Материал</th><th>Код</th><th>Количество</th><th>Ед.</th></tr></thead>';
                html += '<tbody>';
                for (var i = 0; i < passport.materials.length; i++) {
                    var mat = passport.materials[i];
                    html += '<tr style="cursor: pointer;" onclick="openMaterialCard(' + mat.material_id + ')" title="Нажмите для просмотра карточки материала">';
                    html += '<td>' + (i + 1) + '</td>';
                    html += '<td><strong>' + escapeHtml(mat.material_name) + '</strong></td>';
                    html += '<td><code style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 4px; cursor: pointer;">' + escapeHtml(mat.material_code) + '</code></td>';
                    html += '<td>' + Number(mat.quantity).toFixed(3).replace(/\.?0+$/, '').replace('.', ',') + '</td>';
                    html += '<td>' + escapeHtml(mat.unit) + '</td>';
                    html += '</tr>';
                }
                html += '</tbody>';
                html += '</table>';
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
                    var planQty = Number(um.total_required).toFixed(3).replace(/\.?0+$/, '').replace('.', ',');
                    var factQty = Number(um.total_used).toFixed(3).replace(/\.?0+$/, '').replace('.', ',');
                    var deviation = um.total_required > 0 ? ((um.total_used - um.total_required) / um.total_required * 100).toFixed(1) : 0;
                    var deviationClass = Math.abs(deviation) > 10 ? 'color: #f44336; font-weight: 600;' : 'color: #4caf50;';
                    var deviationSign = deviation > 0 ? '+' : '';
                    var tasksList = um.tasks.slice(0, 3).join(', ') + (um.tasks.length > 3 ? '...' : '');
                    
                    html += '<tr style="cursor: pointer;" onclick="openMaterialCard(' + um.material_id + ')" title="Нажмите для просмотра карточки материала">';
                    html += '<td><strong>' + escapeHtml(um.material_name) + '</strong></td>';
                    html += '<td><code style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 4px; cursor: pointer;">' + escapeHtml(um.material_code) + '</code></td>';
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
        
        // Функция открытия карточки материала на складе
        function openMaterialCard(materialId) {
            if (!materialId) {
                console.error('ID материала не указан');
                return;
            }
            // Переход на страницу склада с параметром для автооткрытия карточки
            window.location.href = '../warehouse/materials.php?material=' + materialId;
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
