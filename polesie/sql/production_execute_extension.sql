-- Расширение для модуля исполнения производства
-- ОАО "Полесьеэлектромаш"

-- Таблица движений материалов (если еще не создана)
DROP TABLE IF EXISTS `material_movements`;
CREATE TABLE `material_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `material_id` INT NOT NULL,
  `movement_type` ENUM('receipt', 'consumption', 'adjustment', 'return', 'transfer') NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `reference_type` VARCHAR(50),
  `reference_id` INT,
  `user_id` INT,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mm_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_mm_material` (`material_id`),
  INDEX `idx_mm_type` (`movement_type`),
  INDEX `idx_mm_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Обновление прав доступа для роли "Рабочий" - добавляем право на исполнение производства
UPDATE `user_roles` 
SET `permissions` = '{"производство":["read","execute"],"задания":["read","update"]}'
WHERE `code` = 'worker';

-- Добавляем право на исполнение производства для технолога
UPDATE `user_roles` 
SET `permissions` = '{"производство":["read","create","edit","execute"],"материалы":["read","update"]}'
WHERE `code` = 'technologist';

