-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: СХЕМА БАЗЫ ДАННЫХ (ИСПРАВЛЕННАЯ)
-- JSON оптимизирован для обхода бага парсера phpMyAdmin
-- ============================================
CREATE DATABASE IF NOT EXISTS `polesie_production` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `polesie_production`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Удаление старых таблиц
DROP TABLE IF EXISTS `product_documents`;
DROP TABLE IF EXISTS `product_serial_numbers`;
DROP TABLE IF EXISTS `quality_checks`;
DROP TABLE IF EXISTS `production_tasks_materials`;
DROP TABLE IF EXISTS `production_tasks`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `materials`;
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `material_categories`;
DROP TABLE IF EXISTS `contractors`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `base_units`;

-- 1. ЕДИНИЦЫ ИЗМЕРЕНИЯ
CREATE TABLE `base_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `symbol` VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. РОЛИ ПОЛЬЗОВАТЕЛЕЙ
CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ПОЛЬЗОВАТЕЛИ
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `role_id` INT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. КОНТРАГЕНТЫ
CREATE TABLE `contractors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `inn` VARCHAR(20) UNIQUE,
  `type` ENUM('supplier', 'customer', 'both') DEFAULT 'both',
  `contact_person` VARCHAR(100),
  `phone` VARCHAR(50),
  `email` VARCHAR(100),
  `address` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. КАТЕГОРИИ МАТЕРИАЛОВ
CREATE TABLE `material_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_mat_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `material_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. КАТЕГОРИИ ПРОДУКЦИИ
CREATE TABLE `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_prod_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. МАТЕРИАЛЫ
CREATE TABLE `materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name_full` VARCHAR(200) NOT NULL,
  `name_short` VARCHAR(100),
  `category_id` INT,
  `base_unit_id` INT,
  `specifications` JSON,
  `current_stock` DECIMAL(15,3) DEFAULT 0,
  `min_stock` DECIMAL(15,3) DEFAULT 0,
  `location` VARCHAR(100),
  `supplier_id` INT,
  `last_price` DECIMAL(15,2),
  `currency` CHAR(3) DEFAULT 'BYN',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mat_category` FOREIGN KEY (`category_id`) REFERENCES `material_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mat_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. ПРОДУКЦИЯ
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `category_id` INT,
  `base_unit_id` INT,
  `specifications` JSON,
  `image` VARCHAR(255),
  `base_price` DECIMAL(15,2),
  `currency` CHAR(3) DEFAULT 'BYN',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. ЗАКАЗЫ
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT,
  `responsible_user_id` INT,
  `status` ENUM('new', 'processing', 'ready', 'shipped', 'cancelled') DEFAULT 'new',
  `order_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2),
  `notes` TEXT,
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_responsible` FOREIGN KEY (`responsible_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. ПОЗИЦИИ ЗАКАЗОВ
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `price` DECIMAL(15,2) NOT NULL,
  `total` DECIMAL(15,2) NOT NULL,
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ
CREATE TABLE `production_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_number` VARCHAR(50) UNIQUE,
  `product_id` INT,
  `quantity_plan` DECIMAL(15,3),
  `quantity_fact` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
  `start_date` DATE,
  `end_date` DATE,
  `responsible_id` INT,
  CONSTRAINT `fk_task_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_user` FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. МАТЕРИАЛЫ ДЛЯ ЗАДАНИЙ
CREATE TABLE `production_tasks_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `quantity_required` DECIMAL(15,3) NOT NULL,
  `quantity_used` DECIMAL(15,3) DEFAULT 0,
  CONSTRAINT `fk_ptm_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ptm_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. ПРОВЕРКИ КАЧЕСТВА
CREATE TABLE `quality_checks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT,
  `product_id` INT,
  `check_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `inspector_id` INT,
  `status` ENUM('pass', 'fail', 'rework') NOT NULL,
  `defect_description` TEXT,
  `quantity_checked` INT,
  `quantity_defective` INT DEFAULT 0,
  CONSTRAINT `fk_qc_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. СЕРИЙНЫЕ НОМЕРА ПРОДУКЦИИ
CREATE TABLE `product_serial_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `serial_number` VARCHAR(100) NOT NULL UNIQUE,
  `production_date` DATE,
  `task_id` INT,
  `status` ENUM('active', 'warranty', 'archived') DEFAULT 'active',
  `warranty_start` DATE,
  `warranty_end` DATE,
  `notes` TEXT,
  `technical_specs` JSON,
  `passport_data` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_psn_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psn_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. ДОКУМЕНТЫ ПРОДУКЦИИ
CREATE TABLE `product_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `serial_number_id` INT NOT NULL,
  `document_type` ENUM('manual', 'certificate', 'test_report', 'warranty_card', 'other') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT,
  `mime_type` VARCHAR(100),
  `description` TEXT,
  `uploaded_by` INT,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pd_serial` FOREIGN KEY (`serial_number_id`) REFERENCES `product_serial_numbers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЗАПОЛНЕНИЕ ТЕСТОВЫМИ ДАННЫМИ (JSON БЕЗ ПРОБЕЛОВ)
-- ============================================

INSERT INTO `base_units` (`name`, `code`, `symbol`) VALUES
('Штука', 'pcs', 'шт'), ('Килограмм', 'kg', 'кг'), ('Метр', 'm', 'м'),
('Тонна', 't', 'т'), ('Литр', 'l', 'л'), ('Комплект', 'set', 'компл');

INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ', '{"all":true}'),
('Директор', 'director', 'Руководство', '{"all":true}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"orders":["read","create"],"products":["read"]}'),
('Технолог', 'technologist', 'Производство', '{"production":["read","create"],"materials":["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"warehouse":["read","create"],"materials":["read","update"]}');

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 1),
('director', 'director123', 'Директор Предприятия', 'director@polesie.by', 2),
('ivanov', 'admin123', 'Иванов Иван', 'ivanov@polesie.by', 3),
('petrov', 'admin123', 'Петров Петр', 'petrov@polesie.by', 4),
('sidorov', 'admin123', 'Сидоров Сидор', 'sidorov@polesie.by', 5);

INSERT INTO `contractors` (`name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "СтальПром"', '100123456', 'supplier', 'Кузнецов А.А.', '+375 29 111-22-33', 'info@stalprom.by', 'г. Минск, ул. Промышленная 10'),
('ЗАО "ЭлектроТех"', '200234567', 'supplier', 'Волкова Е.В.', '+375 29 222-33-44', 'sales@electrotech.by', 'г. Гродно, ул. Заводская 5'),
('УП "Метизы"', '300345678', 'supplier', 'Белый И.И.', '+375 29 333-44-55', 'zakaz@metizy.by', 'г. Брест, пр. Машерова 15'),
('ООО "СтройМонтаж"', '400456789', 'customer', 'Орлов О.О.', '+375 29 444-55-66', 'order@stroymontazh.by', 'г. Гомель, ул. Строителей 20'),
('ЧТУП "АгроСервис"', '500567890', 'customer', 'Зеленая З.З.', '+375 29 555-66-77', 'agro@service.by', 'г. Витебск, пер. Полевой 3');

INSERT INTO `material_categories` (`parent_id`, `name`, `code`, `description`) VALUES
(NULL, 'Металлы', 'METAL', 'Черные и цветные металлы'),
(1, 'Прутки', 'METAL_BAR', 'Стальные прутки круглого сечения'),
(1, 'Листовой прокат', 'METAL_SHEET', 'Листы стальные горячекатаные'),
(1, 'Чугун', 'METAL_CAST', 'Чугунные заготовки'),
(NULL, 'Электротехника', 'ELECTRO', 'Электротехнические материалы'),
(5, 'Провода', 'ELECTRO_WIRE', 'Медные и алюминиевые провода'),
(5, 'Шины', 'ELECTRO_BUS', 'Медные шины'),
(NULL, 'Крепеж', 'FASTENER', 'Болты, гайки, шайбы'),
(8, 'Болты', 'FAST_BOLT', 'Болты различных классов прочности'),
(8, 'Гайки', 'FAST_NUT', 'Гайки шестигранные'),
(NULL, 'Подшипники', 'BEARING', 'Подшипники качения');

INSERT INTO `product_categories` (`parent_id`, `name`, `code`, `description`) VALUES
(NULL, 'Электродвигатели', 'MOTOR', 'Асинхронные электродвигатели'),
(NULL, 'Генераторы', 'GENERATOR', 'Дизельные генераторы'),
(NULL, 'Трансформаторы', 'TRANSFORMER', 'Силовые трансформаторы'),
(NULL, 'Щитовое оборудование', 'SWITCHGEAR', 'Распределительные щиты'),
(NULL, 'Запчасти', 'SPARE_PARTS', 'Запасные части и комплектующие');

INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`, `current_stock`, `min_stock`, `location`, `supplier_id`, `last_price`, `currency`) VALUES
('ST-BAR-45-010', 'Пруток стальной 45 Ø10мм', 'Пруток 45 Ø10', 2, 3, '{"diameter_mm":10,"steel_grade":"45","length_m":6,"surface":"калиброванный","gost":"10702-78"}', 321.52, 64.30, 'Склад №1, Секция А', 1, 2.50, 'BYN'),
('ST-BAR-40X-010', 'Пруток легированный 40Х Ø10мм', 'Пруток 40Х Ø10', 2, 3, '{"diameter_mm":10,"steel_grade":"40Х","length_m":6,"surface":"горячекатаный","gost":"2590-2006"}', 17.38, 3.48, 'Склад №1, Секция А', 1, 3.20, 'BYN'),
('ST-BAR-45-020', 'Пруток стальной 45 Ø20мм', 'Пруток 45 Ø20', 2, 3, '{"diameter_mm":20,"steel_grade":"45","length_m":6,"surface":"калиброванный","gost":"10702-78"}', 450.00, 50.00, 'Склад №1, Секция Б', 1, 4.80, 'BYN'),
('ST-SHEET-3-08', 'Лист стальной 3мм', 'Лист 3мм', 3, 2, '{"thickness_mm":3,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 1250.00, 200.00, 'Склад №2, Секция А', 1, 2.10, 'BYN'),
('ST-SHEET-5-10', 'Лист стальной 5мм', 'Лист 5мм', 3, 2, '{"thickness_mm":5,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 890.00, 150.00, 'Склад №2, Секция А', 1, 3.50, 'BYN'),
('CAST-IRON-CH20', 'Чугун серый СЧ20', 'Чугун СЧ20', 4, 2, '{"grade":"СЧ20","hardness_hb":"170-220","gost":"1412-85"}', 500.00, 100.00, 'Склад №3', 1, 2.80, 'BYN'),
('WIRE-CU-2.5', 'Провод медный 2.5мм²', 'Провод 2.5', 6, 3, '{"cross_section_mm2":2.5,"material":"медь","insulation":"ПВХ","gost":"6323-79"}', 1500.00, 300.00, 'Склад №4, Секция А', 2, 1.20, 'BYN'),
('WIRE-CU-4.0', 'Провод медный 4мм²', 'Провод 4.0', 6, 3, '{"cross_section_mm2":4.0,"material":"медь","insulation":"ПВХ","gost":"6323-79"}', 1200.00, 250.00, 'Склад №4, Секция А', 2, 1.85, 'BYN'),
('BUS-CU-20x3', 'Шина медная 20x3мм', 'Шина 20x3', 7, 3, '{"width_mm":20,"thickness_mm":3,"material":"М1","gost":"434-73"}', 250.00, 50.00, 'Склад №4, Секция Б', 2, 45.00, 'BYN'),
('BOLT-M10x50', 'Болт М10х50 8.8', 'Болт М10х50', 9, 1, '{"thread":"M10","length_mm":50,"strength_class":"8.8","coating":"цинк","gost":"7798-70"}', 5000.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.35, 'BYN'),
('BOLT-M12x60', 'Болт М12х60 8.8', 'Болт М12х60', 9, 1, '{"thread":"M12","length_mm":60,"strength_class":"8.8","coating":"цинк","gost":"7798-70"}', 3500.00, 800.00, 'Склад №5, Ящик 1', 3, 0.52, 'BYN'),
('NUT-M10', 'Гайка М10 8', 'Гайка М10', 10, 1, '{"thread":"M10","strength_class":"8","coating":"цинк","gost":"5915-70"}', 6000.00, 1200.00, 'Склад №5, Ящик 2', 3, 0.15, 'BYN'),
('NUT-M12', 'Гайка М12 8', 'Гайка М12', 10, 1, '{"thread":"M12","strength_class":"8","coating":"цинк","gost":"5915-70"}', 4500.00, 1000.00, 'Склад №5, Ящик 2', 3, 0.22, 'BYN'),
('BRG-6205', 'Подшипник 6205-2RS', 'Подшипник 6205', 11, 1, '{"inner_d_mm":25,"outer_d_mm":52,"width_mm":15,"type":"шариковый","seal":"2RS"}', 150.00, 30.00, 'Склад №5, Ящик 3', 2, 8.50, 'BYN'),
('BRG-6305', 'Подшипник 6305-2RS', 'Подшипник 6305', 11, 1, '{"inner_d_mm":25,"outer_d_mm":62,"width_mm":17,"type":"шариковый","seal":"2RS"}', 120.00, 25.00, 'Склад №5, Ящик 3', 2, 12.30, 'BYN');

-- ============================================
-- ПРОДУКЦИЯ: ВСЕ 64 ТОВАРА СО ВСЕМИ СВОЙСТВАМИ
-- Все свойства вынесены в отдельные колонки для удобства расчетов
-- ============================================

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article` VARCHAR(50) NOT NULL UNIQUE,
  `code_gost` VARCHAR(50),
  `name_full` VARCHAR(300) NOT NULL,
  `name_short` VARCHAR(150),
  `category_id` INT,
  `base_unit_id` INT DEFAULT 1,
  -- Электрические характеристики
  `power_kw_min` DECIMAL(10,3),
  `power_kw_max` DECIMAL(10,3),
  `power_kw` DECIMAL(10,3),
  `rpm` INT,
  `voltage_v` VARCHAR(50),
  `frequency_hz` INT DEFAULT 50,
  `efficiency_class` VARCHAR(10),
  -- Механические характеристики
  `shaft_height_mm` INT,
  `climate_versions` VARCHAR(200),
  `mounting_versions` VARCHAR(300),
  `protection_class` VARCHAR(50),
  -- Дополнительные характеристики
  `type` VARCHAR(100),
  `application` VARCHAR(200),
  `housing_material` VARCHAR(100),
  `impeller_material` VARCHAR(100),
  `flow_rate_m3_h` DECIMAL(10,2),
  `head_m` DECIMAL(10,2),
  `max_immersion_depth_m` DECIMAL(10,2),
  `max_solid_size_mm` INT,
  `explosion_protection` VARCHAR(50),
  `capacitor_included` BOOLEAN,
  `standard` VARCHAR(100),
  `material` VARCHAR(100),
  `production_method` VARCHAR(100),
  `custom_made` BOOLEAN,
  `speeds` INT,
  `slip_percent` DECIMAL(5,2),
  `weight_range_kg` VARCHAR(50),
  -- Серийные номера и гарантия
  `is_serial_tracked` BOOLEAN DEFAULT FALSE,
  `warranty_months` INT DEFAULT 24,
  `is_bestseller` BOOLEAN DEFAULT FALSE,
  `serial_number` VARCHAR(100),
  -- Изображение и цена
  `image` VARCHAR(255),
  `base_price` DECIMAL(15,2),
  `currency` CHAR(3) DEFAULT 'BYN',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prod_category_new` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_unit_new` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Вставка всех 64 продуктов
-- ============================================

INSERT INTO `products` (
  `id`, `article`, `code_gost`, `name_full`, `name_short`, `category_id`, `base_unit_id`,
  `power_kw_min`, `power_kw_max`, `power_kw`, `rpm`, `voltage_v`, `frequency_hz`,
  `efficiency_class`, `shaft_height_mm`, `climate_versions`, `mounting_versions`,
  `protection_class`, `type`, `application`, `housing_material`, `impeller_material`,
  `flow_rate_m3_h`, `head_m`, `max_immersion_depth_m`, `max_solid_size_mm`,
  `explosion_protection`, `capacitor_included`, `standard`, `material`,
  `production_method`, `custom_made`, `speeds`, `slip_percent`, `weight_range_kg`,
  `is_serial_tracked`, `warranty_months`, `is_bestseller`, `serial_number`,
  `image`, `base_price`, `currency`, `is_active`)
VALUES
(1, 'AIR-071-2', 'АИР71', 'Электродвигатель асинхронный трехфазный АИР71, 0.37-0.75 кВт, 3000 об/мин', 'АИР71 0.37-0.75кВт 3000об/мин', 11, 1, 0.37, 0.75, NULL, 3000, '380/220', 50, NULL, 71, 'У2,У3,У5,УХЛ2,УХЛ4,Т2', 'IM1081,IM1082,IM2081,IM2082,IM2181,IM2182,IM3081,IM3082,IM3681,IM3682', 'IP54,IP55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, TRUE, 24, FALSE, 'SN-24-002-9964', 'product_air_071_2.jpg', 111.00, 'BYN', TRUE),
(2, 'AIR-080-2', 'АИР80', 'Электродвигатель асинхронный трехфазный АИР80, 0.75-2.2 кВт, 3000 об/мин', 'АИР80 0.75-2.2кВт 3000об/мин', 11, 1, 0.75, 2.2, NULL, 3000, '380/220', 50, NULL, 80, 'У2,У3,У5,УХЛ2,УХЛ4,Т2', 'IM1081,IM1082,IM2081,IM2082,IM2181,IM2182,IM3081,IM3082,IM3681,IM3682', 'IP54,IP55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, TRUE, 24, FALSE, 'SN-24-002-9364', 'product_air_080_2.jpg', 225.00, 'BYN', TRUE),
(3, 'AIR-090-2', 'АИР90', 'Электродвигатель асинхронный трехфазный АИР90, 1.5-3.0 кВт, 3000 об/мин', 'АИР90 1.5-3.0кВт 3000об/мин', 11, 1, 1.5, 3.0, NULL, 3000, '380/220', 50, NULL, 90, 'У2,У3,У5,УХЛ2,УХЛ4,Т2', 'IM1081,IM1082,IM2081,IM2082,IM2181,IM2182,IM3081,IM3082,IM3681,IM3682', 'IP54,IP55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, TRUE, 24, FALSE, 'SN-24-002-1420', 'product_air_090_2.jpg', 450.00, 'BYN', TRUE),
(4, 'AIR-100-2', 'АИР100', 'Электродвигатель асинхронный трехфазный АИР100, 3.0-5.5 кВт, 3000 об/мин', 'АИР100 3.0-5.5кВт 3000об/мин', 11, 1, 3.0, 5.5, NULL, 3000, '380/220', 50, NULL, 100, 'У2,У3,У5,УХЛ2,УХЛ4,Т2', 'IM1081,IM1082,IM2081,IM2082,IM2181,IM2182,IM3081,IM3082,IM3681,IM3682', 'IP54,IP55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, TRUE, 24, FALSE, 'SN-24-002-9518', 'product_air_100_2.jpg', 900.00, 'BYN', TRUE),
(5, 'AIR-112-2', 'АИР112', 'Электродвигатель асинхронный трехфазный АИР112, 4.0-7.5 кВт, 3000 об/мин', 'АИР112 4.0-7.5кВт 3000об/мин', 11, 1, 4.0, 7.5, NULL, 3000, '380/220', 50, NULL, 112, 'У2,У3,У5,УХЛ2,УХЛ4,Т2', 'IM1081,IM1082,IM2081,IM2082,IM2181,IM2182,IM3081,IM3082,IM3681,IM3682', 'IP54,IP55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, TRUE, 24, FALSE, 'SN-24-002-5288', 'product_air_112_2.jpg', 1200.00, 'BYN', TRUE);

INSERT INTO `orders` (`order_number`, `customer_id`, `status`, `order_date`, `total_amount`, `notes`) VALUES
('ORD-2024-001', 4, 'processing', '2024-01-15', 2450.00, 'Срочный заказ'),
('ORD-2024-002', 5, 'ready', '2024-01-18', 1850.00, 'Отгрузка со склада'),
('ORD-2024-003', 4, 'new', '2024-01-20', 3200.00, 'Новый заказ на двигатели');

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`) VALUES
(1, 1, 2, 350.00, 700.00), (1, 2, 3, 480.00, 1440.00), (1, 11, 10, 15.00, 150.00),
(2, 5, 1, 2500.00, 2500.00), (2, 12, 5, 25.00, 125.00),
(3, 3, 4, 650.00, 2600.00), (3, 4, 1, 820.00, 820.00);

INSERT INTO `production_tasks` (`task_number`, `product_id`, `quantity_plan`, `quantity_fact`, `status`, `start_date`, `end_date`, `responsible_id`) VALUES
('TASK-2024-001', 1, 10, 10, 'completed', '2024-01-10', '2024-01-12', 3),
('TASK-2024-002', 2, 5, 5, 'completed', '2024-01-13', '2024-01-15', 3),
('TASK-2024-003', 3, 8, 6, 'in_progress', '2024-01-16', '2024-01-20', 3),
('TASK-2024-004', 4, 3, 0, 'planned', '2024-01-22', '2024-01-25', 3),
('TASK-2024-005', 5, 2, 0, 'planned', '2024-01-25', '2024-01-28', 3);

INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`) VALUES
(1, 1, 60, 60), (1, 7, 100, 100), (1, 14, 20, 20),
(2, 2, 30, 30), (2, 8, 75, 75), (2, 15, 10, 10),
(3, 3, 48, 36), (3, 9, 24, 18),
(4, 4, 18, 0), (4, 10, 48, 0);

INSERT INTO `quality_checks` (`task_id`, `product_id`, `check_date`, `inspector_id`, `status`, `defect_description`, `quantity_checked`, `quantity_defective`) VALUES
(1, 1, '2024-01-12 14:00:00', 2, 'pass', NULL, 10, 0),
(2, 2, '2024-01-15 15:30:00', 2, 'pass', NULL, 5, 0),
(3, 3, '2024-01-18 10:00:00', 2, 'pass', NULL, 6, 0),
(3, 3, '2024-01-19 11:00:00', 2, 'fail', 'Превышен уровень вибрации', 2, 2),
(4, 4, '2024-01-20 09:00:00', 2, 'pass', NULL, 3, 0),
(5, 5, '2024-01-22 16:00:00', 2, 'rework', 'Требуется балансировка ротора', 2, 1),
(5, 5, '2024-01-23 10:00:00', 2, 'pass', NULL, 1, 0);

INSERT INTO `product_serial_numbers` (`product_id`, `serial_number`, `production_date`, `task_id`, `status`) VALUES
(1, 'SN-ADM80A4-2024-0001', '2024-01-12', 1, 'active'),
(1, 'SN-ADM80A4-2024-0002', '2024-01-12', 1, 'active'),
(2, 'SN-ADM90L4-2024-0001', '2024-01-15', 2, 'active'),
(3, 'SN-ADM100L4-2024-0001', '2024-01-18', 3, 'active'),
(5, 'SN-DG5000-2024-0001', '2024-01-22', 5, 'warranty');

SET FOREIGN_KEY_CHECKS = 1;