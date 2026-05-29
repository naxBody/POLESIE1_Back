-- ============================================
-- ЗАКАЗЫ И ПРОИЗВОДСТВЕННЫЕ ЗАДАНИЯ
-- Реалистичные данные для тестирования плана выпуска
-- Версия с прямыми ID продуктов (без подзапросов)
-- ============================================

-- Очистка существующих данных перед вставкой (чтобы избежать дублирования)
DELETE FROM `production_tasks`;
DELETE FROM `order_items`;
DELETE FROM `orders`;

-- Сброс автоинкремента для корректной нумерации
ALTER TABLE `orders` AUTO_INCREMENT = 1;
ALTER TABLE `order_items` AUTO_INCREMENT = 1;
ALTER TABLE `production_tasks` AUTO_INCREMENT = 1;

-- ============================================
-- 1. ЗАКАЗЫ (orders)
-- ============================================

INSERT INTO `orders` (`order_number`, `customer_id`, `responsible_user_id`, `status`, `order_date`, `delivery_date`, `total_amount`, `notes`) VALUES
('ORD-2025-001', 4, 3, 'processing', '2025-01-15', '2025-02-15', 15750.00, 'Срочный заказ для стройки'),
('ORD-2025-002', 5, 3, 'new', '2025-01-20', '2025-02-28', 8900.00, 'Заказ для агрокомплекса'),
('ORD-2025-003', 4, 4, 'processing', '2025-01-10', '2025-02-10', 25600.00, 'Поставка насосного оборудования'),
('ORD-2025-004', 5, 3, 'ready', '2025-01-05', '2025-02-05', 12300.00, 'Готов к отгрузке'),
('ORD-2025-005', 4, 4, 'shipped', '2024-12-20', '2025-01-20', 45000.00, 'Отгружен транспортом'),
('ORD-2025-006', 5, 3, 'processing', '2025-01-25', '2025-03-01', 18700.00, 'Заказ с особыми требованиями'),
('ORD-2025-007', 4, 4, 'new', '2025-01-28', '2025-03-15', 32100.00, 'Крупный заказ двигателей'),
('ORD-2025-008', 5, 3, 'processing', '2025-01-12', '2025-02-20', 9800.00, 'Стандартная поставка'),
('ORD-2025-009', 4, 4, 'cancelled', '2025-01-08', '2025-02-08', 5600.00, 'Отменен по просьбе клиента'),
('ORD-2025-010', 5, 3, 'processing', '2025-01-18', '2025-02-25', 21400.00, 'Приоритетный заказ');

-- ============================================
-- 2. ПОЗИЦИИ ЗАКАЗОВ (order_items)
-- ============================================
-- Примечание: ID продуктов соответствуют порядку вставки в database_updated.sql

-- Заказ ORD-2025-001 (двигатели для стройки)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(1, 7, 10, 385.70, 3857.00, 'in_progress'),
(1, 8, 5, 388.36, 1941.80, 'in_progress'),
(1, 13, 8, 566.50, 4532.00, 'completed'),
(1, 20, 7, 707.20, 4950.40, 'not_started'),
(1, 79, 10, 185.00, 1850.00, 'packed');

-- Заказ ORD-2025-002 (насосы для агро)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(2, 80, 15, 420.00, 6300.00, 'not_started'),
(2, 3, 10, 276.51, 2765.10, 'not_started'),
(2, 4, 5, 277.06, 1385.30, 'not_started');

-- Заказ ORD-2025-003 (насосное оборудование)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(3, 15, 12, 531.44, 6377.28, 'in_progress'),
(3, 22, 8, 676.00, 5408.00, 'in_progress'),
(3, 26, 6, 928.40, 5570.40, 'completed'),
(3, 79, 20, 185.00, 3700.00, 'packed'),
(3, 80, 10, 420.00, 4200.00, 'packed');

-- Заказ ORD-2025-004 (готовый заказ)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(4, 9, 15, 353.85, 5307.75, 'packed'),
(4, 10, 10, 355.25, 3552.50, 'packed'),
(4, 17, 5, 588.70, 2943.50, 'packed'),
(4, 75, 100, 45.00, 4500.00, 'packed');

-- Заказ ORD-2025-005 (отгруженный)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(5, 25, 15, 989.00, 14835.00, 'packed'),
(5, 28, 10, 1060.80, 10608.00, 'packed'),
(5, 67, 20, 277.75, 5555.00, 'packed'),
(5, 68, 15, 277.75, 4166.25, 'packed'),
(5, 31, 20, 284.20, 5684.00, 'packed'),
(5, 35, 15, 288.40, 4326.00, 'packed');

-- Заказ ORD-2025-006 (особые требования)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(6, 59, 15, 285.32, 4279.80, 'in_progress'),
(6, 60, 10, 287.00, 2870.00, 'in_progress'),
(6, 63, 8, 289.80, 2318.40, 'not_started'),
(6, 65, 10, 293.44, 2934.40, 'not_started'),
(6, 48, 20, 284.20, 5684.00, 'not_started'),
(6, 51, 15, 288.40, 4326.00, 'not_started');

-- Заказ ORD-2025-007 (крупный заказ)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(7, 24, 25, 717.40, 17935.00, 'not_started'),
(7, 26, 20, 928.40, 18568.00, 'not_started'),
(7, 39, 15, 295.40, 4431.00, 'not_started'),
(7, 41, 10, 286.00, 2860.00, 'not_started');

-- Заказ ORD-2025-008 (стандартная поставка)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(8, 5, 10, 291.07, 2910.70, 'in_progress'),
(8, 6, 8, 291.60, 2332.80, 'in_progress'),
(8, 11, 6, 392.93, 2357.58, 'completed'),
(8, 12, 5, 394.29, 1971.45, 'packed'),
(8, 75, 50, 45.00, 2250.00, 'packed');

-- Заказ ORD-2025-009 (отмененный)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(9, 69, 10, 281.54, 2815.40, 'not_started'),
(9, 70, 10, 282.10, 2821.00, 'not_started');

-- Заказ ORD-2025-010 (приоритетный)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(10, 14, 10, 572.00, 5720.00, 'in_progress'),
(10, 16, 8, 535.60, 4284.80, 'in_progress'),
(10, 21, 6, 669.50, 4017.00, 'not_started'),
(10, 32, 15, 286.16, 4292.40, 'not_started'),
(10, 36, 10, 281.05, 2810.50, 'not_started');

