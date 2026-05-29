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

-- 20. ПАСПОРТА ПРОДУКЦИИ (спецификации и производственные требования)
CREATE TABLE `product_passports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL UNIQUE,
  `total_weight_kg` DECIMAL(10,3) DEFAULT 0,
  `warranty_months` INT DEFAULT 12,
  `is_serial_tracked` BOOLEAN DEFAULT FALSE,
  `production_notes` TEXT,
  `quality_requirements` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pp_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. МАТЕРИАЛЫ В ПАСПОРТЕ ПРОДУКЦИИ
CREATE TABLE `product_passport_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `passport_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `quantity` DECIMAL(10,3) DEFAULT 0,
  `unit_id` INT,
  `weight_kg` DECIMAL(10,3) DEFAULT 0,
  `percentage` DECIMAL(5,2) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `notes` TEXT,
  CONSTRAINT `fk_ppm_passport` FOREIGN KEY (`passport_id`) REFERENCES `product_passports`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppm_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppm_unit` FOREIGN KEY (`unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL
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

INSERT INTO `product_categories` (`id`, `parent_id`, `name`, `name_ru`, `code`, `description`) VALUES
(1, NULL, 'Электродвигатели', 'Электродвигатели', 'MOTOR', 'Асинхронные электродвигатели'),
(2, 1, 'Общепромышленные двигатели', 'Общепромышленные двигатели', 'MOTOR_GENERAL', 'Двигатели общего назначения (серия АИР)'),
(3, 1, 'Энергоэффективные двигатели', 'Энергоэффективные двигатели', 'MOTOR_EFFICIENT', 'Энергоэффективные двигатели (серия 2AIR, класс IE2)'),
(4, 1, 'Многоскоростные двигатели', 'Многоскоростные двигатели', 'MOTOR_MULTI', 'Многоскоростные электродвигатели'),
(5, 1, 'Специальные двигатели', 'Специальные двигатели', 'MOTOR_SPECIAL', 'Двигатели специального назначения'),
(6, 5, 'С повышенным скольжением', 'Двигатели с повышенным скольжением', 'MOTOR_SLIP', 'Серия АИРС для приводов с переменными нагрузками'),
(7, 5, 'Для насосов', 'Двигатели для насосов', 'MOTOR_PUMP', 'Двигатели для привода моноблочных насосов (индекс Ж)'),
(8, 5, 'Для редукторов', 'Двигатели для редукторов', 'MOTOR_GEARBOX', 'Двигатели для привода редукторов (индекс РЗ)'),
(9, 5, 'Для вентиляторов', 'Двигатели для вентиляторов', 'MOTOR_FAN', 'Двигатели для осевых вентиляторов в животноводческих помещениях'),
(10, 5, 'Для стрелочных приводов', 'Двигатели для железной дороги', 'MOTOR_RAILWAY', 'Серия АИРЧ для стрелочных электроприводов'),
(11, 5, 'Взрывозащищенные', 'Взрывозащищенные двигатели', 'MOTOR_EX', 'Серия АИВР для взрывоопасных сред'),
(12, 1, 'Однофазные двигатели', 'Однофазные двигатели', 'MOTOR_SINGLE', 'Однофазные двигатели 220В (серия АИРЕ)'),
(13, NULL, 'Электронасосы', 'Электронасосы', 'PUMP', 'Насосное оборудование'),
(14, 13, 'Бытовые центробежные', 'Бытовые центробежные насосы', 'PUMP_CENTRIFUGAL', 'Насосы БЦ для воды'),
(15, 13, 'Погружные для грязных вод', 'Погружные насосы для загрязнённых вод', 'PUMP_SUBMERSIBLE', 'Насосы типа ГНОМ'),
(16, NULL, 'Прочая продукция', 'Прочая продукция', 'OTHER', 'Прочая продукция предприятия'),
(17, 16, 'Электроконфорки', 'Электроконфорки чугунные', 'HOTPLATE', 'Бытовые электроконфорки'),
(18, 16, 'Чугунное литьё', 'Отливки из чугуна', 'CAST_IRON', 'Серый и высокопрочный чугун'),
(19, 16, 'Цветное литьё', 'Отливки из цветных металлов', 'CAST_NONFERROUS', 'Алюминиевые сплавы');

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
('LUBE-OIL-TRANS', 'Масло трансформаторное ТКп', 'Масло ТКп', 5, 4, '{"тип":"трансформаторное","температура_застывания_ц":"-45","гост":"10121-75"}', 280.00, 55.00, 'Склад №6, Секция Б', 1, 5.50, 'BYN'),
-- Материалы для паспортов продукции
('STEEL-SHEET-0.5', 'Сталь электротехническая листовая 0.5мм', 'Сталь 0.5мм', 3, 2, '{"толщина_мм":0.5,"марка":"2211","гост":"11036-75"}', 500.00, 100.00, 'Склад №2, Секция А', 1, 45.00, 'BYN'),
('STEEL-SHEET-1.0', 'Сталь электротехническая листовая 1.0мм', 'Сталь 1.0мм', 3, 2, '{"толщина_мм":1.0,"марка":"2211","гост":"11036-75"}', 450.00, 90.00, 'Склад №2, Секция А', 1, 42.00, 'BYN'),
('STEEL-SHEET-0.8', 'Сталь листовая 0.8мм', 'Сталь 0.8мм', 3, 2, '{"толщина_мм":0.8,"марка":"Ст3сп","гост":"19903-90"}', 480.00, 95.00, 'Склад №2, Секция А', 1, 38.00, 'BYN'),
('WIRE-CU-0.6', 'Провод обмоточный медный 0.6мм', 'Провод 0.6', 6, 3, '{"диаметр_мм":0.6,"материал":"медь","изоляция":"ПЭТВ-2","класс":"F"}', 120.00, 25.00, 'Склад №4, Секция Е', 2, 75.00, 'BYN'),
('WIRE-CU-0.8', 'Провод обмоточный медный 0.8мм', 'Провод 0.8', 6, 3, '{"диаметр_мм":0.8,"материал":"медь","изоляция":"ПЭТВ-2","класс":"F"}', 95.00, 20.00, 'Склад №4, Секция Е', 2, 75.00, 'BYN'),
('WIRE-CU-1.0', 'Провод обмоточный медный 1.0мм', 'Провод 1.0', 6, 3, '{"диаметр_мм":1.0,"материал":"медь","изоляция":"ПЭТВ-2","класс":"F"}', 75.00, 15.00, 'Склад №4, Секция Е', 2, 75.00, 'BYN'),
('WIRE-NICHROME-2.0', 'Провод нихромовый 2.0мм', 'Нихром 2.0', 6, 3, '{"диаметр_мм":2.0,"материал":"нихром Х20Н80","сопротивление_ом_м":0.65}', 45.00, 10.00, 'Склад №4, Секция Ж', 2, 185.00, 'BYN'),
('ALUM-BAR-10', 'Алюминий литейный АД1', 'Алюминий АД1', 4, 2, '{"марка":"АД1","гост":"1583-93"}', 350.00, 70.00, 'Склад №3, Секция А', 1, 12.50, 'BYN'),
('BEARING-6201-2RS', 'Подшипник 6201-2RS', 'Подшипник 6201', 11, 1, '{"внутренний_диаметр_мм":12,"внешний_диаметр_мм":32,"ширина_мм":10,"тип":"шариковый","уплотнение":"2RS"}', 250.00, 50.00, 'Склад №5, Ящик 4', 2, 8.50, 'BYN'),
('BEARING-6203-2RS', 'Подшипник 6203-2RS', 'Подшипник 6203', 11, 1, '{"внутренний_диаметр_мм":17,"внешний_диаметр_мм":40,"ширина_мм":12,"тип":"шариковый","уплотнение":"2RS"}', 200.00, 40.00, 'Склад №5, Ящик 4', 2, 9.50, 'BYN'),
('BEARING-6204-2RS', 'Подшипник 6204-2RS', 'Подшипник 6204', 11, 1, '{"внутренний_диаметр_мм":20,"внешний_диаметр_мм":47,"ширина_мм":14,"тип":"шариковый","уплотнение":"2RS"}', 180.00, 35.00, 'Склад №5, Ящик 4', 2, 11.00, 'BYN'),
('SEAL-MECH-20', 'Уплотнение торцевое механическое 20мм', 'Уплотнение 20', 12, 1, '{"внутренний_диаметр_мм":20,"материал":"керамика-графит","давление_бар":10}', 85.00, 15.00, 'Склад №5, Ящик 5', 2, 125.00, 'BYN'),
('INSULATION-PERIKLAZ', 'Наполнитель периклазовый ПЭТ', 'Периклаз', 5, 2, '{"тип":"периклазовый","фракция_мм":"0.1-0.5","чистота_проц":98}', 280.00, 55.00, 'Склад №4, Секция Г', 2, 18.00, 'BYN'),
('CABLE-POWER-3x1.5', 'Кабель силовой 3х1.5мм²', 'Кабель 3х1.5', 6, 3, '{"сечение_мм2":1.5,"жилы":3,"материал":"медь","изоляция":"ПВХ","напряжение_в":450,"гост":"31996-2012"}', 850.00, 150.00, 'Склад №4, Секция Б', 2, 45.00, 'BYN');

-- ============================================
-- ПРОДУКЦИЯ ОАО «ПОЛЕСЬЕЭЛЕКТРОМАШ» (АИР)
-- Обновленный список из product_info.php
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('AIR71А2', 'Двигатель АИР71А2', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 3000, "кпд_проц": 77.0, "косинус_фи": 0.8, "пусковой_ток_к_номинальному": 6.0, "пусковой_момент_к_номинальному": 2.6, "макс_момент_к_номинальному": 2.7, "мин_момент_к_номинальному": 1.6, "масса_кг": 10.2, "габарит": 71, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air71а2.jpg', 282.10, 'BYN', TRUE),
('AIR71В2', 'Двигатель АИР71В2', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 3000, "кпд_проц": 78.0, "косинус_фи": 0.8, "пусковой_ток_к_номинальному": 6.0, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.6, "масса_кг": 10.5, "габарит": 71, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air71в2.jpg', 283.08, 'BYN', TRUE),
('AIR71А4', 'Двигатель АИР71А4', 2, 1, '{"мощность_квт": 0.55, "частота_вращения_об_мин": 1500, "кпд_проц": 71.0, "косинус_фи": 0.71, "пусковой_ток_к_номинальному": 5.0, "пусковой_момент_к_номинальному": 2.3, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.8, "масса_кг": 9.7, "габарит": 71, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air71а4.jpg', 276.51, 'BYN', TRUE),
('AIR71В4', 'Двигатель АИР71В4', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 1500, "кпд_проц": 74.0, "косинус_фи": 0.78, "пусковой_ток_к_номинальному": 5.0, "пусковой_момент_к_номинальному": 2.5, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 2.4, "масса_кг": 10.2, "габарит": 71, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air71в4.jpg', 277.06, 'BYN', TRUE),
('AIR71А6', 'Двигатель АИР71А6', 2, 1, '{"мощность_квт": 0.37, "частота_вращения_об_мин": 1000, "кпд_проц": 66.0, "косинус_фи": 0.63, "пусковой_ток_к_номинальному": 4.5, "пусковой_момент_к_номинальному": 2.1, "макс_момент_к_номинальному": 2.3, "мин_момент_к_номинальному": 1.6, "масса_кг": 9.2, "габарит": 71, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air71а6.jpg', 291.07, 'BYN', TRUE),
('AIR71В6', 'Двигатель АИР71В6', 2, 1, '{"мощность_квт": 0.55, "частота_вращения_об_мин": 1000, "кпд_проц": 69.0, "косинус_фи": 0.68, "пусковой_ток_к_номинальному": 4.5, "пусковой_момент_к_номинальному": 1.9, "макс_момент_к_номинальному": 2.2, "мин_момент_к_номинальному": 1.6, "масса_кг": 10.8, "габарит": 71, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air71в6.jpg', 291.60, 'BYN', TRUE),
('AIR80А2', 'Двигатель АИР80А2', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 3000, "кпд_проц": 82.0, "косинус_фи": 0.85, "пусковой_ток_к_номинальному": 6.5, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 1.8, "масса_кг": 13.3, "габарит": 80, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80а2.jpg', 385.70, 'BYN', TRUE),
('AIR80В2', 'Двигатель АИР80В2', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 3000, "кпд_проц": 83.0, "косинус_фи": 0.87, "пусковой_ток_к_номинальному": 6.4, "пусковой_момент_к_номинальному": 2.1, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 1.8, "масса_кг": 15.9, "габарит": 80, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80в2.jpg', 388.36, 'BYN', TRUE),
('AIR80А4', 'Двигатель АИР80А4', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 1500, "кпд_проц": 76.5, "косинус_фи": 0.77, "пусковой_ток_к_номинальному": 5.0, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.7, "масса_кг": 12.8, "габарит": 80, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80а4.jpg', 353.85, 'BYN', TRUE),
('AIR80В4', 'Двигатель АИР80В4', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 1500, "кпд_проц": 78.5, "косинус_фи": 0.8, "пусковой_ток_к_номинальному": 5.3, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.7, "масса_кг": 14.7, "габарит": 80, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80в4.jpg', 355.25, 'BYN', TRUE),
('AIR80А6', 'Двигатель АИР80А6', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 1000, "кпд_проц": 71.0, "косинус_фи": 0.71, "пусковой_ток_к_номинальному": 4.0, "пусковой_момент_к_номинальному": 2.1, "макс_момент_к_номинальному": 2.2, "мин_момент_к_номинальному": 1.6, "масса_кг": 12.5, "габарит": 80, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80а6.jpg', 392.93, 'BYN', TRUE),
('AIR80В6', 'Двигатель АИР80В6', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 1000, "кпд_проц": 75.0, "косинус_фи": 0.74, "пусковой_ток_к_номинальному": 4.5, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.3, "мин_момент_к_номинальному": 1.8, "масса_кг": 16.2, "габарит": 80, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80в6.jpg', 394.29, 'BYN', TRUE),
('AIR90L2', 'Двигатель АИР90L2', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 3000, "кпд_проц": 84.6, "косинус_фи": 0.88, "пусковой_ток_к_номинальному": 7.0, "пусковой_момент_к_номинальному": 2.3, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 1.7, "масса_кг": 20.6, "габарит": 90, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l2.jpg', 566.50, 'BYN', TRUE),
('AIR90LB2', 'Двигатель АИР90LB2', 2, 1, '{"мощность_квт": 4.0, "частота_вращения_об_мин": 3000, "кпд_проц": 86.5, "косинус_фи": 0.86, "пусковой_ток_к_номинальному": 7.5, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.6, "масса_кг": 23.4, "габарит": 90, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90lb2.jpg', 572.00, 'BYN', TRUE),
('AIR90L4', 'Двигатель АИР90L4', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 1500, "кпд_проц": 81.0, "косинус_фи": 0.83, "пусковой_ток_к_номинальному": 6.0, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 2.0, "масса_кг": 19.7, "габарит": 90, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l4.jpg', 531.44, 'BYN', TRUE),
('AIR90LB4', 'Двигатель АИР90LB4', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 1500, "кпд_проц": 81.0, "косинус_фи": 0.81, "пусковой_ток_к_номинальному": 6.5, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.7, "масса_кг": 24.1, "габарит": 90, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90lb4.jpg', 535.60, 'BYN', TRUE),
('AIR90L6', 'Двигатель АИР90L6', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 1000, "кпд_проц": 76.0, "косинус_фи": 0.72, "пусковой_ток_к_номинальному": 5.0, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.3, "мин_момент_к_номинальному": 1.9, "масса_кг": 20.6, "габарит": 90, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l6.jpg', 588.70, 'BYN', TRUE),
('AIR90LA8', 'Двигатель АИР90LA8', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 750, "кпд_проц": 72.5, "косинус_фи": 0.71, "пусковой_ток_к_номинальному": 4.0, "пусковой_момент_к_номинальному": 1.5, "макс_момент_к_номинальному": 2.0, "мин_момент_к_номинальному": 1.5, "масса_кг": 19.5, "габарит": 90, "число_полюсов": 8, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90la8.jpg', 654.88, 'BYN', TRUE),
('AIR90LB8', 'Двигатель АИР90LB8', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 750, "кпд_проц": 76.0, "косинус_фи": 0.72, "пусковой_ток_к_номинальному": 4.5, "пусковой_момент_к_номинальному": 1.5, "макс_момент_к_номинальному": 2.2, "мин_момент_к_номинальному": 1.5, "масса_кг": 22.3, "габарит": 90, "число_полюсов": 8, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90lb8.jpg', 657.15, 'BYN', TRUE),
('AIR100S2', 'Двигатель АИР100S2', 2, 1, '{"мощность_квт": 4.0, "частота_вращения_об_мин": 3000, "кпд_проц": 86.5, "косинус_фи": 0.86, "пусковой_ток_к_номинальному": 7.5, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.6, "масса_кг": 23.6, "габарит": 100, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air100s2.jpg', 707.20, 'BYN', TRUE),
('AIR100S4', 'Двигатель АИР100S4', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 1500, "кпд_проц": 81.0, "косинус_фи": 0.81, "пусковой_ток_к_номинальному": 6.5, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.7, "масса_кг": 25.8, "габарит": 100, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air100s4.jpg', 669.50, 'BYN', TRUE),
('AIR100L4', 'Двигатель АИР100L4', 2, 1, '{"мощность_квт": 4.0, "частота_вращения_об_мин": 1500, "кпд_проц": 81.0, "косинус_фи": 0.81, "пусковой_ток_к_номинальному": 6.5, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.4, "мин_момент_к_номинальному": 1.7, "масса_кг": 25.8, "габарит": 100, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air100l4.jpg', 676.00, 'BYN', TRUE),
('AIR100L6', 'Двигатель АИР100L6', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 1000, "кпд_проц": 76.0, "косинус_фи": 0.72, "пусковой_ток_к_номинальному": 5.0, "пусковой_момент_к_номинальному": 2.0, "макс_момент_к_номинальному": 2.3, "мин_момент_к_номинальному": 1.9, "масса_кг": 20.8, "габарит": 100, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air100l6.jpg', 838.04, 'BYN', TRUE),
('AIR100L2', 'Двигатель АИР100L2', 2, 1, '{"мощность_квт": 5.5, "частота_вращения_об_мин": 3000, "кпд_проц": 87.0, "косинус_фи": 0.94, "пусковой_ток_к_номинальному": 8.4, "пусковой_момент_к_номинальному": 2.4, "макс_момент_к_номинальному": 3.2, "мин_момент_к_номинальному": 1.65, "масса_кг": 28.0, "габарит": 100, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air100l2.jpg', 717.40, 'BYN', TRUE),
('AIR112M2', 'Двигатель АИР112M2', 2, 1, '{"мощность_квт": 7.5, "частота_вращения_об_мин": 3000, "кпд_проц": 87.5, "косинус_фи": 0.89, "пусковой_ток_к_номинальному": 7.5, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.8, "мин_момент_к_номинальному": 1.8, "масса_кг": 35.0, "габарит": 112, "число_полюсов": 2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air112m2.jpg', 989.00, 'BYN', TRUE),
('AIR112M4', 'Двигатель АИР112M4', 2, 1, '{"мощность_квт": 5.5, "частота_вращения_об_мин": 1500, "кпд_проц": 85.0, "косинус_фи": 0.84, "пусковой_ток_к_номинальному": 7.0, "пусковой_момент_к_номинальному": 2.3, "макс_момент_к_номинальному": 2.7, "мин_момент_к_номинальному": 2.0, "масса_кг": 33.0, "габарит": 112, "число_полюсов": 4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air112m4.jpg', 928.40, 'BYN', TRUE),
('AIR112MA6', 'Двигатель АИР112MA6', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 1000, "кпд_проц": 80.0, "косинус_фи": 0.75, "пусковой_ток_к_номинальному": 6.0, "пусковой_момент_к_номинальному": 2.1, "макс_момент_к_номинальному": 2.5, "мин_момент_к_номинальному": 1.9, "масса_кг": 31.0, "габарит": 112, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air112ma6.jpg', 1050.60, 'BYN', TRUE),
('AIR112MB6', 'Двигатель АИР112MB6', 2, 1, '{"мощность_квт": 4.0, "частота_вращения_об_мин": 1000, "кпд_проц": 81.5, "косинус_фи": 0.77, "пусковой_ток_к_номинальному": 6.5, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 2.0, "масса_кг": 33.5, "габарит": 112, "число_полюсов": 6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air112mb6.jpg', 1060.80, 'BYN', TRUE),
('AIR112MA8', 'Двигатель АИР112MA8', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 750, "кпд_проц": 76.0, "косинус_фи": 0.7, "пусковой_ток_к_номинальному": 5.0, "пусковой_момент_к_номинальному": 1.8, "макс_момент_к_номинальному": 2.2, "мин_момент_к_номинальному": 1.6, "масса_кг": 32.0, "габарит": 112, "число_полюсов": 8, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air112ma8.jpg', 932.80, 'BYN', TRUE),
('AIR112MB8', 'Двигатель АИР112MB8', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 750, "кпд_проц": 78.0, "косинус_фи": 0.72, "пусковой_ток_к_номинальному": 5.5, "пусковой_момент_к_номинальному": 1.9, "макс_момент_к_номинальному": 2.3, "мин_момент_к_номинальному": 1.7, "масса_кг": 34.0, "габарит": 112, "число_полюсов": 8, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air112mb8.jpg', 1272.00, 'BYN', TRUE),
('2AIR80A2', 'Двигатель 2AIR80A2', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 3000, "кпд_проц": 81.3, "косинус_фи": 0.89, "пусковой_ток_к_номинальному": 6.5, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 1.8, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air80a2.jpg', 284.20, 'BYN', TRUE),
('2AIR80B2', 'Двигатель 2AIR80B2', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 3000, "кпд_проц": 83.2, "косинус_фи": 0.92, "пусковой_ток_к_номинальному": 6.4, "пусковой_момент_к_номинальному": 2.1, "макс_момент_к_номинальному": 2.6, "мин_момент_к_номинальному": 1.8, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air80b2.jpg', 286.16, 'BYN', TRUE),
('2AIR80A6', 'Двигатель 2AIR80A6', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 1000, "кпд_проц": 75.9, "косинус_фи": 0.67, "пусковой_ток_к_номинальному": 4.0, "пусковой_момент_к_номинальному": 2.1, "макс_момент_к_номинальному": 2.2, "мин_момент_к_номинальному": 1.6, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air80a6.jpg', 292.18, 'BYN', TRUE),
('2AIR80B6', 'Двигатель 2AIR80B6', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 1000, "кпд_проц": 78.1, "косинус_фи": 0.69, "пусковой_ток_к_номинальному": 4.5, "пусковой_момент_к_номинальному": 2.2, "макс_момент_к_номинальному": 2.3, "мин_момент_к_номинальному": 1.8, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air80b6.jpg', 293.19, 'BYN', TRUE),
('2AIR90L2', 'Двигатель 2AIR90L2', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 3000, "кпд_проц": 85.6, "косинус_фи": 0.94, "пусковой_ток_к_номинальному": 7.0, "пусковой_момент_к_номинальному": 2.3, "макс_момент_к_номинальному": 2.7, "мин_момент_к_номинальному": 2.0, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air90l2.jpg', 288.40, 'BYN', TRUE),
('2AIR90L4', 'Двигатель 2AIR90L4', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 1500, "кпд_проц": 84.5, "косинус_фи": 0.87, "пусковой_ток_к_номинальному": 7.2, "пусковой_момент_к_номинальному": 2.8, "макс_момент_к_номинальному": 2.7, "мин_момент_к_номинальному": 2.2, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air90l4.jpg', 281.05, 'BYN', TRUE),
('2AIR90L6', 'Двигатель 2AIR90L6', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 1000, "кпд_проц": 80.3, "косинус_фи": 0.76, "пусковой_ток_к_номинальному": 6.0, "пусковой_момент_к_номинальному": 2.6, "макс_момент_к_номинальному": 3.0, "мин_момент_к_номинальному": 2.4, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air90l6.jpg', 294.35, 'BYN', TRUE),
('2AIR100S2', 'Двигатель 2AIR100S2', 2, 1, '{"мощность_квт": 4.0, "частота_вращения_об_мин": 3000, "кпд_проц": 85.8, "косинус_фи": 0.93, "пусковой_ток_к_номинальному": 8.3, "пусковой_момент_к_номинальному": 2.5, "макс_момент_к_номинальному": 3.5, "мин_момент_к_номинальному": 2.0, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air100s2.jpg', 291.20, 'BYN', TRUE),
('2AIR100L2', 'Двигатель 2AIR100L2', 2, 1, '{"мощность_квт": 5.5, "частота_вращения_об_мин": 3000, "кпд_проц": 87.0, "косинус_фи": 0.94, "пусковой_ток_к_номинальному": 8.4, "пусковой_момент_к_номинальному": 2.4, "макс_момент_к_номинальному": 3.2, "мин_момент_к_номинальному": 1.65, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air100l2.jpg', 295.40, 'BYN', TRUE),
('2AIR100S4', 'Двигатель 2AIR100S4', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 1500, "кпд_проц": 85.7, "косинус_фи": 0.78, "пусковой_ток_к_номинальному": 7.0, "пусковой_момент_к_номинальному": 2.5, "макс_момент_к_номинальному": 3.0, "мин_момент_к_номинальному": 2.0, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air100s4.jpg', 283.25, 'BYN', TRUE),
('2AIR100L4', 'Двигатель 2AIR100L4', 2, 1, '{"мощность_квт": 4.0, "частота_вращения_об_мин": 1500, "кпд_проц": 86.9, "косинус_фи": 0.79, "пусковой_ток_к_номинальному": 7.5, "пусковой_момент_к_номинальному": 2.5, "макс_момент_к_номинальному": 3.0, "мин_момент_к_номинальному": 2.0, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air100l4.jpg', 286.00, 'BYN', TRUE),
('2AIR100L6', 'Двигатель 2AIR100L6', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 1000, "кпд_проц": 82.2, "косинус_фи": 0.8, "пусковой_ток_к_номинальному": 6.3, "пусковой_момент_к_номинальному": 2.7, "макс_момент_к_номинальному": 3.1, "мин_момент_к_номинальному": 2.0, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_2air100l6.jpg', 296.38, 'BYN', TRUE),
('AIR80А4/2', 'Двигатель АИР80А4/2', 2, 1, '{"масса_кг": 13.1, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80а4/2.jpg', 277.75, 'BYN', TRUE),
('AIR90L4/2', 'Двигатель АИР90L4/2', 2, 1, '{"напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l4/2.jpg', 277.75, 'BYN', TRUE),
('AIR90L6/4', 'Двигатель АИР90L6/4', 2, 1, '{"напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l6/4.jpg', 277.75, 'BYN', TRUE),
('AIR90L8/4', 'Двигатель АИР90L8/4', 2, 1, '{"напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l8/4.jpg', 277.75, 'BYN', TRUE),
('AIR100S8/4', 'Двигатель АИР100S8/4', 2, 1, '{"напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air100s8/4.jpg', 277.75, 'BYN', TRUE),
('AIRС80А2', 'Двигатель АИРС80А2', 2, 1, '{"мощность_квт": 1.9, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс80а2.jpg', 285.32, 'BYN', TRUE),
('AIRС80В2', 'Двигатель АИРС80В2', 2, 1, '{"мощность_квт": 2.5, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс80в2.jpg', 287.00, 'BYN', TRUE),
('AIRС80А4', 'Двигатель АИРС80А4', 2, 1, '{"мощность_квт": 1.32, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс80а4.jpg', 278.63, 'BYN', TRUE),
('AIRС80В4', 'Двигатель АИРС80В4', 2, 1, '{"мощность_квт": 1.7, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс80в4.jpg', 279.67, 'BYN', TRUE),
('AIRС90L2', 'Двигатель АИРС90L2', 2, 1, '{"мощность_квт": 3.5, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс90l2.jpg', 289.80, 'BYN', TRUE),
('AIRС90LB2', 'Двигатель АИРС90LB2', 2, 1, '{"мощность_квт": 4.8, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс90lb2.jpg', 293.44, 'BYN', TRUE),
('AIRС90L4', 'Двигатель АИРС90L4', 2, 1, '{"мощность_квт": 2.4, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс90l4.jpg', 281.60, 'BYN', TRUE),
('AIRС90LB4', 'Двигатель АИРС90LB4', 2, 1, '{"мощность_квт": 3.2, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс90lb4.jpg', 283.80, 'BYN', TRUE),
('AIRС100S2', 'Двигатель АИРС100S2', 2, 1, '{"мощность_квт": 4.8, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс100s2.jpg', 293.44, 'BYN', TRUE),
('AIRС100S4', 'Двигатель АИРС100S4', 2, 1, '{"мощность_квт": 3.2, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airс100s4.jpg', 283.80, 'BYN', TRUE),
('AIR80А2Ж', 'Двигатель АИР80А2Ж', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80а2ж.jpg', 284.20, 'BYN', TRUE),
('AIR80В2Ж', 'Двигатель АИР80В2Ж', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80в2ж.jpg', 286.16, 'BYN', TRUE),
('AIR80В4Ж', 'Двигатель АИР80В4Ж', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air80в4ж.jpg', 279.12, 'BYN', TRUE),
('AIR90L2Ж', 'Двигатель АИР90L2Ж', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l2ж.jpg', 288.40, 'BYN', TRUE),
('AIR90L4Ж', 'Двигатель АИР90L4Ж', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l4ж.jpg', 281.05, 'BYN', TRUE),
('AIR100S2Ж', 'Двигатель АИР100S2Ж', 2, 1, '{"мощность_квт": 4.0, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air100s2ж.jpg', 291.20, 'BYN', TRUE),
('AIR90L2РЗ', 'Двигатель АИР90L2РЗ', 2, 1, '{"мощность_квт": 3.0, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l2рз.jpg', 288.40, 'BYN', TRUE),
('AIR90L4РЗ', 'Двигатель АИР90L4РЗ', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l4рз.jpg', 281.05, 'BYN', TRUE),
('AIR90L6РЗ', 'Двигатель АИР90L6РЗ', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 1000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90l6рз.jpg', 294.35, 'BYN', TRUE),
('AIR90LA8РЗ', 'Двигатель АИР90LA8РЗ', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 750, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90la8рз.jpg', 256.50, 'BYN', TRUE),
('AIR90LB8РЗ', 'Двигатель АИР90LB8РЗ', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 750, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_air90lb8рз.jpg', 376.20, 'BYN', TRUE),
('AIRП80А6', 'Двигатель АИРП80А6', 2, 1, '{"мощность_квт": 0.37, "частота_вращения_об_мин": 1000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airп80а6.jpg', 291.07, 'BYN', TRUE),
('AIRП80С6', 'Двигатель АИРП80С6', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 1000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airп80с6.jpg', 292.18, 'BYN', TRUE),
('AIRЧ80В4', 'Двигатель АИРЧ80В4', 2, 1, '{"мощность_квт": 0.55, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airч80в4.jpg', 276.51, 'BYN', TRUE),
('AIRЧ80В6', 'Двигатель АИРЧ80В6', 2, 1, '{"мощность_квт": 0.3, "частота_вращения_об_мин": 1000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airч80в6.jpg', 290.87, 'BYN', TRUE),
('АИВР80', 'Двигатель АИВР80', 2, 1, '{"напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_аивр80.jpg', 277.75, 'BYN', TRUE),
('АИВР90L', 'Двигатель АИВР90L', 2, 1, '{"напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_аивр90l.jpg', 277.75, 'BYN', TRUE),
('AIRЕ71А2', 'Двигатель АИРЕ71А2', 2, 1, '{"мощность_квт": 0.55, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе71а2.jpg', 281.54, 'BYN', TRUE),
('AIRЕ71В2', 'Двигатель АИРЕ71В2', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе71в2.jpg', 282.10, 'BYN', TRUE),
('AIRЕ71С2', 'Двигатель АИРЕ71С2', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе71с2.jpg', 283.08, 'BYN', TRUE),
('AIRЕ71А4', 'Двигатель АИРЕ71А4', 2, 1, '{"мощность_квт": 0.37, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе71а4.jpg', 276.02, 'BYN', TRUE),
('AIRЕ71В4', 'Двигатель АИРЕ71В4', 2, 1, '{"мощность_квт": 0.55, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе71в4.jpg', 276.51, 'BYN', TRUE),
('AIRЕ71С4', 'Двигатель АИРЕ71С4', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе71с4.jpg', 277.06, 'BYN', TRUE),
('AIRЕ80А2', 'Двигатель АИРЕ80А2', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе80а2.jpg', 283.08, 'BYN', TRUE),
('AIRЕ80В2', 'Двигатель АИРЕ80В2', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе80в2.jpg', 284.20, 'BYN', TRUE),
('AIRЕ80С2', 'Двигатель АИРЕ80С2', 2, 1, '{"мощность_квт": 1.9, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе80с2.jpg', 285.32, 'BYN', TRUE),
('AIRЕ80D2', 'Двигатель АИРЕ80D2', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе80d2.jpg', 286.16, 'BYN', TRUE),
('AIRЕ80А4', 'Двигатель АИРЕ80А4', 2, 1, '{"мощность_квт": 0.75, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе80а4.jpg', 277.06, 'BYN', TRUE),
('AIRЕ80В4', 'Двигатель АИРЕ80В4', 2, 1, '{"мощность_квт": 1.1, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе80в4.jpg', 278.02, 'BYN', TRUE),
('AIRЕ80С4', 'Двигатель АИРЕ80С4', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе80с4.jpg', 279.12, 'BYN', TRUE),
('AIRЕ90L2', 'Двигатель АИРЕ90L2', 2, 1, '{"мощность_квт": 2.2, "частота_вращения_об_мин": 3000, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе90l2.jpg', 286.16, 'BYN', TRUE),
('AIRЕ90L4', 'Двигатель АИРЕ90L4', 2, 1, '{"мощность_квт": 1.5, "частота_вращения_об_мин": 1500, "напряжение_в": 380, "класс_эффективности": "IE2", "монтаж": "IM1081", "степень_защиты": "IP54", "климатическое_исполнение": "У2", "класс_изоляции": "F"}', 'motor_airе90l4.jpg', 279.12, 'BYN', TRUE);

-- ============================================
-- ЭЛЕКТРОНАСОСЫ
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('BC-0.5-20-U1.1', 'Электронасос бытовой центробежный БЦ-0,5-20-У1.1', 14, 1, '{"потребляемая_мощность_вт": 500, "напряжение_в": 220, "производительность_л_мин": 30, "номинальный_напор_м": 20, "макс_высота_всасывания_м": 7, "температура_воды_град_ц": "1–45", "уплотнение": "торцовое керамическое"}', 'pump_bc05.jpg', 185.00, 'BYN', TRUE),
('GNOM-10-10', 'Электронасос погружной ГНОМ 10-10', 15, 1, '{"тип": "погружной для загрязнённых вод", "температура_среды_град_ц": "0–35", "водородный_показатель_рн": "5–10", "плотность_среды_кг_м3": "до 1100", "содержание_твёрдых_примесей_проц": "до 10", "макс_размер_частиц_мм": 5}', 'pump_gnom.jpg', 420.00, 'BYN', TRUE);

-- ============================================
-- ПРОЧАЯ ПРОДУКЦИЯ
-- ============================================
INSERT INTO `products` (`article`, `name`, `category_id`, `base_unit_id`, `specifications`, `image`, `base_price`, `currency`, `is_active`) VALUES
('EKCH-145', 'Электроконфорка чугунная ЭКЧ-145', 17, 1, '{"тип": "бытовая", "диаметр_мм": 145, "мощность_вт": 1000, "напряжение_в": 220}', 'hotplate_ekch145.jpg', 45.00, 'BYN', TRUE),
('EKCH-180', 'Электроконфорка чугунная ЭКЧ-180', 17, 1, '{"тип": "бытовая", "диаметр_мм": 180, "мощность_вт": 1500, "напряжение_в": 220}', 'hotplate_ekch180.jpg', 55.00, 'BYN', TRUE),
('EKCH220-2.0', 'Электроконфорка чугунная ЭКЧ220-2.0/220', 17, 1, '{"тип": "бытовая", "диаметр_мм": 220, "мощность_вт": 2000, "напряжение_в": 220}', 'hotplate_ekch220.jpg', 65.00, 'BYN', TRUE),
('EKCH1-1.0', 'Электроконфорка чугунная ЭКЧ1-1.0/220', 17, 1, '{"тип": "бытовая", "диаметр_мм": 145, "мощность_вт": 1000, "напряжение_в": 220}', 'hotplate_ekch1.jpg', 42.00, 'BYN', TRUE),
('CAST-SCH20', 'Отливка из серого чугуна СЧ20', 18, 2, '{"марка": "СЧ20", "твердость_hb": "170-220", "гост": "1412-85"}', 'cast_iron_sch20.jpg', 3.00, 'BYN', TRUE),
('CAST-SCH25', 'Отливка из серого чугуна СЧ25', 18, 2, '{"марка": "СЧ25", "твердость_hb": "190-240", "гост": "1412-85"}', 'cast_iron_sch25.jpg', 3.50, 'BYN', TRUE),
('CAST-VCH40', 'Отливка из высокопрочного чугуна ВЧ40', 18, 2, '{"марка": "ВЧ40", "твердость_hb": "200-250", "гост": "7293-85"}', 'cast_iron_vch40.jpg', 4.20, 'BYN', TRUE),
('CAST-AK5M2', 'Отливка из алюминиевого сплава АК5М2', 19, 2, '{"сплав": "АК5М2", "гост": "1583-93"}', 'cast_al_ak5m2.jpg', 8.50, 'BYN', TRUE),
('CAST-AK7', 'Отливка из алюминиевого сплава АК7', 19, 2, '{"сплав": "АК7", "гост": "1583-93"}', 'cast_al_ak7.jpg', 8.00, 'BYN', TRUE),
('CAST-AK9', 'Отливка из алюминиевого сплава АК9', 19, 2, '{"сплав": "АК9", "гост": "1583-93"}', 'cast_al_ak9.jpg', 8.20, 'BYN', TRUE),
('CAST-AK12', 'Отливка из алюминиевого сплава АК12', 19, 2, '{"сплав": "АК12", "гост": "1583-93"}', 'cast_al_ak12.jpg', 8.30, 'BYN', TRUE),
('CAST-A5', 'Отливка из алюминия А5', 19, 2, '{"сплав": "А5", "гост": "11069-2001"}', 'cast_al_a5.jpg', 9.00, 'BYN', TRUE),
('CAST-A6', 'Отливка из алюминия А6', 19, 2, '{"сплав": "А6", "гост": "11069-2001"}', 'cast_al_a6.jpg', 8.80, 'BYN', TRUE),
('CAST-AV87', 'Отливка из алюминиевого сплава АВ-87', 19, 2, '{"сплав": "АВ-87", "гост": "1583-93"}', 'cast_al_av87.jpg', 9.50, 'BYN', TRUE);

-- ============================================
-- ЭТАПЫ ПРОИЗВОДСТВА (справочник)
-- ============================================
INSERT INTO `production_stages` (`name`, `code`, `description`, `sort_order`, `color`, `is_active`) VALUES
('Заготовка', 'blank', 'Подготовка заготовок', 1, '#FF6B6B', TRUE),
('Литье', 'casting', 'Литейное производство', 2, '#4ECDC4', TRUE),
('Механообработка', 'machining', 'Токарные и фрезерные работы', 3, '#45B7D1', TRUE),
('Намотка', 'winding', 'Намотка обмоток', 4, '#96CEB4', TRUE),
('Сборка', 'assembly', 'Сборка узлов', 5, '#FFEAA7', TRUE),
('Покраска', 'painting', 'Нанесение лакокрасочных покрытий', 6, '#DDA0DD', TRUE),
('Сушка', 'drying', 'Сушка после покраски', 7, '#98D8C8', TRUE),
('Балансировка', 'balancing', 'Балансировка ротора', 8, '#F7DC6F', TRUE),
('Контроль качества', 'qc', 'Проверка качества', 9, '#BB8FCE', TRUE),
('Упаковка', 'packing', 'Упаковка готовой продукции', 10, '#82E0AA', TRUE);

-- ============================================
-- ПАСПОРТА ПРОДУКЦИИ И МАТЕРИАЛЫ
-- ============================================

-- Паспорт для двигателя АИР71А2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`) VALUES
((SELECT id FROM products WHERE article = 'AIR71А2'), 10.2, 12, TRUE, 
'Стандартный производственный процесс для двигателей серии АИР71', 
'Соответствие ГОСТ Р 51689-2000, проверка сопротивления изоляции, испытание на нагрев');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 2.5, 2, 9.8, 45.0, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 (SELECT id FROM materials WHERE code = 'WIRE-CU-0.8'), 0.15, 4, 1.2, 12.0, 2, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 2, 1, 0.34, 3.3, 3, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 1.8, 2, 4.86, 22.0, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.12, 2, 0.24, 2.3, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.02, 2, 0.036, 0.35, 6, 'Смазка для подшипников');

-- Маршрутная карта для АИР71А2
INSERT INTO `route_cards` (`product_id`, `name`, `version`, `description`, `total_time_hours`, `is_active`) VALUES
((SELECT id FROM products WHERE article = 'AIR71А2'), 'Маршрутная карта АИР71А2', '1.0', 
'Полный технологический процесс производства двигателя АИР71А2', 8.5, TRUE);

INSERT INTO `route_card_operations` (`route_card_id`, `operation_number`, `stage_id`, `name`, `description`, `work_center`, `equipment`, `time_norm_hours`, `materials_required`, `instructions`, `sort_order`) VALUES
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 10, (SELECT id FROM production_stages WHERE code = 'blank'), 
 'Раскрой стали', 'Раскрой электротехнической стали для пластин статора', 'Заготовительный участок', 'Гильотинные ножницы', 0.5, 
 '{"материалы": ["STEEL-SHEET-0.5"], "количество": 2.5}', 
'Соблюдать размеры согласно чертежу МЭ-71А2-001', 1),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 20, (SELECT id FROM production_stages WHERE code = 'machining'), 
 'Штамповка пластин', 'Вырубка пластин статора и ротора', 'Прессовый участок', 'Пресс механический 63т', 1.0, 
 '{"материалы": ["STEEL-SHEET-0.5"]}', 
'Контролировать качество вырубки, отсутствие заусенцев', 2),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 30, (SELECT id FROM production_stages WHERE code = 'casting'), 
 'Литье ротора', 'Литье алюминиевой клетки ротора под давлением', 'Литейный участок', 'Машина литьевая ДПА-250', 1.5, 
 '{"материалы": ["ALUM-BAR-10"]}', 
'Температура литья 680-700°C, давление 250 атм', 3),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 40, (SELECT id FROM production_stages WHERE code = 'winding'), 
 'Намотка статора', 'Намотка обмотки статора', 'Намоточный участок', 'Станок намоточный полуавтоматический', 2.0, 
 '{"материалы": ["WIRE-CU-0.8"]}', 
'Количество витков согласно техпроцессу, натяжение провода 2-3 Н', 4),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 50, (SELECT id FROM production_stages WHERE code = 'assembly'), 
 'Сборка двигателя', 'Установка ротора в статор, монтаж подшипников', 'Сборочный участок', 'Пресс гидравлический', 1.5, 
 '{"материалы": ["BEARING-6203-2RS", "LUBE-GREASE-LITO"]}', 
'Зазор между ротором и статором 0.3-0.5 мм', 5),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 60, (SELECT id FROM production_stages WHERE code = 'painting'), 
 'Покраска корпуса', 'Нанесение порошкового покрытия', 'Окрасочный участок', 'Камера напыления порошковая', 0.8, 
 '{"материалы": ["PAINT-POLYMER"]}', 
'Толщина покрытия 60-80 мкм', 6),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 70, (SELECT id FROM production_stages WHERE code = 'drying'), 
 'Полимеризация покрытия', 'Сушка в печи', 'Участок сушки', 'Печь конвейерная', 0.7, 
 '{}', 
'Температура 180-200°C, время 15 минут', 7),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 80, (SELECT id FROM production_stages WHERE code = 'balancing'), 
 'Балансировка ротора', 'Динамическая балансировка', 'Балансировочный участок', 'Станок балансировочный', 0.3, 
 '{}', 
'Дисбаланс не более 15 г·мм', 8),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 90, (SELECT id FROM production_stages WHERE code = 'qc'), 
 'Контроль качества', 'Проверка электрических параметров', 'Контрольный участок', 'Стенд испытательный', 0.5, 
 '{}', 
'Проверка сопротивления изоляции, тока холостого хода', 9),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR71А2')), 
 100, (SELECT id FROM production_stages WHERE code = 'packing'), 
 'Упаковка', 'Консервация и упаковка', 'Упаковочный участок', '', 0.2, 
 '{}', 
'Упаковка в картонную коробку с инструкцией', 10);

-- Паспорт для двигателя АИР80А2
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`) VALUES
((SELECT id FROM products WHERE article = 'AIR80А2'), 13.3, 12, TRUE, 
'Производственный процесс для двигателей серии АИР80', 
'Соответствие ГОСТ Р 51689-2000, расширенные испытания');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 3.2, 2, 12.5, 43.0, 1, 'Сталь для корпуса статора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 (SELECT id FROM materials WHERE code = 'WIRE-CU-1.0'), 0.22, 4, 1.8, 13.5, 2, 'Обмоточный провод увеличенного сечения'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 2, 1, 0.42, 3.2, 3, 'Подшипники большего размера'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 2.5, 2, 6.75, 25.0, 4, 'Алюминий для литья ротора'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 0.15, 2, 0.3, 2.2, 5, 'Порошковая краска'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 (SELECT id FROM materials WHERE code = 'LUBE-GREASE-LITO'), 0.025, 2, 0.045, 0.34, 6, 'Смазка для подшипников');

-- Маршрутная карта для АИР80А2 (аналогична АИР71А2 с небольшими отличиями)
INSERT INTO `route_cards` (`product_id`, `name`, `version`, `description`, `total_time_hours`, `is_active`) VALUES
((SELECT id FROM products WHERE article = 'AIR80А2'), 'Маршрутная карта АИР80А2', '1.0', 
'Технологический процесс производства двигателя АИР80А2', 9.0, TRUE);

INSERT INTO `route_card_operations` (`route_card_id`, `operation_number`, `stage_id`, `name`, `description`, `work_center`, `equipment`, `time_norm_hours`, `materials_required`, `instructions`, `sort_order`) VALUES
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 10, (SELECT id FROM production_stages WHERE code = 'blank'), 
 'Раскрой стали', 'Раскрой электротехнической стали для пластин статора', 'Заготовительный участок', 'Гильотинные ножницы', 0.6, 
 '{"материалы": ["STEEL-SHEET-0.5"], "количество": 3.2}', 
'Соблюдать размеры согласно чертежу МЭ-80А2-001', 1),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 20, (SELECT id FROM production_stages WHERE code = 'machining'), 
 'Штамповка пластин', 'Вырубка пластин статора и ротора', 'Прессовый участок', 'Пресс механический 100т', 1.2, 
 '{"материалы": ["STEEL-SHEET-0.5"]}', 
'Контролировать качество вырубки', 2),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 30, (SELECT id FROM production_stages WHERE code = 'casting'), 
 'Литье ротора', 'Литье алюминиевой клетки ротора', 'Литейный участок', 'Машина литьевая ДПА-400', 1.8, 
 '{"материалы": ["ALUM-BAR-10"]}', 
'Температура литья 680-700°C', 3),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 40, (SELECT id FROM production_stages WHERE code = 'winding'), 
 'Намотка статора', 'Намотка обмотки статора', 'Намоточный участок', 'Станок намоточный автоматический', 2.2, 
 '{"материалы": ["WIRE-CU-1.0"]}', 
'Натяжение провода 3-4 Н', 4),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 50, (SELECT id FROM production_stages WHERE code = 'assembly'), 
 'Сборка двигателя', 'Установка ротора в статор', 'Сборочный участок', 'Пресс гидравлический', 1.6, 
 '{"материалы": ["BEARING-6204-2RS", "LUBE-GREASE-LITO"]}', 
'Зазор 0.35-0.55 мм', 5),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 60, (SELECT id FROM production_stages WHERE code = 'painting'), 
 'Покраска корпуса', 'Нанесение порошкового покрытия', 'Окрасочный участок', 'Камера напыления', 0.9, 
 '{"материалы": ["PAINT-POLYMER"]}', 
'Толщина покрытия 70-90 мкм', 6),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'AIR80А2')), 
 70, (SELECT id FROM production_stages WHERE code = 'drying'), 
 'Полимеризация покрытия', 'Сушка в печи', 'Участок сушки', 'Печь конвейерная', 0.7, '{}', 'Температура 180-200°C', 7);

-- Паспорт для электронасоса БЦ-0,5-20-У1.1
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`) VALUES
((SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1'), 8.5, 18, TRUE, 
'Производство бытовых центробежных насосов', 
'Герметичность, проверка напора и производительности');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 (SELECT id FROM materials WHERE code = 'CAST-AK5M2'), 1.2, 2, 3.24, 38.0, 1, 'Корпус насоса из алюминиевого сплава'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 (SELECT id FROM materials WHERE code = 'STEEL-SHEET-1.0'), 0.8, 2, 6.3, 74.0, 2, 'Стальной кожух двигателя'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 (SELECT id FROM materials WHERE code = 'WIRE-CU-0.6'), 0.1, 4, 0.7, 8.2, 3, 'Обмоточный провод'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 (SELECT id FROM materials WHERE code = 'BEARING-6201-2RS'), 2, 1, 0.126, 1.5, 4, 'Подшипники'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 (SELECT id FROM materials WHERE code = 'SEAL-MECH-20'), 1, 1, 0.05, 0.6, 5, 'Торцевое уплотнение'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 (SELECT id FROM materials WHERE code = 'PAINT-ENAMEL'), 0.08, 2, 0.15, 1.8, 6, 'Эмаль для наружной покраски');

-- Маршрутная карта для насоса БЦ-0,5-20-У1.1
INSERT INTO `route_cards` (`product_id`, `name`, `version`, `description`, `total_time_hours`, `is_active`) VALUES
((SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1'), 'Маршрутная карта БЦ-0,5-20-У1.1', '1.0', 
'Технологический процесс производства бытового насоса', 5.5, TRUE);

INSERT INTO `route_card_operations` (`route_card_id`, `operation_number`, `stage_id`, `name`, `description`, `work_center`, `equipment`, `time_norm_hours`, `materials_required`, `instructions`, `sort_order`) VALUES
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 10, (SELECT id FROM production_stages WHERE code = 'casting'), 
 'Литье корпуса', 'Литье корпуса насоса из алюминиевого сплава', 'Литейный участок', 'Машина литьевая низкого давления', 1.5, 
 '{"материалы": ["CAST-AK5M2"]}', 
'Контроль герметичности отливки', 1),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 20, (SELECT id FROM production_stages WHERE code = 'machining'), 
 'Механообработка корпуса', 'Токарная обработка посадочных мест', 'Токарный участок', 'Станок токарный ЧПУ', 1.0, 
 '{}', 
'Шероховатость Ra 1.6 на посадочных поверхностях', 2),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 30, (SELECT id FROM production_stages WHERE code = 'winding'), 
 'Намотка двигателя', 'Намотка статора электродвигателя', 'Намоточный участок', 'Станок намоточный', 1.2, 
 '{"материалы": ["WIRE-CU-0.6"]}', 
'Пропитка лаком после намотки', 3),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 40, (SELECT id FROM production_stages WHERE code = 'assembly'), 
 'Сборка насоса', 'Монтаж рабочего колеса, установка уплотнения', 'Сборочный участок', 'Верстак сборочный', 1.0, 
 '{"материалы": ["BEARING-6201-2RS", "SEAL-MECH-20"]}', 
'Проверка легкости вращения вала', 4),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 50, (SELECT id FROM production_stages WHERE code = 'painting'), 
 'Покраска', 'Нанесение эмали на корпус', 'Окрасочный участок', 'Краскораспылитель', 0.5, 
 '{"материалы": ["PAINT-ENAMEL"]}', 
'Нанесение в 2 слоя', 5),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1')), 
 60, (SELECT id FROM production_stages WHERE code = 'qc'), 
 'Испытания', 'Проверка напора и производительности', 'Испытательный стенд', 'Стенд насосный', 0.3, 
 '{}', 
'Напор не менее 20м, производительность не менее 30 л/мин', 6);

-- Паспорт для электроконфорки ЭКЧ-145
INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`) VALUES
((SELECT id FROM products WHERE article = 'EKCH-145'), 2.5, 12, FALSE, 
'Производство электроконфорок бытовых', 
'Проверка герметичности ТЭН, электрическая безопасность');

INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit_id`, `weight_kg`, `percentage`, `sort_order`, `notes`) VALUES
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 (SELECT id FROM materials WHERE code = 'CAST-SCH20'), 1.5, 2, 10.8, 43.0, 1, 'Чугунная плита'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 (SELECT id FROM materials WHERE code = 'WIRE-NICHROME-2.0'), 0.025, 4, 0.18, 7.2, 2, 'Нихромовая спираль'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 (SELECT id FROM materials WHERE code = 'INSULATION-PERIKLAZ'), 0.3, 2, 0.6, 24.0, 3, 'Периклазовый наполнитель'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.8'), 0.4, 2, 3.14, 12.5, 4, 'Стальной кожух'),
((SELECT id FROM product_passports WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 (SELECT id FROM materials WHERE code = 'CABLE-POWER-3x1.5'), 0.5, 3, 0.08, 3.2, 5, 'Силовой кабель');

-- Маршрутная карта для электроконфорки ЭКЧ-145
INSERT INTO `route_cards` (`product_id`, `name`, `version`, `description`, `total_time_hours`, `is_active`) VALUES
((SELECT id FROM products WHERE article = 'EKCH-145'), 'Маршрутная карта ЭКЧ-145', '1.0', 
'Технологический процесс производства электроконфорки', 3.0, TRUE);

INSERT INTO `route_card_operations` (`route_card_id`, `operation_number`, `stage_id`, `name`, `description`, `work_center`, `equipment`, `time_norm_hours`, `materials_required`, `instructions`, `sort_order`) VALUES
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 10, (SELECT id FROM production_stages WHERE code = 'casting'), 
 'Литье чугунной плиты', 'Литье корпуса конфорки из серого чугуна', 'Литейный участок', 'Вагранка', 1.0, 
 '{"материалы": ["CAST-SCH20"]}', 
'Контроль отсутствия раковин', 1),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 20, (SELECT id FROM production_stages WHERE code = 'machining'), 
 'Фрезеровка пазов', 'Фрезеровка пазов для укладки спирали', 'Фрезерный участок', 'Станок фрезерный', 0.5, 
 '{}', 
'Глубина паза 8±0.5 мм', 2),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 30, (SELECT id FROM production_stages WHERE code = 'assembly'), 
 'Укладка нагревательного элемента', 'Монтаж нихромовой спирали с наполнителем', 'Сборочный участок', 'Верстак', 0.8, 
 '{"материалы": ["WIRE-NICHROME-2.0", "INSULATION-PERIKLAZ"]}', 
'Равномерное распределение наполнителя', 3),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 40, (SELECT id FROM production_stages WHERE code = 'assembly'), 
 'Монтаж кожуха', 'Установка стального кожуха и клеммной коробки', 'Сборочный участок', 'Верстак', 0.4, 
 '{"материалы": ["STEEL-SHEET-0.8", "CABLE-POWER-3x1.5"]}', 
'Надежное крепление кабеля', 4),
((SELECT id FROM route_cards WHERE product_id = (SELECT id FROM products WHERE article = 'EKCH-145')), 
 50, (SELECT id FROM production_stages WHERE code = 'qc'), 
 'Контроль и испытания', 'Проверка сопротивления изоляции, включение на нагрев', 'Контрольный участок', 'Стенд испытательный', 0.3, 
 '{}', 
'Ток утечки не более 0.5 мА, время нагрева до 300°C не более 5 мин', 5);
