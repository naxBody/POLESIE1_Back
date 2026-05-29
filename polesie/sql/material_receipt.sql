-- ============================================
-- ДОКУМЕНТЫ ПОСТУПЛЕНИЯ МАТЕРИАЛОВ (ТТН, Накладные)
-- Добавлено для учета поступления материалов
-- ============================================

-- Таблица типов документов поступления
CREATE TABLE IF NOT EXISTS `receipt_document_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `name_ru` VARCHAR(100),
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица документов поступления материалов
CREATE TABLE IF NOT EXISTS `material_receipt_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_number` VARCHAR(50) NOT NULL UNIQUE,
  `document_type_id` INT,
  `document_date` DATE NOT NULL,
  `supplier_id` INT,
  `supplier_invoice_number` VARCHAR(100),
  `supplier_invoice_date` DATE,
  `ttn_number` VARCHAR(100),
  `total_amount` DECIMAL(15,2) DEFAULT 0,
  `currency` CHAR(3) DEFAULT 'BYN',
  `status` ENUM('draft', 'posted', 'cancelled') DEFAULT 'draft',
  `notes` TEXT,
  `attachments` JSON COMMENT 'Прикрепленные файлы (сканы документов)',
  `created_by` INT,
  `posted_by` INT,
  `posted_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mrd_type` FOREIGN KEY (`document_type_id`) REFERENCES `receipt_document_types`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mrd_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contractors`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mrd_created` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mrd_posted` FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица позиций документа поступления
CREATE TABLE IF NOT EXISTS `material_receipt_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_document_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `row_number` INT DEFAULT 0,
  `quantity` DECIMAL(15,3) NOT NULL,
  `unit_price` DECIMAL(15,2),
  `total_price` DECIMAL(15,2),
  `batch_number` VARCHAR(100) COMMENT 'Номер партии',
  `manufacturer` VARCHAR(200) COMMENT 'Производитель',
  `certificate_number` VARCHAR(100) COMMENT 'Номер сертификата',
  `certificate_date` DATE COMMENT 'Дата сертификата',
  `expiry_date` DATE COMMENT 'Срок годности',
  `storage_location` VARCHAR(100) COMMENT 'Место хранения',
  `notes` TEXT,
  CONSTRAINT `fk_mri_receipt` FOREIGN KEY (`receipt_document_id`) REFERENCES `material_receipt_documents`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mri_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица складских операций (движение материалов)
CREATE TABLE IF NOT EXISTS `warehouse_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_type` ENUM('receipt', 'issue', 'transfer', 'adjustment', 'write_off') NOT NULL,
  `document_type` VARCHAR(50) COMMENT 'Тип документа-основания',
  `document_id` INT COMMENT 'ID документа-основания',
  `material_id` INT NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL COMMENT 'Количество (положительное - приход, отрицательное - расход)',
  `unit_id` INT,
  `price` DECIMAL(15,2) COMMENT 'Цена за единицу',
  `total_amount` DECIMAL(15,2) COMMENT 'Общая сумма',
  `batch_number` VARCHAR(100) COMMENT 'Номер партии',
  `from_location` VARCHAR(100) COMMENT 'Откуда',
  `to_location` VARCHAR(100) COMMENT 'Куда',
  `previous_stock` DECIMAL(15,3) COMMENT 'Остаток до операции',
  `new_stock` DECIMAL(15,3) COMMENT 'Остаток после операции',
  `notes` TEXT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_wt_material` FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wt_unit` FOREIGN KEY (`unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_wt_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Индексы для ускорения поиска
CREATE INDEX IF NOT EXISTS `idx_mrd_document_number` ON `material_receipt_documents`(document_number);
CREATE INDEX IF NOT EXISTS `idx_mrd_document_date` ON `material_receipt_documents`(document_date);
CREATE INDEX IF NOT EXISTS `idx_mrd_supplier` ON `material_receipt_documents`(supplier_id);
CREATE INDEX IF NOT EXISTS `idx_mrd_status` ON `material_receipt_documents`(status);
CREATE INDEX IF NOT EXISTS `idx_mri_receipt` ON `material_receipt_items`(receipt_document_id);
CREATE INDEX IF NOT EXISTS `idx_mri_material` ON `material_receipt_items`(material_id);
CREATE INDEX IF NOT EXISTS `idx_wt_material` ON `warehouse_transactions`(material_id);
CREATE INDEX IF NOT EXISTS `idx_wt_document` ON `warehouse_transactions`(document_type, document_id);
CREATE INDEX IF NOT EXISTS `idx_wt_created_at` ON `warehouse_transactions`(created_at);

-- Заполнение типов документов
INSERT INTO `receipt_document_types` (`name`, `name_ru`, `code`, `description`, `sort_order`) VALUES
('Товарно-транспортная накладная', 'ТТН', 'TTN', 'Товарно-транспортная накладная', 1),
('Накладная', 'Накладная', 'INVOICE', 'Товарная накладная', 2),
('Акт приема-передачи', 'Акт', 'ACT', 'Акт приема-передачи материалов', 3),
('Счет-фактура', 'Счет-фактура', 'INVOICE_COUNT', 'Счет-фактура', 4),
('Сертификат соответствия', 'Сертификат', 'CERTIFICATE', 'Сертификат соответствия/качества', 5),
('Паспорт качества', 'Паспорт', 'QUALITY_PASSPORT', 'Паспорт качества материала', 6),
('Другое', 'Другое', 'OTHER', 'Прочие документы', 99)
ON DUPLICATE KEY UPDATE name=name;
