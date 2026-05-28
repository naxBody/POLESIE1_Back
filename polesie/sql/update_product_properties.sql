-- ============================================
-- ОБНОВЛЕНИЕ СВОЙСТВ ПРОДУКЦИИ
-- Все свойства на русском языке с правильной структурой для каждого вида продукции
-- ============================================

USE `polesie_production`;

-- ============================================
-- 1. ТАБЛИЦА ШАБЛОНОВ СВОЙСТВ ПО КАТЕГОРИЯМ
-- ============================================

DROP TABLE IF EXISTS `product_property_templates`;

CREATE TABLE `product_property_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `property_code` VARCHAR(50) NOT NULL,
  `property_name` VARCHAR(100) NOT NULL COMMENT 'Название свойства на русском',
  `property_type` ENUM('number', 'string', 'boolean', 'select', 'text') DEFAULT 'string',
  `unit` VARCHAR(20) COMMENT 'Единица измерения',
  `is_required` BOOLEAN DEFAULT FALSE,
  `sort_order` INT DEFAULT 0,
  `possible_values` JSON COMMENT 'Возможные значения для типа select',
  UNIQUE KEY `uk_category_property` (`category_id`, `property_code`),
  CONSTRAINT `fk_ppt_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Шаблоны свойств для категорий продукции';

-- ============================================
-- 2. ШАБЛОНЫ СВОЙСТВ ДЛЯ КАТЕГОРИЙ ПРОДУКЦИИ
-- ============================================

-- Общепромышленные электродвигатели (категория 2)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(2, 'мощность_квт', 'Мощность', 'number', 'кВт', TRUE, 1),
(2, 'обороты_мин', 'Частота вращения', 'number', 'об/мин', TRUE, 2),
(2, 'напряжение_в', 'Напряжение питания', 'number', 'В', TRUE, 3),
(2, 'габарит', 'Габарит двигателя', 'string', NULL, TRUE, 4),
(2, 'класс_эффективности', 'Класс энергоэффективности', 'select', NULL, TRUE, 5, '["IE1", "IE2", "IE3", "IE4"]'),
(2, 'монтаж', 'Исполнение по монтажу', 'select', NULL, TRUE, 6, '["IM1081", "IM1082", "IM1083", "IM2081", "IM3081"]'),
(2, 'степень_защиты', 'Степень защиты', 'select', NULL, FALSE, 7, '["IP44", "IP54", "IP55", "IP65"]'),
(2, 'климатическое_исполнение', 'Климатическое исполнение', 'select', NULL, FALSE, 8, '["У1", "У2", "У3", "Т2", "Т3", "ОМ2"]'),
(2, 'класс_изоляции', 'Класс изоляции', 'select', NULL, FALSE, 9, '["B", "F", "H"]'),
(2, 'коэффициент_мощности', 'Коэффициент мощности (cos φ)', 'number', NULL, FALSE, 10),
(2, 'кпд_проц', 'КПД', 'number', '%', FALSE, 11),
(2, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 12);

-- Взрывозащищенные двигатели (категория 3)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(3, 'мощность_квт', 'Мощность', 'number', 'кВт', TRUE, 1),
(3, 'обороты_мин', 'Частота вращения', 'number', 'об/мин', TRUE, 2),
(3, 'напряжение_в', 'Напряжение питания', 'number', 'В', TRUE, 3),
(3, 'габарит', 'Габарит двигателя', 'string', NULL, TRUE, 4),
(3, 'маркировка_взрывозащиты', 'Маркировка взрывозащиты', 'string', NULL, TRUE, 5),
(3, 'класс_эффективности', 'Класс энергоэффективности', 'select', NULL, TRUE, 6, '["IE1", "IE2", "IE3", "IE4"]'),
(3, 'уровень_взрывозащиты', 'Уровень взрывозащиты', 'select', NULL, TRUE, 7, '["1ExdIIBT4", "2ExdIIBT4", "1ExdIICT4", "ExnAIICT3"]'),
(3, 'монтаж', 'Исполнение по монтажу', 'select', NULL, FALSE, 8, '["IM1081", "IM1082", "IM1083"]'),
(3, 'степень_защиты', 'Степень защиты', 'select', NULL, FALSE, 9, '["IP54", "IP55", "IP65"]'),
(3, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 10);

-- Крановые двигатели (категория 4)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(4, 'мощность_квт', 'Мощность', 'number', 'кВт', TRUE, 1),
(4, 'обороты_мин', 'Частота вращения', 'number', 'об/мин', TRUE, 2),
(4, 'напряжение_в', 'Напряжение питания', 'number', 'В', TRUE, 3),
(4, 'габарит', 'Габарит двигателя', 'string', NULL, TRUE, 4),
(4, 'режим_работы', 'Режим работы', 'select', NULL, TRUE, 5, '["S3", "S4", "S5"]'),
(4, 'класс_изоляции', 'Класс изоляции', 'select', NULL, TRUE, 6, '["F", "H"]'),
(4, 'степень_защиты', 'Степень защиты', 'select', NULL, FALSE, 7, '["IP54", "IP55"]'),
(4, 'пв_проц', 'ПВ (продолжительность включения)', 'number', '%', FALSE, 8),
(4, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 9);

-- Дизельные генераторы (категория 6)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(6, 'мощность_основная_квт', 'Основная мощность', 'number', 'кВт', TRUE, 1),
(6, 'мощность_резервная_квт', 'Резервная мощность', 'number', 'кВт', FALSE, 2),
(6, 'тип_топлива', 'Тип топлива', 'select', NULL, TRUE, 3, '["дизель", "дизельное"]'),
(6, 'тип_запуска', 'Тип запуска', 'select', NULL, TRUE, 4, '["ручной", "электростартер", "автомат"]'),
(6, 'шум_дб', 'Уровень шума', 'number', 'дБ', FALSE, 5),
(6, 'фазы', 'Количество фаз', 'select', NULL, TRUE, 6, '["1", "3"]'),
(6, 'напряжение_в', 'Напряжение', 'number', 'В', FALSE, 7),
(6, 'частота_гц', 'Частота', 'number', 'Гц', FALSE, 8, '["50"]'),
(6, 'расход_топлива_л_ч', 'Расход топлива', 'number', 'л/ч', FALSE, 9),
(6, 'объем_бака_л', 'Объем топливного бака', 'number', 'л', FALSE, 10),
(6, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 11),
(6, 'исполнение', 'Исполнение', 'select', NULL, FALSE, 12, '["открытое", "в кожухе", "контейнер"]');

-- Бензиновые генераторы (категория 7)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(7, 'мощность_основная_квт', 'Основная мощность', 'number', 'кВт', TRUE, 1),
(7, 'мощность_максимальная_квт', 'Максимальная мощность', 'number', 'кВт', FALSE, 2),
(7, 'тип_топлива', 'Тип топлива', 'select', NULL, TRUE, 3, '["бензин", "бензиновый"]'),
(7, 'тип_запуска', 'Тип запуска', 'select', NULL, TRUE, 4, '["ручной", "электростартер"]'),
(7, 'шум_дб', 'Уровень шума', 'number', 'дБ', FALSE, 5),
(7, 'фазы', 'Количество фаз', 'select', NULL, TRUE, 6, '["1", "3"]'),
(7, 'напряжение_в', 'Напряжение', 'number', 'В', FALSE, 7),
(7, 'двигатель', 'Модель двигателя', 'string', NULL, FALSE, 8),
(7, 'объем_бака_л', 'Объем топливного бака', 'number', 'л', FALSE, 9),
(7, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 10);

-- Масляные трансформаторы (категория 9)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(9, 'мощность_ква', 'Номинальная мощность', 'number', 'кВА', TRUE, 1),
(9, 'напряжение_первичное_кв', 'Напряжение ВН', 'number', 'кВ', TRUE, 2),
(9, 'напряжение_вторичное_в', 'Напряжение НН', 'number', 'В', TRUE, 3),
(9, 'охлаждение', 'Тип охлаждения', 'select', NULL, TRUE, 4, '["oil", "масляное"]'),
(9, 'соединение', 'Схема соединения обмоток', 'select', NULL, TRUE, 5, '["Yyn0", "Dyn11", "Yzn11"]'),
(9, 'напряжение_кз_проц', 'Напряжение короткого замыкания', 'number', '%', FALSE, 6),
(9, 'потери_хх_вт', 'Потери холостого хода', 'number', 'Вт', FALSE, 7),
(9, 'потери_кз_вт', 'Потери короткого замыкания', 'number', 'Вт', FALSE, 8),
(9, 'ток_хх_проц', 'Ток холостого хода', 'number', '%', FALSE, 9),
(9, 'вес_масла_кг', 'Масса масла', 'number', 'кг', FALSE, 10),
(9, 'вес_трансформатора_кг', 'Масса трансформатора', 'number', 'кг', FALSE, 11);

-- Сухие трансформаторы (категория 10)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(10, 'мощность_ква', 'Номинальная мощность', 'number', 'кВА', TRUE, 1),
(10, 'напряжение_первичное_кв', 'Напряжение ВН', 'number', 'кВ', TRUE, 2),
(10, 'напряжение_вторичное_в', 'Напряжение НН', 'number', 'В', TRUE, 3),
(10, 'охлаждение', 'Тип охлаждения', 'select', NULL, TRUE, 4, '["air", "воздушное"]'),
(10, 'соединение', 'Схема соединения обмоток', 'string', NULL, FALSE, 5),
(10, 'класс_нагревостойкости', 'Класс нагревостойкости изоляции', 'select', NULL, FALSE, 6, '["F", "H"]'),
(10, 'степень_защиты', 'Степень защиты', 'select', NULL, FALSE, 7, '["IP00", "IP20", "IP23"]'),
(10, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 8);

-- Вводно-распределительные устройства (категория 12)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(12, 'ток_номинальный_а', 'Номинальный ток', 'number', 'А', TRUE, 1),
(12, 'напряжение_в', 'Напряжение', 'number', 'В', TRUE, 2),
(12, 'степень_защиты_ip', 'Степень защиты IP', 'select', NULL, TRUE, 3, '["IP31", "IP54", "IP65"]'),
(12, 'панели', 'Количество панелей', 'number', 'шт', FALSE, 4),
(12, 'тип_ввода', 'Тип ввода', 'select', NULL, FALSE, 5, '["кабельный", "шинный", "комбинированный"]'),
(12, 'число_вводов', 'Количество вводов', 'number', 'шт', FALSE, 6),
(12, 'число_отходящих', 'Количество отходящих линий', 'number', 'шт', FALSE, 7),
(12, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 8);

-- Щиты управления (категория 13)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(13, 'motors', 'Количество двигателей', 'number', 'шт', TRUE, 1),
(13, 'макс_ток_а', 'Максимальный ток', 'number', 'А', TRUE, 2),
(13, 'напряжение_в', 'Напряжение', 'number', 'В', TRUE, 3),
(13, 'степень_защиты_ip', 'Степень защиты IP', 'select', NULL, TRUE, 4, '["IP31", "IP54", "IP65"]'),
(13, 'тип_стартера', 'Тип пускателя', 'select', NULL, TRUE, 5, '["DOL", "звезда-треугольник", "VFD", "плавный пуск"]'),
(13, 'мощность_двигателя_квт', 'Мощность двигателя', 'number', 'кВт', FALSE, 6),
(13, 'автоматика', 'Наличие автоматики', 'boolean', NULL, FALSE, 7),
(13, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 8);

-- Щиты освещения (категория 14)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(14, 'группы', 'Количество групп', 'number', 'шт', TRUE, 1),
(14, 'макс_ток_а', 'Максимальный ток', 'number', 'А', TRUE, 2),
(14, 'напряжение_в', 'Напряжение', 'number', 'В', TRUE, 3),
(14, 'степень_защиты_ip', 'Степень защиты IP', 'select', NULL, TRUE, 4, '["IP31", "IP54", "IP65"]'),
(14, 'тип_автоматов', 'Тип автоматических выключателей', 'string', NULL, FALSE, 5),
(14, 'наличие_контактора', 'Наличие контактора', 'boolean', NULL, FALSE, 6),
(14, 'наличие_таймера', 'Наличие таймера', 'boolean', NULL, FALSE, 7),
(14, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 8);

-- Роторы (категория 16)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(16, 'артикул_двигателя', 'Артикул двигателя', 'string', NULL, TRUE, 1),
(16, 'диаметр_мм', 'Диаметр', 'number', 'мм', TRUE, 2),
(16, 'длина_мм', 'Длина', 'number', 'мм', TRUE, 3),
(16, 'диаметр_вала_мм', 'Диаметр вала', 'number', 'мм', TRUE, 4),
(16, 'тип_пазов', 'Тип пазов', 'string', NULL, FALSE, 5),
(16, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 6);

-- Статоры (категория 17)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(17, 'артикул_двигателя', 'Артикул двигателя', 'string', NULL, TRUE, 1),
(17, 'внешний_диаметр_мм', 'Внешний диаметр', 'number', 'мм', TRUE, 2),
(17, 'внутренний_диаметр_мм', 'Внутренний диаметр', 'number', 'мм', TRUE, 3),
(17, 'длина_мм', 'Длина', 'number', 'мм', TRUE, 4),
(17, 'пазы', 'Количество пазов', 'number', 'шт', TRUE, 5),
(17, 'диаметр_провода_мм', 'Диаметр провода', 'number', 'мм', FALSE, 6),
(17, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 7);

-- Подшипниковые щиты (категория 18)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(18, 'габарит_двигателя', 'Габарит двигателя', 'string', NULL, TRUE, 1),
(18, 'позиция', 'Позиция', 'select', NULL, TRUE, 2, '["front", "rear", "передний", "задний"]'),
(18, 'тип_подшипника', 'Тип подшипника', 'string', NULL, TRUE, 3),
(18, 'материал', 'Материал', 'string', NULL, FALSE, 4),
(18, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 5);

-- Клеммные коробки (категория 19)
INSERT INTO `product_property_templates` (`category_id`, `property_code`, `property_name`, `property_type`, `unit`, `is_required`, `sort_order`) VALUES
(19, 'габарит_двигателя', 'Габарит двигателя', 'string', NULL, TRUE, 1),
(19, 'клеммы', 'Количество клемм', 'number', 'шт', TRUE, 2),
(19, 'макс_ток_а', 'Максимальный ток', 'number', 'А', TRUE, 3),
(19, 'материал', 'Материал корпуса', 'string', NULL, FALSE, 4),
(19, 'степень_защиты_ip', 'Степень защиты IP', 'select', NULL, FALSE, 5, '["IP54", "IP55", "IP65"]'),
(19, 'вес_кг', 'Масса', 'number', 'кг', FALSE, 6);

-- ============================================
-- 3. ОБНОВЛЕНИЕ СУЩЕСТВУЮЩИХ ДАННЫХ ПРОДУКЦИИ
-- ============================================

-- Обновляем общепромышленные двигатели (категория 2) - добавляем недостающие русские свойства
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.степень_защиты', 'IP54',
    '$.климатическое_исполнение', 'У2',
    '$.класс_изоляции', 'F'
) WHERE `category_id` = 2;

-- Обновляем взрывозащищенные двигатели (категория 3)
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.степень_защиты', 'IP65',
    '$.климатическое_исполнение', 'Т2'
) WHERE `category_id` = 3;

-- Обновляем крановые двигатели (категория 4)
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.степень_защиты', 'IP54',
    '$.пв_проц', 40
) WHERE `category_id` = 4;

-- Обновляем дизельные генераторы (категория 6) - структурируем свойства
UPDATE `products` SET `specifications` = JSON_OBJECT(
    'мощность_основная_квт', JSON_EXTRACT(`specifications`, '$.мощность_квт'),
    'мощность_резервная_квт', JSON_EXTRACT(`specifications`, '$.мощность_квт') * 1.1,
    'тип_топлива', JSON_EXTRACT(`specifications`, '$.тип_топлива'),
    'тип_запуска', JSON_EXTRACT(`specifications`, '$.тип_запуска'),
    'шум_дб', JSON_EXTRACT(`specifications`, '$.шум_дб'),
    'фазы', JSON_EXTRACT(`specifications`, '$.фазы'),
    'напряжение_в', CASE WHEN JSON_EXTRACT(`specifications`, '$.фазы') = '3' THEN 380 ELSE 230 END,
    'частота_гц', 50,
    'расход_топлива_л_ч', CASE 
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_квт') <= 5 THEN 1.5
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_квт') <= 10 THEN 2.5
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_квт') <= 20 THEN 5.0
        ELSE 10.0
    END,
    'объем_бака_л', CASE 
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_квт') <= 5 THEN 15
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_квт') <= 15 THEN 30
        ELSE 50
    END,
    'исполнение', 'в кожухе'
) WHERE `category_id` = 6 AND JSON_EXTRACT(`specifications`, '$.мощность_основная_квт') IS NULL;

-- Обновляем бензиновые генераторы (категория 7)
UPDATE `products` SET `specifications` = JSON_OBJECT(
    'мощность_основная_квт', JSON_EXTRACT(`specifications`, '$.мощность_квт'),
    'мощность_максимальная_квт', JSON_EXTRACT(`specifications`, '$.мощность_квт') * 1.15,
    'тип_топлива', 'бензин',
    'тип_запуска', JSON_EXTRACT(`specifications`, '$.тип_запуска'),
    'шум_дб', JSON_EXTRACT(`specifications`, '$.шум_дб'),
    'фазы', JSON_EXTRACT(`specifications`, '$.фазы'),
    'напряжение_в', CASE WHEN JSON_EXTRACT(`specifications`, '$.фазы') = '3' THEN 380 ELSE 230 END,
    'объем_бака_л', CASE 
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_квт') <= 3 THEN 12
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_квт') <= 6 THEN 25
        ELSE 30
    END
) WHERE `category_id` = 7 AND JSON_EXTRACT(`specifications`, '$.мощность_основная_квт') IS NULL;

-- Обновляем масляные трансформаторы (категория 9)
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.напряжение_кз_проц', 4.5,
    '$.потери_хх_вт', CASE 
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_ква') <= 40 THEN 180
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_ква') <= 100 THEN 350
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_ква') <= 250 THEN 750
        ELSE 1500
    END,
    '$.потери_кз_вт', CASE 
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_ква') <= 40 THEN 1100
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_ква') <= 100 THEN 2000
        WHEN JSON_EXTRACT(`specifications`, '$.мощность_ква') <= 250 THEN 4500
        ELSE 8500
    END,
    '$.ток_хх_проц', 1.2
) WHERE `category_id` = 9;

-- Обновляем сухие трансформаторы (категория 10)
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.класс_нагревостойкости', 'F',
    '$.степень_защиты', 'IP20'
) WHERE `category_id` = 10;

-- Обновляем ВРУ (категория 12)
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.тип_ввода', 'кабельный',
    '$.число_вводов', 1,
    '$.число_отходящих', `specifications`->>'$.панели' * 2
) WHERE `category_id` = 12;

-- Обновляем щиты управления (категория 13)
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.автоматика', TRUE
) WHERE `category_id` = 13;

-- Обновляем щиты освещения (категория 14)
UPDATE `products` SET `specifications` = JSON_SET(
    `specifications`,
    '$.тип_автоматов', 'автоматические выключатели',
    '$.наличие_контактора', TRUE,
    '$.наличие_таймера', FALSE
) WHERE `category_id` = 14;

-- ============================================
-- 4. ПРЕДСТАВЛЕНИЕ ДЛЯ ПРОСМОТРА СВОЙСТВ ПРОДУКЦИИ
-- ============================================

DROP VIEW IF EXISTS `v_product_properties`;

CREATE VIEW `v_product_properties` AS
SELECT 
    p.id,
    p.article,
    p.name,
    pc.name_ru AS category_name,
    p.specifications,
    ppt.property_code,
    ppt.property_name,
    ppt.property_type,
    ppt.unit,
    CASE 
        WHEN ppt.property_type = 'number' THEN JSON_EXTRACT(p.specifications, CONCAT('$.', ppt.property_code))
        ELSE JSON_UNQUOTE(JSON_EXTRACT(p.specifications, CONCAT('$.', ppt.property_code)))
    END AS property_value
FROM products p
JOIN product_categories pc ON p.category_id = pc.id
LEFT JOIN product_property_templates ppt ON p.category_id = ppt.category_id
ORDER BY p.id, ppt.sort_order;

-- ============================================
-- 5. ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ СВОЙСТВ ПРОДУКТА
-- ============================================

DROP FUNCTION IF EXISTS `get_product_property`;

DELIMITER $$

CREATE FUNCTION `get_product_property`(
    product_id INT,
    property_code VARCHAR(50)
) RETURNS VARCHAR(255)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE prop_value VARCHAR(255);
    
    SELECT 
        CASE 
            WHEN ppt.property_type = 'number' THEN CAST(JSON_EXTRACT(p.specifications, CONCAT('$.', property_code)) AS CHAR)
            ELSE JSON_UNQUOTE(JSON_EXTRACT(p.specifications, CONCAT('$.', property_code)))
        END
    INTO prop_value
    FROM products p
    LEFT JOIN product_property_templates ppt ON p.category_id = ppt.category_id AND ppt.property_code = property_code
    WHERE p.id = product_id;
    
    RETURN prop_value;
END$$

DELIMITER ;

-- ============================================
-- 6. ПРИМЕРЫ ЗАПРОСОВ
-- ============================================

-- Получить все свойства для конкретного продукта
-- SELECT * FROM v_product_properties WHERE id = 1;

-- Получить продукты с определенной мощностью
-- SELECT * FROM v_product_properties WHERE property_code = 'мощность_квт' AND CAST(property_value AS DECIMAL) > 5;

-- Получить шаблон свойств для категории
-- SELECT * FROM product_property_templates WHERE category_id = 2 ORDER BY sort_order;
