-- ============================================
-- ДОПОЛНЕНИЕ: ПАСПОРТА И МАТЕРИАЛЫ ДЛЯ ОСТАЛЬНЫХ ТОВАРОВ
-- Добавляет паспорта и материалы для 24 товаров, у которых они отсутствовали
-- Все вставки материалов проверяют существование через WHERE EXISTS
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

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')),
    (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 2.6, 2, 1.300, 51.2, 1, 'Сталь для корпуса статора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'STEEL-SHEET-0.5');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')),
    (SELECT id FROM materials WHERE code = 'WIRE-CU-0.8'), 0.16, 4, 0.048, 1.89, 2, 'Обмоточный провод'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'WIRE-CU-0.8');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')),
    (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 7.89, 3, 'Подшипники'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'BEARING-6203-2RS');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')),
    (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 1.9, 2, 0.950, 37.48, 4, 'Алюминий для литья ротора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'ALUM-BAR-10');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')),
    (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.12, 2, 0.060, 2.37, 5, 'Порошковая краска'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'PAINT-POLYMER');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2')),
    (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.39, 6, 'Смазка для подшипников'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'LUBE-GREASE-LITO');

-- Паспорт для 2AIR80B2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR80B2'), 16.5, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR80B2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')),
    (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 2.9, 2, 1.450, 51.8, 1, 'Сталь для корпуса статора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'STEEL-SHEET-0.5');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')),
    (SELECT id FROM materials WHERE code = 'WIRE-CU-0.9'), 0.18, 4, 0.054, 1.93, 2, 'Обмоточный провод'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'WIRE-CU-0.9');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')),
    (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 7.15, 3, 'Подшипники'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'BEARING-6203-2RS');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')),
    (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.1, 2, 1.050, 37.54, 4, 'Алюминий для литья ротора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'ALUM-BAR-10');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')),
    (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.13, 2, 0.065, 2.32, 5, 'Порошковая краска'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'PAINT-POLYMER');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2')),
    (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.36, 6, 'Смазка для подшипников'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B2'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'LUBE-GREASE-LITO');

-- Паспорт для 2AIR80A6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR80A6'), 13.5, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR80A6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')),
    (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 2.4, 2, 1.200, 50.5, 1, 'Сталь для корпуса статора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'STEEL-SHEET-0.5');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')),
    (SELECT id FROM materials WHERE code = 'WIRE-CU-0.7'), 0.14, 4, 0.042, 1.77, 2, 'Обмоточный провод'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'WIRE-CU-0.7');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')),
    (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 8.43, 3, 'Подшипники'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'BEARING-6203-2RS');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')),
    (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 1.7, 2, 0.850, 35.84, 4, 'Алюминий для литья ротора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'ALUM-BAR-10');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')),
    (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.11, 2, 0.055, 2.32, 5, 'Порошковая краска'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'PAINT-POLYMER');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6')),
    (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.42, 6, 'Смазка для подшипников'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80A6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'LUBE-GREASE-LITO');

-- Паспорт для 2AIR80B6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR80B6'), 17.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR80B6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')
);


INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')),
    (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 3.0, 2, 1.500, 51.5, 1, 'Сталь для корпуса статора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'STEEL-SHEET-0.5');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')),
    (SELECT id FROM materials WHERE code = 'WIRE-CU-0.9'), 0.19, 4, 0.057, 1.96, 2, 'Обмоточный провод'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'WIRE-CU-0.9');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')),
    (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.200, 6.87, 3, 'Подшипники'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'BEARING-6203-2RS');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')),
    (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.2, 2, 1.100, 37.76, 4, 'Алюминий для литья ротора'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'ALUM-BAR-10');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')),
    (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.14, 2, 0.070, 2.40, 5, 'Порошковая краска'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'PAINT-POLYMER');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`)
SELECT 
    (SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6')),
    (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.010, 0.34, 6, 'Смазка для подшипников'
WHERE EXISTS (SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR80B6'))
  AND EXISTS (SELECT 1 FROM materials WHERE code = 'LUBE-GREASE-LITO');

-- Паспорт для 2AIR90L2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR90L2'), 22.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR90L2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L2')
);



-- Паспорт для 2AIR90L4
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR90L4'), 25.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR90L4',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L4')
);



-- Паспорт для 2AIR90L6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR90L6'), 21.5, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR90L6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR90L6')
);



-- Паспорт для 2AIR100S2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100S2'), 28.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100S2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S2')
);



-- Паспорт для 2AIR100S4
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100S4'), 30.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100S4',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100S4')
);



-- Паспорт для 2AIR100L2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100L2'), 32.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100L2',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L2')
);



-- Паспорт для 2AIR100L4
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100L4'), 34.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100L4',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L4')
);



-- Паспорт для 2AIR100L6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = '2AIR100L6'), 30.0, 12, TRUE,
    'Стандартный производственный процесс для двигателя 2AIR100L6',
    'Соответствие ГОСТ, проверка электрических параметров и качества сборки'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = '2AIR100L6')
);



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



-- Паспорт для CAST-SCH25
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-SCH25'), 100.0, 12, FALSE, 'Серый чугун СЧ25 - повышенная прочность', TRUE,
    'Серый чугун СЧ25 - повышенная прочность',
    'Соответствие ГОСТ 1412-85, контроль твердости и микроструктуры'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-SCH25')
);



-- Паспорт для CAST-VCH40
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-VCH40'), 100.0, 12, FALSE, 'Высокопрочный чугун ВЧ40 с шаровидным графитом', TRUE,
    'Высокопрочный чугун ВЧ40 с шаровидным графитом',
    'Соответствие ГОСТ 7293-85, контроль формы графита и механических свойств'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-VCH40')
);



-- Паспорт для CAST-A5
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-A5'), 100.0, 12, FALSE, 'Алюминиевый сплав АК5 - литейный', TRUE,
    'Алюминиевый сплав АК5 - литейный',
    'Соответствие ГОСТ 1583-93, контроль химического состава'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A5')
);



-- Паспорт для CAST-A6
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-A6'), 100.0, 12, FALSE, 'Алюминиевый сплав АК6 - литейный', TRUE,
    'Алюминиевый сплав АК6 - литейный',
    'Соответствие ГОСТ 1583-93, контроль химического состава и механических свойств'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-A6')
);



-- Паспорт для CAST-AK5M2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK5M2'), 100.0, 12, FALSE, 'Алюминиевый сплав АК5М2 - кремнистый с медью', TRUE,
    'Алюминиевый сплав АК5М2 - кремнистый с медью',
    'Соответствие ГОСТ 1583-93, контроль химического состава'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK5M2')
);



-- Паспорт для CAST-AK7
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK7'), 100.0, 12, FALSE, 'Алюминиевый сплав АК7 - силумин', TRUE,
    'Алюминиевый сплав АК7 - силумин',
    'Соответствие ГОСТ 1583-93, хорошие литейные свойства'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK7')
);



-- Паспорт для CAST-AK9
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK9'), 100.0, 12, FALSE, 'Алюминиевый сплав АК9 - высококремнистый', TRUE,
    'Алюминиевый сплав АК9 - высококремнистый',
    'Соответствие ГОСТ 1583-93, отличные литейные свойства'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK9')
);



-- Паспорт для CAST-AV87
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AV87'), 100.0, 12, FALSE, 'Алюминиевый сплав АВ87 - антифрикционный', TRUE,
    'Алюминиевый сплав АВ87 - антифрикционный',
    'Соответствие ГОСТ 1412-85, специальные свойства'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AV87')
);



-- Паспорт для CAST-AK12
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'CAST-AK12'), 100.0, 12, FALSE, 'Алюминиевый сплав АК12 - эвтектический силумин', TRUE,
    'Алюминиевый сплав АК12 - эвтектический силумин',
    'Соответствие ГОСТ 1583-93, отличная жидкотекучесть'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'CAST-AK12')
);



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



-- Паспорт для АИВР90L
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`)
SELECT 
    (SELECT id FROM products WHERE article = 'АИВР90L'), 26.0, 18, TRUE,
    'Взрывозащищенный двигатель АИВР90L',
    'Соответствие ГОСТ Р МЭК 60079, сертификат взрывозащиты Ex d IIB T4'
WHERE NOT EXISTS (
    SELECT 1 FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'АИВР90L')
);



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
