-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ПАСПОРТА ПРОДУКТОВ
-- Импорт из passports.json
-- Всего продуктов: 72
-- ============================================

USE `polesie_production`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Временная таблица для маппинга материалов по code
DROP TABLE IF EXISTS `temp_material_map`;
CREATE TEMPORARY TABLE `temp_material_map` (
  `material_code` VARCHAR(50) NOT NULL,
  `material_id` INT
) ENGINE=Memory;

-- Заполняем временную таблицу существующими материалами
INSERT INTO `temp_material_map` (`material_code`, `material_id`)
SELECT `code`, `id` FROM `materials`;

-- ============================================
-- ДОБАВЛЕНИЕ НОВЫХ МАТЕРИАЛОВ ИЗ ПАСПОРТОВ
-- ============================================

-- Добавляем категорию 'Комплектующие для продукции' если нет
INSERT INTO `material_categories` (`name`, `code`, `description`)
SELECT 'Комплектующие для продукции', 'PRODUCT_COMPONENTS', 'Материалы из паспортов продуктов'
WHERE NOT EXISTS (SELECT 1 FROM `material_categories` WHERE `code` = 'PRODUCT_COMPONENTS');

-- Получаем ID единиц измерения
SET @unit_pcs = (SELECT id FROM base_units WHERE code = 'pcs' LIMIT 1);
SET @unit_kg = (SELECT id FROM base_units WHERE code = 'kg' LIMIT 1);
SET @unit_l = (SELECT id FROM base_units WHERE code = 'l' LIMIT 1);
SET @mat_cat_components = (SELECT id FROM material_categories WHERE code = 'PRODUCT_COMPONENTS' LIMIT 1);

-- Вставка новых материалов из паспортов
INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`) VALUES
('CAST-SC-15', 'Чугун серый СЧ15 для корпуса', 'Чугун серый СЧ15 для корпуса', @mat_cat_components, @unit_kg, '{"source": "passport"}'),
('WIRE-CU-PETV-2.0', 'Провод медный обмоточный ПЭТВ', 'Провод медный обмоточный ПЭТВ', @mat_cat_components, @unit_pcs, '{"source": "passport"}'),
('STEEL-ELECTR-2212', 'Сталь электротехническая 2212', 'Сталь электротехническая 2212', @mat_cat_components, @unit_kg, '{"source": "passport"}'),
('BRG-6200-series', 'Подшипники качения 6200 серии', 'Подшипники качения 6200 серии', @mat_cat_components, @unit_pcs, '{"source": "passport"}'),
('FASTENER-MIX', 'Крепеж (болты, гайки, шайбы)', 'Крепеж (болты, гайки, шайбы)', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('VAR-PES-55', 'Лак электроизоляционный ПЭС-55', 'Лак электроизоляционный ПЭС-55', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('INS-COMBO', 'Изоляционные материалы (комбинированные)', 'Изоляционные материалы (комбинированные)', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('ST-BAR-45-STD', 'Сталь конструкционная 45 для вала', 'Сталь конструкционная 45 для вала', @mat_cat_components, @unit_kg, '{"source": "passport"}'),
('MAT-HOUSE-PUMP', 'Корпус насоса композитный', 'Корпус насоса композитный', @mat_cat_components, @unit_pcs, '{"source": "passport"}'),
('CAST-SC-20', 'Чугун серый СЧ20 для корпуса насоса', 'Чугун серый СЧ20 для корпуса насоса', @mat_cat_components, @unit_kg, '{"source": "passport"}'),
('BRONZE-BRZH', 'Бронза для рабочего колеса', 'Бронза для рабочего колеса', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('SEAL-MECH-K', 'Уплотнение торцевое', 'Уплотнение торцевое', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('STAGE-TSNL', 'Ступени насоса', 'Ступени насоса', @mat_cat_components, @unit_pcs, '{"source": "passport"}'),
('TEN-INDUSTRIAL', 'ТЭН промышленный', 'ТЭН промышленный', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('PANEL-CONTROL', 'Шкаф управления', 'Шкаф управления', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('ST-SHEET-3', 'Лист стальной 3мм', 'Лист стальной 3мм', @mat_cat_components, @unit_kg, '{"source": "passport"}'),
('IRON-PIG', 'Чугун передельный', 'Чугун передельный', @mat_cat_components, @unit_kg, '{"source": "passport"}'),
('FERRO-SI-MN', 'Ферросплавы (Si, Mn)', 'Ферросплавы (Si, Mn)', @mat_cat_components, @unit_l, '{"source": "passport"}'),
('SAND-MOLD', 'Песок формовочный', 'Песок формовочный', @mat_cat_components, @unit_pcs, '{"source": "passport"}'),
('MAT-BASE-GEN', 'Материал базовый', 'Материал базовый', @mat_cat_components, @unit_l, '{"source": "passport"}');

-- Обновляем временную таблицу после добавления новых материалов
INSERT INTO `temp_material_map` (`material_code`, `material_id`)
SELECT `code`, `id` FROM `materials` WHERE `code` NOT IN (SELECT `material_code` FROM `temp_material_map`);

-- ============================================
-- ДОБАВЛЕНИЕ ПАСПОРТОВ ПРОДУКТОВ
-- ============================================

-- Временная таблица для маппинга SKU -> product_id
DROP TABLE IF EXISTS `temp_product_map`;
CREATE TEMPORARY TABLE `temp_product_map` (
  `sku` VARCHAR(50) NOT NULL,
  `product_id` INT
) ENGINE=Memory;

-- Заполняем временную таблицу существующими продуктами
INSERT INTO `temp_product_map` (`sku`, `product_id`)
SELECT `article`, `id` FROM `products`;

-- Добавляем категории продуктов если нет
INSERT INTO `product_categories` (`name`, `code`, `description`)
SELECT 'Электродвигатели асинхронные трехфазные', 'MOTORS_3PHASE', 'Трехфазные асинхронные двигатели'
WHERE NOT EXISTS (SELECT 1 FROM `product_categories` WHERE `code` = 'MOTORS_3PHASE');

-- Вставка новых продуктов из паспортов
INSERT INTO `products` (`article`, `name`, `code_gost`, `category_id`, `base_unit_id`, `specifications`, `is_active`) VALUES
('AIR-071-2', 'Электродвигатель асинхронный трехфазный АИР71, 0.37-0.75 кВт, 3000 об/мин', 'АИР71', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-080-2', 'Электродвигатель асинхронный трехфазный АИР80, 0.75-2.2 кВт, 3000 об/мин', 'АИР80', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-090-2', 'Электродвигатель асинхронный трехфазный АИР90, 1.5-3.0 кВт, 3000 об/мин', 'АИР90', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-100-2', 'Электродвигатель асинхронный трехфазный АИР100, 3.0-5.5 кВт, 3000 об/мин', 'АИР100', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-112-2', 'Электродвигатель асинхронный трехфазный АИР112, 4.0-7.5 кВт, 3000 об/мин', 'АИР112', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-080A2-1.5', 'Энергоэффективный электродвигатель 2АИР80А2, 1.5 кВт, 3000 об/мин, IE2', '2АИР80А2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-080A4-1.1', 'Энергоэффективный электродвигатель 2АИР80А4, 1.1 кВт, 1500 об/мин, IE2', '2АИР80А4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-080A6-0.75', 'Энергоэффективный электродвигатель 2АИР80А6, 0.75 кВт, 1000 об/мин, IE2', '2АИР80А6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-080B2-2.2', 'Энергоэффективный электродвигатель 2АИР80В2, 2.2 кВт, 3000 об/мин, IE2', '2АИР80В2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-080B4-1.5', 'Энергоэффективный электродвигатель 2АИР80В4, 1.5 кВт, 1500 об/мин, IE2', '2АИР80В4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-080B6-1.1', 'Энергоэффективный электродвигатель 2АИР80В6, 1.1 кВт, 1000 об/мин, IE2', '2АИР80В6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-090L2-3.0', 'Энергоэффективный электродвигатель 2АИР90L2, 3.0 кВт, 3000 об/мин, IE2', '2АИР90L2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-090L4-2.2', 'Энергоэффективный электродвигатель 2АИР90L4, 2.2 кВт, 1500 об/мин, IE2', '2АИР90L4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-090L6-1.5', 'Энергоэффективный электродвигатель 2АИР90L6, 1.5 кВт, 1000 об/мин, IE2', '2АИР90L6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-100L2-5.5', 'Энергоэффективный электродвигатель 2АИР100L2, 5.5 кВт, 3000 об/мин, IE2', '2АИР100L2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-100L4-4.0', 'Энергоэффективный электродвигатель 2АИР100L4, 4.0 кВт, 1500 об/мин, IE2', '2АИР100L4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('2AIR-100L6-2.2', 'Энергоэффективный электродвигатель 2АИР100L6, 2.2 кВт, 1000 об/мин, IE2', '2АИР100L6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRS-080', 'Электродвигатель с повышенным скольжением АИРС80', 'АИРС80', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRS-090', 'Электродвигатель с повышенным скольжением АИРС90', 'АИРС90', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRS-100', 'Электродвигатель с повышенным скольжением АИРС100', 'АИРС100', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-PUMP-080', 'Электродвигатель для привода моноблочных насосов АИР80', 'АИР80 (насосы)', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-PUMP-090', 'Электродвигатель для привода моноблочных насосов АИР90', 'АИР90 (насосы)', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-GEAR-090', 'Электродвигатель для привода редукторов АИР90', 'АИР90 (редукторы)', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-MULTI-080', 'Электродвигатель многоскоростной АИР80', 'АИР80 (многоскоростной)', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-MULTI-090', 'Электродвигатель многоскоростной АИР90', 'АИР90 (многоскоростной)', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIR-MULTI-100', 'Электродвигатель многоскоростной АИР100', 'АИР100 (многоскоростной)', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIVR-080B2-2.2', 'Взрывозащищенный электродвигатель АИВР80В2, 2.2 кВт, 3000 об/мин, чугунный корпус', 'АИВР80В2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIVR-080B4-1.5', 'Взрывозащищенный электродвигатель АИВР80В4, 1.5 кВт, 1500 об/мин, чугунный корпус', 'АИВР80В4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIVR-080B6-1.1', 'Взрывозащищенный электродвигатель АИВР80В6, 1.1 кВт, 1000 об/мин, чугунный корпус', 'АИВР80В6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIVR-090L2-3.0', 'Взрывозащищенный электродвигатель АИВР90L2, 3.0 кВт, 3000 об/мин, чугунный корпус', 'АИВР90L2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIVR-090L4-2.2', 'Взрывозащищенный электродвигатель АИВР90L4, 2.2 кВт, 1500 об/мин, чугунный корпус', 'АИВР90L4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIVR-090L6-1.5', 'Взрывозащищенный электродвигатель АИВР90L6, 1.5 кВт, 1000 об/мин, чугунный корпус', 'АИВР90L6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRP-080C6-0.75', 'Электродвигатель для привода осевых вентиляторов АИРП80С6, 0.75 кВт, 1000 об/мин', 'АИРП80С6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRP-080A6-0.37', 'Электродвигатель для привода осевых вентиляторов АИРП80А6, 0.37 кВт, 1000 об/мин', 'АИРП80А6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRC-080B4-0.55', 'Электродвигатель для стрелочных электроприводов АИРЧ80В4, 0.55 кВт, 1500 об/мин', 'АИРЧ80В4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRC-080B6-0.3', 'Электродвигатель для стрелочных электроприводов АИРЧ80В6, 0.3 кВт, 1000 об/мин', 'АИРЧ80В6', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные трехфазные", "category_code": "MOTORS_3PHASE"}', TRUE),
('AIRE-071C2-1.1', 'Электродвигатель однофазный АИРЕ71С2, 1.1 кВт, 3000 об/мин, 220В', 'АИРЕ71С2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-080A2-1.1', 'Электродвигатель однофазный АИРЕ80А2, 1.1 кВт, 3000 об/мин, 220В', 'АИРЕ80А2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-080A4-0.75', 'Электродвигатель однофазный АИРЕ80А4, 0.75 кВт, 1500 об/мин, 220В', 'АИРЕ80А4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-080B2-1.5', 'Электродвигатель однофазный АИРЕ80В2, 1.5 кВт, 3000 об/мин, 220В', 'АИРЕ80В2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-080B4-1.1', 'Электродвигатель однофазный АИРЕ80В4, 1.1 кВт, 1500 об/мин, 220В', 'АИРЕ80В4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-080C2-2.0', 'Электродвигатель однофазный АИРЕ80С2, 2.0 кВт, 3000 об/мин, 220В', 'АИРЕ80С2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-080C4-1.5', 'Электродвигатель однофазный АИРЕ80С4, 1.5 кВт, 1500 об/мин, 220В', 'АИРЕ80С4', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-080D2-2.2', 'Электродвигатель однофазный АМРЕ80D2, 2.2 кВт, 3000 об/мин, 220В', 'АМРЕ80D2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AIRE-090L2-2.2', 'Электродвигатель однофазный АИРЕ90L2, 2.2 кВт, 3000 об/мин, 220В', 'АИРЕ90L2', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AISE-080', 'Электродвигатель однофазный промышленный АИСЕ80', 'АИСЕ80', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AISE-090', 'Электродвигатель однофазный промышленный АИСЕ90', 'АИСЕ90', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('AISE-100', 'Электродвигатель однофазный промышленный АИСЕ100', 'АИСЕ100', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электродвигатели асинхронные однофазные", "category_code": "MOTORS_1PHASE"}', TRUE),
('GNOM-10-10', 'Электронасос центробежный погружной ГНОМ 10x10 для загрязненных вод', 'ГНОМ 10x10', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электронасосы", "category_code": "PUMPS"}', TRUE),
('GNOM-25-20', 'Электронасос центробежный погружной ГНОМ 25x20 для загрязненных вод', 'ГНОМ 25x20', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электронасосы", "category_code": "PUMPS"}', TRUE),
('GNOM-50-50', 'Электронасос центробежный погружной ГНОМ 50x50 для загрязненных вод', 'ГНОМ 50x50', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электронасосы", "category_code": "PUMPS"}', TRUE),
('GNOM-100-25', 'Электронасос центробежный погружной ГНОМ 100x25 для загрязненных вод', 'ГНОМ 100x25', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электронасосы", "category_code": "PUMPS"}', TRUE),
('PUMP-K', 'Насосы консольные типа К, КМ', 'К, КМ', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электронасосы", "category_code": "PUMPS"}', TRUE),
('PUMP-TSNL', 'Насосы консольные линейные типа ЦНЛ', 'ЦНЛ', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электронасосы", "category_code": "PUMPS"}', TRUE),
('PUMP-HOUSEHOLD', 'Насосы центробежные бытовые', 'Насосы бытовые', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электронасосы", "category_code": "PUMPS"}', TRUE),
('EKCH-145-1.0', 'Электроконфорка чугунная ЭКЧ-145, 1.0 кВт, 220В, d145мм', 'ЭКЧ-145-1,0/220', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электроконфорки чугунные", "category_code": "ELECTRIC_HOB"}', TRUE),
('EKCH-180-1.2', 'Электроконфорка чугунная ЭКЧ-180, 1.2 кВт, 220В, d180мм', 'ЭКЧ-180-1,2/220', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электроконфорки чугунные", "category_code": "ELECTRIC_HOB"}', TRUE),
('EKCH-180-1.5', 'Электроконфорка чугунная ЭКЧ-180, 1.5 кВт, 220В, d180мм', 'ЭКЧ-180-1,5/220', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электроконфорки чугунные", "category_code": "ELECTRIC_HOB"}', TRUE),
('EKCH-220-2.0', 'Электроконфорка чугунная ЭКЧ-220, 2.0 кВт, 220В, d220мм', 'ЭКЧ-220-2/220', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электроконфорки чугунные", "category_code": "ELECTRIC_HOB"}', TRUE),
('EKCHE-180-1.2', 'Электроконфорка чугунная экспресс ЭКЧЭ-180, 1.2 кВт, 220В, d180мм', 'ЭКЧЭ-180-1,2/220', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электроконфорки чугунные", "category_code": "ELECTRIC_HOB"}', TRUE),
('EOST-14163-88', 'Электроконфорка чугунная по стандарту ЕОСТ14163-88', 'ЕОСТ14163-88', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Электроконфорки чугунные", "category_code": "ELECTRIC_HOB"}', TRUE),
('GRATE-BOILER', 'Колосниковые решетки различных размеров для отопительных котлов', 'Колосниковые решетки', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Чугунное литье", "category_code": "CAST_IRON"}', TRUE),
('GRATE-BAR', 'Колосниковые балки чугунные', 'Колосниковые балки', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Чугунное литье", "category_code": "CAST_IRON"}', TRUE),
('CAST-BBQ', 'Мангалы чугунные', 'Мангалы', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Чугунное литье", "category_code": "CAST_IRON"}', TRUE),
('CAST-MOLD', 'Изложницы чугунные', 'Изложницы', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Чугунное литье", "category_code": "CAST_IRON"}', TRUE),
('CAST-CRUCIBLE', 'Тигли чугунные', 'Тигли', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Чугунное литье", "category_code": "CAST_IRON"}', TRUE),
('CAST-HS-CUSTOM', 'Отливки из высокопрочного чугуна по чертежам заказчика', 'ВЧ35, ВЧ40, ВЧ50', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Чугунное литье", "category_code": "CAST_IRON"}', TRUE),
('IRON-PIG', 'Чугун чушковой для переплавки', 'Чугун чушковой', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Чугунное литье", "category_code": "CAST_IRON"}', TRUE),
('AL-CAST-CUSTOM', 'Отливки готовых изделий и полуфабрикатов из алюминия и сплавов на его основе', 'АК7, АК9, АК12, АВ-87', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Алюминиевое литье", "category_code": "CAST_ALUMINUM"}', TRUE),
('AL-PIG-AV87', 'Алюминий чушковой марки АВ-87', 'Алюминий АВ-87', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Алюминиевое литье", "category_code": "CAST_ALUMINUM"}', TRUE),
('CUSTOM-CAST-IRON', 'Изготовление отливок из чугуна по чертежам, эскизам, моделям заказчика', 'Чугунное литье на заказ', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Отливки по чертежам заказчика", "category_code": "CUSTOM_CASTING"}', TRUE),
('CUSTOM-CAST-ALUMINUM', 'Изготовление отливок из алюминия по чертежам, эскизам, моделям заказчика', 'Алюминиевое литье на заказ', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{"source": "passport_json", "category": "Отливки по чертежам заказчика", "category_code": "CUSTOM_CASTING"}', TRUE);

-- Обновляем временную таблицу после добавления новых продуктов
INSERT INTO `temp_product_map` (`sku`, `product_id`)
SELECT `article`, `id` FROM `products` WHERE `article` NOT IN (SELECT `sku` FROM `temp_product_map`);

-- ============================================
-- ЗАПИСИ В product_passports
-- ============================================

-- Удаляем старые паспорта если есть (для чистоты)
DELETE FROM `product_passports`;

-- Вставка паспортов продуктов
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`) VALUES
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-071-2'), 4.14, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-080-2'), 5.93, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-090-2'), 7.69, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-100-2'), 11.98, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-112-2'), 15.85, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-080A2-1.5'), 5.98, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-080A4-1.1'), 5.33, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-080A6-0.75'), 4.8, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-080B2-2.2'), 7.17, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-080B4-1.5'), 5.98, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-080B6-1.1'), 5.33, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-090L2-3.0'), 9.06, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-090L4-2.2'), 7.61, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-090L6-1.5'), 6.42, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-100L2-5.5'), 14.73, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-100L4-4.0'), 11.46, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '2AIR-100L6-2.2'), 8.05, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRS-080'), 5.18, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRS-090'), 5.62, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRS-100'), 6.06, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-PUMP-080'), 5.18, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-PUMP-090'), 5.62, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-GEAR-090'), 5.62, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-MULTI-080'), 5.18, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-MULTI-090'), 5.62, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIR-MULTI-100'), 6.06, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIVR-080B2-2.2'), 7.17, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIVR-080B4-1.5'), 5.98, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIVR-080B6-1.1'), 5.33, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIVR-090L2-3.0'), 9.06, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIVR-090L4-2.2'), 7.61, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIVR-090L6-1.5'), 6.42, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRP-080C6-0.75'), 4.8, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRP-080A6-0.37'), 4.24, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRC-080B4-0.55'), 4.51, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRC-080B6-0.3'), 4.15, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-071C2-1.1'), 4.95, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-080A2-1.1'), 5.33, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-080A4-0.75'), 4.8, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-080B2-1.5'), 5.98, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-080B4-1.1'), 5.33, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-080C2-2.0'), 6.82, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-080C4-1.5'), 5.98, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-080D2-2.2'), 7.17, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AIRE-090L2-2.2'), 7.61, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AISE-080'), 5.18, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AISE-090'), 5.62, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AISE-100'), 6.06, 24, TRUE, '["Контроль балансировки ротора", "Пропитка обмоток лаком с сушкой", "Проверка сопротивления изоляции"]', '["ГОСТ Р 51689-2000", "Класс нагревостойкости изоляции F (155°C)", "Степень защиты IP54/IP55"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'GNOM-10-10'), 2.0, 12, TRUE, '["Гидравлические испытания", "Проверка вибрации", "Контроль герметичности"]', '["ГОСТ 10168-2014", "Рабочая температура до 85°C"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'GNOM-25-20'), 2.0, 12, TRUE, '["Гидравлические испытания", "Проверка вибрации", "Контроль герметичности"]', '["ГОСТ 10168-2014", "Рабочая температура до 85°C"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'GNOM-50-50'), 2.0, 12, TRUE, '["Гидравлические испытания", "Проверка вибрации", "Контроль герметичности"]', '["ГОСТ 10168-2014", "Рабочая температура до 85°C"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'GNOM-100-25'), 2.0, 12, TRUE, '["Гидравлические испытания", "Проверка вибрации", "Контроль герметичности"]', '["ГОСТ 10168-2014", "Рабочая температура до 85°C"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'PUMP-K'), 18.5, 12, TRUE, '["Гидравлические испытания", "Проверка вибрации", "Контроль герметичности"]', '["ГОСТ 10168-2014", "Рабочая температура до 85°C"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'PUMP-TSNL'), 45.0, 12, TRUE, '["Гидравлические испытания", "Проверка вибрации", "Контроль герметичности"]', '["ГОСТ 10168-2014", "Рабочая температура до 85°C"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'PUMP-HOUSEHOLD'), 2.0, 12, TRUE, '["Гидравлические испытания", "Проверка вибрации", "Контроль герметичности"]', '["ГОСТ 10168-2014", "Рабочая температура до 85°C"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'EKCH-145-1.0'), 8.0, 12, FALSE, '["Электрические испытания ТЭНов", "Проверка автоматики безопасности"]', '["ГОСТ Р 50033-2000", "Напряжение 380В"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'EKCH-180-1.2'), 8.0, 12, FALSE, '["Электрические испытания ТЭНов", "Проверка автоматики безопасности"]', '["ГОСТ Р 50033-2000", "Напряжение 380В"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'EKCH-180-1.5'), 8.0, 12, FALSE, '["Электрические испытания ТЭНов", "Проверка автоматики безопасности"]', '["ГОСТ Р 50033-2000", "Напряжение 380В"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'EKCH-220-2.0'), 8.0, 12, FALSE, '["Электрические испытания ТЭНов", "Проверка автоматики безопасности"]', '["ГОСТ Р 50033-2000", "Напряжение 380В"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'EKCHE-180-1.2'), 8.0, 12, FALSE, '["Электрические испытания ТЭНов", "Проверка автоматики безопасности"]', '["ГОСТ Р 50033-2000", "Напряжение 380В"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'EOST-14163-88'), 8.0, 12, FALSE, '["Электрические испытания ТЭНов", "Проверка автоматики безопасности"]', '["ГОСТ Р 50033-2000", "Напряжение 380В"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'GRATE-BOILER'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'GRATE-BAR'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'CAST-BBQ'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'CAST-MOLD'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'CAST-CRUCIBLE'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'CAST-HS-CUSTOM'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'IRON-PIG'), 5.0, 24, FALSE, '["Стандартный контроль качества"]', '["ГОСТ/ТУ согласно документации"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AL-CAST-CUSTOM'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'AL-PIG-AV87'), 5.0, 24, FALSE, '["Стандартный контроль качества"]', '["ГОСТ/ТУ согласно документации"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'CUSTOM-CAST-IRON'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]'),
((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = 'CUSTOM-CAST-ALUMINUM'), 15.5, 24, FALSE, '["Контроль химического состава", "Дефектоскопия отливок", "Термообработка"]', '["ГОСТ 1412-2016", "Класс точности CT8-CT10"]');

-- ============================================
-- МАТЕРИАЛЫ ПАСПОРТОВ (product_passport_materials)
-- ============================================

-- Удаляем старые материалы паспортов
DELETE FROM `product_passport_materials`;

-- Вставка материалов паспортов
INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit`, `sort_order`, `notes`) VALUES
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.44, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.09, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 0.69, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.64, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.34, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.22, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-071-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.06, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.27, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.9, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-080-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.46, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 3.0, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-090-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.2, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 1.07, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 6.18, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.7, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.64, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.33, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-100-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.5, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.54, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 1.66, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 8.88, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.72, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.76, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.37, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-112-2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.68, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.28, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.94, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.19, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.39, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.39, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.13, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 0.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.36, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.22, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080A6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.45, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.28, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.94, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.19, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.39, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.39, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.67, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 4.14, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.54, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.29, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.45, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.28, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.94, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.2, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 1.55, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 8.42, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.7, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.74, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.36, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L2-5.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.5, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.2, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.98, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 5.76, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.7, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.62, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.32, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L4-4.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.5, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.2, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.45, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.7, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '2AIR-100L6-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.5, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.2, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.7, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRS-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.5, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-PUMP-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-GEAR-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.2, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.7, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIR-MULTI-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.5, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.45, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.28, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.94, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.19, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.39, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.39, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-080B6-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.67, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 4.14, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.54, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.29, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L2-3.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.45, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L4-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.28, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.94, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIVR-090L6-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.13, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 0.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.36, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.22, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080C6-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.06, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 0.45, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.33, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.21, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRP-080A6-0.37'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.09, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 0.68, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.34, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.22, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B4-0.55'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.05, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 0.37, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.32, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.21, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRC-080B6-0.3'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.44, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.19, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.39, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.64, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.39, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-071C2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.06, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.19, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.39, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.39, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A2-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.13, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 0.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.36, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.22, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080A4-0.75'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.28, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.94, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B2-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.19, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.39, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.39, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080B4-1.1'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.4, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.64, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.46, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.26, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C2-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.28, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.94, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.42, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.24, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080C4-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.45, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-080D2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.45, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 2.93, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.48, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.27, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AIRE-090L2-2.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.66, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.66, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-080'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.2, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 1.93, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.68, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-090'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.35, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-15'), 2.2, 'кг', 1, 'Чугун серый СЧ15 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'WIRE-CU-PETV-2.0'), 0.17, 'кг', 2, 'Провод медный обмоточный ПЭТВ'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STEEL-ELECTR-2212'), 1.26, 'кг', 3, 'Сталь электротехническая 2212'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRG-6200-series'), 2, 'шт', 4, 'Подшипники качения 6200 серии'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FASTENER-MIX'), 0.7, 'кг', 5, 'Крепеж (болты, гайки, шайбы)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'VAR-PES-55'), 0.38, 'л', 6, 'Лак электроизоляционный ПЭС-55'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'INS-COMBO'), 0.23, 'кг', 7, 'Изоляционные материалы (комбинированные)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AISE-100'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-BAR-45-STD'), 1.5, 'кг', 8, 'Сталь конструкционная 45 для вала'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GNOM-10-10'), (SELECT material_id FROM temp_material_map WHERE material_code = 'MAT-HOUSE-PUMP'), 2.0, 'кг', 1, 'Корпус насоса композитный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GNOM-25-20'), (SELECT material_id FROM temp_material_map WHERE material_code = 'MAT-HOUSE-PUMP'), 2.0, 'кг', 1, 'Корпус насоса композитный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GNOM-50-50'), (SELECT material_id FROM temp_material_map WHERE material_code = 'MAT-HOUSE-PUMP'), 2.0, 'кг', 1, 'Корпус насоса композитный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GNOM-100-25'), (SELECT material_id FROM temp_material_map WHERE material_code = 'MAT-HOUSE-PUMP'), 2.0, 'кг', 1, 'Корпус насоса композитный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'PUMP-K'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-20'), 15.0, 'кг', 1, 'Чугун серый СЧ20 для корпуса насоса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'PUMP-K'), (SELECT material_id FROM temp_material_map WHERE material_code = 'BRONZE-BRZH'), 3.5, 'кг', 2, 'Бронза для рабочего колеса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'PUMP-K'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SEAL-MECH-K'), 1, 'шт', 3, 'Уплотнение торцевое'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'PUMP-TSNL'), (SELECT material_id FROM temp_material_map WHERE material_code = 'CAST-SC-20'), 45.0, 'кг', 1, 'Чугун серый СЧ20 для корпуса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'PUMP-TSNL'), (SELECT material_id FROM temp_material_map WHERE material_code = 'STAGE-TSNL'), 5, 'шт', 2, 'Ступени насоса'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'PUMP-HOUSEHOLD'), (SELECT material_id FROM temp_material_map WHERE material_code = 'MAT-HOUSE-PUMP'), 2.0, 'кг', 1, 'Корпус насоса композитный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-145-1.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'TEN-INDUSTRIAL'), 3, 'шт', 1, 'ТЭН промышленный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-145-1.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'PANEL-CONTROL'), 1, 'шт', 2, 'Шкаф управления'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-145-1.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-SHEET-3'), 8.0, 'кг', 3, 'Лист стальной 3мм'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-180-1.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'TEN-INDUSTRIAL'), 3, 'шт', 1, 'ТЭН промышленный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-180-1.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'PANEL-CONTROL'), 1, 'шт', 2, 'Шкаф управления'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-180-1.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-SHEET-3'), 8.0, 'кг', 3, 'Лист стальной 3мм'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-180-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'TEN-INDUSTRIAL'), 3, 'шт', 1, 'ТЭН промышленный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-180-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'PANEL-CONTROL'), 1, 'шт', 2, 'Шкаф управления'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-180-1.5'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-SHEET-3'), 8.0, 'кг', 3, 'Лист стальной 3мм'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-220-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'TEN-INDUSTRIAL'), 3, 'шт', 1, 'ТЭН промышленный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-220-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'PANEL-CONTROL'), 1, 'шт', 2, 'Шкаф управления'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCH-220-2.0'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-SHEET-3'), 8.0, 'кг', 3, 'Лист стальной 3мм'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCHE-180-1.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'TEN-INDUSTRIAL'), 3, 'шт', 1, 'ТЭН промышленный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCHE-180-1.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'PANEL-CONTROL'), 1, 'шт', 2, 'Шкаф управления'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EKCHE-180-1.2'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-SHEET-3'), 8.0, 'кг', 3, 'Лист стальной 3мм'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EOST-14163-88'), (SELECT material_id FROM temp_material_map WHERE material_code = 'TEN-INDUSTRIAL'), 3, 'шт', 1, 'ТЭН промышленный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EOST-14163-88'), (SELECT material_id FROM temp_material_map WHERE material_code = 'PANEL-CONTROL'), 1, 'шт', 2, 'Шкаф управления'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'EOST-14163-88'), (SELECT material_id FROM temp_material_map WHERE material_code = 'ST-SHEET-3'), 8.0, 'кг', 3, 'Лист стальной 3мм'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GRATE-BOILER'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GRATE-BOILER'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GRATE-BOILER'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GRATE-BAR'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GRATE-BAR'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'GRATE-BAR'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-BBQ'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-BBQ'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-BBQ'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-MOLD'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-MOLD'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-MOLD'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-CRUCIBLE'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-CRUCIBLE'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-CRUCIBLE'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-HS-CUSTOM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-HS-CUSTOM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CAST-HS-CUSTOM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'IRON-PIG'), (SELECT material_id FROM temp_material_map WHERE material_code = 'MAT-BASE-GEN'), 5.0, 'кг', 1, 'Материал базовый'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AL-CAST-CUSTOM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AL-CAST-CUSTOM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AL-CAST-CUSTOM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'AL-PIG-AV87'), (SELECT material_id FROM temp_material_map WHERE material_code = 'MAT-BASE-GEN'), 5.0, 'кг', 1, 'Материал базовый'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CUSTOM-CAST-IRON'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CUSTOM-CAST-IRON'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CUSTOM-CAST-IRON'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CUSTOM-CAST-ALUMINUM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'IRON-PIG'), 10.0, 'кг', 1, 'Чугун передельный'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CUSTOM-CAST-ALUMINUM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'FERRO-SI-MN'), 0.5, 'кг', 2, 'Ферросплавы (Si, Mn)'),
((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = 'CUSTOM-CAST-ALUMINUM'), (SELECT material_id FROM temp_material_map WHERE material_code = 'SAND-MOLD'), 5.0, 'кг', 3, 'Песок формовочный');

-- ============================================
-- ОБНОВЛЕНИЕ specifications В products
-- ============================================

-- Обновление AIR-071-2
UPDATE `products` SET `specifications` = '{"power_kw_min": 0.37, "power_kw_max": 0.75, "rpm": 3000, "shaft_height_mm": 71, "voltage_v": "380/220", "frequency_hz": 50, "climate_versions": ["У2", "У3", "У5", "УХЛ2", "УХЛ4", "Т2"], "mounting_versions": ["IM1081", "IM1082", "IM2081", "IM2082", "IM2181", "IM2182", "IM3081", "IM3082", "IM3681", "IM3682"], "protection_class": ["IP54", "IP55"]}' WHERE `article` = 'AIR-071-2';

-- Обновление AIR-080-2
UPDATE `products` SET `specifications` = '{"power_kw_min": 0.75, "power_kw_max": 2.2, "rpm": 3000, "shaft_height_mm": 80, "voltage_v": "380/220", "frequency_hz": 50, "climate_versions": ["У2", "У3", "У5", "УХЛ2", "УХЛ4", "Т2"], "mounting_versions": ["IM1081", "IM1082", "IM2081", "IM2082", "IM2181", "IM2182", "IM3081", "IM3082", "IM3681", "IM3682"], "protection_class": ["IP54", "IP55"]}' WHERE `article` = 'AIR-080-2';

-- Обновление AIR-090-2
UPDATE `products` SET `specifications` = '{"power_kw_min": 1.5, "power_kw_max": 3.0, "rpm": 3000, "shaft_height_mm": 90, "voltage_v": "380/220", "frequency_hz": 50, "climate_versions": ["У2", "У3", "У5", "УХЛ2", "УХЛ4", "Т2"], "mounting_versions": ["IM1081", "IM1082", "IM2081", "IM2082", "IM2181", "IM2182", "IM3081", "IM3082", "IM3681", "IM3682"], "protection_class": ["IP54", "IP55"]}' WHERE `article` = 'AIR-090-2';

-- Обновление AIR-100-2
UPDATE `products` SET `specifications` = '{"power_kw_min": 3.0, "power_kw_max": 5.5, "rpm": 3000, "shaft_height_mm": 100, "voltage_v": "380/220", "frequency_hz": 50, "climate_versions": ["У2", "У3", "У5", "УХЛ2", "УХЛ4", "Т2"], "mounting_versions": ["IM1081", "IM1082", "IM2081", "IM2082", "IM2181", "IM2182", "IM3081", "IM3082", "IM3681", "IM3682"], "protection_class": ["IP54", "IP55"]}' WHERE `article` = 'AIR-100-2';

-- Обновление AIR-112-2
UPDATE `products` SET `specifications` = '{"power_kw_min": 4.0, "power_kw_max": 7.5, "rpm": 3000, "shaft_height_mm": 112, "voltage_v": "380/220", "frequency_hz": 50, "climate_versions": ["У2", "У3", "У5", "УХЛ2", "УХЛ4", "Т2"], "mounting_versions": ["IM1081", "IM1082", "IM2081", "IM2082", "IM2181", "IM2182", "IM3081", "IM3082", "IM3681", "IM3682"], "protection_class": ["IP54", "IP55"]}' WHERE `article` = 'AIR-112-2';

-- Обновление 2AIR-080A2-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "rpm": 3000, "shaft_height_mm": 80, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-080A2-1.5';

-- Обновление 2AIR-080A4-1.1
UPDATE `products` SET `specifications` = '{"power_kw": 1.1, "rpm": 1500, "shaft_height_mm": 80, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-080A4-1.1';

-- Обновление 2AIR-080A6-0.75
UPDATE `products` SET `specifications` = '{"power_kw": 0.75, "rpm": 1000, "shaft_height_mm": 80, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-080A6-0.75';

-- Обновление 2AIR-080B2-2.2
UPDATE `products` SET `specifications` = '{"power_kw": 2.2, "rpm": 3000, "shaft_height_mm": 80, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-080B2-2.2';

-- Обновление 2AIR-080B4-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "rpm": 1500, "shaft_height_mm": 80, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-080B4-1.5';

-- Обновление 2AIR-080B6-1.1
UPDATE `products` SET `specifications` = '{"power_kw": 1.1, "rpm": 1000, "shaft_height_mm": 80, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-080B6-1.1';

-- Обновление 2AIR-090L2-3.0
UPDATE `products` SET `specifications` = '{"power_kw": 3.0, "rpm": 3000, "shaft_height_mm": 90, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-090L2-3.0';

-- Обновление 2AIR-090L4-2.2
UPDATE `products` SET `specifications` = '{"power_kw": 2.2, "rpm": 1500, "shaft_height_mm": 90, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-090L4-2.2';

-- Обновление 2AIR-090L6-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "rpm": 1000, "shaft_height_mm": 90, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-090L6-1.5';

-- Обновление 2AIR-100L2-5.5
UPDATE `products` SET `specifications` = '{"power_kw": 5.5, "rpm": 3000, "shaft_height_mm": 100, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-100L2-5.5';

-- Обновление 2AIR-100L4-4.0
UPDATE `products` SET `specifications` = '{"power_kw": 4.0, "rpm": 1500, "shaft_height_mm": 100, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-100L4-4.0';

-- Обновление 2AIR-100L6-2.2
UPDATE `products` SET `specifications` = '{"power_kw": 2.2, "rpm": 1000, "shaft_height_mm": 100, "efficiency_class": "IE2", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = '2AIR-100L6-2.2';

-- Обновление AIRS-080
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 80, "slip_percent": 10, "application": "Приводы с переменной нагрузкой"}' WHERE `article` = 'AIRS-080';

-- Обновление AIRS-090
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 90, "slip_percent": 10, "application": "Приводы с переменной нагрузкой"}' WHERE `article` = 'AIRS-090';

-- Обновление AIRS-100
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 100, "slip_percent": 10, "application": "Приводы с переменной нагрузкой"}' WHERE `article` = 'AIRS-100';

-- Обновление AIR-PUMP-080
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 80, "application": "Моноблочные насосы"}' WHERE `article` = 'AIR-PUMP-080';

-- Обновление AIR-PUMP-090
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 90, "application": "Моноблочные насосы"}' WHERE `article` = 'AIR-PUMP-090';

-- Обновление AIR-GEAR-090
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 90, "application": "Привод редукторов"}' WHERE `article` = 'AIR-GEAR-090';

-- Обновление AIR-MULTI-080
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 80, "speeds": "2/4/6/8 полюсов", "application": "Переменная скорость"}' WHERE `article` = 'AIR-MULTI-080';

-- Обновление AIR-MULTI-090
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 90, "speeds": "2/4/6/8 полюсов", "application": "Переменная скорость"}' WHERE `article` = 'AIR-MULTI-090';

-- Обновление AIR-MULTI-100
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 100, "speeds": "2/4/6/8 полюсов", "application": "Переменная скорость"}' WHERE `article` = 'AIR-MULTI-100';

-- Обновление AIVR-080B2-2.2
UPDATE `products` SET `specifications` = '{"power_kw": 2.2, "rpm": 3000, "shaft_height_mm": 80, "explosion_protection": "1Ex d IIB T4 Gb", "housing_material": "Чугун", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = 'AIVR-080B2-2.2';

-- Обновление AIVR-080B4-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "rpm": 1500, "shaft_height_mm": 80, "explosion_protection": "1Ex d IIB T4 Gb", "housing_material": "Чугун", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = 'AIVR-080B4-1.5';

-- Обновление AIVR-080B6-1.1
UPDATE `products` SET `specifications` = '{"power_kw": 1.1, "rpm": 1000, "shaft_height_mm": 80, "explosion_protection": "1Ex d IIB T4 Gb", "housing_material": "Чугун", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = 'AIVR-080B6-1.1';

-- Обновление AIVR-090L2-3.0
UPDATE `products` SET `specifications` = '{"power_kw": 3.0, "rpm": 3000, "shaft_height_mm": 90, "explosion_protection": "1Ex d IIB T4 Gb", "housing_material": "Чугун", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = 'AIVR-090L2-3.0';

-- Обновление AIVR-090L4-2.2
UPDATE `products` SET `specifications` = '{"power_kw": 2.2, "rpm": 1500, "shaft_height_mm": 90, "explosion_protection": "1Ex d IIB T4 Gb", "housing_material": "Чугун", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = 'AIVR-090L4-2.2';

-- Обновление AIVR-090L6-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "rpm": 1000, "shaft_height_mm": 90, "explosion_protection": "1Ex d IIB T4 Gb", "housing_material": "Чугун", "voltage_v": "380/220", "frequency_hz": 50}' WHERE `article` = 'AIVR-090L6-1.5';

-- Обновление AIRP-080C6-0.75
UPDATE `products` SET `specifications` = '{"power_kw": 0.75, "rpm": 1000, "shaft_height_mm": 80, "application": "Осевые вентиляторы в животноводческих и птицеводческих помещениях"}' WHERE `article` = 'AIRP-080C6-0.75';

-- Обновление AIRP-080A6-0.37
UPDATE `products` SET `specifications` = '{"power_kw": 0.37, "rpm": 1000, "shaft_height_mm": 80, "application": "Осевые вентиляторы в животноводческих и птицеводческих помещениях"}' WHERE `article` = 'AIRP-080A6-0.37';

-- Обновление AIRC-080B4-0.55
UPDATE `products` SET `specifications` = '{"power_kw": 0.55, "rpm": 1500, "shaft_height_mm": 80, "application": "Стрелочные электроприводы железнодорожной автоматики", "climate_versions": ["УХЛ", "Т"]}' WHERE `article` = 'AIRC-080B4-0.55';

-- Обновление AIRC-080B6-0.3
UPDATE `products` SET `specifications` = '{"power_kw": 0.3, "rpm": 1000, "shaft_height_mm": 80, "application": "Стрелочные электроприводы железнодорожной автоматики", "climate_versions": ["УХЛ", "Т"]}' WHERE `article` = 'AIRC-080B6-0.3';

-- Обновление AIRE-071C2-1.1
UPDATE `products` SET `specifications` = '{"power_kw": 1.1, "rpm": 3000, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 71, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-071C2-1.1';

-- Обновление AIRE-080A2-1.1
UPDATE `products` SET `specifications` = '{"power_kw": 1.1, "rpm": 3000, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 80, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-080A2-1.1';

-- Обновление AIRE-080A4-0.75
UPDATE `products` SET `specifications` = '{"power_kw": 0.75, "rpm": 1500, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 80, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-080A4-0.75';

-- Обновление AIRE-080B2-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "rpm": 3000, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 80, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-080B2-1.5';

-- Обновление AIRE-080B4-1.1
UPDATE `products` SET `specifications` = '{"power_kw": 1.1, "rpm": 1500, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 80, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-080B4-1.1';

-- Обновление AIRE-080C2-2.0
UPDATE `products` SET `specifications` = '{"power_kw": 2.0, "rpm": 3000, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 80, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-080C2-2.0';

-- Обновление AIRE-080C4-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "rpm": 1500, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 80, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-080C4-1.5';

-- Обновление AIRE-080D2-2.2
UPDATE `products` SET `specifications` = '{"power_kw": 2.2, "rpm": 3000, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 80, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-080D2-2.2';

-- Обновление AIRE-090L2-2.2
UPDATE `products` SET `specifications` = '{"power_kw": 2.2, "rpm": 3000, "voltage_v": "220", "frequency_hz": 50, "shaft_height_mm": 90, "capacitor_included": true, "application": "Бытовое и промышленное назначение"}' WHERE `article` = 'AIRE-090L2-2.2';

-- Обновление AISE-080
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 80, "voltage_v": "220", "application": "Промышленное назначение"}' WHERE `article` = 'AISE-080';

-- Обновление AISE-090
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 90, "voltage_v": "220", "application": "Промышленное назначение"}' WHERE `article` = 'AISE-090';

-- Обновление AISE-100
UPDATE `products` SET `specifications` = '{"shaft_height_mm": 100, "voltage_v": "220", "application": "Промышленное назначение"}' WHERE `article` = 'AISE-100';

-- Обновление GNOM-10-10
UPDATE `products` SET `specifications` = '{"flow_rate_m3_h": 10, "head_m": 10, "power_kw": 0.9, "voltage_v": "220/380", "frequency_hz": 50, "housing_material": "Чугун", "impeller_material": "Чугун", "max_solid_size_mm": 5, "max_immersion_depth_m": 7, "application": "Перекачка загрязненной воды, осушение котлованов и траншей"}' WHERE `article` = 'GNOM-10-10';

-- Обновление GNOM-25-20
UPDATE `products` SET `specifications` = '{"flow_rate_m3_h": 25, "head_m": 20, "power_kw": 3.0, "voltage_v": "220/380", "frequency_hz": 50, "housing_material": "Чугун", "impeller_material": "Чугун", "max_solid_size_mm": 5, "max_immersion_depth_m": 7, "application": "Перекачка загрязненной воды, осушение котлованов и траншей"}' WHERE `article` = 'GNOM-25-20';

-- Обновление GNOM-50-50
UPDATE `products` SET `specifications` = '{"flow_rate_m3_h": 50, "head_m": 50, "power_kw": 11.0, "voltage_v": "380", "frequency_hz": 50, "housing_material": "Чугун", "impeller_material": "Чугун", "max_solid_size_mm": 5, "max_immersion_depth_m": 7, "application": "Перекачка загрязненной воды, осушение котлованов и траншей"}' WHERE `article` = 'GNOM-50-50';

-- Обновление GNOM-100-25
UPDATE `products` SET `specifications` = '{"flow_rate_m3_h": 100, "head_m": 25, "power_kw": 11.0, "voltage_v": "380", "frequency_hz": 50, "housing_material": "Чугун", "impeller_material": "Чугун", "max_solid_size_mm": 5, "max_immersion_depth_m": 7, "application": "Перекачка загрязненной воды, осушение котлованов и траншей"}' WHERE `article` = 'GNOM-100-25';

-- Обновление PUMP-K
UPDATE `products` SET `specifications` = '{"application": "Перекачка воды и подобных жидкостей", "type": "Консольные центробежные"}' WHERE `article` = 'PUMP-K';

-- Обновление PUMP-TSNL
UPDATE `products` SET `specifications` = '{"application": "Перекачка воды и подобных жидкостей", "type": "Консольные линейные"}' WHERE `article` = 'PUMP-TSNL';

-- Обновление PUMP-HOUSEHOLD
UPDATE `products` SET `specifications` = '{"application": "Бытовое водоснабжение", "type": "Центробежные"}' WHERE `article` = 'PUMP-HOUSEHOLD';

-- Обновление EKCH-145-1.0
UPDATE `products` SET `specifications` = '{"power_kw": 1.0, "voltage_v": "220", "frequency_hz": 50, "diameter_mm": 145, "material": "Чугун", "application": "Бытовые электроплиты"}' WHERE `article` = 'EKCH-145-1.0';

-- Обновление EKCH-180-1.2
UPDATE `products` SET `specifications` = '{"power_kw": 1.2, "voltage_v": "220", "frequency_hz": 50, "diameter_mm": 180, "material": "Чугун", "application": "Бытовые электроплиты"}' WHERE `article` = 'EKCH-180-1.2';

-- Обновление EKCH-180-1.5
UPDATE `products` SET `specifications` = '{"power_kw": 1.5, "voltage_v": "220", "frequency_hz": 50, "diameter_mm": 180, "material": "Чугун", "application": "Бытовые электроплиты"}' WHERE `article` = 'EKCH-180-1.5';

-- Обновление EKCH-220-2.0
UPDATE `products` SET `specifications` = '{"power_kw": 2.0, "voltage_v": "220", "frequency_hz": 50, "diameter_mm": 220, "material": "Чугун", "application": "Бытовые электроплиты"}' WHERE `article` = 'EKCH-220-2.0';

-- Обновление EKCHE-180-1.2
UPDATE `products` SET `specifications` = '{"power_kw": 1.2, "voltage_v": "220", "frequency_hz": 50, "diameter_mm": 180, "material": "Чугун", "type": "Экспресс (быстрого нагрева)", "application": "Бытовые электроплиты"}' WHERE `article` = 'EKCHE-180-1.2';

-- Обновление EOST-14163-88
UPDATE `products` SET `specifications` = '{"material": "Чугун", "standard": "ЕОСТ14163-88", "application": "Бытовые электроплиты"}' WHERE `article` = 'EOST-14163-88';

-- Обновление GRATE-BOILER
UPDATE `products` SET `specifications` = '{"material": "СЧ15, СЧ20", "standard": "ГОСТ 1412-85", "production_method": "Ручная формовка", "weight_range_kg": "20-1500", "application": "Отопительные котлы, печи"}' WHERE `article` = 'GRATE-BOILER';

-- Обновление GRATE-BAR
UPDATE `products` SET `specifications` = '{"material": "СЧ15, СЧ20", "standard": "ГОСТ 1412-85", "production_method": "Ручная формовка", "weight_range_kg": "20-1500", "application": "Отопительные котлы, печи"}' WHERE `article` = 'GRATE-BAR';

-- Обновление CAST-BBQ
UPDATE `products` SET `specifications` = '{"material": "СЧ15, СЧ20", "standard": "ГОСТ 1412-85", "production_method": "Ручная формовка", "weight_range_kg": "20-1500"}' WHERE `article` = 'CAST-BBQ';

-- Обновление CAST-MOLD
UPDATE `products` SET `specifications` = '{"material": "СЧ15, СЧ20", "standard": "ГОСТ 1412-85", "production_method": "Ручная формовка", "weight_range_kg": "20-1500", "application": "Металлургическое производство"}' WHERE `article` = 'CAST-MOLD';

-- Обновление CAST-CRUCIBLE
UPDATE `products` SET `specifications` = '{"material": "СЧ15, СЧ20", "standard": "ГОСТ 1412-85", "production_method": "Ручная формовка", "weight_range_kg": "20-1500", "application": "Плавка металлов"}' WHERE `article` = 'CAST-CRUCIBLE';

-- Обновление CAST-HS-CUSTOM
UPDATE `products` SET `specifications` = '{"material": "ВЧ35, ВЧ40, ВЧ50", "production_method": "Ручная/машинная формовка", "weight_range_kg": "20-1500", "custom_made": true}' WHERE `article` = 'CAST-HS-CUSTOM';

-- Обновление IRON-PIG
UPDATE `products` SET `specifications` = '{"material": "СЧ15, СЧ20", "application": "Переплавка, литейное производство"}' WHERE `article` = 'IRON-PIG';

-- Обновление AL-CAST-CUSTOM
UPDATE `products` SET `specifications` = '{"material": "АК7, АК9, АК12, АВ-87", "production_method": "Литье под давлением", "custom_made": true, "application": "Корпуса электродвигателей, детали по чертежам заказчика"}' WHERE `article` = 'AL-CAST-CUSTOM';

-- Обновление AL-PIG-AV87
UPDATE `products` SET `specifications` = '{"material": "АВ-87", "application": "Переплавка, литейное производство"}' WHERE `article` = 'AL-PIG-AV87';

-- Обновление CUSTOM-CAST-IRON
UPDATE `products` SET `specifications` = '{"material": "СЧ15, СЧ20, ВЧ35-ВЧ50", "production_method": "Ручная формовка (20-1500 кг), машинная формовка (до 30 кг, 650x480 мм)", "weight_range_kg": "20-1500", "custom_made": true, "order_requirements": "Чертежи, эскизы или модели заказчика"}' WHERE `article` = 'CUSTOM-CAST-IRON';

-- Обновление CUSTOM-CAST-ALUMINUM
UPDATE `products` SET `specifications` = '{"material": "АК7, АК9, АК12, АВ-87", "production_method": "Литье под давлением", "custom_made": true, "order_requirements": "Чертежи, эскизы или модели заказчика"}' WHERE `article` = 'CUSTOM-CAST-ALUMINUM';

-- ============================================
-- ЗАВЕРШЕНИЕ
-- ============================================

SET FOREIGN_KEY_CHECKS = 1;

-- Очистка временных таблиц
DROP TABLE IF EXISTS `temp_material_map`;
DROP TABLE IF EXISTS `temp_product_map`;

-- Готово!
-- Импортировано 72 паспортов продуктов