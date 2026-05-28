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
            
            // Определяем категорию продукта для выбора нужных характеристик
            $categoryId = intval($prod['category_id']);
            $parentCategoryId = intval($prod['category_parent_id']);
            
            // Полный маппинг всех возможных русскоязычных ключей БД
            $allSpecsMap = [
                // Основные характеристики двигателей
                'мощность_квт' => 'power_kw',
                'обороты_мин' => 'rpm',
                'напряжение_в' => 'voltage_v',
                'габарит' => 'frame_size',
                'высота_оси_мм' => 'shaft_height_mm',
                'класс_эффективности' => 'efficiency_class',
                'монтаж' => 'mounting_versions',
                'степень_защиты' => 'protection_class',
                'климатическое_исполнение' => 'climate_versions',
                'класс_изоляции' => 'insulation_class',
                'коэффициент_мощности' => 'power_factor',
                'кпд_проц' => 'efficiency_percent',
                'вес_кг' => 'weight_kg',
                // Взрывозащищенные
                'маркировка_взрывозащиты' => 'explosion_marking',
                'уровень_взрывозащиты' => 'explosion_level',
                // Крановые
                'режим_работы' => 'duty_cycle',
                'пв_проц' => 'duty_percent',
                // Генераторы
                'мощность_основная_квт' => 'power_main_kw',
                'мощность_резервная_квт' => 'power_reserve_kw',
                'мощность_максимальная_квт' => 'power_max_kw',
                'тип_топлива' => 'fuel_type',
                'тип_запуска' => 'start_type',
                'шум_дб' => 'noise_db',
                'фазы' => 'phases',
                'частота_гц' => 'frequency_hz',
                'расход_топлива_л_ч' => 'fuel_consumption_l_h',
                'объем_бака_л' => 'tank_capacity_l',
                'исполнение' => 'execution_type',
                'двигатель' => 'engine_model',
                // Трансформаторы
                'мощность_кВа' => 'power_kva',
                'напряжение_вн_кВ' => 'voltage_hv_kv',
                'напряжение_нн_кВ' => 'voltage_lv_kv',
                'ток_хх_А' => 'no_load_current_a',
                'потери_кВт' => 'losses_kw',
                // Насосы
                'производительность_м3ч' => 'flow_rate_m3_h',
                'напор_м' => 'head_m',
                'частота_гц' => 'frequency_hz',
                'взрывозащита' => 'explosion_protection',
                'область_применения' => 'application',
                'материал_корпуса' => 'housing_material',
                'тип_двигателя' => 'motor_type'
            ];
            
            // Создаем массив specs со всеми возможными значениями
            $fullSpecs = [];
            foreach ($allSpecsMap as $ruKey => $enKey) {
                if (isset($specs[$ruKey])) {
                    $fullSpecs[$enKey] = $specs[$ruKey];
                }
                // Также проверяем английские ключи (для обратной совместимости)
                if (isset($specs[$enKey]) && !isset($fullSpecs[$enKey])) {
                    $fullSpecs[$enKey] = $specs[$enKey];
                }
            }
            
            // Добавляем специальные поля для диапазонов мощности
            if (isset($specs['мощность_квт_min'])) $fullSpecs['power_kw_min'] = $specs['мощность_квт_min'];
            if (isset($specs['мощность_квт_max'])) $fullSpecs['power_kw_max'] = $specs['мощность_квт_max'];
            if (isset($specs['power_kw_min'])) $fullSpecs['power_kw_min'] = $fullSpecs['power_kw_min'] ?? $specs['power_kw_min'];
            if (isset($specs['power_kw_max'])) $fullSpecs['power_kw_max'] = $fullSpecs['power_kw_max'] ?? $specs['power_kw_max'];
            
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
                'specs' => $fullSpecs,
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
                        $categoryId = intval($product['category_id']);
                        
                        // Функция проверки значения (не null, не пустая строка, не 0)
                        function hasValue($val) {
                            return $val !== null && $val !== '' && $val !== 'null';
                        }
                        
                        // === ОБЩЕПРОМЫШЛЕННЫЕ ЭЛЕКТРОДВИГАТЕЛИ (категория 2) ===
                        if ($categoryId == 2) {
                            if (hasValue($specs['power_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Мощность', 'value' => $specs['power_kw'] . ' кВт'];
                            }
                            if (hasValue($specs['rpm'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Частота вращения', 'value' => $specs['rpm'] . ' об/мин'];
                            }
                            if (hasValue($specs['voltage_v'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Напряжение питания', 'value' => $specs['voltage_v'] . ' В'];
                            }
                            if (hasValue($specs['frame_size'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Габарит двигателя', 'value' => $specs['frame_size']];
                            }
                            if (hasValue($specs['efficiency_class'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Класс энергоэффективности', 'value' => $specs['efficiency_class']];
                            }
                            if (hasValue($specs['mounting_versions'] ?? null)) {
                                $val = is_array($specs['mounting_versions']) ? implode(', ', $specs['mounting_versions']) : $specs['mounting_versions'];
                                $displaySpecs[] = ['label' => 'Исполнение по монтажу', 'value' => $val];
                            }
                            if (hasValue($specs['protection_class'] ?? null)) {
                                $val = is_array($specs['protection_class']) ? implode(', ', $specs['protection_class']) : $specs['protection_class'];
                                $displaySpecs[] = ['label' => 'Степень защиты', 'value' => $val];
                            }
                            if (hasValue($specs['climate_versions'] ?? null)) {
                                $val = is_array($specs['climate_versions']) ? implode(', ', $specs['climate_versions']) : $specs['climate_versions'];
                                $displaySpecs[] = ['label' => 'Климатическое исполнение', 'value' => $val];
                            }
                            if (hasValue($specs['insulation_class'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Класс изоляции', 'value' => $specs['insulation_class']];
                            }
                            if (hasValue($specs['power_factor'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Коэффициент мощности (cos φ)', 'value' => $specs['power_factor']];
                            }
                            if (hasValue($specs['efficiency_percent'] ?? null)) {
                                $displaySpecs[] = ['label' => 'КПД', 'value' => $specs['efficiency_percent'] . '%'];
                            }
                            if (hasValue($specs['weight_kg'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Масса', 'value' => $specs['weight_kg'] . ' кг'];
                            }
                        }
                        // === ВЗРЫВОЗАЩИЩЕННЫЕ ДВИГАТЕЛИ (категория 3) ===
                        elseif ($categoryId == 3) {
                            if (hasValue($specs['power_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Мощность', 'value' => $specs['power_kw'] . ' кВт'];
                            }
                            if (hasValue($specs['rpm'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Частота вращения', 'value' => $specs['rpm'] . ' об/мин'];
                            }
                            if (hasValue($specs['voltage_v'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Напряжение питания', 'value' => $specs['voltage_v'] . ' В'];
                            }
                            if (hasValue($specs['frame_size'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Габарит двигателя', 'value' => $specs['frame_size']];
                            }
                            if (hasValue($specs['explosion_marking'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Маркировка взрывозащиты', 'value' => $specs['explosion_marking']];
                            }
                            if (hasValue($specs['efficiency_class'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Класс энергоэффективности', 'value' => $specs['efficiency_class']];
                            }
                            if (hasValue($specs['explosion_level'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Уровень взрывозащиты', 'value' => $specs['explosion_level']];
                            }
                            if (hasValue($specs['mounting_versions'] ?? null)) {
                                $val = is_array($specs['mounting_versions']) ? implode(', ', $specs['mounting_versions']) : $specs['mounting_versions'];
                                $displaySpecs[] = ['label' => 'Исполнение по монтажу', 'value' => $val];
                            }
                            if (hasValue($specs['protection_class'] ?? null)) {
                                $val = is_array($specs['protection_class']) ? implode(', ', $specs['protection_class']) : $specs['protection_class'];
                                $displaySpecs[] = ['label' => 'Степень защиты', 'value' => $val];
                            }
                            if (hasValue($specs['weight_kg'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Масса', 'value' => $specs['weight_kg'] . ' кг'];
                            }
                        }
                        // === КРАНОВЫЕ ДВИГАТЕЛИ (категория 4) ===
                        elseif ($categoryId == 4) {
                            if (hasValue($specs['power_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Мощность', 'value' => $specs['power_kw'] . ' кВт'];
                            }
                            if (hasValue($specs['rpm'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Частота вращения', 'value' => $specs['rpm'] . ' об/мин'];
                            }
                            if (hasValue($specs['voltage_v'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Напряжение питания', 'value' => $specs['voltage_v'] . ' В'];
                            }
                            if (hasValue($specs['frame_size'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Габарит двигателя', 'value' => $specs['frame_size']];
                            }
                            if (hasValue($specs['duty_cycle'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Режим работы', 'value' => $specs['duty_cycle']];
                            }
                            if (hasValue($specs['insulation_class'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Класс изоляции', 'value' => $specs['insulation_class']];
                            }
                            if (hasValue($specs['protection_class'] ?? null)) {
                                $val = is_array($specs['protection_class']) ? implode(', ', $specs['protection_class']) : $specs['protection_class'];
                                $displaySpecs[] = ['label' => 'Степень защиты', 'value' => $val];
                            }
                            if (hasValue($specs['duty_percent'] ?? null)) {
                                $displaySpecs[] = ['label' => 'ПВ (продолжительность включения)', 'value' => $specs['duty_percent'] . '%'];
                            }
                            if (hasValue($specs['weight_kg'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Масса', 'value' => $specs['weight_kg'] . ' кг'];
                            }
                        }
                        // === ДИЗЕЛЬНЫЕ ГЕНЕРАТОРЫ (категория 6) ===
                        elseif ($categoryId == 6) {
                            if (hasValue($specs['power_main_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Основная мощность', 'value' => $specs['power_main_kw'] . ' кВт'];
                            }
                            if (hasValue($specs['power_reserve_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Резервная мощность', 'value' => $specs['power_reserve_kw'] . ' кВт'];
                            }
                            if (hasValue($specs['fuel_type'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Тип топлива', 'value' => $specs['fuel_type']];
                            }
                            if (hasValue($specs['start_type'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Тип запуска', 'value' => $specs['start_type']];
                            }
                            if (hasValue($specs['noise_db'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Уровень шума', 'value' => $specs['noise_db'] . ' дБ'];
                            }
                            if (hasValue($specs['phases'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Количество фаз', 'value' => $specs['phases']];
                            }
                            if (hasValue($specs['voltage_v'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Напряжение', 'value' => $specs['voltage_v'] . ' В'];
                            }
                            if (hasValue($specs['frequency_hz'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Частота', 'value' => $specs['frequency_hz'] . ' Гц'];
                            }
                            if (hasValue($specs['fuel_consumption_l_h'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Расход топлива', 'value' => $specs['fuel_consumption_l_h'] . ' л/ч'];
                            }
                            if (hasValue($specs['tank_capacity_l'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Объем топливного бака', 'value' => $specs['tank_capacity_l'] . ' л'];
                            }
                            if (hasValue($specs['weight_kg'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Масса', 'value' => $specs['weight_kg'] . ' кг'];
                            }
                            if (hasValue($specs['execution_type'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Исполнение', 'value' => $specs['execution_type']];
                            }
                        }
                        // === БЕНЗИНОВЫЕ ГЕНЕРАТОРЫ (категория 7) ===
                        elseif ($categoryId == 7) {
                            if (hasValue($specs['power_main_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Основная мощность', 'value' => $specs['power_main_kw'] . ' кВт'];
                            }
                            if (hasValue($specs['power_max_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Максимальная мощность', 'value' => $specs['power_max_kw'] . ' кВт'];
                            }
                            if (hasValue($specs['fuel_type'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Тип топлива', 'value' => $specs['fuel_type']];
                            }
                            if (hasValue($specs['start_type'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Тип запуска', 'value' => $specs['start_type']];
                            }
                            if (hasValue($specs['noise_db'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Уровень шума', 'value' => $specs['noise_db'] . ' дБ'];
                            }
                            if (hasValue($specs['phases'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Количество фаз', 'value' => $specs['phases']];
                            }
                            if (hasValue($specs['voltage_v'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Напряжение', 'value' => $specs['voltage_v'] . ' В'];
                            }
                            if (hasValue($specs['engine_model'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Модель двигателя', 'value' => $specs['engine_model']];
                            }
                            if (hasValue($specs['tank_capacity_l'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Объем топливного бака', 'value' => $specs['tank_capacity_l'] . ' л'];
                            }
                            if (hasValue($specs['weight_kg'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Масса', 'value' => $specs['weight_kg'] . ' кг'];
                            }
                        }
                        // === ДЛЯ ВСЕХ ОСТАЛЬНЫХ КАТЕГОРИЙ - универсальный вывод ===
                        else {
                            // Мощность
                            if (hasValue($specs['power_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Мощность', 'value' => $specs['power_kw'] . ' кВт'];
                            } elseif (hasValue($specs['power_main_kw'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Мощность', 'value' => $specs['power_main_kw'] . ' кВт'];
                            }
                            // Обороты
                            if (hasValue($specs['rpm'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Обороты', 'value' => $specs['rpm'] . ' об/мин'];
                            }
                            // Напряжение
                            if (hasValue($specs['voltage_v'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Напряжение', 'value' => $specs['voltage_v'] . ' В'];
                            }
                            // Высота оси / габарит
                            if (hasValue($specs['shaft_height_mm'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Высота оси', 'value' => $specs['shaft_height_mm'] . ' мм'];
                            } elseif (hasValue($specs['frame_size'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Габарит', 'value' => $specs['frame_size']];
                            }
                            // Класс энергоэффективности
                            if (hasValue($specs['efficiency_class'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Класс энергоэффективности', 'value' => $specs['efficiency_class']];
                            }
                            // Класс защиты
                            if (hasValue($specs['protection_class'] ?? null)) {
                                $val = is_array($specs['protection_class']) ? implode(', ', $specs['protection_class']) : $specs['protection_class'];
                                $displaySpecs[] = ['label' => 'Класс защиты', 'value' => $val];
                            }
                            // Монтаж
                            if (hasValue($specs['mounting_versions'] ?? null)) {
                                $val = is_array($specs['mounting_versions']) ? implode(', ', $specs['mounting_versions']) : $specs['mounting_versions'];
                                $displaySpecs[] = ['label' => 'Варианты монтажа', 'value' => $val];
                            }
                            // Частота
                            if (hasValue($specs['frequency_hz'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Частота', 'value' => $specs['frequency_hz'] . ' Гц'];
                            }
                            // Климатическое исполнение
                            if (hasValue($specs['climate_versions'] ?? null)) {
                                $val = is_array($specs['climate_versions']) ? implode(', ', $specs['climate_versions']) : $specs['climate_versions'];
                                $displaySpecs[] = ['label' => 'Климатическое исполнение', 'value' => $val];
                            }
                            // Вес
                            if (hasValue($specs['weight_kg'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Вес', 'value' => $specs['weight_kg'] . ' кг'];
                            }
                            // Материал корпуса
                            if (hasValue($specs['housing_material'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Материал корпуса', 'value' => $specs['housing_material']];
                            }
                            // Тип двигателя
                            if (hasValue($specs['motor_type'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Тип двигателя', 'value' => $specs['motor_type']];
                            }
                            // Производительность (для насосов)
                            if (hasValue($specs['flow_rate_m3_h'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Производительность', 'value' => $specs['flow_rate_m3_h'] . ' м³/ч'];
                            }
                            // Напор (для насосов)
                            if (hasValue($specs['head_m'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Напор', 'value' => $specs['head_m'] . ' м'];
                            }
                            // Взрывозащита
                            if (hasValue($specs['explosion_protection'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Вид взрывозащиты', 'value' => $specs['explosion_protection']];
                            }
                            // Назначение
                            if (hasValue($specs['application'] ?? null)) {
                                $displaySpecs[] = ['label' => 'Назначение', 'value' => $specs['application']];
                            }
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
            <div class="product-modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; padding: 16px 24px; background: white; border-top: 1px solid #e5e7eb;">
                <button id="btnPrintPassport" onclick="printPassport()" style="background: var(--primary-color); border: none; color: white; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;" title="Распечатать паспорт изделия">
                    🖨️ Распечатать паспорт
                </button>
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
                
                // Мощность с поддержкой русских ключей
                if (specs.power_kw !== undefined || specs['мощность_квт'] !== undefined) {
                    var powerVal = specs.power_kw !== undefined ? specs.power_kw : specs['мощность_квт'];
                    html += '<div class="spec-row"><span class="spec-label">Мощность:</span><span class="spec-value">' + powerVal + ' кВт</span></div>';
                } else if (specs.power_kw_min !== undefined || specs.power_kw_max !== undefined || specs['мощность_квт_min'] !== undefined || specs['мощность_квт_max'] !== undefined) {
                    var powerMin = specs.power_kw_min !== undefined ? specs.power_kw_min : (specs['мощность_квт_min'] !== undefined ? specs['мощность_квт_min'] : null);
                    var powerMax = specs.power_kw_max !== undefined ? specs.power_kw_max : (specs['мощность_квт_max'] !== undefined ? specs['мощность_квт_max'] : null);
                    var powerRange = '';
                    if (powerMin !== null && powerMax !== null) {
                        powerRange = powerMin + ' - ' + powerMax + ' кВт';
                    } else if (powerMin !== null) {
                        powerRange = powerMin + ' кВт';
                    } else if (powerMax !== null) {
                        powerRange = powerMax + ' кВт';
                    }
                    if (powerRange) {
                        html += '<div class="spec-row"><span class="spec-label">Мощность:</span><span class="spec-value">' + powerRange + '</span></div>';
                    }
                }
                
                // Обороты с поддержкой русских ключей
                if (specs.rpm !== undefined || specs['обороты_мин'] !== undefined) {
                    var rpmVal = specs.rpm !== undefined ? specs.rpm : specs['обороты_мин'];
                    html += '<div class="spec-row"><span class="spec-label">Обороты:</span><span class="spec-value">' + rpmVal + ' об/мин</span></div>';
                }
                
                // Высота оси с поддержкой русских ключей
                if (specs.shaft_height_mm !== undefined || specs['высота_оси_мм'] !== undefined || specs['габарит'] !== undefined) {
                    var shaftVal = specs.shaft_height_mm !== undefined ? specs.shaft_height_mm : (specs['высота_оси_мм'] !== undefined ? specs['высота_оси_мм'] : specs['габарит']);
                    html += '<div class="spec-row"><span class="spec-label">Высота оси:</span><span class="spec-value">' + shaftVal + ' мм</span></div>';
                }
                
                // Класс энергоэффективности с поддержкой русских ключей
                if (specs.efficiency_class !== undefined || specs['класс_эффективности'] !== undefined) {
                    var effVal = specs.efficiency_class !== undefined ? specs.efficiency_class : specs['класс_эффективности'];
                    html += '<div class="spec-row"><span class="spec-label">Класс энергоэффективности:</span><span class="spec-value">' + escapeHtml(String(effVal)) + '</span></div>';
                }
                
                // Напряжение с поддержкой русских ключей
                if (specs.voltage_v !== undefined || specs['напряжение_в'] !== undefined) {
                    var voltVal = specs.voltage_v !== undefined ? specs.voltage_v : specs['напряжение_в'];
                    html += '<div class="spec-row"><span class="spec-label">Напряжение:</span><span class="spec-value">' + voltVal + ' В</span></div>';
                }
                
                // Частота с поддержкой русских ключей
                if (specs.frequency_hz !== undefined || specs['частота_гц'] !== undefined) {
                    var freqVal = specs.frequency_hz !== undefined ? specs.frequency_hz : specs['частота_гц'];
                    html += '<div class="spec-row"><span class="spec-label">Частота:</span><span class="spec-value">' + freqVal + ' Гц</span></div>';
                }
                
                // Класс защиты с поддержкой русских ключей
                if (specs.protection_class !== undefined || specs['степень_защиты'] !== undefined) {
                    var protVal = specs.protection_class !== undefined ? specs.protection_class : specs['степень_защиты'];
                    var protectionValue = Array.isArray(protVal) ? protVal.join(', ') : protVal;
                    html += '<div class="spec-row"><span class="spec-label">Класс защиты:</span><span class="spec-value">' + escapeHtml(protectionValue) + '</span></div>';
                }
                
                // Варианты монтажа с поддержкой русских ключей
                if (specs.mounting_versions !== undefined || specs['монтаж'] !== undefined) {
                    var mountVal = specs.mounting_versions !== undefined ? specs.mounting_versions : specs['монтаж'];
                    var mountingValue = Array.isArray(mountVal) ? mountVal.join(', ') : mountVal;
                    html += '<div class="spec-row"><span class="spec-label">Варианты монтажа:</span><span class="spec-value">' + escapeHtml(mountingValue) + '</span></div>';
                }
                
                // Климатическое исполнение с поддержкой русских ключей
                if (specs.climate_versions !== undefined || specs['климатическое_исполнение'] !== undefined) {
                    var climateVal = specs.climate_versions !== undefined ? specs.climate_versions : specs['климатическое_исполнение'];
                    var climateValue = Array.isArray(climateVal) ? climateVal.join(', ') : climateVal;
                    html += '<div class="spec-row"><span class="spec-label">Климатическое исполнение:</span><span class="spec-value">' + escapeHtml(climateValue) + '</span></div>';
                }
                
                // Вес с поддержкой русских ключей
                if (specs.weight_kg !== undefined || specs['вес_кг'] !== undefined) {
                    var weightVal = specs.weight_kg !== undefined ? specs.weight_kg : specs['вес_кг'];
                    html += '<div class="spec-row"><span class="spec-label">Вес:</span><span class="spec-value">' + weightVal + ' кг</span></div>';
                }
                
                // Материал корпуса с поддержкой русских ключей
                if (specs.housing_material !== undefined || specs['материал_корпуса'] !== undefined) {
                    var housingVal = specs.housing_material !== undefined ? specs.housing_material : specs['материал_корпуса'];
                    html += '<div class="spec-row"><span class="spec-label">Материал корпуса:</span><span class="spec-value">' + escapeHtml(housingVal) + '</span></div>';
                }
                
                // Тип двигателя с поддержкой русских ключей
                if (specs.motor_type !== undefined || specs['тип_двигателя'] !== undefined) {
                    var motorVal = specs.motor_type !== undefined ? specs.motor_type : specs['тип_двигателя'];
                    html += '<div class="spec-row"><span class="spec-label">Тип двигателя:</span><span class="spec-value">' + escapeHtml(motorVal) + '</span></div>';
                }
                if (specs.flow_rate_m3_h !== undefined || specs['производительность_м3ч'] !== undefined) {
                    var flowVal = specs.flow_rate_m3_h !== undefined ? specs.flow_rate_m3_h : specs['производительность_м3ч'];
                    html += '<div class="spec-row"><span class="spec-label">Производительность:</span><span class="spec-value">' + flowVal + ' м³/ч</span></div>';
                }
                if (specs.head_m !== undefined || specs['напор_м'] !== undefined) {
                    var headVal = specs.head_m !== undefined ? specs.head_m : specs['напор_м'];
                    html += '<div class="spec-row"><span class="spec-label">Напор:</span><span class="spec-value">' + headVal + ' м</span></div>';
                }
                if (specs.explosion_protection !== undefined || specs['взрывозащита'] !== undefined) {
                    var explVal = specs.explosion_protection !== undefined ? specs.explosion_protection : specs['взрывозащита'];
                    html += '<div class="spec-row"><span class="spec-label">Вид взрывозащиты:</span><span class="spec-value">' + escapeHtml(explVal) + '</span></div>';
                }
                if (specs.application !== undefined || specs['область_применения'] !== undefined) {
                    var appVal = specs.application !== undefined ? specs.application : specs['область_применения'];
                    html += '<div class="spec-row"><span class="spec-label">Назначение:</span><span class="spec-value">' + escapeHtml(appVal) + '</span></div>';
                }
                
                // Другие спецификации
                for (var key in specs) {
                    if (!['power_kw', 'power_kw_min', 'power_kw_max', 'мощность_квт', 'мощность_квт_min', 'мощность_квт_max', 'rpm', 'обороты_мин', 'shaft_height_mm', 'высота_оси_мм', 'габарит', 'efficiency_class', 'класс_эффективности', 'voltage_v', 'напряжение_в', 'frequency_hz', 'частота_гц', 'protection_class', 'степень_защиты', 'mounting_versions', 'монтаж', 'climate_versions', 'климатическое_исполнение', 'application', 'область_применения', 'flow_rate_m3_h', 'производительность_м3ч', 'head_m', 'напор_м', 'housing_material', 'материал_корпуса', 'explosion_protection', 'взрывозащита', 'weight_kg', 'вес_кг', 'motor_type', 'тип_двигателя'].includes(key)) {
                        var value = specs[key];
                        if (Array.isArray(value)) {
                            value = value.join(', ');
                        }
                        html += '<div class="spec-row"><span class="spec-label">' + escapeHtml(key) + ':</span><span class="spec-value">' + escapeHtml(String(value)) + '</span></div>';
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
            
            // === ДОКУМЕНТЫ ===
            html += '<div class="passport-section" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 16px;">';
            html += '<div class="passport-section-title" style="font-size: 14px; font-weight: 600; color: #2563eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e5e7eb;">📄 Документы</div>';
            html += '<div class="document-list" id="modalDocumentsList">';
            html += '<div style="text-align: center; padding: 20px; color: #6b7280;">Документы не прикреплены</div>';
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
                var docsHtml = '';
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
                    docsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #9ca3af;">Документы не прикреплены</div>';
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
