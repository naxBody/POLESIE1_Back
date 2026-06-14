-- Добавление полей contract_number и contract_date в таблицу orders
ALTER TABLE `orders` 
ADD COLUMN `contract_number` VARCHAR(100) DEFAULT NULL AFTER `order_number`,
ADD COLUMN `contract_date` DATE DEFAULT NULL AFTER `contract_number`;
