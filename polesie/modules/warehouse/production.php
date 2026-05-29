<?php
/**
 * Справочник продукции - полный каталог с фильтрацией и просмотром
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();
$pageTitle = 'Продукция';

// Получение всех продуктов из базы данных
$allProducts = [];
$categories = [];

try {
    // Получение категорий продукции
    $catStmt = $pdo->query("SELECT * FROM product_categories ORDER BY name");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Построение иерархии категорий
    $categoryTree = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] === null) {
            $categoryTree[$cat['id']] = $cat;
            $categoryTree[$cat['id']]['subcategories'] = [];
        }
    }
    foreach ($categories as $cat) {
        if ($cat['parent_id'] !== null && isset($categoryTree[$cat['parent_id']])) {
            $categoryTree[$cat['parent_id']]['subcategories'][] = $cat;
        }
    }
    
    // Получение всей продукции с категориями
    try {
        $sql = "SELECT p.*, 
                       pc.name as category_name, 
                       pc.parent_id as category_parent_id,
                       parent_cat.name as parent_category_name,
                       bu.name as unit_name
                FROM products p
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                LEFT JOIN product_categories parent_cat ON pc.parent_id = parent_cat.id
                LEFT JOIN base_units bu ON p.base_unit_id = bu.id
                WHERE (p.is_active = TRUE OR p.is_active IS NULL OR p.is_active = 1)
                ORDER BY p.name ASC";
        
        $stmt = $pdo->query($sql);
        $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Загружено записей из БД: " . count($dbProducts));
        
        // Преобразование данных для совместимости с существующим кодом
        foreach ($dbProducts as $prod) {
            // specifications может быть JSON или текстом
            $specs = [];
            if (!empty($prod['specifications'])) {
                $specsData = json_decode($prod['specifications'], true);
                if (is_array($specsData)) {
                    $specs = $specsData;
                }
            }
            
            // Полная обработка русскоязычных ключей БД (как в products/list.php)
            // Мощность
            if (isset($specs['мощность_квт'])) {
                $prod['power_kw'] = is_numeric($specs['мощность_квт']) ? floatval($specs['мощность_квт']) : $specs['мощность_квт'];
            }
            // Обороты
            if (isset($specs['обороты_мин'])) {
                $prod['rpm'] = is_numeric($specs['обороты_мин']) ? intval($specs['обороты_мин']) : $specs['обороты_мин'];
            }
            // Напряжение
            if (isset($specs['напряжение_в'])) {
                $prod['voltage_v'] = is_numeric($specs['напряжение_в']) ? intval($specs['напряжение_в']) : $specs['напряжение_в'];
            }
            // Класс эффективности
            if (isset($specs['класс_эффективности'])) {
                $prod['efficiency_class'] = strval($specs['класс_эффективности']);
            }
            // Высота оси
            if (isset($specs['высота_оси_мм'])) {
                $prod['shaft_height_mm'] = is_numeric($specs['высота_оси_мм']) ? floatval($specs['высота_оси_мм']) : $specs['высота_оси_мм'];
            }
            // Габарит
            if (isset($specs['габарит'])) {
                $prod['frame_size'] = strval($specs['габарит']);
            }
            // Монтаж
            if (isset($specs['монтаж'])) {
                $prod['mounting_versions'] = strval($specs['монтаж']);
            }
            // Степень защиты
            if (isset($specs['степень_защиты'])) {
                $prod['protection_class'] = strval($specs['степень_защиты']);
            }
            
            // Маппинг русскоязычных ключей БД в английские для отображения
            // мощность_квт -> power_kw, обороты_мин -> rpm, и т.д.
            $powerKw = !empty($prod['power_kw']) ? $prod['power_kw'] : ($specs['power_kw'] ?? $specs['мощность_квт'] ?? null);
            $rpm = !empty($prod['rpm']) ? $prod['rpm'] : ($specs['rpm'] ?? $specs['обороты_мин'] ?? null);
            $voltageV = !empty($prod['voltage_v']) ? $prod['voltage_v'] : ($specs['voltage_v'] ?? $specs['напряжение_в'] ?? null);
            $efficiencyClass = !empty($prod['efficiency_class']) ? $prod['efficiency_class'] : ($specs['efficiency_class'] ?? $specs['класс_эффективности'] ?? null);
            $shaftHeightMm = !empty($prod['shaft_height_mm']) ? $prod['shaft_height_mm'] : ($specs['shaft_height_mm'] ?? $specs['высота_оси_мм'] ?? $specs['габарит'] ?? null);
            
            // Защита от null значений
            $protectionClassInput = !empty($prod['protection_class']) ? $prod['protection_class'] : ($specs['protection_class'] ?? $specs['степень_защиты'] ?? '');
            $mountingVersionsInput = !empty($prod['mounting_versions']) ? $prod['mounting_versions'] : (isset($specs['mounting_versions']) ? $specs['mounting_versions'] : (isset($specs['монтаж']) ? $specs['монтаж'] : ''));
            
            $protectionClass = !empty($protectionClassInput) ? $protectionClassInput : '';
            $mountingVersions = !empty($mountingVersionsInput) ? $mountingVersionsInput : '';
            
            $product = [
                'id' => $prod['id'],
                'sku' => $prod['article'],
                'code_gost' => !empty($prod['code_gost']) ? $prod['code_gost'] : '',
                'name_full' => !empty($prod['name']) ? $prod['name'] : ($prod['name_short'] ?? ''),
                'name_short' => !empty($prod['name_short']) ? $prod['name_short'] : ($prod['name'] ?? ''),
                'category_id' => $prod['category_id'],
                'category_parent_id' => $prod['category_parent_id'],
                'parent_category' => [
                    'id' => $prod['category_parent_id'],
                    'name_ru' => $prod['parent_category_name'] ?? ($prod['category_name'] ?? '')
                ],
                'subcategory' => [
                    'id' => $prod['category_id'],
                    'name_ru' => $prod['category_name'] ?? ''
                ],
                'specs' => [
                    'power_kw' => $powerKw,
                    'power_kw_min' => $specs['power_kw_min'] ?? $specs['мощность_квт_min'] ?? null,
                    'power_kw_max' => $specs['power_kw_max'] ?? $specs['мощность_квт_max'] ?? null,
                    'rpm' => $rpm,
                    'voltage_v' => $voltageV,
                    'efficiency_class' => $efficiencyClass,
                    'shaft_height_mm' => $shaftHeightMm,
                    'protection_class' => $protectionClass ? (is_array($protectionClass) ? $protectionClass : explode(',', $protectionClass)) : [],
                    'mounting_versions' => $mountingVersions ? (is_array($mountingVersions) ? $mountingVersions : explode(',', $mountingVersions)) : []
                ],
                'is_bestseller' => !empty($prod['is_bestseller']),
                'is_serial_tracked' => !empty($prod['is_serial_tracked']),
                'image' => $prod['image'] ?? null,
                'base_price' => $prod['base_price'] ?? 0,
                'currency' => $prod['currency'] ?? 'BYN',
                'is_active' => !empty($prod['is_active'])
            ];
            $allProducts[] = $product;
        }
        
        error_log("Преобразовано продукции: " . count($allProducts));
    } catch (Exception $e) {
        error_log("Ошибка при загрузке продукции: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Trace: " . $e->getTraceAsString());
        $allProducts = [];
    }
    
} catch (Exception $e) {
    // Если ошибка - продолжаем с пустыми данными
    error_log("Ошибка при загрузке справочников: " . $e->getMessage());
}

error_log("Всего продукции в базе: " . count($allProducts));
error_log("Категорий продукции: " . count($categories));

// Получение уникальных значений для фильтров
$efficiencyClasses = [];
$rpmValues = [];
$protectionClasses = [];
$mountingVersions = [];

foreach ($allProducts as $prod) {
    $specs = $prod['specs'] ?? [];
    
    if (!empty($specs['efficiency_class']) && !in_array($specs['efficiency_class'], $efficiencyClasses)) {
        $efficiencyClasses[] = $specs['efficiency_class'];
    }
    if (!empty($specs['rpm']) && !in_array($specs['rpm'], $rpmValues)) {
        $rpmValues[] = $specs['rpm'];
    }
    if (!empty($specs['protection_class'])) {
        foreach ((is_array($specs['protection_class']) ? $specs['protection_class'] : [$specs['protection_class']]) as $pc) {
            if (!empty($pc) && !in_array($pc, $protectionClasses)) {
                $protectionClasses[] = $pc;
            }
        }
    }
    if (!empty($specs['mounting_versions'])) {
        foreach ((is_array($specs['mounting_versions']) ? $specs['mounting_versions'] : [$specs['mounting_versions']]) as $mv) {
            if (!empty($mv) && !in_array($mv, $mountingVersions)) {
                $mountingVersions[] = $mv;
            }
        }
    }
}

sort($efficiencyClasses);
sort($rpmValues);
sort($protectionClasses);
sort($mountingVersions);

// Получение данных о серийных номерах и документах для каждого продукта
$productSerialData = [];
$stmt = $pdo->query("
    SELECT sn.*, pd.file_path as manual_path
    FROM product_serial_numbers sn
    LEFT JOIN product_documents pd ON sn.id = pd.serial_number_id AND pd.document_type = 'manual'
    WHERE sn.status IN ('active', 'warranty')
    ORDER BY sn.created_at DESC
");
$serialNumbers = $stmt->fetchAll();

foreach ($serialNumbers as $sn) {
    $productId = $sn['product_id'];
    if (!isset($productSerialData[$productId])) {
        $productSerialData[$productId] = [];
    }
    $productSerialData[$productId][] = $sn;
}

// Получение документов для серийных номеров
$productDocuments = [];
if (!empty($serialNumbers)) {
    $serialIds = array_column($serialNumbers, 'id');
    $placeholders = implode(',', array_fill(0, count($serialIds), '?'));
    $docsStmt = $pdo->prepare("SELECT * FROM product_documents WHERE serial_number_id IN ($placeholders) ORDER BY uploaded_at DESC");
    $docsStmt->execute($serialIds);
    $allDocs = $docsStmt->fetchAll();
    
    foreach ($allDocs as $doc) {
        $snId = $doc['serial_number_id'];
        if (!isset($productDocuments[$snId])) {
            $productDocuments[$snId] = [];
        }
        $productDocuments[$snId][] = $doc;
    }
}

// Применение фильтров
$filteredProducts = $allProducts;

// Фильтр по категории
$filterCategory = $_GET['category'] ?? '';
if ($filterCategory !== '') {
    $filteredProducts = array_filter($filteredProducts, function($p) use ($filterCategory) {
        return isset($p['category_id']) && ($p['category_id'] == $filterCategory || (isset($p['category_parent_id']) && $p['category_parent_id'] == $filterCategory));
    });
}

// Фильтр по подкатегории
$filterSubcategory = $_GET['subcategory'] ?? '';
if ($filterSubcategory !== '') {
    $filteredProducts = array_filter($filteredProducts, function($p) use ($filterSubcategory) {
        return isset($p['category_id']) && $p['category_id'] == $filterSubcategory;
    });
}

// Фильтр по поиску
$filterSearch = $_GET['search'] ?? '';
if ($filterSearch !== '') {
    $filteredProducts = array_filter($filteredProducts, function($p) use ($filterSearch) {
        $searchInName = stripos($p['name_full'], $filterSearch) !== false || 
                        stripos($p['name_short'], $filterSearch) !== false;
        $searchInSku = stripos($p['sku'], $filterSearch) !== false;
        $searchInGost = isset($p['code_gost']) && stripos($p['code_gost'], $filterSearch) !== false;
        $searchInCategory = stripos($p['parent_category']['name_ru'] ?? '', $filterSearch) !== false ||
                            stripos($p['subcategory']['name_ru'] ?? '', $filterSearch) !== false;
        return $searchInName || $searchInSku || $searchInGost || $searchInCategory;
    });
}

// Фильтр по классу энергоэффективности
$filterEfficiency = $_GET['efficiency'] ?? '';
if ($filterEfficiency !== '') {
    $filteredProducts = array_filter($filteredProducts, function($p) use ($filterEfficiency) {
        return isset($p['specs']['efficiency_class']) && 
               $p['specs']['efficiency_class'] === $filterEfficiency;
    });
}

// Фильтр по оборотам
$filterRpm = $_GET['rpm'] ?? '';
if ($filterRpm !== '') {
    $filteredProducts = array_filter($filteredProducts, function($p) use ($filterRpm) {
        return isset($p['specs']['rpm']) && 
               $p['specs']['rpm'] == $filterRpm;
    });
}

// Фильтр по классу защиты
$filterProtection = $_GET['protection'] ?? '';
if ($filterProtection !== '') {
    $filteredProducts = array_filter($filteredProducts, function($p) use ($filterProtection) {
        return isset($p['specs']['protection_class']) && is_array($p['specs']['protection_class']) && in_array($filterProtection, $p['specs']['protection_class']);
    });
}

// Фильтр по хитам продаж
$filterBestseller = $_GET['bestseller'] ?? '';
if ($filterBestseller === '1') {
    $filteredProducts = array_filter($filteredProducts, function($p) {
        return !empty($p['is_bestseller']);
    });
}

// Сортировка
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';

usort($filteredProducts, function($a, $b) use ($sortBy, $sortOrder) {
    $result = 0;
    switch ($sortBy) {
        case 'name':
            $result = strcmp($a['name_full'], $b['name_full']);
            break;
        case 'sku':
            $result = strcmp($a['sku'], $b['sku']);
            break;
        case 'category':
            $result = strcmp($a['parent_category']['name_ru'] ?? '', $b['parent_category']['name_ru'] ?? '');
            break;
        case 'power':
            $powerA = $a['specs']['power_kw'] ?? $a['specs']['power_kw_min'] ?? 0;
            $powerB = $b['specs']['power_kw'] ?? $b['specs']['power_kw_min'] ?? 0;
            $result = $powerA - $powerB;
            break;
    }
    return $sortOrder === 'desc' ? -$result : $result;
});

// Подготовка подкатегорий для выбранной категории
$availableSubcategories = [];
if ($filterCategory !== '') {
    foreach ($categories as $cat) {
        if ($cat['id'] == $filterCategory && isset($cat['subcategories'])) {
            $availableSubcategories = $cat['subcategories'];
            break;
        }
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

<style>
.production-page {
    padding: 24px;
}

.filters-panel {
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
}

.filters-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.filters-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filter-select,
.filter-input {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 14px;
    background: var(--bg-primary);
    color: var(--text-primary);
    cursor: pointer;
    transition: border-color var(--transition-fast);
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: var(--primary-color);
}

.filter-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    align-items: center;
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
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.filter-chip-remove {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity var(--transition-fast);
}

.filter-chip-remove:hover {
    opacity: 1;
}

.stats-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow);
}

.stats-count {
    font-size: 14px;
    color: var(--text-secondary);
}

.stats-count strong {
    color: var(--text-primary);
    font-size: 16px;
}

.view-controls {
    display: flex;
    gap: 8px;
}

.view-btn {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all var(--transition-fast);
    font-size: 14px;
}

.view-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}

.product-card {
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    cursor: pointer;
    border: 1px solid transparent;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary-color);
}

.product-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
}

.product-category {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.product-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.4;
}

.product-sku {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: var(--text-secondary);
    background: var(--gray-100);
    padding: 4px 8px;
    border-radius: 4px;
    margin-top: 8px;
    display: inline-block;
}

.product-specs {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.spec-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 8px;
}

.spec-label {
    color: var(--text-secondary);
}

.spec-value {
    font-weight: 500;
    color: var(--text-primary);
}

.product-badges {
    display: flex;
    gap: 6px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.badge-bestseller {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-serial {
    background: rgba(6, 182, 212, 0.1);
    color: var(--info-color);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
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

/* Modal styles - using global CSS classes */
.spec-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.spec-list li {
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 14px;
}

.spec-list li:last-child {
    border-bottom: none;
}

.spec-list li strong {
    color: var(--text-secondary);
    font-weight: 500;
    margin-right: 8px;
}

/* Product Modal Styles */
.product-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.product-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.product-modal {
    background: var(--bg-primary);
    border-radius: var(--border-radius-lg);
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: var(--shadow-xl);
    transform: translateY(-20px);
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
}

.product-modal-overlay.active .product-modal {
    transform: translateY(0);
}

.product-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
}

.product-modal-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.product-modal-subtitle {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
    font-family: 'Courier New', monospace;
}

.product-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius);
    transition: all var(--transition-fast);
}

.product-modal-close:hover {
    background: var(--gray-100);
    color: var(--text-primary);
}

.product-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.product-modal-footer {
    padding: 16px 24px;
    background: white;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.product-detail-section {
    margin-bottom: 24px;
}

.product-detail-section:last-child {
    margin-bottom: 0;
}

.product-detail-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--primary-color);
    display: inline-block;
}

.product-specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 16px;
}

.product-spec-item {
    background: var(--bg-secondary);
    padding: 12px 16px;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}

.product-spec-name {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
}

.product-spec-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
}

.product-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
}

.product-status-active {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}
</style>

<div class="production-page">
    <!-- Панель фильтров -->
    <div class="filters-panel">
        <div class="filters-header">
            <div class="filters-title">
                <span>🔍</span> Фильтры продукции
            </div>
        </div>
        
        <form method="GET" id="filterForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Поиск</label>
                    <input type="text" name="search" class="filter-input" placeholder="Название, SKU, ГОСТ..." value="<?= e($filterSearch) ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Категория</label>
                    <select name="category" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name_ru']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Подкатегория</label>
                    <select name="subcategory" class="filter-select" <?= empty($availableSubcategories) ? 'disabled' : '' ?>>
                        <option value="">Все подкатегории</option>
                        <?php foreach ($availableSubcategories as $subcat): ?>
                            <option value="<?= $subcat['id'] ?>" <?= $filterSubcategory == $subcat['id'] ? 'selected' : '' ?>>
                                <?= e($subcat['name_ru']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Класс энергоэффективности</label>
                    <select name="efficiency" class="filter-select">
                        <option value="">Все</option>
                        <?php foreach ($efficiencyClasses as $ec): ?>
                            <option value="<?= e($ec) ?>" <?= $filterEfficiency == $ec ? 'selected' : '' ?>><?= e($ec) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Обороты (об/мин)</label>
                    <select name="rpm" class="filter-select">
                        <option value="">Все</option>
                        <?php foreach ($rpmValues as $rpm): ?>
                            <option value="<?= e($rpm) ?>" <?= $filterRpm == $rpm ? 'selected' : '' ?>><?= e($rpm) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Класс защиты</label>
                    <select name="protection" class="filter-select">
                        <option value="">Все</option>
                        <?php foreach ($protectionClasses as $pc): ?>
                            <option value="<?= e($pc) ?>" <?= $filterProtection == $pc ? 'selected' : '' ?>><?= e($pc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Хиты продаж</label>
                    <select name="bestseller" class="filter-select">
                        <option value="">Все</option>
                        <option value="1" <?= $filterBestseller === '1' ? 'selected' : '' ?>>⭐ Хиты продаж</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Применить</button>
                <a href="production.php" class="btn btn-outline">Сбросить</a>
            </div>
            
            <?php
            // Отображение активных фильтров
            $activeFilters = [];
            if ($filterCategory !== '') {
                foreach ($categories as $cat) {
                    if ($cat['id'] == $filterCategory) {
                        $activeFilters[] = ['name' => 'Категория: ' . $cat['name_ru'], 'remove' => 'category'];
                    }
                }
            }
            if ($filterSubcategory !== '') {
                foreach ($availableSubcategories as $subcat) {
                    if ($subcat['id'] == $filterSubcategory) {
                        $activeFilters[] = ['name' => 'Подкатегория: ' . $subcat['name_ru'], 'remove' => 'subcategory'];
                    }
                }
            }
            if ($filterEfficiency !== '') {
                $activeFilters[] = ['name' => 'Эффективность: ' . $filterEfficiency, 'remove' => 'efficiency'];
            }
            if ($filterRpm !== '') {
                $activeFilters[] = ['name' => 'Обороты: ' . $filterRpm, 'remove' => 'rpm'];
            }
            if ($filterProtection !== '') {
                $activeFilters[] = ['name' => 'Защита: ' . $filterProtection, 'remove' => 'protection'];
            }
            if ($filterBestseller === '1') {
                $activeFilters[] = ['name' => '⭐ Хиты продаж', 'remove' => 'bestseller'];
            }
            
            if (!empty($activeFilters)):
            ?>
            <div class="active-filters">
                <?php foreach ($activeFilters as $filter): ?>
                    <span class="filter-chip">
                        <?= e($filter['name']) ?>
                        <a href="?<?= http_build_query(array_merge($_GET, [$filter['remove'] => ''])) ?>" class="filter-chip-remove">×</a>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Статистика -->
    <div class="stats-bar">
        <div class="stats-count">
            Найдено: <strong><?= count($filteredProducts) ?></strong> из <strong><?= count($allProducts) ?></strong> позиций
        </div>
        <div class="view-controls">
            <button class="view-btn active" title="Карточки">▦</button>
        </div>
    </div>
    
    <!-- Список продукции -->
    <?php if (empty($filteredProducts)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📦</div>
            <h3>Продукция не найдена</h3>
            <p>Измените параметры фильтрации</p>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($filteredProducts as $product): ?>
                <?php
                // Добавляем информацию о серийном номере из JSON или БД в данные продукта
                $productId = $product['id'] ?? null;
                
                // Сначала проверяем, есть ли серийный номер прямо в данных продукта (из JSON)
                if (empty($product['serial_number']) && !empty($product['is_serial_tracked'])) {
                    // Если нет в JSON, пробуем загрузить из БД
                    if ($productId && isset($productSerialData[$productId]) && !empty($productSerialData[$productId])) {
                        // Берем последний активный серийный номер из БД
                        $lastSerial = end($productSerialData[$productId]);
                        $product['serial_number'] = $lastSerial['serial_number'];
                    }
                }
                
                // Убеждаемся, что флаг is_serial_tracked установлен
                if (!empty($product['serial_number'])) {
                    $product['is_serial_tracked'] = true;
                }
                
                // Сохраняем данные продукта в data-атрибуте как JSON
                $productJson = htmlspecialchars(json_encode($product, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="product-card" data-product="<?= $productJson ?>" onclick="openProductModal(this)">
                    <div class="product-card-header">
                        <div>
                            <div class="product-category"><?= e($product['parent_category']['name_ru']) ?> / <?= e($product['subcategory']['name_ru']) ?></div>
                            <div class="product-name"><?= e($product['name_full']) ?></div>
                            <div class="product-sku">SKU: <?= e($product['sku']) ?> <?php if (!empty($product['code_gost'])): ?>| ГОСТ: <?= e($product['code_gost']) ?><?php endif; ?></div>
                        </div>
                    </div>
                    
                    <div class="product-specs">
                        <?php
                        $specs = $product['specs'] ?? [];
                        $displaySpecs = [];
                        
                        // Мощность - проверяем все возможные ключи
                        $powerKw = $specs['power_kw'] ?? $specs['мощность_квт'] ?? $specs['мощность_основная_квт'] ?? null;
                        $powerKwMin = $specs['power_kw_min'] ?? $specs['мощность_квт_min'] ?? null;
                        $powerKwMax = $specs['power_kw_max'] ?? $specs['мощность_квт_max'] ?? null;
                        $powerReserve = $specs['мощность_резервная_квт'] ?? $specs['мощность_максимальная_квт'] ?? null;
                        
                        if (!empty($powerKw)) {
                            $displaySpecs[] = ['label' => 'Мощность', 'value' => $powerKw . ' кВт'];
                        } elseif (!empty($powerKwMin) || !empty($powerKwMax)) {
                            $powerRange = '';
                            if (!empty($powerKwMin) && !empty($powerKwMax)) {
                                $powerRange = $powerKwMin . '-' . $powerKwMax . ' кВт';
                            } elseif (!empty($powerKwMin)) {
                                $powerRange = $powerKwMin . ' кВт';
                            } elseif (!empty($powerKwMax)) {
                                $powerRange = $powerKwMax . ' кВт';
                            }
                            if ($powerRange) {
                                $displaySpecs[] = ['label' => 'Мощность', 'value' => $powerRange];
                            }
                        }
                        if (!empty($powerReserve)) {
                            $displaySpecs[] = ['label' => 'Резервная мощность', 'value' => $powerReserve . ' кВт'];
                        }
                        
                        // Мощность для трансформаторов (кВА)
                        $powerKva = $specs['мощность_ква'] ?? $specs['power_kva'] ?? null;
                        if (!empty($powerKva)) {
                            $displaySpecs[] = ['label' => 'Номинальная мощность', 'value' => $powerKva . ' кВА'];
                        }
                        
                        // Обороты - проверяем все возможные ключи
                        $rpm = $specs['rpm'] ?? $specs['обороты_мин'] ?? null;
                        if (!empty($rpm)) {
                            $displaySpecs[] = ['label' => 'Обороты', 'value' => $rpm . ' об/мин'];
                        }
                        
                        // Высота оси - проверяем все возможные ключи
                        $shaftHeight = $specs['shaft_height_mm'] ?? $specs['высота_оси_мм'] ?? $specs['габарит'] ?? null;
                        if (!empty($shaftHeight)) {
                            $displaySpecs[] = ['label' => 'Высота оси', 'value' => $shaftHeight . ' мм'];
                        }
                        
                        // Класс энергоэффективности - проверяем все возможные ключи
                        $efficiencyClass = $specs['efficiency_class'] ?? $specs['класс_эффективности'] ?? null;
                        if (!empty($efficiencyClass)) {
                            $displaySpecs[] = ['label' => 'Класс энергоэффективности', 'value' => $efficiencyClass];
                        }
                        
                        // Напряжение - проверяем все возможные ключи
                        $voltageV = $specs['voltage_v'] ?? $specs['напряжение_в'] ?? null;
                        if (!empty($voltageV)) {
                            $displaySpecs[] = ['label' => 'Напряжение', 'value' => $voltageV . ' В'];
                        }
                        
                        // Класс защиты - проверяем все возможные ключи и форматы
                        $protectionClass = $specs['protection_class'] ?? $specs['степень_защиты'] ?? null;
                        if (!empty($protectionClass)) {
                            $protectionValue = is_array($protectionClass) ? implode(', ', $protectionClass) : $protectionClass;
                            $displaySpecs[] = ['label' => 'Класс защиты', 'value' => $protectionValue];
                        }
                        
                        // Варианты монтажа - проверяем все возможные ключи
                        $mountingVersions = $specs['mounting_versions'] ?? $specs['монтаж'] ?? null;
                        if (!empty($mountingVersions)) {
                            $mountingValue = is_array($mountingVersions) ? implode(', ', $mountingVersions) : $mountingVersions;
                            $displaySpecs[] = ['label' => 'Варианты монтажа', 'value' => $mountingValue];
                        }
                        
                        // Частота
                        $frequencyHz = $specs['frequency_hz'] ?? $specs['частота_гц'] ?? null;
                        if (!empty($frequencyHz)) {
                            $displaySpecs[] = ['label' => 'Частота', 'value' => $frequencyHz . ' Гц'];
                        }
                        
                        // Климатическое исполнение
                        $climateVersions = $specs['climate_versions'] ?? $specs['климатическое_исполнение'] ?? null;
                        if (!empty($climateVersions)) {
                            $climateValue = is_array($climateVersions) ? implode(', ', $climateVersions) : $climateVersions;
                            $displaySpecs[] = ['label' => 'Климатическое исполнение', 'value' => $climateValue];
                        }
                        
                        // Вес
                        $weightKg = $specs['weight_kg'] ?? $specs['вес_кг'] ?? $specs['масса_кг'] ?? null;
                        if (!empty($weightKg)) {
                            $displaySpecs[] = ['label' => 'Вес', 'value' => $weightKg . ' кг'];
                        }
                        
                        // Материал корпуса
                        $housingMaterial = $specs['housing_material'] ?? $specs['материал_корпуса'] ?? null;
                        if (!empty($housingMaterial)) {
                            $displaySpecs[] = ['label' => 'Материал корпуса', 'value' => $housingMaterial];
                        }
                        
                        // Тип двигателя
                        $motorType = $specs['motor_type'] ?? $specs['тип_двигателя'] ?? null;
                        if (!empty($motorType)) {
                            $displaySpecs[] = ['label' => 'Тип двигателя', 'value' => $motorType];
                        }
                        
                        // Напряжение ВН (для трансформаторов)
                        $voltageHv = $specs['напряжение_вн_кв'] ?? $specs['напряжение_первичное_кв'] ?? null;
                        if (!empty($voltageHv)) {
                            $displaySpecs[] = ['label' => 'Напряжение ВН', 'value' => $voltageHv . ' кВ'];
                        }
                        
                        // Напряжение НН (для трансформаторов)
                        $voltageLv = $specs['напряжение_нн_в'] ?? $specs['напряжение_вторичное_в'] ?? null;
                        if (!empty($voltageLv)) {
                            $displaySpecs[] = ['label' => 'Напряжение НН', 'value' => $voltageLv . ' В'];
                        }
                        
                        // Схема соединения (для трансформаторов)
                        $connectionScheme = $specs['схема_соединения'] ?? $specs['соединение'] ?? null;
                        if (!empty($connectionScheme)) {
                            $displaySpecs[] = ['label' => 'Схема соединения', 'value' => $connectionScheme];
                        }
                        
                        // Номинальный ток (для щитового оборудования)
                        $ratedCurrent = $specs['ток_номинальный_а'] ?? $specs['макс_ток_а'] ?? null;
                        if (!empty($ratedCurrent)) {
                            $displaySpecs[] = ['label' => 'Номинальный ток', 'value' => $ratedCurrent . ' А'];
                        }
                        
                        // Количество групп/двигателей/панелей
                        $numGroups = $specs['количество_групп'] ?? $specs['группы'] ?? null;
                        if (!empty($numGroups)) {
                            $displaySpecs[] = ['label' => 'Количество групп', 'value' => $numGroups . ' шт'];
                        }
                        $numMotors = $specs['количество_двигателей'] ?? $specs['motors'] ?? null;
                        if (!empty($numMotors)) {
                            $displaySpecs[] = ['label' => 'Количество двигателей', 'value' => $numMotors . ' шт'];
                        }
                        $numPanels = $specs['количество_панелей'] ?? $specs['панели'] ?? null;
                        if (!empty($numPanels)) {
                            $displaySpecs[] = ['label' => 'Количество панелей', 'value' => $numPanels . ' шт'];
                        }
                        
                        // Тип топлива (для генераторов)
                        $fuelType = $specs['тип_топлива'] ?? null;
                        if (!empty($fuelType)) {
                            $displaySpecs[] = ['label' => 'Тип топлива', 'value' => $fuelType];
                        }
                        
                        // Тип запуска (для генераторов)
                        $startType = $specs['тип_запуска'] ?? null;
                        if (!empty($startType)) {
                            $displaySpecs[] = ['label' => 'Тип запуска', 'value' => $startType];
                        }
                        
                        // Объем бака (для генераторов)
                        $tankVolume = $specs['объем_бака_л'] ?? null;
                        if (!empty($tankVolume)) {
                            $displaySpecs[] = ['label' => 'Объем бака', 'value' => $tankVolume . ' л'];
                        }
                        
                        // Расход топлива (для генераторов)
                        $fuelConsumption = $specs['расход_топлива_л_ч'] ?? null;
                        if (!empty($fuelConsumption)) {
                            $displaySpecs[] = ['label' => 'Расход топлива', 'value' => $fuelConsumption . ' л/ч'];
                        }
                        
                        // Уровень шума (для генераторов)
                        $noiseLevel = $specs['шум_дб'] ?? $specs['уровень_шума_дб'] ?? null;
                        if (!empty($noiseLevel)) {
                            $displaySpecs[] = ['label' => 'Уровень шума', 'value' => $noiseLevel . ' дБ'];
                        }
                        
                        // Потери (для трансформаторов)
                        $lossNoLoad = $specs['потери_хх_вт'] ?? null;
                        if (!empty($lossNoLoad)) {
                            $displaySpecs[] = ['label' => 'Потери холостого хода', 'value' => $lossNoLoad . ' Вт'];
                        }
                        $lossShortCircuit = $specs['потери_кз_вт'] ?? null;
                        if (!empty($lossShortCircuit)) {
                            $displaySpecs[] = ['label' => 'Потери короткого замыкания', 'value' => $lossShortCircuit . ' Вт'];
                        }
                        
                        // Масса масла (для масляных трансформаторов)
                        $oilMass = $specs['масса_масла_кг'] ?? null;
                        if (!empty($oilMass)) {
                            $displaySpecs[] = ['label' => 'Масса масла', 'value' => $oilMass . ' кг'];
                        }
                        
                        // Класс нагревостойкости (для трансформаторов)
                        $insulationClass = $specs['класс_нагревостойкости'] ?? null;
                        if (!empty($insulationClass)) {
                            $displaySpecs[] = ['label' => 'Класс нагревостойкости', 'value' => $insulationClass];
                        }
                        
                        // Для роторов и статоров
                        $rotorDiameter = $specs['диаметр_мм'] ?? null;
                        if (!empty($rotorDiameter)) {
                            $displaySpecs[] = ['label' => 'Диаметр', 'value' => $rotorDiameter . ' мм'];
                        }
                        $rotorLength = $specs['длина_мм'] ?? null;
                        if (!empty($rotorLength)) {
                            $displaySpecs[] = ['label' => 'Длина', 'value' => $rotorLength . ' мм'];
                        }
                        $shaftDiameter = $specs['диаметр_вала_мм'] ?? null;
                        if (!empty($shaftDiameter)) {
                            $displaySpecs[] = ['label' => 'Диаметр вала', 'value' => $shaftDiameter . ' мм'];
                        }
                        $grooves = $specs['пазы'] ?? null;
                        if (!empty($grooves)) {
                            $displaySpecs[] = ['label' => 'Количество пазов', 'value' => $grooves . ' шт'];
                        }
                        
                        foreach ($displaySpecs as $spec):
                        ?>
                        <div class="spec-row">
                            <span class="spec-label"><?= e($spec['label']) ?></span>
                            <span class="spec-value"><?= e($spec['value']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="product-badges">
                        <?php if (!empty($product['is_bestseller'])): ?>
                            <span class="badge-bestseller">⭐ Хит продаж</span>
                        <?php endif; ?>
                        <?php if (!empty($product['is_serial_tracked'])): ?>
                            <span class="badge-serial">📋 Серийный учёт</span>
                        <?php endif; ?>
                        <?php if (!empty($product['warranty_months'])): ?>
                            <span class="badge-serial">🛡️ Гарантия <?= $product['warranty_months'] ?> мес.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

    <!-- Модальное окно просмотра продукции -->
    <div id="productModalOverlay" class="product-modal-overlay" onclick="closeProductModal(event)">
        <div class="product-modal">
            <div class="product-modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                <div style="flex: 1;">
                    <h3 class="product-modal-title" id="modalProductName" style="color: white;">Название продукции</h3>
                    <p class="product-modal-subtitle" id="modalProductSku" style="color: rgba(255,255,255,0.9);">SKU: —</p>
                    <div id="modalProductWarranty"></div>
                </div>
                <button class="product-modal-close" onclick="closeProductModalDirect()" style="color: white; opacity: 0.8;">×</button>
            </div>
            <div class="product-modal-body" id="modalProductBody" style="background: #f9fafb;">
                <!-- Контент будет заполнен через JS -->
            </div>
        </div>
    </div>

</div>
        </div>
    </div>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
    
    <script>
        function openProductModal(element) {
            // Получаем данные продукта из data-атрибута
            var productJson = element.getAttribute('data-product');
            var product = JSON.parse(productJson);
            
            console.log('Opening product:', product);
            
            // Генерируем ID для продуктов из JSON если его нет (используем простой хэш)
            if (!product.id && product.sku) {
                // Простая реализация хэширования для JavaScript (djb2 алгоритм)
                var str = product.sku;
                var hash = 5381;
                for (var i = 0; i < str.length; i++) {
                    hash = ((hash << 5) + hash) + str.charCodeAt(i);
                    hash = hash & hash; // Convert to 32-bit integer
                }
                product.id = 'PROD_' + Math.abs(hash).toString();
            }
            
            document.getElementById('modalProductName').textContent = product.name_full || 'Без названия';
            document.getElementById('modalProductSku').textContent = 'SKU: ' + (product.sku || '—') + 
                (product.code_gost ? ' | ГОСТ: ' + product.code_gost : '');
            
            // Сохраняем текущий продукт для печати
            window.currentProduct = product;
            
            // Получаем ID продукта для загрузки данных о серийных номерах
            var productId = product.id;
            
            // Загружаем данные о последнем серийном номере и документах через AJAX
            var serialData = null;
            var documents = [];
            var manualPath = null;
            
            // Формируем HTML с паспортными данными
            var html = '';
            
            // === ЗАГОЛОВОК ПАСПОРТА ===
            html += '<div class="passport-header" style="background: linear-gradient(135deg, #2563eb, #1e40af); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
            html += '<div style="text-align: center;">';
            html += '<h2 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">ПАСПОРТ ИЗДЕЛИЯ</h2>';
            html += '<div style="font-size: 12px; opacity: 0.9;">ОАО "Полесьеэлектромаш"</div>';
            html += '</div>';
            html += '</div>';
            
            // Генерируем или получаем серийный номер
            var initialSerial = product.serial_number || '';
            if (!initialSerial && product.is_serial_tracked) {
                var date = new Date();
                var year = date.getFullYear().toString().substr(-2);
                var month = (date.getMonth() + 1).toString().padStart(2, '0');
                var randomPart = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                initialSerial = 'SN-' + year + month + '-' + productId + '-' + randomPart;
            }
            
            // === ОСНОВНАЯ ИНФОРМАЦИЯ ===
            html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px;">';
            html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">📂 Основная информация</div>';
            html += '<div class="specs-list">';
            html += '<div class="spec-row"><span class="spec-label">Наименование изделия:</span><span class="spec-value">' + escapeHtml(product.name_full || '—') + '</span></div>';
            html += '<div class="spec-row"><span class="spec-label">Артикул:</span><span class="spec-value">' + escapeHtml(product.sku || '—') + '</span></div>';
            if (product.is_serial_tracked) {
                html += '<div class="spec-row"><span class="spec-label">Серийный номер:</span><span class="spec-value" id="modal-serial-number">' + (initialSerial ? escapeHtml(initialSerial) : 'Загрузка...') + '</span></div>';
            }
            if (product.code_gost) {
                html += '<div class="spec-row"><span class="spec-label">ГОСТ:</span><span class="spec-value">' + escapeHtml(product.code_gost) + '</span></div>';
            }
            html += '<div class="spec-row"><span class="spec-label">Категория:</span><span class="spec-value">' + escapeHtml(product.parent_category?.name_ru || '—') + '</span></div>';
            html += '<div class="spec-row"><span class="spec-label">Подкатегория:</span><span class="spec-value">' + escapeHtml(product.subcategory?.name_ru || '—') + '</span></div>';
            if (product.unit_name) {
                html += '<div class="spec-row"><span class="spec-label">Ед. измерения:</span><span class="spec-value">' + escapeHtml(product.unit_name) + '</span></div>';
            }
            if (product.base_price) {
                html += '<div class="spec-row"><span class="spec-label">Цена:</span><span class="spec-value">' + formatPrice(product.base_price) + ' BYN</span></div>';
            }
            html += '<div class="spec-row"><span class="spec-label">Статус:</span><span class="spec-value">' + (product.is_active ? '<span style="color: #22c55e; font-weight: 600;">✓ Активен</span>' : '<span style="color: #ef4444;">✗ Неактивен</span>') + '</span></div>';
            if (product.is_bestseller) {
                html += '<div class="spec-row"><span class="spec-label">Статус продаж:</span><span class="spec-value">⭐ Хит продаж</span></div>';
            }
            html += '</div>';
            html += '</div>';
            
            // === ТЕХНИЧЕСКИЕ ХАРАКТЕРИСТИКИ ===
            if (product.specs && typeof product.specs === 'object') {
                html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px;">';
                html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">⚙️ Технические характеристики</div>';
                html += '<div class="specs-list">';
                
                var specs = product.specs;
                
                // Вспомогательная функция для получения значения (поддержка русских и английских ключей)
                function getSpecValue(keys) {
                    for (var i = 0; i < keys.length; i++) {
                        if (specs[keys[i]] !== undefined && specs[keys[i]] !== null && specs[keys[i]] !== '') {
                            return specs[keys[i]];
                        }
                    }
                    return null;
                }
                
                // Вспомогательная функция для добавления строки спецификации
                function addSpecRow(label, value, unit) {
                    if (value === null || value === undefined || value === '') return '';
                    var unitStr = unit ? ' ' + unit : '';
                    var displayValue = Array.isArray(value) ? value.join(', ') : value;
                    return '<div class="spec-row"><span class="spec-label">' + label + ':</span><span class="spec-value">' + escapeHtml(String(displayValue)) + unitStr + '</span></div>';
                }
                
                // Мощность (для двигателей, генераторов, трансформаторов)
                var powerVal = getSpecValue(['power_kw', 'мощность_квт', 'мощность_основная_квт', 'мощность_ква']);
                if (powerVal !== null) {
                    var powerUnit = specs['мощность_ква'] !== undefined ? 'кВА' : 'кВт';
                    html += addSpecRow('Мощность', powerVal, powerUnit);
                } else {
                    // Диапазон мощности
                    var powerMin = getSpecValue(['power_kw_min', 'мощность_квт_min']);
                    var powerMax = getSpecValue(['power_kw_max', 'мощность_квт_max']);
                    if (powerMin !== null || powerMax !== null) {
                        var powerRange = '';
                        if (powerMin !== null && powerMax !== null) {
                            powerRange = powerMin + ' - ' + powerMax;
                        } else if (powerMin !== null) {
                            powerRange = powerMin;
                        } else if (powerMax !== null) {
                            powerRange = powerMax;
                        }
                        if (powerRange) {
                            html += addSpecRow('Мощность', powerRange, 'кВт');
                        }
                    }
                }
                
                // Обороты (только для двигателей)
                var rpmVal = getSpecValue(['rpm', 'обороты_мин']);
                if (rpmVal !== null) {
                    html += addSpecRow('Обороты', rpmVal, 'об/мин');
                }
                
                // Высота оси / габарит (только для двигателей)
                var shaftVal = getSpecValue(['shaft_height_mm', 'высота_оси_мм', 'габарит']);
                if (shaftVal !== null) {
                    html += addSpecRow('Высота оси', shaftVal, 'мм');
                }
                
                // Класс энергоэффективности (только для двигателей)
                var effVal = getSpecValue(['efficiency_class', 'класс_эффективности']);
                if (effVal !== null) {
                    html += addSpecRow('Класс энергоэффективности', effVal, '');
                }
                
                // Напряжение (для трансформаторов, щитового оборудования)
                var voltVal = getSpecValue(['voltage_v', 'напряжение_в']);
                if (voltVal !== null) {
                    html += addSpecRow('Напряжение', voltVal, 'В');
                }
                
                // Частота (для двигателей, генераторов)
                var freqVal = getSpecValue(['frequency_hz', 'частота_гц']);
                if (freqVal !== null) {
                    html += addSpecRow('Частота', freqVal, 'Гц');
                }
                
                // Класс защиты (IP)
                var protVal = getSpecValue(['protection_class', 'степень_защиты']);
                if (protVal !== null) {
                    html += addSpecRow('Класс защиты', protVal, '');
                }
                
                // Варианты монтажа (для двигателей)
                var mountVal = getSpecValue(['mounting_versions', 'монтаж']);
                if (mountVal !== null) {
                    html += addSpecRow('Варианты монтажа', mountVal, '');
                }
                
                // Климатическое исполнение
                var climateVal = getSpecValue(['climate_versions', 'климатическое_исполнение']);
                if (climateVal !== null) {
                    html += addSpecRow('Климатическое исполнение', climateVal, '');
                }
                
                // Вес / масса
                var weightVal = getSpecValue(['weight_kg', 'вес_кг', 'масса_кг']);
                if (weightVal !== null) {
                    html += addSpecRow('Масса', weightVal, 'кг');
                }
                
                // Материал корпуса
                var housingVal = getSpecValue(['housing_material', 'материал_корпуса']);
                if (housingVal !== null) {
                    html += addSpecRow('Материал корпуса', housingVal, '');
                }
                
                // Тип двигателя / тип продукта
                var motorVal = getSpecValue(['motor_type', 'тип_двигателя', 'тип_продукта']);
                if (motorVal !== null) {
                    html += addSpecRow('Тип', motorVal, '');
                }
                
                // Производительность (для насосов, вентиляторов)
                var flowVal = getSpecValue(['flow_rate_m3_h', 'производительность_м3ч']);
                if (flowVal !== null) {
                    html += addSpecRow('Производительность', flowVal, 'м³/ч');
                }
                
                // Напор (для насосов)
                var headVal = getSpecValue(['head_m', 'напор_м']);
                if (headVal !== null) {
                    html += addSpecRow('Напор', headVal, 'м');
                }
                
                // Взрывозащита
                var explVal = getSpecValue(['explosion_protection', 'взрывозащита']);
                if (explVal !== null) {
                    html += addSpecRow('Вид взрывозащиты', explVal, '');
                }
                
                // Назначение / область применения
                var appVal = getSpecValue(['application', 'область_применения', 'назначение']);
                if (appVal !== null) {
                    html += addSpecRow('Назначение', appVal, '');
                }
                
                // Ток номинальный (для щитового оборудования)
                var currentVal = getSpecValue(['current_a', 'ток_а', 'номинальный_ток_а', 'ток_номинальный_а']);
                if (currentVal !== null) {
                    html += addSpecRow('Номинальный ток', currentVal, 'А');
                }
                
                // Количество групп (для щитов освещения)
                var groupsCountVal = getSpecValue(['groups_count', 'количество_групп']);
                if (groupsCountVal !== null) {
                    html += addSpecRow('Количество групп', groupsCountVal, '');
                }
                
                // Количество двигателей (для щитов управления)
                var motorsCountVal = getSpecValue(['num_motors', 'количество_двигателей']);
                if (motorsCountVal !== null) {
                    html += addSpecRow('Количество двигателей', motorsCountVal, '');
                }
                
                // Потери холостого хода / короткого замыкания (для трансформаторов)
                var noLoadLossVal = getSpecValue(['no_load_loss_w', 'потери_холостого_хода_вт', 'потери_хх_вт']);
                if (noLoadLossVal !== null) {
                    html += addSpecRow('Потери холостого хода', noLoadLossVal, 'Вт');
                }
                var loadLossVal = getSpecValue(['load_loss_w', 'потери_кз_вт']);
                if (loadLossVal !== null) {
                    html += addSpecRow('Потери короткого замыкания', loadLossVal, 'Вт');
                }
                
                // Напряжение КЗ, ток ХХ (для трансформаторов)
                var shortCircuitVoltVal = getSpecValue(['short_circuit_voltage_v', 'напряжение_кз_в', 'напряжение_кз_проц']);
                if (shortCircuitVoltVal !== null) {
                    var scUnit = specs['напряжение_кз_проц'] !== undefined ? '%' : 'В';
                    html += addSpecRow('Напряжение КЗ', shortCircuitVoltVal, scUnit);
                }
                var noLoadCurrentVal = getSpecValue(['no_load_current_a', 'ток_хх_а', 'ток_хх_проц']);
                if (noLoadCurrentVal !== null) {
                    var nlcUnit = specs['ток_хх_проц'] !== undefined ? '%' : 'А';
                    html += addSpecRow('Ток холостого хода', noLoadCurrentVal, nlcUnit);
                }
                
                // Масса масла (для масляных трансформаторов)
                var oilMassVal = getSpecValue(['oil_mass_kg', 'масса_масла_кг']);
                if (oilMassVal !== null) {
                    html += addSpecRow('Масса масла', oilMassVal, 'кг');
                }
                
                // Схема соединения (для трансформаторов)
                var connectionSchemeVal = getSpecValue(['connection_scheme', 'схема_соединения']);
                if (connectionSchemeVal !== null) {
                    html += addSpecRow('Схема соединения', connectionSchemeVal, '');
                }
                
                // Расход топлива (для генераторов)
                var fuelConsumptionVal = getSpecValue(['fuel_consumption_l_h', 'расход_топлива_л_ч']);
                if (fuelConsumptionVal !== null) {
                    html += addSpecRow('Расход топлива', fuelConsumptionVal, 'л/ч');
                }
                
                // Уровень шума (для генераторов)
                var noiseLevelVal = getSpecValue(['noise_level_db', 'шум_дб']);
                if (noiseLevelVal !== null) {
                    html += addSpecRow('Уровень шума', noiseLevelVal, 'дБ');
                }
                
                // Тип запуска (для генераторов)
                var startTypeVal = getSpecValue(['start_type', 'тип_запуска']);
                if (startTypeVal !== null) {
                    html += addSpecRow('Тип запуска', startTypeVal, '');
                }
                
                // Исполнение (для генераторов)
                var executionTypeVal = getSpecValue(['execution_type', 'исполнение']);
                if (executionTypeVal !== null) {
                    html += addSpecRow('Исполнение', executionTypeVal, '');
                }
                
                // Объем бака (для генераторов)
                var tankVal = getSpecValue(['tank_capacity_l', 'объем_бака_л']);
                if (tankVal !== null) {
                    html += addSpecRow('Объем бака', tankVal, 'л');
                }
                
                // Тип топлива (для генераторов)
                var fuelVal = getSpecValue(['fuel_type', 'тип_топлива']);
                if (fuelVal !== null) {
                    html += addSpecRow('Тип топлива', fuelVal, '');
                }
                
                // Размеры (для запчастей)
                var dimVal = getSpecValue(['dimensions', 'размеры', 'length_mm', 'длина_мм', 'width_mm', 'ширина_мм', 'height_mm', 'высота_мм']);
                if (dimVal !== null) {
                    html += addSpecRow('Размеры', dimVal, 'мм');
                }
                
                // Диаметр (для запчастей)
                var diamVal = getSpecValue(['diameter_mm', 'диаметр_мм']);
                if (diamVal !== null) {
                    html += addSpecRow('Диаметр', diamVal, 'мм');
                }
                
                // Другие спецификации (не дублируя уже обработанные ключи)
                var processedKeys = ['power_kw', 'power_kw_min', 'power_kw_max', 'мощность_квт', 'мощность_квт_min', 'мощность_квт_max', 'мощность_основная_квт', 'мощность_ква',
                    'rpm', 'обороты_мин', 'shaft_height_mm', 'высота_оси_мм', 'габарит', 
                    'efficiency_class', 'класс_эффективности', 
                    'voltage_v', 'напряжение_в', 'frequency_hz', 'частота_гц', 
                    'protection_class', 'степень_защиты', 'mounting_versions', 'монтаж', 
                    'climate_versions', 'климатическое_исполнение', 
                    'application', 'область_применения', 'назначение',
                    'flow_rate_m3_h', 'производительность_м3ч', 'head_m', 'напор_м', 
                    'housing_material', 'материал_корпуса', 'explosion_protection', 'взрывозащита', 
                    'weight_kg', 'вес_кг', 'масса_кг', 'motor_type', 'тип_двигателя', 'тип_продукта',
                    'current_a', 'ток_а', 'номинальный_ток_а', 'ток_номинальный_а',
                    'groups_count', 'количество_групп', 'num_motors', 'количество_двигателей',
                    'no_load_loss_w', 'потери_холостого_хода_вт', 'load_loss_w', 'потери_кз_вт', 'потери_хх_вт',
                    'short_circuit_voltage_v', 'напряжение_кз_в', 'напряжение_кз_проц',
                    'no_load_current_a', 'ток_хх_а', 'ток_хх_проц',
                    'oil_mass_kg', 'масса_масла_кг', 'connection_scheme', 'схема_соединения',
                    'fuel_consumption_l_h', 'расход_топлива_л_ч', 'noise_level_db', 'шум_дб',
                    'start_type', 'тип_запуска', 'execution_type', 'исполнение',
                    'tank_capacity_l', 'объем_бака_л', 'fuel_type', 'тип_топлива',
                    'dimensions', 'размеры', 'length_mm', 'длина_мм', 'width_mm', 'ширина_мм', 'height_mm', 'высота_мм',
                    'diameter_mm', 'диаметр_мм'];
                
                for (var key in specs) {
                    if (!processedKeys.includes(key)) {
                        var value = specs[key];
                        if (value !== null && value !== undefined && value !== '') {
                            if (Array.isArray(value)) {
                                value = value.join(', ');
                            }
                            html += '<div class="spec-row"><span class="spec-label">' + escapeHtml(key) + ':</span><span class="spec-value">' + escapeHtml(String(value)) + '</span></div>';
                        }
                    }
                }
                
                html += '</div>';
                html += '</div>';
            }
            
            // === ДАТЫ И ГАРАНТИЯ ===
            html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px;">';
            html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">📅 Даты и гарантия</div>';
            html += '<div class="specs-list">';
            if (product.manufacture_date) {
                html += '<div class="spec-row"><span class="spec-label">Дата выпуска:</span><span class="spec-value">' + formatDate(product.manufacture_date) + '</span></div>';
            }
            if (product.warranty_start) {
                html += '<div class="spec-row"><span class="spec-label">Начало гарантии:</span><span class="spec-value">' + formatDate(product.warranty_start) + '</span></div>';
            }
            if (product.warranty_end) {
                html += '<div class="spec-row"><span class="spec-label">Окончание гарантии:</span><span class="spec-value">' + formatDate(product.warranty_end) + '</span></div>';
            }
            if (product.warranty_months) {
                html += '<div class="spec-row"><span class="spec-label">Срок гарантии:</span><span class="spec-value">' + product.warranty_months + ' мес.</span></div>';
            }
            html += '</div>';
            html += '</div>';
            
            // === ОПИСАНИЕ ===
            if (product.description) {
                html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px;">';
                html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">📝 Описание</div>';
                html += '<div style="font-size: 14px; line-height: 1.6; color: #374151;">' + escapeHtml(product.description) + '</div>';
                html += '</div>';
            }
            
            // === ПАСПОРТ ===
            html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px;">';
            html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">📄 Паспорт</div>';
            html += '<div class="document-list" id="modalDocumentsList">';
            html += '<div style="text-align: center; padding: 30px 20px;">';
            html += '<button onclick="printPassport()" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); border: none; color: white; padding: 14px 28px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3); transition: all 0.3s ease;" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 6px 20px rgba(37, 99, 235, 0.4)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 14px rgba(37, 99, 235, 0.3)\'">📄 Сформировать паспорт</button>';
            html += '<div style="margin-top: 12px; font-size: 13px; color: #9ca3af;">Документы отсутствуют</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // === РУКОВОДСТВО ПО ЭКСПЛУАТАЦИИ ===
            html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px;">';
            html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">📖 Руководство по эксплуатации</div>';
            html += '<div id="modalManualSection">';
            html += '<div style="text-align: center; padding: 20px; color: #6b7280;">Руководство не загружено</div>';
            html += '</div>';
            html += '</div>';
            
            // === ДОПОЛНИТЕЛЬНО ===
            html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
            html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">ℹ️ Дополнительно</div>';
            html += '<div class="specs-list">';
            if (product.created_at) {
                html += '<div class="spec-row"><span class="spec-label">Дата создания:</span><span class="spec-value">' + formatDate(product.created_at) + '</span></div>';
            }
            if (product.updated_at) {
                html += '<div class="spec-row"><span class="spec-label">Дата обновления:</span><span class="spec-value">' + formatDate(product.updated_at) + '</span></div>';
            }
            html += '</div>';
            html += '</div>';
            
            document.getElementById('modalProductBody').innerHTML = html;
            document.getElementById('productModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Загружаем данные о серийных номерах и документах
            loadProductSerialData(productId);
        }
        
        function loadProductSerialData(productId) {
            fetch('<?= asset('modules/warehouse/api_product_data.php') ?>?product_id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateModalWithSerialData(data.data);
                    }
                })
                .catch(error => {
                    console.error('Error loading serial data:', error);
                });
        }
        
        function updateModalWithSerialData(data) {
            // Обновляем серийный номер в основной информации
            var snDisplay = document.getElementById('modal-serial-number');
            if (snDisplay) {
                if (data.serial_number) {
                    snDisplay.textContent = data.serial_number;
                } else {
                    snDisplay.textContent = 'Не указан';
                }
            }
            
            // Обновляем даты и гарантию
            if (data.manufacture_date || data.warranty_start || data.warranty_end || data.warranty_months) {
                var datesHtml = '';
                if (data.manufacture_date) {
                    datesHtml += '<div class="spec-row"><span class="spec-label">Дата выпуска:</span><span class="spec-value">' + formatDate(data.manufacture_date) + '</span></div>';
                }
                if (data.warranty_start) {
                    datesHtml += '<div class="spec-row"><span class="spec-label">Начало гарантии:</span><span class="spec-value">' + formatDate(data.warranty_start) + '</span></div>';
                }
                if (data.warranty_end) {
                    datesHtml += '<div class="spec-row"><span class="spec-label">Окончание гарантии:</span><span class="spec-value">' + formatDate(data.warranty_end) + '</span></div>';
                }
                if (data.warranty_months) {
                    datesHtml += '<div class="spec-row"><span class="spec-label">Срок гарантии:</span><span class="spec-value">' + data.warranty_months + ' мес.</span></div>';
                }
                
                var datesSection = document.querySelectorAll('.passport-section')[2];
                if (datesSection) {
                    datesSection.querySelector('.specs-list').innerHTML = datesHtml;
                }
            }
            
            // Обновляем документы - показываем реальные данные или пустой список
            if (data.documents && data.documents.length > 0) {
                var docsHtml = '<button onclick="printPassport()" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); border: none; color: white; padding: 14px 28px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3); transition: all 0.3s ease; margin-bottom: 20px;" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 6px 20px rgba(37, 99, 235, 0.4)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 14px rgba(37, 99, 235, 0.3)\'">📄 Сформировать паспорт</button>';
                data.documents.forEach(function(doc) {
                    var icon = '📄';
                    if (doc.document_type === 'manual') icon = '📖';
                    else if (doc.document_type === 'certificate') icon = '🏆';
                    else if (doc.document_type === 'test_report') icon = '📊';
                    else if (doc.document_type === 'warranty_card') icon = '🛡️';
                    
                    docsHtml += '<div class="document-row" style="display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #e5e5e5; gap: 12px;">';
                    docsHtml += '<div class="document-icon-large" style="font-size: 28px;">' + icon + '</div>';
                    docsHtml += '<div class="document-info" style="flex: 1;">';
                    docsHtml += '<div class="document-name" style="font-weight: 600; font-size: 14px; color: #111827;">' + escapeHtml(doc.file_name) + '</div>';
                    docsHtml += '<div class="document-meta" style="font-size: 12px; color: #6b7280; margin-top: 2px;">' + getDocumentTypeLabel(doc.document_type) + ' • ' + formatDate(doc.uploaded_at) + '</div>';
                    docsHtml += '</div>';
                    docsHtml += '<a href="' + escapeHtml(doc.file_path) + '" class="btn-icon" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: white; border: 1px solid #e5e7eb; border-radius: 6px; text-decoration: none; font-size: 16px; transition: all 0.2s;" download>⬇️</a>';
                    docsHtml += '</div>';
                });
                
                var docsList = document.getElementById('modalDocumentsList');
                if (docsList) {
                    docsList.innerHTML = docsHtml;
                }
            } else {
                var docsList = document.getElementById('modalDocumentsList');
                if (docsList) {
                    docsList.innerHTML = '<div style="text-align: center; padding: 30px 20px;"><button onclick="printPassport()" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); border: none; color: white; padding: 14px 28px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3); transition: all 0.3s ease;" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 6px 20px rgba(37, 99, 235, 0.4)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 14px rgba(37, 99, 235, 0.3)\'">📄 Сформировать паспорт</button><div style="margin-top: 12px; font-size: 13px; color: #9ca3af;">Документы отсутствуют</div></div>';
                }
            }
            
            // Обновляем руководство по эксплуатации
            if (data.manual_path) {
                var manualHtml = '<div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f3f4f6; border-radius: 6px;">';
                manualHtml += '<div style="font-size: 32px;">📖</div>';
                manualHtml += '<div style="flex: 1;">';
                manualHtml += '<div style="font-weight: 600; color: #111827;">Руководство по эксплуатации</div>';
                manualHtml += '<div style="font-size: 12px; color: #6b7280;">PDF документ</div>';
                manualHtml += '</div>';
                manualHtml += '<a href="' + escapeHtml(data.manual_path) + '" class="btn-icon" style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #2563eb; border: none; border-radius: 6px; text-decoration: none; color: white; font-size: 18px;" download>⬇️</a>';
                manualHtml += '</div>';
                
                var manualSection = document.getElementById('modalManualSection');
                if (manualSection) {
                    manualSection.innerHTML = manualHtml;
                }
            } else {
                var manualSection = document.getElementById('modalManualSection');
                if (manualSection) {
                    manualSection.innerHTML = '<div style="text-align: center; padding: 20px; color: #9ca3af;">Руководство не загружено</div>';
                }
            }
        }
        
        function getDocumentTypeLabel(type) {
            var labels = {
                'manual': 'Руководство',
                'certificate': 'Сертификат',
                'test_report': 'Отчёт об испытаниях',
                'warranty_card': 'Гарантийный талон',
                'other': 'Другое'
            };
            return labels[type] || type;
        }
        
        function formatPrice(price) {
            return parseFloat(price).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$& ');
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '—';
            var date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            return date.toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });
        }
        
        function closeProductModal(event) {
            if (event && event.target !== event.currentTarget) return;
            closeProductModalDirect();
        }
        
        function closeProductModalDirect() {
            document.getElementById('productModalOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function printPassport() {
            // Проверяем наличие продукта
            if (!window.currentProduct) {
                alert('Ошибка: продукт не выбран');
                return;
            }
            
            var productId = window.currentProduct.id;
            var productSku = window.currentProduct.sku || '';
            
            if (!productId && !productSku) {
                alert('Ошибка: у продукта отсутствует идентификатор');
                return;
            }
            
            // Получаем серийный номер из модального окна
            var serialElement = document.getElementById('modal-serial-number');
            var serialNumber = serialElement ? serialElement.textContent.trim() : '';
            
            // Сохраняем новый серийный номер если он еще не сохранен
            saveSerialNumber(productId || productSku, serialNumber);
            
            // Открываем модальное окно для редактирования данных перед печатью
            openPrintPreviewModal(productId || productSku, serialNumber);
        }
        
        function openPrintPreviewModal(printId, serialNumber) {
            // Загружаем текущие данные паспорта
            fetch('<?= asset('modules/production/api_passport.php') ?>?action=get_dynamic_data&id=' + encodeURIComponent(printId))
                .then(response => response.json())
                .then(data => {
                    // Заполняем форму данными
                    document.getElementById('pp_product_name').value = data.product_name || window.currentProduct.name_full || '';
                    document.getElementById('pp_product_description').value = data.product_description || '';
                    document.getElementById('pp_manufacture_date').value = data.manufacture_date || new Date().toISOString().split('T')[0];
                    document.getElementById('pp_warranty_period').value = data.warranty_period || '12';
                    document.getElementById('pp_warranty_start').value = data.warranty_start || new Date().toISOString().split('T')[0];
                    
                    // Рассчитываем дату окончания гарантии
                    if (data.warranty_start && data.warranty_period) {
                        var startDate = new Date(data.warranty_start);
                        var months = parseInt(data.warranty_period);
                        var endDate = new Date(startDate.setMonth(startDate.getMonth() + months));
                        document.getElementById('pp_warranty_end').value = endDate.toISOString().split('T')[0];
                    } else {
                        document.getElementById('pp_warranty_end').value = '';
                    }
                    
                    document.getElementById('pp_org_name').value = data.org_name || 'ОАО "Полесьеэлектромаш"';
                    document.getElementById('pp_org_address').value = data.org_address || '';
                    document.getElementById('pp_org_phone').value = data.org_phone || '';
                    document.getElementById('pp_org_email').value = data.org_email || '';
                    
                    // Сохраняем ID и серийный номер для последующего использования
                    window.printPreviewData = {
                        id: printId,
                        serial: serialNumber
                    };
                    
                    // Показываем модальное окно
                    var modal = document.getElementById('printPreviewModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        setTimeout(function() {
                            modal.classList.add('active');
                        }, 10);
                    }
                })
                .catch(error => {
                    console.error('Ошибка загрузки данных:', error);
                    alert('Ошибка загрузки данных паспорта. Продолжить с данными по умолчанию?');
                    // Показываем окно с пустыми значениями
                    window.printPreviewData = {
                        id: printId,
                        serial: serialNumber
                    };
                    var modal = document.getElementById('printPreviewModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        setTimeout(function() {
                            modal.classList.add('active');
                        }, 10);
                    }
                });
        }
        
        function closePrintPreviewModal() {
            var modal = document.getElementById('printPreviewModal');
            if (modal) {
                modal.classList.remove('active');
                setTimeout(function() {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function confirmAndPrint() {
            if (!window.printPreviewData) {
                alert('Ошибка: данные не загружены');
                return;
            }
            
            // Собираем данные из формы
            var formData = {
                action: 'save_dynamic_data',
                id: window.printPreviewData.id,
                serial_number: window.printPreviewData.serial,
                product_name: document.getElementById('pp_product_name').value,
                product_description: document.getElementById('pp_product_description').value,
                manufacture_date: document.getElementById('pp_manufacture_date').value,
                warranty_period: document.getElementById('pp_warranty_period').value,
                warranty_start: document.getElementById('pp_warranty_start').value,
                warranty_end: document.getElementById('pp_warranty_end').value,
                org_name: document.getElementById('pp_org_name').value,
                org_address: document.getElementById('pp_org_address').value,
                org_phone: document.getElementById('pp_org_phone').value,
                org_email: document.getElementById('pp_org_email').value
            };
            
            // Сохраняем данные
            fetch('<?= asset('modules/production/api_passport.php') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Закрываем модальное окно
                    closePrintPreviewModal();
                    
                    // Открываем страницу для печати в новом окне
                    var printUrl = '<?= asset('modules/warehouse/print_passport.php') ?>?id=' + encodeURIComponent(window.printPreviewData.id) + '&serial=' + encodeURIComponent(window.printPreviewData.serial);
                    var printWindow = window.open(printUrl, '_blank');
                    
                    if (printWindow) {
                        printWindow.onload = function() {
                            setTimeout(function() {
                                printWindow.print();
                            }, 500);
                        };
                    } else {
                        alert('Пожалуйста, разрешите открытие всплывающих окон для печати паспорта');
                    }
                } else {
                    alert('Ошибка сохранения данных: ' + (result.message || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Ошибка сохранения данных');
            });
        }
        
        // Автопересчет даты окончания гарантии при изменении периода или даты начала
        function recalculateWarrantyEnd() {
            var startDate = document.getElementById('pp_warranty_start').value;
            var period = document.getElementById('pp_warranty_period').value;
            
            if (startDate && period) {
                var date = new Date(startDate);
                var months = parseInt(period);
                var endDate = new Date(date.setMonth(date.getMonth() + months));
                document.getElementById('pp_warranty_end').value = endDate.toISOString().split('T')[0];
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
        
        // Закрытие по ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('productModalOverlay');
                if (modal && modal.classList.contains('active')) {
                    closeProductModalDirect();
                }
            }
        });
        
        // Функция сохранения серийного номера в БД
        function saveSerialNumber(productId, serialNumber) {
            fetch('<?= asset('modules/warehouse/api_save_serial.php') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    serial_number: serialNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Серийный номер сохранён:', serialNumber);
                } else {
                    console.error('Ошибка сохранения серийного номера:', data.error);
                }
            })
            .catch(error => console.error('Ошибка сохранения серийного номера:', error));
        }
    </script>

    <style>
        /* Стили для модального окна в формате паспорта */
        .passport-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .passport-section {
            background: white;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 16px;
        }

        .passport-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
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
            color: #6b7280;
            min-width: 200px;
            font-weight: 500;
            flex-shrink: 0;
        }

        .spec-value {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
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
            padding: 12px 0;
            border-bottom: 1px solid #e5e5e5;
            gap: 12px;
        }

        .document-row:last-child {
            border-bottom: none;
        }

        .document-icon-large {
            font-size: 28px;
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-weight: 600;
            font-size: 14px;
            color: #111827;
        }

        .document-meta {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }

        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-icon:hover {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
        }
        
        /* Стили для модального окна предпросмотра печати */
        #printPreviewModal {
            display: none;
        }
        
        .print-preview-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            max-height: 60vh;
            overflow-y: auto;
            padding: 8px;
        }
        
        .print-preview-form h4 {
            grid-column: 1 / -1;
            margin: 16px 0 8px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
        }
        
        .print-preview-form h4:first-child {
            margin-top: 0;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .form-field.full-width {
            grid-column: 1 / -1;
        }
        
        .form-field label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .form-field input,
        .form-field textarea {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .form-field textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .print-preview-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
    </style>
    
    <!-- Модальное окно предпросмотра и редактирования данных паспорта -->
    <div id="printPreviewModal" class="product-modal-overlay">
        <div class="product-modal">
            <div class="product-modal-header">
                <div>
                    <h3 class="product-modal-title">Редактирование данных паспорта</h3>
                    <p class="product-modal-subtitle">Измените данные перед печатью</p>
                </div>
                <button type="button" class="product-modal-close" onclick="closePrintPreviewModal()">&times;</button>
            </div>
            <div class="product-modal-body">
                <form id="printPreviewForm" onsubmit="event.preventDefault(); confirmAndPrint();">
                    <div class="print-preview-form">
                        <h4>Информация об изделии</h4>
                        
                        <div class="form-field full-width">
                            <label for="pp_product_name">Наименование изделия</label>
                            <input type="text" id="pp_product_name" name="pp_product_name" required>
                        </div>
                        
                        <div class="form-field full-width">
                            <label for="pp_product_description">Описание/Модель</label>
                            <textarea id="pp_product_description" name="pp_product_description"></textarea>
                        </div>
                        
                        <h4>Даты и гарантия</h4>
                        
                        <div class="form-field">
                            <label for="pp_manufacture_date">Дата изготовления</label>
                            <input type="date" id="pp_manufacture_date" name="pp_manufacture_date" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="pp_warranty_period">Гарантийный срок (месяцев)</label>
                            <input type="number" id="pp_warranty_period" name="pp_warranty_period" min="1" max="60" value="12" onchange="recalculateWarrantyEnd()">
                        </div>
                        
                        <div class="form-field">
                            <label for="pp_warranty_start">Начало гарантии</label>
                            <input type="date" id="pp_warranty_start" name="pp_warranty_start" onchange="recalculateWarrantyEnd()">
                        </div>
                        
                        <div class="form-field">
                            <label for="pp_warranty_end">Окончание гарантии</label>
                            <input type="date" id="pp_warranty_end" name="pp_warranty_end" readonly>
                        </div>
                        
                        <h4>Данные организации</h4>
                        
                        <div class="form-field full-width">
                            <label for="pp_org_name">Название организации</label>
                            <input type="text" id="pp_org_name" name="pp_org_name" required>
                        </div>
                        
                        <div class="form-field full-width">
                            <label for="pp_org_address">Адрес</label>
                            <input type="text" id="pp_org_address" name="pp_org_address">
                        </div>
                        
                        <div class="form-field">
                            <label for="pp_org_phone">Телефон</label>
                            <input type="tel" id="pp_org_phone" name="pp_org_phone">
                        </div>
                        
                        <div class="form-field">
                            <label for="pp_org_email">Email</label>
                            <input type="email" id="pp_org_email" name="pp_org_email">
                        </div>
                    </div>
                    
                    <div class="print-preview-actions">
                        <button type="button" onclick="closePrintPreviewModal()" style="padding: 10px 20px; border: 1px solid var(--border-color); background: var(--bg-primary); border-radius: 6px; cursor: pointer; font-size: 14px;">Отмена</button>
                        <button type="submit" style="padding: 10px 20px; background: var(--primary-color); border: none; color: white; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">💾 Сохранить и печатать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
