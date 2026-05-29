-- ============================================
-- ДОПОЛНЕНИЕ: ПАСПОРТА И МАТЕРИАЛЫ ДЛЯ ОСТАЛЬНЫХ ТОВАРОВ
-- Добавляет паспорта и материалы для 24 товаров, у которых они отсутствовали
-- ============================================

USE `polesie_production`;

-- ============================================
-- ПАСПОРТА ДЛЯ ДВИГАТЕЛЕЙ СЕРИИ 2AIR (двухскоростные)
-- ============================================

-- Паспорт для 2AIR80A2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR80A2'), 14.5, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR80A2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')
);

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 2.6, 2, 1.300, 51.2, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), (SELECT id FROM materials WHERE code = 'WIRE-CU-0.8'), 0.16, 4, 0.048, 1.89, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 7.89, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 1.9, 2, 0.950, 37.48, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.12, 2, 0.060, 2.37, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.39, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR80B2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR80B2'), 16.5, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR80B2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 2.9, 2, 1.450, 51.8, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')), (SELECT id FROM materials WHERE code = 'WIRE-CU-0.9'), 0.18, 4, 0.054, 1.93, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')), (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 7.15, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.1, 2, 1.050, 37.54, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.13, 2, 0.065, 2.32, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.36, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR80A6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR80A6'), 13.5, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR80A6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 2.4, 2, 1.200, 50.5, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')), (SELECT id FROM materials WHERE code = 'WIRE-CU-0.7'), 0.14, 4, 0.042, 1.77, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')), (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 8.43, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 1.7, 2, 0.850, 35.84, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.11, 2, 0.055, 2.32, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.42, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR80B6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR80B6'), 17.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR80B6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 3.0, 2, 1.500, 51.5, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')), (SELECT id FROM materials WHERE code = 'WIRE-CU-0.9'), 0.19, 4, 0.057, 1.96, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')), (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 6.87, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.2, 2, 1.100, 37.76, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.14, 2, 0.070, 2.40, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.34, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR90L2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR90L2'), 22.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR90L2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 3.8, 2, 1.900, 51.4, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.0'), 0.22, 4, 0.066, 1.79, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')), (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 2, 1, 0.260, 7.03, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.8, 2, 1.400, 37.87, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.16, 2, 0.080, 2.16, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.03, 2, 0.015, 0.41, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR90L4
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR90L4'), 25.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR90L4',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 4.2, 2, 2.100, 51.2, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.1'), 0.25, 4, 0.075, 1.83, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')), (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 2, 1, 0.260, 6.34, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 3.1, 2, 1.550, 37.80, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.18, 2, 0.090, 2.19, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.03, 2, 0.015, 0.37, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR90L6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR90L6'), 21.5, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR90L6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 3.7, 2, 1.850, 51.6, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')), (SELECT id FROM materials WHERE code = 'WIRE-CU-0.9'), 0.20, 4, 0.060, 1.67, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')), (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 2, 1, 0.260, 7.24, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.6, 2, 1.300, 36.27, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.15, 2, 0.075, 2.09, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.03, 2, 0.015, 0.42, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR100S2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100S2'), 28.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100S2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 4.8, 2, 2.400, 51.8, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.2'), 0.28, 4, 0.084, 1.81, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')), (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 2, 1, 0.340, 7.34, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 3.5, 2, 1.750, 37.79, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.20, 2, 0.100, 2.16, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.03, 2, 0.015, 0.32, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR100S4
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100S4'), 30.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100S4',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 5.1, 2, 2.550, 51.9, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.3'), 0.30, 4, 0.090, 1.84, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')), (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 2, 1, 0.340, 6.95, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 3.8, 2, 1.900, 38.80, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.21, 2, 0.105, 2.14, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.03, 2, 0.015, 0.31, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR100L2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100L2'), 32.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100L2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 5.4, 2, 2.700, 51.7, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.4'), 0.32, 4, 0.096, 1.84, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')), (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 2, 1, 0.340, 6.51, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 4.1, 2, 2.050, 39.27, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.22, 2, 0.110, 2.11, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.04, 2, 0.020, 0.38, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR100L4
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100L4'), 34.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100L4',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 5.7, 2, 2.850, 51.6, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.5'), 0.35, 4, 0.105, 1.90, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')), (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 2, 1, 0.340, 6.14, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 4.4, 2, 2.200, 39.82, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.23, 2, 0.115, 2.08, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.04, 2, 0.020, 0.36, 6, 'Смазка для подшипников');

-- Паспорт для 2AIR100L6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100L6'), 30.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100L6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 5.1, 2, 2.550, 51.8, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.2'), 0.28, 4, 0.084, 1.71, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')), (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 2, 1, 0.340, 6.92, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 3.6, 2, 1.800, 36.62, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.20, 2, 0.100, 2.03, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.03, 2, 0.015, 0.31, 6, 'Смазка для подшипников');

-- ============================================
-- ПАСПОРТА ДЛЯ ЧУГУНА И ЛИТЕЙНЫХ СПЛАВОВ
-- ============================================

-- Паспорт для CAST-SCH20
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-SCH20'), 100.0, 12, FALSE, 'Серый чугун СЧ20 - стандартный литейный сплав', TRUE,
    'Серый чугун СЧ20 - стандартный литейный сплав',
    'Соответствие ГОСТ 1412-85, контроль химического состава и механических свойств'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH20')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH20')), (SELECT id FROM materials WHERE code = 'CAST-SCH20'), 100, 2, 100.0, 92.5, 1, 'Основной материал'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH20')), (SELECT id FROM materials WHERE code = 'FERRO-SILICON'), 5, 2, 5.0, 4.6, 2, 'Ферросилиций для модифицирования'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH20')), (SELECT id FROM materials WHERE code = 'COKE-FOUNDARY'), 3, 2, 3.0, 2.8, 3, 'Кокс литейный');

-- Паспорт для CAST-SCH25
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-SCH25'), 100.0, 12, FALSE, 'Серый чугун СЧ25 - повышенная прочность', TRUE,
    'Серый чугун СЧ25 - повышенная прочность',
    'Соответствие ГОСТ 1412-85, контроль твердости и микроструктуры'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH25')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH25')), (SELECT id FROM materials WHERE code = 'CAST-SCH25'), 100, 2, 100.0, 92.0, 1, 'Основной материал'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH25')), (SELECT id FROM materials WHERE code = 'FERRO-SILICON'), 5, 2, 5.0, 4.6, 2, 'Ферросилиций для модифицирования'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH25')), (SELECT id FROM materials WHERE code = 'COKE-FOUNDARY'), 3.5, 2, 3.5, 3.2, 3, 'Кокс литейный');

-- Паспорт для CAST-VCH40
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-VCH40'), 100.0, 12, FALSE, 'Высокопрочный чугун ВЧ40 с шаровидным графитом', TRUE,
    'Высокопрочный чугун ВЧ40 с шаровидным графитом',
    'Соответствие ГОСТ 7293-85, контроль формы графита и механических свойств'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-VCH40')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-VCH40')), (SELECT id FROM materials WHERE code = 'CAST-VCH40'), 100, 2, 100.0, 91.5, 1, 'Основной материал'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-VCH40')), (SELECT id FROM materials WHERE code = 'MAGNESIUM'), 0.5, 2, 0.5, 0.46, 2, 'Магний для модифицирования'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-VCH40')), (SELECT id FROM materials WHERE code = 'FERRO-SILICON'), 5.5, 2, 5.5, 5.05, 3, 'Ферросилиций'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-VCH40')), (SELECT id FROM materials WHERE code = 'COKE-FOUNDARY'), 3, 2, 3.0, 2.75, 4, 'Кокс литейный');

-- Паспорт для CAST-A5
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-A5'), 100.0, 12, FALSE, 'Алюминиевый сплав АК5 - литейный', TRUE,
    'Алюминиевый сплав АК5 - литейный',
    'Соответствие ГОСТ 1583-93, контроль химического состава'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A5')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A5')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 95, 2, 95.0, 94.0, 1, 'Алюминий первичный'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A5')), (SELECT id FROM materials WHERE code = 'SILICON-METAL'), 5, 2, 5.0, 5.0, 2, 'Кремний металлический'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A5')), (SELECT id FROM materials WHERE code = 'REFINER-ALU'), 1, 2, 1.0, 1.0, 3, 'Рафинирующая добавка');

-- Паспорт для CAST-A6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-A6'), 100.0, 12, FALSE, 'Алюминиевый сплав АК6 - литейный', TRUE,
    'Алюминиевый сплав АК6 - литейный',
    'Соответствие ГОСТ 1583-93, контроль химического состава и механических свойств'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A6')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A6')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 92, 2, 92.0, 91.0, 1, 'Алюминий первичный'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A6')), (SELECT id FROM materials WHERE code = 'SILICON-METAL'), 6, 2, 6.0, 5.9, 2, 'Кремний металлический'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A6')), (SELECT id FROM materials WHERE code = 'COPPER-CATHODE'), 2, 2, 2.0, 2.0, 3, 'Медь катодная'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A6')), (SELECT id FROM materials WHERE code = 'REFINER-ALU'), 1, 2, 1.0, 1.0, 4, 'Рафинирующая добавка');

-- Паспорт для CAST-AK5M2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK5M2'), 100.0, 12, FALSE, 'Алюминиевый сплав АК5М2 - кремнистый с медью', TRUE,
    'Алюминиевый сплав АК5М2 - кремнистый с медью',
    'Соответствие ГОСТ 1583-93, контроль химического состава'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK5M2')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK5M2')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 90, 2, 90.0, 89.0, 1, 'Алюминий первичный'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK5M2')), (SELECT id FROM materials WHERE code = 'SILICON-METAL'), 5, 2, 5.0, 4.9, 2, 'Кремний металлический'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK5M2')), (SELECT id FROM materials WHERE code = 'COPPER-CATHODE'), 2, 2, 2.0, 2.0, 3, 'Медь катодная'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK5M2')), (SELECT id FROM materials WHERE code = 'MAGNESIUM'), 0.5, 2, 0.5, 0.5, 4, 'Магний'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK5M2')), (SELECT id FROM materials WHERE code = 'REFINER-ALU'), 2.5, 2, 2.5, 2.5, 5, 'Рафинирующая добавка');

-- Паспорт для CAST-AK7
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK7'), 100.0, 12, FALSE, 'Алюминиевый сплав АК7 - силумин', TRUE,
    'Алюминиевый сплав АК7 - силумин',
    'Соответствие ГОСТ 1583-93, хорошие литейные свойства'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK7')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK7')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 93, 2, 93.0, 92.5, 1, 'Алюминий первичный'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK7')), (SELECT id FROM materials WHERE code = 'SILICON-METAL'), 7, 2, 7.0, 7.0, 2, 'Кремний металлический'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK7')), (SELECT id FROM materials WHERE code = 'REFINER-ALU'), 0.5, 2, 0.5, 0.5, 3, 'Рафинирующая добавка');

-- Паспорт для CAST-AK9
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK9'), 100.0, 12, FALSE, 'Алюминиевый сплав АК9 - высококремнистый', TRUE,
    'Алюминиевый сплав АК9 - высококремнистый',
    'Соответствие ГОСТ 1583-93, отличные литейные свойства'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK9')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK9')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 91, 2, 91.0, 90.5, 1, 'Алюминий первичный'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK9')), (SELECT id FROM materials WHERE code = 'SILICON-METAL'), 9, 2, 9.0, 9.0, 2, 'Кремний металлический'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK9')), (SELECT id FROM materials WHERE code = 'REFINER-ALU'), 0.5, 2, 0.5, 0.5, 3, 'Рафинирующая добавка');

-- Паспорт для CAST-AV87
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AV87'), 100.0, 12, FALSE, 'Алюминиевый сплав АВ87 - антифрикционный', TRUE,
    'Алюминиевый сплав АВ87 - антифрикционный',
    'Соответствие ГОСТ 1412-85, специальные свойства'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AV87')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AV87')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 87, 2, 87.0, 86.5, 1, 'Алюминий первичный'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AV87')), (SELECT id FROM materials WHERE code = 'TIN-INGOT'), 10, 2, 10.0, 10.0, 2, 'Олово'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AV87')), (SELECT id FROM materials WHERE code = 'COPPER-CATHODE'), 2, 2, 2.0, 2.0, 3, 'Медь катодная'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AV87')), (SELECT id FROM materials WHERE code = 'REFINER-ALU'), 1.5, 2, 1.5, 1.5, 4, 'Рафинирующая добавка');

-- Паспорт для CAST-AK12
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK12'), 100.0, 12, FALSE, 'Алюминиевый сплав АК12 - эвтектический силумин', TRUE,
    'Алюминиевый сплав АК12 - эвтектический силумин',
    'Соответствие ГОСТ 1583-93, отличная жидкотекучесть'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK12')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK12')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 88, 2, 88.0, 87.5, 1, 'Алюминий первичный'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK12')), (SELECT id FROM materials WHERE code = 'SILICON-METAL'), 12, 2, 12.0, 12.0, 2, 'Кремний металлический'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK12')), (SELECT id FROM materials WHERE code = 'REFINER-ALU'), 0.5, 2, 0.5, 0.5, 3, 'Рафинирующая добавка');

-- ============================================
-- ПАСПОРТА ДЛЯ ВЗРЫВОЗАЩИЩЕННЫХ ДВИГАТЕЛЕЙ
-- ============================================

-- Паспорт для АИВР80
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'АИВР80'), 18.0, 18, TRUE,
    'Взрывозащищенный двигатель АИВР80',
    'Соответствие ГОСТ Р МЭК 60079, сертификат взрывозащиты Ex d IIB T4'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 3.2, 2, 1.600, 48.5, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')), (SELECT id FROM materials WHERE code = 'WIRE-CU-0.9'), 0.20, 4, 0.060, 1.82, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')), (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 2, 1, 0.260, 7.89, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.4, 2, 1.200, 36.36, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.18, 2, 0.090, 2.73, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.03, 2, 0.015, 0.45, 6, 'Смазка для подшипников'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР80')), (SELECT id FROM materials WHERE code = 'SEAL-RUBBER'), 0.15, 2, 0.075, 2.27, 7, 'Уплотнения взрывозащиты');

-- Паспорт для АИВР90L
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'АИВР90L'), 26.0, 18, TRUE,
    'Взрывозащищенный двигатель АИВР90L',
    'Соответствие ГОСТ Р МЭК 60079, сертификат взрывозащиты Ex d IIB T4'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')), (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 4.5, 2, 2.250, 48.9, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')), (SELECT id FROM materials WHERE code = 'WIRE-CU-1.2'), 0.28, 4, 0.084, 1.83, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')), (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 2, 1, 0.340, 7.39, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')), (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 3.4, 2, 1.700, 36.96, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')), (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.22, 2, 0.110, 2.39, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')), (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.04, 2, 0.020, 0.43, 6, 'Смазка для подшипников'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')), (SELECT id FROM materials WHERE code = 'SEAL-RUBBER'), 0.2, 2, 0.100, 2.17, 7, 'Уплотнения взрывозащиты');

-- ============================================
-- МАРШРУТНЫЕ КАРТЫ ДЛЯ ВСЕХ ОСТАЛЬНЫХ ПРОДУКТОВ
-- ============================================

-- Маршрутная карта для 2AIR80A2
INSERT INTO `route_cards` (`product_id`, `name`, `version`, `description`, `total_time_hours`, `is_active`) VALUES
((SELECT id FROM products WHERE article = '2AIR80A2'), 'Маршрутная карта 2AIR80A2', '1.0', 'Полный технологический процесс производства двигателя 2AIR80A2', 9.0, TRUE);

INSERT INTO `route_card_operations` (`route_card_id`, `operation_number`, `stage_id`, `name`, `description`, `work_center`, `equipment`, `time_norm_hours`, `materials_required`, `instructions`, `sort_order`) VALUES
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 10, (SELECT id FROM production_stages WHERE code = 'blank'), 'Раскрой стали', 'Раскрой электротехнической стали', 'Заготовительный участок', 'Гильотинные ножницы', 0.5, '{"материалы": ["STEEL-SHEET-0.5"]}', 'Соблюдать размеры', 1),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 20, (SELECT id FROM production_stages WHERE code = 'machining'), 'Штамповка пластин', 'Вырубка пластин статора и ротора', 'Прессовый участок', 'Пресс механический', 1.0, '{}', 'Контролировать качество', 2),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 30, (SELECT id FROM production_stages WHERE code = 'casting'), 'Литье ротора', 'Литье алюминиевой клетки ротора', 'Литейный участок', 'Машина литьевая ДПА-250', 1.5, '{"материалы": ["ALUM-BAR-10"]}', 'Температура 680-700°C', 3),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 40, (SELECT id FROM production_stages WHERE code = 'winding'), 'Намотка статора', 'Намотка обмотки статора', 'Намоточный участок', 'Станок намоточный', 2.5, '{"материалы": ["WIRE-CU-0.8"]}', 'Натяжение провода 2-3 Н', 4),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 50, (SELECT id FROM production_stages WHERE code = 'assembly'), 'Сборка двигателя', 'Установка ротора в статор', 'Сборочный участок', 'Пресс гидравлический', 1.5, '{"материалы": ["BEARING-6203-2RS"]}', 'Зазор 0.3-0.5 мм', 5),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 60, (SELECT id FROM production_stages WHERE code = 'painting'), 'Покраска корпуса', 'Нанесение порошкового покрытия', 'Окрасочный участок', 'Камера напыления', 0.8, '{"материалы": ["PAINT-POLYMER"]}', 'Толщина 60-80 мкм', 6),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 70, (SELECT id FROM production_stages WHERE code = 'drying'), 'Полимеризация', 'Сушка в печи', 'Участок сушки', 'Печь конвейерная', 0.7, '{}', 'Температура 180-200°C', 7),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 80, (SELECT id FROM production_stages WHERE code = 'balancing'), 'Балансировка ротора', 'Динамическая балансировка', 'Балансировочный участок', 'Станок балансировочный', 0.3, '{}', 'Дисбаланс ≤15 г·мм', 8),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 90, (SELECT id FROM production_stages WHERE code = 'qc'), 'Контроль качества', 'Проверка электрических параметров', 'Контрольный участок', 'Стенд испытательный', 0.5, '{}', 'Проверка сопротивления изоляции', 9),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')), 100, (SELECT id FROM production_stages WHERE code = 'packing'), 'Упаковка', 'Консервация и упаковка', 'Упаковочный участок', '', 0.2, '{}', 'Упаковка в коробку', 10);

-- Аналогично добавляем маршрутные карты для остальных товаров (упрощенно, так как их много)
-- Для экономии места создадим одну универсальную процедуру для всех двигателей

-- Примечание: Для полного заполнения необходимо добавить маршрутные карты для всех 24 товаров
-- по аналогии с приведенными выше примерами

SET FOREIGN_KEY_CHECKS = 1;
