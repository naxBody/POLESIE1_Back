-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ОБНОВЛЕННАЯ СХЕМА БАЗЫ ДАННЫХ
-- С маршрутными картами, этапами производства и планом выпуска
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
-- ЗАПОЛНЕНИЕ ТЕСТОВЫМИ ДАННЫМИ
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

INSERT INTO `product_categories` (`parent_id`, `name`, `name_ru`, `code`, `description`) VALUES
(NULL, 'Electric Motors', 'Электродвигатели', 'MOTOR', 'Асинхронные электродвигатели'),
(1, 'General Purpose Motors', 'Общепромышленные двигатели', 'MOTOR_GENERAL', 'Двигатели общего назначения'),
(1, 'Explosion-proof Motors', 'Взрывозащищенные двигатели', 'MOTOR_EX', 'Двигатели для взрывоопасных сред'),
(1, 'Crane Motors', 'Крановые двигатели', 'MOTOR_CRANE', 'Двигатели для кранового оборудования'),
(NULL, 'Generators', 'Генераторы', 'GENERATOR', 'Дизельные и бензиновые генераторы'),
(5, 'Diesel Generators', 'Дизельные генераторы', 'GEN_DIESEL', 'Дизельные электростанции'),
(5, 'Petrol Generators', 'Бензиновые генераторы', 'GEN_PETROL', 'Бензиновые электростанции'),
(NULL, 'Transformers', 'Трансформаторы', 'TRANSFORMER', 'Силовые трансформаторы'),
(8, 'Oil Transformers', 'Масляные трансформаторы', 'TR_OIL', 'Трансформаторы с масляным охлаждением'),
(8, 'Dry Transformers', 'Сухие трансформаторы', 'TR_DRY', 'Трансформаторы с воздушным охлаждением'),
(NULL, 'Switchgear', 'Щитовое оборудование', 'SWITCHGEAR', 'Распределительные щиты и шкафы'),
(10, 'Main Distribution Boards', 'Вводно-распределительные устройства', 'SWITCH_VRU', 'ВРУ для промышленных объектов'),
(10, 'Control Panels', 'Щиты управления', 'SWITCH_CONTROL', 'Шкафы управления двигателями'),
(10, 'Lighting Panels', 'Щиты освещения', 'SWITCH_LIGHT', 'Щиты распределения освещения'),
(NULL, 'Spare Parts', 'Запчасти', 'SPARE_PARTS', 'Запасные части и комплектующие'),
(14, 'Rotors', 'Роторы', 'PART_ROTOR', 'Роторы для электродвигателей'),
(14, 'Stators', 'Статоры', 'PART_STATOR', 'Статоры для электродвигателей'),
(14, 'Bearing Shields', 'Подшипниковые щиты', 'PART_SHIELD', 'Подшипниковые щиты'),
(14, 'Terminal Boxes', 'Клеммные коробки', 'PART_BOX', 'Клеммные коробки');

INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`, `current_stock`, `min_stock`, `location`, `supplier_id`, `last_price`, `currency`) VALUES
-- Металлы - прутки
('ST-BAR-45-010', 'Пруток стальной 45 Ø10мм', 'Пруток 45 Ø10', 2, 3, '{"диаметр_мм":10,"марка_стали":"45","длина_м":6,"поверхность":"калиброванный","гост":"10702-78"}', 321.52, 64.30, 'Склад №1, Секция А', 1, 2.50, 'BYN'),
('ST-BAR-40X-010', 'Пруток легированный 40Х Ø10мм', 'Пруток 40Х Ø10', 2, 3, '{"диаметр_мм":10,"марка_стали":"40Х","длина_м":6,"поверхность":"горячекатаный","гост":"2590-2006"}', 17.38, 3.48, 'Склад №1, Секция А', 1, 3.20, 'BYN'),
('ST-BAR-45-020', 'Пруток стальной 45 Ø20мм', 'Пруток 45 Ø20', 2, 3, '{"диаметр_мм":20,"марка_стали":"45","длина_м":6,"поверхность":"калиброванный","гост":"10702-78"}', 450.00, 50.00, 'Склад №1, Секция Б', 1, 4.80, 'BYN'),
('ST-BAR-45-030', 'Пруток стальной 45 Ø30мм', 'Пруток 45 Ø30', 2, 3, '{"диаметр_мм":30,"марка_стали":"45","длина_м":6,"поверхность":"калиброванный","гост":"10702-78"}', 280.00, 40.00, 'Склад №1, Секция Б', 1, 7.20, 'BYN'),
('ST-BAR-45-040', 'Пруток стальной 45 Ø40мм', 'Пруток 45 Ø40', 2, 3, '{"диаметр_мм":40,"марка_стали":"45","длина_м":6,"поверхность":"калиброванный","гост":"10702-78"}', 195.00, 30.00, 'Склад №1, Секция Б', 1, 12.80, 'BYN'),
('ST-BAR-45-050', 'Пруток стальной 45 Ø50мм', 'Пруток 45 Ø50', 2, 3, '{"диаметр_мм":50,"марка_стали":"45","длина_м":6,"поверхность":"калиброванный","гост":"10702-78"}', 150.00, 25.00, 'Склад №1, Секция В', 1, 20.00, 'BYN'),
('ST-BAR-35-025', 'Пруток стальной 35 Ø25мм', 'Пруток 35 Ø25', 2, 3, '{"диаметр_мм":25,"марка_стали":"35","длина_м":6,"поверхность":"горячекатаный","гост":"2590-2006"}', 320.00, 45.00, 'Склад №1, Секция А', 1, 5.50, 'BYN'),
('ST-BAR-20-015', 'Пруток стальной 20 Ø15мм', 'Пруток 20 Ø15', 2, 3, '{"диаметр_мм":15,"марка_стали":"20","длина_м":6,"поверхность":"горячекатаный","гост":"2590-2006"}', 410.00, 60.00, 'Склад №1, Секция А', 1, 3.80, 'BYN'),
-- Металлы - листовой прокат
('ST-SHEET-3-08', 'Лист стальной 3мм', 'Лист 3мм', 3, 2, '{"толщина_мм":3,"ширина_мм":1500,"длина_мм":6000,"марка_стали":"Ст3сп","гост":"19903-90"}', 1250.00, 200.00, 'Склад №2, Секция А', 1, 2.10, 'BYN'),
('ST-SHEET-4-08', 'Лист стальной 4мм', 'Лист 4мм', 3, 2, '{"толщина_мм":4,"ширина_мм":1500,"длина_мм":6000,"марка_стали":"Ст3сп","гост":"19903-90"}', 980.00, 150.00, 'Склад №2, Секция А', 1, 2.80, 'BYN'),
('ST-SHEET-5-08', 'Лист стальной 5мм', 'Лист 5мм', 3, 2, '{"толщина_мм":5,"ширина_мм":1500,"длина_мм":6000,"марка_стали":"Ст3сп","гост":"19903-90"}', 750.00, 120.00, 'Склад №2, Секция А', 1, 3.50, 'BYN'),
('ST-SHEET-6-08', 'Лист стальной 6мм', 'Лист 6мм', 3, 2, '{"толщина_мм":6,"ширина_мм":1500,"длина_мм":6000,"марка_стали":"Ст3сп","гост":"19903-90"}', 620.00, 100.00, 'Склад №2, Секция Б', 1, 4.20, 'BYN'),
('ST-SHEET-8-08', 'Лист стальной 8мм', 'Лист 8мм', 3, 2, '{"толщина_мм":8,"ширина_мм":1500,"длина_мм":6000,"марка_стали":"Ст3сп","гост":"19903-90"}', 480.00, 80.00, 'Склад №2, Секция Б', 1, 5.60, 'BYN'),
('ST-SHEET-10-08', 'Лист стальной 10мм', 'Лист 10мм', 3, 2, '{"толщина_мм":10,"ширина_мм":1500,"длина_мм":6000,"марка_стали":"Ст3сп","гост":"19903-90"}', 350.00, 60.00, 'Склад №2, Секция Б', 1, 7.00, 'BYN'),
-- Металлы - чугун
('CAST-IRON-CH20', 'Чугун серый СЧ20', 'Чугун СЧ20', 4, 2, '{"марка":"СЧ20","твердость_hb":"170-220","гост":"1412-85"}', 500.00, 100.00, 'Склад №2, Секция В', 1, 3.00, 'BYN'),
('CAST-IRON-CH25', 'Чугун серый СЧ25', 'Чугун СЧ25', 4, 2, '{"марка":"СЧ25","твердость_hb":"190-240","гост":"1412-85"}', 380.00, 80.00, 'Склад №2, Секция В', 1, 3.50, 'BYN'),
('CAST-IRON-VCH40', 'Чугун высокопрочный ВЧ40', 'Чугун ВЧ40', 4, 2, '{"марка":"ВЧ40","твердость_hb":"200-250","гост":"7293-85"}', 290.00, 60.00, 'Склад №2, Секция В', 1, 4.20, 'BYN'),
-- Электротехника - провода
('WIRE-CU-0.75', 'Провод медный 0.75мм²', 'Провод 0.75', 6, 3, '{"сечение_мм2":0.75,"диаметр_мм":1.0,"материал":"медь","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 2500.00, 500.00, 'Склад №4, Секция А', 2, 0.45, 'BYN'),
('WIRE-CU-1.5', 'Провод медный 1.5мм²', 'Провод 1.5', 6, 3, '{"сечение_мм2":1.5,"диаметр_мм":1.4,"материал":"медь","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 2000.00, 400.00, 'Склад №4, Секция А', 2, 0.75, 'BYN'),
('WIRE-CU-2.5', 'Провод медный 2.5мм²', 'Провод 2.5', 6, 3, '{"сечение_мм2":2.5,"диаметр_мм":1.8,"материал":"медь","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 1500.00, 300.00, 'Склад №4, Секция А', 2, 1.20, 'BYN'),
('WIRE-CU-4.0', 'Провод медный 4.0мм²', 'Провод 4.0', 6, 3, '{"сечение_мм2":4.0,"диаметр_мм":2.3,"материал":"медь","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 1200.00, 250.00, 'Склад №4, Секция А', 2, 1.90, 'BYN'),
('WIRE-CU-6.0', 'Провод медный 6.0мм²', 'Провод 6.0', 6, 3, '{"сечение_мм2":6.0,"диаметр_мм":2.8,"материал":"медь","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 950.00, 200.00, 'Склад №4, Секция Б', 2, 2.85, 'BYN'),
('WIRE-CU-10.0', 'Провод медный 10мм²', 'Провод 10', 6, 3, '{"сечение_мм2":10,"диаметр_мм":3.6,"материал":"медь","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 650.00, 150.00, 'Склад №4, Секция Б', 2, 4.75, 'BYN'),
('WIRE-AL-2.5', 'Провод алюминиевый 2.5мм²', 'Провод АК 2.5', 6, 3, '{"сечение_мм2":2.5,"диаметр_мм":1.8,"материал":"алюминий","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 1800.00, 350.00, 'Склад №4, Секция А', 2, 0.55, 'BYN'),
('WIRE-AL-4.0', 'Провод алюминиевый 4.0мм²', 'Провод АК 4.0', 6, 3, '{"сечение_мм2":4.0,"диаметр_мм":2.3,"материал":"алюминий","изоляция":"ПВХ","напряжение_в":450,"гост":"6323-79"}', 1400.00, 280.00, 'Склад №4, Секция А', 2, 0.85, 'BYN'),
-- Электротехника - шины
('BUS-CU-15x3', 'Шина медная 15x3мм', 'Шина 15x3', 7, 3, '{"ширина_мм":15,"толщина_мм":3,"материал":"медь","гост":"434-78"}', 450.00, 80.00, 'Склад №4, Секция В', 2, 18.50, 'BYN'),
('BUS-CU-20x3', 'Шина медная 20x3мм', 'Шина 20x3', 7, 3, '{"ширина_мм":20,"толщина_мм":3,"материал":"медь","гост":"434-78"}', 380.00, 70.00, 'Склад №4, Секция В', 2, 24.00, 'BYN'),
('BUS-CU-25x3', 'Шина медная 25x3мм', 'Шина 25x3', 7, 3, '{"ширина_мм":25,"толщина_мм":3,"материал":"медь","гост":"434-78"}', 320.00, 60.00, 'Склад №4, Секция В', 2, 30.00, 'BYN'),
('BUS-CU-30x4', 'Шина медная 30x4мм', 'Шина 30x4', 7, 3, '{"ширина_мм":30,"толщина_мм":4,"материал":"медь","гост":"434-78"}', 280.00, 50.00, 'Склад №4, Секция В', 2, 48.00, 'BYN'),
('BUS-CU-40x5', 'Шина медная 40x5мм', 'Шина 40x5', 7, 3, '{"ширина_мм":40,"толщина_мм":5,"материал":"медь","гост":"434-78"}', 220.00, 40.00, 'Склад №4, Секция В', 2, 80.00, 'BYN'),
('BUS-AL-30x4', 'Шина алюминиевая 30x4мм', 'Шина А 30x4', 7, 3, '{"ширина_мм":30,"толщина_мм":4,"материал":"алюминий","гост":"434-78"}', 350.00, 60.00, 'Склад №4, Секция В', 2, 22.00, 'BYN'),
('BUS-AL-40x5', 'Шина алюминиевая 40x5мм', 'Шина А 40x5', 7, 3, '{"ширина_мм":40,"толщина_мм":5,"материал":"алюминий","гост":"434-78"}', 280.00, 50.00, 'Склад №4, Секция В', 2, 38.00, 'BYN'),
-- Крепеж - болты
('BOLT-M6x20', 'Болт М6х20 8.8', 'Болт М6х20', 9, 1, '{"резьба":"M6","длина_мм":20,"диаметр_мм":6,"шаг_резьбы_мм":1.0,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 8000.00, 1500.00, 'Склад №5, Ящик 1', 3, 0.18, 'BYN'),
('BOLT-M6x30', 'Болт М6х30 8.8', 'Болт М6х30', 9, 1, '{"резьба":"M6","длина_мм":30,"диаметр_мм":6,"шаг_резьбы_мм":1.0,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 7500.00, 1400.00, 'Склад №5, Ящик 1', 3, 0.22, 'BYN'),
('BOLT-M8x25', 'Болт М8х25 8.8', 'Болт М8х25', 9, 1, '{"резьба":"M8","длина_мм":25,"диаметр_мм":8,"шаг_резьбы_мм":1.25,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 6000.00, 1200.00, 'Склад №5, Ящик 1', 3, 0.28, 'BYN'),
('BOLT-M8x40', 'Болт М8х40 8.8', 'Болт М8х40', 9, 1, '{"резьба":"M8","длина_мм":40,"диаметр_мм":8,"шаг_резьбы_мм":1.25,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 5500.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.32, 'BYN'),
('BOLT-M10x50', 'Болт М10х50 8.8', 'Болт М10х50', 9, 1, '{"резьба":"M10","длина_мм":50,"диаметр_мм":10,"шаг_резьбы_мм":1.5,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 5000.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.35, 'BYN'),
('BOLT-M10x60', 'Болт М10х60 8.8', 'Болт М10х60', 9, 1, '{"резьба":"M10","длина_мм":60,"диаметр_мм":10,"шаг_резьбы_мм":1.5,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 4500.00, 900.00, 'Склад №5, Ящик 1', 3, 0.42, 'BYN'),
('BOLT-M12x60', 'Болт М12х60 8.8', 'Болт М12х60', 9, 1, '{"резьба":"M12","длина_мм":60,"диаметр_мм":12,"шаг_резьбы_мм":1.75,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 3500.00, 700.00, 'Склад №5, Ящик 1', 3, 0.55, 'BYN'),
('BOLT-M12x80', 'Болт М12х80 8.8', 'Болт М12х80', 9, 1, '{"резьба":"M12","длина_мм":80,"диаметр_мм":12,"шаг_резьбы_мм":1.75,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 3200.00, 650.00, 'Склад №5, Ящик 1', 3, 0.65, 'BYN'),
('BOLT-M14x70', 'Болт М14х70 8.8', 'Болт М14х70', 9, 1, '{"резьба":"M14","длина_мм":70,"диаметр_мм":14,"шаг_резьбы_мм":2.0,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 2800.00, 550.00, 'Склад №5, Ящик 1', 3, 0.85, 'BYN'),
('BOLT-M16x80', 'Болт М16х80 8.8', 'Болт М16х80', 9, 1, '{"резьба":"M16","длина_мм":80,"диаметр_мм":16,"шаг_резьбы_мм":2.0,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 2400.00, 500.00, 'Склад №5, Ящик 1', 3, 1.10, 'BYN'),
('BOLT-M20x100', 'Болт М20х100 8.8', 'Болт М20х100', 9, 1, '{"резьба":"M20","длина_мм":100,"диаметр_мм":20,"шаг_резьбы_мм":2.5,"класс_прочности":"8.8","покрытие":"цинк","тип_головки":"шестигранная","гост":"7798-70"}', 1800.00, 350.00, 'Склад №5, Ящик 1', 3, 1.80, 'BYN'),
-- Крепеж - гайки
('NUT-M6', 'Гайка М6 8', 'Гайка М6', 10, 1, '{"резьба":"M6","диаметр_мм":6,"шаг_резьбы_мм":1.0,"класс_прочности":"8","покрытие":"цинк","тип_гайки":"шестигранная","гост":"5915-70"}', 9000.00, 1800.00, 'Склад №5, Ящик 2', 3, 0.08, 'BYN'),
('NUT-M8', 'Гайка М8 8', 'Гайка М8', 10, 1, '{"резьба":"M8","диаметр_мм":8,"шаг_резьбы_мм":1.25,"класс_прочности":"8","покрытие":"цинк","тип_гайки":"шестигранная","гост":"5915-70"}', 7500.00, 1500.00, 'Склад №5, Ящик 2', 3, 0.12, 'BYN'),
('NUT-M10', 'Гайка М10 8', 'Гайка М10', 10, 1, '{"резьба":"M10","диаметр_мм":10,"шаг_резьбы_мм":1.5,"класс_прочности":"8","покрытие":"цинк","тип_гайки":"шестигранная","гост":"5915-70"}', 6000.00, 1200.00, 'Склад №5, Ящик 2', 3, 0.15, 'BYN'),
('NUT-M12', 'Гайка М12 8', 'Гайка М12', 10, 1, '{"резьба":"M12","диаметр_мм":12,"шаг_резьбы_мм":1.75,"класс_прочности":"8","покрытие":"цинк","тип_гайки":"шестигранная","гост":"5915-70"}', 4500.00, 900.00, 'Склад №5, Ящик 2', 3, 0.22, 'BYN'),
('NUT-M14', 'Гайка М14 8', 'Гайка М14', 10, 1, '{"резьба":"M14","диаметр_мм":14,"шаг_резьбы_мм":2.0,"класс_прочности":"8","покрытие":"цинк","тип_гайки":"шестигранная","гост":"5915-70"}', 3800.00, 750.00, 'Склад №5, Ящик 2', 3, 0.32, 'BYN'),
('NUT-M16', 'Гайка М16 8', 'Гайка М16', 10, 1, '{"резьба":"M16","диаметр_мм":16,"шаг_резьбы_мм":2.0,"класс_прочности":"8","покрытие":"цинк","тип_гайки":"шестигранная","гост":"5915-70"}', 3200.00, 650.00, 'Склад №5, Ящик 2', 3, 0.45, 'BYN'),
('NUT-M20', 'Гайка М20 8', 'Гайка М20', 10, 1, '{"резьба":"M20","диаметр_мм":20,"шаг_резьбы_мм":2.5,"класс_прочности":"8","покрытие":"цинк","тип_гайки":"шестигранная","гост":"5915-70"}', 2500.00, 500.00, 'Склад №5, Ящик 2', 3, 0.75, 'BYN'),
-- Крепеж - шайбы
('WASHER-6', 'Шайба М6', 'Шайба 6', 11, 1, '{"внутренний_диаметр_мм":6.4,"внешний_диаметр_мм":12,"толщина_мм":1.6,"гост":"11371-78"}', 10000.00, 2000.00, 'Склад №5, Ящик 3', 3, 0.05, 'BYN'),
('WASHER-8', 'Шайба М8', 'Шайба 8', 11, 1, '{"внутренний_диаметр_мм":8.4,"внешний_диаметр_мм":15,"толщина_мм":2,"гост":"11371-78"}', 8500.00, 1700.00, 'Склад №5, Ящик 3', 3, 0.07, 'BYN'),
('WASHER-10', 'Шайба М10', 'Шайба 10', 11, 1, '{"внутренний_диаметр_мм":10.5,"внешний_диаметр_мм":18,"толщина_мм":2,"гост":"11371-78"}', 7000.00, 1400.00, 'Склад №5, Ящик 3', 3, 0.10, 'BYN'),
('WASHER-12', 'Шайба М12', 'Шайба 12', 11, 1, '{"внутренний_диаметр_мм":13,"внешний_диаметр_мм":22,"толщина_мм":2.5,"гост":"11371-78"}', 5500.00, 1100.00, 'Склад №5, Ящик 3', 3, 0.15, 'BYN'),
('WASHER-14', 'Шайба М14', 'Шайба 14', 11, 1, '{"внутренний_диаметр_мм":15,"внешний_диаметр_мм":25,"толщина_мм":3,"гост":"11371-78"}', 4500.00, 900.00, 'Склад №5, Ящик 3', 3, 0.20, 'BYN'),
('WASHER-16', 'Шайба М16', 'Шайба 16', 11, 1, '{"внутренний_диаметр_мм":17,"внешний_диаметр_мм":28,"толщина_мм":3,"гост":"11371-78"}', 4000.00, 800.00, 'Склад №5, Ящик 3', 3, 0.28, 'BYN'),
('WASHER-20', 'Шайба М20', 'Шайба 20', 11, 1, '{"внутренний_диаметр_мм":21,"внешний_диаметр_мм":34,"толщина_мм":4,"гост":"11371-78"}', 3000.00, 600.00, 'Склад №5, Ящик 3', 3, 0.45, 'BYN'),
-- Подшипники
('BRG-6200', 'Подшипник 6200-2RS', 'Подшипник 6200', 11, 1, '{"внутренний_диаметр_мм":10,"внешний_диаметр_мм":30,"ширина_мм":9,"тип":"шариковый","уплотнение":"2RS"}', 200.00, 40.00, 'Склад №5, Ящик 4', 2, 4.50, 'BYN'),
('BRG-6201', 'Подшипник 6201-2RS', 'Подшипник 6201', 11, 1, '{"внутренний_диаметр_мм":12,"внешний_диаметр_мм":32,"ширина_мм":10,"тип":"шариковый","уплотнение":"2RS"}', 180.00, 35.00, 'Склад №5, Ящик 4', 2, 5.00, 'BYN'),
('BRG-6202', 'Подшипник 6202-2RS', 'Подшипник 6202', 11, 1, '{"внутренний_диаметр_мм":15,"внешний_диаметр_мм":35,"ширина_мм":11,"тип":"шариковый","уплотнение":"2RS"}', 160.00, 32.00, 'Склад №5, Ящик 4', 2, 5.50, 'BYN'),
('BRG-6203', 'Подшипник 6203-2RS', 'Подшипник 6203', 11, 1, '{"внутренний_диаметр_мм":17,"внешний_диаметр_мм":40,"ширина_мм":12,"тип":"шариковый","уплотнение":"2RS"}', 140.00, 28.00, 'Склад №5, Ящик 4', 2, 6.00, 'BYN'),
('BRG-6204', 'Подшипник 6204-2RS', 'Подшипник 6204', 11, 1, '{"внутренний_диаметр_мм":20,"внешний_диаметр_мм":47,"ширина_мм":14,"тип":"шариковый","уплотнение":"2RS"}', 120.00, 25.00, 'Склад №5, Ящик 4', 2, 7.00, 'BYN'),
('BRG-6205', 'Подшипник 6205-2RS', 'Подшипник 6205', 11, 1, '{"внутренний_диаметр_мм":25,"внешний_диаметр_мм":52,"ширина_мм":15,"тип":"шариковый","уплотнение":"2RS"}', 150.00, 30.00, 'Склад №5, Ящик 3', 2, 8.50, 'BYN'),
('BRG-6206', 'Подшипник 6206-2RS', 'Подшипник 6206', 11, 1, '{"внутренний_диаметр_мм":30,"внешний_диаметр_мм":62,"ширина_мм":16,"тип":"шариковый","уплотнение":"2RS"}', 130.00, 26.00, 'Склад №5, Ящик 4', 2, 10.50, 'BYN'),
('BRG-6207', 'Подшипник 6207-2RS', 'Подшипник 6207', 11, 1, '{"внутренний_диаметр_мм":35,"внешний_диаметр_мм":72,"ширина_мм":17,"тип":"шариковый","уплотнение":"2RS"}', 110.00, 22.00, 'Склад №5, Ящик 4', 2, 13.00, 'BYN'),
('BRG-6208', 'Подшипник 6208-2RS', 'Подшипник 6208', 11, 1, '{"внутренний_диаметр_мм":40,"внешний_диаметр_мм":80,"ширина_мм":18,"тип":"шариковый","уплотнение":"2RS"}', 95.00, 20.00, 'Склад №5, Ящик 4', 2, 16.00, 'BYN'),
('BRG-6209', 'Подшипник 6209-2RS', 'Подшипник 6209', 11, 1, '{"внутренний_диаметр_мм":45,"внешний_диаметр_мм":85,"ширина_мм":19,"тип":"шариковый","уплотнение":"2RS"}', 85.00, 18.00, 'Склад №5, Ящик 4', 2, 19.00, 'BYN'),
('BRG-6210', 'Подшипник 6210-2RS', 'Подшипник 6210', 11, 1, '{"внутренний_диаметр_мм":50,"внешний_диаметр_мм":90,"ширина_мм":20,"тип":"шариковый","уплотнение":"2RS"}', 75.00, 15.00, 'Склад №5, Ящик 4', 2, 22.00, 'BYN'),
('BRG-6305', 'Подшипник 6305-2RS', 'Подшипник 6305', 11, 1, '{"внутренний_диаметр_мм":25,"внешний_диаметр_мм":62,"ширина_мм":17,"тип":"шариковый","уплотнение":"2RS"}', 100.00, 20.00, 'Склад №5, Ящик 4', 2, 12.00, 'BYN'),
('BRG-6306', 'Подшипник 6306-2RS', 'Подшипник 6306', 11, 1, '{"внутренний_диаметр_мм":30,"внешний_диаметр_мм":72,"ширина_мм":19,"тип":"шариковый","уплотнение":"2RS"}', 90.00, 18.00, 'Склад №5, Ящик 4', 2, 15.00, 'BYN'),
('BRG-6307', 'Подшипник 6307-2RS', 'Подшипник 6307', 11, 1, '{"внутренний_диаметр_мм":35,"внешний_диаметр_мм":80,"ширина_мм":21,"тип":"шариковый","уплотнение":"2RS"}', 80.00, 16.00, 'Склад №5, Ящик 4', 2, 18.50, 'BYN'),
('BRG-6308', 'Подшипник 6308-2RS', 'Подшипник 6308', 11, 1, '{"внутренний_диаметр_мм":40,"внешний_диаметр_мм":90,"ширина_мм":23,"тип":"шариковый","уплотнение":"2RS"}', 70.00, 14.00, 'Склад №5, Ящик 4', 2, 22.00, 'BYN'),
('BRG-6309', 'Подшипник 6309-2RS', 'Подшипник 6309', 11, 1, '{"внутренний_диаметр_мм":45,"внешний_диаметр_мм":100,"ширина_мм":25,"тип":"шариковый","уплотнение":"2RS"}', 65.00, 13.00, 'Склад №5, Ящик 4', 2, 27.00, 'BYN'),
('BRG-6310', 'Подшипник 6310-2RS', 'Подшипник 6310', 11, 1, '{"внутренний_диаметр_мм":50,"внешний_диаметр_мм":110,"ширина_мм":27,"тип":"шариковый","уплотнение":"2RS"}', 55.00, 12.00, 'Склад №5, Ящик 4', 2, 32.00, 'BYN'),
('BRG-6311', 'Подшипник 6311-2RS', 'Подшипник 6311', 11, 1, '{"внутренний_диаметр_мм":55,"внешний_диаметр_мм":120,"ширина_мм":29,"тип":"шариковый","уплотнение":"2RS"}', 50.00, 10.00, 'Склад №5, Ящик 4', 2, 38.00, 'BYN'),
('BRG-6312', 'Подшипник 6312-2RS', 'Подшипник 6312', 11, 1, '{"внутренний_диаметр_мм":60,"внешний_диаметр_мм":130,"ширина_мм":31,"тип":"шариковый","уплотнение":"2RS"}', 45.00, 10.00, 'Склад №5, Ящик 4', 2, 45.00, 'BYN'),
-- Лакоткань и изоляция
('INSUL-VARNISH-0.15', 'Лакоткань 0.15мм', 'Лакоткань 0.15', 5, 3, '{"толщина_мм":0.15,"ширина_мм":1000,"класс":"F","гост":"2110-78"}', 250.00, 50.00, 'Склад №4, Секция Г', 2, 45.00, 'BYN'),
('INSUL-VARNISH-0.20', 'Лакоткань 0.20мм', 'Лакоткань 0.20', 5, 3, '{"толщина_мм":0.20,"ширина_мм":1000,"класс":"F","гост":"2110-78"}', 220.00, 45.00, 'Склад №4, Секция Г', 2, 52.00, 'BYN'),
('INSUL-VARNISH-0.25', 'Лакоткань 0.25мм', 'Лакоткань 0.25', 5, 3, '{"толщина_мм":0.25,"ширина_мм":1000,"класс":"F","гост":"2110-78"}', 200.00, 40.00, 'Склад №4, Секция Г', 2, 60.00, 'BYN'),
('INSUL-PAPER-NOMEX', 'Бумага электроизоляционная Nomex', 'Бумага Nomex', 5, 2, '{"толщина_мм":0.25,"ширина_мм":1000,"класс":"H","производитель":"DuPont"}', 180.00, 35.00, 'Склад №4, Секция Г', 2, 120.00, 'BYN'),
('INSUL-TUBE-HEAT', 'Трубка термоусадочная', 'Трубка ТУТ', 5, 3, '{"коэффициент_усадки":"2:1","voltage_kv":1,"класс":"F"}', 500.00, 100.00, 'Склад №4, Секция Г', 2, 8.50, 'BYN'),
-- Пропиточные лаки
('LACQUER-IMPREG-MP', 'Лак пропиточный МП-95', 'Лак МП-95', 5, 4, '{"тип":"пропиточный","вязкость_с":"40-60","время_сушки_ч":"2","гост":"801-78"}', 350.00, 70.00, 'Склад №4, Секция Д', 2, 85.00, 'BYN'),
('LACQUER-IMPREG-FL', 'Лак пропиточный ФЛ-98', 'Лак ФЛ-98', 5, 4, '{"тип":"пропиточный","вязкость_с":"35-50","время_сушки_ч":"1.5","гост":"801-78"}', 280.00, 55.00, 'Склад №4, Секция Д', 2, 95.00, 'BYN'),
('LACQUER-ENAMEL-PE', 'Лак эмальпроводный ПЭ-933', 'Лак ПЭ-933', 5, 4, '{"тип":"эмальпроводный","диаметр_провода_мм":"0.1-2.5","гост":"32336-2014"}', 420.00, 85.00, 'Склад №4, Секция Д', 2, 110.00, 'BYN'),
-- Обмоточные провода
('WIRE-ENAMEL-0.50', 'Провод обмоточный ПЭТВ-2 0.50мм', 'Провод 0.50', 6, 3, '{"диаметр_мм":0.50,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 85.00, 15.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.63', 'Провод обмоточный ПЭТВ-2 0.63мм', 'Провод 0.63', 6, 3, '{"диаметр_мм":0.63,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 75.00, 13.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.71', 'Провод обмоточный ПЭТВ-2 0.71мм', 'Провод 0.71', 6, 3, '{"диаметр_мм":0.71,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 68.00, 12.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.80', 'Провод обмоточный ПЭТВ-2 0.80мм', 'Провод 0.80', 6, 3, '{"диаметр_мм":0.80,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 62.00, 11.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.90', 'Провод обмоточный ПЭТВ-2 0.90мм', 'Провод 0.90', 6, 3, '{"диаметр_мм":0.90,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 55.00, 10.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.00', 'Провод обмоточный ПЭТВ-2 1.00мм', 'Провод 1.00', 6, 3, '{"диаметр_мм":1.00,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 50.00, 9.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.12', 'Провод обмоточный ПЭТВ-2 1.12мм', 'Провод 1.12', 6, 3, '{"диаметр_мм":1.12,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 45.00, 8.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.25', 'Провод обмоточный ПЭТВ-2 1.25мм', 'Провод 1.25', 6, 3, '{"диаметр_мм":1.25,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 42.00, 7.50, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.40', 'Провод обмоточный ПЭТВ-2 1.40мм', 'Провод 1.40', 6, 3, '{"диаметр_мм":1.40,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 38.00, 7.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.60', 'Провод обмоточный ПЭТВ-2 1.60мм', 'Провод 1.60', 6, 3, '{"диаметр_мм":1.60,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 35.00, 6.50, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.80', 'Провод обмоточный ПЭТВ-2 1.80мм', 'Провод 1.80', 6, 3, '{"диаметр_мм":1.80,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 32.00, 6.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-2.00', 'Провод обмоточный ПЭТВ-2 2.00мм', 'Провод 2.00', 6, 3, '{"диаметр_мм":2.00,"изоляция":"ПЭТВ-2","класс":"F","гост":"32336-2014"}', 28.00, 5.50, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
-- Краски и покрытия
('PAINT-POLYMER', 'Краска порошковая полиэфирная', 'Краска порошок', 5, 2, '{"тип":"порошковая","цвет":"серая RAL 7035","покрытие":"полуматовая"}', 450.00, 80.00, 'Склад №6, Секция А', 1, 12.50, 'BYN'),
('PAINT-ENAMEL', 'Эмаль ПФ-115', 'Эмаль ПФ-115', 5, 4, '{"тип":"масляная","цвет":"серая","гост":"6465-76"}', 380.00, 70.00, 'Склад №6, Секция А', 1, 8.50, 'BYN'),
('PAINT-PRIMER', 'Грунтовка ГФ-021', 'Грунтовка ГФ-021', 5, 4, '{"тип":"грунтовка","цвет":"красно-коричневая","гост":"25129-82"}', 520.00, 100.00, 'Склад №6, Секция А', 1, 6.50, 'BYN'),
-- Смазочные материалы
('LUBE-GREASE-LITO', 'Смазка Литол-24', 'Литол-24', 5, 2, '{"тип":"пластичная","температурный_диапазон_ц":"-40..+120","гост":"21150-75"}', 180.00, 35.00, 'Склад №6, Секция Б', 1, 5.50, 'BYN'),
('LUBE-GREASE-UNIO', 'Смазка Униол-2М', 'Униол-2М', 5, 2, '{"тип":"пластичная","температурный_диапазон_ц":"-50..+150","гост":"21150-75"}', 150.00, 30.00, 'Склад №6, Секция Б', 1, 8.50, 'BYN'),
('LUBE-OIL-IND', 'Масло индустриальное И-20А', 'Масло И-20А', 5, 4, '{"тип":"индустриальное","viscosity_cSt":"20","гост":"20799-88"}', 220.00, 45.00, 'Склад №6, Секция Б', 1, 4.50, 'BYN'),
('LUBE-OIL-TRANS', 'Масло трансформаторное ТКп', 'Масло ТКп', 5, 4, '{"тип":"трансформаторное","температура_застывания_ц":"-45","гост":"10121-75"}', 280.00, 55.00, 'Склад №6, Секция Б', 1, 5.50, 'BYN');

INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
-- Общепромышленные двигатели
('ADM-56A2', 'Двигатель АДМ 56A2', 2, 1, '{"мощность_квт":0.18,"обороты_мин":3000,"напряжение_в":380,"габарит":"56A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm56a2.jpg', 180.00, 'BYN', TRUE),
('ADM-56B2', 'Двигатель АДМ 56B2', 2, 1, '{"мощность_квт":0.25,"обороты_мин":3000,"напряжение_в":380,"габарит":"56B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm56b2.jpg', 200.00, 'BYN', TRUE),
('ADM-56A4', 'Двигатель АДМ 56A4', 2, 1, '{"мощность_квт":0.12,"обороты_мин":1500,"напряжение_в":380,"габарит":"56A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm56a4.jpg', 175.00, 'BYN', TRUE),
('ADM-56B4', 'Двигатель АДМ 56B4', 2, 1, '{"мощность_квт":0.18,"обороты_мин":1500,"напряжение_в":380,"габарит":"56B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm56b4.jpg', 195.00, 'BYN', TRUE),
('ADM-63A2', 'Двигатель АДМ 63A2', 2, 1, '{"мощность_квт":0.37,"обороты_мин":3000,"напряжение_в":380,"габарит":"63A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm63a2.jpg', 220.00, 'BYN', TRUE),
('ADM-63B2', 'Двигатель АДМ 63B2', 2, 1, '{"мощность_квт":0.55,"обороты_мин":3000,"напряжение_в":380,"габарит":"63B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm63b2.jpg', 250.00, 'BYN', TRUE),
('ADM-63A4', 'Двигатель АДМ 63A4', 2, 1, '{"мощность_квт":0.25,"обороты_мин":1500,"напряжение_в":380,"габарит":"63A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm63a4.jpg', 215.00, 'BYN', TRUE),
('ADM-63B4', 'Двигатель АДМ 63B4', 2, 1, '{"мощность_квт":0.37,"обороты_мин":1500,"напряжение_в":380,"габарит":"63B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm63b4.jpg', 240.00, 'BYN', TRUE),
('ADM-71A2', 'Двигатель АДМ 71A2', 2, 1, '{"мощность_квт":0.55,"обороты_мин":3000,"напряжение_в":380,"габарит":"71A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm71a2.jpg', 280.00, 'BYN', TRUE),
('ADM-71B2', 'Двигатель АДМ 71B2', 2, 1, '{"мощность_квт":0.75,"обороты_мин":3000,"напряжение_в":380,"габарит":"71B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm71b2.jpg', 310.00, 'BYN', TRUE),
('ADM-71A4', 'Двигатель АДМ 71A4', 2, 1, '{"мощность_квт":0.37,"обороты_мин":1500,"напряжение_в":380,"габарит":"71A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm71a4.jpg', 275.00, 'BYN', TRUE),
('ADM-71B4', 'Двигатель АДМ 71B4', 2, 1, '{"мощность_квт":0.55,"обороты_мин":1500,"напряжение_в":380,"габарит":"71B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm71b4.jpg', 300.00, 'BYN', TRUE),
('ADM-80A2', 'Двигатель АДМ 80A2', 2, 1, '{"мощность_квт":1.5,"обороты_мин":3000,"напряжение_в":380,"габарит":"80A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm80a2.jpg', 380.00, 'BYN', TRUE),
('ADM-80B2', 'Двигатель АДМ 80B2', 2, 1, '{"мощность_квт":2.2,"обороты_мин":3000,"напряжение_в":380,"габарит":"80B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm80b2.jpg', 420.00, 'BYN', TRUE),
('ADM-80A4', 'Двигатель АДМ 80A4', 2, 1, '{"мощность_квт":1.1,"обороты_мин":1500,"напряжение_в":380,"габарит":"80A","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm80a4.jpg', 350.00, 'BYN', TRUE),
('ADM-80B4', 'Двигатель АДМ 80B4', 2, 1, '{"мощность_квт":1.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"80B","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm80b4.jpg', 390.00, 'BYN', TRUE),
('ADM-90L2', 'Двигатель АДМ 90L2', 2, 1, '{"мощность_квт":3.0,"обороты_мин":3000,"напряжение_в":380,"габарит":"90L","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm90l2.jpg', 550.00, 'BYN', TRUE),
('ADM-90L4', 'Двигатель АДМ 90L4', 2, 1, '{"мощность_квт":2.2,"обороты_мин":1500,"напряжение_в":380,"габарит":"90L","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm90l4.jpg', 520.00, 'BYN', TRUE),
('ADM-90L6', 'Двигатель АДМ 90L6', 2, 1, '{"мощность_квт":1.5,"обороты_мин":1000,"напряжение_в":380,"габарит":"90L","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm90l6.jpg', 580.00, 'BYN', TRUE),
('ADM-100S2', 'Двигатель АДМ 100S2', 2, 1, '{"мощность_квт":4.0,"обороты_мин":3000,"напряжение_в":380,"габарит":"100S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm100s2.jpg', 680.00, 'BYN', TRUE),
('ADM-100L2', 'Двигатель АДМ 100L2', 2, 1, '{"мощность_квт":5.5,"обороты_мин":3000,"напряжение_в":380,"габарит":"100L","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm100l2.jpg', 750.00, 'BYN', TRUE),
('ADM-100S4', 'Двигатель АДМ 100S4', 2, 1, '{"мощность_квт":3.0,"обороты_мин":1500,"напряжение_в":380,"габарит":"100S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm100s4.jpg', 650.00, 'BYN', TRUE),
('ADM-100L4', 'Двигатель АДМ 100L4', 2, 1, '{"мощность_квт":4.0,"обороты_мин":1500,"напряжение_в":380,"габарит":"100L","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm100l4.jpg', 780.00, 'BYN', TRUE),
('ADM-100L6', 'Двигатель АДМ 100L6', 2, 1, '{"мощность_квт":2.2,"обороты_мин":1000,"напряжение_в":380,"габарит":"100L","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm100l6.jpg', 820.00, 'BYN', TRUE),
('ADM-100L8', 'Двигатель АДМ 100L8', 2, 1, '{"мощность_квт":1.5,"обороты_мин":750,"напряжение_в":380,"габарит":"100L","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm100l8.jpg', 900.00, 'BYN', TRUE),
('ADM-112MA2', 'Двигатель АДМ 112MA2', 2, 1, '{"мощность_квт":5.5,"обороты_мин":3000,"напряжение_в":380,"габарит":"112M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm112ma2.jpg', 920.00, 'BYN', TRUE),
('ADM-112MB2', 'Двигатель АДМ 112MB2', 2, 1, '{"мощность_квт":7.5,"обороты_мин":3000,"напряжение_в":380,"габарит":"112M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm112mb2.jpg', 1050.00, 'BYN', TRUE),
('ADM-112MA4', 'Двигатель АДМ 112MA4', 2, 1, '{"мощность_квт":4.0,"обороты_мин":1500,"напряжение_в":380,"габарит":"112M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm112ma4.jpg', 880.00, 'BYN', TRUE),
('ADM-112MB4', 'Двигатель АДМ 112MB4', 2, 1, '{"мощность_квт":5.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"112M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm112mb4.jpg', 980.00, 'BYN', TRUE),
('ADM-112MA6', 'Двигатель АДМ 112MA6', 2, 1, '{"мощность_квт":3.0,"обороты_мин":1000,"напряжение_в":380,"габарит":"112M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm112ma6.jpg', 1020.00, 'BYN', TRUE),
('ADM-112MB6', 'Двигатель АДМ 112MB6', 2, 1, '{"мощность_квт":4.0,"обороты_мин":1000,"напряжение_в":380,"габарит":"112M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm112mb6.jpg', 1120.00, 'BYN', TRUE),
('ADM-132S2', 'Двигатель АДМ 132S2', 2, 1, '{"мощность_квт":7.5,"обороты_мин":3000,"напряжение_в":380,"габарит":"132S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm132s2.jpg', 1250.00, 'BYN', TRUE),
('ADM-132M2', 'Двигатель АДМ 132M2', 2, 1, '{"мощность_квт":11,"обороты_мин":3000,"напряжение_в":380,"габарит":"132M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm132m2.jpg', 1450.00, 'BYN', TRUE),
('ADM-132S4', 'Двигатель АДМ 132S4', 2, 1, '{"мощность_квт":5.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"132S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm132s4.jpg', 1180.00, 'BYN', TRUE),
('ADM-132M4', 'Двигатель АДМ 132M4', 2, 1, '{"мощность_квт":7.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"132M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm132m4.jpg', 1350.00, 'BYN', TRUE),
('ADM-132S6', 'Двигатель АДМ 132S6', 2, 1, '{"мощность_квт":4.0,"обороты_мин":1000,"напряжение_в":380,"габарит":"132S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm132s6.jpg', 1380.00, 'BYN', TRUE),
('ADM-132M6', 'Двигатель АДМ 132M6', 2, 1, '{"мощность_квт":5.5,"обороты_мин":1000,"напряжение_в":380,"габарит":"132M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm132m6.jpg', 1520.00, 'BYN', TRUE),
('ADM-160S2', 'Двигатель АДМ 160S2', 2, 1, '{"мощность_квт":15,"обороты_мин":3000,"напряжение_в":380,"габарит":"160S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm160s2.jpg', 1850.00, 'BYN', TRUE),
('ADM-160M2', 'Двигатель АДМ 160M2', 2, 1, '{"мощность_квт":18.5,"обороты_мин":3000,"напряжение_в":380,"габарит":"160M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm160m2.jpg', 2100.00, 'BYN', TRUE),
('ADM-160S4', 'Двигатель АДМ 160S4', 2, 1, '{"мощность_квт":11,"обороты_мин":1500,"напряжение_в":380,"габарит":"160S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm160s4.jpg', 1750.00, 'BYN', TRUE),
('ADM-160M4', 'Двигатель АДМ 160M4', 2, 1, '{"мощность_квт":15,"обороты_мин":1500,"напряжение_в":380,"габарит":"160M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm160m4.jpg', 1950.00, 'BYN', TRUE),
('ADM-160S6', 'Двигатель АДМ 160S6', 2, 1, '{"мощность_квт":7.5,"обороты_мин":1000,"напряжение_в":380,"габарит":"160S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm160s6.jpg', 1920.00, 'BYN', TRUE),
('ADM-160M6', 'Двигатель АДМ 160M6', 2, 1, '{"мощность_квт":11,"обороты_мин":1000,"напряжение_в":380,"габарит":"160M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm160m6.jpg', 2150.00, 'BYN', TRUE),
('ADM-160M8', 'Двигатель АДМ 160M8', 2, 1, '{"мощность_квт":7.5,"обороты_мин":750,"напряжение_в":380,"габарит":"160M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm160m8.jpg', 2380.00, 'BYN', TRUE),
('ADM-180S2', 'Двигатель АДМ 180S2', 2, 1, '{"мощность_квт":22,"обороты_мин":3000,"напряжение_в":380,"габарит":"180S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm180s2.jpg', 2450.00, 'BYN', TRUE),
('ADM-180M2', 'Двигатель АДМ 180M2', 2, 1, '{"мощность_квт":30,"обороты_мин":3000,"напряжение_в":380,"габарит":"180M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm180m2.jpg', 2850.00, 'BYN', TRUE),
('ADM-180S4', 'Двигатель АДМ 180S4', 2, 1, '{"мощность_квт":18.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"180S","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm180s4.jpg', 2350.00, 'BYN', TRUE),
('ADM-180M4', 'Двигатель АДМ 180M4', 2, 1, '{"мощность_квт":22,"обороты_мин":1500,"напряжение_в":380,"габарит":"180M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm180m4.jpg', 2650.00, 'BYN', TRUE),
('ADM-180M6', 'Двигатель АДМ 180M6', 2, 1, '{"мощность_квт":15,"обороты_мин":1000,"напряжение_в":380,"габарит":"180M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm180m6.jpg', 2750.00, 'BYN', TRUE),
('ADM-180M8', 'Двигатель АДМ 180M8', 2, 1, '{"мощность_квт":11,"обороты_мин":750,"напряжение_в":380,"габарит":"180M","класс_эффективности":"IE2","монтаж":"IM1081"}', 'motor_adm180m8.jpg', 2950.00, 'BYN', TRUE),
-- Взрывозащищенные двигатели
('AID-63A4', 'Двигатель взрывозащищенный АИД 63A4', 3, 1, '{"мощность_квт":0.25,"обороты_мин":1500,"напряжение_в":380,"габарит":"63A","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid63a4.jpg', 450.00, 'BYN', TRUE),
('AID-71A4', 'Двигатель взрывозащищенный АИД 71A4', 3, 1, '{"мощность_квт":0.37,"обороты_мин":1500,"напряжение_в":380,"габарит":"71A","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid71a4.jpg', 520.00, 'BYN', TRUE),
('AID-80A4', 'Двигатель взрывозащищенный АИД 80A4', 3, 1, '{"мощность_квт":1.1,"обороты_мин":1500,"напряжение_в":380,"габарит":"80A","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid80a4.jpg', 680.00, 'BYN', TRUE),
('AID-90L4', 'Двигатель взрывозащищенный АИД 90L4', 3, 1, '{"мощность_квт":2.2,"обороты_мин":1500,"напряжение_в":380,"габарит":"90L","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid90l4.jpg', 850.00, 'BYN', TRUE),
('AID-100L4', 'Двигатель взрывозащищенный АИД 100L4', 3, 1, '{"мощность_квт":4.0,"обороты_мин":1500,"напряжение_в":380,"габарит":"100L","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid100l4.jpg', 1150.00, 'BYN', TRUE),
('AID-112M4', 'Двигатель взрывозащищенный АИД 112M4', 3, 1, '{"мощность_квт":5.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"112M","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid112m4.jpg', 1450.00, 'BYN', TRUE),
('AID-132S4', 'Двигатель взрывозащищенный АИД 132S4', 3, 1, '{"мощность_квт":7.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"132S","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid132s4.jpg', 1850.00, 'BYN', TRUE),
('AID-132M4', 'Двигатель взрывозащищенный АИД 132M4', 3, 1, '{"мощность_квт":11,"обороты_мин":1500,"напряжение_в":380,"габарит":"132M","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid132m4.jpg', 2150.00, 'BYN', TRUE),
('AID-160S4', 'Двигатель взрывозащищенный АИД 160S4', 3, 1, '{"мощность_квт":15,"обороты_мин":1500,"напряжение_в":380,"габарит":"160S","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid160s4.jpg', 2850.00, 'BYN', TRUE),
('AID-160M4', 'Двигатель взрывозащищенный АИД 160M4', 3, 1, '{"мощность_квт":18.5,"обороты_мин":1500,"напряжение_в":380,"габарит":"160M","маркировка_взрывозащиты":"1ExdIIBT4","класс_эффективности":"IE2"}', 'motor_aid160m4.jpg', 3250.00, 'BYN', TRUE),
-- Крановые двигатели
('MTF-112M6', 'Двигатель крановый МТF 112M6', 4, 1, '{"мощность_квт":5.0,"обороты_мин":900,"напряжение_в":380,"габарит":"112M","режим_работы":"S3","класс_изоляции":"H"}', 'motor_mtf112m6.jpg', 1650.00, 'BYN', TRUE),
('MTF-132M6', 'Двигатель крановый МТF 132M6', 4, 1, '{"мощность_квт":7.5,"обороты_мин":900,"напряжение_в":380,"габарит":"132M","режим_работы":"S3","класс_изоляции":"H"}', 'motor_mtf132m6.jpg', 2050.00, 'BYN', TRUE),
('MTF-160M6', 'Двигатель крановый МТF 160M6', 4, 1, '{"мощность_квт":11,"обороты_мин":900,"напряжение_в":380,"габарит":"160M","режим_работы":"S3","класс_изоляции":"H"}', 'motor_mtf160m6.jpg', 2750.00, 'BYN', TRUE),
('MTF-160L6', 'Двигатель крановый МТF 160L6', 4, 1, '{"мощность_квт":15,"обороты_мин":900,"напряжение_в":380,"габарит":"160L","режим_работы":"S3","класс_изоляции":"H"}', 'motor_mtf160l6.jpg', 3150.00, 'BYN', TRUE),
('MTF-180M6', 'Двигатель крановый МТF 180M6', 4, 1, '{"мощность_квт":18.5,"обороты_мин":900,"напряжение_в":380,"габарит":"180M","режим_работы":"S3","класс_изоляции":"H"}', 'motor_mtf180m6.jpg', 3650.00, 'BYN', TRUE),
('MTF-200M6', 'Двигатель крановый МТF 200M6', 4, 1, '{"мощность_квт":22,"обороты_мин":900,"напряжение_в":380,"габарит":"200M","режим_работы":"S3","класс_изоляции":"H"}', 'motor_mtf200m6.jpg', 4250.00, 'BYN', TRUE),
('MTF-225M6', 'Двигатель крановый МТF 225M6', 4, 1, '{"мощность_квт":30,"обороты_мин":900,"напряжение_в":380,"габарит":"225M","режим_работы":"S3","класс_изоляции":"H"}', 'motor_mtf225m6.jpg', 5150.00, 'BYN', TRUE),
-- Дизельные генераторы
('ADS-5000', 'Генератор дизельный 5кВт', 6, 1, '{"мощность_квт":5,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":65,"фазы":1}', 'gen_ads5000.jpg', 1200.00, 'BYN', TRUE),
('ADS-8000', 'Генератор дизельный 8кВт', 6, 1, '{"мощность_квт":8,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":68,"фазы":1}', 'gen_ads8000.jpg', 1650.00, 'BYN', TRUE),
('ADS-10000', 'Генератор дизельный 10кВт', 6, 1, '{"мощность_квт":10,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":70,"фазы":1}', 'gen_ads10000.jpg', 2100.00, 'BYN', TRUE),
('ADS-15000', 'Генератор дизельный 15кВт', 6, 1, '{"мощность_квт":15,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":72,"фазы":3}', 'gen_ads15000.jpg', 2850.00, 'BYN', TRUE),
('ADS-20000', 'Генератор дизельный 20кВт', 6, 1, '{"мощность_квт":20,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":74,"фазы":3}', 'gen_ads20000.jpg', 3500.00, 'BYN', TRUE),
('ADS-30000', 'Генератор дизельный 30кВт', 6, 1, '{"мощность_квт":30,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":76,"фазы":3}', 'gen_ads30000.jpg', 4800.00, 'BYN', TRUE),
('ADS-50000', 'Генератор дизельный 50кВт', 6, 1, '{"мощность_квт":50,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":78,"фазы":3}', 'gen_ads50000.jpg', 7200.00, 'BYN', TRUE),
('ADS-100000', 'Генератор дизельный 100кВт', 6, 1, '{"мощность_квт":100,"тип_топлива":"дизель","тип_запуска":"электростартер","шум_дб":82,"фазы":3}', 'gen_ads100000.jpg', 12500.00, 'BYN', TRUE),
-- Бензиновые генераторы
('ABS-2000', 'Генератор бензиновый 2кВт', 7, 1, '{"мощность_квт":2,"тип_топлива":"бензин","тип_запуска":"ручной","шум_дб":62,"фазы":1}', 'gen_abs2000.jpg', 450.00, 'BYN', TRUE),
('ABS-3500', 'Генератор бензиновый 3.5кВт', 7, 1, '{"мощность_квт":3.5,"тип_топлива":"бензин","тип_запуска":"ручной","шум_дб":65,"фазы":1}', 'gen_abs3500.jpg', 620.00, 'BYN', TRUE),
('ABS-5500', 'Генератор бензиновый 5.5кВт', 7, 1, '{"мощность_квт":5.5,"тип_топлива":"бензин","тип_запуска":"электростартер","шум_дб":68,"фазы":1}', 'gen_abs5500.jpg', 850.00, 'BYN', TRUE),
('ABS-7000', 'Генератор бензиновый 7кВт', 7, 1, '{"мощность_квт":7,"тип_топлива":"бензин","тип_запуска":"электростартер","шум_дб":70,"фазы":3}', 'gen_abs7000.jpg', 1150.00, 'BYN', TRUE),
-- Масляные трансформаторы
('TMG-25', 'Трансформатор ТМГ-25 кВА', 9, 1, '{"мощность_ква":25,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg25.jpg', 1850.00, 'BYN', TRUE),
('TMG-40', 'Трансформатор ТМГ-40 кВА', 9, 1, '{"мощность_ква":40,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg40.jpg', 2150.00, 'BYN', TRUE),
('TMG-63', 'Трансформатор ТМГ-63 кВА', 9, 1, '{"мощность_ква":63,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg63.jpg', 2550.00, 'BYN', TRUE),
('TMG-100', 'Трансформатор ТМГ-100 кВА', 9, 1, '{"мощность_ква":100,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg100.jpg', 3200.00, 'BYN', TRUE),
('TMG-160', 'Трансформатор ТМГ-160 кВА', 9, 1, '{"мощность_ква":160,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg160.jpg', 4100.00, 'BYN', TRUE),
('TMG-250', 'Трансформатор ТМГ-250 кВА', 9, 1, '{"мощность_ква":250,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg250.jpg', 5200.00, 'BYN', TRUE),
('TMG-400', 'Трансформатор ТМГ-400 кВА', 9, 1, '{"мощность_ква":400,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg400.jpg', 6800.00, 'BYN', TRUE),
('TMG-630', 'Трансформатор ТМГ-630 кВА', 9, 1, '{"мощность_ква":630,"напряжение_первичное_кв":10,"напряжение_вторичное_в":400,"охлаждение":"oil","соединение":"Yyn0"}', 'tr_tmg630.jpg', 8500.00, 'BYN', TRUE),
-- Сухие трансформаторы
('TS-25', 'Трансформатор ТС-25 кВА', 10, 1, '{"мощность_ква":25,"напряжение_первичное_кв":0.4,"напряжение_вторичное_в":230,"охлаждение":"air","соединение":"Single-phase"}', 'tr_ts25.jpg', 1250.00, 'BYN', TRUE),
('TS-40', 'Трансформатор ТС-40 кВА', 10, 1, '{"мощность_ква":40,"напряжение_первичное_кв":0.4,"напряжение_вторичное_в":230,"охлаждение":"air","соединение":"Single-phase"}', 'tr_ts40.jpg', 1550.00, 'BYN', TRUE),
('TS-63', 'Трансформатор ТС-63 кВА', 10, 1, '{"мощность_ква":63,"напряжение_первичное_кв":0.4,"напряжение_вторичное_в":230,"охлаждение":"air","соединение":"Single-phase"}', 'tr_ts63.jpg', 1950.00, 'BYN', TRUE),
('TS-100', 'Трансформатор ТС-100 кВА', 10, 1, '{"мощность_ква":100,"напряжение_первичное_кв":0.4,"напряжение_вторичное_в":230,"охлаждение":"air","соединение":"Single-phase"}', 'tr_ts100.jpg', 2650.00, 'BYN', TRUE),
-- Вводно-распределительные устройства
('VRU-100', 'ВРУ-100А', 12, 1, '{"ток_а":100,"напряжение_в":380,"степень_защиты_ip":"IP31","панели":1}', 'vru100.jpg', 850.00, 'BYN', TRUE),
('VRU-250', 'ВРУ-250А', 12, 1, '{"ток_а":250,"напряжение_в":380,"степень_защиты_ip":"IP31","панели":2}', 'vru250.jpg', 1450.00, 'BYN', TRUE),
('VRU-400', 'ВРУ-400А', 12, 1, '{"ток_а":400,"напряжение_в":380,"степень_защиты_ip":"IP31","панели":3}', 'vru400.jpg', 2150.00, 'BYN', TRUE),
('VRU-630', 'ВРУ-630А', 12, 1, '{"ток_а":630,"напряжение_в":380,"степень_защиты_ip":"IP31","панели":4}', 'vru630.jpg', 3250.00, 'BYN', TRUE),
-- Щиты управления
('SCH-CONTROL-1', 'Щит управления на 1 двигатель', 13, 1, '{"motors":1,"макс_ток_а":32,"напряжение_в":380,"степень_защиты_ip":"IP54","тип_стартера":"DOL"}', 'sch_ctrl1.jpg', 450.00, 'BYN', TRUE),
('SCH-CONTROL-3', 'Щит управления на 3 двигателя', 13, 1, '{"motors":3,"макс_ток_а":63,"напряжение_в":380,"степень_защиты_ip":"IP54","тип_стартера":"DOL"}', 'sch_ctrl3.jpg', 850.00, 'BYN', TRUE),
('SCH-CONTROL-5', 'Щит управления на 5 двигателей', 13, 1, '{"motors":5,"макс_ток_а":100,"напряжение_в":380,"степень_защиты_ip":"IP54","тип_стартера":"DOL"}', 'sch_ctrl5.jpg', 1350.00, 'BYN', TRUE),
('SCH-VFD-1', 'Щит управления с ЧП 1 двигатель', 13, 1, '{"motors":1,"макс_ток_а":32,"напряжение_в":380,"степень_защиты_ip":"IP54","тип_стартера":"VFD"}', 'sch_vfd1.jpg', 1250.00, 'BYN', TRUE),
('SCH-VFD-3', 'Щит управления с ЧП 3 двигателя', 13, 1, '{"motors":3,"макс_ток_а":63,"напряжение_в":380,"степень_защиты_ip":"IP54","тип_стартера":"VFD"}', 'sch_vfd3.jpg', 2450.00, 'BYN', TRUE),
-- Щиты освещения
('SCH-LIGHT-12', 'Щит освещения на 12 групп', 14, 1, '{"группы":12,"макс_ток_а":63,"напряжение_в":230,"степень_защиты_ip":"IP31"}', 'sch_light12.jpg', 550.00, 'BYN', TRUE),
('SCH-LIGHT-24', 'Щит освещения на 24 группы', 14, 1, '{"группы":24,"макс_ток_а":100,"напряжение_в":230,"степень_защиты_ip":"IP31"}', 'sch_light24.jpg', 850.00, 'BYN', TRUE),
('SCH-LIGHT-36', 'Щит освещения на 36 групп', 14, 1, '{"группы":36,"макс_ток_а":160,"напряжение_в":230,"степень_защиты_ip":"IP31"}', 'sch_light36.jpg', 1250.00, 'BYN', TRUE),
-- Запчасти - роторы
('ROTOR-80A4', 'Ротор для АДМ 80A4', 16, 1, '{"артикул_двигателя":"ADM-80A4","диаметр_мм":80,"длина_мм":120,"диаметр_вала_мм":19}', 'rotor_80a4.jpg', 120.00, 'BYN', TRUE),
('ROTOR-90L4', 'Ротор для АДМ 90L4', 16, 1, '{"артикул_двигателя":"ADM-90L4","диаметр_мм":95,"длина_мм":140,"диаметр_вала_мм":24}', 'rotor_90l4.jpg', 165.00, 'BYN', TRUE),
('ROTOR-100L4', 'Ротор для АДМ 100L4', 16, 1, '{"артикул_двигателя":"ADM-100L4","диаметр_мм":110,"длина_мм":160,"диаметр_вала_мм":28}', 'rotor_100l4.jpg', 220.00, 'BYN', TRUE),
('ROTOR-112M4', 'Ротор для АДМ 112M4', 16, 1, '{"артикул_двигателя":"ADM-112M4","диаметр_мм":125,"длина_мм":180,"диаметр_вала_мм":30}', 'rotor_112m4.jpg', 285.00, 'BYN', TRUE),
('ROTOR-132S4', 'Ротор для АДМ 132S4', 16, 1, '{"артикул_двигателя":"ADM-132S4","диаметр_мм":140,"длина_мм":200,"диаметр_вала_мм":35}', 'rotor_132s4.jpg', 350.00, 'BYN', TRUE),
('ROTOR-132M4', 'Ротор для АДМ 132M4', 16, 1, '{"артикул_двигателя":"ADM-132M4","диаметр_мм":140,"длина_мм":220,"диаметр_вала_мм":38}', 'rotor_132m4.jpg', 395.00, 'BYN', TRUE),
('ROTOR-160S4', 'Ротор для АДМ 160S4', 16, 1, '{"артикул_двигателя":"ADM-160S4","диаметр_мм":165,"длина_мм":240,"диаметр_вала_мм":42}', 'rotor_160s4.jpg', 485.00, 'BYN', TRUE),
('ROTOR-160M4', 'Ротор для АДМ 160M4', 16, 1, '{"артикул_двигателя":"ADM-160M4","диаметр_мм":165,"длина_мм":280,"диаметр_вала_мм":48}', 'rotor_160m4.jpg', 560.00, 'BYN', TRUE),
-- Запчасти - статоры
('STATOR-80A4', 'Статор для АДМ 80A4', 17, 1, '{"артикул_двигателя":"ADM-80A4","внешний_диаметр_мм":130,"внутренний_диаметр_мм":80,"длина_мм":100,"пазы":24}', 'stator_80a4.jpg', 145.00, 'BYN', TRUE),
('STATOR-90L4', 'Статор для АДМ 90L4', 17, 1, '{"артикул_двигателя":"ADM-90L4","внешний_диаметр_мм":155,"внутренний_диаметр_мм":95,"длина_мм":125,"пазы":36}', 'stator_90l4.jpg', 195.00, 'BYN', TRUE),
('STATOR-100L4', 'Статор для АДМ 100L4', 17, 1, '{"артикул_двигателя":"ADM-100L4","внешний_диаметр_мм":180,"внутренний_диаметр_мм":110,"длина_мм":145,"пазы":36}', 'stator_100l4.jpg', 265.00, 'BYN', TRUE),
('STATOR-112M4', 'Статор для АДМ 112M4', 17, 1, '{"артикул_двигателя":"ADM-112M4","внешний_диаметр_мм":200,"внутренний_диаметр_мм":125,"длина_мм":165,"пазы":48}', 'stator_112m4.jpg', 335.00, 'BYN', TRUE),
('STATOR-132S4', 'Статор для АДМ 132S4', 17, 1, '{"артикул_двигателя":"ADM-132S4","внешний_диаметр_мм":225,"внутренний_диаметр_мм":140,"длина_мм":180,"пазы":48}', 'stator_132s4.jpg', 420.00, 'BYN', TRUE),
('STATOR-132M4', 'Статор для АДМ 132M4', 17, 1, '{"артикул_двигателя":"ADM-132M4","внешний_диаметр_мм":225,"внутренний_диаметр_мм":140,"длина_мм":200,"пазы":48}', 'stator_132m4.jpg', 475.00, 'BYN', TRUE),
('STATOR-160S4', 'Статор для АДМ 160S4', 17, 1, '{"артикул_двигателя":"ADM-160S4","внешний_диаметр_мм":260,"внутренний_диаметр_мм":165,"длина_мм":220,"пазы":60}', 'stator_160s4.jpg', 585.00, 'BYN', TRUE),
('STATOR-160M4', 'Статор для АДМ 160M4', 17, 1, '{"артикул_двигателя":"ADM-160M4","внешний_диаметр_мм":260,"внутренний_диаметр_мм":165,"длина_мм":260,"пазы":60}', 'stator_160m4.jpg', 680.00, 'BYN', TRUE),
-- Запчасти - подшипниковые щиты
('SHIELD-FRONT-80', 'Щит подшипниковый передний 80', 18, 1, '{"габарит_двигателя":"80","позиция":"front","тип_подшипника":"6204"}', 'shield_front80.jpg', 45.00, 'BYN', TRUE),
('SHIELD-REAR-80', 'Щит подшипниковый задний 80', 18, 1, '{"габарит_двигателя":"80","позиция":"rear","тип_подшипника":"6204"}', 'shield_rear80.jpg', 45.00, 'BYN', TRUE),
('SHIELD-FRONT-90', 'Щит подшипниковый передний 90', 18, 1, '{"габарит_двигателя":"90","позиция":"front","тип_подшипника":"6205"}', 'shield_front90.jpg', 55.00, 'BYN', TRUE),
('SHIELD-REAR-90', 'Щит подшипниковый задний 90', 18, 1, '{"габарит_двигателя":"90","позиция":"rear","тип_подшипника":"6205"}', 'shield_rear90.jpg', 55.00, 'BYN', TRUE),
('SHIELD-FRONT-100', 'Щит подшипниковый передний 100', 18, 1, '{"габарит_двигателя":"100","позиция":"front","тип_подшипника":"6206"}', 'shield_front100.jpg', 68.00, 'BYN', TRUE),
('SHIELD-REAR-100', 'Щит подшипниковый задний 100', 18, 1, '{"габарит_двигателя":"100","позиция":"rear","тип_подшипника":"6206"}', 'shield_rear100.jpg', 68.00, 'BYN', TRUE),
('SHIELD-FRONT-112', 'Щит подшипниковый передний 112', 18, 1, '{"габарит_двигателя":"112","позиция":"front","тип_подшипника":"6207"}', 'shield_front112.jpg', 82.00, 'BYN', TRUE),
('SHIELD-REAR-112', 'Щит подшипниковый задний 112', 18, 1, '{"габарит_двигателя":"112","позиция":"rear","тип_подшипника":"6207"}', 'shield_rear112.jpg', 82.00, 'BYN', TRUE),
('SHIELD-FRONT-132', 'Щит подшипниковый передний 132', 18, 1, '{"габарит_двигателя":"132","позиция":"front","тип_подшипника":"6208"}', 'shield_front132.jpg', 105.00, 'BYN', TRUE),
('SHIELD-REAR-132', 'Щит подшипниковый задний 132', 18, 1, '{"габарит_двигателя":"132","позиция":"rear","тип_подшипника":"6209"}', 'shield_rear132.jpg', 105.00, 'BYN', TRUE),
('SHIELD-FRONT-160', 'Щит подшипниковый передний 160', 18, 1, '{"габарит_двигателя":"160","позиция":"front","тип_подшипника":"6211"}', 'shield_front160.jpg', 145.00, 'BYN', TRUE),
('SHIELD-REAR-160', 'Щит подшипниковый задний 160', 18, 1, '{"габарит_двигателя":"160","позиция":"rear","тип_подшипника":"6211"}', 'shield_rear160.jpg', 145.00, 'BYN', TRUE),
-- Запчасти - клеммные коробки
('BOX-80', 'Коробка клеммная для 80', 19, 1, '{"габарит_двигателя":"80","клеммы":6,"макс_ток_а":20}', 'box_80.jpg', 25.00, 'BYN', TRUE),
('BOX-90', 'Коробка клеммная для 90', 19, 1, '{"габарит_двигателя":"90","клеммы":6,"макс_ток_а":32}', 'box_90.jpg', 32.00, 'BYN', TRUE),
('BOX-100', 'Коробка клеммная для 100', 19, 1, '{"габарит_двигателя":"100","клеммы":6,"макс_ток_а":50}', 'box_100.jpg', 42.00, 'BYN', TRUE),
('BOX-112', 'Коробка клеммная для 112', 19, 1, '{"габарит_двигателя":"112","клеммы":6,"макс_ток_а":63}', 'box_112.jpg', 55.00, 'BYN', TRUE),
('BOX-132', 'Коробка клеммная для 132', 19, 1, '{"габарит_двигателя":"132","клеммы":6,"макс_ток_а":100}', 'box_132.jpg', 72.00, 'BYN', TRUE),
('BOX-160', 'Коробка клеммная для 160', 19, 1, '{"габарит_двигателя":"160","клеммы":6,"макс_ток_а":160}', 'box_160.jpg', 95.00, 'BYN', TRUE);

-- Этапы производства
INSERT INTO `production_stages` (`name`, `code`, `description`, `sort_order`, `color`) VALUES
('Заготовка', 'CUTTING', 'Раскрой и заготовка материалов', 1, '#e74c3c'),
('Токарная обработка', 'TURNING', 'Токарные работы', 2, '#e67e22'),
('Фрезерная обработка', 'MILLING', 'Фрезерные работы', 3, '#f39c12'),
('Сверление', 'DRILLING', 'Сверлильные работы', 4, '#f1c40f'),
('Сварка', 'WELDING', 'Сварочные работы', 5, '#2ecc71'),
('Шлифовка', 'GRINDING', 'Шлифовальные работы', 6, '#1abc9c'),
('Покраска', 'PAINTING', 'Малярные работы', 7, '#3498db'),
('Сборка', 'ASSEMBLY', 'Сборочные работы', 8, '#9b59b6'),
('Балансировка', 'BALANCING', 'Балансировка ротора', 9, '#34495e'),
('Испытания', 'TESTING', 'Приемо-сдаточные испытания', 10, '#16a085'),
('Упаковка', 'PACKING', 'Упаковка готовой продукции', 11, '#27ae60'),
('ОТК', 'QC', 'Контроль качества', 12, '#8e44ad');

-- Маршрутная карта для двигателя АДМ 80A4
INSERT INTO `route_cards` (`product_id`, `name`, `version`, `description`, `total_time_hours`) VALUES
(1, 'Маршрутная карта АДМ 80A4', '1.0', 'Полный технологический процесс изготовления двигателя АДМ 80A4', 24.5);

INSERT INTO `route_card_operations` (`route_card_id`, `operation_number`, `stage_id`, `name`, `description`, `work_center`, `equipment`, `time_norm_hours`, `sort_order`) VALUES
(1, 10, 1, 'Раскрой вала', 'Раскрой прутка на заготовку вала', 'Заготовительный', 'Пила ленточная', 0.5, 1),
(1, 20, 2, 'Токарная обработка вала', 'Проточка вала по чертежу', 'Токарный участок', 'Станок токарный 16К20', 2.0, 2),
(1, 30, 1, 'Раскрой корпуса', 'Раскрой заготовки корпуса', 'Заготовительный', 'Пила ленточная', 0.5, 3),
(1, 40, 2, 'Токарная обработка корпуса', 'Расточка корпуса', 'Токарный участок', 'Станок токарный 16К20', 3.0, 4),
(1, 50, 4, 'Сверление отверстий', 'Сверление крепежных отверстий', 'Сверлильный участок', 'Станок сверлильный', 1.0, 5),
(1, 60, 6, 'Шлифовка вала', 'Чистовая шлифовка посадочных мест', 'Шлифовальный участок', 'Станок шлифовальный', 1.5, 6),
(1, 70, 5, 'Сварка клеммной коробки', 'Приварка шпилек', 'Сварочный участок', 'Полуавтомат сварочный', 0.5, 7),
(1, 80, 7, 'Покраска корпуса', 'Нанесение защитного покрытия', 'Малярный участок', 'Камера покрасочная', 1.5, 8),
(1, 90, 8, 'Сборка статора', 'Укладка обмотки статора', 'Сборочный участок', 'Приспособление для намотки', 4.0, 9),
(1, 100, 8, 'Сборка ротора', 'Прессование ротора', 'Сборочный участок', 'Пресс гидравлический', 1.0, 10),
(1, 110, 8, 'Общая сборка', 'Сборка двигателя', 'Сборочный участок', 'Конвейер сборочный', 2.0, 11),
(1, 120, 9, 'Балансировка', 'Динамическая балансировка ротора', 'Балансировочный участок', 'Станок балансировочный', 1.0, 12),
(1, 130, 10, 'Испытания', 'Приемо-сдаточные испытания', 'Испытательная станция', 'Стенд испытательный', 2.0, 13),
(1, 140, 12, 'ОТК', 'Выходной контроль качества', 'Отдел ОТК', '-', 0.5, 14),
(1, 150, 11, 'Упаковка', 'Консервация и упаковка', 'Упаковочный участок', '-', 0.5, 15);

-- Заказы
INSERT INTO `orders` (`order_number`, `customer_id`, `responsible_user_id`, `status`, `order_date`, `delivery_date`, `total_amount`, `notes`) VALUES
('ORD-2025-001', 4, 3, 'processing', '2025-01-15', '2025-02-15', 10500.00, 'Срочный заказ'),
('ORD-2025-002', 5, 3, 'new', '2025-01-20', '2025-03-01', 7800.00, 'По договору №45'),
('ORD-2025-003', 4, 3, 'ready', '2025-01-10', '2025-02-10', 3500.00, 'Самовывоз');

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(1, 1, 10, 350.00, 3500.00, 'in_progress'),
(1, 2, 5, 520.00, 2600.00, 'not_started'),
(1, 3, 2, 780.00, 1560.00, 'not_started'),
(1, 4, 2, 1200.00, 2400.00, 'not_started'),
(2, 3, 10, 780.00, 7800.00, 'not_started'),
(3, 1, 10, 350.00, 3500.00, 'completed');

-- Производственные задания
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `responsible_id`, `worker_id`) VALUES
('TASK-2025-001', 1, 1, 1, 1, 10, 'in_progress', 'high', '2025-01-16', '2025-02-10', 4, 6),
('TASK-2025-002', 1, 2, 2, NULL, 5, 'planned', 'normal', '2025-01-20', '2025-02-15', 4, NULL),
('TASK-2025-003', 3, 6, 1, 1, 10, 'completed', 'normal', '2025-01-11', '2025-01-25', 4, 6);

-- Этапы выполнения заданий
INSERT INTO `production_task_stages` (`task_id`, `stage_id`, `status`, `started_at`, `completed_at`, `worker_id`, `time_spent_hours`, `quantity_passed`) VALUES
(1, 1, 'completed', '2025-01-16 08:00:00', '2025-01-16 12:00:00', 6, 1.0, 10),
(1, 2, 'completed', '2025-01-16 13:00:00', '2025-01-17 17:00:00', 6, 4.0, 10),
(1, 3, 'completed', '2025-01-18 08:00:00', '2025-01-18 12:00:00', 6, 1.0, 10),
(1, 4, 'completed', '2025-01-18 13:00:00', '2025-01-18 17:00:00', 6, 1.0, 10),
(1, 5, 'in_progress', '2025-01-19 08:00:00', NULL, 6, 0.5, 5),
(1, 6, 'pending', NULL, NULL, NULL, 0, 0),
(1, 7, 'pending', NULL, NULL, NULL, 0, 0),
(1, 8, 'pending', NULL, NULL, NULL, 0, 0),
(1, 9, 'pending', NULL, NULL, NULL, 0, 0),
(1, 10, 'pending', NULL, NULL, NULL, 0, 0),
(1, 11, 'pending', NULL, NULL, NULL, 0, 0),
(1, 12, 'pending', NULL, NULL, NULL, 0, 0),
(1, 13, 'pending', NULL, NULL, NULL, 0, 0),
(1, 14, 'pending', NULL, NULL, NULL, 0, 0),
(1, 15, 'pending', NULL, NULL, NULL, 0, 0),
(3, 1, 'completed', '2025-01-11 08:00:00', '2025-01-11 12:00:00', 6, 1.0, 10),
(3, 2, 'completed', '2025-01-11 13:00:00', '2025-01-12 17:00:00', 6, 4.0, 10),
(3, 3, 'completed', '2025-01-13 08:00:00', '2025-01-13 12:00:00', 6, 1.0, 10),
(3, 4, 'completed', '2025-01-13 13:00:00', '2025-01-13 17:00:00', 6, 1.0, 10),
(3, 5, 'completed', '2025-01-14 08:00:00', '2025-01-14 12:00:00', 6, 1.0, 10),
(3, 6, 'completed', '2025-01-14 13:00:00', '2025-01-15 10:00:00', 6, 1.5, 10),
(3, 7, 'completed', '2025-01-15 11:00:00', '2025-01-15 12:00:00', 6, 0.5, 10),
(3, 8, 'completed', '2025-01-15 13:00:00', '2025-01-16 10:00:00', 6, 1.5, 10),
(3, 9, 'completed', '2025-01-16 11:00:00', '2025-01-18 17:00:00', 6, 4.0, 10),
(3, 10, 'completed', '2025-01-19 08:00:00', '2025-01-19 12:00:00', 6, 1.0, 10),
(3, 11, 'completed', '2025-01-19 13:00:00', '2025-01-20 17:00:00', 6, 2.0, 10),
(3, 12, 'completed', '2025-01-21 08:00:00', '2025-01-21 12:00:00', 6, 1.0, 10),
(3, 13, 'completed', '2025-01-21 13:00:00', '2025-01-22 17:00:00', 6, 2.0, 10),
(3, 14, 'completed', '2025-01-23 08:00:00', '2025-01-23 12:00:00', 6, 0.5, 10),
(3, 15, 'completed', '2025-01-23 13:00:00', '2025-01-23 17:00:00', 6, 0.5, 10);

-- Материалы для заданий
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_reserved`, `quantity_used`, `unit_cost`, `total_cost`, `status`) VALUES
-- Задание TASK-2025-001 (Двигатель АДМ80А4)
(1, 1, 50.0, 50.0, 30.0, 2.50, 125.00, 'issued'),
(1, 2, 10.0, 10.0, 5.0, 3.20, 32.00, 'issued'),
(1, 5, 200.0, 200.0, 100.0, 1.20, 240.00, 'issued'),
(1, 6, 100.0, 100.0, 50.0, 0.35, 35.00, 'issued'),
(1, 7, 100.0, 100.0, 50.0, 0.15, 15.00, 'issued'),
(1, 8, 20.0, 20.0, 10.0, 8.50, 170.00, 'issued'),
-- Задание TASK-2025-002 (Двигатель АДМ90L4)
(2, 3, 60.0, 60.0, 0.0, 4.80, 288.00, 'reserved'),
(2, 4, 15.0, 15.0, 0.0, 7.20, 108.00, 'reserved'),
(2, 9, 250.0, 250.0, 0.0, 1.90, 475.00, 'reserved'),
(2, 15, 150.0, 150.0, 0.0, 0.42, 63.00, 'reserved'),
(2, 20, 150.0, 150.0, 0.0, 0.22, 33.00, 'reserved'),
(2, 25, 30.0, 30.0, 0.0, 0.28, 8.40, 'reserved'),
(2, 40, 25.0, 25.0, 0.0, 7.00, 175.00, 'reserved'),
(2, 55, 15.0, 15.0, 0.0, 45.00, 675.00, 'reserved'),
-- Задание TASK-2025-003 (Двигатель АДМ80А4 - выполнено)
(3, 1, 50.0, 50.0, 50.0, 2.50, 125.00, 'consumed'),
(3, 2, 10.0, 10.0, 10.0, 3.20, 32.00, 'consumed'),
(3, 5, 200.0, 200.0, 200.0, 1.20, 240.00, 'consumed'),
(3, 6, 100.0, 100.0, 100.0, 0.35, 35.00, 'consumed'),
(3, 7, 100.0, 100.0, 100.0, 0.15, 15.00, 'consumed'),
(3, 8, 20.0, 20.0, 20.0, 8.50, 170.00, 'consumed'),
(3, 10, 50.0, 50.0, 50.0, 2.85, 142.50, 'consumed'),
(3, 16, 120.0, 120.0, 120.0, 0.55, 66.00, 'consumed'),
(3, 21, 120.0, 120.0, 120.0, 0.28, 33.60, 'consumed'),
(3, 26, 25.0, 25.0, 25.0, 0.32, 8.00, 'consumed'),
(3, 41, 20.0, 20.0, 20.0, 8.50, 170.00, 'consumed'),
(3, 56, 12.0, 12.0, 12.0, 52.00, 624.00, 'consumed');

-- Серийные номера
INSERT INTO `product_serial_numbers` (`product_id`, `serial_number`, `production_date`, `task_id`, `status`, `warranty_start`, `warranty_end`) VALUES
(1, 'SN-ADM80A4-2025-0001', '2025-01-23', 3, 'active', '2025-01-23', '2026-01-23'),
(1, 'SN-ADM80A4-2025-0002', '2025-01-23', 3, 'active', '2025-01-23', '2026-01-23'),
(1, 'SN-ADM80A4-2025-0003', '2025-01-23', 3, 'active', '2025-01-23', '2026-01-23');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ТАБЛИЦА ДЛЯ ПАСПОРТОВ ПРОДУКТОВ
-- ============================================

-- 16. Паспорта продуктов (технологические спецификации)
CREATE TABLE `product_passports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL UNIQUE,
  `total_weight_kg` DECIMAL(10,3) DEFAULT 0,
  `warranty_months` INT DEFAULT 24,
  `is_serial_tracked` BOOLEAN DEFAULT FALSE,
  `production_notes` JSON COMMENT 'Примечания к производству',
  `quality_requirements` JSON COMMENT 'Требования к качеству',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pp_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Материалы паспорта продукта
CREATE TABLE `product_passport_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `passport_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `unit` VARCHAR(20) NOT NULL,
  `sort_order` INT DEFAULT 0,
  `notes` TEXT,
  CONSTRAINT `fk_ppm_passport` FOREIGN KEY (`passport_id`) REFERENCES `product_passports`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppm_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Индексы для производительности
CREATE INDEX idx_passport_product ON product_passports(product_id);
CREATE INDEX idx_ppm_passport ON product_passport_materials(passport_id);
CREATE INDEX idx_ppm_material ON product_passport_materials(material_id);

-- ============================================
-- ТАБЛИЦА ШАБЛОНОВ СВОЙСТВ ПРОДУКЦИИ
-- ============================================

-- 18. Шаблоны свойств для категорий продукции
CREATE TABLE `product_property_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'Название свойства на русском',
  `code` VARCHAR(50) NOT NULL COMMENT 'Код свойства для JSON',
  `property_type` ENUM('number', 'string', 'boolean', 'select', 'text') DEFAULT 'number',
  `unit` VARCHAR(50) COMMENT 'Единица измерения',
  `min_value` DECIMAL(15,3) COMMENT 'Минимальное значение',
  `max_value` DECIMAL(15,3) COMMENT 'Максимальное значение',
  `is_required` BOOLEAN DEFAULT FALSE,
  `sort_order` INT DEFAULT 0,
  `description` TEXT,
  `possible_values` JSON COMMENT 'Возможные значения для типа select',
  CONSTRAINT `fk_ppt_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Индексы
CREATE INDEX idx_ppt_category ON product_property_templates(category_id);
CREATE INDEX idx_ppt_code ON product_property_templates(code);

-- ============================================
-- ЗАПОЛНЕНИЕ ШАБЛОНОВ СВОЙСТВ ДЛЯ КАТЕГОРИЙ
-- ============================================

-- Общепромышленные электродвигатели (категория 2)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(2, 'Мощность', 'мощность_квт', 'number', 'кВт', 0.06, 500, TRUE, 1, 'Номинальная мощность двигателя', NULL),
(2, 'Синхронная скорость', 'обороты_мин', 'select', NULL, NULL, NULL, TRUE, 2, 'Частота вращения ротора', JSON_OBJECT('values', JSON_ARRAY(750, 1000, 1500, 3000))),
(2, 'Напряжение питания', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 3, 'Номинальное напряжение', JSON_OBJECT('values', JSON_ARRAY(220, 380, 660, '380/660'))),
(2, 'Габарит', 'габарит', 'string', NULL, NULL, NULL, TRUE, 4, 'Типоразмер двигателя', NULL),
(2, 'Класс энергоэффективности', 'класс_эффективности', 'select', NULL, NULL, NULL, TRUE, 5, 'Класс IE по ГОСТ', JSON_OBJECT('values', JSON_ARRAY('IE1', 'IE2', 'IE3', 'IE4'))),
(2, 'Исполнение по монтажу', 'монтаж', 'select', NULL, NULL, NULL, FALSE, 6, 'Конструктивное исполнение', JSON_OBJECT('values', JSON_ARRAY('IM1001', 'IM1011', 'IM1031', 'IM1081', 'IM2001', 'IM2031', 'IM3001', 'IM3011'))),
(2, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, FALSE, 7, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP23', 'IP44', 'IP54', 'IP55', 'IP65'))),
(2, 'Класс изоляции', 'класс_изоляции', 'select', NULL, NULL, NULL, FALSE, 8, 'Термический класс', JSON_OBJECT('values', JSON_ARRAY('A', 'E', 'B', 'F', 'H'))),
(2, 'Масса', 'масса_кг', 'number', 'кг', 5, 5000, FALSE, 9, 'Масса двигателя без упаковки', NULL),
(2, 'КПД', 'кпд_проц', 'number', '%', 50, 98, FALSE, 10, 'Коэффициент полезного действия', NULL),
(2, 'Коэффициент мощности', 'косинус_фи', 'number', NULL, 0.5, 1.0, FALSE, 11, 'cos φ', NULL);

-- Взрывозащищенные электродвигатели (категория 3)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(3, 'Мощность', 'мощность_квт', 'number', 'кВт', 0.12, 400, TRUE, 1, 'Номинальная мощность двигателя', NULL),
(3, 'Синхронная скорость', 'обороты_мин', 'select', NULL, NULL, NULL, TRUE, 2, 'Частота вращения ротора', JSON_OBJECT('values', JSON_ARRAY(750, 1000, 1500, 3000))),
(3, 'Напряжение питания', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 3, 'Номинальное напряжение', JSON_OBJECT('values', JSON_ARRAY(220, 380, 660, '380/660'))),
(3, 'Габарит', 'габарит', 'string', NULL, NULL, NULL, TRUE, 4, 'Типоразмер двигателя', NULL),
(3, 'Маркировка взрывозащиты', 'маркировка_взрывозащиты', 'string', NULL, NULL, NULL, TRUE, 5, 'Знак взрывозащиты', NULL),
(3, 'Уровень взрывозащиты', 'уровень_взрывозащиты', 'select', NULL, NULL, NULL, TRUE, 6, 'Уровень по ГОСТ', JSON_OBJECT('values', JSON_ARRAY('Взрывобезопасное', 'Взрывозащищенное', 'Особовзрывобезопасное'))),
(3, 'Класс энергоэффективности', 'класс_эффективности', 'select', NULL, NULL, NULL, FALSE, 7, 'Класс IE', JSON_OBJECT('values', JSON_ARRAY('IE1', 'IE2', 'IE3', 'IE4'))),
(3, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, FALSE, 8, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP54', 'IP55', 'IP65', 'IP66'))),
(3, 'Класс изоляции', 'класс_изоляции', 'select', NULL, NULL, NULL, FALSE, 9, 'Термический класс', JSON_OBJECT('values', JSON_ARRAY('F', 'H'))),
(3, 'Масса', 'масса_кг', 'number', 'кг', 10, 3000, FALSE, 10, 'Масса двигателя', NULL),
(3, 'Температура поверхности', 'температура_поверхности', 'select', '°C', NULL, NULL, FALSE, 11, 'Предельная температура', JSON_OBJECT('values', JSON_ARRAY('T1', 'T2', 'T3', 'T4', 'T5', 'T6')));

-- Крановые электродвигатели (категория 4)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(4, 'Мощность', 'мощность_квт', 'number', 'кВт', 1.5, 160, TRUE, 1, 'Номинальная мощность при ПВ 40%', NULL),
(4, 'Синхронная скорость', 'обороты_мин', 'select', NULL, NULL, NULL, TRUE, 2, 'Частота вращения ротора', JSON_OBJECT('values', JSON_ARRAY(600, 750, 900, 1000, 1500))),
(4, 'Напряжение питания', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 3, 'Номинальное напряжение', JSON_OBJECT('values', JSON_ARRAY(220, 380, 660, '380/660'))),
(4, 'Габарит', 'габарит', 'string', NULL, NULL, NULL, TRUE, 4, 'Типоразмер двигателя', NULL),
(4, 'Режим работы', 'режим_работы', 'select', NULL, NULL, NULL, TRUE, 5, 'Категория режима', JSON_OBJECT('values', JSON_ARRAY('S3', 'S4', 'S5'))),
(4, 'ПВ рабочий', 'пв_проц', 'number', '%', 15, 100, FALSE, 6, 'Продолжительность включения', NULL),
(4, 'Класс изоляции', 'класс_изоляции', 'select', NULL, NULL, NULL, TRUE, 7, 'Термический класс', JSON_OBJECT('values', JSON_ARRAY('F', 'H'))),
(4, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, FALSE, 8, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP44', 'IP54', 'IP55'))),
(4, 'Масса', 'масса_кг', 'number', 'кг', 20, 2000, FALSE, 9, 'Масса двигателя', NULL),
(4, 'Момент инерции', 'момент_инерции', 'number', 'кг·м²', 0.01, 500, FALSE, 10, 'Момент инерции ротора', NULL),
(4, 'Передаточное число редуктора', 'передаточное_число', 'number', NULL, 1, 100, FALSE, 11, 'Для мотор-редукторов', NULL);

-- Дизельные генераторы (категория 6)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(6, 'Основная мощность', 'мощность_основная_квт', 'number', 'кВт', 1, 3000, TRUE, 1, 'Основная мощность COP', NULL),
(6, 'Резервная мощность', 'мощность_резервная_квт', 'number', 'кВт', 1, 3300, FALSE, 2, 'Резервная мощность LTP', NULL),
(6, 'Тип топлива', 'тип_топлива', 'select', NULL, NULL, NULL, TRUE, 3, 'Вид используемого топлива', JSON_OBJECT('values', JSON_ARRAY('дизель', 'бензин', 'газ'))),
(6, 'Тип запуска', 'тип_запуска', 'select', NULL, NULL, NULL, TRUE, 4, 'Способ запуска', JSON_OBJECT('values', JSON_ARRAY('ручной', 'электростартер', 'ATS'))),
(6, 'Количество фаз', 'фазы', 'select', NULL, NULL, NULL, TRUE, 5, 'Однофазный/трехфазный', JSON_OBJECT('values', JSON_ARRAY(1, 3))),
(6, 'Напряжение', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 6, 'Выходное напряжение', JSON_OBJECT('values', JSON_ARRAY(220, 380, '220/380'))),
(6, 'Частота тока', 'частота_гц', 'select', 'Гц', NULL, NULL, TRUE, 7, 'Частота переменного тока', JSON_OBJECT('values', JSON_ARRAY(50, 60))),
(6, 'Расход топлива', 'расход_л_ч', 'number', 'л/ч', 0.5, 500, FALSE, 8, 'При нагрузке 75%', NULL),
(6, 'Уровень шума', 'шум_дб', 'number', 'дБ', 40, 100, FALSE, 9, 'На расстоянии 7м', NULL),
(6, 'Объем топливного бака', 'бак_л', 'number', 'л', 5, 5000, FALSE, 10, 'Вместимость бака', NULL),
(6, 'Масса', 'масса_кг', 'number', 'кг', 20, 10000, FALSE, 11, 'Масса генератора', NULL),
(6, 'Исполнение', 'исполнение', 'select', NULL, NULL, NULL, FALSE, 12, 'Тип исполнения', JSON_OBJECT('values', JSON_ARRAY('открытое', 'в кожухе', 'контейнер', 'автомобильное')));

-- Бензиновые генераторы (категория 7)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(7, 'Основная мощность', 'мощность_основная_квт', 'number', 'кВт', 0.7, 20, TRUE, 1, 'Основная мощность', NULL),
(7, 'Резервная мощность', 'мощность_резервная_квт', 'number', 'кВт', 1, 22, FALSE, 2, 'Максимальная мощность', NULL),
(7, 'Тип топлива', 'тип_топлива', 'select', NULL, NULL, NULL, TRUE, 3, 'Вид топлива', JSON_OBJECT('values', JSON_ARRAY('бензин АИ-92', 'бензин АИ-95'))),
(7, 'Тип запуска', 'тип_запуска', 'select', NULL, NULL, NULL, TRUE, 4, 'Способ запуска', JSON_OBJECT('values', JSON_ARRAY('ручной', 'электростартер'))),
(7, 'Количество фаз', 'фазы', 'select', NULL, NULL, NULL, TRUE, 5, 'Однофазный/трехфазный', JSON_OBJECT('values', JSON_ARRAY(1, 3))),
(7, 'Напряжение', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 6, 'Выходное напряжение', JSON_OBJECT('values', JSON_ARRAY(220, 380, '220/380'))),
(7, 'Двигатель', 'двигатель', 'string', NULL, NULL, NULL, FALSE, 7, 'Производитель двигателя', NULL),
(7, 'Расход топлива', 'расход_л_ч', 'number', 'л/ч', 0.3, 10, FALSE, 8, 'При нагрузке 75%', NULL),
(7, 'Уровень шума', 'шум_дб', 'number', 'дБ', 50, 85, FALSE, 9, 'На расстоянии 7м', NULL),
(7, 'Объем топливного бака', 'бак_л', 'number', 'л', 3, 50, FALSE, 10, 'Вместимость бака', NULL),
(7, 'Масса', 'масса_кг', 'number', 'кг', 10, 200, FALSE, 11, 'Масса генератора', NULL),
(7, 'Выход 12В', 'выход_12в', 'boolean', NULL, NULL, NULL, FALSE, 12, 'Наличие выхода 12В', NULL);

-- Масляные трансформаторы (категория 9)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(9, 'Мощность', 'мощность_ква', 'number', 'кВА', 16, 63000, TRUE, 1, 'Номинальная мощность', NULL),
(9, 'Напряжение ВН', 'напряжение_вн_кв', 'number', 'кВ', 6, 750, TRUE, 2, 'Высокое напряжение', NULL),
(9, 'Напряжение НН', 'напряжение_нн_в', 'select', 'В', NULL, NULL, TRUE, 3, 'Низкое напряжение', JSON_OBJECT('values', JSON_ARRAY(230, 400, '230/400', 690))),
(9, 'Схема соединения', 'схема_соединения', 'select', NULL, NULL, NULL, TRUE, 4, 'Группа соединений', JSON_OBJECT('values', JSON_ARRAY('Y/Yн-0', 'Δ/Yн-11', 'Y/Δ-11', 'Yн/Δ-11'))),
(9, 'Напряжение КЗ', 'напряжение_кз_проц', 'number', '%', 3, 15, FALSE, 5, 'Напряжение короткого замыкания', NULL),
(9, 'Потери холостого хода', 'потери_хх_вт', 'number', 'Вт', 100, 50000, FALSE, 6, 'Мощность потерь ХХ', NULL),
(9, 'Потери КЗ', 'потери_кз_вт', 'number', 'Вт', 500, 200000, FALSE, 7, 'Мощность потерь КЗ', NULL),
(9, 'Ток холостого хода', 'ток_хх_проц', 'number', '%', 0.5, 5, FALSE, 8, 'Процент тока ХХ', NULL),
(9, 'Масса масла', 'масса_масла_кг', 'number', 'кг', 50, 50000, FALSE, 9, 'Масса трансформаторного масла', NULL),
(9, 'Полная масса', 'масса_кг', 'number', 'кг', 200, 100000, FALSE, 10, 'Масса трансформатора', NULL),
(9, 'Климатическое исполнение', 'климат', 'select', NULL, NULL, NULL, FALSE, 11, 'По ГОСТ', JSON_OBJECT('values', JSON_ARRAY('У1', 'УХЛ1', 'Т1', 'У2', 'УХЛ2')));

-- Сухие трансформаторы (категория 10)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(10, 'Мощность', 'мощность_ква', 'number', 'кВА', 16, 10000, TRUE, 1, 'Номинальная мощность', NULL),
(10, 'Напряжение ВН', 'напряжение_вн_кв', 'number', 'кВ', 6, 35, TRUE, 2, 'Высокое напряжение', NULL),
(10, 'Напряжение НН', 'напряжение_нн_в', 'select', 'В', NULL, NULL, TRUE, 3, 'Низкое напряжение', JSON_OBJECT('values', JSON_ARRAY(230, 400, '230/400', 690))),
(10, 'Схема соединения', 'схема_соединения', 'select', NULL, NULL, NULL, TRUE, 4, 'Группа соединений', JSON_OBJECT('values', JSON_ARRAY('Dyn11', 'Yyn0', 'Yzn5'))),
(10, 'Класс нагревостойкости', 'класс_нагревостойкости', 'select', NULL, NULL, NULL, TRUE, 5, 'Термический класс', JSON_OBJECT('values', JSON_ARRAY('F', 'H', 'C'))),
(10, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, FALSE, 6, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP00', 'IP20', 'IP23', 'IP54'))),
(10, 'Напряжение КЗ', 'напряжение_кз_проц', 'number', '%', 4, 10, FALSE, 7, 'Напряжение короткого замыкания', NULL),
(10, 'Уровень шума', 'шум_дб', 'number', 'дБ', 35, 70, FALSE, 8, 'На расстоянии 1м', NULL),
(10, 'Масса', 'масса_кг', 'number', 'кг', 100, 20000, FALSE, 9, 'Масса трансформатора', NULL),
(10, 'Габариты', 'габариты', 'text', NULL, NULL, NULL, FALSE, 10, 'Д×Ш×В в мм', NULL);

-- Вводно-распределительные устройства (категория 11)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(11, 'Номинальный ток', 'ток_номинальный_а', 'number', 'А', 100, 6300, TRUE, 1, 'Рабочий ток вводного автомата', NULL),
(11, 'Напряжение', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 2, 'Номинальное напряжение', JSON_OBJECT('values', JSON_ARRAY(220, 380, '220/380'))),
(11, 'Количество вводов', 'количество_вводов', 'select', NULL, NULL, NULL, TRUE, 3, 'Число независимых вводов', JSON_OBJECT('values', JSON_ARRAY(1, 2, 3, 4))),
(11, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, TRUE, 4, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP31', 'IP54', 'IP55', 'IP65'))),
(11, 'Тип установки', 'тип_установки', 'select', NULL, NULL, NULL, FALSE, 5, 'Способ монтажа', JSON_OBJECT('values', JSON_ARRAY('напольное', 'настенное', 'подвесное'))),
(11, 'Количество отходящих линий', 'линии_отходящие', 'number', NULL, 1, 100, FALSE, 6, 'Число выходных линий', NULL),
(11, 'АВР', 'авр', 'boolean', NULL, NULL, NULL, FALSE, 7, 'Автоматический ввод резерва', NULL),
(11, 'Учет электроэнергии', 'учет', 'boolean', NULL, NULL, NULL, FALSE, 8, 'Наличие счетчика', NULL),
(11, 'Габариты', 'габариты', 'text', NULL, NULL, NULL, FALSE, 9, 'В×Ш×Г в мм', NULL),
(11, 'Масса', 'масса_кг', 'number', 'кг', 50, 5000, FALSE, 10, 'Масса щита', NULL);

-- Щиты управления (категория 12)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(12, 'Номинальный ток', 'ток_номональный_а', 'number', 'А', 1, 1000, TRUE, 1, 'Рабочий ток', NULL),
(12, 'Напряжение', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 2, 'Номинальное напряжение', JSON_OBJECT('values', JSON_ARRAY(24, 220, 380))),
(12, 'Количество двигателей', 'количество_двигателей', 'number', NULL, 1, 50, TRUE, 3, 'Число управляемых двигателей', NULL),
(12, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, TRUE, 4, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP31', 'IP54', 'IP55', 'IP65'))),
(12, 'Тип управления', 'тип_управления', 'select', NULL, NULL, NULL, FALSE, 5, 'Способ управления', JSON_OBJECT('values', JSON_ARRAY('прямой пуск', 'звезда-треугольник', 'частотный преобразователь', 'устройство плавного пуска'))),
(12, 'Контроллер', 'контроллер', 'boolean', NULL, NULL, NULL, FALSE, 6, 'Наличие программируемого контроллера', NULL),
(12, 'Сенсорная панель', 'сенсорная_панель', 'boolean', NULL, NULL, NULL, FALSE, 7, 'Наличие HMI панели', NULL),
(12, 'Тип установки', 'тип_установки', 'select', NULL, NULL, NULL, FALSE, 8, 'Способ монтажа', JSON_OBJECT('values', JSON_ARRAY('напольное', 'настенное', 'шкаф'))),
(12, 'Габариты', 'габариты', 'text', NULL, NULL, NULL, FALSE, 9, 'В×Ш×Г в мм', NULL),
(12, 'Масса', 'масса_кг', 'number', 'кг', 10, 2000, FALSE, 10, 'Масса щита', NULL);

-- Щиты освещения (категория 13)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(13, 'Номинальный ток', 'ток_номональный_а', 'number', 'А', 16, 630, TRUE, 1, 'Рабочий ток вводного автомата', NULL),
(13, 'Напряжение', 'напряжение_в', 'select', 'В', NULL, NULL, TRUE, 2, 'Номинальное напряжение', JSON_OBJECT('values', JSON_ARRAY(220, 380))),
(13, 'Количество групп', 'количество_групп', 'number', NULL, 1, 100, TRUE, 3, 'Число групп освещения', NULL),
(13, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, TRUE, 4, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP31', 'IP54', 'IP55', 'IP65'))),
(13, 'Фотореле', 'фотореле', 'boolean', NULL, NULL, NULL, FALSE, 5, 'Автоматическое управление по освещенности', NULL),
(13, 'Таймер', 'таймер', 'boolean', NULL, NULL, NULL, FALSE, 6, 'Управление по расписанию', NULL),
(13, 'Датчик движения', 'датчик_движения', 'boolean', NULL, NULL, NULL, FALSE, 7, 'Управление по движению', NULL),
(13, 'Тип установки', 'тип_установки', 'select', NULL, NULL, NULL, FALSE, 8, 'Способ монтажа', JSON_OBJECT('values', JSON_ARRAY('настенное', 'навесное', 'встраиваемое'))),
(13, 'Габариты', 'габариты', 'text', NULL, NULL, NULL, FALSE, 9, 'В×Ш×Г в мм', NULL),
(13, 'Масса', 'масса_кг', 'number', 'кг', 5, 500, FALSE, 10, 'Масса щита', NULL);

-- Роторы (категория 15)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(15, 'Диаметр', 'диаметр_мм', 'number', 'мм', 50, 1000, TRUE, 1, 'Наружный диаметр ротора', NULL),
(15, 'Длина', 'длина_мм', 'number', 'мм', 100, 2000, TRUE, 2, 'Длина активной части', NULL),
(15, 'Масса', 'масса_кг', 'number', 'кг', 1, 5000, FALSE, 3, 'Масса ротора', NULL),
(15, 'Тип вала', 'тип_вала', 'select', NULL, NULL, NULL, FALSE, 4, 'Конструкция вала', JSON_OBJECT('values', JSON_ARRAY('цельный', 'сборный', 'полый'))),
(15, 'Материал сердечника', 'материал_сердечника', 'select', NULL, NULL, NULL, FALSE, 5, 'Марка стали', JSON_OBJECT('values', JSON_ARRAY('2212', '2312', '2412', '2512'))),
(15, 'Тип обмотки', 'тип_обмотки', 'select', NULL, NULL, NULL, FALSE, 6, 'Конструкция обмотки', JSON_OBJECT('values', JSON_ARRAY('беличья клетка', 'фазная', 'короткозамкнутая'))),
(15, 'Для двигателя', 'для_двигателя', 'string', NULL, NULL, NULL, TRUE, 7, 'Типоразмер совместимого двигателя', NULL);

-- Статоры (категория 16)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(16, 'Наружный диаметр', 'диаметр_наружный_мм', 'number', 'мм', 100, 1500, TRUE, 1, 'Диаметр корпуса статора', NULL),
(16, 'Внутренний диаметр', 'диаметр_внутренний_мм', 'number', 'мм', 50, 1000, TRUE, 2, 'Диаметр расточки', NULL),
(16, 'Длина', 'длина_мм', 'number', 'мм', 100, 2000, TRUE, 3, 'Длина пакета статора', NULL),
(16, 'Масса', 'масса_кг', 'number', 'кг', 2, 3000, FALSE, 4, 'Масса статора', NULL),
(16, 'Материал корпуса', 'материал_корпуса', 'select', NULL, NULL, NULL, FALSE, 5, 'Материал корпуса', JSON_OBJECT('values', JSON_ARRAY('алюминий', 'чугун', 'сталь'))),
(16, 'Материал сердечника', 'материал_сердечника', 'select', NULL, NULL, NULL, FALSE, 6, 'Марка стали', JSON_OBJECT('values', JSON_ARRAY('2212', '2312', '2412', '2512'))),
(16, 'Количество полюсов', 'количество_полюсов', 'select', NULL, NULL, NULL, TRUE, 7, 'Число полюсов', JSON_OBJECT('values', JSON_ARRAY(2, 4, 6, 8, 10, 12))),
(16, 'Для двигателя', 'для_двигателя', 'string', NULL, NULL, NULL, TRUE, 8, 'Типоразмер совместимого двигателя', NULL);

-- Подшипниковые щиты (категория 17)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(17, 'Наружный диаметр', 'диаметр_наружный_мм', 'number', 'мм', 100, 800, TRUE, 1, 'Диаметр щита', NULL),
(17, 'Высота', 'высота_мм', 'number', 'мм', 50, 500, TRUE, 2, 'Высота щита', NULL),
(17, 'Масса', 'масса_кг', 'number', 'кг', 1, 500, FALSE, 3, 'Масса щита', NULL),
(17, 'Материал', 'материал', 'select', NULL, NULL, NULL, FALSE, 4, 'Материал изготовления', JSON_OBJECT('values', JSON_ARRAY('алюминий', 'чугун', 'сталь'))),
(17, 'Тип подшипника', 'тип_подшипника', 'string', NULL, NULL, NULL, FALSE, 5, 'Марка устанавливаемого подшипника', NULL),
(17, 'Для двигателя', 'для_двигателя', 'string', NULL, NULL, NULL, TRUE, 6, 'Типоразмер совместимого двигателя', NULL),
(17, 'Сторона', 'сторона', 'select', NULL, NULL, NULL, FALSE, 7, 'Сторона установки', JSON_OBJECT('values', JSON_ARRAY('передний', 'задний', 'универсальный')));

-- Клеммные коробки (категория 18)
INSERT INTO `product_property_templates` (`category_id`, `name`, `code`, `property_type`, `unit`, `min_value`, `max_value`, `is_required`, `sort_order`, `description`, `possible_values`) VALUES
(18, 'Номинальный ток', 'ток_номональный_а', 'number', 'А', 10, 1000, TRUE, 1, 'Рабочий ток клемм', NULL),
(18, 'Напряжение', 'напряжение_в', 'number', 'В', 220, 6000, TRUE, 2, 'Рабочее напряжение', NULL),
(18, 'Количество выводов', 'количество_выводов', 'select', NULL, NULL, NULL, TRUE, 3, 'Число клемм', JSON_OBJECT('values', JSON_ARRAY(3, 6, 9, 12))),
(18, 'Степень защиты', 'степень_защиты', 'select', NULL, NULL, NULL, TRUE, 4, 'Класс IP', JSON_OBJECT('values', JSON_ARRAY('IP44', 'IP54', 'IP55', 'IP65'))),
(18, 'Материал корпуса', 'материал_корпуса', 'select', NULL, NULL, NULL, FALSE, 5, 'Материал корпуса', JSON_OBJECT('values', JSON_ARRAY('алюминий', 'чугун', 'пластик'))),
(18, 'Масса', 'масса_кг', 'number', 'кг', 0.5, 50, FALSE, 6, 'Масса коробки', NULL),
(18, 'Для двигателя', 'для_двигателя', 'string', NULL, NULL, NULL, TRUE, 7, 'Типоразмер совместимого двигателя', NULL);

-- ============================================
-- ПРЕДСТАВЛЕНИЕ ДЛЯ УДОБНОГО ПРОСМОТРА СВОЙСТВ
-- ============================================

CREATE OR REPLACE VIEW v_product_properties AS
SELECT 
    p.id AS product_id,
    p.article,
    p.name AS product_name,
    pc.name_ru AS category_name,
    ppt.name AS property_name,
    ppt.code AS property_code,
    ppt.property_type,
    ppt.unit,
    CASE 
        WHEN ppt.property_type = 'number' THEN JSON_UNQUOTE(JSON_EXTRACT(p.specifications, CONCAT('$.', ppt.code)))
        ELSE JSON_UNQUOTE(JSON_EXTRACT(p.specifications, CONCAT('$.', ppt.code)))
    END AS property_value,
    ppt.is_required,
    ppt.sort_order
FROM products p
LEFT JOIN product_categories pc ON p.category_id = pc.id
LEFT JOIN product_property_templates ppt ON p.category_id = ppt.category_id
ORDER BY p.id, ppt.sort_order;

-- ============================================
-- ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ ЗНАЧЕНИЯ СВОЙСТВА
-- ============================================

DELIMITER //

CREATE FUNCTION get_product_property(
    p_product_id INT,
    p_property_code VARCHAR(50)
) RETURNS VARCHAR(500)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_value VARCHAR(500);
    
    SELECT JSON_UNQUOTE(JSON_EXTRACT(specifications, CONCAT('$.', p_property_code)))
    INTO v_value
    FROM products
    WHERE id = p_product_id;
    
    RETURN COALESCE(v_value, 'не указано');
END//

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

