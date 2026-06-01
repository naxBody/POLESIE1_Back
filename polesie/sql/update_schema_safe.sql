-- ============================================
-- БЕЗОПАСНОЕ ОБНОВЛЕНИЕ СХЕМЫ БАЗЫ ДАННЫХ
-- Добавление системы ролей и прав доступа без удаления существующих таблиц
-- ============================================

USE `polesie_production`;

SET NAMES utf8mb4;

-- Исправляем проблему с collation: приводим все к utf8mb4_unicode_ci
ALTER DATABASE `polesie_production` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Приводим таблицу users к единой сортировке
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Приводим таблицу user_roles к единой сортировке (если существует)
ALTER TABLE `user_roles` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================
-- 1. СОЗДАНИЕ ТАБЛИЦЫ РОЛЕЙ (если не существует)
-- ============================================

CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. ДОБАВЛЕНИЕ КОЛОНКИ role_id В users (если не существует)
-- ============================================

-- Проверяем и добавляем колонку role_id
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'role_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " INT")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Добавляем внешний ключ если его нет
SET @constraint_exists = (
  SELECT COUNT(*) 
  FROM information_schema.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND CONSTRAINT_NAME = 'fk_users_role'
);

SET @sql = IF(@constraint_exists = 0,
  'ALTER TABLE `users` ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. ЗАПОЛНЕНИЕ РОЛЕЙ (игнорируем дубликаты)
-- ============================================

INSERT IGNORE INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ ко всем модулям системы', '{"all": true}'),
('Директор', 'director', 'Руководство предприятием', '{"orders": ["read", "create", "update", "delete"], "production": ["read"], "warehouse": ["read"], "contractors": ["read", "create", "update"], "reports": ["read"]}'),
('Менеджер по продажам', 'sales_manager', 'Работа с заказами и клиентами', '{"orders": ["read", "create", "update"], "products": ["read"], "contractors": ["read", "create"], "production": ["read"]}'),
('Технолог', 'technologist', 'Управление производственными процессами', '{"production": ["read", "create", "update"], "route_cards": ["read", "create", "update"], "materials": ["read"], "tasks": ["read", "create", "update"]}'),
('Кладовщик', 'storekeeper', 'Учет материалов на складе', '{"warehouse": ["read", "create", "update"], "materials": ["read", "update"], "receipts": ["read", "create"], "writeoffs": ["read", "create"]}'),
('Рабочий', 'worker', 'Выполнение производственных заданий', '{"production": ["read"], "tasks": ["read", "update"], "my_tasks": ["read", "update"]}'),
('Контроль качества', 'quality_control', 'Проверка качества продукции', '{"production": ["read"], "quality_checks": ["read", "create"], "tasks": ["read"]}'),
('Бухгалтер', 'accountant', 'Финансовый учет, работа с документами и отчетами', '{"orders": ["read"], "contractors": ["read"], "reports": ["read", "export"], "finance": ["read", "create", "update"]}');

-- ============================================
-- 4. ОБНОВЛЕНИЕ ПОЛЬЗОВАТЕЛЕЙ (привязка к ролям)
-- ============================================

-- Создаем временную таблицу для маппинга username -> role_code
CREATE TEMPORARY TABLE IF NOT EXISTS `temp_user_roles` (
  `username` VARCHAR(50) NOT NULL,
  `role_code` VARCHAR(50) NOT NULL
);

-- Очищаем и заполняем
DELETE FROM `temp_user_roles`;

INSERT INTO `temp_user_roles` (`username`, `role_code`) VALUES
('admin', 'admin'),
('director', 'director'),
('ivanov', 'sales_manager'),
('petrov', 'technologist'),
('sidorov', 'storekeeper'),
('worker1', 'worker'),
('accountant1', 'accountant');

-- Обновляем role_id у существующих пользователей (с явным указанием collation для избежания конфликтов)
UPDATE `users` u
JOIN `temp_user_roles` tur ON u.username COLLATE utf8mb4_unicode_ci = tur.username COLLATE utf8mb4_unicode_ci
JOIN `user_roles` ur ON tur.role_code COLLATE utf8mb4_unicode_ci = ur.code COLLATE utf8mb4_unicode_ci
SET u.role_id = ur.id;

-- Удаляем временную таблицу
DROP TEMPORARY TABLE IF EXISTS `temp_user_roles`;

-- ============================================
-- 5. ЕСЛИ ПОЛЬЗОВАТЕЛИ НЕ СУЩЕСТВУЮТ - СОЗДАЕМ ТЕСТОВЫХ
-- ============================================

-- Проверяем是否存在 пользователей и создаем если нет
SET @user_count = (SELECT COUNT(*) FROM `users`);

-- Если пользователей нет вообще, создаем тестовых
INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', 
       (SELECT id FROM `user_roles` WHERE code = 'admin'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'admin');

INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'director', 'director123', 'Директор Предприятия', 'director@polesie.by',
       (SELECT id FROM `user_roles` WHERE code = 'director'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'director');

INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'ivanov', 'manager123', 'Иванов Иван Иванович', 'ivanov@polesie.by',
       (SELECT id FROM `user_roles` WHERE code = 'sales_manager'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'ivanov');

INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'petrov', 'tech123', 'Петров Петр Петрович', 'petrov@polesie.by',
       (SELECT id FROM `user_roles` WHERE code = 'technologist'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'petrov');

INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'sidorov', 'store123', 'Сидоров Сидор Сидорович', 'sidorov@polesie.by',
       (SELECT id FROM `user_roles` WHERE code = 'storekeeper'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'sidorov');

INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'worker1', 'worker123', 'Рабочий Алексей Алексеевич', 'worker1@polesie.by',
       (SELECT id FROM `user_roles` WHERE code = 'worker'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'worker1');

INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'qc1', 'qc123', 'Контролер Качества', 'qc@polesie.by',
       (SELECT id FROM `user_roles` WHERE code = 'quality_control'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'qc1');

INSERT IGNORE INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role_id`, `is_active`) 
SELECT 'accountant1', 'account123', 'Бухгалтер Елена', 'accountant1@polesie.by',
       (SELECT id FROM `user_roles` WHERE code = 'accountant'), TRUE
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'accountant1');

-- ============================================
-- 6. ИНФОРМАЦИЯ О РОЛЯХ ДЛЯ ПРОВЕРКИ
-- ============================================

SELECT '=== РОЛИ УСПЕШНО ДОБАВЛЕНЫ ===' AS status;
SELECT * FROM `user_roles`;

SELECT '=== ПОЛЬЗОВАТЕЛИ С РОЛЯМИ ===' AS status;
SELECT u.id, u.username, u.full_name, u.email, r.name AS role_name, r.code AS role_code, u.is_active
FROM `users` u
LEFT JOIN `user_roles` r ON u.role_id = r.id;

-- ============================================
-- ГОТОВО! Система ролей успешно установлена.
-- ============================================
