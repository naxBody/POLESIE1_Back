-- ============================================
-- МОДУЛЬ ПЛАТЕЖЕЙ И ФИНАНСОВ
-- Полная реализация платежного функционала
-- ОАО "Полесьеэлектромаш"
-- ============================================

USE `polesie_production`;

-- Отключаем проверки внешних ключей для безопасного обновления
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- УДАЛЕНИЕ ТАБЛИЦ В ПРАВИЛЬНОМ ПОРЯДКЕ
-- Сначала зависимые, потом справочники
-- ============================================

-- Удаляем представления
DROP VIEW IF EXISTS `v_payments_summary`;
DROP VIEW IF EXISTS `v_payments_by_contractor`;
DROP VIEW IF EXISTS `v_cash_flow`;

-- Удаляем основные таблицы платежей (зависимые)
DROP TABLE IF EXISTS `payment_status_history`;
DROP TABLE IF EXISTS `payment_document_items`;
DROP TABLE IF EXISTS `payment_documents`;

-- Удаляем справочники
DROP TABLE IF EXISTS `expense_articles`;
DROP TABLE IF EXISTS `bank_accounts`;
DROP TABLE IF EXISTS `payment_types`;

-- Включаем проверки внешних ключей обратно
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- СПРАВОЧНИКИ ДЛЯ ПЛАТЕЖЕЙ
-- ============================================

-- Типы платежей
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
WHERE pd.status = 'posted'
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
WHERE pd.status = 'posted'
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
WHERE pd.status = 'posted'
ORDER BY pd.document_date DESC, pd.document_number DESC;

-- ============================================
-- ФУНКЦИИ ДЛЯ ПРОВЕДЕНИЯ ПЛАТЕЖЕЙ
-- ============================================

DELIMITER //

-- Удаляем существующие процедуры перед созданием
DROP PROCEDURE IF EXISTS `post_payment`//
DROP PROCEDURE IF EXISTS `unpost_payment`//
DROP PROCEDURE IF EXISTS `change_payment_status`//

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

-- Добавляем права для модуля finance (если таблица существует)
-- Проверяем существование таблицы перед вставкой
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_schema = 'polesie_production' 
                     AND table_name = 'role_module_permissions');

SET @sql = IF(@table_exists > 0,
  'INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`)
   SELECT id, ''finance'', TRUE, TRUE, TRUE, TRUE
   FROM user_roles 
   WHERE code = ''admin''
   ON DUPLICATE KEY UPDATE 
       can_view = TRUE, 
       can_create = TRUE, 
       can_edit = TRUE, 
       can_delete = TRUE;',
  'SELECT ''Table role_module_permissions does not exist, skipping...'' as message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Бухгалтер имеет полный доступ к финансам
SET @sql = IF(@table_exists > 0,
  'INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`)
   SELECT id, ''finance'', TRUE, TRUE, TRUE, TRUE
   FROM user_roles 
   WHERE code = ''accountant''
   ON DUPLICATE KEY UPDATE 
       can_view = TRUE, 
       can_create = TRUE, 
       can_edit = TRUE, 
       can_delete = TRUE;',
  'SELECT ''Table role_module_permissions does not exist, skipping...'' as message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Директор может просматривать финансы
SET @sql = IF(@table_exists > 0,
  'INSERT INTO `role_module_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`)
   SELECT id, ''finance'', TRUE, FALSE, FALSE, FALSE
   FROM user_roles 
   WHERE code = ''director''
   ON DUPLICATE KEY UPDATE can_view = TRUE;',
  'SELECT ''Table role_module_permissions does not exist, skipping...'' as message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

-- ============================================
-- ПРИМЕРЫ ПЛАТЕЖЕЙ (ДЕМОНСТРАЦИОННЫЕ ДАННЫЕ)
-- ============================================

-- Сначала проверим и создадим тестовых контрагентов если их нет
INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ООО \"Поставщик материалов\"', '100123456', 'supplier'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100123456');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ООО \"Торговый партнер\"', '100234567', 'customer'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100234567');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ЗАО \"СтройКомплект\"', '100345678', 'supplier'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100345678');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ОАО \"ЭнергоСбыт\"', '100456789', 'supplier'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100456789');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ЧУП \"ТрансЛогистик\"', '100567890', 'supplier'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100567890');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ООО \"АрендаПлюс\"', '100678901', 'supplier'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100678901');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ИП \"СервисПро\"', '100789012', 'supplier'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100789012');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ООО \"ОфисСнаб\"', '100890123', 'supplier'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100890123');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ЗАО \"БелПромЗаказ\"', '100901234', 'customer'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '100901234');

INSERT INTO `contractors` (`name`, `inn`, `type`) 
SELECT 'ООО \"АвансТрейд\"', '101012345', 'customer'
WHERE NOT EXISTS (SELECT 1 FROM contractors WHERE inn = '101012345');

-- Получаем ID созданных контрагентов для использования в платежах
SET @contractor_supplier_1 = (SELECT id FROM contractors WHERE inn = '100123456' LIMIT 1);
SET @contractor_customer_1 = (SELECT id FROM contractors WHERE inn = '100234567' LIMIT 1);
SET @contractor_supplier_2 = (SELECT id FROM contractors WHERE inn = '100345678' LIMIT 1);
SET @contractor_supplier_3 = (SELECT id FROM contractors WHERE inn = '100456789' LIMIT 1);
SET @contractor_supplier_4 = (SELECT id FROM contractors WHERE inn = '100567890' LIMIT 1);
SET @contractor_supplier_5 = (SELECT id FROM contractors WHERE inn = '100678901' LIMIT 1);
SET @contractor_supplier_6 = (SELECT id FROM contractors WHERE inn = '100789012' LIMIT 1);
SET @contractor_supplier_7 = (SELECT id FROM contractors WHERE inn = '100890123' LIMIT 1);
SET @contractor_customer_2 = (SELECT id FROM contractors WHERE inn = '100901234' LIMIT 1);
SET @contractor_customer_3 = (SELECT id FROM contractors WHERE inn = '101012345' LIMIT 1);

-- Получаем ID других справочников
SET @bank_byn = (SELECT id FROM bank_accounts WHERE currency='BYN' AND account_type='checking' LIMIT 1);
SET @bank_usd = (SELECT id FROM bank_accounts WHERE currency='USD' LIMIT 1);
SET @bank_cash = (SELECT id FROM bank_accounts WHERE account_type='cash' LIMIT 1);

SET @pt_income_order = (SELECT id FROM payment_types WHERE code='INCOME_ORDER' LIMIT 1);
SET @pt_income_advance = (SELECT id FROM payment_types WHERE code='INCOME_ADVANCE' LIMIT 1);
SET @pt_income_other = (SELECT id FROM payment_types WHERE code='INCOME_OTHER' LIMIT 1);
SET @pt_income_loan = (SELECT id FROM payment_types WHERE code='INCOME_LOAN' LIMIT 1);
SET @pt_expense_materials = (SELECT id FROM payment_types WHERE code='EXPENSE_MATERIALS' LIMIT 1);
SET @pt_expense_services = (SELECT id FROM payment_types WHERE code='EXPENSE_SERVICES' LIMIT 1);
SET @pt_expense_utilities = (SELECT id FROM payment_types WHERE code='EXPENSE_UTILITIES' LIMIT 1);
SET @pt_expense_salary = (SELECT id FROM payment_types WHERE code='EXPENSE_SALARY' LIMIT 1);
SET @pt_expense_taxes = (SELECT id FROM payment_types WHERE code='EXPENSE_TAXES' LIMIT 1);
SET @pt_expense_rent = (SELECT id FROM payment_types WHERE code='EXPENSE_RENT' LIMIT 1);
SET @pt_expense_transport = (SELECT id FROM payment_types WHERE code='EXPENSE_TRANSPORT' LIMIT 1);
SET @pt_expense_office = (SELECT id FROM payment_types WHERE code='EXPENSE_OFFICE' LIMIT 1);

SET @ea_mat_main = (SELECT id FROM expense_articles WHERE code='MAT_MAIN' LIMIT 1);
SET @ea_serv_prod = (SELECT id FROM expense_articles WHERE code='SERV_PROD' LIMIT 1);
SET @ea_util_elec = (SELECT id FROM expense_articles WHERE code='UTIL_ELEC' LIMIT 1);
SET @ea_salary_main = (SELECT id FROM expense_articles WHERE code='SALARY_MAIN' LIMIT 1);
SET @ea_tax_income = (SELECT id FROM expense_articles WHERE code='TAX_INCOME' LIMIT 1);
SET @ea_rent_warehouse = (SELECT id FROM expense_articles WHERE code='RENT_WAREHOUSE' LIMIT 1);
SET @ea_serv_trans = (SELECT id FROM expense_articles WHERE code='SERV_TRANS' LIMIT 1);
SET @ea_mat_aux = (SELECT id FROM expense_articles WHERE code='MAT_AUX' LIMIT 1);

-- Примеры платежей (доходы)
INSERT INTO `payment_documents` (`document_number`, `document_date`, `payment_type_id`, `amount`, `currency`, `vat_amount`, `vat_rate`, `contractor_id`, `bank_account_id`, `expense_article_id`, `description`, `payment_purpose`, `status`, `created_by`) VALUES
('PAY202501010001', '2025-01-05', @pt_income_order, 150000.00, 'BYN', 30000.00, 20.00, @contractor_customer_2, @bank_byn, NULL, 'Оплата за заказ №001', 'Оплата по договору поставки №1 от 15.12.2024', 'posted', 1),
('PAY202501010002', '2025-01-10', @pt_income_advance, 50000.00, 'BYN', 10000.00, 20.00, @contractor_customer_3, @bank_byn, NULL, 'Аванс за заказ №005', 'Авансовый платеж по договору №5 от 20.12.2024', 'posted', 1),
('PAY202501010003', '2025-01-15', @pt_income_other, 25000.00, 'BYN', 0, 0, NULL, @bank_byn, NULL, 'Прочие поступления', 'Возврат подотчетных средств', 'posted', 1),
('PAY202501010004', '2025-01-20', @pt_income_order, 200000.00, 'BYN', 40000.00, 20.00, @contractor_customer_1, @bank_byn, NULL, 'Оплата за крупный заказ', 'Оплата по договору №10 от 05.01.2025', 'posted', 1),
('PAY202501010005', '2025-01-25', @pt_income_loan, 100000.00, 'BYN', 0, 0, NULL, @bank_byn, NULL, 'Краткосрочный займ', 'Поступление займа от банка', 'posted', 1);

-- Примеры платежей (расходы)
INSERT INTO `payment_documents` (`document_number`, `document_date`, `payment_type_id`, `amount`, `currency`, `vat_amount`, `vat_rate`, `contractor_id`, `bank_account_id`, `expense_article_id`, `description`, `payment_purpose`, `status`, `created_by`) VALUES
('PAY202501020001', '2025-01-08', @pt_expense_materials, 75000.00, 'BYN', 15000.00, 20.00, @contractor_supplier_1, @bank_byn, @ea_mat_main, 'Оплата материалов от поставщика', 'Оплата по счету №123 от 05.01.2025 за материалы', 'posted', 1),
('PAY202501020002', '2025-01-12', @pt_expense_services, 30000.00, 'BYN', 6000.00, 20.00, @contractor_supplier_2, @bank_byn, @ea_serv_prod, 'Производственные услуги', 'Оплата услуг по договору подряда №15 от 10.01.2025', 'posted', 1),
('PAY202501020003', '2025-01-15', @pt_expense_utilities, 12000.00, 'BYN', 2400.00, 20.00, @contractor_supplier_3, @bank_byn, @ea_util_elec, 'Электроэнергия за январь 2025', 'Оплата электроэнергии по договору №Э-2024', 'posted', 1),
('PAY202501020004', '2025-01-18', @pt_expense_salary, 45000.00, 'BYN', 0, 0, NULL, @bank_cash, @ea_salary_main, 'Заработная плата за январь 2025', 'Выплата заработной платы сотрудникам', 'posted', 1),
('PAY202501020005', '2025-01-20', @pt_expense_taxes, 18000.00, 'BYN', 0, 0, NULL, @bank_byn, @ea_tax_income, 'Налог на прибыль за Q4 2024', 'Уплата налога на прибыль организаций', 'posted', 1),
('PAY202501020006', '2025-01-22', @pt_expense_rent, 8000.00, 'BYN', 1600.00, 20.00, @contractor_supplier_5, @bank_byn, @ea_rent_warehouse, 'Аренда склада за январь 2025', 'Оплата аренды складского помещения по договору №А-2024', 'approved', 1),
('PAY202501020007', '2025-01-25', @pt_expense_transport, 5000.00, 'BYN', 1000.00, 20.00, @contractor_supplier_4, @bank_byn, @ea_serv_trans, 'Транспортные услуги', 'Оплата доставки грузов по договору №Т-2025', 'pending', 1),
('PAY202501020008', '2025-01-28', @pt_expense_office, 3500.00, 'BYN', 700.00, 20.00, @contractor_supplier_7, @bank_byn, @ea_mat_aux, 'Канцелярские товары', 'Оплата канцелярских товаров по счету №456', 'draft', 1),
('PAY202501020009', '2025-01-30', @pt_expense_materials, 95000.00, 'BYN', 19000.00, 20.00, @contractor_supplier_1, @bank_byn, @ea_mat_main, 'Оплата второй партии материалов', 'Оплата по договору №М-2025 от 25.01.2025', 'approved', 1),
('PAY202501020010', '2025-02-01', @pt_expense_services, 15000.00, 'BYN', 3000.00, 20.00, @contractor_supplier_6, @bank_byn, @ea_serv_prod, 'Консультационные услуги', 'Оплата консультационных услуг', 'draft', 1);

-- Платежи в валюте (USD)
INSERT INTO `payment_documents` (`document_number`, `document_date`, `payment_type_id`, `amount`, `currency`, `vat_amount`, `vat_rate`, `contractor_id`, `bank_account_id`, `expense_article_id`, `description`, `payment_purpose`, `status`, `created_by`) VALUES
('PAY202501030001', '2025-01-15', @pt_expense_materials, 5000.00, 'USD', 0, 0, @contractor_supplier_2, @bank_usd, @ea_mat_main, 'Импорт материалов', 'Оплата импортного контракта №И-2025', 'posted', 1),
('PAY202501030002', '2025-01-20', @pt_income_order, 8000.00, 'USD', 0, 0, @contractor_customer_1, @bank_usd, NULL, 'Экспорт продукции', 'Оплата экспортного контракта №Э-2025', 'posted', 1);
