<?php
/**
 * Список продукции
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

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "SELECT p.*, c.name as category_name, 
               COALESCE(u.symbol, 'шт') as unit_name
        FROM products p 
        LEFT JOIN product_categories c ON p.category_id = c.id 
        LEFT JOIN base_units u ON p.base_unit_id = u.id
        WHERE 1=1";
$params = [];

if ($search) {
    // Поддержка как старой структуры (поле name), так и новой (name_full, name_short)
    $sql .= " AND (p.article LIKE ?";
    $params[] = "%$search%";
    
    // Проверяем наличие полей name_full и name_short
    try {
        $testStmt = $pdo->query("SELECT name_full FROM products LIMIT 1");
        $sql .= " OR p.name_full LIKE ? OR p.name_short LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    } catch (Exception $e) {
        // Если поля name_full нет, используем старое поле name
        $sql .= " OR p.name LIKE ?";
        $params[] = "%$search%";
    }
    $sql .= ")";
}

if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}

// Определяем поле для сортировки (name_full или name)
$sortField = 'name';
try {
    $testStmt = $pdo->query("SELECT name_full FROM products LIMIT 1");
    $sortField = 'name_full';
} catch (Exception $e) {
    $sortField = 'name';
}

$sql .= " ORDER BY p.$sortField ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Загружено продукции: " . count($products));
    error_log("SQL запрос: " . $sql);
    error_log("Параметры: " . json_encode($params));
    
    // Преобразуем JSON значения в обычные строки/числа И добавляем specifications как массив
    // Как в materials.php - берем значения напрямую из decoded JSON
    foreach ($products as &$product) {
        // Декодируем specifications JSON в массив для использования
        $specsArray = [];
        if (!empty($product['specifications'])) {
            $decodedSpecs = json_decode($product['specifications'], true);
            if (is_array($decodedSpecs)) {
                $specsArray = $decodedSpecs;
            }
        }
        // Добавляем decoded specifications как отдельное поле
        $product['specifications'] = $specsArray;
        
        // Берем значения напрямую из specsArray (как в materials.php)
        // Мощность
        if (isset($specsArray['мощность_квт'])) {
            $product['power_kw'] = is_numeric($specsArray['мощность_квт']) ? floatval($specsArray['мощность_квт']) : $specsArray['мощность_квт'];
        }
        // Обороты
        if (isset($specsArray['обороты_мин'])) {
            $product['rpm'] = is_numeric($specsArray['обороты_мин']) ? intval($specsArray['обороты_мин']) : $specsArray['обороты_мин'];
        }
        // Напряжение
        if (isset($specsArray['напряжение_в'])) {
            $product['voltage_v'] = is_numeric($specsArray['напряжение_в']) ? intval($specsArray['напряжение_в']) : $specsArray['напряжение_в'];
        }
        // Частота
        if (isset($specsArray['частота_гц'])) {
            $product['frequency_hz'] = is_numeric($specsArray['частота_гц']) ? intval($specsArray['частота_гц']) : $specsArray['частота_гц'];
        }
        // Класс эффективности
        if (isset($specsArray['класс_эффективности'])) {
            $product['efficiency_class'] = strval($specsArray['класс_эффективности']);
        }
        // Высота оси
        if (isset($specsArray['высота_оси_мм'])) {
            $product['shaft_height_mm'] = is_numeric($specsArray['высота_оси_мм']) ? floatval($specsArray['высота_оси_мм']) : $specsArray['высота_оси_мм'];
        }
        // Габарит
        if (isset($specsArray['габарит'])) {
            $product['frame_size'] = strval($specsArray['габарит']);
        }
        // Климатическое исполнение
        if (isset($specsArray['климатическое_исполнение'])) {
            $product['climate_versions'] = strval($specsArray['климатическое_исполнение']);
        }
        // Монтаж
        if (isset($specsArray['монтаж'])) {
            $product['mounting_versions'] = strval($specsArray['монтаж']);
        }
        // Степень защиты
        if (isset($specsArray['степень_защиты'])) {
            $product['protection_class'] = strval($specsArray['степень_защиты']);
        }
        // Тип двигателя
        if (isset($specsArray['тип_двигателя'])) {
            $product['motor_type'] = strval($specsArray['тип_двигателя']);
        }
        // Область применения
        if (isset($specsArray['область_применения'])) {
            $product['application'] = strval($specsArray['область_применения']);
        }
        // Материал корпуса
        if (isset($specsArray['материал_корпуса'])) {
            $product['housing_material'] = strval($specsArray['материал_корпуса']);
        }
        // Материал вала
        if (isset($specsArray['материал_вала'])) {
            $product['shaft_material'] = strval($specsArray['материал_вала']);
        }
        // Взрывозащита
        if (isset($specsArray['взрывозащита'])) {
            $product['explosion_protection'] = strval($specsArray['взрывозащита']);
        }
        // Конденсатор в комплекте
        if (isset($specsArray['конденсатор_в_комплекте'])) {
            $val = $specsArray['конденсатор_в_комплекте'];
            $product['capacitor_included'] = ($val === true || $val === 'true' || $val === '1' || $val === 1);
        }
        // Стандарт
        if (isset($specsArray['стандарт'])) {
            $product['standard'] = strval($specsArray['стандарт']);
        }
        // Вес
        if (isset($specsArray['вес_кг'])) {
            $product['weight_range_kg'] = is_numeric($specsArray['вес_кг']) ? floatval($specsArray['вес_кг']) : $specsArray['вес_кг'];
        }
        // Гарантия
        if (isset($specsArray['гарантия_мес'])) {
            $product['warranty_months'] = is_numeric($specsArray['гарантия_мес']) ? intval($specsArray['гарантия_мес']) : $specsArray['гарантия_мес'];
        }
    }
    unset($product);
    
    error_log("Преобразовано продуктов: " . count($products));
} catch (Exception $e) {
    error_log("Ошибка при загрузке продукции: " . $e->getMessage());
    error_log("SQL: " . $sql);
    error_log("Trace: " . $e->getTraceAsString());
    $products = [];
}

// Получение серийных номеров и документов для каждого продукта
error_log("Начинаем обработку " . count($products) . " продуктов");
foreach ($products as &$product) {
    error_log("Обработка продукта ID=" . $product['id'] . ", name=" . ($product['name_full'] ?? $product['name_short'] ?? $product['name'] ?? 'N/A'));
    
    // Получение последнего серийного номера для продукта
    try {
        $serialStmt = $pdo->prepare("SELECT id, serial_number, production_date, warranty_start, warranty_end, notes 
                                      FROM product_serial_numbers 
                                      WHERE product_id = ? 
                                      ORDER BY created_at DESC LIMIT 1");
        $serialStmt->execute([$product['id']]);
        $serialData = $serialStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Ошибка получения серийного номера для продукта ID=" . $product['id'] . ": " . $e->getMessage());
        $serialData = false;
    }
    
    if ($serialData) {
        $product['serial_number'] = $serialData['serial_number'];
        $product['manufacture_date'] = $serialData['production_date'];
        $product['warranty_start'] = $serialData['warranty_start'];
        $product['warranty_end'] = $serialData['warranty_end'];
        $product['notes'] = $serialData['notes'];
        
        // Получение документов для этого серийного номера
        $docsStmt = $pdo->prepare("SELECT document_type, file_name, file_path, file_size, mime_type, description 
                                   FROM product_documents 
                                   WHERE serial_number_id = ? 
                                   ORDER BY uploaded_at DESC");
        $docsStmt->execute([$serialData['id']]);
        $docs = $docsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $product['documents'] = [];
        foreach ($docs as $doc) {
            $product['documents'][] = [
                'type' => $doc['document_type'],
                'name' => $doc['description'] ?: $doc['file_name'],
                'url' => $doc['file_path'],
                'size' => $doc['file_size'] ? round($doc['file_size'] / 1024, 1) . ' KB' : null
            ];
        }
        
        // Получение пути к руководству
        $product['manual_url'] = null;
    } else {
        $product['serial_number'] = null;
        $product['manufacture_date'] = null;
        $product['warranty_start'] = null;
        $product['warranty_end'] = null;
        $product['notes'] = null;
        $product['documents'] = [];
        $product['manual_url'] = null;
    }
}

// Получение категорий
try {
    $catStmt = $pdo->query("SELECT * FROM product_categories ORDER BY name");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Ошибка при загрузке категорий: " . $e->getMessage());
    $categories = [];
}

// Получение шаблонов свойств для всех категорий
try {
    $templatesStmt = $pdo->query("SELECT category_id, code, name, property_type, unit, sort_order FROM product_property_templates ORDER BY category_id, sort_order");
    $propertyTemplates = $templatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Группируем шаблоны по category_id
    $templatesByCategory = [];
    foreach ($propertyTemplates as $tmpl) {
        if (!isset($templatesByCategory[$tmpl['category_id']])) {
            $templatesByCategory[$tmpl['category_id']] = [];
        }
        $templatesByCategory[$tmpl['category_id']][] = $tmpl;
    }
} catch (Exception $e) {
    error_log("Ошибка при загрузке шаблонов свойств: " . $e->getMessage());
    $templatesByCategory = [];
}

error_log("Всего категорий продукции: " . count($categories));
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
                <div class="content">
                    <div class="page-header">
                        <div class="page-header-title">
                            <h2>📦 Продукция</h2>
                            <p>Каталог выпускаемой продукции</p>
                        </div>
                        <div class="page-header-actions">
                            <?php if (hasPermission('products.create')): ?>
                                <a href="create.php" class="btn btn-primary">+ Добавить</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Поиск по названию, артикулу..." value="<?= e($search) ?>" 
                           style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    <select name="category" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-secondary">Найти</button>
                    <a href="list.php" class="btn btn-outline">Сброс</a>
                </div>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>Артикул</th>
                        <th>Наименование</th>
                        <th>Категория</th>
                        <th>Мощность, кВт</th>
                        <th>Обороты, об/мин</th>
                        <th>Цена (BYN)</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <?php 
                    // Подготовка данных для безопасной передачи в JS
                    $specs = !empty($p['specifications']) && is_array($p['specifications']) ? $p['specifications'] : [];
                    
                    $productData = [
                        'id' => $p['id'],
                        'name' => $p['name'] ?? '',
                        'article' => $p['article'] ?? '',
                        'category_name' => $p['category_name'] ?? '',
                        'base_price' => $p['base_price'] ?? 0,
                        'is_active' => (int)($p['is_active'] ?? 0),
                        'specifications' => $specs
                    ];
                    ?>
                    <tr class="table-row-clickable" onclick="openProductModal(<?= json_encode($productData, JSON_UNESCAPED_UNICODE) ?>)">
                        <td><code><?= e($p['article']) ?></code></td>
                        <td><strong><?= e($p['name'] ?? '—') ?></strong></td>
                        <td><?= e($p['category_name'] ?? '—') ?></td>
                        <td><?= isset($specs['мощность_квт']) ? e($specs['мощность_квт']) : '-' ?></td>
                        <td><?= isset($specs['частота_вращения_об_мин']) ? e($specs['частота_вращения_об_мин']) : '-' ?></td>
                        <td><?= number_format((float)($p['base_price'] ?? 0), 2, ',', ' ') ?></td>
                        <td>
                            <?php if (isset($p['is_active']) && ($p['is_active'] == 1 || $p['is_active'] === true)): ?>
                                <span class="badge badge-success">✓ Активно</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">○ Не активно</span>
                            <?php endif; ?>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <?php if (hasPermission('products.edit')): ?>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn-icon" title="Редактировать">✏️</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>Продукция не найдена</h3>
                    <p>Измените параметры фильтрации или добавьте первую позицию продукции</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
    
    <!-- Модальное окно просмотра продукции -->
    <div id="productModalOverlay" class="product-modal-overlay" onclick="closeProductModal(event)">
        <div class="product-modal product-passport-modal">
            <div class="product-modal-header passport-header">
                <div>
                    <div style="font-size: 12px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;">Паспорт изделия</div>
                    <h3 class="product-modal-title" id="modalProductName" style="margin-top: 8px; font-size: 22px;">Название продукции</h3>
                    <p class="product-modal-subtitle" id="modalProductArticle" style="margin-top: 6px; font-size: 14px;">Артикул: —</p>
                </div>
                <button class="product-modal-close" onclick="closeProductModalDirect()">×</button>
            </div>
            <div class="product-modal-body passport-body" id="modalProductBody" style="max-height: calc(85vh - 180px); overflow-y: auto;">
                <!-- Контент будет заполнен через JS -->
            </div>
            <div class="product-modal-footer passport-footer" style="padding: 20px 24px; background: white; border-top: 1px solid var(--border-color); display: flex; justify-content: center; gap: 12px; flex-shrink: 0;">
                <!-- Кнопка перемещена в раздел Паспорт -->
            </div>
        </div>
    </div>
    
    <script>
        function openProductModal(product) {
            console.log('Opening product:', product);
            console.log('Product name:', product.name);
            console.log('Product name_full:', product.name_full);
            console.log('Product name_short:', product.name_short);
            
            // Сохраняем текущий продукт для использования в функции печати
            window.currentProduct = product;
            
            document.getElementById('modalProductName').textContent = product.name || 'Без названия';
            document.getElementById('modalProductArticle').textContent = 'Артикул: ' + (product.article || '—');
            
            var statusBadge = '';
            if (product.is_active !== undefined && product.is_active !== null) {
                var statusClass = product.is_active == 1 ? 'product-status-active' : 'product-status-inactive';
                var statusText = product.is_active == 1 ? '✓ Активно' : '○ Не активно';
                statusBadge = '<span class="product-status-badge ' + statusClass + '">' + statusText + '</span>';
            }
            
            var html = '';
            
            // Основная информация в виде списка как в паспорте
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📋 Основная информация</div>';
            html += '<div class="specs-list">';
            html += '<div class="spec-row"><div class="spec-label">Артикул</div><div class="spec-value">' + (product.article || '—') + '</div></div>';
            
            // Серийный номер - как обычное свойство в основной информации
            var serialNumber = product.serial_number || null;
            if (!serialNumber) {
                // Генерируем временный серийный номер для отображения
                var date = new Date();
                var year = date.getFullYear().toString().substr(-2);
                var month = (date.getMonth() + 1).toString().padStart(2, '0');
                var randomPart = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                serialNumber = 'SN-' + year + month + '-' + product.id + '-' + randomPart;
            }
            html += '<div class="spec-row"><div class="spec-label">Серийный номер</div><div class="spec-value">' + escapeHtml(serialNumber) + '</div></div>';
            
            if (product.code_gost) {
                html += '<div class="spec-row"><div class="spec-label">ГОСТ</div><div class="spec-value">' + escapeHtml(product.code_gost) + '</div></div>';
            }
            html += '<div class="spec-row"><div class="spec-label">Категория</div><div class="spec-value">' + (product.category_name || '—') + '</div></div>';
            html += '<div class="spec-row"><div class="spec-label">Ед. измерения</div><div class="spec-value">' + (product.unit_name || '—') + '</div></div>';
            html += '<div class="spec-row"><div class="spec-label">Цена</div><div class="spec-value">' + (product.base_price ? Number(product.base_price).toFixed(2).replace('.', ',') + ' BYN' : '—') + '</div></div>';
            html += '<div class="spec-row"><div class="spec-label">Статус</div><div class="spec-value">' + statusBadge + '</div></div>';
            html += '</div>';
            html += '</div>';
            
            // Технические характеристики электродвигателя из отдельных полей
            var hasSpecs = product.power_kw || product.rpm || product.voltage_v || product.shaft_height_mm || product.frame_size;
            if (hasSpecs) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">⚙️ Технические характеристики</div>';
                html += '<div class="specs-list">';
                
                if (product.power_kw) {
                    html += '<div class="spec-row"><div class="spec-label">Мощность, кВт</div><div class="spec-value">' + escapeHtml(String(product.power_kw)) + '</div></div>';
                }
                if (product.rpm) {
                    html += '<div class="spec-row"><div class="spec-label">Частота вращения, об/мин</div><div class="spec-value">' + escapeHtml(String(product.rpm)) + '</div></div>';
                }
                if (product.voltage_v) {
                    html += '<div class="spec-row"><div class="spec-label">Напряжение, В</div><div class="spec-value">' + escapeHtml(product.voltage_v) + '</div></div>';
                }
                if (product.frequency_hz) {
                    html += '<div class="spec-row"><div class="spec-label">Частота тока, Гц</div><div class="spec-value">' + escapeHtml(String(product.frequency_hz)) + '</div></div>';
                }
                if (product.efficiency_class) {
                    html += '<div class="spec-row"><div class="spec-label">Класс энергоэффективности</div><div class="spec-value">' + escapeHtml(product.efficiency_class) + '</div></div>';
                }
                if (product.shaft_height_mm) {
                    html += '<div class="spec-row"><div class="spec-label">Высота оси вращения, мм</div><div class="spec-value">' + escapeHtml(String(product.shaft_height_mm)) + '</div></div>';
                }
                if (product.frame_size) {
                    html += '<div class="spec-row"><div class="spec-label">Типоразмер корпуса</div><div class="spec-value">' + escapeHtml(product.frame_size) + '</div></div>';
                }
                if (product.climate_versions) {
                    html += '<div class="spec-row"><div class="spec-label">Климатическое исполнение</div><div class="spec-value">' + escapeHtml(product.climate_versions) + '</div></div>';
                }
                if (product.mounting_versions) {
                    html += '<div class="spec-row"><div class="spec-label">Исполнение по монтажу</div><div class="spec-value">' + escapeHtml(product.mounting_versions) + '</div></div>';
                }
                if (product.protection_class) {
                    html += '<div class="spec-row"><div class="spec-label">Класс защиты</div><div class="spec-value">' + escapeHtml(product.protection_class) + '</div></div>';
                }
                if (product.motor_type) {
                    html += '<div class="spec-row"><div class="spec-label">Тип двигателя</div><div class="spec-value">' + escapeHtml(product.motor_type) + '</div></div>';
                }
                if (product.application) {
                    html += '<div class="spec-row"><div class="spec-label">Область применения</div><div class="spec-value">' + escapeHtml(product.application) + '</div></div>';
                }
                if (product.housing_material) {
                    html += '<div class="spec-row"><div class="spec-label">Материал корпуса</div><div class="spec-value">' + escapeHtml(product.housing_material) + '</div></div>';
                }
                if (product.shaft_material) {
                    html += '<div class="spec-row"><div class="spec-label">Материал вала</div><div class="spec-value">' + escapeHtml(product.shaft_material) + '</div></div>';
                }
                if (product.explosion_protection) {
                    html += '<div class="spec-row"><div class="spec-label">Взрывозащита</div><div class="spec-value">' + escapeHtml(product.explosion_protection) + '</div></div>';
                }
                if (product.capacitor_included) {
                    html += '<div class="spec-row"><div class="spec-label">Конденсатор в комплекте</div><div class="spec-value">✓ Да</div></div>';
                }
                if (product.standard) {
                    html += '<div class="spec-row"><div class="spec-label">Стандарт</div><div class="spec-value">' + escapeHtml(product.standard) + '</div></div>';
                }
                if (product.weight_range_kg) {
                    html += '<div class="spec-row"><div class="spec-label">Вес, кг</div><div class="spec-value">' + escapeHtml(product.weight_range_kg) + '</div></div>';
                }
                
                html += '</div>';
                html += '</div>';
            }
            
            // Даты и гарантия
            var hasDates = product.manufacture_date || product.warranty_start || product.warranty_end || product.warranty_months;
            if (hasDates) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📅 Даты и гарантия</div>';
                html += '<div class="specs-list">';
                if (product.manufacture_date) {
                    html += '<div class="spec-row"><div class="spec-label">Дата выпуска</div><div class="spec-value">' + formatDate(product.manufacture_date) + '</div></div>';
                }
                if (product.warranty_start) {
                    html += '<div class="spec-row"><div class="spec-label">Начало гарантии</div><div class="spec-value">' + formatDate(product.warranty_start) + '</div></div>';
                }
                if (product.warranty_end) {
                    html += '<div class="spec-row"><div class="spec-label">Гарантия до</div><div class="spec-value">' + formatDate(product.warranty_end) + '</div></div>';
                }
                if (product.warranty_months) {
                    html += '<div class="spec-row"><div class="spec-label">Срок гарантии</div><div class="spec-value">' + product.warranty_months + ' мес.</div></div>';
                }
                html += '</div>';
                html += '</div>';
            }
            
            // Полное описание
            if (product.description && product.description.trim() !== '') {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📝 Описание</div>';
                html += '<div class="spec-row"><div class="spec-value" style="white-space: pre-wrap; line-height: 1.6;">' + escapeHtml(product.description) + '</div></div>';
                html += '</div>';
            }
            
            // Паспорт (документы)
            html += '<div class="passport-section">';
            html += '<div class="passport-section-title">📄 Паспорт</div>';
            
            if (product.documents && product.documents.length > 0) {
                html += '<button onclick="printProductPassport()" style="background: var(--primary-color); border: none; color: white; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 16px;" title="Сформировать паспорт изделия">';
                html += '📄 Сформировать паспорт';
                html += '</button>';
                html += '<ul class="document-list">';
                for (var i = 0; i < product.documents.length; i++) {
                    var doc = product.documents[i];
                    var docIcon = getDocumentIcon(doc.type);
                    html += '<li class="document-row">';
                    html += '<div class="document-icon-large">' + docIcon + '</div>';
                    html += '<div class="document-info">';
                    html += '<div class="document-name">' + escapeHtml(doc.name) + '</div>';
                    if (doc.size) {
                        html += '<div class="document-meta">' + doc.size + '</div>';
                    }
                    html += '</div>';
                    html += '<div class="document-actions">';
                    html += '<a href="' + escapeHtml(doc.url) + '" target="_blank" class="btn-icon" title="Просмотреть">👁️</a>';
                    html += '<a href="' + escapeHtml(doc.url) + '" download class="btn-icon" title="Скачать">⬇️</a>';
                    html += '</div>';
                    html += '</li>';
                }
                html += '</ul>';
            } else {
                // Если документов нет, показываем только красивую кнопку
                html += '<button type="button" onclick="printProductPassport(' + productId + ')" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">';
                html += '<span style="font-size: 18px;">📄</span> Сформировать паспорт';
                html += '</button>';
            }
            html += '</div>';
            
            // Инструкция по эксплуатации
            if (product.manual_url || product.manual_text) {
                html += '<div class="passport-section">';
                html += '<div class="passport-section-title">📘 Руководство по эксплуатации</div>';
                if (product.manual_url) {
                    html += '<a href="' + escapeHtml(product.manual_url) + '" target="_blank" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: 12px;">📥 Скачать инструкцию</a>';
                }
                if (product.manual_text) {
                    html += '<div class="spec-row"><div class="spec-value" style="white-space: pre-wrap; line-height: 1.6;">' + escapeHtml(product.manual_text) + '</div></div>';
                }
                html += '</div>';
            }
            
            // Дополнительная информация (дата создания, обновления)
            html += '<div class="passport-section" style="border-top: 2px solid var(--border-color); margin-top: 20px; padding-top: 16px;">';
            html += '<div class="passport-section-title" style="font-size: 13px;">ℹ️ Дополнительно</div>';
            html += '<div class="specs-list">';
            if (product.created_at) {
                html += '<div class="spec-row"><div class="spec-label">Создан</div><div class="spec-value">' + escapeHtml(product.created_at) + '</div></div>';
            }
            if (product.updated_at) {
                html += '<div class="spec-row"><div class="spec-label">Обновлён</div><div class="spec-value">' + escapeHtml(product.updated_at) + '</div></div>';
            }
            html += '</div>';
            html += '</div>';
            
            document.getElementById('modalProductBody').innerHTML = html;
            document.getElementById('productModalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function formatSpecName(key) {
            var names = {
                'power_kw': 'Мощность, кВт',
                'power_kw_min': 'Мощность мин., кВт',
                'power_kw_max': 'Мощность макс., кВт',
                'rpm': 'Обороты, об/мин',
                'shaft_height_mm': 'Высота оси, мм',
                'voltage_v': 'Напряжение, В',
                'frequency_hz': 'Частота, Гц',
                'climate_versions': 'Климатическое исполнение',
                'mounting_versions': 'Варианты монтажа',
                'protection_class': 'Класс защиты',
                'diameter_mm': 'Диаметр, мм',
                'material': 'Материал',
                'application': 'Применение',
                'type': 'Тип',
                'weight_kg': 'Вес, кг',
                'dimensions': 'Габариты'
            };
            return names[key] || key;
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '—';
            var date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            var day = String(date.getDate()).padStart(2, '0');
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var year = date.getFullYear();
            return day + '.' + month + '.' + year;
        }
        
        function getDocumentIcon(type) {
            var icons = {
                'passport': '📄',
                'certificate': '📜',
                'manual': '📘',
                'warranty': '🛡️',
                'test_report': '📊',
                'other': '📁'
            };
            return icons[type] || '📁';
        }
        
        function closeProductModal(event) {
            if (event.target === document.getElementById('productModalOverlay')) {
                closeProductModalDirect();
            }
        }
        
        function closeProductModalDirect() {
            document.getElementById('productModalOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function printProductPassport() {
            // Получаем данные текущего продукта из модального окна
            var productName = document.getElementById('modalProductName').textContent;
            var productArticle = document.getElementById('modalProductArticle').textContent;
            
            // Извлекаем ID продукта из данных, сохранённых в модальном окне
            if (!window.currentProduct) {
                alert('Ошибка: нет данных о продукте. Пожалуйста, откройте продукт заново.');
                return;
            }
            
            var productId = window.currentProduct.id;
            if (!productId) {
                alert('Ошибка: нет ID продукта');
                return;
            }
            
            // Получаем серийный номер из модального окна
            var serialElement = document.querySelector('.serial-number-display');
            var serialNumber = serialElement ? serialElement.textContent.trim() : '';
            
            // Если серийный номер пустой, генерируем его
            if (!serialNumber || serialNumber === 'Не присвоен') {
                var date = new Date();
                var year = date.getFullYear().toString().substr(-2);
                var month = (date.getMonth() + 1).toString().padStart(2, '0');
                var randomPart = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                serialNumber = 'SN-' + year + month + '-' + productId + '-' + randomPart;
                
                // Сохраняем новый серийный номер через AJAX
                saveProductSerialNumber(productId, serialNumber);
                
                // Обновляем отображение
                if (serialElement) {
                    serialElement.textContent = serialNumber;
                }
            }
            
            // Открываем страницу для печати в новом окне
            var printUrl = '<?= asset('modules/warehouse/print_passport.php') ?>?id=' + productId + '&serial=' + encodeURIComponent(serialNumber);
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
        }
        
        function saveProductSerialNumber(productId, serialNumber) {
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
                    // Обновляем данные текущего продукта после сохранения
                    if (window.currentProduct) {
                        window.currentProduct.serial_number = serialNumber;
                    }
                } else {
                    console.error('Ошибка сохранения серийного номера:', data.error);
                }
            })
            .catch(error => console.error('Ошибка сохранения серийного номера:', error));
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
                if (modal.classList.contains('active')) {
                    closeProductModalDirect();
                }
            }
        });
    </script>
</body>
</html>
