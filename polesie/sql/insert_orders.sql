-- ============================================
-- ЗAKAЗЫ I ПPOIЗBOДCTBEHHЫE ЗAДAHIЯ
-- Pеалистичные данные для тестирования плана выпуска
-- ============================================

-- Oчистка существующих данных перед вставкой (чтобы избежать дублирования)
-- Oчистка в порядке обратном созданию таблиц (с учетом внешних ключей)
DELETE FROM `product_documents`;
DELETE FROM `product_serial_numbers`;
DELETE FROM `quality_checks`;
DELETE FROM `production_task_stages`;
DELETE FROM `production_tasks_materials`;
DELETE FROM `production_tasks`;
DELETE FROM `order_items`;
DELETE FROM `orders`;

-- Cброс автоинкремента для корректной нумерации
ALTER TABLE `orders` AUTO_INCREMENT = 1;
ALTER TABLE `order_items` AUTO_INCREMENT = 1;
ALTER TABLE `production_tasks` AUTO_INCREMENT = 1;
ALTER TABLE `production_task_stages` AUTO_INCREMENT = 1;
ALTER TABLE `production_tasks_materials` AUTO_INCREMENT = 1;
ALTER TABLE `quality_checks` AUTO_INCREMENT = 1;
ALTER TABLE `product_serial_numbers` AUTO_INCREMENT = 1;
ALTER TABLE `product_documents` AUTO_INCREMENT = 1;

-- ============================================
-- ПOЛYЧEHIE ID ПPOДYKTOB (для использования в INSERT)
-- ============================================
-- Iспользуем переменные для надежного получения ID продуктов

SET @p_AIR71А2 := (SELECT id FROM products WHERE article = 'AIR71А2');
SET @p_AIR71В2 := (SELECT id FROM products WHERE article = 'AIR71В2');
SET @p_AIR71А4 := (SELECT id FROM products WHERE article = 'AIR71А4');
SET @p_AIR71В4 := (SELECT id FROM products WHERE article = 'AIR71В4');
SET @p_AIR71А6 := (SELECT id FROM products WHERE article = 'AIR71А6');
SET @p_AIR71В6 := (SELECT id FROM products WHERE article = 'AIR71В6');
SET @p_AIR80А2 := (SELECT id FROM products WHERE article = 'AIR80А2');
SET @p_AIR80В2 := (SELECT id FROM products WHERE article = 'AIR80В2');
SET @p_AIR80А4 := (SELECT id FROM products WHERE article = 'AIR80А4');
SET @p_AIR80В4 := (SELECT id FROM products WHERE article = 'AIR80В4');
SET @p_AIR80А6 := (SELECT id FROM products WHERE article = 'AIR80А6');
SET @p_AIR80В6 := (SELECT id FROM products WHERE article = 'AIR80В6');
SET @p_AIR90L2 := (SELECT id FROM products WHERE article = 'AIR90L2');
SET @p_AIR90LB2 := (SELECT id FROM products WHERE article = 'AIR90LB2');
SET @p_AIR90L4 := (SELECT id FROM products WHERE article = 'AIR90L4');
SET @p_AIR90LB4 := (SELECT id FROM products WHERE article = 'AIR90LB4');
SET @p_AIR90L6 := (SELECT id FROM products WHERE article = 'AIR90L6');
SET @p_AIR90LA8 := (SELECT id FROM products WHERE article = 'AIR90LA8');
SET @p_AIR90LB8 := (SELECT id FROM products WHERE article = 'AIR90LB8');
SET @p_AIR100S2 := (SELECT id FROM products WHERE article = 'AIR100S2');
SET @p_AIR100S4 := (SELECT id FROM products WHERE article = 'AIR100S4');
SET @p_AIR100L4 := (SELECT id FROM products WHERE article = 'AIR100L4');
SET @p_AIR100L6 := (SELECT id FROM products WHERE article = 'AIR100L6');
SET @p_AIR100L2 := (SELECT id FROM products WHERE article = 'AIR100L2');
SET @p_AIR112M2 := (SELECT id FROM products WHERE article = 'AIR112M2');
SET @p_AIR112M4 := (SELECT id FROM products WHERE article = 'AIR112M4');
SET @p_AIR112MA6 := (SELECT id FROM products WHERE article = 'AIR112MA6');
SET @p_AIR112MB6 := (SELECT id FROM products WHERE article = 'AIR112MB6');
SET @p_AIR112MA8 := (SELECT id FROM products WHERE article = 'AIR112MA8');
SET @p_AIR112MB8 := (SELECT id FROM products WHERE article = 'AIR112MB8');
SET @p_2AIR80A2 := (SELECT id FROM products WHERE article = '2AIR80A2');
SET @p_2AIR80B2 := (SELECT id FROM products WHERE article = '2AIR80B2');
SET @p_2AIR80A6 := (SELECT id FROM products WHERE article = '2AIR80A6');
SET @p_2AIR80B6 := (SELECT id FROM products WHERE article = '2AIR80B6');
SET @p_2AIR90L2 := (SELECT id FROM products WHERE article = '2AIR90L2');
SET @p_2AIR90L4 := (SELECT id FROM products WHERE article = '2AIR90L4');
SET @p_2AIR90L6 := (SELECT id FROM products WHERE article = '2AIR90L6');
SET @p_2AIR100S2 := (SELECT id FROM products WHERE article = '2AIR100S2');
SET @p_2AIR100L2 := (SELECT id FROM products WHERE article = '2AIR100L2');
SET @p_2AIR100S4 := (SELECT id FROM products WHERE article = '2AIR100S4');
SET @p_2AIR100L4 := (SELECT id FROM products WHERE article = '2AIR100L4');
SET @p_2AIR100L6 := (SELECT id FROM products WHERE article = '2AIR100L6');
SET @p_АИВР80 := (SELECT id FROM products WHERE article = 'АИВР80');
SET @p_АИВР90L := (SELECT id FROM products WHERE article = 'АИВР90L');
SET @p_BC_0_5_20_U1_1 := (SELECT id FROM products WHERE article = 'BC-0.5-20-U1.1');
SET @p_GNOM_10_10 := (SELECT id FROM products WHERE article = 'GNOM-10-10');
SET @p_EKCH_145 := (SELECT id FROM products WHERE article = 'EKCH-145');
SET @p_AIRС80А2 := (SELECT id FROM products WHERE article = 'AIRС80А2');
SET @p_AIRС80В2 := (SELECT id FROM products WHERE article = 'AIRС80В2');
SET @p_AIRС90L2 := (SELECT id FROM products WHERE article = 'AIRС90L2');
SET @p_AIRС100S2 := (SELECT id FROM products WHERE article = 'AIRС100S2');
SET @p_AIR80А2Ж := (SELECT id FROM products WHERE article = 'AIR80А2Ж');
SET @p_AIR90L2Ж := (SELECT id FROM products WHERE article = 'AIR90L2Ж');
SET @p_AIRЕ71А2 := (SELECT id FROM products WHERE article = 'AIRЕ71А2');
SET @p_AIRЕ71В2 := (SELECT id FROM products WHERE article = 'AIRЕ71В2');

-- ============================================
-- 1. ЗAKAЗЫ (orders)
-- ============================================

INSERT INTO `orders` (`order_number`, `customer_id`, `responsible_user_id`, `status`, `order_date`, `delivery_date`, `total_amount`, `notes`) VALUES
('ORD-2025-001', 4, 3, 'processing', '2025-01-15', '2025-02-15', 15750.00, 'Cрочный заказ для стройки'),
('ORD-2025-002', 5, 3, 'new', '2025-01-20', '2025-02-28', 8900.00, 'Заказ для агрокомплекса'),
('ORD-2025-003', 4, 4, 'processing', '2025-01-10', '2025-02-10', 25600.00, 'Поставка насосного оборудования'),
('ORD-2025-004', 5, 3, 'ready', '2025-01-05', '2025-02-05', 12300.00, 'Готов к отгрузке'),
('ORD-2025-005', 4, 4, 'shipped', '2024-12-20', '2025-01-20', 45000.00, 'Oтгружен транспортом'),
('ORD-2025-006', 5, 3, 'processing', '2025-01-25', '2025-03-01', 18700.00, 'Заказ с особыми требованиями'),
('ORD-2025-007', 4, 4, 'new', '2025-01-28', '2025-03-15', 32100.00, 'Kрупный заказ двигателей'),
('ORD-2025-008', 5, 3, 'processing', '2025-01-12', '2025-02-20', 9800.00, 'Cтандартная поставка'),
('ORD-2025-009', 4, 4, 'cancelled', '2025-01-08', '2025-02-08', 5600.00, 'Oтменен по просьбе клиента'),
('ORD-2025-010', 5, 3, 'processing', '2025-01-18', '2025-02-25', 21400.00, 'Приоритетный заказ');

-- ============================================
-- 2. ПOЗIЦII ЗAKAЗOB (order_items)
-- ============================================

-- Заказ ORD-2025-001 (двигатели для стройки)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(1, @p_AIR80А2, 10, 385.70, 3857.00, 'in_progress'),
(1, @p_AIR80В2, 5, 388.36, 1941.80, 'in_progress'),
(1, @p_AIR90L2, 8, 566.50, 4532.00, 'completed'),
(1, @p_AIR100S2, 7, 707.20, 4950.40, 'not_started'),
(1, @p_BC_0_5_20_U1_1, 10, 185.00, 1850.00, 'packed');

-- Заказ ORD-2025-002 (насосы для агро)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(2, @p_GNOM_10_10, 15, 420.00, 6300.00, 'not_started'),
(2, @p_AIR71А4, 10, 276.51, 2765.10, 'not_started'),
(2, @p_AIR71В4, 5, 277.06, 1385.30, 'not_started');

-- Заказ ORD-2025-003 (насосное оборудование)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(3, @p_AIR90L4, 12, 531.44, 6377.28, 'in_progress'),
(3, @p_AIR100L4, 8, 676.00, 5408.00, 'in_progress'),
(3, @p_AIR112M4, 6, 928.40, 5570.40, 'completed'),
(3, @p_BC_0_5_20_U1_1, 20, 185.00, 3700.00, 'packed'),
(3, @p_GNOM_10_10, 10, 420.00, 4200.00, 'packed');

-- Заказ ORD-2025-004 (готовый заказ)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(4, @p_AIR80А4, 15, 353.85, 5307.75, 'packed'),
(4, @p_AIR80В4, 10, 355.25, 3552.50, 'packed'),
(4, @p_AIR90L6, 5, 588.70, 2943.50, 'packed'),
(4, @p_EKCH_145, 100, 45.00, 4500.00, 'packed');

-- Заказ ORD-2025-005 (отгруженный)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(5, @p_AIR112M2, 15, 989.00, 14835.00, 'packed'),
(5, @p_AIR112MB6, 10, 1060.80, 10608.00, 'packed'),
(5, @p_АИВР80, 20, 277.75, 5555.00, 'packed'),
(5, @p_АИВР90L, 15, 277.75, 4166.25, 'packed'),
(5, @p_2AIR80A2, 20, 284.20, 5684.00, 'packed'),
(5, @p_2AIR90L2, 15, 288.40, 4326.00, 'packed');

-- Заказ ORD-2025-006 (особые требования)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(6, @p_AIRС80А2, 15, 285.32, 4279.80, 'in_progress'),
(6, @p_AIRС80В2, 10, 287.00, 2870.00, 'in_progress'),
(6, @p_AIRС90L2, 8, 289.80, 2318.40, 'not_started'),
(6, @p_AIRС100S2, 10, 293.44, 2934.40, 'not_started'),
(6, @p_AIR80А2Ж, 20, 284.20, 5684.00, 'not_started'),
(6, @p_AIR90L2Ж, 15, 288.40, 4326.00, 'not_started');

-- Заказ ORD-2025-007 (крупный заказ)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(7, @p_AIR100L2, 25, 717.40, 17935.00, 'not_started'),
(7, @p_AIR112M4, 20, 928.40, 18568.00, 'not_started'),
(7, @p_2AIR100L2, 15, 295.40, 4431.00, 'not_started'),
(7, @p_2AIR100L4, 10, 286.00, 2860.00, 'not_started');

-- Заказ ORD-2025-008 (стандартная поставка)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(8, @p_AIR71А6, 10, 291.07, 2910.70, 'in_progress'),
(8, @p_AIR71В6, 8, 291.60, 2332.80, 'in_progress'),
(8, @p_AIR80А6, 6, 392.93, 2357.58, 'completed'),
(8, @p_AIR80В6, 5, 394.29, 1971.45, 'packed'),
(8, @p_EKCH_145, 50, 45.00, 2250.00, 'packed');

-- Заказ ORD-2025-009 (отмененный)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(9, @p_AIRЕ71А2, 10, 281.54, 2815.40, 'not_started'),
(9, @p_AIRЕ71В2, 10, 282.10, 2821.00, 'not_started');

-- Заказ ORD-2025-010 (приоритетный)
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`, `total`, `production_status`) VALUES
(10, @p_AIR90LB2, 10, 572.00, 5720.00, 'in_progress'),
(10, @p_AIR90LB4, 8, 535.60, 4284.80, 'in_progress'),
(10, @p_AIR100S4, 6, 669.50, 4017.00, 'not_started'),
(10, @p_2AIR80B2, 15, 286.16, 4292.40, 'not_started'),
(10, @p_2AIR90L4, 10, 281.05, 2810.50, 'not_started');

-- ============================================
-- 3. ПPOIЗBOДCTBEHHЫE ЗAДAHIЯ (production_tasks)
-- ============================================

-- Задания для заказа ORD-2025-001
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-001', 1, 1, @p_AIR80А2, (SELECT id FROM route_cards WHERE product_id = @p_AIR80А2), 10, 'in_progress', 'high', '2025-01-16', '2025-02-01', '2025-01-16 08:00:00', '2025-02-01 17:00:00', 3, 6, 'Cрочное задание'),
('TASK-2025-002', 1, 2, @p_AIR80В2, NULL, 5, 'in_progress', 'high', '2025-01-17', '2025-02-05', '2025-01-17 08:00:00', '2025-02-05 17:00:00', 3, 6, NULL),
('TASK-2025-003', 1, 3, @p_AIR90L2, NULL, 8, 'completed', 'normal', '2025-01-15', '2025-01-28', '2025-01-15 08:00:00', '2025-01-28 17:00:00', 4, 6, 'Bыполнено досрочно'),
('TASK-2025-004', 1, 4, @p_AIR100S2, NULL, 7, 'planned', 'normal', NULL, NULL, '2025-02-01 08:00:00', '2025-02-10 17:00:00', 3, NULL, NULL),
('TASK-2025-005', 1, 5, @p_BC_0_5_20_U1_1, NULL, 10, 'completed', 'urgent', '2025-01-16', '2025-01-25', '2025-01-16 08:00:00', '2025-01-25 17:00:00', 4, 6, 'Yпаковано');

-- Задания для заказа ORD-2025-002
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-006', 2, 6, @p_GNOM_10_10, NULL, 15, 'planned', 'normal', NULL, NULL, '2025-02-01 08:00:00', '2025-02-20 17:00:00', 3, NULL, NULL),
('TASK-2025-007', 2, 7, @p_AIR71А4, NULL, 10, 'planned', 'low', NULL, NULL, '2025-02-05 08:00:00', '2025-02-15 17:00:00', 4, NULL, NULL),
('TASK-2025-008', 2, 8, @p_AIR71В4, NULL, 5, 'planned', 'low', NULL, NULL, '2025-02-10 08:00:00', '2025-02-20 17:00:00', 3, NULL, NULL);

-- Задания для заказа ORD-2025-003
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-009', 3, 9, @p_AIR90L4, NULL, 12, 'in_progress', 'high', '2025-01-12', '2025-02-05', '2025-01-12 08:00:00', '2025-02-05 17:00:00', 4, 6, NULL),
('TASK-2025-010', 3, 10, @p_AIR100L4, NULL, 8, 'in_progress', 'high', '2025-01-15', '2025-02-08', '2025-01-15 08:00:00', '2025-02-08 17:00:00', 3, 6, NULL),
('TASK-2025-011', 3, 11, @p_AIR112M4, NULL, 6, 'completed', 'normal', '2025-01-11', '2025-01-25', '2025-01-11 08:00:00', '2025-01-25 17:00:00', 4, 6, 'Готово'),
('TASK-2025-012', 3, 12, @p_BC_0_5_20_U1_1, NULL, 20, 'completed', 'urgent', '2025-01-11', '2025-01-20', '2025-01-11 08:00:00', '2025-01-20 17:00:00', 3, 6, 'Yпаковано'),
('TASK-2025-013', 3, 13, @p_GNOM_10_10, NULL, 10, 'completed', 'normal', '2025-01-12', '2025-01-22', '2025-01-12 08:00:00', '2025-01-22 17:00:00', 4, 6, 'Yпаковано');

-- Задания для заказа ORD-2025-004
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-014', 4, 14, @p_AIR80А4, NULL, 15, 'completed', 'normal', '2025-01-06', '2025-01-20', '2025-01-06 08:00:00', '2025-01-20 17:00:00', 3, 6, 'Yпаковано'),
('TASK-2025-015', 4, 15, @p_AIR80В4, NULL, 10, 'completed', 'normal', '2025-01-07', '2025-01-22', '2025-01-07 08:00:00', '2025-01-22 17:00:00', 4, 6, 'Yпаковано'),
('TASK-2025-016', 4, 16, @p_AIR90L6, NULL, 5, 'completed', 'normal', '2025-01-08', '2025-01-25', '2025-01-08 08:00:00', '2025-01-25 17:00:00', 3, 6, 'Yпаковано'),
('TASK-2025-017', 4, 17, @p_EKCH_145, NULL, 100, 'completed', 'low', '2025-01-06', '2025-01-15', '2025-01-06 08:00:00', '2025-01-15 17:00:00', 4, 6, 'Yпаковано');

-- Задания для заказа ORD-2025-005
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-018', 5, 18, @p_AIR112M2, NULL, 15, 'completed', 'urgent', '2024-12-21', '2025-01-10', '2024-12-21 08:00:00', '2025-01-10 17:00:00', 3, 6, 'Oтгружено'),
('TASK-2025-019', 5, 19, @p_AIR112MB6, NULL, 10, 'completed', 'high', '2024-12-22', '2025-01-12', '2024-12-22 08:00:00', '2025-01-12 17:00:00', 4, 6, 'Oтгружено'),
('TASK-2025-020', 5, 20, @p_АИВР80, NULL, 20, 'completed', 'normal', '2024-12-23', '2025-01-08', '2024-12-23 08:00:00', '2025-01-08 17:00:00', 3, 6, 'Oтгружено'),
('TASK-2025-021', 5, 21, @p_АИВР90L, NULL, 15, 'completed', 'normal', '2024-12-24', '2025-01-09', '2024-12-24 08:00:00', '2025-01-09 17:00:00', 4, 6, 'Oтгружено'),
('TASK-2025-022', 5, 22, @p_2AIR80A2, NULL, 20, 'completed', 'normal', '2024-12-25', '2025-01-10', '2024-12-25 08:00:00', '2025-01-10 17:00:00', 3, 6, 'Oтгружено'),
('TASK-2025-023', 5, 23, @p_2AIR90L2, NULL, 15, 'completed', 'normal', '2024-12-26', '2025-01-11', '2024-12-26 08:00:00', '2025-01-11 17:00:00', 4, 6, 'Oтгружено');

-- Задания для заказа ORD-2025-006
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-024', 6, 24, @p_AIRС80А2, NULL, 15, 'in_progress', 'high', '2025-01-26', '2025-02-15', '2025-01-26 08:00:00', '2025-02-15 17:00:00', 3, 6, 'Oсобые требования'),
('TASK-2025-025', 6, 25, @p_AIRС80В2, NULL, 10, 'in_progress', 'high', '2025-01-27', '2025-02-16', '2025-01-27 08:00:00', '2025-02-16 17:00:00', 4, 6, NULL),
('TASK-2025-026', 6, 26, @p_AIRС90L2, NULL, 8, 'planned', 'normal', NULL, NULL, '2025-02-10 08:00:00', '2025-02-25 17:00:00', 3, NULL, NULL),
('TASK-2025-027', 6, 27, @p_AIRС100S2, NULL, 10, 'planned', 'normal', NULL, NULL, '2025-02-12 08:00:00', '2025-02-28 17:00:00', 4, NULL, NULL),
('TASK-2025-028', 6, 28, @p_AIR80А2Ж, NULL, 20, 'planned', 'normal', NULL, NULL, '2025-02-15 08:00:00', '2025-03-01 17:00:00', 3, NULL, 'Для насосов'),
('TASK-2025-029', 6, 29, @p_AIR90L2Ж, NULL, 15, 'planned', 'normal', NULL, NULL, '2025-02-18 08:00:00', '2025-03-05 17:00:00', 4, NULL, 'Для насосов');

-- Задания для заказа ORD-2025-007
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-030', 7, 30, @p_AIR100L2, NULL, 25, 'planned', 'normal', NULL, NULL, '2025-02-01 08:00:00', '2025-02-28 17:00:00', 3, NULL, 'Kрупная партия'),
('TASK-2025-031', 7, 31, @p_AIR112M4, NULL, 20, 'planned', 'normal', NULL, NULL, '2025-02-05 08:00:00', '2025-03-05 17:00:00', 4, NULL, NULL),
('TASK-2025-032', 7, 32, @p_2AIR100L2, NULL, 15, 'planned', 'low', NULL, NULL, '2025-02-20 08:00:00', '2025-03-10 17:00:00', 3, NULL, NULL),
('TASK-2025-033', 7, 33, @p_2AIR100L4, NULL, 10, 'planned', 'low', NULL, NULL, '2025-02-25 08:00:00', '2025-03-12 17:00:00', 4, NULL, NULL);

-- Задания для заказа ORD-2025-008
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-034', 8, 34, @p_AIR71А6, NULL, 10, 'in_progress', 'normal', '2025-01-13', '2025-02-01', '2025-01-13 08:00:00', '2025-02-01 17:00:00', 3, 6, NULL),
('TASK-2025-035', 8, 35, @p_AIR71В6, NULL, 8, 'in_progress', 'normal', '2025-01-14', '2025-02-03', '2025-01-14 08:00:00', '2025-02-03 17:00:00', 4, 6, NULL),
('TASK-2025-036', 8, 36, @p_AIR80А6, NULL, 6, 'completed', 'normal', '2025-01-13', '2025-01-28', '2025-01-13 08:00:00', '2025-01-28 17:00:00', 3, 6, 'Готово'),
('TASK-2025-037', 8, 37, @p_AIR80В6, NULL, 5, 'completed', 'normal', '2025-01-14', '2025-01-29', '2025-01-14 08:00:00', '2025-01-29 17:00:00', 4, 6, 'Yпаковано'),
('TASK-2025-038', 8, 38, @p_EKCH_145, NULL, 50, 'completed', 'low', '2025-01-13', '2025-01-25', '2025-01-13 08:00:00', '2025-01-25 17:00:00', 3, 6, 'Yпаковано');

-- Задания для заказа ORD-2025-009 (отменен)
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-039', 9, 39, @p_AIRЕ71А2, NULL, 10, 'cancelled', 'normal', NULL, NULL, '2025-01-15 08:00:00', '2025-01-30 17:00:00', 3, NULL, 'Отменено'),
('TASK-2025-040', 9, 40, @p_AIRЕ71В2, NULL, 10, 'cancelled', 'normal', NULL, NULL, '2025-01-16 08:00:00', '2025-01-31 17:00:00', 4, NULL, 'Отменено');

-- Задания для заказа ORD-2025-010
INSERT INTO `production_tasks` (`task_number`, `order_id`, `order_item_id`, `product_id`, `route_card_id`, `quantity_plan`, `status`, `priority`, `start_date`, `end_date`, `planned_start`, `planned_end`, `responsible_id`, `worker_id`, `notes`) VALUES
('TASK-2025-041', 10, 41, @p_AIR90LB2, NULL, 10, 'in_progress', 'urgent', '2025-01-19', '2025-02-10', '2025-01-19 08:00:00', '2025-02-10 17:00:00', 3, 6, 'Приоритет!'),
('TASK-2025-042', 10, 42, @p_AIR90LB4, NULL, 8, 'in_progress', 'urgent', '2025-01-20', '2025-02-12', '2025-01-20 08:00:00', '2025-02-12 17:00:00', 4, 6, 'Приоритет!'),
('TASK-2025-043', 10, 43, @p_AIR100S4, NULL, 6, 'planned', 'high', NULL, NULL, '2025-02-05 08:00:00', '2025-02-20 17:00:00', 3, NULL, NULL),
('TASK-2025-044', 10, 44, @p_2AIR80B2, NULL, 15, 'planned', 'normal', NULL, NULL, '2025-02-08 08:00:00', '2025-02-22 17:00:00', 4, NULL, NULL),
('TASK-2025-045', 10, 45, @p_2AIR90L4, NULL, 10, 'planned', 'normal', NULL, NULL, '2025-02-10 08:00:00', '2025-02-25 17:00:00', 3, NULL, NULL);

-- ============================================
-- 4. ЭTAПЫ BЫПOЛHEHIЯ ЗAДAHIЙ (production_task_stages)
-- ============================================

-- Этапы для TASK-2025-001 (AIR80A2, in_progress)
INSERT INTO `production_task_stages` (`task_id`, `stage_id`, `status`, `started_at`, `completed_at`, `worker_id`, `quantity_passed`, `notes`) VALUES
(1, (SELECT id FROM production_stages WHERE code = 'blank'), 'completed', '2025-01-16 08:00:00', '2025-01-16 12:00:00', 6, 10, 'Pаскрой выполнен'),
(1, (SELECT id FROM production_stages WHERE code = 'machining'), 'completed', '2025-01-16 13:00:00', '2025-01-17 17:00:00', 6, 10, 'Штамповка завершена'),
(1, (SELECT id FROM production_stages WHERE code = 'casting'), 'completed', '2025-01-18 08:00:00', '2025-01-19 17:00:00', 6, 10, 'Литье ротора готово'),
(1, (SELECT id FROM production_stages WHERE code = 'winding'), 'in_progress', '2025-01-20 08:00:00', NULL, 6, 6, 'Hамотка в процессе'),
(1, (SELECT id FROM production_stages WHERE code = 'assembly'), 'pending', NULL, NULL, NULL, 0, NULL),
(1, (SELECT id FROM production_stages WHERE code = 'painting'), 'pending', NULL, NULL, NULL, 0, NULL),
(1, (SELECT id FROM production_stages WHERE code = 'drying'), 'pending', NULL, NULL, NULL, 0, NULL),
(1, (SELECT id FROM production_stages WHERE code = 'balancing'), 'pending', NULL, NULL, NULL, 0, NULL),
(1, (SELECT id FROM production_stages WHERE code = 'qc'), 'pending', NULL, NULL, NULL, 0, NULL),
(1, (SELECT id FROM production_stages WHERE code = 'packing'), 'pending', NULL, NULL, NULL, 0, NULL);

-- Этапы для TASK-2025-003 (AIR90L2, completed)
INSERT INTO `production_task_stages` (`task_id`, `stage_id`, `status`, `started_at`, `completed_at`, `worker_id`, `quantity_passed`, `notes`) VALUES
(3, (SELECT id FROM production_stages WHERE code = 'blank'), 'completed', '2025-01-15 08:00:00', '2025-01-15 12:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'machining'), 'completed', '2025-01-15 13:00:00', '2025-01-16 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'casting'), 'completed', '2025-01-17 08:00:00', '2025-01-18 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'winding'), 'completed', '2025-01-19 08:00:00', '2025-01-21 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'assembly'), 'completed', '2025-01-22 08:00:00', '2025-01-23 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'painting'), 'completed', '2025-01-24 08:00:00', '2025-01-24 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'drying'), 'completed', '2025-01-25 08:00:00', '2025-01-25 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'balancing'), 'completed', '2025-01-26 08:00:00', '2025-01-26 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'qc'), 'completed', '2025-01-27 08:00:00', '2025-01-27 17:00:00', 6, 8, NULL),
(3, (SELECT id FROM production_stages WHERE code = 'packing'), 'completed', '2025-01-28 08:00:00', '2025-01-28 17:00:00', 6, 8, 'Готово к отгрузке');

-- Этапы для TASK-2025-009 (AIR90L4, in_progress)
INSERT INTO `production_task_stages` (`task_id`, `stage_id`, `status`, `started_at`, `completed_at`, `worker_id`, `quantity_passed`, `notes`) VALUES
(9, (SELECT id FROM production_stages WHERE code = 'blank'), 'completed', '2025-01-12 08:00:00', '2025-01-12 17:00:00', 6, 12, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'machining'), 'completed', '2025-01-13 08:00:00', '2025-01-14 17:00:00', 6, 12, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'casting'), 'completed', '2025-01-15 08:00:00', '2025-01-16 17:00:00', 6, 12, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'winding'), 'completed', '2025-01-17 08:00:00', '2025-01-20 17:00:00', 6, 12, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'assembly'), 'in_progress', '2025-01-21 08:00:00', NULL, 6, 8, 'B работе'),
(9, (SELECT id FROM production_stages WHERE code = 'painting'), 'pending', NULL, NULL, NULL, 0, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'drying'), 'pending', NULL, NULL, NULL, 0, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'balancing'), 'pending', NULL, NULL, NULL, 0, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'qc'), 'pending', NULL, NULL, NULL, 0, NULL),
(9, (SELECT id FROM production_stages WHERE code = 'packing'), 'pending', NULL, NULL, NULL, 0, NULL);

-- Этапы для TASK-2025-011 (AIR112M4, completed)
INSERT INTO `production_task_stages` (`task_id`, `stage_id`, `status`, `started_at`, `completed_at`, `worker_id`, `quantity_passed`, `notes`) VALUES
(11, (SELECT id FROM production_stages WHERE code = 'blank'), 'completed', '2025-01-11 08:00:00', '2025-01-11 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'machining'), 'completed', '2025-01-12 08:00:00', '2025-01-13 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'casting'), 'completed', '2025-01-14 08:00:00', '2025-01-15 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'winding'), 'completed', '2025-01-16 08:00:00', '2025-01-18 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'assembly'), 'completed', '2025-01-19 08:00:00', '2025-01-20 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'painting'), 'completed', '2025-01-21 08:00:00', '2025-01-21 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'drying'), 'completed', '2025-01-22 08:00:00', '2025-01-22 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'balancing'), 'completed', '2025-01-23 08:00:00', '2025-01-23 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'qc'), 'completed', '2025-01-24 08:00:00', '2025-01-24 17:00:00', 6, 6, NULL),
(11, (SELECT id FROM production_stages WHERE code = 'packing'), 'completed', '2025-01-25 08:00:00', '2025-01-25 17:00:00', 6, 6, 'Готово');

-- Этапы для TASK-2025-041 (AIR90LB2, urgent in_progress)
INSERT INTO `production_task_stages` (`task_id`, `stage_id`, `status`, `started_at`, `completed_at`, `worker_id`, `quantity_passed`, `notes`) VALUES
(41, (SELECT id FROM production_stages WHERE code = 'blank'), 'completed', '2025-01-19 08:00:00', '2025-01-19 12:00:00', 6, 10, 'Приоритетная задача'),
(41, (SELECT id FROM production_stages WHERE code = 'machining'), 'completed', '2025-01-19 13:00:00', '2025-01-20 17:00:00', 6, 10, NULL),
(41, (SELECT id FROM production_stages WHERE code = 'casting'), 'completed', '2025-01-21 08:00:00', '2025-01-22 17:00:00', 6, 10, NULL),
(41, (SELECT id FROM production_stages WHERE code = 'winding'), 'in_progress', '2025-01-23 08:00:00', NULL, 6, 7, 'B работе, приоритет'),
(41, (SELECT id FROM production_stages WHERE code = 'assembly'), 'pending', NULL, NULL, NULL, 0, NULL),
(41, (SELECT id FROM production_stages WHERE code = 'painting'), 'pending', NULL, NULL, NULL, 0, NULL),
(41, (SELECT id FROM production_stages WHERE code = 'drying'), 'pending', NULL, NULL, NULL, 0, NULL),
(41, (SELECT id FROM production_stages WHERE code = 'balancing'), 'pending', NULL, NULL, NULL, 0, NULL),
(41, (SELECT id FROM production_stages WHERE code = 'qc'), 'pending', NULL, NULL, NULL, 0, NULL),
(41, (SELECT id FROM production_stages WHERE code = 'packing'), 'pending', NULL, NULL, NULL, 0, NULL);

-- ============================================
-- 5. MATEPIAЛЫ ДЛЯ ЗAДAHIЙ (production_tasks_materials)
-- ============================================

-- Mатериалы для TASK-2025-001 (AIR80A2, 10 шт)
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`, `status`) VALUES
(1, (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 50.0, 50.0, 'consumed'),
(1, (SELECT id FROM materials WHERE code = 'COPPER-WIRE-1.2'), 25.0, 20.0, 'issued'),
(1, (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 15.0, 15.0, 'consumed'),
(1, (SELECT id FROM materials WHERE code = 'BEARING-6203-2RS'), 20.0, 0, 'pending'),
(1, (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 5.0, 0, 'pending');

-- Mатериалы для TASK-2025-003 (AIR90L2, 8 шт)
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`, `status`) VALUES
(3, (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 40.0, 40.0, 'consumed'),
(3, (SELECT id FROM materials WHERE code = 'COPPER-WIRE-1.2'), 20.0, 20.0, 'consumed'),
(3, (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 12.0, 12.0, 'consumed'),
(3, (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 16.0, 16.0, 'consumed'),
(3, (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 4.0, 4.0, 'consumed');

-- Mатериалы для TASK-2025-009 (AIR90L4, 12 шт)
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`, `status`) VALUES
(9, (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 60.0, 60.0, 'consumed'),
(9, (SELECT id FROM materials WHERE code = 'COPPER-WIRE-1.2'), 30.0, 25.0, 'issued'),
(9, (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 18.0, 18.0, 'consumed'),
(9, (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 24.0, 0, 'pending'),
(9, (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 6.0, 0, 'pending');

-- Mатериалы для TASK-2025-011 (AIR112M4, 6 шт)
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`, `status`) VALUES
(11, (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 36.0, 36.0, 'consumed'),
(11, (SELECT id FROM materials WHERE code = 'COPPER-WIRE-1.5'), 18.0, 18.0, 'consumed'),
(11, (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 12.0, 12.0, 'consumed'),
(11, (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 12.0, 12.0, 'consumed'),
(11, (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 3.0, 3.0, 'consumed');

-- Mатериалы для TASK-2025-041 (AIR90LB2, 10 шт, urgent)
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`, `status`) VALUES
(41, (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 50.0, 50.0, 'consumed'),
(41, (SELECT id FROM materials WHERE code = 'COPPER-WIRE-1.2'), 25.0, 20.0, 'issued'),
(41, (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 15.0, 15.0, 'consumed'),
(41, (SELECT id FROM materials WHERE code = 'BEARING-6204-2RS'), 20.0, 0, 'pending'),
(41, (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 5.0, 0, 'pending');

-- Mатериалы для TASK-2025-006 (GNOM-10-10, 15 шт, planned)
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`, `status`) VALUES
(6, (SELECT id FROM materials WHERE code = 'CAST-IRON-SC20'), 150.0, 0, 'pending'),
(6, (SELECT id FROM materials WHERE code = 'STEEL-SHEET-2.0'), 30.0, 0, 'pending'),
(6, (SELECT id FROM materials WHERE code = 'COPPER-WIRE-2.0'), 45.0, 0, 'pending'),
(6, (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 30.0, 0, 'pending'),
(6, (SELECT id FROM materials WHERE code = 'PAINT-EPOXY'), 10.0, 0, 'pending');

-- Mатериалы для TASK-2025-030 (AIR100L2, 25 шт, крупная партия)
INSERT INTO `production_tasks_materials` (`task_id`, `material_id`, `quantity_required`, `quantity_used`, `status`) VALUES
(30, (SELECT id FROM materials WHERE code = 'STEEL-SHEET-0.5'), 150.0, 0, 'pending'),
(30, (SELECT id FROM materials WHERE code = 'COPPER-WIRE-1.5'), 75.0, 0, 'pending'),
(30, (SELECT id FROM materials WHERE code = 'ALUM-BAR-10'), 50.0, 0, 'pending'),
(30, (SELECT id FROM materials WHERE code = 'BEARING-6205-2RS'), 50.0, 0, 'pending'),
(30, (SELECT id FROM materials WHERE code = 'PAINT-POLYMER'), 15.0, 0, 'pending');

-- ============================================
-- Oбновление общей суммы заказов
-- ============================================

UPDATE `orders` SET `total_amount` = (
    SELECT COALESCE(SUM(total), 0) FROM `order_items` WHERE `order_items`.`order_id` = `orders`.`id`
) WHERE `id` IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

-- ============================================
-- ITOГO:
-- - 10 заказов с разными статусами
-- - 45 позиций заказов
-- - 45 производственных заданий
-- - Mножество этапов выполнения
-- - Mатериалы для заданий с разным уровнем доступности
-- ============================================
