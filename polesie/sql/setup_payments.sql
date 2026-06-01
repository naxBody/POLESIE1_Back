-- ============================================
-- МОДУЛЬ ПЛАТЕЖЕЙ И ФИНАНСОВ
-- Полная реализация платежного функционала
-- ОАО "Полесьеэлектромаш"
-- ============================================

USE `polesie_production`;

-- Отключаем проверки внешних ключей для безопасного обновления
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- СПРАВОЧНИКИ ДЛЯ ПЛАТЕЖЕЙ
-- ============================================

-- Типы платежей
DROP TABLE IF EXISTS `payment_types`;

CREATE TABLE `payment_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `type` ENUM('income', 'expense') NOT NULL COMMENT 'Доход или расход',
  `category` VARCHAR(100) NOT NULL COMMENT 'Категория платежа',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Справочник типов платежей';

-- Статьи затрат
DROP TABLE IF EXISTS `expense_articles`;

CREATE TABLE `expense_articles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `parent_id` INT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ea_parent` FOREIGN KEY (`parent_id`) REFERENCES `expense_articles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Статьи затрат';

-- Счета организаций
DROP TABLE IF EXISTS `bank_accounts`;

CREATE TABLE `bank_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_holder` VARCHAR(200) NOT NULL COMMENT 'Владелец счета',
  `account_number` VARCHAR(50) NOT NULL COMMENT 'Номер счета',
  `bank_name` VARCHAR(200) NOT NULL COMMENT 'Название банка',
  `bank_bic` VARCHAR(50) COMMENT 'БИК банка',
  `currency` VARCHAR(3) DEFAULT 'BYN' COMMENT 'Валюта счета',
  `balance` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Текущий баланс',
  `account_type` ENUM('checking', 'savings', 'cash') DEFAULT 'checking' COMMENT 'Тип счета',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_account_number (account_number),
  INDEX idx_account_holder (account_holder)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Банковские счета и кассы';

-- ============================================
-- ОСНОВНЫЕ ТАБЛИЦЫ ПЛАТЕЖЕЙ
-- ============================================

-- Платежные документы
DROP TABLE IF EXISTS `payment_documents`;

CREATE TABLE `payment_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Номер документа',
  `document_date` DATE NOT NULL COMMENT 'Дата документа',
  `payment_type_id` INT NOT NULL COMMENT 'Тип платежа',
  `amount` DECIMAL(15,2) NOT NULL COMMENT 'Сумма платежа',
  `currency` VARCHAR(3) DEFAULT 'BYN' COMMENT 'Валюта',
  `vat_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Сумма НДС',
  `vat_rate` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Ставка НДС %',
  
  -- Контрагент
  `contractor_id` INT NULL COMMENT 'Контрагент (покупатель/поставщик)',
  `contractor_account` VARCHAR(200) COMMENT 'Счет контрагента',
  
  -- Банковский счет
  `bank_account_id` INT NOT NULL COMMENT 'Банковский счет проведения',
  
  -- Статья затрат/доходов
  `expense_article_id` INT NULL COMMENT 'Статья затрат (для расходов)',
  
  -- Связь с заказами/материалами
  `order_id` INT NULL COMMENT 'Заказ (для оплаты заказов)',
  `material_receipt_id` INT NULL COMMENT 'Поступление материалов (для оплаты материалов)',
  `production_task_id` INT NULL COMMENT 'Производственное задание (для оплаты услуг)',
  
  -- Статус и проведение
  `status` ENUM('draft', 'pending', 'approved', 'posted', 'cancelled') DEFAULT 'draft' COMMENT 'Статус документа',
  `posted_at` TIMESTAMP NULL COMMENT 'Дата проведения',
  `posted_by` INT NULL COMMENT 'Кто провел',
  
  -- Дополнительная информация
  `description` TEXT COMMENT 'Назначение платежа',
  `payment_purpose` TEXT COMMENT 'Назначение платежа (официальное)',
  `document_reference` VARCHAR(100) COMMENT 'Ссылка на первичный документ (счет, накладная)',
  `attachment_path` VARCHAR(500) COMMENT 'Путь к прикрепленному файлу',
  
  -- Авторство
  `created_by` INT NOT NULL COMMENT 'Создал пользователь',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Внешние ключи
  CONSTRAINT `fk_pd_payment_type` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types`(`id`),
  CONSTRAINT `fk_pd_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pd_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`(`id`),
  CONSTRAINT `fk_pd_expense_article` FOREIGN KEY (`expense_article_id`) REFERENCES `expense_articles`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pd_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pd_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_pd_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  
  INDEX idx_document_date (document_date),
  INDEX idx_status (status),
  INDEX idx_contractor (contractor_id),
  INDEX idx_order (order_id),
  INDEX idx_bank_account (bank_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Платежные документы';

-- Детализация платежей (разбивка по статьям)
DROP TABLE IF EXISTS `payment_document_items`;

CREATE TABLE `payment_document_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_document_id` INT NOT NULL,
  `line_number` INT NOT NULL COMMENT 'Номер строки',
  `expense_article_id` INT NOT NULL COMMENT 'Статья затрат',
  `amount` DECIMAL(15,2) NOT NULL COMMENT 'Сумма по строке',
  `vat_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'НДС по строке',
  `description` TEXT COMMENT 'Комментарий к строке',
  `quantity` DECIMAL(15,3) DEFAULT 1.000 COMMENT 'Количество (если применимо)',
  `unit_price` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Цена за единицу',
  
  CONSTRAINT `fk_pdi_payment_doc` FOREIGN KEY (`payment_document_id`) REFERENCES `payment_documents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pdi_expense_article` FOREIGN KEY (`expense_article_id`) REFERENCES `expense_articles`(`id`),
  
  INDEX idx_payment_doc (payment_document_id),
  INDEX idx_article (expense_article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Строки платежного документа';

-- История изменений статусов платежей
DROP TABLE IF EXISTS `payment_status_history`;

CREATE TABLE `payment_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payment_document_id` INT NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` INT NOT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `comment` TEXT COMMENT 'Комментарий к изменению статуса',
  
  CONSTRAINT `fk_psh_payment_doc` FOREIGN KEY (`payment_document_id`) REFERENCES `payment_documents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psh_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`),
  
  INDEX idx_payment_doc (payment_document_id),
  INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='История изменений статусов платежей';

-- ============================================
-- ВСТАВКА ДАННЫХ В СПРАВОЧНИКИ
-- ============================================

-- Типы платежей (доходы)
INSERT INTO `payment_types` (`code`, `name`, `description`, `type`, `category`) VALUES
('INCOME_ORDER', 'Оплата от покупателей за заказы', 'Поступление средств от клиентов за выполненные заказы', 'income', 'Выручка'),
('INCOME_ADVANCE', 'Авансы полученные', 'Полученные авансы от покупателей', 'income', 'Авансы'),
('INCOME_OTHER', 'Прочие доходы', 'Прочие поступления', 'income', 'Прочее'),
('INCOME_LOAN', 'Займы полученные', 'Полученные займы и кредиты', 'income', 'Финансирование'),
('INCOME_REFUND', 'Возврат от поставщиков', 'Возврат средств от поставщиков', 'income', 'Возвраты');

-- Типы платежей (расходы)
INSERT INTO `payment_types` (`code`, `name`, `description`, `type`, `category`) VALUES
('EXPENSE_MATERIALS', 'Оплата материалов', 'Оплата поставщикам за материалы и сырье', 'expense', 'Материалы'),
('EXPENSE_SERVICES', 'Оплата услуг', 'Оплата сторонних услуг', 'expense', 'Услуги'),
('EXPENSE_SALARY', 'Выплата зарплаты', 'Выплата заработной платы сотрудникам', 'expense', 'ФОТ'),
('EXPENSE_TAXES', 'Налоги и сборы', 'Уплата налогов и обязательных сборов', 'expense', 'Налоги'),
('EXPENSE_UTILITIES', 'Коммунальные услуги', 'Оплата электроэнергии, воды, отопления', 'expense', 'Коммуналка'),
('EXPENSE_RENT', 'Аренда', 'Арендные платежи', 'expense', 'Аренда'),
('EXPENSE_EQUIPMENT', 'Оборудование', 'Покупка и обслуживание оборудования', 'expense', 'Основные средства'),
('EXPENSE_TRANSPORT', 'Транспортные расходы', 'ГСМ, ремонт транспорта, такси', 'expense', 'Транспорт'),
('EXPENSE_OFFICE', 'Канцелярия и хозтовары', 'Покупка офисных принадлежностей', 'expense', 'Офис'),
('EXPENSE_OTHER', 'Прочие расходы', 'Прочие выплаты', 'expense', 'Прочее');

-- Статьи затрат
INSERT INTO `expense_articles` (`code`, `name`, `description`, `parent_id`, `sort_order`) VALUES
('MAT_MAIN', 'Основные материалы', 'Сырье и основные материалы для производства', NULL, 1),
('MAT_AUX', 'Вспомогательные материалы', 'Вспомогательные материалы', NULL, 2),
('MAT_PACK', 'Упаковка', 'Упаковочные материалы', NULL, 3),
('SERV_PROD', 'Производственные услуги', 'Услуги сторонних организаций по производству', NULL, 4),
('SERV_TRANS', 'Транспортные услуги', 'Доставка, перевозка грузов', NULL, 5),
('SERV_OTHER', 'Прочие услуги', 'Прочие сторонние услуги', NULL, 6),
('TAX_VAT', 'НДС', 'Налог на добавленную стоимость', NULL, 7),
('TAX_INCOME', 'Налог на прибыль', 'Налог на прибыль организаций', NULL, 8),
('TAX_OTHER', 'Прочие налоги', 'Прочие налоги и сборы', NULL, 9),
('SALARY_MAIN', 'Основная зарплата', 'Основная заработная плата', NULL, 10),
('SALARY_BONUS', 'Премии и бонусы', 'Премиальные выплаты', NULL, 11),
('UTIL_ELEC', 'Электроэнергия', 'Оплата электроэнергии', NULL, 12),
('UTIL_WATER', 'Водоснабжение', 'Оплата водоснабжения', NULL, 13),
('RENT_OFFICE', 'Аренда офиса', 'Аренда офисных помещений', NULL, 14),
('RENT_WAREHOUSE', 'Аренда склада', 'Аренда складских помещений', NULL, 15);

-- Банковские счета (примеры)
INSERT INTO `bank_accounts` (`account_holder`, `account_number`, `bank_name`, `bank_bic`, `currency`, `balance`, `account_type`) VALUES
('ОАО "Полесьеэлектромаш"', 'BY12AKBB30120000000001234567', 'ОАО "АСБ Беларусбанк"', 'AKBBBY2X', 'BYN', 150000.00, 'checking'),
('ОАО "Полесьеэлектромаш"', 'BY12AKBB30120000000001234568', 'ОАО "АСБ Беларусбанк"', 'AKBBBY2X', 'USD', 5000.00, 'checking'),
('ОАО "Полесьеэлектромаш"', 'Касса', 'Главная касса', NULL, 'BYN', 5000.00, 'cash');

-- ============================================
-- ПРЕДСТАВЛЕНИЯ ДЛЯ ОТЧЕТНОСТИ
-- ============================================

DROP VIEW IF EXISTS `v_payments_summary`;

CREATE VIEW `v_payments_summary` AS
SELECT 
    DATE_FORMAT(pd.document_date, '%Y-%m') as period,
    pt.type as payment_type,
    pt.category,
    COUNT(pd.id) as documents_count,
    SUM(pd.amount) as total_amount,
    SUM(pd.vat_amount) as total_vat
FROM payment_documents pd
JOIN payment_types pt ON pd.payment_type_id = pt.id
WHERE pd.status = 'posted' AND pd.is_cancelled != TRUE
GROUP BY DATE_FORMAT(pd.document_date, '%Y-%m'), pt.type, pt.category
ORDER BY period DESC, pt.type, pt.category;

DROP VIEW IF EXISTS `v_payments_by_contractor`;

CREATE VIEW `v_payments_by_contractor` AS
SELECT 
    c.id as contractor_id,
    c.name as contractor_name,
    c.inn as contractor_inn,
    pt.type as payment_type,
    COUNT(pd.id) as payments_count,
    SUM(pd.amount) as total_amount,
    MIN(pd.document_date) as first_payment,
    MAX(pd.document_date) as last_payment
FROM payment_documents pd
JOIN contractors c ON pd.contractor_id = c.id
JOIN payment_types pt ON pd.payment_type_id = pt.id
WHERE pd.status = 'posted' AND pd.is_cancelled != TRUE
GROUP BY c.id, c.name, c.inn, pt.type
ORDER BY total_amount DESC;

DROP VIEW IF EXISTS `v_cash_flow`;

CREATE VIEW `v_cash_flow` AS
SELECT 
    pd.document_date,
    pd.document_number,
    pt.name as payment_type_name,
    pt.type as flow_type,
    CASE WHEN pt.type = 'income' THEN pd.amount ELSE 0 END as income_amount,
    CASE WHEN pt.type = 'expense' THEN pd.amount ELSE 0 END as expense_amount,
    pd.amount as transaction_amount,
    c.name as contractor_name,
    ba.account_number,
    pd.description
FROM payment_documents pd
JOIN payment_types pt ON pd.payment_type_id = pt.id
LEFT JOIN contractors c ON pd.contractor_id = c.id
JOIN bank_accounts ba ON pd.bank_account_id = ba.id
WHERE pd.status = 'posted' AND pd.is_cancelled != TRUE
ORDER BY pd.document_date DESC, pd.document_number DESC;

-- ============================================
-- ФУНКЦИИ ДЛЯ ПРОВЕДЕНИЯ ПЛАТЕЖЕЙ
-- ============================================

DELIMITER //

-- Процедура проведения платежа
CREATE PROCEDURE `post_payment`(
    IN p_payment_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_status VARCHAR(50);
    DECLARE v_amount DECIMAL(15,2);
    DECLARE v_payment_type VARCHAR(50);
    DECLARE v_bank_account_id INT;
    
    -- Получаем данные платежа
    SELECT pd.status, pd.amount, pt.type, pd.bank_account_id
    INTO v_status, v_amount, v_payment_type, v_bank_account_id
    FROM payment_documents pd
    JOIN payment_types pt ON pd.payment_type_id = pt.id
    WHERE pd.id = p_payment_id;
    
    -- Проверка статуса
    IF v_status != 'approved' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Можно проводить только утвержденные платежи';
    END IF;
    
    -- Обновляем статус
    UPDATE payment_documents 
    SET status = 'posted', 
        posted_at = NOW(), 
        posted_by = p_user_id
    WHERE id = p_payment_id;
    
    -- Обновляем баланс счета
    IF v_payment_type = 'income' THEN
        UPDATE bank_accounts 
        SET balance = balance + v_amount 
        WHERE id = v_bank_account_id;
    ELSE
        UPDATE bank_accounts 
        SET balance = balance - v_amount 
        WHERE id = v_bank_account_id;
    END IF;
    
    -- Записываем в историю
    INSERT INTO payment_status_history (payment_document_id, old_status, new_status, changed_by, comment)
    VALUES (p_payment_id, v_status, 'posted', p_user_id, 'Платеж проведен');
    
END//

-- Процедура отмены проведения платежа
CREATE PROCEDURE `unpost_payment`(
    IN p_payment_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_status VARCHAR(50);
    DECLARE v_amount DECIMAL(15,2);
    DECLARE v_payment_type VARCHAR(50);
    DECLARE v_bank_account_id INT;
    
    -- Получаем данные платежа
    SELECT pd.status, pd.amount, pt.type, pd.bank_account_id
    INTO v_status, v_amount, v_payment_type, v_bank_account_id
    FROM payment_documents pd
    JOIN payment_types pt ON pd.payment_type_id = pt.id
    WHERE pd.id = p_payment_id;
    
    -- Проверка статуса
    IF v_status != 'posted' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Можно отменить только проведенные платежи';
    END IF;
    
    -- Обновляем статус
    UPDATE payment_documents 
    SET status = 'draft', 
        posted_at = NULL, 
        posted_by = NULL
    WHERE id = p_payment_id;
    
    -- Возвращаем баланс счета
    IF v_payment_type = 'income' THEN
        UPDATE bank_accounts 
        SET balance = balance - v_amount 
        WHERE id = v_bank_account_id;
    ELSE
        UPDATE bank_accounts 
        SET balance = balance + v_amount 
        WHERE id = v_bank_account_id;
    END IF;
    
    -- Записываем в историю
    INSERT INTO payment_status_history (payment_document_id, old_status, new_status, changed_by, comment)
    VALUES (p_payment_id, v_status, 'draft', p_user_id, 'Проведение отменено');
    
END//

DELIMITER ;

-- ============================================
-- ОБНОВЛЕНИЕ ПРАВ ДОСТУПА ДЛЯ БУХГАЛТЕРА
-- ============================================

-- Добавляем модуль финансов в права доступа
UPDATE `user_roles` 
SET permissions = JSON_SET(permissions, '$.финансы', JSON_ARRAY('view', 'create', 'edit', 'delete'))
WHERE code = 'accountant';

-- Добавляем права для модуля finance в role_module_permissions
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT id, 'finance', TRUE, TRUE, TRUE, TRUE
FROM user_roles 
WHERE code = 'accountant'
ON DUPLICATE KEY UPDATE 
    can_view = TRUE, 
    can_create = TRUE, 
    can_edit = TRUE, 
    can_delete = TRUE;

-- Администратор имеет полный доступ к финансам
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT id, 'finance', TRUE, TRUE, TRUE, TRUE
FROM user_roles 
WHERE code = 'admin'
ON DUPLICATE KEY UPDATE 
    can_view = TRUE, 
    can_create = TRUE, 
    can_edit = TRUE, 
    can_delete = TRUE;

-- Директор может просматривать финансы
INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`)
SELECT id, 'finance', TRUE, FALSE, FALSE, FALSE
FROM user_roles 
WHERE code = 'director'
ON DUPLICATE KEY UPDATE can_view = TRUE;

-- ============================================
-- ВОЗВРАЩАЕМ ПРОВЕРКИ ВНЕШНИХ КЛЮЧЕЙ
-- ============================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ПРИМЕРЫ ЗАПРОСОВ
-- ============================================

-- Получить все платежи за текущий месяц
-- SELECT * FROM payment_documents 
-- WHERE DATE_FORMAT(document_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
-- ORDER BY document_date DESC;

-- Оборотно-сальдовая ведомость по контрагентам
-- SELECT * FROM v_payments_by_contractor;

-- Движение денежных средств
-- SELECT * FROM v_cash_flow WHERE document_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);
