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
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Создание нового заказа</h3>
                        <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                    </div>
                    
                    <form method="POST" action="" id="orderForm">
                        <div class="card-body">
                            <!-- Основная информация -->
                            <h4 style="margin-bottom: 16px; color: var(--text-primary);">Основная информация</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Заказчик *</label>
                                    <select name="contractor_id" class="form-control" required>
                                        <option value="">Выберите заказчика</option>
                                        <?php foreach ($contractors as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= (($_POST['contractor_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?> (ИНН: <?= e($c['inn']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Статус</label>
                                    <select name="status" class="form-control">
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s['status'] ?>" <?= (($_POST['status'] ?? 'new') == $s['status']) ? 'selected' : '' ?>>
                                            <?= e($s['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Дата заказа *</label>
                                    <input type="date" name="order_date" class="form-control" 
                                           value="<?= $_POST['order_date'] ?? date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Дата доставки</label>
                                    <input type="date" name="delivery_date" class="form-control" 
                                           value="<?= $_POST['delivery_date'] ?? '' ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Адрес доставки</label>
                                <textarea name="delivery_address" class="form-control" rows="2"><?= e($_POST['delivery_address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Номер договора</label>
                                    <input type="text" name="contract_number" class="form-control" 
                                           value="<?= e($_POST['contract_number'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Дата договора</label>
                                    <input type="date" name="contract_date" class="form-control" 
                                           value="<?= $_POST['contract_date'] ?? '' ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Условия оплаты</label>
                                <textarea name="payment_terms" class="form-control" rows="2"><?= e($_POST['payment_terms'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Ответственный</label>
                                <select name="responsible_user_id" class="form-control">
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
                            
                            <!-- Реквизиты заказчика (автозаполнение) -->
                            <h4 style="margin: 32px 0 16px; color: var(--text-primary);">📋 Реквизиты заказчика</h4>
                            <div id="customerDetails" style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                                <p style="color: var(--text-secondary); font-size: 14px;">Выберите заказчика выше - реквизиты заполнятся автоматически</p>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Наименование</label>
                                        <input type="text" id="customerName" class="form-control" readonly style="background: #fff;">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">ИНН/УНП</label>
                                        <input type="text" id="customerInn" class="form-control" readonly style="background: #fff;">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Адрес</label>
                                        <input type="text" id="customerAddress" class="form-control" readonly style="background: #fff;">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Контактное лицо</label>
                                        <input type="text" id="customerContact" class="form-control" readonly style="background: #fff;">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Телефон</label>
                                        <input type="text" id="customerPhone" class="form-control" readonly style="background: #fff;">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" id="customerEmail" class="form-control" readonly style="background: #fff;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Позиции заказа -->
                            <h4 style="margin: 32px 0 16px; color: var(--text-primary);">Позиции заказа</h4>
                            
                            <div id="orderItems">
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
                            </div>
                            
                            <button type="button" class="btn btn-secondary" onclick="addItem()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Добавить позицию
                            </button>
                            
                            <div class="form-group" style="margin-top: 24px;">
                                <label class="form-label">Примечание</label>
                                <textarea name="notes" class="form-control" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
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
                                    Создать заказ
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
        // Данные контрагентов для автозаполнения
        const contractorsData = {
            <?php foreach ($contractors as $c): ?>
            <?= $c['id'] ?>: {
                name: '<?= addslashes($c['name']) ?>',
                inn: '<?= addslashes($c['inn'] ?? '') ?>',
                address: '<?= addslashes($c['address'] ?? '') ?>',
                contact_person: '<?= addslashes($c['contact_person'] ?? '') ?>',
                phone: '<?= addslashes($c['phone'] ?? '') ?>',
                email: '<?= addslashes($c['email'] ?? '') ?>'
            },
            <?php endforeach; ?>
        };
        
        let itemCount = 1;
        
        // Автозаполнение реквизитов заказчика
        document.querySelector('select[name="contractor_id"]').addEventListener('change', function() {
            const contractorId = this.value;
            const details = contractorsData[contractorId];
            
            if (details) {
                document.getElementById('customerName').value = details.name;
                document.getElementById('customerInn').value = details.inn;
                document.getElementById('customerAddress').value = details.address;
                document.getElementById('customerContact').value = details.contact_person;
                document.getElementById('customerPhone').value = details.phone;
                document.getElementById('customerEmail').value = details.email;
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
    </script>
</body>
</html>
