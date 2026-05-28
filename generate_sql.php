<?php
/**
 * Генерация SQL файла из polesie_products.php
 */

$data = require '/workspace/polesie_products.php';

$sql = "-- =====================================================================\n";
$sql .= "-- ПОЛНАЯ НОМЕНКЛАТУРА ПРОДУКЦИИ ОАО «ПОЛЕСЬЕЭЛЕКТРОМАШ»\n";
$sql .= "-- Файл: database_updated.sql\n";
$sql .= "-- Источник: polesie_products.php\n";
$sql .= "-- =====================================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
$sql .= "-- Очистка существующих данных\n";
$sql .= "DELETE FROM `products`;\n";
$sql .= "DELETE FROM `product_categories`;\n\n";

// Категории
$sql .= "-- =====================================================================\n";
$sql .= "-- КАТЕГОРИИ ПРОДУКЦИИ\n";
$sql .= "-- =====================================================================\n";
$sql .= "INSERT INTO `product_categories` (`id`, `name`, `slug`, `parent_id`) VALUES\n";

$categoryMap = [
    'asynchronous_three_phase_general' => 1,
    'energy_efficient' => 2,
    'multispeed' => 3,
    'high_slip' => 4,
    'for_pumps' => 5,
    'for_gearboxes' => 6,
    'for_poultry' => 7,
    'for_railway' => 8,
    'explosion_proof' => 9,
    'single_phase' => 10,
    'pumps' => 11,
    'other_products' => 12,
];

$categories = [];
$categoryId = 1;
foreach ($data['categories'] as $key => $cat) {
    if (isset($categoryMap[$key])) {
        $id = $categoryMap[$key];
        $name = addslashes($cat['name']);
        $slug = str_replace('_', '-', $key);
        $categories[] = "($id, '$name', '$slug', NULL)";
        $categoryId++;
    }
}
$sql .= implode(",\n", $categories) . ";\n\n";

// Продукция
$productInserts = [];
$productId = 1;

foreach ($data['categories'] as $key => $cat) {
    if (!isset($categoryMap[$key])) continue;
    
    $catId = $categoryMap[$key];
    
    if (isset($cat['models'])) {
        foreach ($cat['models'] as $model) {
            $article = $model['model'];
            $name = 'Двигатель ' . $article;
            
            // Создаем спецификации
            $specs = [];
            foreach ($model as $k => $v) {
                if ($k !== 'model') {
                    $specs[$k] = $v;
                }
            }
            $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE);
            
            $price = isset($model['мощность_квт']) ? round($model['мощность_квт'] * 100 + 200, 2) : 300.00;
            $image = 'motor_' . strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $article)) . '.jpg';
            
            $productInserts[] = "('$article', '$name', $catId, 1, '$specsJson', '$image', $price, 'BYN', TRUE)";
            $productId++;
        }
    }
    
    // Подкатегории (для насосов и т.д.)
    if (isset($cat['subcategories'])) {
        foreach ($cat['subcategories'] as $subkey => $subcat) {
            if (isset($subcat['parameters'])) {
                $article = isset($subcat['parameters']['потребляемая_мощность_вт']) ? 'БЦ-0,5-20-У1.1' : 'ГНОМ';
                $name = $subcat['name'];
                $specsJson = json_encode($subcat['parameters'], JSON_UNESCAPED_UNICODE);
                $price = isset($subcat['parameters']['потребляемая_мощность_вт']) ? 180.00 : 250.00;
                $image = $subkey === 'centrifugal_household' ? 'pump_bc05.jpg' : 'pump_gnom.jpg';
                
                $productInserts[] = "('$article', '$name', $catId, 1, '$specsJson', '$image', $price, 'BYN', TRUE)";
                $productId++;
            }
        }
    }
    
    // Прочая продукция
    if (isset($cat['items'])) {
        foreach ($cat['items'] as $item) {
            if (isset($item['models'])) {
                foreach ($item['models'] as $model) {
                    $name = $item['name'] . ' ' . $model;
                    $specs = ['назначение' => 'Для бытовых электроплит'];
                    $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE);
                    $price = 45.00 + (strpos($model, '180') !== false ? 10 : 0) + (strpos($model, '2.0') !== false ? 20 : 0);
                    $image = 'ekch' . preg_replace('/[^0-9]/', '', $model) . '.jpg';
                    
                    $productInserts[] = "('$model', '$name', $catId, 1, '$specsJson', '$image', $price, 'BYN', TRUE)";
                    $productId++;
                }
            }
        }
    }
}

$sql .= "-- =====================================================================\n";
$sql .= "-- ПРОДУКЦИЯ\n";
$sql .= "-- =====================================================================\n";
$sql .= "INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES\n";
$sql .= implode(",\n", $productInserts) . ";\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents('/workspace/polesie/sql/database_updated.sql', $sql);
echo "SQL файл успешно создан!\n";
echo "Строк: " . count(explode("\n", $sql)) . "\n";
echo "Продуктов: " . count($productInserts) . "\n";
echo "Категорий: " . count($categories) . "\n";
