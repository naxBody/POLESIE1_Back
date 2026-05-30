-- Добавление колонки notes в таблицу contractors
ALTER TABLE `contractors` ADD COLUMN `notes` TEXT AFTER `address`;

-- Добавление колонки updated_at в таблицу contractors (используется в edit.php)
ALTER TABLE `contractors` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
