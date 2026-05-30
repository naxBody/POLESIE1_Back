-- Настройка различных этапов производства для разных типов продукции
-- ОАО "Полесьеэлектромаш"

-- ============================================
-- 1. ПРОВЕРКА И ДОБАВЛЕНИЕ ЭТАПОВ ПРОИЗВОДСТВА
-- ============================================

-- Добавляем дополнительные этапы если их нет
INSERT INTO production_stages (`name`, `code`, `description`, `sort_order`, `color`, `is_active`) VALUES
('Термообработка', 'heat_treatment', 'Термическая обработка деталей', 11, '#FF9F43', TRUE),
('Гальваника', 'plating', 'Гальваническое покрытие', 12, '#FD79A8', TRUE),
('Сварка', 'welding', 'Сварочные работы', 13, '#E17055', TRUE)
ON DUPLICATE KEY UPDATE name=name;

-- ============================================
-- 2. СОЗДАНИЕ МАРШРУТНЫХ КАРТ ДЛЯ ПРОДУКТОВ БЕЗ НИХ
-- ============================================

-- Для двигателей (AIR, АИР, 2AIR, АИВР)
INSERT INTO route_cards (product_id, name, version, description, total_time_hours, is_active, created_at)
SELECT 
    p.id,
    CONCAT('Маршрутная карта ', p.article, ' - Двигатель'),
    '1.0',
    CONCAT('Типовой процесс производства двигателя ', p.name),
    24.0,
    TRUE,
    NOW()
FROM products p
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND NOT EXISTS (
      SELECT 1 FROM route_cards rc WHERE rc.product_id = p.id AND rc.is_active = 1
  );

-- Для литейных изделий (коробки, корпуса)
INSERT INTO route_cards (product_id, name, version, description, total_time_hours, is_active, created_at)
SELECT 
    p.id,
    CONCAT('Маршрутная карта ', p.article, ' - Литье'),
    '1.0',
    CONCAT('Типовой процесс производства литейного изделия ', p.name),
    12.0,
    TRUE,
    NOW()
FROM products p
WHERE (p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%')
  AND NOT EXISTS (
      SELECT 1 FROM route_cards rc WHERE rc.product_id = p.id AND rc.is_active = 1
  );

-- Для редукторов (GNOM и подобные)
INSERT INTO route_cards (product_id, name, version, description, total_time_hours, is_active, created_at)
SELECT 
    p.id,
    CONCAT('Маршрутная карта ', p.article, ' - Редуктор'),
    '1.0',
    CONCAT('Типовой процесс производства редуктора ', p.name),
    16.0,
    TRUE,
    NOW()
FROM products p
WHERE (p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND NOT EXISTS (
      SELECT 1 FROM route_cards rc WHERE rc.product_id = p.id AND rc.is_active = 1
  );

-- Для остальных продуктов (универсальный маршрут)
INSERT INTO route_cards (product_id, name, version, description, total_time_hours, is_active, created_at)
SELECT 
    p.id,
    CONCAT('Маршрутная карта ', p.article),
    '1.0',
    CONCAT('Производственный процесс ', p.name),
    8.0,
    TRUE,
    NOW()
FROM products p
WHERE NOT EXISTS (
      SELECT 1 FROM route_cards rc WHERE rc.product_id = p.id AND rc.is_active = 1
  );

-- ============================================
-- 3. ОПЕРАЦИИ ДЛЯ ДВИГАТЕЛЕЙ
-- ============================================

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    10,
    ps.id,
    'Раскрой материалов',
    'Подготовка и раскрой электротехнической стали',
    'Заготовительный участок',
    'Гильотинные ножницы, Пресс',
    0.5,
    1
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND ps.code = 'blank'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 10);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    20,
    ps.id,
    'Литье ротора',
    'Литье алюминиевого ротора',
    'Литейный участок',
    'Машина литьевая под давлением',
    1.5,
    2
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND ps.code = 'casting'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 20);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    30,
    ps.id,
    'Механообработка',
    'Токарная обработка деталей',
    'Механообрабатывающий участок',
    'Станок токарный, Станок фрезерный',
    2.0,
    3
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND ps.code = 'machining'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 30);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    40,
    ps.id,
    'Намотка статора',
    'Намотка обмоток статора',
    'Намоточный участок',
    'Станок намоточный автоматический',
    3.0,
    4
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND ps.code = 'winding'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 40);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    50,
    ps.id,
    'Сборка двигателя',
    'Сборка статора и ротора, установка подшипников',
    'Сборочный участок',
    'Пресс гидравлический, Верстак сборочный',
    2.5,
    5
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND ps.code = 'assembly'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 50);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    60,
    ps.id,
    'Покраска',
    'Нанесение лакокрасочного покрытия',
    'Малярный участок',
    'Камера окрасочная',
    1.5,
    6
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND ps.code = 'painting'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 60);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    70,
    ps.id,
    'Контроль качества',
    'Проверка электрических параметров и испытаний',
    'Контрольный участок',
    'Стенд испытательный',
    1.0,
    7
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND ps.code = 'qc'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 70);

-- ============================================
-- 4. ОПЕРАЦИИ ДЛЯ ЛИТЕЙНЫХ ИЗДЕЛИЙ
-- ============================================

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    10,
    ps.id,
    'Литье корпуса',
    'Литье алюминиевого корпуса',
    'Литейный участок',
    'Машина литьевая под давлением',
    1.5,
    1
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%')
  AND ps.code = 'casting'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 10);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    20,
    ps.id,
    'Механообработка',
    'Обработка отливок',
    'Механообрабатывающий участок',
    'Станок ЧПУ, Станок фрезерный',
    2.0,
    2
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%')
  AND ps.code = 'machining'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 20);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    30,
    ps.id,
    'Сборка',
    'Установка комплектующих',
    'Сборочный участок',
    'Верстак сборочный',
    1.0,
    3
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%')
  AND ps.code = 'assembly'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 30);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    40,
    ps.id,
    'Контроль качества',
    'Проверка геометрии и качества',
    'Контрольный участок',
    'Измерительный инструмент',
    0.5,
    4
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%')
  AND ps.code = 'qc'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 40);

-- ============================================
-- 5. ОПЕРАЦИИ ДЛЯ РЕДУКТОРОВ
-- ============================================

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    10,
    ps.id,
    'Заготовка',
    'Подготовка заготовок валов и корпусов',
    'Заготовительный участок',
    'Пила ленточная',
    0.5,
    1
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'blank'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 10);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    20,
    ps.id,
    'Механообработка',
    'Изготовление деталей редуктора',
    'Механообрабатывающий участок',
    'Станок токарный, Станок зубофрезерный',
    4.0,
    2
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'machining'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 20);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    30,
    ps.id,
    'Термообработка',
    'Закалка и отпуск деталей',
    'Термический участок',
    'Печь термическая',
    2.0,
    3
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'heat_treatment'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 30);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    40,
    ps.id,
    'Сборка редуктора',
    'Установка валов, шестерен, подшипников',
    'Сборочный участок',
    'Пресс гидравлический',
    2.5,
    4
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'assembly'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 40);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    50,
    ps.id,
    'Испытания',
    'Проверка передаточного числа и шума',
    'Контрольный участок',
    'Стенд испытательный',
    1.0,
    5
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE (p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'qc'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 50);

-- ============================================
-- 6. УНИВЕРСАЛЬНЫЕ ОПЕРАЦИИ ДЛЯ ОСТАЛЬНЫХ ПРОДУКТОВ
-- ============================================

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    10,
    ps.id,
    'Заготовка',
    'Подготовка материалов',
    'Заготовительный участок',
    'Оборудование заготовительное',
    1.0,
    1
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE NOT (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%' OR p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%' OR p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'blank'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 10);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    20,
    ps.id,
    'Обработка',
    'Механическая обработка',
    'Механообрабатывающий участок',
    'Станок универсальный',
    2.0,
    2
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE NOT (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%' OR p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%' OR p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'machining'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 20);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    30,
    ps.id,
    'Сборка',
    'Сборка изделия',
    'Сборочный участок',
    'Верстак сборочный',
    1.5,
    3
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE NOT (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%' OR p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%' OR p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'assembly'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 30);

INSERT INTO route_card_operations (route_card_id, operation_number, stage_id, name, description, work_center, equipment, time_norm_hours, sort_order)
SELECT 
    rc.id,
    40,
    ps.id,
    'Контроль',
    'Контроль качества',
    'Контрольный участок',
    'Измерительный инструмент',
    0.5,
    4
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
CROSS JOIN production_stages ps
WHERE NOT (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%' OR p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%' OR p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND ps.code = 'qc'
  AND NOT EXISTS (SELECT 1 FROM route_card_operations rco WHERE rco.route_card_id = rc.id AND rco.operation_number = 40);

-- ============================================
-- 7. ОБНОВЛЕНИЕ ЗАДАНИЙ
-- ============================================

-- Обновляем route_card_id в существующих заданиях где он не указан
UPDATE production_tasks pt
JOIN products p ON p.id = pt.product_id
JOIN route_cards rc ON rc.product_id = p.id AND rc.is_active = 1
SET pt.route_card_id = rc.id
WHERE pt.route_card_id IS NULL;

-- ============================================
-- 8. ФИНАЛЬНАЯ СТАТИСТИКА
-- ============================================

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
WHERE pt.route_card_id IS NOT NULL
UNION ALL
SELECT 
    'Маршруты для двигателей',
    COUNT(DISTINCT rc.id)
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'AIR%' OR p.article LIKE '2AIR%' OR p.article LIKE 'АИР%' OR p.article LIKE 'АИВР%' OR p.name LIKE '%двигатель%')
  AND rc.is_active = 1
UNION ALL
SELECT 
    'Маршруты для литейных',
    COUNT(DISTINCT rc.id)
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'BC-%' OR p.article LIKE 'EKCH%' OR p.name LIKE '%коробк%' OR p.name LIKE '%корпус%' OR p.name LIKE '%литье%')
  AND rc.is_active = 1
UNION ALL
SELECT 
    'Маршруты для редукторов',
    COUNT(DISTINCT rc.id)
FROM route_cards rc
JOIN products p ON p.id = rc.product_id
WHERE (p.article LIKE 'GNOM%' OR p.name LIKE '%редуктор%')
  AND rc.is_active = 1;
