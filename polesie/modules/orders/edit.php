<?php
/**
 * Редактирование заказа
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

if (!hasPermission('orders.update')) {
    die('Доступ запрещен');
}

$user = getCurrentUser();
$pdo = getDbConnection();

$errors = [];
$success = false;

// Получение ID заказа
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    die('Неверный ID заказа');
}

// Получение данных заказа
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die('Заказ не найден');
}

// Получение позиций заказа
$stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.article 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

// Получение справочников
$contractors = $pdo->query("SELECT * FROM contractors WHERE type IN ('customer', 'both') ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT p.*, pc.name as category_name, u.symbol as unit_name 
                         FROM products p 
                         LEFT JOIN product_categories pc ON p.category_id = pc.id 
                         LEFT JOIN base_units u ON p.base_unit_id = u.id 
                         WHERE 1=1 ORDER BY p.name")->fetchAll();
$statuses = $pdo->query("SELECT DISTINCT status, 
                               CASE status
                                   WHEN 'new' THEN 'Новый'
                                   WHEN 'processing' THEN 'В работе'
                                   WHEN 'ready' THEN 'Готов'
                                   WHEN 'shipped' THEN 'Отгружен'
                                   WHEN 'cancelled' THEN 'Отменен'
                                   ELSE status
                               END as name
                        FROM orders ORDER BY name")->fetchAll();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $contractorId = (int)($_POST['contractor_id'] ?? 0);
        $status = $_POST['status'] ?? 'new';
        $orderDate = $_POST['order_date'] ?? date('Y-m-d');
        $deliveryDate = $_POST['delivery_date'] ?: null;
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $paymentTerms = trim($_POST['payment_terms'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $contractNumber = trim($_POST['contract_number'] ?? '');
        $contractDate = $_POST['contract_date'] ?: null;
        $responsibleUserId = $_POST['responsible_user_id'] ?: null;
        
        // Валидация
        if (!$contractorId) {
            $errors[] = 'Выберите заказчика';
        }
        
        $items = $_POST['items'] ?? [];
        if (empty($items)) {
            $errors[] = 'Добавьте хотя бы одну позицию';
        }
        
        if (empty($errors)) {
            // Инициализация общей суммы
            $totalAmount = 0;
            
            // Обновление заказа
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET customer_id = ?, status = ?, order_date = ?, delivery_date = ?, 
                    notes = ?, responsible_user_id = ?, total_amount = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $contractorId, $status, $orderDate, $deliveryDate,
                $notes, $responsibleUserId, $totalAmount, $orderId
            ]);
            
            // Удаляем старые позиции
            $deleteStmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $deleteStmt->execute([$orderId]);
            
            // Добавление новых позиций
            foreach ($items as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }
                
                $productId = (int)$item['product_id'];
                $quantity = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? 0);
                $discount = (float)($item['discount'] ?? 0);
                
                $total = $quantity * $price * (1 - $discount / 100);
                $totalAmount += $total;
                
                $itemStmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price, total, production_status)
                    VALUES (?, ?, ?, ?, ?, 'not_started')
                ");
                
                $itemStmt->execute([
                    $orderId, $productId, $quantity, $price, $total
                ]);
            }
            
            // Обновление общей суммы
            $updateStmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $updateStmt->execute([$totalAmount, $orderId]);
            
            logActivity('order_update', 'order', $orderId, null, ['order_number' => $order['order_number']]);
            
            $pdo->commit();
            $success = true;
            
            // Перенаправление на просмотр
            header("Location: view.php?id=$orderId&success=1");
            exit;
        }
        
        $pdo->rollBack();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Ошибка: ' . $e->getMessage();
    }
}

$pageTitle = 'Редактирование заказа #' . $order['order_number'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <style>
        .section-block {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid #e9ecef;
        }
        .section-block h4 {
            margin-bottom: 16px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
        }
        .section-block h4 svg {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php require_once BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Редактирование заказа #<?= e($order['order_number']) ?></h3>
                        <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                    </div>
                    
                    <form method="POST" action="" id="orderForm">
                        <div class="card-body">
                            
                            <!-- Блок 1: Данные о заказчике -->
                            <div class="section-block">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    Данные о заказчике
                                </h4>
                                
                                <div class="form-group">
                                    <label class="form-label">Заказчик *</label>
                                    <select name="contractor_id" class="form-control" required>
                                        <option value="">Выберите заказчика</option>
                                        <?php foreach ($contractors as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($order['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?> (ИНН: <?= e($c['inn']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Блок 2: Основная информация -->
                            <div class="section-block">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    Основная информация
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Статус заказа</label>
                                        <select name="status" class="form-control">
                                            <?php foreach ($statuses as $s): ?>
                                            <option value="<?= $s['status'] ?>" <?= ($order['status'] == $s['status']) ? 'selected' : '' ?>>
                                                <?= e($s['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Дата заказа *</label>
                                        <input type="date" name="order_date" class="form-control" 
                                               value="<?= $order['order_date'] ?? date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Блок 3: Доставка -->
                            <div class="section-block">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="3" width="15" height="13"></rect>
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                    </svg>
                                    Доставка
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Дата доставки</label>
                                        <input type="date" name="delivery_date" class="form-control" 
                                               value="<?= $order['delivery_date'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Адрес доставки</label>
                                    <textarea name="delivery_address" class="form-control" rows="2" placeholder="Укажите полный адрес доставки"><?= e($order['delivery_address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Блок 4: Договор и оплата -->
                            <div class="section-block">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                        <polyline points="10 9 9 9 8 9"></polyline>
                                    </svg>
                                    Договор и оплата
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Номер договора</label>
                                        <input type="text" name="contract_number" class="form-control" 
                                               value="<?= e($order['contract_number'] ?? '') ?>" placeholder="№ договора">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Дата договора</label>
                                        <input type="date" name="contract_date" class="form-control" 
                                               value="<?= $order['contract_date'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Условия оплаты</label>
                                    <textarea name="payment_terms" class="form-control" rows="2" placeholder="Например: 100% предоплата, отсрочка 14 дней и т.д."><?= e($order['payment_terms'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Блок 5: Ответственные -->
                            <div class="section-block">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <polyline points="16 11 18 13 22 9"></polyline>
                                    </svg>
                                    Ответственные
                                </h4>
                                
                                <div class="form-group">
                                    <label class="form-label">Ответственный менеджер</label>
                                    <select name="responsible_user_id" class="form-control">
                                        <option value="">Не назначен</option>
                                        <?php
                                        $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = TRUE ORDER BY full_name")->fetchAll();
                                        foreach ($users as $u):
                                        ?>
                                        <option value="<?= $u['id'] ?>" <?= ($order['responsible_user_id'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= e($u['full_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Блок 6: Позиции заказа -->
                            <div class="section-block">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Позиции заказа
                                </h4>
                                
                                <div style="margin-bottom: 16px; display: flex; gap: 12px; align-items: center;">
                                    <button type="button" class="btn btn-primary" onclick="openProductSelector()" style="font-size: 14px; padding: 10px 20px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <path d="m21 21-4.35-4.35"></path>
                                        </svg>
                                        Выбрать продукцию
                                    </button>
                                    <span style="color: var(--text-secondary); font-size: 14px;">или добавьте позицию вручную ниже</span>
                                </div>
                                
                                <div id="orderItems">
                                    <?php 
                                    $itemIndex = 0;
                                    if (empty($orderItems)): 
                                    ?>
                                    <div class="order-item" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;">
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Продукция</label>
                                            <select name="items[0][product_id]" class="form-control product-select" required>
                                                <option value="">Выберите продукцию</option>
                                                <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" data-price="<?= $p['base_price'] ?>" data-unit="<?= e($p['unit_name']) ?>">
                                                    <?= e($p['article']) ?> - <?= e($p['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Количество</label>
                                            <input type="number" name="items[0][quantity]" class="form-control quantity" step="1" min="1" value="1" required>
                                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Цена (BYN)</label>
                                            <input type="number" name="items[0][unit_price]" class="form-control unit-price" step="0.01" min="0">
                                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Скидка (%)</label>
                                            <input type="number" name="items[0][discount]" class="form-control discount" step="0.1" min="0" max="100" value="0">
                                        </div>
                                        
                                        <div style="padding-top: 28px;">
                                            <button type="button" class="btn btn-danger btn-sm remove-item" disabled>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M3 6h18"></path>
                                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($orderItems as $item): ?>
                                    <div class="order-item" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;">
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Продукция</label>
                                            <select name="items[<?= $itemIndex ?>][product_id]" class="form-control product-select" required>
                                                <option value="">Выберите продукцию</option>
                                                <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" data-price="<?= $p['base_price'] ?>" data-unit="<?= e($p['unit_name']) ?>" <?= ($p['id'] == $item['product_id']) ? 'selected' : '' ?>>
                                                    <?= e($p['article']) ?> - <?= e($p['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Количество</label>
                                            <input type="number" name="items[<?= $itemIndex ?>][quantity]" class="form-control quantity" step="1" min="1" value="<?= $item['quantity'] ?>" required>
                                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Цена (BYN)</label>
                                            <input type="number" name="items[<?= $itemIndex ?>][unit_price]" class="form-control unit-price" step="0.01" min="0" value="<?= $item['price'] ?>">
                                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label class="form-label">Скидка (%)</label>
                                            <input type="number" name="items[<?= $itemIndex ?>][discount]" class="form-control discount" step="0.1" min="0" max="100" value="<?= $item['discount'] ?? 0 ?>">
                                        </div>
                                        
                                        <div style="padding-top: 28px;">
                                            <button type="button" class="btn btn-danger btn-sm remove-item" onclick="this.closest('.order-item').remove()">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M3 6h18"></path>
                                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                        <?php $itemIndex++; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn btn-secondary" onclick="addItem()" style="margin-top: 12px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Добавить позицию вручную
                                </button>
                                
                                <div class="form-group" style="margin-top: 24px;">
                                    <label class="form-label">Примечание</label>
                                    <textarea name="notes" class="form-control" rows="3"><?= e($order['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Модальное окно выбора продукции -->
                        <div id="productSelectorOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
                            <div style="background: white; border-radius: 12px; width: 90%; max-width: 900px; max-height: 80vh; overflow: hidden; display: flex; flex-direction: column;">
                                <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                                    <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text-primary);">Выбор продукции</h3>
                                    <button type="button" onclick="closeProductSelector()" style="background: none; border: none; cursor: pointer; padding: 8px; color: var(--text-secondary);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                                
                                <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                                        <input type="text" id="productSearchInput" class="form-control" placeholder="Поиск по названию или артикулу..." style="font-size: 14px;" oninput="applyProductFilters()">
                                        <select id="productCategoryFilter" class="form-control" style="font-size: 14px;" onchange="applyProductFilters()">
                                            <option value="">Все категории</option>
                                            <?php 
                                            $categories = $pdo->query("SELECT DISTINCT pc.id, pc.name FROM product_categories pc INNER JOIN products p ON p.category_id = pc.id ORDER BY pc.name")->fetchAll();
                                            foreach ($categories as $cat): 
                                            ?>
                                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select id="productUnitFilter" class="form-control" style="font-size: 14px;" onchange="applyProductFilters()">
                                            <option value="">Все единицы измерения</option>
                                            <?php 
                                            $units = $pdo->query("SELECT DISTINCT u.id, u.symbol FROM base_units u INNER JOIN products p ON p.base_unit_id = u.id ORDER BY u.symbol")->fetchAll();
                                            foreach ($units as $unit): 
                                            ?>
                                            <option value="<?= $unit['id'] ?>"><?= e($unit['symbol']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div style="flex: 1; overflow-y: auto; padding: 0;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead style="position: sticky; top: 0; background: #f9fafb; z-index: 1;">
                                            <tr>
                                                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid #e5e7eb;">Артикул</th>
                                                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid #e5e7eb;">Наименование</th>
                                                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid #e5e7eb;">Категория</th>
                                                <th style="padding: 12px 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid #e5e7eb;">Ед. изм.</th>
                                                <th style="padding: 12px 16px; text-align: right; font-size: 13px; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid #e5e7eb;">Цена</th>
                                                <th style="padding: 12px 16px; text-align: center; font-size: 13px; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid #e5e7eb;">Действие</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productTableBody">
                                            <?php foreach ($products as $p): ?>
                                            <tr class="product-row" 
                                                data-category-id="<?= $p['category_id'] ?>" 
                                                data-unit="<?= $p['base_unit_id'] ?>" 
                                                data-name="<?= strtolower(e($p['name'])) ?>" 
                                                data-article="<?= strtolower(e($p['article'])) ?>"
                                                data-category-name="<?= strtolower(e($p['category_name'] ?? '')) ?>"
                                                style="border-bottom: 1px solid #f3f4f6;">
                                                <td style="padding: 12px 16px; font-size: 13px; color: var(--text-primary);"><?= e($p['article']) ?></td>
                                                <td style="padding: 12px 16px; font-size: 13px; color: var(--text-primary);"><?= e($p['name']) ?></td>
                                                <td style="padding: 12px 16px; font-size: 13px; color: var(--text-secondary);"><?= e($p['category_name'] ?? '-') ?></td>
                                                <td style="padding: 12px 16px; font-size: 13px; color: var(--text-secondary);"><?= e($p['unit_name'] ?? '-') ?></td>
                                                <td style="padding: 12px 16px; font-size: 13px; color: var(--text-primary); text-align: right; font-weight: 500;"><?= $p['base_price'] > 0 ? number_format($p['base_price'], 2, ',', ' ') : '-' ?> BYN</td>
                                                <td style="padding: 12px 16px; text-align: center;">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="addProductToOrder(<?= $p['id'] ?>, '<?= addslashes(e($p['name'])) ?>', <?= $p['base_price'] ?>, '<?= e($p['unit_name'] ?? 'шт') ?>')" style="padding: 6px 12px; font-size: 12px;">
                                                        Добавить
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div style="padding: 16px 24px; border-top: 1px solid #e5e7eb; background: #f9fafb; text-align: right;">
                                    <button type="button" onclick="closeProductSelector()" class="btn btn-secondary" style="padding: 10px 24px;">Закрыть</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                                <button type="submit" class="btn btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    Сохранить изменения
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        let itemCount = <?= max($itemIndex, 1) ?>;
        
        function addItem() {
            const container = document.getElementById('orderItems');
            const newItem = document.createElement('div');
            newItem.className = 'order-item';
            newItem.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;';
            newItem.innerHTML = `
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Продукция</label>
                    <select name="items[${itemCount}][product_id]" class="form-control product-select" required>
                        <option value="">Выберите продукцию</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['base_price'] ?>" data-unit="<?= e($p['unit_name']) ?>">
                            <?= e($p['article']) ?> - <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Количество</label>
                    <input type="number" name="items[${itemCount}][quantity]" class="form-control quantity" step="1" min="1" value="1" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Цена (BYN)</label>
                    <input type="number" name="items[${itemCount}][unit_price]" class="form-control unit-price" step="0.01" min="0">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Скидка (%)</label>
                    <input type="number" name="items[${itemCount}][discount]" class="form-control discount" step="0.1" min="0" max="100" value="0">
                </div>
                <div style="padding-top: 28px;">
                    <button type="button" class="btn btn-danger btn-sm remove-item" onclick="this.closest('.order-item').remove()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </button>
                </div>
            `;
            container.appendChild(newItem);
            itemCount++;
        }
        
        // Автозаполнение цены при выборе продукции
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const option = e.target.options[e.target.selectedIndex];
                const price = option.dataset.price;
                if (price && price > 0) {
                    const row = e.target.closest('.order-item');
                    const priceInput = row.querySelector('.unit-price');
                    if (priceInput && !priceInput.value) {
                        priceInput.value = price;
                    }
                }
            }
        });
        
        // Функции для модального окна выбора продукции
        function openProductSelector() {
            document.getElementById('productSelectorOverlay').style.display = 'flex';
            document.getElementById('productSearchInput').focus();
        }
        
        function closeProductSelector() {
            document.getElementById('productSelectorOverlay').style.display = 'none';
        }
        
        function applyProductFilters() {
            const searchTerm = document.getElementById('productSearchInput').value.toLowerCase().trim();
            const categoryFilter = document.getElementById('productCategoryFilter').value;
            const unitFilter = document.getElementById('productUnitFilter').value;
            
            const rows = document.querySelectorAll('.product-row');
            rows.forEach(row => {
                const matchesSearch = !searchTerm || 
                    row.dataset.name.includes(searchTerm) || 
                    row.dataset.article.includes(searchTerm) ||
                    row.dataset.categoryName.includes(searchTerm);
                const matchesCategory = !categoryFilter || row.dataset.categoryId == categoryFilter;
                const matchesUnit = !unitFilter || row.dataset.unit == unitFilter;
                
                row.style.display = (matchesSearch && matchesCategory && matchesUnit) ? '' : 'none';
            });
        }
        
        function addProductToOrder(productId, productName, basePrice, unitName) {
            const container = document.getElementById('orderItems');
            const newItem = document.createElement('div');
            newItem.className = 'order-item';
            newItem.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;';
            
            // Создаем select элемент и заполняем его опциями
            const productSelect = document.createElement('select');
            productSelect.name = `items[${itemCount}][product_id]`;
            productSelect.className = 'form-control product-select';
            productSelect.required = true;
            
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Выберите продукцию';
            productSelect.appendChild(defaultOption);
            
            <?php foreach ($products as $p): ?>
            const option<?= $p['id'] ?> = document.createElement('option');
            option<?= $p['id'] ?>.value = '<?= $p['id'] ?>';
            option<?= $p['id'] ?>.dataset.price = '<?= $p['base_price'] ?>';
            option<?= $p['id'] ?>.dataset.unit = '<?= e($p['unit_name']) ?>';
            option<?= $p['id'] ?>.textContent = '<?= e($p['article']) ?> - <?= e($p['name']) ?>';
            if (option<?= $p['id'] ?>.value == productId) {
                option<?= $p['id'] ?>.selected = true;
            }
            productSelect.appendChild(option<?= $p['id'] ?>);
            <?php endforeach; ?>
            
            newItem.innerHTML = `
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Продукция</label>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Количество</label>
                    <input type="number" name="items[${itemCount}][quantity]" class="form-control quantity" step="1" min="1" value="1" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Цена (BYN)</label>
                    <input type="number" name="items[${itemCount}][unit_price]" class="form-control unit-price" step="0.01" min="0" value="${basePrice || 0}">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Скидка (%)</label>
                    <input type="number" name="items[${itemCount}][discount]" class="form-control discount" step="0.1" min="0" max="100" value="0">
                </div>
                <div style="padding-top: 28px;">
                    <button type="button" class="btn btn-danger btn-sm remove-item" onclick="this.closest('.order-item').remove()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </button>
                </div>
            `;
            
            // Вставляем select в первый div
            newItem.querySelector('.form-group:first-child').appendChild(productSelect);
            
            container.appendChild(newItem);
            itemCount++;
            
            // Закрываем модальное окно и показываем уведомление
            closeProductSelector();
            showNotification('Позиция добавлена в заказ', 'success');
        }
        
        // Закрытие модального окна по клику вне его
        document.getElementById('productSelectorOverlay')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductSelector();
            }
        });
    </script>
</body>
</html>
