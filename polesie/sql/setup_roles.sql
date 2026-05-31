-- ============================================
-- ОБНОВЛЕНИЕ РОЛЕЙ ПОЛЬЗОВАТЕЛЕЙ
-- Расширенная система прав доступа
-- ОАО "Полесьеэлектромаш"
-- ============================================

USE `polesie_production`;



SET FOREIGN_KEY_CHECKS = 0;
-- Обновление таблицы user_roles с расширенными правами
DROP TABLE IF EXISTS `user_roles`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `permissions` JSON,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Создание таблицы прав доступа к модулям
DROP TABLE IF EXISTS `role_module_permissions`;

CREATE TABLE `role_module_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `can_view` BOOLEAN DEFAULT FALSE,
  `can_create` BOOLEAN DEFAULT FALSE,
  `can_edit` BOOLEAN DEFAULT FALSE,
  `can_delete` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rmp_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_role_module` (`role_id`, `module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ВСТАВКА РОЛЕЙ
-- ============================================

INSERT INTO `user_roles` (`name`, `code`, `description`, `permissions`) VALUES
('Администратор', 'admin', 'Полный доступ ко всем модулям системы', '{"all": true}'),
('Директор', 'director', 'Руководство предприятия, просмотр всех отчетов', '{"заказы": ["view", "edit"], "производство": ["view"], "склад": ["view"], "сотрудники": ["view"], "отчеты": ["view", "export"]}' ),
('Менеджер по продажам', 'sales_manager', 'Работа с заказами и клиентами', '{"заказы": ["view", "create", "edit"], "контрагенты": ["view", "create", "edit"], "продукция": ["view"]}'),
('Технолог', 'technologist', 'Управление производственными процессами', '{"производство": ["view", "create", "edit"], "продукция": ["view", "edit"], "маршрутные_карты": ["view", "create", "edit"], "материалы": ["view"]}'),
('Кладовщик', 'storekeeper', 'Учет материалов и продукции на складе', '{"склад": ["view", "create", "edit"], "материалы": ["view", "edit"], "поступление": ["create"], "списание": ["create"], "продукция": ["view"]}'),
('Рабочий', 'worker', 'Исполнение производственных заданий', '{"производство": ["view"], "задания": ["view", "update_status"], "материалы": ["view"]}'),
('Контроль качества', 'quality_inspector', 'Проверка качества продукции', '{"контроль_качества": ["view", "create"], "производство": ["view"], "продукция": ["view"]}');

-- ============================================
-- НАСТРОЙКА ПРАВ ДОСТУПА ПО МОДУЛЯМ
-- ============================================

-- Администратор - полный доступ
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(1, 'dashboard', TRUE, TRUE, TRUE, TRUE),
(1, 'orders', TRUE, TRUE, TRUE, TRUE),
(1, 'contractors', TRUE, TRUE, TRUE, TRUE),
(1, 'production', TRUE, TRUE, TRUE, TRUE),
(1, 'products', TRUE, TRUE, TRUE, TRUE),
(1, 'warehouse', TRUE, TRUE, TRUE, TRUE),
(1, 'materials', TRUE, TRUE, TRUE, TRUE),
(1, 'employees', TRUE, TRUE, TRUE, TRUE),
(1, 'quality', TRUE, TRUE, TRUE, TRUE),
(1, 'reports', TRUE, TRUE, TRUE, TRUE),
(1, 'settings', TRUE, TRUE, TRUE, TRUE);

-- Директор - просмотр всех, редактирование заказов
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(2, 'dashboard', TRUE, FALSE, FALSE, FALSE),
(2, 'orders', TRUE, FALSE, TRUE, FALSE),
(2, 'contractors', TRUE, FALSE, FALSE, FALSE),
(2, 'production', TRUE, FALSE, FALSE, FALSE),
(2, 'products', TRUE, FALSE, FALSE, FALSE),
(2, 'warehouse', TRUE, FALSE, FALSE, FALSE),
(2, 'materials', TRUE, FALSE, FALSE, FALSE),
(2, 'employees', TRUE, FALSE, FALSE, FALSE),
(2, 'quality', TRUE, FALSE, FALSE, FALSE),
(2, 'reports', TRUE, TRUE, FALSE, FALSE);

-- Менеджер по продажам
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(3, 'dashboard', TRUE, FALSE, FALSE, FALSE),
(3, 'orders', TRUE, TRUE, TRUE, FALSE),
(3, 'contractors', TRUE, TRUE, TRUE, FALSE),
(3, 'products', TRUE, FALSE, FALSE, FALSE),
(3, 'production', TRUE, FALSE, FALSE, FALSE);

-- Технолог
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(4, 'dashboard', TRUE, FALSE, FALSE, FALSE),
(4, 'production', TRUE, TRUE, TRUE, FALSE),
(4, 'products', TRUE, FALSE, TRUE, FALSE),
(4, 'materials', TRUE, FALSE, FALSE, FALSE),
(4, 'warehouse', TRUE, FALSE, FALSE, FALSE),
(4, 'orders', TRUE, FALSE, FALSE, FALSE);

-- Кладовщик - ТОЛЬКО СКЛАД
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(5, 'dashboard', TRUE, FALSE, FALSE, FALSE),
(5, 'warehouse', TRUE, TRUE, TRUE, FALSE),
(5, 'materials', TRUE, TRUE, TRUE, FALSE),
(5, 'products', TRUE, FALSE, FALSE, FALSE);

-- Рабочий - ТОЛЬКО ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(6, 'dashboard', TRUE, FALSE, FALSE, FALSE),
(6, 'production', TRUE, FALSE, TRUE, FALSE),
(6, 'materials', TRUE, FALSE, FALSE, FALSE);

-- Контроль качества
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(7, 'dashboard', TRUE, FALSE, FALSE, FALSE),
(7, 'quality', TRUE, TRUE, TRUE, FALSE),
(7, 'production', TRUE, FALSE, FALSE, FALSE),
(7, 'products', TRUE, FALSE, FALSE, FALSE);

-- ============================================
-- ОБНОВЛЕНИЕ ПОЛЬЗОВАТЕЛЕЙ
-- ============================================

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(50),
  `role_id` INT,
  `department` VARCHAR(100),
  `position` VARCHAR(100),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` TIMESTAMP NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Вставка тестовых пользователей
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `phone`, `role_id`, `department`, `position`) VALUES
('admin', 'admin123', 'Администратор Системы', 'admin@polesie.by', '+375 29 111-00-00', 1, 'IT', 'Системный администратор'),
('director', 'director123', 'Директор Предприятия', 'director@polesie.by', '+375 29 111-00-01', 2, 'Руководство', 'Директор'),
('ivanov', 'manager123', 'Иванов Иван', 'ivanov@polesie.by', '+375 29 111-00-02', 3, 'Продажи', 'Менеджер по продажам'),
('petrov', 'tech123', 'Петров Петр', 'petrov@polesie.by', '+375 29 111-00-03', 4, 'Производство', 'Технолог'),
('sidorov', 'store123', 'Сидоров Сидор', 'sidorov@polesie.by', '+375 29 111-00-04', 5, 'Склад', 'Кладовщик'),
('worker1', 'worker123', 'Рабочий Алексей', 'worker1@polesie.by', '+375 29 111-00-05', 6, 'Производство', 'Рабочий'),
('quality1', 'quality123', 'Контролер Ольга', 'quality1@polesie.by', '+375 29 111-00-06', 7, 'ОТК', 'Инспектор по качеству');

-- ============================================
-- ПРЕДСТАВЛЕНИЕ ДЛЯ БЫСТРОГО ПОЛУЧЕНИЯ ПРАВ
-- ============================================

DROP VIEW IF EXISTS `v_user_permissions`;

CREATE VIEW `v_user_permissions` AS
SELECT 
    u.id as user_id,
    u.username,
    u.full_name,
    r.id as role_id,
    r.code as role_code,
    r.name as role_name,
    r.permissions,
    GROUP_CONCAT(
        CONCAT(
            rmp.module, ':',
            IF(rmp.can_view, 'V', '-'),
            IF(rmp.can_create, 'C', '-'),
            IF(rmp.can_edit, 'E', '-'),
            IF(rmp.can_delete, 'D', '-')
        ) SEPARATOR '|'
    ) as module_permissions
FROM users u
JOIN user_roles r ON u.role_id = r.id
LEFT JOIN role_module_permissions rmp ON r.id = rmp.role_id
WHERE u.is_active = TRUE AND r.is_active = TRUE
GROUP BY u.id, r.id;

-- ============================================
-- ИНДЕКСЫ ДЛЯ ОПТИМИЗАЦИИ
-- ============================================

CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_rmp_role ON role_module_permissions(role_id);
CREATE INDEX idx_rmp_module ON role_module_permissions(module);





SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
-- ============================================

-- Получить все права пользователя:
-- SELECT * FROM v_user_permissions WHERE username = 'sidorov';

-- Проверить доступ к модулю:
-- SELECT can_view, can_create, can_edit, can_delete 
-- FROM role_module_permissions 
-- WHERE role_id = (SELECT role_id FROM users WHERE username = 'sidorov') 
-- AND module = 'warehouse';
