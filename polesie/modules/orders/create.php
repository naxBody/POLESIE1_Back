<?php
/**
 * Создание нового заказа
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

if (!hasPermission('orders.create')) {
    die('Доступ запрещен');
}

$user = getCurrentUser();
$pdo = getDbConnection();

$errors = [];
$success = false;

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

// Генерация предлагаемого номера договора на основе последнего созданного заказа
$suggestedContractNumber = '';
$suggestedContractDate = date('Y-m-d');
$stmt = $pdo->query("SELECT contract_number, contract_date FROM orders WHERE contract_number IS NOT NULL AND contract_number != '' ORDER BY id DESC LIMIT 1");
$lastOrder = $stmt->fetch();
if ($lastOrder && !empty($lastOrder['contract_number'])) {
    // Извлекаем базовую часть номера (без цифр в конце) и увеличиваем номер
    if (preg_match('/^(.*?)(\d+)$/', $lastOrder['contract_number'], $matches)) {
        $basePart = $matches[1];
        $numPart = intval($matches[2]) + 1;
        $suggestedContractNumber = $basePart . str_pad($numPart, strlen($matches[2]), '0', STR_PAD_LEFT);
    } else {
        // Если номер не содержит цифр в конце, просто добавляем /1
        $suggestedContractNumber = $lastOrder['contract_number'] . '/1';
    }
} else {
    // Если заказов еще нет, предлагаем формат ДД.ММ.ГГГГ/1
    $suggestedContractNumber = date('d.m.Y') . '/1';
}

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
            // Генерация номера заказа
            $orderNumber = generateUniqueNumber('ORD', 'orders', 'order_number');
            
            // Инициализация общей суммы
            $totalAmount = 0;
            
            // Создание заказа
            $stmt = $pdo->prepare("
                INSERT INTO orders (order_number, customer_id, status, order_date, delivery_date, 
                                   notes, responsible_user_id, created_at, total_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $orderNumber, $contractorId, $status, $orderDate, $deliveryDate,
                $notes, $responsibleUserId, $totalAmount
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Добавление позиций
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
            
            logActivity('order_create', 'order', $orderId, null, ['order_number' => $orderNumber]);
            
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

$pageTitle = 'Новый заказ';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
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
                
                <!-- Заголовок страницы -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <div>
                        <div style="display: flex; align-items: center; margin-bottom: 4px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 12px; color: var(--text-primary);">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                            <h2 style="font-size: 24px; font-weight: 600; color: var(--text-primary); margin: 0;">Новый заказ</h2>
                        </div>
                        <p style="color: var(--text-secondary); margin: 0; padding-left: 40px;">Заполните информацию для создания заказа</p>
                    </div>
                    <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                </div>
                
                <form method="POST" action="" id="orderForm">
                    <!-- Секция 1: Информация о заказчике -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <h3 class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                Информация о заказчике
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group" style="flex: 2;">
                                    <label class="form-label">Заказчик <span style="color: #ef4444;">*</span></label>
                                    <select name="contractor_id" id="contractorSelect" class="form-control" required style="font-size: 14px;">
                                        <option value="">Выберите заказчика из списка</option>
                                        <?php foreach ($contractors as $c): ?>
                                        <option value="<?= $c['id'] ?>" 
                                                data-name="<?= e($c['name']) ?>"
                                                data-inn="<?= e($c['inn'] ?? '') ?>"
                                                data-address="<?= e($c['address'] ?? '') ?>"
                                                data-contact="<?= e($c['contact_person'] ?? '') ?>"
                                                data-phone="<?= e($c['phone'] ?? '') ?>"
                                                data-email="<?= e($c['email'] ?? '') ?>"
                                                <?= (($_POST['contractor_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?> (ИНН: <?= e($c['inn']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Ответственный</label>
                                    <select name="responsible_user_id" class="form-control" style="font-size: 14px;">
                                        <option value="">Не назначен</option>
                                        <?php
                                        $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = TRUE ORDER BY full_name")->fetchAll();
                                        foreach ($users as $u):
                                        ?>
                                        <option value="<?= $u['id'] ?>" <?= (($_POST['responsible_user_id'] ?? 0) == $u['id']) ? 'selected' : '' ?>>
                                            <?= e($u['full_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Реквизиты заказчика (автозаполнение) -->
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-top: 20px; border: 1px solid #e5e7eb;">
                                <div style="display: flex; align-items: center; margin-bottom: 16px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: var(--text-secondary);">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                        <polyline points="10 9 9 9 8 9"></polyline>
                                    </svg>
                                    <h4 style="margin: 0; font-size: 16px; font-weight: 600; color: var(--text-primary);">Реквизиты заказчика</h4>
                                </div>
                                <p style="color: var(--text-secondary); font-size: 13px; margin: 0 0 16px 0;">
                                    Выберите заказчика выше — реквизиты заполнятся автоматически (можно редактировать)
                                </p>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 13px; color: var(--text-secondary);">Наименование</label>
                                        <input type="text" id="customerName" class="form-control" style="background: #fff;" placeholder="Наименование организации">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 13px; color: var(--text-secondary);">ИНН/УНП</label>
                                        <input type="text" id="customerInn" class="form-control" style="background: #fff;" placeholder="Идентификационный номер">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 13px; color: var(--text-secondary);">Юридический адрес</label>
                                        <input type="text" id="customerAddress" class="form-control" style="background: #fff;" placeholder="Адрес регистрации">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 13px; color: var(--text-secondary);">Контактное лицо</label>
                                        <input type="text" id="customerContact" class="form-control" style="background: #fff;" placeholder="ФИО контактного лица">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 13px; color: var(--text-secondary);">Телефон</label>
                                        <input type="text" id="customerPhone" class="form-control" style="background: #fff;" placeholder="+375 (XX) XXX-XX-XX">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="font-size: 13px; color: var(--text-secondary);">E-mail</label>
                                        <input type="email" id="customerEmail" class="form-control" style="background: #fff;" placeholder="email@example.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Секция 2: Параметры заказа -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <h3 class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                                Параметры заказа
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Статус заказа</label>
                                    <select name="status" class="form-control" style="font-size: 14px;">
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s['status'] ?>" <?= (($_POST['status'] ?? 'new') == $s['status']) ? 'selected' : '' ?>>
                                            <?= e($s['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Дата заказа <span style="color: #ef4444;">*</span></label>
                                    <input type="date" name="order_date" class="form-control" 
                                           value="<?= $_POST['order_date'] ?? date('Y-m-d') ?>" required style="font-size: 14px;">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Дата доставки</label>
                                    <input type="date" name="delivery_date" class="form-control" 
                                           value="<?= $_POST['delivery_date'] ?? '' ?>" style="font-size: 14px;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Адрес доставки</label>
                                <textarea name="delivery_address" class="form-control" rows="2" 
                                          placeholder="Укажите полный адрес доставки"><?= e($_POST['delivery_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div style="border-top: 1px solid #e5e7eb; margin: 20px 0; padding-top: 20px;">
                                <h4 style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 16px 0; display: flex; align-items: center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                    </svg>
                                    Договорные документы
                                </h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Номер договора</label>
                                        <input type="text" name="contract_number" class="form-control" 
                                               value="<?= e($_POST['contract_number'] ?? $suggestedContractNumber) ?>" 
                                               placeholder="№ договора (предлагается автоматически)" style="font-size: 14px;">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Дата договора</label>
                                        <input type="date" name="contract_date" class="form-control" 
                                               value="<?= $_POST['contract_date'] ?? $suggestedContractDate ?>" style="font-size: 14px;">
                                    </div>
                                </div>
                                
                                <div class="form-group" style="margin-top: 16px;">
                                    <label class="form-label">Условия оплаты</label>
                                    <textarea name="payment_terms" class="form-control" rows="2" 
                                              placeholder="Например: 100% предоплата, безналичный расчет"><?= e($_POST['payment_terms'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Секция 3: Состав заказа -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="card-title" style="margin: 0;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                                Состав заказа
                            </h3>
                            <button type="button" class="btn btn-secondary" onclick="addItem()" style="font-size: 13px; padding: 8px 16px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Добавить позицию
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Заголовки таблицы -->
                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-weight: 600; font-size: 13px; color: var(--text-secondary);">
                                <div>Продукция</div>
                                <div>Количество</div>
                                <div>Цена (BYN)</div>
                                <div>Скидка (%)</div>
                                <div>Сумма</div>
                                <div></div>
                            </div>
                            
                            <!-- Контейнер для позиций -->
                            <div id="orderItems">
                                <div class="order-item" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <select name="items[0][product_id]" class="form-control product-select" required style="font-size: 13px;">
                                            <option value="">Выберите продукцию</option>
                                            <?php foreach ($products as $p): ?>
                                            <option value="<?= $p['id'] ?>" data-price="<?= $p['base_price'] ?>" data-unit="<?= e($p['unit_name']) ?>">
                                                <?= e($p['article']) ?> - <?= e($p['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <input type="number" name="items[0][quantity]" class="form-control quantity" step="1" min="1" value="1" required style="font-size: 13px;" oninput="calculateItemTotal(this.closest('.order-item')); calculateTotal();">
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <input type="number" name="items[0][unit_price]" class="form-control unit-price" step="0.01" min="0" style="font-size: 13px;" oninput="calculateItemTotal(this.closest('.order-item')); calculateTotal();">
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <input type="number" name="items[0][discount]" class="form-control discount" step="0.1" min="0" max="100" value="0" style="font-size: 13px;" oninput="calculateItemTotal(this.closest('.order-item')); calculateTotal();">
                                    </div>
                                    
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <input type="text" class="form-control item-total" readonly style="font-size: 13px; font-weight: 600; background: #f8f9fa;" value="0.00">
                                    </div>
                                    
                                    <div style="padding-top: 0; display: flex; align-items: center;">
                                        <button type="button" class="btn btn-danger btn-sm remove-item" onclick="this.closest('.order-item').remove(); calculateTotal();" title="Удалить позицию" style="opacity: 0.6;">
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
                            </div>
                            
                            <!-- Итого -->
                            <div style="margin-top: 24px; padding: 20px; background: #f8f9fa; border-radius: 12px; color: var(--text-primary); border: 1px solid #e5e7eb;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 16px; font-weight: 600;">Общая сумма заказа:</span>
                                    <span id="orderTotal" style="font-size: 24px; font-weight: 700;">0.00 BYN</span>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 24px;">
                                <label class="form-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: middle;">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    Примечание к заказу
                                </label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Дополнительная информация по заказу"><?= e($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Кнопки действий -->
                    <div style="display: flex; justify-content: flex-end; gap: 12px; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <a href="list.php" class="btn btn-secondary" style="padding: 12px 24px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Отмена
                        </a>
                        <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-size: 15px; font-weight: 600;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Создать заказ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        // Функция для расчета суммы по позиции
        function calculateItemTotal(row) {
            const quantity = parseFloat(row.querySelector('.quantity')?.value) || 0;
            const price = parseFloat(row.querySelector('.unit-price')?.value) || 0;
            const discount = parseFloat(row.querySelector('.discount')?.value) || 0;
            
            const subtotal = quantity * price;
            const discountAmount = subtotal * (discount / 100);
            const total = subtotal - discountAmount;
            
            const totalField = row.querySelector('.item-total');
            if (totalField) {
                totalField.value = total.toFixed(2);
            }
            
            return total;
        }
        
        // Функция для подсчета общей суммы заказа
        function calculateTotal() {
            let grandTotal = 0;
            document.querySelectorAll('.order-item').forEach(row => {
                grandTotal += calculateItemTotal(row);
            });
            document.getElementById('orderTotal').textContent = grandTotal.toFixed(2) + ' BYN';
        }
        
        let itemCount = 1;
        
        // Автозаполнение реквизитов заказчика из data-атрибутов
        document.getElementById('contractorSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (this.value) {
                document.getElementById('customerName').value = selectedOption.dataset.name || '';
                document.getElementById('customerInn').value = selectedOption.dataset.inn || '';
                document.getElementById('customerAddress').value = selectedOption.dataset.address || '';
                document.getElementById('customerContact').value = selectedOption.dataset.contact || '';
                document.getElementById('customerPhone').value = selectedOption.dataset.phone || '';
                document.getElementById('customerEmail').value = selectedOption.dataset.email || '';
            } else {
                // Очистка полей
                document.getElementById('customerName').value = '';
                document.getElementById('customerInn').value = '';
                document.getElementById('customerAddress').value = '';
                document.getElementById('customerContact').value = '';
                document.getElementById('customerPhone').value = '';
                document.getElementById('customerEmail').value = '';
            }
        });
        
        // Инициализация при загрузке страницы - заполнение первой позиции
        document.addEventListener('DOMContentLoaded', function() {
            const firstProductSelect = document.querySelector('.product-select');
            if (firstProductSelect && firstProductSelect.value) {
                const option = firstProductSelect.options[firstProductSelect.selectedIndex];
                const price = option.dataset.price;
                const row = firstProductSelect.closest('.order-item');
                
                if (price && price > 0) {
                    const priceInput = row.querySelector('.unit-price');
                    if (priceInput && !priceInput.value) {
                        priceInput.value = price;
                    }
                }
                
                calculateItemTotal(row);
                calculateTotal();
            }
        });
        
        function addItem() {
            const container = document.getElementById('orderItems');
            const newItem = document.createElement('div');
            newItem.className = 'order-item';
            newItem.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: start;';
            newItem.innerHTML = `
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="items[${itemCount}][product_id]" class="form-control product-select" required style="font-size: 13px;">
                        <option value="">Выберите продукцию</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['base_price'] ?>" data-unit="<?= e($p['unit_name']) ?>">
                            <?= e($p['article']) ?> - <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="number" name="items[${itemCount}][quantity]" class="form-control quantity" step="1" min="1" value="1" required style="font-size: 13px;" oninput="calculateItemTotal(this.closest('.order-item')); calculateTotal();">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="number" name="items[${itemCount}][unit_price]" class="form-control unit-price" step="0.01" min="0" style="font-size: 13px;" oninput="calculateItemTotal(this.closest('.order-item')); calculateTotal();">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="number" name="items[${itemCount}][discount]" class="form-control discount" step="0.1" min="0" max="100" value="0" style="font-size: 13px;" oninput="calculateItemTotal(this.closest('.order-item')); calculateTotal();">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" class="form-control item-total" readonly style="font-size: 13px; font-weight: 600; background: #f8f9fa;" value="0.00">
                </div>
                <div style="padding-top: 0; display: flex; align-items: center;">
                    <button type="button" class="btn btn-danger btn-sm remove-item" onclick="this.closest('.order-item').remove(); calculateTotal();" title="Удалить позицию" style="opacity: 0.6;">
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
        
        // Автозаполнение цены и расчет при выборе продукции
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const option = e.target.options[e.target.selectedIndex];
                const price = option.dataset.price;
                const row = e.target.closest('.order-item');
                
                if (price && price > 0) {
                    const priceInput = row.querySelector('.unit-price');
                    if (priceInput && !priceInput.value) {
                        priceInput.value = price;
                    }
                }
                
                // Пересчитываем сумму
                calculateItemTotal(row);
                calculateTotal();
            }
        });
    </script>
</body>
</html>
