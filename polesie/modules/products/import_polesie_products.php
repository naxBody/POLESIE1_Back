<?php
/**
 * 🏭 Импорт полной номенклатуры продукции ОАО «Полесьеэлектромаш»
 * Скрипт загружает данные из polesie_products.php в базу данных
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Проверка авторизации
session_start();
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

$user = getCurrentUser();
$pdo = getDbConnection();

// Загружаем данные о продукции
$productsData = require __DIR__ . '/../../polesie_products.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->beginTransaction();
    
    $importedCount = 0;
    $skippedCount = 0;
    $errors = [];
    
    // Категории продукции (соответствие ключей из polesie_products.php и ID в БД)
    $categoryMap = [
        'asynchronous_three_phase_general' => 2,  // MOTOR_AIR
        'energy_efficient' => 3,                   // MOTOR_2AIR
        'multispeed' => 4,                         // MOTOR_MULTI
        'high_slip' => 6,                          // MOTOR_AIRS
        'for_pumps' => 7,                          // MOTOR_PUMP
        'for_gearboxes' => 8,                      // MOTOR_GEAR
        'for_poultry' => 9,                        // MOTOR_POULTRY
        'for_railway' => 10,                       // MOTOR_RAILWAY
        'explosion_proof' => 11,                   // MOTOR_EX
        'single_phase' => 12,                      // MOTOR_SINGLE
        'centrifugal_household' => 14,             // PUMP_CENTRIFUGAL
        'submersible_dirty' => 15,                 // PUMP_SUBMERSIBLE
    ];
    
    // Получаем base_unit_id для штук
    $stmt = $pdo->prepare("SELECT id FROM base_units WHERE code = 'pcs'");
    $stmt->execute();
    $baseUnitId = $stmt->fetchColumn() ?: 1;
    
    // Обработка каждой категории
    foreach ($productsData['categories'] as $categoryKey => $categoryData) {
        // Пропускаем категории без моделей
        if (!isset($categoryData['models']) && !isset($categoryData['subcategories'])) {
            continue;
        }
        
        // Если есть подкатегории (например, special_purpose)
        if (isset($categoryData['subcategories'])) {
            foreach ($categoryData['subcategories'] as $subcatKey => $subcatData) {
                if (!isset($subcatData['models'])) {
                    continue;
                }
                
                $categoryId = $categoryMap[$subcatKey] ?? null;
                if (!$categoryId) {
                    $errors[] = "Не найдена категория для {$subcatKey}";
                    continue;
                }
                
                foreach ($subcatData['models'] as $model) {
                    $result = importProduct($pdo, $model, $categoryId, $baseUnitId);
                    if ($result['imported']) {
                        $importedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
            continue;
        }
        
        // Обычная категория с моделями
        if (isset($categoryData['models'])) {
            $categoryId = $categoryMap[$categoryKey] ?? null;
            if (!$categoryId) {
                $errors[] = "Не найдена категория для {$categoryKey}";
                continue;
            }
            
            foreach ($categoryData['models'] as $model) {
                $result = importProduct($pdo, $model, $categoryId, $baseUnitId);
                if ($result['imported']) {
                    $importedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }
    }
    
    // Обработка прочей продукции
    if (isset($productsData['categories']['other_products']['items'])) {
        $categoryId = 16; // OTHER
        foreach ($productsData['categories']['other_products']['items'] as $item) {
            $article = md5($item['name']) . '-OTHER';
            
            // Проверяем существование
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE article = ?");
            $checkStmt->execute([$article]);
            if ($checkStmt->fetch()) {
                $skippedCount++;
                continue;
            }
            
            $specs = [
                'наименование' => $item['name'],
                'описание' => $item['description'] ?? '',
            ];
            
            if (isset($item['models'])) {
                $specs['модели'] = $item['models'];
            }
            if (isset($item['materials'])) {
                $specs['материалы'] = $item['materials'];
            }
            
            $insertStmt = $pdo->prepare("
                INSERT INTO products (article, name, category_id, base_unit_id, specifications, image, base_price, currency, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            $insertStmt->execute([
                $article,
                $item['name'],
                $categoryId,
                $baseUnitId,
                json_encode($specs, JSON_UNESCAPED_UNICODE),
                'other_' . substr(md5($item['name']), 0, 8) . '.jpg',
                100.00,
                'BYN'
            ]);
            
            $importedCount++;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Импорт завершен успешно',
        'imported' => $importedCount,
        'skipped' => $skippedCount,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка импорта: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Функция импорта одной модели продукта
 */
function importProduct($pdo, $model, $categoryId, $baseUnitId) {
    // Определяем артикул
    $article = $model['model'] ?? null;
    if (!$article) {
        return ['imported' => false, 'reason' => 'Нет модели'];
    }
    
    // Проверяем существование
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE article = ?");
    $checkStmt->execute([$article]);
    if ($checkStmt->fetch()) {
        return ['imported' => false, 'reason' => 'Уже существует'];
    }
    
    // Формируем спецификации
    $specs = [];
    foreach ($model as $key => $value) {
        if ($key !== 'model') {
            $specs[$key] = $value;
        }
    }
    
    // Добавляем общие параметры
    $specs['напряжение_в'] = $specs['напряжение_в'] ?? 380;
    $specs['степень_защиты'] = $specs['степень_защиты'] ?? 'IP54';
    $specs['класс_изоляции'] = $specs['класс_изоляции'] ?? 'F';
    $specs['климатическое_исполнение'] = $specs['климатическое_исполнение'] ?? 'У2';
    
    // Формируем имя
    $name = 'Двигатель ' . $article;
    if (strpos($article, '2AIR') === 0) {
        $name = 'Двигатель энергоэффективный ' . $article;
    } elseif (strpos($article, '/') !== false) {
        $name = 'Двигатель многоскоростной ' . str_replace('/', '-', $article);
    } elseif (strpos($article, 'АИРС') === 0) {
        $name = 'Двигатель специальный ' . $article;
    } elseif (strpos($article, 'Ж') !== false) {
        $name = 'Двигатель для насоса ' . $article;
    } elseif (strpos($article, 'РЗ') !== false) {
        $name = 'Двигатель для редуктора ' . $article;
    } elseif (strpos($article, 'АИРП') === 0) {
        $name = 'Двигатель для птичника ' . $article;
    } elseif (strpos($article, 'АИРЧ') === 0) {
        $name = 'Двигатель железнодорожный ' . $article;
    } elseif (strpos($article, 'АИВР') === 0) {
        $name = 'Двигатель взрывозащищенный ' . $article;
    } elseif (strpos($article, 'АИРЕ') === 0) {
        $name = 'Двигатель однофазный ' . $article;
    }
    
    // Рассчитываем базовую цену (примерно)
    $power = $specs['мощность_квт'] ?? 0;
    if (is_numeric($power)) {
        $basePrice = $power * 150; // Примерная цена за кВт
        if (strpos($article, '2AIR') === 0) {
            $basePrice *= 1.3; // Энергоэффективные дороже
        }
        if (strpos($article, 'АИВР') === 0) {
            $basePrice *= 3; // Взрывозащищенные намного дороже
        }
    } else {
        $basePrice = 500; // Default price
    }
    
    $insertStmt = $pdo->prepare("
        INSERT INTO products (article, name, category_id, base_unit_id, specifications, image, base_price, currency, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)
    ");
    
    $insertStmt->execute([
        $article,
        $name,
        $categoryId,
        $baseUnitId,
        json_encode($specs, JSON_UNESCAPED_UNICODE),
        'motor_' . preg_replace('/[^A-Za-z0-9]/', '_', $article) . '.jpg',
        round($basePrice, 2),
        'BYN'
    ]);
    
    return ['imported' => true];
}
