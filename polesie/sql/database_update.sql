-- ============================================
-- ПОЛЕСЬЕ ПРОДАКШН: ОБНОВЛЕННАЯ СХЕМА БАЗЫ ДАННЫХ
-- С маршрутными картами, этапами производства и планом выпуска
-- Все товары и материалы ОАО "Полесьеэлектромаш" хранятся в базе данных
-- ============================================
CREATE DATABASE IF NOT EXISTS `polesie_production` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `polesie_production`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- УДАЛЕНИЕ СТАРЫХ ТАБЛИЦ
-- ============================================
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

-- ============================================
-- 1. ЕДИНИЦЫ ИЗМЕРЕНИЯ
-- ============================================
CREATE TABLE `base_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `symbol` VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. РОЛИ ПОЛЬЗОВАТЕЛЕЙ
-- ============================================
CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. ПОЛЬЗОВАТЕЛИ
-- ============================================
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

-- ============================================
-- 4. КОНТРАГЕНТЫ
-- ============================================
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

-- ============================================
-- 5. КАТЕГОРИИ МАТЕРИАЛОВ
-- ============================================
CREATE TABLE `material_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_mat_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `material_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. КАТЕГОРИИ ПРОДУКЦИИ
-- ============================================
CREATE TABLE `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  CONSTRAINT `fk_prod_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. МАТЕРИАЛЫ (с отдельными полями для свойств)
-- ============================================
CREATE TABLE `materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name_full` VARCHAR(200) NOT NULL,
  `name_short` VARCHAR(100),
  `category_id` INT,
  `base_unit_id` INT,
  
  -- Основные свойства материалов
  `material_type` VARCHAR(50) COMMENT 'Тип материала: металл, провод, лак, подшипник и т.д.',
  `grade` VARCHAR(50) COMMENT 'Марка материала (Ст3, 45, 40Х, М1, ПЭТВ и т.д.)',
  `diameter_mm` DECIMAL(10,2) COMMENT 'Диаметр в мм (для прутков, проводов)',
  `thickness_mm` DECIMAL(10,2) COMMENT 'Толщина в мм (для листов)',
  `width_mm` DECIMAL(10,2) COMMENT 'Ширина в мм',
  `length_mm` DECIMAL(12,2) COMMENT 'Длина в мм',
  `cross_section_mm2` DECIMAL(10,2) COMMENT 'Сечение в мм² (для проводов)',
  `weight_kg` DECIMAL(12,3) COMMENT 'Вес в кг',
  
  -- Дополнительные свойства
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

-- ============================================
-- 8. ПРОДУКЦИЯ (с отдельными полями для свойств)
-- ============================================
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article` VARCHAR(50) NOT NULL UNIQUE,
  `code_gost` VARCHAR(50) COMMENT 'Код по ГОСТ',
  `name_full` VARCHAR(200) NOT NULL,
  `name_short` VARCHAR(100),
  `category_id` INT,
  `base_unit_id` INT,
  
  -- === ОСНОВНЫЕ ХАРАКТЕРИСТИКИ ЭЛЕКТРОДВИГАТЕЛЕЙ ===
  `power_kw` DECIMAL(10,3) COMMENT 'Номинальная мощность, кВт',
  `power_kw_min` DECIMAL(10,3) COMMENT 'Минимальная мощность в серии, кВт',
  `power_kw_max` DECIMAL(10,3) COMMENT 'Максимальная мощность в серии, кВт',
  `rpm` INT COMMENT 'Частота вращения, об/мин',
  `voltage_v` VARCHAR(50) COMMENT 'Напряжение, В (например: 380, 220/380, 380/660)',
  `frequency_hz` INT DEFAULT 50 COMMENT 'Частота тока, Гц',
  `efficiency_class` VARCHAR(10) COMMENT 'Класс энергоэффективности (IE1, IE2, IE3, IE4)',
  `shaft_height_mm` INT COMMENT 'Высота оси вращения, мм',
  `frame_size` VARCHAR(20) COMMENT 'Типоразмер корпуса (например: 80A, 90L, 100L)',
  
  -- Исполнения
  `climate_versions` VARCHAR(50) COMMENT 'Климатические исполнения (У1, У2, У3, Т2 и т.д.)',
  `mounting_versions` VARCHAR(50) COMMENT 'Исполнения по монтажу (IM1081, IM2081, IM3081)',
  `protection_class` VARCHAR(20) COMMENT 'Класс защиты (IP44, IP54, IP55)',
  
  -- Тип двигателя
  `motor_type` VARCHAR(50) COMMENT 'Тип двигателя (асинхронный, взрывозащищенный, насосный)',
  `application` VARCHAR(100) COMMENT 'Область применения',
  
  -- Материалы конструкции
  `housing_material` VARCHAR(50) COMMENT 'Материал корпуса (алюминий, чугун, сталь)',
  `impeller_material` VARCHAR(50) COMMENT 'Материал рабочего колеса',
  `shaft_material` VARCHAR(50) COMMENT 'Материал вала',
  
  -- Для насосных двигателей
  `flow_rate_m3_h` DECIMAL(10,2) COMMENT 'Расход жидкости, м³/ч',
  `head_m` DECIMAL(10,2) COMMENT 'Напор, м',
  `max_immersion_depth_m` DECIMAL(10,2) COMMENT 'Максимальная глубина погружения, м',
  `max_solid_size_mm` INT COMMENT 'Максимальный размер твердых включений, мм',
  
  -- Специальные свойства
  `explosion_protection` VARCHAR(50) COMMENT 'Взрывозащита (Ex d, Ex de, Ex n)',
  `capacitor_included` BOOLEAN DEFAULT FALSE COMMENT 'Конденсатор в комплекте',
  `standard` VARCHAR(50) COMMENT 'Стандарт (ГОСТ, IEC, DIN)',
  `production_method` VARCHAR(50) COMMENT 'Метод производства (серийный, под заказ)',
  `custom_made` BOOLEAN DEFAULT FALSE COMMENT 'Изготовление под заказ',
  `speeds` INT COMMENT 'Количество скоростей',
  `slip_percent` DECIMAL(5,2) COMMENT 'Скольжение, %',
  `weight_range_kg` VARCHAR(50) COMMENT 'Диапазон веса, кг',
  
  -- Общие свойства продукции
  `image` VARCHAR(255),
  `base_price` DECIMAL(15,2),
  `currency` CHAR(3) DEFAULT 'BYN',
  `is_active` BOOLEAN DEFAULT TRUE,
  `is_serial_tracked` BOOLEAN DEFAULT FALSE COMMENT 'Требуется серийный учет',
  `warranty_months` INT DEFAULT 24 COMMENT 'Гарантийный срок, месяцев',
  `is_bestseller` BOOLEAN DEFAULT FALSE COMMENT 'Хит продаж',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prod_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Индексы для ускорения поиска
CREATE INDEX idx_products_power ON products(power_kw);
CREATE INDEX idx_products_rpm ON products(rpm);
CREATE INDEX idx_products_frame ON products(frame_size);
CREATE INDEX idx_products_motor_type ON products(motor_type);
CREATE INDEX idx_materials_type ON materials(material_type);
CREATE INDEX idx_materials_grade ON materials(grade);


-- ============================================
-- 9. ЗАКАЗЫ
-- ============================================
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

-- ============================================
-- 10. ПОЗИЦИИ ЗАКАЗОВ
-- ============================================
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

-- ============================================
-- 11. ЭТАПЫ ПРОИЗВОДСТВА (справочник)
-- ============================================
CREATE TABLE `production_stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) UNIQUE,
  `description` TEXT,
  `sort_order` INT DEFAULT 0,
  `color` VARCHAR(20) DEFAULT '#3498db',
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 12. МАРШРУТНЫЕ КАРТЫ (техпроцессы для продукции)
-- ============================================
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

-- ============================================
-- 13. ОПЕРАЦИИ МАРШРУТНОЙ КАРТЫ
-- ============================================
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

-- ============================================
-- 14. ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ
-- ============================================
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

-- ============================================
-- 15. ЭТАПЫ ВЫПОЛНЕНИЯ ЗАДАНИЯ
-- ============================================
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

-- ============================================
-- 16. МАТЕРИАЛЫ ДЛЯ ЗАДАНИЙ
-- ============================================
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

-- ============================================
-- 17. ПРОВЕРКИ КАЧЕСТВА
-- ============================================
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

-- ============================================
-- 18. СЕРИЙНЫЕ НОМЕРА ПРОДУКЦИИ
-- ============================================
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

-- ============================================
-- 19. ДОКУМЕНТЫ ПРОДУКЦИИ
-- ============================================
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
-- 20. ПАСПОРТА ПРОДУКТОВ (технологические спецификации)
-- ============================================
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

-- ============================================
-- 21. МАТЕРИАЛЫ ПАСПОРТА ПРОДУКТА
-- ============================================
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
-- ЗАПОЛНЕНИЕ ТЕСТОВЫМИ ДАННЫМИ
-- ============================================

-- Единицы измерения
INSERT INTO `base_units` (`name`, `code`, `symbol`) VALUES
('Штука', 'pcs', 'шт'), ('Килограмм', 'kg', 'кг'), ('Метр', 'm', 'м'),
('Тонна', 't', 'т'), ('Литр', 'l', 'л'), ('Комплект', 'set', 'компл');

-- Роли пользователей
INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ', '{"all":true}'),
('Директор', 'director', 'Руководство', '{"all":true}'),
('Менеджер', 'sales_manager', 'Заказы и клиенты', '{"orders":["read","create"],"products":["read"]}'),
('Технолог', 'technologist', 'Производство', '{"production":["read","create"],"materials":["read"]}'),
('Кладовщик', 'storekeeper', 'Склад', '{"warehouse":["read","create"],"materials":["read","update"]}'),
('Рабочий', 'worker', 'Исполнитель', '{"production":["read"],"tasks":["update"]}');

-- Пользователи (пароль admin123 для всех)
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 1),
('director', 'director123', 'Директор Предприятия', 'director@polesie.by', 2),
('ivanov', 'admin123', 'Иванов Иван', 'ivanov@polesie.by', 3),
('petrov', 'admin123', 'Петров Петр', 'petrov@polesie.by', 4),
('sidorov', 'admin123', 'Сидоров Сидор', 'sidorov@polesie.by', 5),
('worker1', 'admin123', 'Рабочий Алексей', 'worker1@polesie.by', 6);

-- Контрагенты
INSERT INTO `contractors` (`name`, `inn`, `type`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "СтальПром"', '100123456', 'supplier', 'Кузнецов А.А.', '+375 29 111-22-33', 'info@stalprom.by', 'г. Минск, ул. Промышленная 10'),
('ЗАО "ЭлектроТех"', '200234567', 'supplier', 'Волкова Е.В.', '+375 29 222-33-44', 'sales@electrotech.by', 'г. Гродно, ул. Заводская 5'),
('УП "Метизы"', '300345678', 'supplier', 'Белый И.И.', '+375 29 333-44-55', 'zakaz@metizy.by', 'г. Брест, пр. Машерова 15'),
('ООО "СтройМонтаж"', '400456789', 'customer', 'Орлов О.О.', '+375 29 444-55-66', 'order@stroymontazh.by', 'г. Гомель, ул. Строителей 20'),
('ЧТУП "АгроСервис"', '500567890', 'customer', 'Зеленая З.З.', '+375 29 555-66-77', 'agro@service.by', 'г. Витебск, пер. Полевой 3');

-- Категории материалов
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
(NULL, 'Подшипники', 'BEARING', 'Подшипники качения'),
(NULL, 'Лаки и эмали', 'LACQUER', 'Изоляционные лаки и эмали'),
(NULL, 'Изоляционные материалы', 'INSULATION', 'Прокладочные и изоляционные материалы');

-- Категории продукции
INSERT INTO `product_categories` (`parent_id`, `name`, `code`, `description`) VALUES
(NULL, 'Электродвигатели', 'MOTOR', 'Асинхронные электродвигатели'),
(NULL, 'Генераторы', 'GENERATOR', 'Дизельные генераторы'),
(NULL, 'Трансформаторы', 'TRANSFORMER', 'Силовые трансформаторы'),
(NULL, 'Щитовое оборудование', 'SWITCHGEAR', 'Распределительные щиты'),
(NULL, 'Запчасти', 'SPARE_PARTS', 'Запасные части и комплектующие');


-- ============================================
-- МАТЕРИАЛЫ ОАО "ПОЛЕСЬЕЭЛЕКТРОМАШ"
-- Все материалы с отдельными полями для свойств
-- ============================================
INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `material_type`, `grade`, `diameter_mm`, `thickness_mm`, `width_mm`, `length_mm`, `cross_section_mm2`, `weight_kg`, `current_stock`, `min_stock`, `location`, `supplier_id`, `last_price`, `currency`) VALUES
-- Прутки стальные
('ST-BAR-45-010', 'Пруток стальной 45 Ø10мм', 'Пруток 45 Ø10', 2, 3, 'металл', '45', 10.0, NULL, NULL, 6000, NULL, NULL, 321.52, 64.30, 'Склад №1, Секция А', 1, 2.50, 'BYN'),
('ST-BAR-40X-010', 'Пруток легированный 40Х Ø10мм', 'Пруток 40Х Ø10', 2, 3, 'металл', '40Х', 10.0, NULL, NULL, 6000, NULL, NULL, 17.38, 3.48, 'Склад №1, Секция А', 1, 3.20, 'BYN'),
('ST-BAR-45-020', 'Пруток стальной 45 Ø20мм', 'Пруток 45 Ø20', 2, 3, 'металл', '45', 20.0, NULL, NULL, 6000, NULL, NULL, 450.00, 50.00, 'Склад №1, Секция Б', 1, 4.80, 'BYN'),
-- Листовой прокат
('ST-SHEET-3-08', 'Лист стальной 3мм Ст3сп', 'Лист 3мм', 3, 2, 'металл', 'Ст3сп', NULL, 3.0, 1500, 6000, NULL, NULL, 1250.00, 200.00, 'Склад №2, Секция А', 1, 2.10, 'BYN'),
-- Провода обмоточные
('WIRE-CU-2.5', 'Провод медный ПЭТВ-2 2.5мм²', 'Провод 2.5', 6, 3, 'провод', 'М1', NULL, NULL, NULL, NULL, 2.5, NULL, 1500.00, 300.00, 'Склад №4, Секция А', 2, 1.20, 'BYN'),
('WIRE-PETV-0.51', 'Провод обмоточный ПЭТВ-2 Ø0.51мм', 'Провод 0.51', 6, 3, 'провод', 'М1', 0.51, NULL, NULL, NULL, NULL, NULL, 200.00, 50.00, 'Склад №4, Секция Б', 2, 15.00, 'BYN'),
('WIRE-PETV-0.80', 'Провод обмоточный ПЭТВ-2 Ø0.80мм', 'Провод 0.80', 6, 3, 'провод', 'М1', 0.80, NULL, NULL, NULL, NULL, NULL, 180.00, 40.00, 'Склад №4, Секция Б', 2, 18.00, 'BYN'),
-- Крепеж
('BOLT-M10x50', 'Болт М10х50 8.8 оцинкованный', 'Болт М10х50', 9, 1, 'крепеж', '8.8', NULL, NULL, NULL, NULL, NULL, NULL, 5000.00, 1000.00, 'Склад №5, Ящик 1', 3, 0.35, 'BYN'),
('NUT-M10', 'Гайка М10 8 оцинкованная', 'Гайка М10', 10, 1, 'крепеж', '8', NULL, NULL, NULL, NULL, NULL, NULL, 6000.00, 1200.00, 'Склад №5, Ящик 2', 3, 0.15, 'BYN'),
-- Подшипники
('BRG-6205', 'Подшипник 6205-2RS', 'Подшипник 6205', 11, 1, 'подшипник', '6205', NULL, NULL, NULL, NULL, NULL, 0.13, 150.00, 30.00, 'Склад №5, Ящик 3', 2, 8.50, 'BYN'),
('BRG-6206', 'Подшипник 6206-2RS', 'Подшипник 6206', 11, 1, 'подшипник', '6206', NULL, NULL, NULL, NULL, NULL, 0.19, 120.00, 25.00, 'Склад №5, Ящик 3', 2, 10.50, 'BYN'),
('BRG-6207', 'Подшипник 6207-2RS', 'Подшипник 6207', 11, 1, 'подшипник', '6207', NULL, NULL, NULL, NULL, NULL, 0.26, 100.00, 20.00, 'Склад №5, Ящик 3', 2, 12.50, 'BYN'),
('BRG-6305', 'Подшипник 6305-2RS', 'Подшипник 6305', 11, 1, 'подшипник', '6305', NULL, NULL, NULL, NULL, NULL, 0.21, 80.00, 15.00, 'Склад №5, Ящик 4', 2, 14.00, 'BYN'),
-- Лаки и эмали
('LACQUER-ML92', 'Лак масляно-битумный МЛ-92', 'Лак МЛ-92', 12, 6, 'лак', 'МЛ-92', NULL, NULL, NULL, NULL, NULL, NULL, 50.00, 10.00, 'Склад №6, Секция А', 2, 25.00, 'BYN'),
('LACQUER-KO916', 'Лак кремнийорганический КО-916', 'Лак КО-916', 12, 6, 'лак', 'КО-916', NULL, NULL, NULL, NULL, NULL, NULL, 30.00, 5.00, 'Склад №6, Секция А', 2, 45.00, 'BYN'),
-- Изоляционные материалы
('INSUL-LAKTOCAN', 'Лакоткань ЛХМ-0.25мм', 'Лакоткань', 13, 3, 'изоляция', 'ЛХМ', NULL, 0.25, 1000, 50000, NULL, NULL, 200.00, 50.00, 'Склад №6, Секция Б', 2, 35.00, 'BYN'),
('INSUL-FILM', 'Пленка электроизоляционная ПЭТ-Э', 'Пленка ПЭТ', 13, 2, 'изоляция', 'ПЭТ-Э', NULL, 0.15, 500, 10000, NULL, NULL, 150.00, 30.00, 'Склад №6, Секция Б', 2, 28.00, 'BYN');

-- ============================================
-- ПРОДУКЦИЯ ОАО "ПОЛЕСЬЕЭЛЕКТРОМАШ"
-- Все товары с отдельными полями для основных характеристик двигателей
-- ============================================
INSERT INTO `products` (`article`, `code_gost`, `name_full`, `name_short`, `category_id`, `base_unit_id`, 
  `power_kw`, `power_kw_min`, `power_kw_max`, `rpm`, `voltage_v`, `frequency_hz`, `efficiency_class`, 
  `shaft_height_mm`, `frame_size`, `climate_versions`, `mounting_versions`, `protection_class`, 
  `motor_type`, `application`, `housing_material`, `impeller_material`, `shaft_material`,
  `flow_rate_m3_h`, `head_m`, `max_immersion_depth_m`, `max_solid_size_mm`,
  `explosion_protection`, `capacitor_included`, `standard`, `production_method`, `custom_made`, 
  `speeds`, `slip_percent`, `weight_range_kg`, `is_serial_tracked`, `warranty_months`, `is_bestseller`,
  `image`, `base_price`, `currency`, `is_active`) VALUES
-- Электродвигатели АДМ 80A4 серии
('ADM-80A4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 80A4', 'АДМ 80A4', 1, 1,
  1.1, 1.1, 1.1, 1500, '380', 50, 'IE2', 80, '80A', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'алюминий', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 4.5, '9-11', TRUE, 24, TRUE,
  'motor_adm80a4.jpg', 350.00, 'BYN', TRUE),

('ADM-80B4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 80B4', 'АДМ 80B4', 1, 1,
  1.5, 1.5, 1.5, 1500, '380', 50, 'IE2', 80, '80B', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'алюминий', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 4.2, '10-12', TRUE, 24, TRUE,
  'motor_adm80b4.jpg', 420.00, 'BYN', TRUE),

('ADM-90L4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 90L4', 'АДМ 90L4', 1, 1,
  2.2, 2.2, 2.2, 1500, '380', 50, 'IE2', 90, '90L', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'алюминий', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 3.8, '14-16', TRUE, 24, TRUE,
  'motor_adm90l4.jpg', 520.00, 'BYN', TRUE),

('ADM-100L4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 100L4', 'АДМ 100L4', 1, 1,
  4.0, 4.0, 4.0, 1500, '380', 50, 'IE2', 100, '100L', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'алюминий', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 3.5, '20-23', TRUE, 24, TRUE,
  'motor_adm100l4.jpg', 780.00, 'BYN', TRUE),

('ADM-112M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 112M4', 'АДМ 112M4', 1, 1,
  5.5, 5.5, 5.5, 1500, '380', 50, 'IE2', 112, '112M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'алюминий', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 3.2, '26-30', TRUE, 24, TRUE,
  'motor_adm112m4.jpg', 950.00, 'BYN', TRUE),

('ADM-132S4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 132S4', 'АДМ 132S4', 1, 1,
  7.5, 7.5, 7.5, 1500, '380', 50, 'IE2', 132, '132S', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 2.9, '38-42', TRUE, 24, TRUE,
  'motor_adm132s4.jpg', 1250.00, 'BYN', TRUE),

('ADM-132M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 132M4', 'АДМ 132M4', 1, 1,
  11.0, 11.0, 11.0, 1500, '380', 50, 'IE2', 132, '132M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 2.6, '45-50', TRUE, 24, TRUE,
  'motor_adm132m4.jpg', 1650.00, 'BYN', TRUE),

('ADM-160S4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 160S4', 'АДМ 160S4', 1, 1,
  15.0, 15.0, 15.0, 1500, '380', 50, 'IE2', 160, '160S', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 2.3, '65-72', TRUE, 24, TRUE,
  'motor_adm160s4.jpg', 2200.00, 'BYN', TRUE),

('ADM-160M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 160M4', 'АДМ 160M4', 1, 1,
  18.5, 18.5, 18.5, 1500, '380', 50, 'IE2', 160, '160M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 2.1, '75-82', TRUE, 24, TRUE,
  'motor_adm160m4.jpg', 2650.00, 'BYN', TRUE),

('ADM-180S4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 180S4', 'АДМ 180S4', 1, 1,
  22.0, 22.0, 22.0, 1500, '380', 50, 'IE2', 180, '180S', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 1.9, '90-98', TRUE, 24, TRUE,
  'motor_adm180s4.jpg', 3200.00, 'BYN', TRUE),

('ADM-180M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 180M4', 'АДМ 180M4', 1, 1,
  30.0, 30.0, 30.0, 1500, '380', 50, 'IE2', 180, '180M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 1.7, '105-115', TRUE, 24, TRUE,
  'motor_adm180m4.jpg', 4100.00, 'BYN', TRUE),

('ADM-200M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 200M4', 'АДМ 200M4', 1, 1,
  37.0, 37.0, 37.0, 1500, '380', 50, 'IE2', 200, '200M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 1.5, '130-145', TRUE, 24, TRUE,
  'motor_adm200m4.jpg', 5200.00, 'BYN', TRUE),

('ADM-225M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 225M4', 'АДМ 225M4', 1, 1,
  45.0, 45.0, 45.0, 1500, '380', 50, 'IE2', 225, '225M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 1.3, '160-180', TRUE, 24, TRUE,
  'motor_adm225m4.jpg', 6500.00, 'BYN', TRUE),

('ADM-250S4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 250S4', 'АДМ 250S4', 1, 1,
  55.0, 55.0, 55.0, 1500, '380', 50, 'IE2', 250, '250S', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 1.2, '200-220', TRUE, 24, TRUE,
  'motor_adm250s4.jpg', 8200.00, 'BYN', TRUE),

('ADM-250M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 250M4', 'АДМ 250M4', 1, 1,
  75.0, 75.0, 75.0, 1500, '380', 50, 'IE2', 250, '250M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 1.0, '240-265', TRUE, 24, TRUE,
  'motor_adm250m4.jpg', 10500.00, 'BYN', TRUE),

('ADM-280S4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 280S4', 'АДМ 280S4', 1, 1,
  90.0, 90.0, 90.0, 1500, '380', 50, 'IE2', 280, '280S', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 0.9, '290-320', TRUE, 24, TRUE,
  'motor_adm280s4.jpg', 13000.00, 'BYN', TRUE),

('ADM-280M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 280M4', 'АДМ 280M4', 1, 1,
  110.0, 110.0, 110.0, 1500, '380', 50, 'IE2', 280, '280M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 0.8, '340-375', TRUE, 24, TRUE,
  'motor_adm280m4.jpg', 16000.00, 'BYN', TRUE),

('ADM-315S4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 315S4', 'АДМ 315S4', 1, 1,
  132.0, 132.0, 132.0, 1500, '380', 50, 'IE2', 315, '315S', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 0.7, '420-460', TRUE, 24, TRUE,
  'motor_adm315s4.jpg', 20000.00, 'BYN', TRUE),

('ADM-315M4', 'ГОСТ Р 51689-2000', 'Двигатель асинхронный АДМ 315M4', 'АДМ 315M4', 1, 1,
  160.0, 160.0, 160.0, 1500, '380', 50, 'IE2', 315, '315M', 'У1,У2,У3', 'IM1081', 'IP54',
  'асинхронный', 'Общепромышленное применение', 'чугун', NULL, 'сталь 45',
  NULL, NULL, NULL, NULL, NULL, FALSE, 'ГОСТ', 'серийный', FALSE, 1, 0.6, '500-550', TRUE, 24, TRUE,
  'motor_adm315m4.jpg', 25000.00, 'BYN', TRUE);


-- ============================================
-- ЭТАПЫ ПРОИЗВОДСТВА
-- ============================================
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

-- ============================================
-- МАРШРУТНАЯ КАРТА ДЛЯ АДМ 80A4
-- ============================================
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

-- ============================================
-- ЗАКАЗЫ И ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ (примеры)
-- ============================================
INSERT INTO `orders` (`order_number`, `customer_id`, `responsible_user_id`, `status`, `order_date`, `delivery_date`, `total_amount`, `notes`) VALUES
('ORD-2025-001', 4, 3, 'processing', '2025-01-15', '2025-02-15', 10500.00, 'Срочный заказ'),
('ORD-2025-002', 5, 3, 'new', '2025-01-20', '2025-03-01', 7800.00, 'По договору №45'),
('ORD-2025-003', 4, 3, 'ready', '2025-01-10', '2025-02-10', 3500.00, 'Самовывоз');

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(1, 1, 10, 350.00, 3500.00, 'in_progress'),
(1, 2, 5, 420.00, 2100.00, 'not_started'),
(1, 3, 2, 520.00, 1040.00, 'not_started'),
(1, 4, 2, 780.00, 1560.00, 'not_started'),
(1, 5, 2, 950.00, 1900.00, 'not_started'),
(2, 3, 10, 520.00, 5200.00, 'not_started'),
(3, 1, 10, 350.00, 3500.00, 'completed');

-- Производственные задания
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `responsible_id`, `worker_id`) VALUES
('TASK-2025-001', 1, 1, 1, 1, 10, 'in_progress', 'high', '2025-01-16', '2025-02-10', 4, 6),
('TASK-2025-002', 1, 2, 2, NULL, 5, 'planned', 'normal', '2025-01-20', '2025-02-15', 4, NULL),
('TASK-2025-003', 3, 7, 1, 1, 10, 'completed', 'normal', '2025-01-11', '2025-01-25', 4, 6);

-- Этапы выполнения заданий
INSERT INTO `production_task_stages` (`task_id`, `stage_id`, `status`, `started_at`, `completed_at`, `worker_id`, `time_spent_hours`, `quantity_passed`) VALUES
(1, 1, 'completed', '2025-01-16 08:00:00', '2025-01-16 12:00:00', 6, 1.0, 10),
(1, 2, 'completed', '2025-01-16 13:00:00', '2025-01-17 17:00:00', 6, 4.0, 10),
(1, 3, 'completed', '2025-01-18 08:00:00', '2025-01-18 12:00:00', 6, 1.0, 10),
(1, 4, 'completed', '2025-01-18 13:00:00', '2025-01-18 17:00:00', 6, 1.0, 10),
(1, 5, 'in_progress', '2025-01-19 08:00:00', NULL, 6, 0.5, 5),
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
(1, 1, 50.0, 50.0, 30.0, 2.50, 125.00, 'issued'),
(1, 2, 10.0, 10.0, 5.0, 3.20, 32.00, 'issued'),
(1, 5, 200.0, 200.0, 100.0, 1.20, 240.00, 'issued'),
(1, 6, 100.0, 100.0, 50.0, 15.00, 1500.00, 'issued'),
(1, 8, 100.0, 100.0, 50.0, 0.35, 35.00, 'issued'),
(1, 9, 100.0, 100.0, 50.0, 0.15, 15.00, 'issued'),
(1, 10, 20.0, 20.0, 10.0, 8.50, 170.00, 'issued');

-- Серийные номера
INSERT INTO `product_serial_numbers` (`product_id`, `serial_number`, `production_date`, `task_id`, `status`, `warranty_start`, `warranty_end`) VALUES
(1, 'SN-ADM80A4-2025-0001', '2025-01-23', 3, 'active', '2025-01-23', '2026-01-23'),
(1, 'SN-ADM80A4-2025-0002', '2025-01-23', 3, 'active', '2025-01-23', '2026-01-23'),
(1, 'SN-ADM80A4-2025-0003', '2025-01-23', 3, 'active', '2025-01-23', '2026-01-23');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- КОНЕЦ ФАЙЛА ОБНОВЛЕНИЯ БАЗЫ ДАННЫХ
-- Все товары и материалы ОАО "Полесьеэлектромаш" хранятся в базе данных
-- Каждое свойство имеет свое отдельное поле
-- ============================================
