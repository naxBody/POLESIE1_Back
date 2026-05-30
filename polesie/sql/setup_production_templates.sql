-- Проверка и настройка этапов производства для разных видов продукции
-- ОАО "Полесьеэлектромаш"

-- ============================================
-- 1. ПРОВЕРКА СУЩЕСТВУЮЩИХ ДАННЫХ
-- ============================================

-- Показать все продукты и их маршрутные карты
SELECT 
    p.id,
    p.article,
    p.name as product_name,
    rc.id as route_card_id,
    rc.name as route_name,
    COUNT(rco.id) as operations_count
FROM products p
LEFT JOIN route_cards rc ON rc.product_id = p.id AND rc.is_active = 1
LEFT JOIN route_card_operations rco ON rco.route_card_id = rc.id
GROUP BY p.id, rc.id
ORDER BY p.article;

-- Показать этапы для каждого продукта
SELECT 
    p.article,
    p.name as product_name,
    rc.name as route_name,
    rco.operation_number,
    ps.name as stage_name,
    rco.name as operation_name,
    rco.work_center,
    rco.time_norm_hours
FROM products p
JOIN route_cards rc ON rc.product_id = p.id AND rc.is_active = 1
JOIN route_card_operations rco ON rco.route_card_id = rc.id
LEFT JOIN production_stages ps ON ps.id = rco.stage_id
ORDER BY p.article, rco.sort_order;

-- ============================================
-- 2. ДОПОЛНЕНИЕ ШАБЛОНОВ ДЛЯ НОВЫХ ПРОДУКТОВ
-- ============================================

-- Если для каких-то продуктов нет маршрутных карт, создаем типовые

-- Для двигателей серии АИР (если нет)
INSERT INTO route_cards (product_id, name, version, description, total_time_hours, is_active)
SELECT 
    p.id,
    CONCAT('Маршрутная карта ', p.article),
    '1.0',
    CONCAT('Типовой процесс производства двигателя ', p.name),
    24.0,
    TRUE
FROM products p
WHERE p.article LIKE 'AIR%' 
  OR p.article LIKE '2AIR%' 
  OR p.article LIKE 'АИР%'
  OR p.article LIKE 'АИВР%'
  AND NOT EXISTS (
      SELECT 1 FROM route_cards rc WHERE rc.product_id = p.id AND rc.is_active = 1
  );

-- Для коробок и клеммных коробок (если нет)
INSERT INTO route_cards (product_id, name, version, description, total_time_hours, is_active)
SELECT 
    p.id,
    CONCAT('Маршрутная карта ', p.article),
    '1.0',
    CONCAT('Типовой процесс производства ', p.name),
    8.0,
    TRUE
FROM products p
WHERE (p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%')
  AND NOT EXISTS (
      SELECT 1 FROM route_cards rc WHERE rc.product_id = p.id AND rc.is_active = 1
  );

-- Для редукторов (если нет)
INSERT INTO route_cards (product_id, name, version, description, total_time_hours, is_active)
SELECT 
    p.id,
    CONCAT('Маршрутная карта ', p.article),
    '1.0',
    CONCAT('Типовой процесс производства редуктора ', p.name),
    16.0,
    TRUE
FROM products p
WHERE p.article LIKE 'GNOM%'
  AND NOT EXISTS (
      SELECT 1 FROM route_cards rc WHERE rc.product_id = p.id AND rc.is_active = 1
  );

-- ============================================
-- 3. СОЗДАНИЕ ОПЕРАЦИЙ ДЛЯ ТИПОВЫХ МАРШРУТОВ
-- ============================================

-- Операции для двигателей АИР
INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    10,
    (SELECT id FROM production_stages WHERE code = 'blank'),
    'Раскрой стали',
    'Раскрой электротехнической стали для статора и ротора',
    'Заготовительный участок',
    'Гильотинные ножницы',
    0.5,
    '{"материалы": ["STEEL-SHEET-0.5"]}',
    'Соблюдать размеры по чертежу',
    1
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%')
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    20,
    (SELECT id FROM production_stages WHERE code = 'machining'),
    'Штамповка пластин',
    'Вырубка пластин статора и ротора',
    'Прессовый участок',
    'Пресс механический',
    1.0,
    '{}',
    'Контролировать качество вырубки',
    2
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%')
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    30,
    (SELECT id FROM production_stages WHERE code = 'winding'),
    'Намотка обмоток',
    'Намотка обмоток статора',
    'Намоточный участок',
    'Станок намоточный',
    2.5,
    '{"материалы": ["WIRE-CU-1.0", "WIRE-CU-1.5"]}',
    'Соблюдать количество витков',
    3
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%')
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    40,
    (SELECT id FROM production_stages WHERE code = 'assembly'),
    'Сборка двигателя',
    'Сборка статора и ротора, установка подшипников',
    'Сборочный участок',
    'Стенд сборочный',
    3.0,
    '{"материалы": ["BEARING-6205", "BEARING-6209"]}',
    'Соблюдать момент затяжки',
    4
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%')
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    50,
    (SELECT id FROM production_stages WHERE code = 'painting'),
    'Окраска',
    'Покраска корпуса двигателя',
    'Малярный участок',
    'Камера окрасочная',
    1.5,
    '{"материалы": ["PAINT-BLUE"]}',
    'Равномерное нанесение',
    5
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%')
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    60,
    (SELECT id FROM production_stages WHERE code = 'qc'),
    'Контроль качества',
    'Проверка электрических параметров',
    'Контрольный участок',
    'Стенд испытательный',
    1.0,
    '{}',
    'Проверить все параметры',
    6
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%')
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

-- Операции для коробок BC
INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    10,
    (SELECT id FROM production_stages WHERE code = 'casting'),
    'Литье корпуса',
    'Литье алюминиевого корпуса',
    'Литейный участок',
    'Машина литьевая',
    1.0,
    '{"материалы": ["ALUM-BAR-10"]}',
    'Температура 680-700°C',
    1
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE p.article LIKE 'BC-%'
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    20,
    (SELECT id FROM production_stages WHERE code = 'machining'),
    'Механообработка',
    'Обработка корпуса после литья',
    'Механообрабатывающий участок',
    'Станок ЧПУ',
    1.5,
    '{}',
    'Соблюдать допуски',
    2
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE p.article LIKE 'BC-%'
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    30,
    (SELECT id FROM production_stages WHERE code = 'assembly'),
    'Сборка',
    'Установка клеммной колодки и крышки',
    'Сборочный участок',
    'Верстак сборочный',
    0.5,
    '{"материалы": ["TERMINAL-BLOCK"]}',
    'Проверить крепление',
    3
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE p.article LIKE 'BC-%'
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

-- Операции для редукторов GNOM
INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    10,
    (SELECT id FROM production_stages WHERE code = 'machining'),
    'Изготовление деталей',
    'Токарная и фрезерная обработка',
    'Механообрабатывающий участок',
    'Станок токарный, Станок фрезерный',
    3.0,
    '{"материалы": ["STEEL-BAR-45"]}',
    'Соблюдать чертежи',
    1
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE p.article LIKE 'GNOM%'
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    20,
    (SELECT id FROM production_stages WHERE code = 'assembly'),
    'Сборка редуктора',
    'Установка валов, шестерен, подшипников',
    'Сборочный участок',
    'Пресс гидравлический',
    2.0,
    '{"материалы": ["GEAR-SET", "BEARING-6205"]}',
    'Соблюдать зазоры',
    2
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE p.article LIKE 'GNOM%'
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, materials_required, instructions, sort_order)
SELECT 
    rc.id,
    30,
    (SELECT id FROM production_stages WHERE code = 'qc'),
    'Испытания',
    'Проверка передаточного числа и шума',
    'Контрольный участок',
    'Стенд испытательный',
    1.0,
    '{}',
    'Проверить все режимы',
    3
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE p.article LIKE 'GNOM%'
  AND NOT EXISTS (
      SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id
  );

-- ============================================
-- 4. ОБНОВЛЕНИЕ ЗАДАНИЙ БЕЗ МАРШРУТНЫХ КАРТ
-- ============================================

-- Обновляем route_card_id в существующих заданиях где он не указан
UPDATE production_tasks pt
JOIN products p ON p.id = pt.product_id
JOIN route_cards rc ON rc.product_id = p.id AND rc.is_active = 1
SET pt.route_card_id = rc.id
WHERE pt.route_card_id IS NULL;

-- ============================================
-- 5. ФИНАЛЬНАЯ ПРОВЕРКА
-- ============================================

-- Итоговая статистика
SELECT 
    'Продукты с маршрутными картами' as metric,
    COUNT(DISTINCT p.id) as count
FROM products p
JOIN route_cards rc ON rc.product_id = p.id AND rc.is_active = 1
UNION ALL
SELECT 
    'Всего операций в маршрутах',
    COUNT(rco.id)
FROM route_card_operations rco
JOIN route_cards rc ON rc.id = rco.route_card_id
WHERE rc.is_active = 1
UNION ALL
SELECT 
    'Задания с маршрутными картами',
    COUNT(pt.id)
FROM production_tasks pt
WHERE pt.route_card_id IS NOT NULL;
