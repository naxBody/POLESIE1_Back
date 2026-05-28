-- ============================================
-- ПОЛЕСЬЕЭЛЕКТРОМАШ: ПОЛНАЯ НОМЕНКЛАТУРА ПРОДУКЦИИ
-- Источник: официальный каталог предприятия
-- ============================================
CREATE DATABASE IF NOT EXISTS `polesie_production` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `polesie_production`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Удаление старых таблиц
DROP TABLE IF EXISTS `product_documents`;
DROP TABLE IF EXISTS `product_serial_numbers`;
DROP TABLE IF EXISTS `quality_checks`;
DROP TABLE IF EXISTS `production_task_stages`;
DROP TABLE IF EXISTS `production_tasks_materials`;
DROP TABLE IF EXISTS `production_tasks`;
DROP TABLE IF EXISTS `route_card_operations`;
DROP TABLE IF EXISTS `route_cards`;
DROP TABLE IF EXISTS `production_stages`;
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
  `name_ru` VARCHAR(100),
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_mat_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `material_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. КАТЕГОРИИ ПРОДУКЦИИ
CREATE TABLE `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `name_ru` VARCHAR(100),
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
  `delivery_date` DATE,
  `total_amount` DECIMAL(15,2),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  `production_status` ENUM('not_started', 'in_progress', 'completed', 'packed') DEFAULT 'not_started',
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. ЭТАПЫ ПРОИЗВОДСТВА (справочник)
CREATE TABLE `production_stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  `sort_order` INT DEFAULT 0,
  `color` VARCHAR(20) DEFAULT '#3498db',
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. МАРШРУТНЫЕ КАРТЫ (техпроцессы для продукции)
CREATE TABLE `route_cards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `version` VARCHAR(20) DEFAULT '1.0',
  `description` TEXT,
  `total_time_hours` DECIMAL(10,2) DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_route_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. ОПЕРАЦИИ МАРШРУТНОЙ КАРТЫ
CREATE TABLE `route_card_operations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `route_card_id` INT NOT NULL,
  `operation_number` INT NOT NULL,
  `stage_id` INT,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `work_center` VARCHAR(100),
  `equipment` VARCHAR(200),
  `time_norm_hours` DECIMAL(10,2) DEFAULT 0,
  `materials_required` JSON COMMENT 'Требуемые материалы для операции',
  `instructions` TEXT COMMENT 'Инструкции для оператора',
  `sort_order` INT DEFAULT 0,
  CONSTRAINT `fk_op_route` FOREIGN KEY (`route_card_id`) REFERENCES `route_cards`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_op_stage` FOREIGN KEY (`stage_id`) REFERENCES `production_stages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ
CREATE TABLE `production_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_number` VARCHAR(50) UNIQUE,
  `order_id` INT,
  `order_item_id` INT,
  `product_id` INT NOT NULL,
  `route_card_id` INT,
  `quantity_plan` DECIMAL(15,3) NOT NULL,
  `quantity_fact` DECIMAL(15,3) DEFAULT 0,
  `quantity_good` DECIMAL(15,3) DEFAULT 0,
  `quantity_defect` DECIMAL(15,3) DEFAULT 0,
  `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
  `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
  `start_date` DATE,
  `end_date` DATE,
  `planned_start` DATETIME,
  `planned_end` DATETIME,
  `actual_start` DATETIME,
  `actual_end` DATETIME,
  `responsible_id` INT,
  `worker_id` INT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_task_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_route` FOREIGN KEY (`route_card_id`) REFERENCES `route_cards`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_worker` FOREIGN KEY (`worker_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. ЭТАПЫ ВЫПОЛНЕНИЯ ЗАДАНИЯ (отслеживание прогресса)
CREATE TABLE `production_task_stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `stage_id` INT NOT NULL,
  `operation_id` INT,
  `status` ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `worker_id` INT,
  `time_spent_hours` DECIMAL(10,2) DEFAULT 0,
  `quantity_passed` DECIMAL(15,3) DEFAULT 0,
  `quantity_rejected` DECIMAL(15,3) DEFAULT 0,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pts_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pts_stage` FOREIGN KEY (`stage_id`) REFERENCES `production_stages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pts_operation` FOREIGN KEY (`operation_id`) REFERENCES `route_card_operations`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pts_worker` FOREIGN KEY (`worker_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. МАТЕРИАЛЫ ДЛЯ ЗАДАНИЙ
CREATE TABLE `production_tasks_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `quantity_required` DECIMAL(15,3) NOT NULL,
  `quantity_reserved` DECIMAL(15,3) DEFAULT 0,
  `quantity_used` DECIMAL(15,3) DEFAULT 0,
  `unit_cost` DECIMAL(15,2),
  `total_cost` DECIMAL(15,2),
  `status` ENUM('pending', 'reserved', 'issued', 'consumed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ptm_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ptm_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. ПРОВЕРКИ КАЧЕСТВА
CREATE TABLE `quality_checks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT,
  `task_stage_id` INT,
  `product_id` INT,
  `check_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `inspector_id` INT,
  `status` ENUM('pass', 'fail', 'rework') NOT NULL,
  `defect_description` TEXT,
  `quantity_checked` INT,
  `quantity_defective` INT DEFAULT 0,
  `photos` JSON,
  CONSTRAINT `fk_qc_task` FOREIGN KEY (`task_id`) REFERENCES `production_tasks`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_stage` FOREIGN KEY (`task_stage_id`) REFERENCES `production_task_stages`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qc_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. СЕРИЙНЫЕ НОМЕРА ПРОДУКЦИИ
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

-- 19. ДОКУМЕНТЫ ПРОДУКЦИИ
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
-- ЗАПОЛНЕНИЕ БАЗОВЫМИ ДАННЫМИ
-- ============================================

INSERT INTO `base_units` (`name`, `code`, `symbol`) VALUES
('Штука', 'pcs', 'шт'), ('Килограмм', 'kg', 'кг'), ('Метр', 'm', 'м'),
('Тонна', 't', 'т'), ('Литр', 'l', 'л'), ('Комплект', 'set', 'компл');

INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ', '{"все":true}'),
('Директор', 'director', 'Руководство', '{"все":true}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"заказы":["read","create"],"продукция":["read"]}'),
('Технолог', 'technologist', 'Производство', '{"производство":["read","create"],"материалы":["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"склад":["read","create"],"материалы":["read","update"]}'),
('Рабочий', 'worker', 'Исполнитель', '{"производство":["read"],"задания":["update"]}');

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 1),
('director', 'director123', 'Директор Предприятия', 'director@polesie.by', 2),
('ivanov', 'admin123', 'Иванов Иван', 'ivanov@polesie.by', 3),
('petrov', 'admin123', 'Петров Петр', 'petrov@polesie.by', 4),
('sidorov', 'admin123', 'Сидоров Сидор', 'sidorov@polesie.by', 5),
('worker1', 'admin123', 'Рабочий Алексей', 'worker1@polesie.by', 6);

INSERT INTO `contractors` (`name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "СтальПром"', '100123456', 'supplier', 'Кузнецов А.А.', '+375 29 111-22-33', 'info@stalprom.by', 'г. Минск, ул. Промышленная 10'),
('ЗАО "ЭлектроТех"', '200234567', 'supplier', 'Волкова Е.В.', '+375 29 222-33-44', 'sales@electrotech.by', 'г. Гродно, ул. Заводская 5'),
('УП "Метизы"', '300345678', 'supplier', 'Белый И.И.', '+375 29 333-44-55', 'zakaz@metizy.by', 'г. Брест, пр. Машерова 15'),
('ООО "СтройМонтаж"', '400456789', 'customer', 'Орлов О.О.', '+375 29 444-55-66', 'order@stroymontazh.by', 'г. Гомель, ул. Строителей 20'),
('ЧТУП "АгроСервис"', '500567890', 'customer', 'Зеленая З.З.', '+375 29 555-66-77', 'agro@service.by', 'г. Витебск, пер. Полевой 3');

INSERT INTO `material_categories` (`parent_id`, `name`, `name_ru`, `code`, `description`) VALUES
(NULL, 'Metals', 'Металлы', 'METAL', 'Черные и цветные металлы'),
(1, 'Bars', 'Прутки', 'METAL_BAR', 'Стальные прутки круглого сечения'),
(1, 'Sheet Metal', 'Листовой прокат', 'METAL_SHEET', 'Листы стальные горячекатаные'),
(1, 'Cast Iron', 'Чугун', 'METAL_CAST', 'Чугунные заготовки'),
(NULL, 'Electrical Engineering', 'Электротехника', 'ELECTRO', 'Электротехнические материалы'),
(5, 'Wires', 'Провода', 'ELECTRO_WIRE', 'Медные и алюминиевые провода'),
(5, 'Busbars', 'Шины', 'ELECTRO_BUS', 'Медные шины'),
(NULL, 'Fasteners', 'Крепеж', 'FASTENER', 'Болты, гайки, шайбы'),
(8, 'Bolts', 'Болты', 'FAST_BOLT', 'Болты различных классов прочности'),
(8, 'Nuts', 'Гайки', 'FAST_NUT', 'Гайки шестигранные'),
(8, 'Washers', 'Шайбы', 'FAST_WASHER', 'Шайбы плоские'),
(NULL, 'Bearings', 'Подшипники', 'BEARING', 'Подшипники качения');

INSERT INTO `product_categories` (`id`, `parent_id`, `name`, `name_ru`, `code`, `description`) VALUES
(1, NULL, 'asynchronous_three_phase_general', 'Электродвигатели асинхронные трёхфазные общепромышленного назначения (серия АИР)', 'MOTOR_AIR', 'Предназначены для комплектации электроприводов различных механизмов во всех отраслях промышленности и аграрного комплекса'),
(2, NULL, 'energy_efficient', 'Энергоэффективные электродвигатели общепромышленного назначения (серия 2AIR, класс IE2)', 'MOTOR_2AIR', 'КПД выше серийных двигателей в среднем на 5%. Соответствуют СТБ IEC 60034-30-2011'),
(3, NULL, 'multispeed', 'Многоскоростные электродвигатели', 'MOTOR_MULTI', 'Для привода механизмов, требующих ступенчатого регулирования частоты вращения'),
(4, NULL, 'special_purpose', 'Электродвигатели специального назначения', 'MOTOR_SPECIAL', 'Двигатели специального назначения'),
(5, 4, 'high_slip', 'С повышенным скольжением (серия АИРС)', 'MOTOR_AIRS', 'Для механизмов с тяжелыми условиями пуска'),
(6, 4, 'for_pumps', 'Для привода моноблочных насосов (индекс Ж)', 'MOTOR_PUMP', 'Для привода моноблочных насосов'),
(7, 4, 'for_gearboxes', 'Для привода редукторов (индекс РЗ)', 'MOTOR_GEAR', 'Для привода редукторов'),
(8, 4, 'for_poultry', 'Для привода осевых вентиляторов в животноводческих и птицеводческих помещениях', 'MOTOR_POULTRY', 'Для системы микроклимата птицеводческих помещений'),
(9, 4, 'for_railway', 'Для стрелочных электроприводов железной дороги (серия АИРЧ)', 'MOTOR_RAILWAY', 'Для стрелочных электроприводов железной дороги'),
(10, 4, 'explosion_proof', 'Взрывозащищённые электродвигатели (серия АИВР)', 'MOTOR_EX', 'Степень взрывозащиты 1ExdIIBT4 по ГОСТ 30852.0. Чугунная станина'),
(11, NULL, 'single_phase', 'Электродвигатели однофазные (бытового и промышленного назначения)', 'MOTOR_SINGLE', 'С двухфазной обмоткой и рабочим конденсатором, напряжение 220 В'),
(12, NULL, 'pumps', 'Электронасосы', 'PUMPS', 'Бытовые и промышленные насосы'),
(13, 12, 'centrifugal_household', 'Электронасос бытовой центробежный', 'PUMP_CENTRIFUGAL', 'Для перекачки воды из рек, озёр, колодцев, скважин'),
(14, 12, 'submersible_dirty', 'Электронасосы центробежные погружные для загрязнённых вод типа «ГНОМ»', 'PUMP_SUBMERSIBLE', 'Для перекачивания загрязнённых вод с механическими примесями'),
(15, NULL, 'other_products', 'Прочая продукция', 'OTHER', 'Электроконфорки, литьё');

-- ============================================
-- ПРОДУКЦИЯ: ОБЩЕПРОМЫШЛЕННЫЕ ДВИГАТЕЛИ АИР (КАТЕГОРИЯ 1)
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
-- Габарит 71
('АИР71А2', 'Двигатель АИР71А2', 1, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":3000,"кпд_проц":77.0,"косинус_фи":0.8,"пусковой_ток_к_номинальному":6.0,"пусковой_момент_к_номинальному":2.6,"макс_момент_к_номинальному":2.7,"мин_момент_к_номинальному":1.6,"масса_кг":10.2,"габарит":71,"число_полюсов":2}', 'motor_AIR71A2.jpg', 212.50, 'BYN', TRUE),
('АИР71В2', 'Двигатель АИР71В2', 1, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":3000,"кпд_проц":78.0,"косинус_фи":0.8,"пусковой_ток_к_номинальному":6.0,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.6,"масса_кг":10.5,"габарит":71,"число_полюсов":2}', 'motor_AIR71B2.jpg', 265.00, 'BYN', TRUE),
('АИР71А4', 'Двигатель АИР71А4', 1, 1, '{"мощность_квт":0.55,"частота_вращения_об_мин":1500,"кпд_проц":71.0,"косинус_фи":0.71,"пусковой_ток_к_номинальному":5.0,"пусковой_момент_к_номинальному":2.3,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.8,"масса_кг":9.7,"габарит":71,"число_полюсов":4}', 'motor_AIR71A4.jpg', 182.50, 'BYN', TRUE),
('АИР71В4', 'Двигатель АИР71В4', 1, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":1500,"кпд_проц":74.0,"косинус_фи":0.78,"пусковой_ток_к_номинальному":5.0,"пусковой_момент_к_номинальному":2.5,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":2.4,"масса_кг":10.2,"габарит":71,"число_полюсов":4}', 'motor_AIR71B4.jpg', 212.50, 'BYN', TRUE),
('АИР71А6', 'Двигатель АИР71А6', 1, 1, '{"мощность_квт":0.37,"частота_вращения_об_мин":1000,"кпд_проц":66.0,"косинус_фи":0.63,"пусковой_ток_к_номинальному":4.5,"пусковой_момент_к_номинальному":2.1,"макс_момент_к_номинальному":2.3,"мин_момент_к_номинальному":1.6,"масса_кг":9.2,"габарит":71,"число_полюсов":6}', 'motor_AIR71A6.jpg', 155.50, 'BYN', TRUE),
('АИР71В6', 'Двигатель АИР71В6', 1, 1, '{"мощность_квт":0.55,"частота_вращения_об_мин":1000,"кпд_проц":69.0,"косинус_фи":0.68,"пусковой_ток_к_номинальному":4.5,"пусковой_момент_к_номинальному":1.9,"макс_момент_к_номинальному":2.2,"мин_момент_к_номинальному":1.6,"масса_кг":10.8,"габарит":71,"число_полюсов":6}', 'motor_AIR71B6.jpg', 182.50, 'BYN', TRUE),
-- Габарит 80
('АИР80А2', 'Двигатель АИР80А2', 1, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":3000,"кпд_проц":82.0,"косинус_фи":0.85,"пусковой_ток_к_номинальному":6.5,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":1.8,"масса_кг":13.3,"габарит":80,"число_полюсов":2}', 'motor_AIR80A2.jpg', 325.00, 'BYN', TRUE),
('АИР80В2', 'Двигатель АИР80В2', 1, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":3000,"кпд_проц":83.0,"косинус_фи":0.87,"пусковой_ток_к_номинальному":6.4,"пусковой_момент_к_номинальному":2.1,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":1.8,"масса_кг":15.9,"габарит":80,"число_полюсов":2}', 'motor_AIR80B2.jpg', 430.00, 'BYN', TRUE),
('АИР80А4', 'Двигатель АИР80А4', 1, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":1500,"кпд_проц":76.5,"косинус_фи":0.77,"пусковой_ток_к_номинальному":5.0,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.7,"масса_кг":12.8,"габарит":80,"число_полюсов":4}', 'motor_AIR80A4.jpg', 265.00, 'BYN', TRUE),
('АИР80В4', 'Двигатель АИР80В4', 1, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":1500,"кпд_проц":78.5,"косинус_фи":0.80,"пусковой_ток_к_номинальному":5.3,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.7,"масса_кг":14.7,"габарит":80,"число_полюсов":4}', 'motor_AIR80B4.jpg', 325.00, 'BYN', TRUE),
('АИР80А6', 'Двигатель АИР80А6', 1, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":1000,"кпд_проц":71.0,"косинус_фи":0.71,"пусковой_ток_к_номинальному":4.0,"пусковой_момент_к_номинальному":2.1,"макс_момент_к_номинальному":2.2,"мин_момент_к_номинальному":1.6,"масса_кг":12.5,"габарит":80,"число_полюсов":6}', 'motor_AIR80A6.jpg', 212.50, 'BYN', TRUE),
('АИР80В6', 'Двигатель АИР80В6', 1, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":1000,"кпд_проц":75.0,"косинус_фи":0.74,"пусковой_ток_к_номинальному":4.5,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.3,"мин_момент_к_номинальному":1.8,"масса_кг":16.2,"габарит":80,"число_полюсов":6}', 'motor_AIR80B6.jpg', 265.00, 'BYN', TRUE),
-- Габарит 90
('АИР90L2', 'Двигатель АИР90L2', 1, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":3000,"кпд_проц":84.6,"косинус_фи":0.88,"пусковой_ток_к_номинальному":7.0,"пусковой_момент_к_номинальному":2.3,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":1.7,"масса_кг":20.6,"габарит":90,"число_полюсов":2}', 'motor_AIR90L2.jpg', 550.00, 'BYN', TRUE),
('АИР90LB2', 'Двигатель АИР90LB2', 1, 1, '{"мощность_квт":4.0,"частота_вращения_об_мин":3000,"кпд_проц":86.5,"косинус_фи":0.86,"пусковой_ток_к_номинальному":7.5,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.6,"масса_кг":23.4,"габарит":90,"число_полюсов":2}', 'motor_AIR90LB2.jpg', 700.00, 'BYN', TRUE),
('АИР90L4', 'Двигатель АИР90L4', 1, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":1500,"кпд_проц":81.0,"косинус_фи":0.83,"пусковой_ток_к_номинальному":6.0,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":2.0,"масса_кг":19.7,"габарит":90,"число_полюсов":4}', 'motor_AIR90L4.jpg', 430.00, 'BYN', TRUE),
('АИР90LB4', 'Двигатель АИР90LB4', 1, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":1500,"кпд_проц":81.0,"косинус_фи":0.81,"пусковой_ток_к_номинальному":6.5,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.7,"масса_кг":24.1,"габарит":90,"число_полюсов":4}', 'motor_AIR90LB4.jpg', 550.00, 'BYN', TRUE),
('АИР90L6', 'Двигатель АИР90L6', 1, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":1000,"кпд_проц":76.0,"косинус_фи":0.72,"пусковой_ток_к_номинальному":5.0,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.3,"мин_момент_к_номинальному":1.9,"масса_кг":20.6,"габарит":90,"число_полюсов":6}', 'motor_AIR90L6.jpg', 325.00, 'BYN', TRUE),
('АИР90LA8', 'Двигатель АИР90LA8', 1, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":750,"кпд_проц":72.5,"косинус_фи":0.71,"пусковой_ток_к_номинальному":4.0,"пусковой_момент_к_номинальному":1.5,"макс_момент_к_номинальному":2.0,"мин_момент_к_номинальному":1.5,"масса_кг":19.5,"габарит":90,"число_полюсов":8}', 'motor_AIR90LA8.jpg', 212.50, 'BYN', TRUE),
('АИР90LB8', 'Двигатель АИР90LB8', 1, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":750,"кпд_проц":76.0,"косинус_фи":0.72,"пусковой_ток_к_номинальному":4.5,"пусковой_момент_к_номинальному":1.5,"макс_момент_к_номинальному":2.2,"мин_момент_к_номинальному":1.5,"масса_кг":22.3,"габарит":90,"число_полюсов":8}', 'motor_AIR90LB8.jpg', 265.00, 'BYN', TRUE),
-- Габарит 100
('АИР100S2', 'Двигатель АИР100S2', 1, 1, '{"мощность_квт":4.0,"частота_вращения_об_мин":3000,"кпд_проц":86.5,"косинус_фи":0.86,"пусковой_ток_к_номинальному":7.5,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.6,"масса_кг":23.6,"габарит":100,"число_полюсов":2}', 'motor_AIR100S2.jpg', 700.00, 'BYN', TRUE),
('АИР100S4', 'Двигатель АИР100S4', 1, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":1500,"кпд_проц":81.0,"косинус_фи":0.81,"пусковой_ток_к_номинальному":6.5,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.7,"масса_кг":25.8,"габарит":100,"число_полюсов":4}', 'motor_AIR100S4.jpg', 550.00, 'BYN', TRUE),
('АИР100L4', 'Двигатель АИР100L4', 1, 1, '{"мощность_квт":4.0,"частота_вращения_об_мин":1500,"кпд_проц":81.0,"косинус_фи":0.81,"пусковой_ток_к_номинальному":6.5,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.4,"мин_момент_к_номинальному":1.7,"масса_кг":25.8,"габарит":100,"число_полюсов":4}', 'motor_AIR100L4.jpg', 700.00, 'BYN', TRUE),
('АИР100L6', 'Двигатель АИР100L6', 1, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":1000,"кпд_проц":76.0,"косинус_фи":0.72,"пусковой_ток_к_номинальному":5.0,"пусковой_момент_к_номинальному":2.0,"макс_момент_к_номинальному":2.3,"мин_момент_к_номинальному":1.9,"масса_кг":20.8,"габарит":100,"число_полюсов":6}', 'motor_AIR100L6.jpg', 430.00, 'BYN', TRUE),
('АИР100L2', 'Двигатель АИР100L2', 1, 1, '{"мощность_квт":5.5,"частота_вращения_об_мин":3000,"кпд_проц":87.0,"косинус_фи":0.94,"пусковой_ток_к_номинальному":8.4,"пусковой_момент_к_номинальному":2.4,"макс_момент_к_номинальному":3.2,"мин_момент_к_номинальному":1.65,"масса_кг":28.0,"габарит":100,"число_полюсов":2}', 'motor_AIR100L2.jpg', 925.00, 'BYN', TRUE),
-- Габарит 112
('АИР112M2', 'Двигатель АИР112M2', 1, 1, '{"мощность_квт":7.5,"частота_вращения_об_мин":3000,"кпд_проц":87.5,"косинус_фи":0.89,"пусковой_ток_к_номинальному":7.5,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.8,"мин_момент_к_номинальному":1.8,"масса_кг":35.0,"габарит":112,"число_полюсов":2}', 'motor_AIR112M2.jpg', 1225.00, 'BYN', TRUE),
('АИР112M4', 'Двигатель АИР112M4', 1, 1, '{"мощность_квт":5.5,"частота_вращения_об_мин":1500,"кпд_проц":85.0,"косинус_фи":0.84,"пусковой_ток_к_номинальному":7.0,"пусковой_момент_к_номинальному":2.3,"макс_момент_к_номинальному":2.7,"мин_момент_к_номинальному":2.0,"масса_кг":33.0,"габарит":112,"число_полюсов":4}', 'motor_AIR112M4.jpg', 925.00, 'BYN', TRUE),
('АИР112MA6', 'Двигатель АИР112MA6', 1, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":1000,"кпд_проц":80.0,"косинус_фи":0.75,"пусковой_ток_к_номинальному":6.0,"пусковой_момент_к_номинальному":2.1,"макс_момент_к_номинальному":2.5,"мин_момент_к_номинальному":1.9,"масса_кг":31.0,"габарит":112,"число_полюсов":6}', 'motor_AIR112MA6.jpg', 550.00, 'BYN', TRUE),
('АИР112MB6', 'Двигатель АИР112MB6', 1, 1, '{"мощность_квт":4.0,"частота_вращения_об_мин":1000,"кпд_проц":81.5,"косинус_фи":0.77,"пусковой_ток_к_номинальному":6.5,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":2.0,"масса_кг":33.5,"габарит":112,"число_полюсов":6}', 'motor_AIR112MB6.jpg', 700.00, 'BYN', TRUE),
('АИР112MA8', 'Двигатель АИР112MA8', 1, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":750,"кпд_проц":76.0,"косинус_фи":0.70,"пусковой_ток_к_номинальному":5.0,"пусковой_момент_к_номинальному":1.8,"макс_момент_к_номинальному":2.2,"мин_момент_к_номинальному":1.6,"масса_кг":32.0,"габарит":112,"число_полюсов":8}', 'motor_AIR112MA8.jpg', 430.00, 'BYN', TRUE),
('АИР112MB8', 'Двигатель АИР112MB8', 1, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":750,"кпд_проц":78.0,"косинус_фи":0.72,"пусковой_ток_к_номинальному":5.5,"пусковой_момент_к_номинальному":1.9,"макс_момент_к_номинальному":2.3,"мин_момент_к_номинальному":1.7,"масса_кг":34.0,"габарит":112,"число_полюсов":8}', 'motor_AIR112MB8.jpg', 550.00, 'BYN', TRUE);

-- ============================================
-- ПРОДУКЦИЯ: ЭНЕРГОЭФФЕКТИВНЫЕ ДВИГАТЕЛИ 2AIR (КАТЕГОРИЯ 2)
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('2AIR80A2', 'Двигатель 2AIR80A2', 2, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":3000,"кпд_проц":81.3,"класс_энергоэффективности":2,"косинус_фи":0.89,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":1.8,"пусковой_ток_к_номинальному":6.5}', 'motor_2AIR80A2.jpg', 420.00, 'BYN', TRUE),
('2AIR80B2', 'Двигатель 2AIR80B2', 2, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":3000,"кпд_проц":83.2,"класс_энергоэффективности":2,"косинус_фи":0.92,"пусковой_момент_к_номинальному":2.1,"макс_момент_к_номинальному":2.6,"мин_момент_к_номинальному":1.8,"пусковой_ток_к_номинальному":6.4}', 'motor_2AIR80B2.jpg', 546.00, 'BYN', TRUE),
('2AIR80A6', 'Двигатель 2AIR80A6', 2, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":1000,"кпд_проц":75.9,"класс_энергоэффективности":2,"косинус_фи":0.67,"пусковой_момент_к_номинальному":2.1,"макс_момент_к_номинальному":2.2,"мин_момент_к_номинальному":1.6,"пусковой_ток_к_номинальному":4.0}', 'motor_2AIR80A6.jpg', 285.00, 'BYN', TRUE),
('2AIR80B6', 'Двигатель 2AIR80B6', 2, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":1000,"кпд_проц":78.1,"класс_энергоэффективности":2,"косинус_фи":0.69,"пусковой_момент_к_номинальному":2.2,"макс_момент_к_номинальному":2.3,"мин_момент_к_номинальному":1.8,"пусковой_ток_к_номинальному":4.5}', 'motor_2AIR80B6.jpg', 348.00, 'BYN', TRUE),
('2AIR90L2', 'Двигатель 2AIR90L2', 2, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":3000,"кпд_проц":85.6,"класс_энергоэффективности":2,"косинус_фи":0.94,"пусковой_момент_к_номинальному":2.3,"макс_момент_к_номинальному":2.7,"мин_момент_к_номинальному":2.0,"пусковой_ток_к_номинальному":7.0}', 'motor_2AIR90L2.jpg', 690.00, 'BYN', TRUE),
('2AIR90L4', 'Двигатель 2AIR90L4', 2, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":1500,"кпд_проц":84.5,"класс_энергоэффективности":2,"косинус_фи":0.87,"пусковой_момент_к_номинальному":2.8,"макс_момент_к_номинальному":2.7,"мин_момент_к_номинальному":2.2,"пусковой_ток_к_номинальному":7.2}', 'motor_2AIR90L4.jpg', 546.00, 'BYN', TRUE),
('2AIR90L6', 'Двигатель 2AIR90L6', 2, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":1000,"кпд_проц":80.3,"класс_энергоэффективности":2,"косинус_фи":0.76,"пусковой_момент_к_номинальному":2.6,"макс_момент_к_номинальному":3.0,"мин_момент_к_номинальному":2.4,"пусковой_ток_к_номинальному":6.0}', 'motor_2AIR90L6.jpg', 420.00, 'BYN', TRUE),
('2AIR100S2', 'Двигатель 2AIR100S2', 2, 1, '{"мощность_квт":4.0,"частота_вращения_об_мин":3000,"кпд_проц":85.8,"класс_энергоэффективности":2,"косинус_фи":0.93,"пусковой_момент_к_номинальному":2.5,"макс_момент_к_номинальному":3.5,"мин_момент_к_номинальному":2.0,"пусковой_ток_к_номинальному":8.3}', 'motor_2AIR100S2.jpg', 870.00, 'BYN', TRUE),
('2AIR100L2', 'Двигатель 2AIR100L2', 2, 1, '{"мощность_квт":5.5,"частота_вращения_об_мин":3000,"кпд_проц":87.0,"класс_энергоэффективности":2,"косинус_фи":0.94,"пусковой_момент_к_номинальному":2.4,"макс_момент_к_номинальному":3.2,"мин_момент_к_номинальному":1.65,"пусковой_ток_к_номинальному":8.4}', 'motor_2AIR100L2.jpg', 1140.00, 'BYN', TRUE),
('2AIR100S4', 'Двигатель 2AIR100S4', 2, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":1500,"кпд_проц":85.7,"класс_энергоэффективности":2,"косинус_фи":0.78,"пусковой_момент_к_номинальному":2.5,"макс_момент_к_номинальному":3.0,"мин_момент_к_номинальному":2.0,"пусковой_ток_к_номинальному":7.0}', 'motor_2AIR100S4.jpg', 690.00, 'BYN', TRUE),
('2AIR100L4', 'Двигатель 2AIR100L4', 2, 1, '{"мощность_квт":4.0,"частота_вращения_об_мин":1500,"кпд_проц":86.9,"класс_энергоэффективности":2,"косинус_фи":0.79,"пусковой_момент_к_номинальному":2.5,"макс_момент_к_номинальному":3.0,"мин_момент_к_номинальному":2.0,"пусковой_ток_к_номинальному":7.5}', 'motor_2AIR100L4.jpg', 870.00, 'BYN', TRUE),
('2AIR100L6', 'Двигатель 2AIR100L6', 2, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":1000,"кпд_проц":82.2,"класс_энергоэффективности":2,"косинус_фи":0.80,"пусковой_момент_к_номинальному":2.7,"макс_момент_к_номинальному":3.1,"мин_момент_к_номинальному":2.0,"пусковой_ток_к_номинальному":6.3}', 'motor_2AIR100L6.jpg', 700.00, 'BYN', TRUE);

-- ============================================
-- ПРОДУКЦИЯ: МНОГОСКОРОСТНЫЕ ДВИГАТЕЛИ (КАТЕГОРИЯ 3)
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИР80А4/2', 'Двигатель АИР80А4/2', 3, 1, '{"мощность_квт_1500":1.12,"мощность_квт_3000":1.5,"частота_вращения_об_мин":"1500/3000","кпд_проц_1500":74.0,"кпд_проц_3000":73.0,"косинус_фи_1500":0.78,"косинус_фи_3000":0.85,"масса_кг":13.1}', 'motor_AIR80A4-2.jpg', 450.00, 'BYN', TRUE),
('АИР90L4/2', 'Двигатель АИР90L4/2', 3, 1, '{"мощность_квт_1500":2.2,"мощность_квт_3000":2.65,"частота_вращения_об_мин":"1500/3000"}', 'motor_AIR90L4-2.jpg', 620.00, 'BYN', TRUE),
('АИР90L6/4', 'Двигатель АИР90L6/4', 3, 1, '{"мощность_квт_1000":1.32,"мощность_квт_1500":1.6,"частота_вращения_об_мин":"1000/1500"}', 'motor_AIR90L6-4.jpg', 640.00, 'BYN', TRUE),
('АИР90L8/4', 'Двигатель АИР90L8/4', 3, 1, '{"мощность_квт_750":0.8,"мощность_квт_1500":1.32,"частота_вращения_об_мин":"750/1500"}', 'motor_AIR90L8-4.jpg', 660.00, 'BYN', TRUE),
('АИР100S8/4', 'Двигатель АИР100S8/4', 3, 1, '{"мощность_квт_750":1.0,"мощность_квт_1500":1.7,"частота_вращения_об_мин":"750/1500"}', 'motor_AIR100S8-4.jpg', 780.00, 'BYN', TRUE);

-- ============================================
-- ПРОДУКЦИЯ: ДВИГАТЕЛИ СПЕЦИАЛЬНОГО НАЗНАЧЕНИЯ (КАТЕГОРИЯ 4)
-- ============================================
-- АИРС - с повышенным скольжением (КАТЕГОРИЯ 5)
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИРС80А2', 'Двигатель АИРС80А2', 5, 1, '{"мощность_квт":1.9,"частота_вращения_об_мин":3000,"тип":"с повышенным скольжением"}', 'motor_AIRS80A2.jpg', 480.00, 'BYN', TRUE),
('АИРС80В2', 'Двигатель АИРС80В2', 5, 1, '{"мощность_квт":2.5,"частота_вращения_об_мин":3000,"тип":"с повышенным скольжением"}', 'motor_AIRS80B2.jpg', 546.00, 'BYN', TRUE),
('АИРС80А4', 'Двигатель АИРС80А4', 5, 1, '{"мощность_квт":1.32,"частота_вращения_об_мин":1500,"тип":"с повышенным скольжением"}', 'motor_AIRS80A4.jpg', 420.00, 'BYN', TRUE),
('АИРС80В4', 'Двигатель АИРС80В4', 5, 1, '{"мощность_квт":1.7,"частота_вращения_об_мин":1500,"тип":"с повышенным скольжением"}', 'motor_AIRS80B4.jpg', 480.00, 'BYN', TRUE),
('АИРС90L2', 'Двигатель АИРС90L2', 5, 1, '{"мощность_квт":3.5,"частота_вращения_об_мин":3000,"тип":"с повышенным скольжением"}', 'motor_AIRS90L2.jpg', 690.00, 'BYN', TRUE),
('АИРС90LB2', 'Двигатель АИРС90LB2', 5, 1, '{"мощность_квт":4.8,"частота_вращения_об_мин":3000,"тип":"с повышенным скольжением"}', 'motor_AIRS90LB2.jpg', 870.00, 'BYN', TRUE),
('АИРС90L4', 'Двигатель АИРС90L4', 5, 1, '{"мощность_квт":2.4,"частота_вращения_об_мин":1500,"тип":"с повышенным скольжением"}', 'motor_AIRS90L4.jpg', 546.00, 'BYN', TRUE),
('АИРС90LB4', 'Двигатель АИРС90LB4', 5, 1, '{"мощность_квт":3.2,"частота_вращения_об_мин":1500,"тип":"с повышенным скольжением"}', 'motor_AIRS90LB4.jpg', 690.00, 'BYN', TRUE),
('АИРС100S2', 'Двигатель АИРС100S2', 5, 1, '{"мощность_квт":4.8,"частота_вращения_об_мин":3000,"тип":"с повышенным скольжением"}', 'motor_AIRS100S2.jpg', 870.00, 'BYN', TRUE),
('АИРС100S4', 'Двигатель АИРС100S4', 5, 1, '{"мощность_квт":3.2,"частота_вращения_об_мин":1500,"тип":"с повышенным скольжением"}', 'motor_AIRS100S4.jpg', 690.00, 'BYN', TRUE);

-- Для моноблочных насосов (Ж) (КАТЕГОРИЯ 6)
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИР80А2Ж', 'Двигатель АИР80А2Ж', 6, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":3000,"тип":"для моноблочных насосов"}', 'motor_AIR80A2Zh.jpg', 325.00, 'BYN', TRUE),
('АИР80В2Ж', 'Двигатель АИР80В2Ж', 6, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":3000,"тип":"для моноблочных насосов"}', 'motor_AIR80B2Zh.jpg', 430.00, 'BYN', TRUE),
('АИР80В4Ж', 'Двигатель АИР80В4Ж', 6, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":1500,"тип":"для моноблочных насосов"}', 'motor_AIR80B4Zh.jpg', 325.00, 'BYN', TRUE),
('АИР90L2Ж', 'Двигатель АИР90L2Ж', 6, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":3000,"тип":"для моноблочных насосов"}', 'motor_AIR90L2Zh.jpg', 550.00, 'BYN', TRUE),
('АИР90L4Ж', 'Двигатель АИР90L4Ж', 6, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":1500,"тип":"для моноблочных насосов"}', 'motor_AIR90L4Zh.jpg', 430.00, 'BYN', TRUE),
('АИР100S2Ж', 'Двигатель АИР100S2Ж', 6, 1, '{"мощность_квт":4.0,"частота_вращения_об_мин":3000,"тип":"для моноблочных насосов"}', 'motor_AIR100S2Zh.jpg', 700.00, 'BYN', TRUE);

-- Для привода редукторов (РЗ) (КАТЕГОРИЯ 7)
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИР90L2РЗ', 'Двигатель АИР90L2РЗ', 7, 1, '{"мощность_квт":3.0,"частота_вращения_об_мин":3000,"тип":"для привода редукторов"}', 'motor_AIR90L2RZ.jpg', 550.00, 'BYN', TRUE),
('АИР90L4РЗ', 'Двигатель АИР90L4РЗ', 7, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":1500,"тип":"для привода редукторов"}', 'motor_AIR90L4RZ.jpg', 430.00, 'BYN', TRUE),
('АИР90L6РЗ', 'Двигатель АИР90L6РЗ', 7, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":1000,"тип":"для привода редукторов"}', 'motor_AIR90L6RZ.jpg', 325.00, 'BYN', TRUE),
('АИР90LA8РЗ', 'Двигатель АИР90LA8РЗ', 7, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":750,"тип":"для привода редукторов"}', 'motor_AIR90LA8RZ.jpg', 212.50, 'BYN', TRUE),
('АИР90LB8РЗ', 'Двигатель АИР90LB8РЗ', 7, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":750,"тип":"для привода редукторов"}', 'motor_AIR90LB8RZ.jpg', 265.00, 'BYN', TRUE);

-- Для птичников (АИРП) (КАТЕГОРИЯ 8)
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИРП80А6', 'Двигатель АИРП80А6', 8, 1, '{"мощность_квт":0.37,"частота_вращения_об_мин":1000,"тип":"для птичников"}', 'motor_AIRP80A6.jpg', 224.00, 'BYN', TRUE),
('АИРП80С6', 'Двигатель АИРП80С6', 8, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":1000,"тип":"для птичников"}', 'motor_AIRP80C6.jpg', 300.00, 'BYN', TRUE);

-- Для железной дороги (АИРЧ) (КАТЕГОРИЯ 9)
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИРЧ80В4', 'Двигатель АИРЧ80В4', 9, 1, '{"мощность_квт":0.55,"частота_вращения_об_мин":1500,"тип":"для железной дороги"}', 'motor_AIRCh80B4.jpg', 337.50, 'BYN', TRUE),
('АИРЧ80В6', 'Двигатель АИРЧ80В6', 9, 1, '{"мощность_квт":0.3,"частота_вращения_об_мин":1000,"тип":"для железной дороги"}', 'motor_AIRCh80B6.jpg', 275.00, 'BYN', TRUE);

-- Взрывозащищённые (АИВР) (КАТЕГОРИЯ 10)
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИВР80', 'Двигатель АИВР80', 10, 1, '{"мощность_квт":"1.5–2.2","частота_вращения_об_мин":"3000/1500","тип":"взрывозащищённый","степень_взрывозащиты":"1ExdIIBT4"}', 'motor_AIVR80.jpg', 2500.00, 'BYN', TRUE),
('АИВР90L', 'Двигатель АИВР90L', 10, 1, '{"мощность_квт":"2.2–3.0","частота_вращения_об_мин":"3000/1500","тип":"взрывозащищённый","степень_взрывозащиты":"1ExdIIBT4"}', 'motor_AIVR90L.jpg', 2800.00, 'BYN', TRUE);

-- ============================================
-- ПРОДУКЦИЯ: ОДНОФАЗНЫЕ ДВИГАТЕЛИ (КАТЕГОРИЯ 11)
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('АИРЕ71А2', 'Двигатель АИРЕ71А2', 11, 1, '{"мощность_квт":0.55,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE71A2.jpg', 337.50, 'BYN', TRUE),
('АИРЕ71В2', 'Двигатель АИРЕ71В2', 11, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE71B2.jpg', 387.50, 'BYN', TRUE),
('АИРЕ71С2', 'Двигатель АИРЕ71С2', 11, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE71C2.jpg', 475.00, 'BYN', TRUE),
('АИРЕ71А4', 'Двигатель АИРЕ71А4', 11, 1, '{"мощность_квт":0.37,"частота_вращения_об_мин":1500,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE71A4.jpg', 292.50, 'BYN', TRUE),
('АИРЕ71В4', 'Двигатель АИРЕ71В4', 11, 1, '{"мощность_квт":0.55,"частота_вращения_об_мин":1500,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE71B4.jpg', 337.50, 'BYN', TRUE),
('АИРЕ71С4', 'Двигатель АИРЕ71С4', 11, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":1500,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE71C4.jpg', 387.50, 'BYN', TRUE),
('АИРЕ80А2', 'Двигатель АИРЕ80А2', 11, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE80A2.jpg', 475.00, 'BYN', TRUE),
('АИРЕ80В2', 'Двигатель АИРЕ80В2', 11, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE80B2.jpg', 575.00, 'BYN', TRUE),
('АИРЕ80С2', 'Двигатель АИРЕ80С2', 11, 1, '{"мощность_квт":1.9,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE80C2.jpg', 675.00, 'BYN', TRUE),
('АИРЕ80D2', 'Двигатель АИРЕ80D2', 11, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE80D2.jpg', 750.00, 'BYN', TRUE),
('АИРЕ80А4', 'Двигатель АИРЕ80А4', 11, 1, '{"мощность_квт":0.75,"частота_вращения_об_мин":1500,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE80A4.jpg', 387.50, 'BYN', TRUE),
('АИРЕ80В4', 'Двигатель АИРЕ80В4', 11, 1, '{"мощность_квт":1.1,"частота_вращения_об_мин":1500,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE80B4.jpg', 475.00, 'BYN', TRUE),
('АИРЕ80С4', 'Двигатель АИРЕ80С4', 11, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":1500,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE80C4.jpg', 575.00, 'BYN', TRUE),
('АИРЕ90L2', 'Двигатель АИРЕ90L2', 11, 1, '{"мощность_квт":2.2,"частота_вращения_об_мин":3000,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE90L2.jpg', 750.00, 'BYN', TRUE),
('АИРЕ90L4', 'Двигатель АИРЕ90L4', 11, 1, '{"мощность_квт":1.5,"частота_вращения_об_мин":1500,"напряжение_в":220,"тип":"однофазный"}', 'motor_AIRE90L4.jpg', 575.00, 'BYN', TRUE);

-- ============================================
-- ПРОДУКЦИЯ: ЭЛЕКТРОНАСОСЫ (КАТЕГОРИЯ 12-14)
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('БЦ-0,5-20-У1.1', 'Электронасос бытовой центробежный БЦ-0,5-20-У1.1', 13, 1, '{"потребляемая_мощность_вт":500,"напряжение_в":220,"производительность_л_мин":30,"номинальный_напор_м":20,"макс_высота_всасывания_м":7,"температура_воды_град_ц":"1–45","уплотнение":"торцовое керамическое"}', 'pump_BC.jpg', 350.00, 'BYN', TRUE),
('ГНОМ', 'Электронасосы центробежные погружные для загрязнённых вод типа «ГНОМ»', 14, 1, '{"температура_среды_град_ц":"0–35","водородный_показатель_рн":"5–10","плотность_среды_кг_м3":"до 1100","содержание_твёрдых_примесей_проц":"до 10","макс_размер_частиц_мм":5}', 'pump_GNOM.jpg', 450.00, 'BYN', TRUE);

-- ============================================
-- ПРОДУКЦИЯ: ПРОЧАЯ ПРОДУКЦИЯ (КАТЕГОРИЯ 15)
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('ЭКЧ-145', 'Электроконфорка чугунная бытовая ЭКЧ-145', 15, 1, '{"наименование":"Электроконфорки чугунные бытового назначения","описание":"Для бытовых электроплит","модель":"ЭКЧ-145"}', 'other_EKCH145.jpg', 100.00, 'BYN', TRUE),
('ЭКЧ-180', 'Электроконфорка чугунная бытовая ЭКЧ-180', 15, 1, '{"наименование":"Электроконфорки чугунные бытового назначения","описание":"Для бытовых электроплит","модель":"ЭКЧ-180"}', 'other_EKCH180.jpg', 100.00, 'BYN', TRUE),
('ЭКЧ220-2.0/220', 'Электроконфорка чугунная бытовая ЭКЧ220-2.0/220', 15, 1, '{"наименование":"Электроконфорки чугунные бытового назначения","описание":"Для бытовых электроплит","модель":"ЭКЧ220-2.0/220"}', 'other_EKCH220.jpg', 100.00, 'BYN', TRUE),
('ЭКЧ1-1.0/220', 'Электроконфорка чугунная бытовая ЭКЧ1-1.0/220', 15, 1, '{"наименование":"Электроконфорки чугунные бытового назначения","описание":"Для бытовых электроплит","модель":"ЭКЧ1-1.0/220"}', 'other_EKCH1.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-СЧ10', 'Отливка из серого чугуна СЧ10', 15, 1, '{"наименование":"Чугунное литьё","описание":"Отливки из серого и высокопрочного чугуна","материал":"СЧ10"}', 'other_cast_SC10.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-СЧ15', 'Отливка из серого чугуна СЧ15', 15, 1, '{"наименование":"Чугунное литьё","описание":"Отливки из серого и высокопрочного чугуна","материал":"СЧ15"}', 'other_cast_SC15.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-СЧ20', 'Отливка из серого чугуна СЧ20', 15, 1, '{"наименование":"Чугунное литьё","описание":"Отливки из серого и высокопрочного чугуна","материал":"СЧ20"}', 'other_cast_SC20.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-ВЧ35', 'Отливка из высокопрочного чугуна ВЧ35', 15, 1, '{"наименование":"Чугунное литьё","описание":"Отливки из серого и высокопрочного чугуна","материал":"ВЧ35"}', 'other_cast_VCH35.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-ВЧ40', 'Отливка из высокопрочного чугуна ВЧ40', 15, 1, '{"наименование":"Чугунное литьё","описание":"Отливки из серого и высокопрочного чугуна","материал":"ВЧ40"}', 'other_cast_VCH40.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-ВЧ50', 'Отливка из высокопрочного чугуна ВЧ50', 15, 1, '{"наименование":"Чугунное литьё","описание":"Отливки из серого и высокопрочного чугуна","материал":"ВЧ50"}', 'other_cast_VCH50.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-АК5М2', 'Отливка из алюминиевого сплава АК5М2', 15, 1, '{"наименование":"Цветное литьё","описание":"Отливки из алюминиевых сплавов","материал":"АК5М2"}', 'other_alu_AK5M2.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-АК7', 'Отливка из алюминиевого сплава АК7', 15, 1, '{"наименование":"Цветное литьё","описание":"Отливки из алюминиевых сплавов","материал":"АК7"}', 'other_alu_AK7.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-АК9', 'Отливка из алюминиевого сплава АК9', 15, 1, '{"наименование":"Цветное литьё","описание":"Отливки из алюминиевых сплавов","материал":"АК9"}', 'other_alu_AK9.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-АК12', 'Отливка из алюминиевого сплава АК12', 15, 1, '{"наименование":"Цветное литьё","описание":"Отливки из алюминиевых сплавов","материал":"АК12"}', 'other_alu_AK12.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-А5', 'Отливка из алюминиевого сплава А5', 15, 1, '{"наименование":"Цветное литьё","описание":"Отливки из алюминиевых сплавов","материал":"А5"}', 'other_alu_A5.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-А6', 'Отливка из алюминиевого сплава А6', 15, 1, '{"наименование":"Цветное литьё","описание":"Отливки из алюминиевых сплавов","материал":"А6"}', 'other_alu_A6.jpg', 100.00, 'BYN', TRUE),
('ЛИТЬЁ-АВ-87', 'Отливка из алюминиевого сплава АВ-87', 15, 1, '{"наименование":"Цветное литьё","описание":"Отливки из алюминиевых сплавов","материал":"АВ-87"}', 'other_alu_AV87.jpg', 100.00, 'BYN', TRUE);

SET FOREIGN_KEY_CHECKS = 1;
