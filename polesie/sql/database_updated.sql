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
('Администратор', 'admin', 'Полный доступ', '{"all":true}'),
('Директор', 'director', 'Руководство', '{"all":true}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"orders":["read","create"],"products":["read"]}'),
('Технолог', 'technologist', 'Производство', '{"production":["read","create"],"materials":["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"warehouse":["read","create"],"materials":["read","update"]}'),
('Рабочий', 'worker', 'Исполнитель', '{"production":["read"],"tasks":["update"]}');

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
(1, 'Общепромышленные двигатели', 'MOTOR_GENERAL', 'Двигатели общего назначения'),
(1, 'Взрывозащищенные двигатели', 'MOTOR_EX', 'Двигатели для взрывоопасных сред'),
(1, 'Крановые двигатели', 'MOTOR_CRANE', 'Двигатели для кранового оборудования'),
(NULL, 'Генераторы', 'GENERATOR', 'Дизельные и бензиновые генераторы'),
(5, 'Дизельные генераторы', 'GEN_DIESEL', 'Дизельные электростанции'),
(5, 'Бензиновые генераторы', 'GEN_PETROL', 'Бензиновые электростанции'),
(NULL, 'Трансформаторы', 'TRANSFORMER', 'Силовые трансформаторы'),
(8, 'Масляные трансформаторы', 'TR_OIL', 'Трансформаторы с масляным охлаждением'),
(8, 'Сухие трансформаторы', 'TR_DRY', 'Трансформаторы с воздушным охлаждением'),
(NULL, 'Щитовое оборудование', 'SWITCHGEAR', 'Распределительные щиты и шкафы'),
(10, 'Вводно-распределительные устройства', 'SWITCH_VRU', 'ВРУ для промышленных объектов'),
(10, 'Щиты управления', 'SWITCH_CONTROL', 'Шкафы управления двигателями'),
(10, 'Щиты освещения', 'SWITCH_LIGHT', 'Щиты распределения освещения'),
(NULL, 'Запчасти', 'SPARE_PARTS', 'Запасные части и комплектующие'),
(14, 'Роторы', 'PART_ROTOR', 'Роторы для электродвигателей'),
(14, 'Статоры', 'PART_STATOR', 'Статоры для электродвигателей'),
(14, 'Подшипниковые щиты', 'PART_SHIELD', 'Подшипниковые щиты'),
(14, 'Клеммные коробки', 'PART_BOX', 'Клеммные коробки');

INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`, `current_stock`, `min_stock`, `location`, `supplier_id`, `last_price`, `currency`) VALUES
-- Металлы - прутки
('ST-BAR-45-010', 'Пруток стальной 45 Ø10мм', 'Пруток 45 Ø10', 2, 3, '{"diameter_mm":10,"steel_grade":"45","length_m":6,"surface":"калиброванный","gost":"10702-78"}', 321.52, 64.30, 'Склад №1, Секция А', 1, 2.50, 'BYN'),
('ST-BAR-40X-010', 'Пруток легированный 40Х Ø10мм', 'Пруток 40Х Ø10', 2, 3, '{"diameter_mm":10,"steel_grade":"40Х","length_m":6,"surface":"горячекатаный","gost":"2590-2006"}', 17.38, 3.48, 'Склад №1, Секция А', 1, 3.20, 'BYN'),
('ST-BAR-45-020', 'Пруток стальной 45 Ø20мм', 'Пруток 45 Ø20', 2, 3, '{"diameter_mm":20,"steel_grade":"45","length_m":6,"surface":"калиброванный","gost":"10702-78"}', 450.00, 50.00, 'Склад №1, Секция Б', 1, 4.80, 'BYN'),
('ST-BAR-45-030', 'Пруток стальной 45 Ø30мм', 'Пруток 45 Ø30', 2, 3, '{"diameter_mm":30,"steel_grade":"45","length_m":6,"surface":"калиброванный","gost":"10702-78"}', 280.00, 40.00, 'Склад №1, Секция Б', 1, 7.20, 'BYN'),
('ST-BAR-45-040', 'Пруток стальной 45 Ø40мм', 'Пруток 45 Ø40', 2, 3, '{"diameter_mm":40,"steel_grade":"45","length_m":6,"surface":"калиброванный","gost":"10702-78"}', 195.00, 30.00, 'Склад №1, Секция Б', 1, 12.80, 'BYN'),
('ST-BAR-45-050', 'Пруток стальной 45 Ø50мм', 'Пруток 45 Ø50', 2, 3, '{"diameter_mm":50,"steel_grade":"45","length_m":6,"surface":"калиброванный","gost":"10702-78"}', 150.00, 25.00, 'Склад №1, Секция В', 1, 20.00, 'BYN'),
('ST-BAR-35-025', 'Пруток стальной 35 Ø25мм', 'Пруток 35 Ø25', 2, 3, '{"diameter_mm":25,"steel_grade":"35","length_m":6,"surface":"горячекатаный","gost":"2590-2006"}', 320.00, 45.00, 'Склад №1, Секция А', 1, 5.50, 'BYN'),
('ST-BAR-20-015', 'Пруток стальной 20 Ø15мм', 'Пруток 20 Ø15', 2, 3, '{"diameter_mm":15,"steel_grade":"20","length_m":6,"surface":"горячекатаный","gost":"2590-2006"}', 410.00, 60.00, 'Склад №1, Секция А', 1, 3.80, 'BYN'),
-- Металлы - листовой прокат
('ST-SHEET-3-08', 'Лист стальной 3мм', 'Лист 3мм', 3, 2, '{"thickness_mm":3,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 1250.00, 200.00, 'Склад №2, Секция А', 1, 2.10, 'BYN'),
('ST-SHEET-4-08', 'Лист стальной 4мм', 'Лист 4мм', 3, 2, '{"thickness_mm":4,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 980.00, 150.00, 'Склад №2, Секция А', 1, 2.80, 'BYN'),
('ST-SHEET-5-08', 'Лист стальной 5мм', 'Лист 5мм', 3, 2, '{"thickness_mm":5,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 750.00, 120.00, 'Склад №2, Секция А', 1, 3.50, 'BYN'),
('ST-SHEET-6-08', 'Лист стальной 6мм', 'Лист 6мм', 3, 2, '{"thickness_mm":6,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 620.00, 100.00, 'Склад №2, Секция Б', 1, 4.20, 'BYN'),
('ST-SHEET-8-08', 'Лист стальной 8мм', 'Лист 8мм', 3, 2, '{"thickness_mm":8,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 480.00, 80.00, 'Склад №2, Секция Б', 1, 5.60, 'BYN'),
('ST-SHEET-10-08', 'Лист стальной 10мм', 'Лист 10мм', 3, 2, '{"thickness_mm":10,"width_mm":1500,"length_mm":6000,"steel_grade":"Ст3сп","gost":"19903-90"}', 350.00, 60.00, 'Склад №2, Секция Б', 1, 7.00, 'BYN'),
-- Металлы - чугун
('CAST-IRON-CH20', 'Чугун серый СЧ20', 'Чугун СЧ20', 4, 2, '{"grade":"СЧ20","hardness_hb":"170-220","gost":"1412-85"}', 500.00, 100.00, 'Склад №2, Секция В', 1, 3.00, 'BYN'),
('CAST-IRON-CH25', 'Чугун серый СЧ25', 'Чугун СЧ25', 4, 2, '{"grade":"СЧ25","hardness_hb":"190-240","gost":"1412-85"}', 380.00, 80.00, 'Склад №2, Секция В', 1, 3.50, 'BYN'),
('CAST-IRON-VCH40', 'Чугун высокопрочный ВЧ40', 'Чугун ВЧ40', 4, 2, '{"grade":"ВЧ40","hardness_hb":"200-250","gost":"7293-85"}', 290.00, 60.00, 'Склад №2, Секция В', 1, 4.20, 'BYN'),
-- Электротехника - провода
('WIRE-CU-0.75', 'Провод медный 0.75мм²', 'Провод 0.75', 6, 3, '{"cross_section_mm2":0.75,"diameter_mm":1.0,"material":"медь","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 2500.00, 500.00, 'Склад №4, Секция А', 2, 0.45, 'BYN'),
('WIRE-CU-1.5', 'Провод медный 1.5мм²', 'Провод 1.5', 6, 3, '{"cross_section_mm2":1.5,"diameter_mm":1.4,"material":"медь","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 2000.00, 400.00, 'Склад №4, Секция А', 2, 0.75, 'BYN'),
('WIRE-CU-2.5', 'Провод медный 2.5мм²', 'Провод 2.5', 6, 3, '{"cross_section_mm2":2.5,"diameter_mm":1.8,"material":"медь","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 1500.00, 300.00, 'Склад №4, Секция А', 2, 1.20, 'BYN'),
('WIRE-CU-4.0', 'Провод медный 4.0мм²', 'Провод 4.0', 6, 3, '{"cross_section_mm2":4.0,"diameter_mm":2.3,"material":"медь","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 1200.00, 250.00, 'Склад №4, Секция А', 2, 1.90, 'BYN'),
('WIRE-CU-6.0', 'Провод медный 6.0мм²', 'Провод 6.0', 6, 3, '{"cross_section_mm2":6.0,"diameter_mm":2.8,"material":"медь","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 950.00, 200.00, 'Склад №4, Секция Б', 2, 2.85, 'BYN'),
('WIRE-CU-10.0', 'Провод медный 10мм²', 'Провод 10', 6, 3, '{"cross_section_mm2":10,"diameter_mm":3.6,"material":"медь","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 650.00, 150.00, 'Склад №4, Секция Б', 2, 4.75, 'BYN'),
('WIRE-AL-2.5', 'Провод алюминиевый 2.5мм²', 'Провод АК 2.5', 6, 3, '{"cross_section_mm2":2.5,"diameter_mm":1.8,"material":"алюминий","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 1800.00, 350.00, 'Склад №4, Секция А', 2, 0.55, 'BYN'),
('WIRE-AL-4.0', 'Провод алюминиевый 4.0мм²', 'Провод АК 4.0', 6, 3, '{"cross_section_mm2":4.0,"diameter_mm":2.3,"material":"алюминий","insulation":"ПВХ","voltage_v":450,"gost":"6323-79"}', 1400.00, 280.00, 'Склад №4, Секция А', 2, 0.85, 'BYN'),
-- Электротехника - шины
('BUS-CU-15x3', 'Шина медная 15x3мм', 'Шина 15x3', 7, 3, '{"width_mm":15,"thickness_mm":3,"material":"медь","gost":"434-78"}', 450.00, 80.00, 'Склад №4, Секция В', 2, 18.50, 'BYN'),
('BUS-CU-20x3', 'Шина медная 20x3мм', 'Шина 20x3', 7, 3, '{"width_mm":20,"thickness_mm":3,"material":"медь","gost":"434-78"}', 380.00, 70.00, 'Склад №4, Секция В', 2, 24.00, 'BYN'),
('BUS-CU-25x3', 'Шина медная 25x3мм', 'Шина 25x3', 7, 3, '{"width_mm":25,"thickness_mm":3,"material":"медь","gost":"434-78"}', 320.00, 60.00, 'Склад №4, Секция В', 2, 30.00, 'BYN'),
('BUS-CU-30x4', 'Шина медная 30x4мм', 'Шина 30x4', 7, 3, '{"width_mm":30,"thickness_mm":4,"material":"медь","gost":"434-78"}', 280.00, 50.00, 'Склад №4, Секция В', 2, 48.00, 'BYN'),
('BUS-CU-40x5', 'Шина медная 40x5мм', 'Шина 40x5', 7, 3, '{"width_mm":40,"thickness_mm":5,"material":"медь","gost":"434-78"}', 220.00, 40.00, 'Склад №4, Секция В', 2, 80.00, 'BYN'),
('BUS-AL-30x4', 'Шина алюминиевая 30x4мм', 'Шина А 30x4', 7, 3, '{"width_mm":30,"thickness_mm":4,"material":"алюминий","gost":"434-78"}', 350.00, 60.00, 'Склад №4, Секция В', 2, 22.00, 'BYN'),
('BUS-AL-40x5', 'Шина алюминиевая 40x5мм', 'Шина А 40x5', 7, 3, '{"width_mm":40,"thickness_mm":5,"material":"алюминий","gost":"434-78"}', 280.00, 50.00, 'Склад №4, Секция В', 2, 38.00, 'BYN'),
-- Крепеж - болты
('BOLT-M6x20', 'Болт М6х20 8.8', 'Болт М6х20', 9, 1, '{"thread":"M6","length_mm":20,"diameter_mm":6,"pitch_mm":1.0,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 8000.00, 1500.00, 'Склад №5, Ящик 1', 3, 0.18, 'BYN'),
('BOLT-M6x30', 'Болт М6х30 8.8', 'Болт М6х30', 9, 1, '{"thread":"M6","length_mm":30,"diameter_mm":6,"pitch_mm":1.0,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 7500.00, 1400.00, 'Склад №5, Ящик 1', 3, 0.22, 'BYN'),
('BOLT-M8x25', 'Болт М8х25 8.8', 'Болт М8х25', 9, 1, '{"thread":"M8","length_mm":25,"diameter_mm":8,"pitch_mm":1.25,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 6000.00, 1200.00, 'Склад №5, Ящик 1', 3, 0.28, 'BYN'),
('BOLT-M8x40', 'Болт М8х40 8.8', 'Болт М8х40', 9, 1, '{"thread":"M8","length_mm":40,"diameter_mm":8,"pitch_mm":1.25,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 5500.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.32, 'BYN'),
('BOLT-M10x50', 'Болт М10х50 8.8', 'Болт М10х50', 9, 1, '{"thread":"M10","length_mm":50,"diameter_mm":10,"pitch_mm":1.5,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 5000.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.35, 'BYN'),
('BOLT-M10x60', 'Болт М10х60 8.8', 'Болт М10х60', 9, 1, '{"thread":"M10","length_mm":60,"diameter_mm":10,"pitch_mm":1.5,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 4500.00, 900.00, 'Склад №5, Ящик 1', 3, 0.42, 'BYN'),
('BOLT-M12x60', 'Болт М12х60 8.8', 'Болт М12х60', 9, 1, '{"thread":"M12","length_mm":60,"diameter_mm":12,"pitch_mm":1.75,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 3500.00, 700.00, 'Склад №5, Ящик 1', 3, 0.55, 'BYN'),
('BOLT-M12x80', 'Болт М12х80 8.8', 'Болт М12х80', 9, 1, '{"thread":"M12","length_mm":80,"diameter_mm":12,"pitch_mm":1.75,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 3200.00, 650.00, 'Склад №5, Ящик 1', 3, 0.65, 'BYN'),
('BOLT-M14x70', 'Болт М14х70 8.8', 'Болт М14х70', 9, 1, '{"thread":"M14","length_mm":70,"diameter_mm":14,"pitch_mm":2.0,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 2800.00, 550.00, 'Склад №5, Ящик 1', 3, 0.85, 'BYN'),
('BOLT-M16x80', 'Болт М16х80 8.8', 'Болт М16х80', 9, 1, '{"thread":"M16","length_mm":80,"diameter_mm":16,"pitch_mm":2.0,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 2400.00, 500.00, 'Склад №5, Ящик 1', 3, 1.10, 'BYN'),
('BOLT-M20x100', 'Болт М20х100 8.8', 'Болт М20х100', 9, 1, '{"thread":"M20","length_mm":100,"diameter_mm":20,"pitch_mm":2.5,"strength_class":"8.8","coating":"цинк","head_type":"шестигранная","gost":"7798-70"}', 1800.00, 350.00, 'Склад №5, Ящик 1', 3, 1.80, 'BYN'),
-- Крепеж - гайки
('NUT-M6', 'Гайка М6 8', 'Гайка М6', 10, 1, '{"thread":"M6","diameter_mm":6,"pitch_mm":1.0,"strength_class":"8","coating":"цинк","nut_type":"шестигранная","gost":"5915-70"}', 9000.00, 1800.00, 'Склад №5, Ящик 2', 3, 0.08, 'BYN'),
('NUT-M8', 'Гайка М8 8', 'Гайка М8', 10, 1, '{"thread":"M8","diameter_mm":8,"pitch_mm":1.25,"strength_class":"8","coating":"цинк","nut_type":"шестигранная","gost":"5915-70"}', 7500.00, 1500.00, 'Склад №5, Ящик 2', 3, 0.12, 'BYN'),
('NUT-M10', 'Гайка М10 8', 'Гайка М10', 10, 1, '{"thread":"M10","diameter_mm":10,"pitch_mm":1.5,"strength_class":"8","coating":"цинк","nut_type":"шестигранная","gost":"5915-70"}', 6000.00, 1200.00, 'Склад №5, Ящик 2', 3, 0.15, 'BYN'),
('NUT-M12', 'Гайка М12 8', 'Гайка М12', 10, 1, '{"thread":"M12","diameter_mm":12,"pitch_mm":1.75,"strength_class":"8","coating":"цинк","nut_type":"шестигранная","gost":"5915-70"}', 4500.00, 900.00, 'Склад №5, Ящик 2', 3, 0.22, 'BYN'),
('NUT-M14', 'Гайка М14 8', 'Гайка М14', 10, 1, '{"thread":"M14","diameter_mm":14,"pitch_mm":2.0,"strength_class":"8","coating":"цинк","nut_type":"шестигранная","gost":"5915-70"}', 3800.00, 750.00, 'Склад №5, Ящик 2', 3, 0.32, 'BYN'),
('NUT-M16', 'Гайка М16 8', 'Гайка М16', 10, 1, '{"thread":"M16","diameter_mm":16,"pitch_mm":2.0,"strength_class":"8","coating":"цинк","nut_type":"шестигранная","gost":"5915-70"}', 3200.00, 650.00, 'Склад №5, Ящик 2', 3, 0.45, 'BYN'),
('NUT-M20', 'Гайка М20 8', 'Гайка М20', 10, 1, '{"thread":"M20","diameter_mm":20,"pitch_mm":2.5,"strength_class":"8","coating":"цинк","nut_type":"шестигранная","gost":"5915-70"}', 2500.00, 500.00, 'Склад №5, Ящик 2', 3, 0.75, 'BYN'),
-- Крепеж - шайбы
('WASHER-6', 'Шайба М6', 'Шайба 6', 8, 1, '{"inner_d_mm":6.4,"outer_d_mm":12,"thickness_mm":1.6,"gost":"11371-78"}', 10000.00, 2000.00, 'Склад №5, Ящик 3', 3, 0.05, 'BYN'),
('WASHER-8', 'Шайба М8', 'Шайба 8', 8, 1, '{"inner_d_mm":8.4,"outer_d_mm":15,"thickness_mm":2,"gost":"11371-78"}', 8500.00, 1700.00, 'Склад №5, Ящик 3', 3, 0.07, 'BYN'),
('WASHER-10', 'Шайба М10', 'Шайба 10', 8, 1, '{"inner_d_mm":10.5,"outer_d_mm":18,"thickness_mm":2,"gost":"11371-78"}', 7000.00, 1400.00, 'Склад №5, Ящик 3', 3, 0.10, 'BYN'),
('WASHER-12', 'Шайба М12', 'Шайба 12', 8, 1, '{"inner_d_mm":13,"outer_d_mm":22,"thickness_mm":2.5,"gost":"11371-78"}', 5500.00, 1100.00, 'Склад №5, Ящик 3', 3, 0.15, 'BYN'),
('WASHER-14', 'Шайба М14', 'Шайба 14', 8, 1, '{"inner_d_mm":15,"outer_d_mm":25,"thickness_mm":3,"gost":"11371-78"}', 4500.00, 900.00, 'Склад №5, Ящик 3', 3, 0.20, 'BYN'),
('WASHER-16', 'Шайба М16', 'Шайба 16', 8, 1, '{"inner_d_mm":17,"outer_d_mm":28,"thickness_mm":3,"gost":"11371-78"}', 4000.00, 800.00, 'Склад №5, Ящик 3', 3, 0.28, 'BYN'),
('WASHER-20', 'Шайба М20', 'Шайба 20', 8, 1, '{"inner_d_mm":21,"outer_d_mm":34,"thickness_mm":4,"gost":"11371-78"}', 3000.00, 600.00, 'Склад №5, Ящик 3', 3, 0.45, 'BYN'),
-- Подшипники
('BRG-6200', 'Подшипник 6200-2RS', 'Подшипник 6200', 11, 1, '{"inner_d_mm":10,"outer_d_mm":30,"width_mm":9,"type":"шариковый","seal":"2RS"}', 200.00, 40.00, 'Склад №5, Ящик 4', 2, 4.50, 'BYN'),
('BRG-6201', 'Подшипник 6201-2RS', 'Подшипник 6201', 11, 1, '{"inner_d_mm":12,"outer_d_mm":32,"width_mm":10,"type":"шариковый","seal":"2RS"}', 180.00, 35.00, 'Склад №5, Ящик 4', 2, 5.00, 'BYN'),
('BRG-6202', 'Подшипник 6202-2RS', 'Подшипник 6202', 11, 1, '{"inner_d_mm":15,"outer_d_mm":35,"width_mm":11,"type":"шариковый","seal":"2RS"}', 160.00, 32.00, 'Склад №5, Ящик 4', 2, 5.50, 'BYN'),
('BRG-6203', 'Подшипник 6203-2RS', 'Подшипник 6203', 11, 1, '{"inner_d_mm":17,"outer_d_mm":40,"width_mm":12,"type":"шариковый","seal":"2RS"}', 140.00, 28.00, 'Склад №5, Ящик 4', 2, 6.00, 'BYN'),
('BRG-6204', 'Подшипник 6204-2RS', 'Подшипник 6204', 11, 1, '{"inner_d_mm":20,"outer_d_mm":47,"width_mm":14,"type":"шариковый","seal":"2RS"}', 120.00, 25.00, 'Склад №5, Ящик 4', 2, 7.00, 'BYN'),
('BRG-6205', 'Подшипник 6205-2RS', 'Подшипник 6205', 11, 1, '{"inner_d_mm":25,"outer_d_mm":52,"width_mm":15,"type":"шариковый","seal":"2RS"}', 150.00, 30.00, 'Склад №5, Ящик 3', 2, 8.50, 'BYN'),
('BRG-6206', 'Подшипник 6206-2RS', 'Подшипник 6206', 11, 1, '{"inner_d_mm":30,"outer_d_mm":62,"width_mm":16,"type":"шариковый","seal":"2RS"}', 130.00, 26.00, 'Склад №5, Ящик 4', 2, 10.50, 'BYN'),
('BRG-6207', 'Подшипник 6207-2RS', 'Подшипник 6207', 11, 1, '{"inner_d_mm":35,"outer_d_mm":72,"width_mm":17,"type":"шариковый","seal":"2RS"}', 110.00, 22.00, 'Склад №5, Ящик 4', 2, 13.00, 'BYN'),
('BRG-6208', 'Подшипник 6208-2RS', 'Подшипник 6208', 11, 1, '{"inner_d_mm":40,"outer_d_mm":80,"width_mm":18,"type":"шариковый","seal":"2RS"}', 95.00, 20.00, 'Склад №5, Ящик 4', 2, 16.00, 'BYN'),
('BRG-6209', 'Подшипник 6209-2RS', 'Подшипник 6209', 11, 1, '{"inner_d_mm":45,"outer_d_mm":85,"width_mm":19,"type":"шариковый","seal":"2RS"}', 85.00, 18.00, 'Склад №5, Ящик 4', 2, 19.00, 'BYN'),
('BRG-6210', 'Подшипник 6210-2RS', 'Подшипник 6210', 11, 1, '{"inner_d_mm":50,"outer_d_mm":90,"width_mm":20,"type":"шариковый","seal":"2RS"}', 75.00, 15.00, 'Склад №5, Ящик 4', 2, 22.00, 'BYN'),
('BRG-6305', 'Подшипник 6305-2RS', 'Подшипник 6305', 11, 1, '{"inner_d_mm":25,"outer_d_mm":62,"width_mm":17,"type":"шариковый","seal":"2RS"}', 100.00, 20.00, 'Склад №5, Ящик 4', 2, 12.00, 'BYN'),
('BRG-6306', 'Подшипник 6306-2RS', 'Подшипник 6306', 11, 1, '{"inner_d_mm":30,"outer_d_mm":72,"width_mm":19,"type":"шариковый","seal":"2RS"}', 90.00, 18.00, 'Склад №5, Ящик 4', 2, 15.00, 'BYN'),
('BRG-6307', 'Подшипник 6307-2RS', 'Подшипник 6307', 11, 1, '{"inner_d_mm":35,"outer_d_mm":80,"width_mm":21,"type":"шариковый","seal":"2RS"}', 80.00, 16.00, 'Склад №5, Ящик 4', 2, 18.50, 'BYN'),
('BRG-6308', 'Подшипник 6308-2RS', 'Подшипник 6308', 11, 1, '{"inner_d_mm":40,"outer_d_mm":90,"width_mm":23,"type":"шариковый","seal":"2RS"}', 70.00, 14.00, 'Склад №5, Ящик 4', 2, 22.00, 'BYN'),
('BRG-6309', 'Подшипник 6309-2RS', 'Подшипник 6309', 11, 1, '{"inner_d_mm":45,"outer_d_mm":100,"width_mm":25,"type":"шариковый","seal":"2RS"}', 65.00, 13.00, 'Склад №5, Ящик 4', 2, 27.00, 'BYN'),
('BRG-6310', 'Подшипник 6310-2RS', 'Подшипник 6310', 11, 1, '{"inner_d_mm":50,"outer_d_mm":110,"width_mm":27,"type":"шариковый","seal":"2RS"}', 55.00, 12.00, 'Склад №5, Ящик 4', 2, 32.00, 'BYN'),
('BRG-6311', 'Подшипник 6311-2RS', 'Подшипник 6311', 11, 1, '{"inner_d_mm":55,"outer_d_mm":120,"width_mm":29,"type":"шариковый","seal":"2RS"}', 50.00, 10.00, 'Склад №5, Ящик 4', 2, 38.00, 'BYN'),
('BRG-6312', 'Подшипник 6312-2RS', 'Подшипник 6312', 11, 1, '{"inner_d_mm":60,"outer_d_mm":130,"width_mm":31,"type":"шариковый","seal":"2RS"}', 45.00, 10.00, 'Склад №5, Ящик 4', 2, 45.00, 'BYN'),
-- Лакоткань и изоляция
('INSUL-VARNISH-0.15', 'Лакоткань 0.15мм', 'Лакоткань 0.15', 5, 3, '{"thickness_mm":0.15,"width_mm":1000,"class":"F","gost":"2110-78"}', 250.00, 50.00, 'Склад №4, Секция Г', 2, 45.00, 'BYN'),
('INSUL-VARNISH-0.20', 'Лакоткань 0.20мм', 'Лакоткань 0.20', 5, 3, '{"thickness_mm":0.20,"width_mm":1000,"class":"F","gost":"2110-78"}', 220.00, 45.00, 'Склад №4, Секция Г', 2, 52.00, 'BYN'),
('INSUL-VARNISH-0.25', 'Лакоткань 0.25мм', 'Лакоткань 0.25', 5, 3, '{"thickness_mm":0.25,"width_mm":1000,"class":"F","gost":"2110-78"}', 200.00, 40.00, 'Склад №4, Секция Г', 2, 60.00, 'BYN'),
('INSUL-PAPER-NOMEX', 'Бумага электроизоляционная Nomex', 'Бумага Nomex', 5, 2, '{"thickness_mm":0.25,"width_mm":1000,"class":"H","manufacturer":"DuPont"}', 180.00, 35.00, 'Склад №4, Секция Г', 2, 120.00, 'BYN'),
('INSUL-TUBE-HEAT', 'Трубка термоусадочная', 'Трубка ТУТ', 5, 3, '{"shrink_ratio":"2:1","voltage_kv":1,"class":"F"}', 500.00, 100.00, 'Склад №4, Секция Г', 2, 8.50, 'BYN'),
-- Пропиточные лаки
('LACQUER-IMPREG-MP', 'Лак пропиточный МП-95', 'Лак МП-95', 5, 4, '{"type":"пропиточный","viscosity_s":"40-60","drying_time_h":"2","gost":"801-78"}', 350.00, 70.00, 'Склад №4, Секция Д', 2, 85.00, 'BYN'),
('LACQUER-IMPREG-FL', 'Лак пропиточный ФЛ-98', 'Лак ФЛ-98', 5, 4, '{"type":"пропиточный","viscosity_s":"35-50","drying_time_h":"1.5","gost":"801-78"}', 280.00, 55.00, 'Склад №4, Секция Д', 2, 95.00, 'BYN'),
('LACQUER-ENAMEL-PE', 'Лак эмальпроводный ПЭ-933', 'Лак ПЭ-933', 5, 4, '{"type":"эмальпроводный","wire_diameter_mm":"0.1-2.5","gost":"32336-2014"}', 420.00, 85.00, 'Склад №4, Секция Д', 2, 110.00, 'BYN'),
-- Обмоточные провода
('WIRE-ENAMEL-0.50', 'Провод обмоточный ПЭТВ-2 0.50мм', 'Провод 0.50', 6, 3, '{"diameter_mm":0.50,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 85.00, 15.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.63', 'Провод обмоточный ПЭТВ-2 0.63мм', 'Провод 0.63', 6, 3, '{"diameter_mm":0.63,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 75.00, 13.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.71', 'Провод обмоточный ПЭТВ-2 0.71мм', 'Провод 0.71', 6, 3, '{"diameter_mm":0.71,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 68.00, 12.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.80', 'Провод обмоточный ПЭТВ-2 0.80мм', 'Провод 0.80', 6, 3, '{"diameter_mm":0.80,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 62.00, 11.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-0.90', 'Провод обмоточный ПЭТВ-2 0.90мм', 'Провод 0.90', 6, 3, '{"diameter_mm":0.90,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 55.00, 10.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.00', 'Провод обмоточный ПЭТВ-2 1.00мм', 'Провод 1.00', 6, 3, '{"diameter_mm":1.00,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 50.00, 9.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.12', 'Провод обмоточный ПЭТВ-2 1.12мм', 'Провод 1.12', 6, 3, '{"diameter_mm":1.12,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 45.00, 8.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.25', 'Провод обмоточный ПЭТВ-2 1.25мм', 'Провод 1.25', 6, 3, '{"diameter_mm":1.25,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 42.00, 7.50, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.40', 'Провод обмоточный ПЭТВ-2 1.40мм', 'Провод 1.40', 6, 3, '{"diameter_mm":1.40,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 38.00, 7.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.60', 'Провод обмоточный ПЭТВ-2 1.60мм', 'Провод 1.60', 6, 3, '{"diameter_mm":1.60,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 35.00, 6.50, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-1.80', 'Провод обмоточный ПЭТВ-2 1.80мм', 'Провод 1.80', 6, 3, '{"diameter_mm":1.80,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 32.00, 6.00, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
('WIRE-ENAMEL-2.00', 'Провод обмоточный ПЭТВ-2 2.00мм', 'Провод 2.00', 6, 3, '{"diameter_mm":2.00,"insulation":"ПЭТВ-2","class":"F","gost":"32336-2014"}', 28.00, 5.50, 'Склад №4, Секция Е', 2, 65.00, 'BYN'),
-- Краски и покрытия
('PAINT-POLYMER', 'Краска порошковая полиэфирная', 'Краска порошок', 5, 2, '{"type":"порошковая","color":"серая RAL 7035","finish":"полуматовая"}', 450.00, 80.00, 'Склад №6, Секция А', 1, 12.50, 'BYN'),
('PAINT-ENAMEL', 'Эмаль ПФ-115', 'Эмаль ПФ-115', 5, 4, '{"type":"масляная","color":"серая","gost":"6465-76"}', 380.00, 70.00, 'Склад №6, Секция А', 1, 8.50, 'BYN'),
('PAINT-PRIMER', 'Грунтовка ГФ-021', 'Грунтовка ГФ-021', 5, 4, '{"type":"грунтовка","color":"красно-коричневая","gost":"25129-82"}', 520.00, 100.00, 'Склад №6, Секция А', 1, 6.50, 'BYN'),
-- Смазочные материалы
('LUBE-GREASE-LITO', 'Смазка Литол-24', 'Литол-24', 5, 2, '{"type":"пластичная","temp_range_c":"-40..+120","gost":"21150-75"}', 180.00, 35.00, 'Склад №6, Секция Б', 1, 5.50, 'BYN'),
('LUBE-GREASE-UNIO', 'Смазка Униол-2М', 'Униол-2М', 5, 2, '{"type":"пластичная","temp_range_c":"-50..+150","gost":"21150-75"}', 150.00, 30.00, 'Склад №6, Секция Б', 1, 8.50, 'BYN'),
('LUBE-OIL-IND', 'Масло индустриальное И-20А', 'Масло И-20А', 5, 4, '{"type":"индустриальное","viscosity_cSt":"20","gost":"20799-88"}', 220.00, 45.00, 'Склад №6, Секция Б', 1, 4.50, 'BYN'),
('LUBE-OIL-TRANS', 'Масло трансформаторное ТКп', 'Масло ТКп', 5, 4, '{"type":"трансформаторное","pour_point_c":"-45","gost":"10121-75"}', 280.00, 55.00, 'Склад №6, Секция Б', 1, 5.50, 'BYN');

INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
-- Общепромышленные двигатели
('ADM-56A2', 'Двигатель АДМ 56A2', 2, 1, '{"power_kw":0.18,"rpm":3000,"voltage_v":380,"frame":"56A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm56a2.jpg', 180.00, 'BYN', TRUE),
('ADM-56B2', 'Двигатель АДМ 56B2', 2, 1, '{"power_kw":0.25,"rpm":3000,"voltage_v":380,"frame":"56B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm56b2.jpg', 200.00, 'BYN', TRUE),
('ADM-56A4', 'Двигатель АДМ 56A4', 2, 1, '{"power_kw":0.12,"rpm":1500,"voltage_v":380,"frame":"56A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm56a4.jpg', 175.00, 'BYN', TRUE),
('ADM-56B4', 'Двигатель АДМ 56B4', 2, 1, '{"power_kw":0.18,"rpm":1500,"voltage_v":380,"frame":"56B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm56b4.jpg', 195.00, 'BYN', TRUE),
('ADM-63A2', 'Двигатель АДМ 63A2', 2, 1, '{"power_kw":0.37,"rpm":3000,"voltage_v":380,"frame":"63A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm63a2.jpg', 220.00, 'BYN', TRUE),
('ADM-63B2', 'Двигатель АДМ 63B2', 2, 1, '{"power_kw":0.55,"rpm":3000,"voltage_v":380,"frame":"63B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm63b2.jpg', 250.00, 'BYN', TRUE),
('ADM-63A4', 'Двигатель АДМ 63A4', 2, 1, '{"power_kw":0.25,"rpm":1500,"voltage_v":380,"frame":"63A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm63a4.jpg', 215.00, 'BYN', TRUE),
('ADM-63B4', 'Двигатель АДМ 63B4', 2, 1, '{"power_kw":0.37,"rpm":1500,"voltage_v":380,"frame":"63B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm63b4.jpg', 240.00, 'BYN', TRUE),
('ADM-71A2', 'Двигатель АДМ 71A2', 2, 1, '{"power_kw":0.55,"rpm":3000,"voltage_v":380,"frame":"71A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm71a2.jpg', 280.00, 'BYN', TRUE),
('ADM-71B2', 'Двигатель АДМ 71B2', 2, 1, '{"power_kw":0.75,"rpm":3000,"voltage_v":380,"frame":"71B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm71b2.jpg', 310.00, 'BYN', TRUE),
('ADM-71A4', 'Двигатель АДМ 71A4', 2, 1, '{"power_kw":0.37,"rpm":1500,"voltage_v":380,"frame":"71A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm71a4.jpg', 275.00, 'BYN', TRUE),
('ADM-71B4', 'Двигатель АДМ 71B4', 2, 1, '{"power_kw":0.55,"rpm":1500,"voltage_v":380,"frame":"71B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm71b4.jpg', 300.00, 'BYN', TRUE),
('ADM-80A2', 'Двигатель АДМ 80A2', 2, 1, '{"power_kw":1.5,"rpm":3000,"voltage_v":380,"frame":"80A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm80a2.jpg', 380.00, 'BYN', TRUE),
('ADM-80B2', 'Двигатель АДМ 80B2', 2, 1, '{"power_kw":2.2,"rpm":3000,"voltage_v":380,"frame":"80B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm80b2.jpg', 420.00, 'BYN', TRUE),
('ADM-80A4', 'Двигатель АДМ 80A4', 2, 1, '{"power_kw":1.1,"rpm":1500,"voltage_v":380,"frame":"80A","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm80a4.jpg', 350.00, 'BYN', TRUE),
('ADM-80B4', 'Двигатель АДМ 80B4', 2, 1, '{"power_kw":1.5,"rpm":1500,"voltage_v":380,"frame":"80B","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm80b4.jpg', 390.00, 'BYN', TRUE),
('ADM-90L2', 'Двигатель АДМ 90L2', 2, 1, '{"power_kw":3.0,"rpm":3000,"voltage_v":380,"frame":"90L","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm90l2.jpg', 550.00, 'BYN', TRUE),
('ADM-90L4', 'Двигатель АДМ 90L4', 2, 1, '{"power_kw":2.2,"rpm":1500,"voltage_v":380,"frame":"90L","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm90l4.jpg', 520.00, 'BYN', TRUE),
('ADM-90L6', 'Двигатель АДМ 90L6', 2, 1, '{"power_kw":1.5,"rpm":1000,"voltage_v":380,"frame":"90L","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm90l6.jpg', 580.00, 'BYN', TRUE),
('ADM-100S2', 'Двигатель АДМ 100S2', 2, 1, '{"power_kw":4.0,"rpm":3000,"voltage_v":380,"frame":"100S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm100s2.jpg', 680.00, 'BYN', TRUE),
('ADM-100L2', 'Двигатель АДМ 100L2', 2, 1, '{"power_kw":5.5,"rpm":3000,"voltage_v":380,"frame":"100L","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm100l2.jpg', 750.00, 'BYN', TRUE),
('ADM-100S4', 'Двигатель АДМ 100S4', 2, 1, '{"power_kw":3.0,"rpm":1500,"voltage_v":380,"frame":"100S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm100s4.jpg', 650.00, 'BYN', TRUE),
('ADM-100L4', 'Двигатель АДМ 100L4', 2, 1, '{"power_kw":4.0,"rpm":1500,"voltage_v":380,"frame":"100L","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm100l4.jpg', 780.00, 'BYN', TRUE),
('ADM-100L6', 'Двигатель АДМ 100L6', 2, 1, '{"power_kw":2.2,"rpm":1000,"voltage_v":380,"frame":"100L","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm100l6.jpg', 820.00, 'BYN', TRUE),
('ADM-100L8', 'Двигатель АДМ 100L8', 2, 1, '{"power_kw":1.5,"rpm":750,"voltage_v":380,"frame":"100L","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm100l8.jpg', 900.00, 'BYN', TRUE),
('ADM-112MA2', 'Двигатель АДМ 112MA2', 2, 1, '{"power_kw":5.5,"rpm":3000,"voltage_v":380,"frame":"112M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm112ma2.jpg', 920.00, 'BYN', TRUE),
('ADM-112MB2', 'Двигатель АДМ 112MB2', 2, 1, '{"power_kw":7.5,"rpm":3000,"voltage_v":380,"frame":"112M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm112mb2.jpg', 1050.00, 'BYN', TRUE),
('ADM-112MA4', 'Двигатель АДМ 112MA4', 2, 1, '{"power_kw":4.0,"rpm":1500,"voltage_v":380,"frame":"112M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm112ma4.jpg', 880.00, 'BYN', TRUE),
('ADM-112MB4', 'Двигатель АДМ 112MB4', 2, 1, '{"power_kw":5.5,"rpm":1500,"voltage_v":380,"frame":"112M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm112mb4.jpg', 980.00, 'BYN', TRUE),
('ADM-112MA6', 'Двигатель АДМ 112MA6', 2, 1, '{"power_kw":3.0,"rpm":1000,"voltage_v":380,"frame":"112M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm112ma6.jpg', 1020.00, 'BYN', TRUE),
('ADM-112MB6', 'Двигатель АДМ 112MB6', 2, 1, '{"power_kw":4.0,"rpm":1000,"voltage_v":380,"frame":"112M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm112mb6.jpg', 1120.00, 'BYN', TRUE),
('ADM-132S2', 'Двигатель АДМ 132S2', 2, 1, '{"power_kw":7.5,"rpm":3000,"voltage_v":380,"frame":"132S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm132s2.jpg', 1250.00, 'BYN', TRUE),
('ADM-132M2', 'Двигатель АДМ 132M2', 2, 1, '{"power_kw":11,"rpm":3000,"voltage_v":380,"frame":"132M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm132m2.jpg', 1450.00, 'BYN', TRUE),
('ADM-132S4', 'Двигатель АДМ 132S4', 2, 1, '{"power_kw":5.5,"rpm":1500,"voltage_v":380,"frame":"132S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm132s4.jpg', 1180.00, 'BYN', TRUE),
('ADM-132M4', 'Двигатель АДМ 132M4', 2, 1, '{"power_kw":7.5,"rpm":1500,"voltage_v":380,"frame":"132M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm132m4.jpg', 1350.00, 'BYN', TRUE),
('ADM-132S6', 'Двигатель АДМ 132S6', 2, 1, '{"power_kw":4.0,"rpm":1000,"voltage_v":380,"frame":"132S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm132s6.jpg', 1380.00, 'BYN', TRUE),
('ADM-132M6', 'Двигатель АДМ 132M6', 2, 1, '{"power_kw":5.5,"rpm":1000,"voltage_v":380,"frame":"132M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm132m6.jpg', 1520.00, 'BYN', TRUE),
('ADM-160S2', 'Двигатель АДМ 160S2', 2, 1, '{"power_kw":15,"rpm":3000,"voltage_v":380,"frame":"160S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm160s2.jpg', 1850.00, 'BYN', TRUE),
('ADM-160M2', 'Двигатель АДМ 160M2', 2, 1, '{"power_kw":18.5,"rpm":3000,"voltage_v":380,"frame":"160M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm160m2.jpg', 2100.00, 'BYN', TRUE),
('ADM-160S4', 'Двигатель АДМ 160S4', 2, 1, '{"power_kw":11,"rpm":1500,"voltage_v":380,"frame":"160S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm160s4.jpg', 1750.00, 'BYN', TRUE),
('ADM-160M4', 'Двигатель АДМ 160M4', 2, 1, '{"power_kw":15,"rpm":1500,"voltage_v":380,"frame":"160M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm160m4.jpg', 1950.00, 'BYN', TRUE),
('ADM-160S6', 'Двигатель АДМ 160S6', 2, 1, '{"power_kw":7.5,"rpm":1000,"voltage_v":380,"frame":"160S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm160s6.jpg', 1920.00, 'BYN', TRUE),
('ADM-160M6', 'Двигатель АДМ 160M6', 2, 1, '{"power_kw":11,"rpm":1000,"voltage_v":380,"frame":"160M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm160m6.jpg', 2150.00, 'BYN', TRUE),
('ADM-160M8', 'Двигатель АДМ 160M8', 2, 1, '{"power_kw":7.5,"rpm":750,"voltage_v":380,"frame":"160M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm160m8.jpg', 2380.00, 'BYN', TRUE),
('ADM-180S2', 'Двигатель АДМ 180S2', 2, 1, '{"power_kw":22,"rpm":3000,"voltage_v":380,"frame":"180S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm180s2.jpg', 2450.00, 'BYN', TRUE),
('ADM-180M2', 'Двигатель АДМ 180M2', 2, 1, '{"power_kw":30,"rpm":3000,"voltage_v":380,"frame":"180M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm180m2.jpg', 2850.00, 'BYN', TRUE),
('ADM-180S4', 'Двигатель АДМ 180S4', 2, 1, '{"power_kw":18.5,"rpm":1500,"voltage_v":380,"frame":"180S","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm180s4.jpg', 2350.00, 'BYN', TRUE),
('ADM-180M4', 'Двигатель АДМ 180M4', 2, 1, '{"power_kw":22,"rpm":1500,"voltage_v":380,"frame":"180M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm180m4.jpg', 2650.00, 'BYN', TRUE),
('ADM-180M6', 'Двигатель АДМ 180M6', 2, 1, '{"power_kw":15,"rpm":1000,"voltage_v":380,"frame":"180M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm180m6.jpg', 2750.00, 'BYN', TRUE),
('ADM-180M8', 'Двигатель АДМ 180M8', 2, 1, '{"power_kw":11,"rpm":750,"voltage_v":380,"frame":"180M","efficiency_class":"IE2","mounting":"IM1081"}', 'motor_adm180m8.jpg', 2950.00, 'BYN', TRUE),
-- Взрывозащищенные двигатели
('AID-63A4', 'Двигатель взрывозащищенный АИД 63A4', 3, 1, '{"power_kw":0.25,"rpm":1500,"voltage_v":380,"frame":"63A","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid63a4.jpg', 450.00, 'BYN', TRUE),
('AID-71A4', 'Двигатель взрывозащищенный АИД 71A4', 3, 1, '{"power_kw":0.37,"rpm":1500,"voltage_v":380,"frame":"71A","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid71a4.jpg', 520.00, 'BYN', TRUE),
('AID-80A4', 'Двигатель взрывозащищенный АИД 80A4', 3, 1, '{"power_kw":1.1,"rpm":1500,"voltage_v":380,"frame":"80A","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid80a4.jpg', 680.00, 'BYN', TRUE),
('AID-90L4', 'Двигатель взрывозащищенный АИД 90L4', 3, 1, '{"power_kw":2.2,"rpm":1500,"voltage_v":380,"frame":"90L","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid90l4.jpg', 850.00, 'BYN', TRUE),
('AID-100L4', 'Двигатель взрывозащищенный АИД 100L4', 3, 1, '{"power_kw":4.0,"rpm":1500,"voltage_v":380,"frame":"100L","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid100l4.jpg', 1150.00, 'BYN', TRUE),
('AID-112M4', 'Двигатель взрывозащищенный АИД 112M4', 3, 1, '{"power_kw":5.5,"rpm":1500,"voltage_v":380,"frame":"112M","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid112m4.jpg', 1450.00, 'BYN', TRUE),
('AID-132S4', 'Двигатель взрывозащищенный АИД 132S4', 3, 1, '{"power_kw":7.5,"rpm":1500,"voltage_v":380,"frame":"132S","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid132s4.jpg', 1850.00, 'BYN', TRUE),
('AID-132M4', 'Двигатель взрывозащищенный АИД 132M4', 3, 1, '{"power_kw":11,"rpm":1500,"voltage_v":380,"frame":"132M","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid132m4.jpg', 2150.00, 'BYN', TRUE),
('AID-160S4', 'Двигатель взрывозащищенный АИД 160S4', 3, 1, '{"power_kw":15,"rpm":1500,"voltage_v":380,"frame":"160S","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid160s4.jpg', 2850.00, 'BYN', TRUE),
('AID-160M4', 'Двигатель взрывозащищенный АИД 160M4', 3, 1, '{"power_kw":18.5,"rpm":1500,"voltage_v":380,"frame":"160M","ex_marking":"1ExdIIBT4","efficiency_class":"IE2"}', 'motor_aid160m4.jpg', 3250.00, 'BYN', TRUE),
-- Крановые двигатели
('MTF-112M6', 'Двигатель крановый МТF 112M6', 4, 1, '{"power_kw":5.0,"rpm":900,"voltage_v":380,"frame":"112M","duty_type":"S3","insulation_class":"H"}', 'motor_mtf112m6.jpg', 1650.00, 'BYN', TRUE),
('MTF-132M6', 'Двигатель крановый МТF 132M6', 4, 1, '{"power_kw":7.5,"rpm":900,"voltage_v":380,"frame":"132M","duty_type":"S3","insulation_class":"H"}', 'motor_mtf132m6.jpg', 2050.00, 'BYN', TRUE),
('MTF-160M6', 'Двигатель крановый МТF 160M6', 4, 1, '{"power_kw":11,"rpm":900,"voltage_v":380,"frame":"160M","duty_type":"S3","insulation_class":"H"}', 'motor_mtf160m6.jpg', 2750.00, 'BYN', TRUE),
('MTF-160L6', 'Двигатель крановый МТF 160L6', 4, 1, '{"power_kw":15,"rpm":900,"voltage_v":380,"frame":"160L","duty_type":"S3","insulation_class":"H"}', 'motor_mtf160l6.jpg', 3150.00, 'BYN', TRUE),
('MTF-180M6', 'Двигатель крановый МТF 180M6', 4, 1, '{"power_kw":18.5,"rpm":900,"voltage_v":380,"frame":"180M","duty_type":"S3","insulation_class":"H"}', 'motor_mtf180m6.jpg', 3650.00, 'BYN', TRUE),
('MTF-200M6', 'Двигатель крановый МТF 200M6', 4, 1, '{"power_kw":22,"rpm":900,"voltage_v":380,"frame":"200M","duty_type":"S3","insulation_class":"H"}', 'motor_mtf200m6.jpg', 4250.00, 'BYN', TRUE),
('MTF-225M6', 'Двигатель крановый МТF 225M6', 4, 1, '{"power_kw":30,"rpm":900,"voltage_v":380,"frame":"225M","duty_type":"S3","insulation_class":"H"}', 'motor_mtf225m6.jpg', 5150.00, 'BYN', TRUE),
-- Дизельные генераторы
('ADS-5000', 'Генератор дизельный 5кВт', 6, 1, '{"power_kw":5,"fuel_type":"дизель","start_type":"электростартер","noise_db":65,"phases":1}', 'gen_ads5000.jpg', 1200.00, 'BYN', TRUE),
('ADS-8000', 'Генератор дизельный 8кВт', 6, 1, '{"power_kw":8,"fuel_type":"дизель","start_type":"электростартер","noise_db":68,"phases":1}', 'gen_ads8000.jpg', 1650.00, 'BYN', TRUE),
('ADS-10000', 'Генератор дизельный 10кВт', 6, 1, '{"power_kw":10,"fuel_type":"дизель","start_type":"электростартер","noise_db":70,"phases":1}', 'gen_ads10000.jpg', 2100.00, 'BYN', TRUE),
('ADS-15000', 'Генератор дизельный 15кВт', 6, 1, '{"power_kw":15,"fuel_type":"дизель","start_type":"электростартер","noise_db":72,"phases":3}', 'gen_ads15000.jpg', 2850.00, 'BYN', TRUE),
('ADS-20000', 'Генератор дизельный 20кВт', 6, 1, '{"power_kw":20,"fuel_type":"дизель","start_type":"электростартер","noise_db":74,"phases":3}', 'gen_ads20000.jpg', 3500.00, 'BYN', TRUE),
('ADS-30000', 'Генератор дизельный 30кВт', 6, 1, '{"power_kw":30,"fuel_type":"дизель","start_type":"электростартер","noise_db":76,"phases":3}', 'gen_ads30000.jpg', 4800.00, 'BYN', TRUE),
('ADS-50000', 'Генератор дизельный 50кВт', 6, 1, '{"power_kw":50,"fuel_type":"дизель","start_type":"электростартер","noise_db":78,"phases":3}', 'gen_ads50000.jpg', 7200.00, 'BYN', TRUE),
('ADS-100000', 'Генератор дизельный 100кВт', 6, 1, '{"power_kw":100,"fuel_type":"дизель","start_type":"электростартер","noise_db":82,"phases":3}', 'gen_ads100000.jpg', 12500.00, 'BYN', TRUE),
-- Бензиновые генераторы
('ABS-2000', 'Генератор бензиновый 2кВт', 7, 1, '{"power_kw":2,"fuel_type":"бензин","start_type":"ручной","noise_db":62,"phases":1}', 'gen_abs2000.jpg', 450.00, 'BYN', TRUE),
('ABS-3500', 'Генератор бензиновый 3.5кВт', 7, 1, '{"power_kw":3.5,"fuel_type":"бензин","start_type":"ручной","noise_db":65,"phases":1}', 'gen_abs3500.jpg', 620.00, 'BYN', TRUE),
('ABS-5500', 'Генератор бензиновый 5.5кВт', 7, 1, '{"power_kw":5.5,"fuel_type":"бензин","start_type":"электростартер","noise_db":68,"phases":1}', 'gen_abs5500.jpg', 850.00, 'BYN', TRUE),
('ABS-7000', 'Генератор бензиновый 7кВт', 7, 1, '{"power_kw":7,"fuel_type":"бензин","start_type":"электростартер","noise_db":70,"phases":3}', 'gen_abs7000.jpg', 1150.00, 'BYN', TRUE),
-- Масляные трансформаторы
('TMG-25', 'Трансформатор ТМГ-25 кВА', 9, 1, '{"power_kva":25,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg25.jpg', 1850.00, 'BYN', TRUE),
('TMG-40', 'Трансформатор ТМГ-40 кВА', 9, 1, '{"power_kva":40,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg40.jpg', 2150.00, 'BYN', TRUE),
('TMG-63', 'Трансформатор ТМГ-63 кВА', 9, 1, '{"power_kva":63,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg63.jpg', 2550.00, 'BYN', TRUE),
('TMG-100', 'Трансформатор ТМГ-100 кВА', 9, 1, '{"power_kva":100,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg100.jpg', 3200.00, 'BYN', TRUE),
('TMG-160', 'Трансформатор ТМГ-160 кВА', 9, 1, '{"power_kva":160,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg160.jpg', 4100.00, 'BYN', TRUE),
('TMG-250', 'Трансформатор ТМГ-250 кВА', 9, 1, '{"power_kva":250,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg250.jpg', 5200.00, 'BYN', TRUE),
('TMG-400', 'Трансформатор ТМГ-400 кВА', 9, 1, '{"power_kva":400,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg400.jpg', 6800.00, 'BYN', TRUE),
('TMG-630', 'Трансформатор ТМГ-630 кВА', 9, 1, '{"power_kva":630,"voltage_primary_kv":10,"voltage_secondary_v":400,"cooling":"oil","connection":"Yyn0"}', 'tr_tmg630.jpg', 8500.00, 'BYN', TRUE),
-- Сухие трансформаторы
('TS-25', 'Трансформатор ТС-25 кВА', 10, 1, '{"power_kva":25,"voltage_primary_kv":0.4,"voltage_secondary_v":230,"cooling":"air","connection":"Single-phase"}', 'tr_ts25.jpg', 1250.00, 'BYN', TRUE),
('TS-40', 'Трансформатор ТС-40 кВА', 10, 1, '{"power_kva":40,"voltage_primary_kv":0.4,"voltage_secondary_v":230,"cooling":"air","connection":"Single-phase"}', 'tr_ts40.jpg', 1550.00, 'BYN', TRUE),
('TS-63', 'Трансформатор ТС-63 кВА', 10, 1, '{"power_kva":63,"voltage_primary_kv":0.4,"voltage_secondary_v":230,"cooling":"air","connection":"Single-phase"}', 'tr_ts63.jpg', 1950.00, 'BYN', TRUE),
('TS-100', 'Трансформатор ТС-100 кВА', 10, 1, '{"power_kva":100,"voltage_primary_kv":0.4,"voltage_secondary_v":230,"cooling":"air","connection":"Single-phase"}', 'tr_ts100.jpg', 2650.00, 'BYN', TRUE),
-- Вводно-распределительные устройства
('VRU-100', 'ВРУ-100А', 12, 1, '{"current_a":100,"voltage_v":380,"ip_rating":"IP31","panels":1}', 'vru100.jpg', 850.00, 'BYN', TRUE),
('VRU-250', 'ВРУ-250А', 12, 1, '{"current_a":250,"voltage_v":380,"ip_rating":"IP31","panels":2}', 'vru250.jpg', 1450.00, 'BYN', TRUE),
('VRU-400', 'ВРУ-400А', 12, 1, '{"current_a":400,"voltage_v":380,"ip_rating":"IP31","panels":3}', 'vru400.jpg', 2150.00, 'BYN', TRUE),
('VRU-630', 'ВРУ-630А', 12, 1, '{"current_a":630,"voltage_v":380,"ip_rating":"IP31","panels":4}', 'vru630.jpg', 3250.00, 'BYN', TRUE),
-- Щиты управления
('SCH-CONTROL-1', 'Щит управления на 1 двигатель', 13, 1, '{"motors":1,"max_current_a":32,"voltage_v":380,"ip_rating":"IP54","starter_type":"DOL"}', 'sch_ctrl1.jpg', 450.00, 'BYN', TRUE),
('SCH-CONTROL-3', 'Щит управления на 3 двигателя', 13, 1, '{"motors":3,"max_current_a":63,"voltage_v":380,"ip_rating":"IP54","starter_type":"DOL"}', 'sch_ctrl3.jpg', 850.00, 'BYN', TRUE),
('SCH-CONTROL-5', 'Щит управления на 5 двигателей', 13, 1, '{"motors":5,"max_current_a":100,"voltage_v":380,"ip_rating":"IP54","starter_type":"DOL"}', 'sch_ctrl5.jpg', 1350.00, 'BYN', TRUE),
('SCH-VFD-1', 'Щит управления с ЧП 1 двигатель', 13, 1, '{"motors":1,"max_current_a":32,"voltage_v":380,"ip_rating":"IP54","starter_type":"VFD"}', 'sch_vfd1.jpg', 1250.00, 'BYN', TRUE),
('SCH-VFD-3', 'Щит управления с ЧП 3 двигателя', 13, 1, '{"motors":3,"max_current_a":63,"voltage_v":380,"ip_rating":"IP54","starter_type":"VFD"}', 'sch_vfd3.jpg', 2450.00, 'BYN', TRUE),
-- Щиты освещения
('SCH-LIGHT-12', 'Щит освещения на 12 групп', 14, 1, '{"groups":12,"max_current_a":63,"voltage_v":230,"ip_rating":"IP31"}', 'sch_light12.jpg', 550.00, 'BYN', TRUE),
('SCH-LIGHT-24', 'Щит освещения на 24 группы', 14, 1, '{"groups":24,"max_current_a":100,"voltage_v":230,"ip_rating":"IP31"}', 'sch_light24.jpg', 850.00, 'BYN', TRUE),
('SCH-LIGHT-36', 'Щит освещения на 36 групп', 14, 1, '{"groups":36,"max_current_a":160,"voltage_v":230,"ip_rating":"IP31"}', 'sch_light36.jpg', 1250.00, 'BYN', TRUE),
-- Запчасти - роторы
('ROTOR-80A4', 'Ротор для АДМ 80A4', 16, 1, '{"motor_article":"ADM-80A4","diameter_mm":80,"length_mm":120,"shaft_d_mm":19}', 'rotor_80a4.jpg', 120.00, 'BYN', TRUE),
('ROTOR-90L4', 'Ротор для АДМ 90L4', 16, 1, '{"motor_article":"ADM-90L4","diameter_mm":95,"length_mm":140,"shaft_d_mm":24}', 'rotor_90l4.jpg', 165.00, 'BYN', TRUE),
('ROTOR-100L4', 'Ротор для АДМ 100L4', 16, 1, '{"motor_article":"ADM-100L4","diameter_mm":110,"length_mm":160,"shaft_d_mm":28}', 'rotor_100l4.jpg', 220.00, 'BYN', TRUE),
('ROTOR-112M4', 'Ротор для АДМ 112M4', 16, 1, '{"motor_article":"ADM-112M4","diameter_mm":125,"length_mm":180,"shaft_d_mm":30}', 'rotor_112m4.jpg', 285.00, 'BYN', TRUE),
('ROTOR-132S4', 'Ротор для АДМ 132S4', 16, 1, '{"motor_article":"ADM-132S4","diameter_mm":140,"length_mm":200,"shaft_d_mm":35}', 'rotor_132s4.jpg', 350.00, 'BYN', TRUE),
('ROTOR-132M4', 'Ротор для АДМ 132M4', 16, 1, '{"motor_article":"ADM-132M4","diameter_mm":140,"length_mm":220,"shaft_d_mm":38}', 'rotor_132m4.jpg', 395.00, 'BYN', TRUE),
('ROTOR-160S4', 'Ротор для АДМ 160S4', 16, 1, '{"motor_article":"ADM-160S4","diameter_mm":165,"length_mm":240,"shaft_d_mm":42}', 'rotor_160s4.jpg', 485.00, 'BYN', TRUE),
('ROTOR-160M4', 'Ротор для АДМ 160M4', 16, 1, '{"motor_article":"ADM-160M4","diameter_mm":165,"length_mm":280,"shaft_d_mm":48}', 'rotor_160m4.jpg', 560.00, 'BYN', TRUE),
-- Запчасти - статоры
('STATOR-80A4', 'Статор для АДМ 80A4', 17, 1, '{"motor_article":"ADM-80A4","outer_d_mm":130,"inner_d_mm":80,"length_mm":100,"slots":24}', 'stator_80a4.jpg', 145.00, 'BYN', TRUE),
('STATOR-90L4', 'Статор для АДМ 90L4', 17, 1, '{"motor_article":"ADM-90L4","outer_d_mm":155,"inner_d_mm":95,"length_mm":125,"slots":36}', 'stator_90l4.jpg', 195.00, 'BYN', TRUE),
('STATOR-100L4', 'Статор для АДМ 100L4', 17, 1, '{"motor_article":"ADM-100L4","outer_d_mm":180,"inner_d_mm":110,"length_mm":145,"slots":36}', 'stator_100l4.jpg', 265.00, 'BYN', TRUE),
('STATOR-112M4', 'Статор для АДМ 112M4', 17, 1, '{"motor_article":"ADM-112M4","outer_d_mm":200,"inner_d_mm":125,"length_mm":165,"slots":48}', 'stator_112m4.jpg', 335.00, 'BYN', TRUE),
('STATOR-132S4', 'Статор для АДМ 132S4', 17, 1, '{"motor_article":"ADM-132S4","outer_d_mm":225,"inner_d_mm":140,"length_mm":180,"slots":48}', 'stator_132s4.jpg', 420.00, 'BYN', TRUE),
('STATOR-132M4', 'Статор для АДМ 132M4', 17, 1, '{"motor_article":"ADM-132M4","outer_d_mm":225,"inner_d_mm":140,"length_mm":200,"slots":48}', 'stator_132m4.jpg', 475.00, 'BYN', TRUE),
('STATOR-160S4', 'Статор для АДМ 160S4', 17, 1, '{"motor_article":"ADM-160S4","outer_d_mm":260,"inner_d_mm":165,"length_mm":220,"slots":60}', 'stator_160s4.jpg', 585.00, 'BYN', TRUE),
('STATOR-160M4', 'Статор для АДМ 160M4', 17, 1, '{"motor_article":"ADM-160M4","outer_d_mm":260,"inner_d_mm":165,"length_mm":260,"slots":60}', 'stator_160m4.jpg', 680.00, 'BYN', TRUE),
-- Запчасти - подшипниковые щиты
('SHIELD-FRONT-80', 'Щит подшипниковый передний 80', 18, 1, '{"motor_frame":"80","position":"front","bearing_type":"6204"}', 'shield_front80.jpg', 45.00, 'BYN', TRUE),
('SHIELD-REAR-80', 'Щит подшипниковый задний 80', 18, 1, '{"motor_frame":"80","position":"rear","bearing_type":"6204"}', 'shield_rear80.jpg', 45.00, 'BYN', TRUE),
('SHIELD-FRONT-90', 'Щит подшипниковый передний 90', 18, 1, '{"motor_frame":"90","position":"front","bearing_type":"6205"}', 'shield_front90.jpg', 55.00, 'BYN', TRUE),
('SHIELD-REAR-90', 'Щит подшипниковый задний 90', 18, 1, '{"motor_frame":"90","position":"rear","bearing_type":"6205"}', 'shield_rear90.jpg', 55.00, 'BYN', TRUE),
('SHIELD-FRONT-100', 'Щит подшипниковый передний 100', 18, 1, '{"motor_frame":"100","position":"front","bearing_type":"6206"}', 'shield_front100.jpg', 68.00, 'BYN', TRUE),
('SHIELD-REAR-100', 'Щит подшипниковый задний 100', 18, 1, '{"motor_frame":"100","position":"rear","bearing_type":"6206"}', 'shield_rear100.jpg', 68.00, 'BYN', TRUE),
('SHIELD-FRONT-112', 'Щит подшипниковый передний 112', 18, 1, '{"motor_frame":"112","position":"front","bearing_type":"6207"}', 'shield_front112.jpg', 82.00, 'BYN', TRUE),
('SHIELD-REAR-112', 'Щит подшипниковый задний 112', 18, 1, '{"motor_frame":"112","position":"rear","bearing_type":"6207"}', 'shield_rear112.jpg', 82.00, 'BYN', TRUE),
('SHIELD-FRONT-132', 'Щит подшипниковый передний 132', 18, 1, '{"motor_frame":"132","position":"front","bearing_type":"6208"}', 'shield_front132.jpg', 105.00, 'BYN', TRUE),
('SHIELD-REAR-132', 'Щит подшипниковый задний 132', 18, 1, '{"motor_frame":"132","position":"rear","bearing_type":"6209"}', 'shield_rear132.jpg', 105.00, 'BYN', TRUE),
('SHIELD-FRONT-160', 'Щит подшипниковый передний 160', 18, 1, '{"motor_frame":"160","position":"front","bearing_type":"6211"}', 'shield_front160.jpg', 145.00, 'BYN', TRUE),
('SHIELD-REAR-160', 'Щит подшипниковый задний 160', 18, 1, '{"motor_frame":"160","position":"rear","bearing_type":"6211"}', 'shield_rear160.jpg', 145.00, 'BYN', TRUE),
-- Запчасти - клеммные коробки
('BOX-80', 'Коробка клеммная для 80', 19, 1, '{"motor_frame":"80","terminals":6,"max_current_a":20}', 'box_80.jpg', 25.00, 'BYN', TRUE),
('BOX-90', 'Коробка клеммная для 90', 19, 1, '{"motor_frame":"90","terminals":6,"max_current_a":32}', 'box_90.jpg', 32.00, 'BYN', TRUE),
('BOX-100', 'Коробка клеммная для 100', 19, 1, '{"motor_frame":"100","terminals":6,"max_current_a":50}', 'box_100.jpg', 42.00, 'BYN', TRUE),
('BOX-112', 'Коробка клеммная для 112', 19, 1, '{"motor_frame":"112","terminals":6,"max_current_a":63}', 'box_112.jpg', 55.00, 'BYN', TRUE),
('BOX-132', 'Коробка клеммная для 132', 19, 1, '{"motor_frame":"132","terminals":6,"max_current_a":100}', 'box_132.jpg', 72.00, 'BYN', TRUE),
('BOX-160', 'Коробка клеммная для 160', 19, 1, '{"motor_frame":"160","terminals":6,"max_current_a":160}', 'box_160.jpg', 95.00, 'BYN', TRUE);

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

