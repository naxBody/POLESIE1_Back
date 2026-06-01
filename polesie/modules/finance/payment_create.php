<?php
/**
 * Модуль финансов и платежей - Создание нового платежа
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

if (!canCreateInModule('finance') && getCurrentUser()['role_code'] !== 'admin') {
    $_SESSION['error'] = 'У вас нет доступа к созданию платежей';
    redirect(pageUrl('modules/finance/index.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();
$errors = [];
$success = false;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Получение данных из формы
        $paymentTypeId = $_POST['payment_type_id'] ?? null;
        $documentDate = $_POST['document_date'] ?? date('Y-m-d');
        $amount = floatval($_POST['amount'] ?? 0);
        $vatRate = floatval($_POST['vat_rate'] ?? 0);
        $contractorId = $_POST['contractor_id'] ?: null;
        $bankAccountId = $_POST['bank_account_id'] ?? null;
        $expenseArticleId = $_POST['expense_article_id'] ?: null;
        $orderId = $_POST['order_id'] ?: null;
        $description = $_POST['description'] ?? '';
        $paymentPurpose = $_POST['payment_purpose'] ?? '';
        $documentReference = $_POST['document_reference'] ?? '';
        
        // Валидация
        if (!$paymentTypeId) {
            $errors[] = 'Не выбран тип платежа';
        }
        if (!$bankAccountId) {
            $errors[] = 'Не выбран банковский счет';
        }
        if ($amount <= 0) {
            $errors[] = 'Сумма должна быть больше нуля';
        }
        
        if (empty($errors)) {
            // Генерация номера документа
            $stmt = $pdo->prepare("
                SELECT document_number FROM payment_documents 
                WHERE document_number LIKE ? 
                ORDER BY document_number DESC LIMIT 1
            ");
            $stmt->execute(['PAY%' . date('Ymd') . '%']);
            $lastNumber = $stmt->fetchColumn();
            
            if ($lastNumber) {
                $num = intval(substr($lastNumber, -4)) + 1;
            } else {
                $num = 1;
            }
            $documentNumber = 'PAY' . date('Ymd') . str_pad($num, 4, '0', STR_PAD_LEFT);
            
            // Расчет НДС
            $vatAmount = $amount * ($vatRate / 100);
            
            // Определение типа платежа для определения статьи затрат
            $stmt = $pdo->prepare("SELECT type FROM payment_types WHERE id = ?");
            $stmt->execute([$paymentTypeId]);
            $paymentTypeData = $stmt->fetch();
            
            // Если расход, статья затрат обязательна
            if ($paymentTypeData['type'] === 'expense' && !$expenseArticleId) {
                $errors[] = 'Для расхода необходимо выбрать статью затрат';
            }
            
            if (empty($errors)) {
                // Создание платежного документа
                $stmt = $pdo->prepare("
                    INSERT INTO payment_documents (
                        document_number, document_date, payment_type_id, amount, currency,
                        vat_amount, vat_rate, contractor_id, contractor_account,
                        bank_account_id, expense_article_id, order_id,
                        description, payment_purpose, document_reference,
                        created_by, status
                    ) VALUES (?, ?, ?, ?, 'BYN', ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'draft')
                ");
                
                $stmt->execute([
                    $documentNumber,
                    $documentDate,
                    $paymentTypeId,
                    $amount,
                    $vatAmount,
                    $vatRate,
                    $contractorId,
                    $bankAccountId,
                    $expenseArticleId,
                    $orderId,
                    $description,
                    $paymentPurpose,
                    $documentReference,
                    $user['id']
                ]);
                
                $paymentId = $pdo->lastInsertId();
                
                // Запись в историю статусов
                $stmt = $pdo->prepare("
                    INSERT INTO payment_status_history (payment_document_id, old_status, new_status, changed_by, comment)
                    VALUES (?, NULL, 'draft', ?, 'Документ создан')
                ");
                $stmt->execute([$paymentId, $user['id']]);
                
                $pdo->commit();
                
                $_SESSION['success'] = "Платеж {$documentNumber} успешно создан";
                redirect(pageUrl('modules/finance/view.php?id=' . $paymentId));
            }
        }
        
        if (!empty($errors)) {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Ошибка при создании платежа: ' . $e->getMessage();
    }
}

// Загрузка справочников
$paymentTypes = $pdo->query("SELECT * FROM payment_types ORDER BY type, category")->fetchAll();
$bankAccounts = $pdo->query("SELECT * FROM bank_accounts ORDER BY account_type, account_holder")->fetchAll();
$contractors = $pdo->query("SELECT id, name, inn FROM contractors ORDER BY name")->fetchAll();
$expenseArticles = $pdo->query("SELECT * FROM expense_articles ORDER BY sort_order, name")->fetchAll();
$orders = $pdo->query("
    SELECT id, order_number, customer_id 
    FROM orders 
    WHERE status IN ('new', 'processing') 
    ORDER BY order_date DESC
")->fetchAll();

$pageTitle = 'Новый платеж';
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
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div style="padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <div>
                            <h1 style="font-size: 24px; font-weight: 700; color: #1f2937; margin: 0;">📝 Новый платеж</h1>
                            <p style="color: #6b7280; margin: 4px 0 0 0;">Создание платежного документа</p>
                        </div>
                        <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <strong>Ошибки:</strong>
                        <ul style="margin: 8px 0 0 20px;">
                            <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-container">
                        <form method="POST" action="">
                            <!-- Основная информация -->
                            <div class="form-section">
                                <h3 class="form-section-title">Основная информация</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Дата документа <span class="required">*</span></label>
                                        <input type="date" name="document_date" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Тип платежа <span class="required">*</span></label>
                                        <select name="payment_type_id" required id="payment_type">
                                            <option value="">Выберите тип...</option>
                                            <?php foreach ($paymentTypes as $pt): ?>
                                            <option value="<?= $pt['id'] ?>" data-type="<?= $pt['type'] ?>">
                                                <?= e($pt['name']) ?> (<?= $pt['type'] === 'income' ? 'Доход' : 'Расход' ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Сумма платежа (BYN) <span class="required">*</span></label>
                                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Ставка НДС (%)</label>
                                        <input type="number" name="vat_rate" step="0.01" min="0" max="100" value="0" id="vat_rate">
                                    </div>
                                    <div class="form-group full-width">
                                        <label>Краткое описание</label>
                                        <textarea name="description" placeholder="Краткое описание платежа..."></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Контрагенты и счета -->
                            <div class="form-section">
                                <h3 class="form-section-title">Контрагент и счета</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Банковский счет <span class="required">*</span></label>
                                        <select name="bank_account_id" required>
                                            <option value="">Выберите счет...</option>
                                            <?php foreach ($bankAccounts as $ba): ?>
                                            <option value="<?= $ba['id'] ?>">
                                                <?= e($ba['account_holder']) ?> - <?= e($ba['account_number']) ?> (<?= e($ba['currency']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Контрагент</label>
                                        <select name="contractor_id" id="contractor_id">
                                            <option value="">Выберите контрагента...</option>
                                            <?php foreach ($contractors as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group full-width">
                                        <label>Назначение платежа (официальное)</label>
                                        <textarea name="payment_purpose" placeholder="Введите официальное назначение платежа..." style="min-height: 100px;"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Детализация -->
                            <div class="form-section">
                                <h3 class="form-section-title">Детализация</h3>
                                <div class="form-grid">
                                    <div class="form-group" id="expense_article_group" style="display: none;">
                                        <label>Статья затрат</label>
                                        <select name="expense_article_id">
                                            <option value="">Выберите статью...</option>
                                            <?php foreach ($expenseArticles as $ea): ?>
                                            <option value="<?= $ea['id'] ?>"><?= e($ea['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Связь с заказом</label>
                                        <select name="order_id">
                                            <option value="">Не связано с заказом</option>
                                            <?php foreach ($orders as $o): ?>
                                            <option value="<?= $o['id'] ?>"><?= e($o['order_number']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group full-width">
                                        <label>Ссылка на первичный документ</label>
                                        <input type="text" name="document_reference" placeholder="Номер счета, накладной и т.п.">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                                <button type="submit" class="btn btn-primary">💾 Создать платеж</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        // Показывать статью затрат только для расходов
        document.getElementById('payment_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const type = selectedOption.getAttribute('data-type');
            const expenseGroup = document.getElementById('expense_article_group');
            
            if (type === 'expense') {
                expenseGroup.style.display = 'block';
            } else {
                expenseGroup.style.display = 'none';
            }
        });
    </script>
</body>
</html>
