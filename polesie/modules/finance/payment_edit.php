<?php
/**
 * Модуль финансов и платежей - Редактирование платежа
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

if (!canEditInModule('finance') && getCurrentUser()['role_code'] !== 'admin') {
    $_SESSION['error'] = 'У вас нет доступа к редактированию платежей';
    redirect(pageUrl('modules/finance/index.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();
$errors = [];
$success = false;

// Получение ID платежа
$paymentId = $_GET['id'] ?? 0;

if (!$paymentId) {
    $_SESSION['error'] = 'Платеж не найден';
    redirect(pageUrl('modules/finance/list.php'));
}

// Получение данных платежа с полной информацией
$stmt = $pdo->prepare("
    SELECT pd.*, 
        pt.name as payment_type_name, pt.type as flow_type, pt.category,
        c.name as contractor_name, c.inn as contractor_inn, c.address as contractor_address,
        ba.account_number as bank_account_number, ba.account_holder as bank_account_holder,
        ea.name as expense_article_name,
        o.order_number,
        u.full_name as created_by_name,
        CASE pd.status
            WHEN 'draft' THEN 'Черновик'
            WHEN 'pending' THEN 'На согласовании'
            WHEN 'approved' THEN 'Утвержден'
            WHEN 'posted' THEN 'Проведен'
            WHEN 'cancelled' THEN 'Отменен'
            ELSE pd.status
        END as status_name,
        CASE pd.status
            WHEN 'draft' THEN '#95a5a6'
            WHEN 'pending' THEN '#f39c12'
            WHEN 'approved' THEN '#3498db'
            WHEN 'posted' THEN '#27ae60'
            WHEN 'cancelled' THEN '#e74c3c'
            ELSE '#95a5a6'
        END as status_color
    FROM payment_documents pd
    JOIN payment_types pt ON pd.payment_type_id = pt.id
    LEFT JOIN contractors c ON pd.contractor_id = c.id
    JOIN bank_accounts ba ON pd.bank_account_id = ba.id
    LEFT JOIN expense_articles ea ON pd.expense_article_id = ea.id
    LEFT JOIN orders o ON pd.order_id = o.id
    LEFT JOIN users u ON pd.created_by = u.id
    WHERE pd.id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = 'Платеж не найден';
    redirect(pageUrl('modules/finance/list.php'));
}

// Можно редактировать только черновики
if ($payment['status'] !== 'draft') {
    $_SESSION['error'] = 'Можно редактировать только платежи в статусе "Черновик"';
    redirect(pageUrl('modules/finance/view.php?id=' . $paymentId));
}

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
            // Определение типа платежа для определения статьи затрат
            $stmt = $pdo->prepare("SELECT type FROM payment_types WHERE id = ?");
            $stmt->execute([$paymentTypeId]);
            $paymentTypeData = $stmt->fetch();
            
            // Если расход, статья затрат обязательна
            if ($paymentTypeData['type'] === 'expense' && !$expenseArticleId) {
                $errors[] = 'Для расхода необходимо выбрать статью затрат';
            }
            
            if (empty($errors)) {
                // Расчет НДС
                $vatAmount = $amount * ($vatRate / 100);
                
                // Обновление платежного документа
                $stmt = $pdo->prepare("
                    UPDATE payment_documents SET
                        document_date = ?,
                        payment_type_id = ?,
                        amount = ?,
                        currency = 'BYN',
                        vat_amount = ?,
                        vat_rate = ?,
                        contractor_id = ?,
                        bank_account_id = ?,
                        expense_article_id = ?,
                        order_id = ?,
                        description = ?,
                        payment_purpose = ?,
                        document_reference = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
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
                    $paymentId
                ]);
                
                // Запись в историю статусов
                $stmt = $pdo->prepare("
                    INSERT INTO payment_status_history (payment_document_id, old_status, new_status, changed_by, comment)
                    VALUES (?, 'draft', 'draft', ?, 'Документ отредактирован')
                ");
                $stmt->execute([$paymentId, $user['id']]);
                
                $pdo->commit();
                
                $_SESSION['success'] = "Платеж {$payment['document_number']} успешно обновлен";
                redirect(pageUrl('modules/finance/view.php?id=' . $paymentId));
            }
        }
        
        if (!empty($errors)) {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Ошибка при обновлении платежа: ' . $e->getMessage();
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

$pageTitle = 'Редактирование платежа №' . $payment['document_number'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            border: 1px solid #f0f0f0;
        }
        .form-section-title {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 16px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section-title i {
            color: #3498db;
            font-size: 18px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .form-group {
            margin-bottom: 14px;
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
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-group label i {
            color: #6b7280;
            font-size: 14px;
        }
        .form-group label .required {
            color: #e74c3c;
            margin-left: 2px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background: #fff;
            color: #1f2937;
        }
        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #d1d5db;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 0;
            padding: 0;
            border-top: none;
        }
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-error i {
            font-size: 20px;
            margin-top: 1px;
        }
        .readonly-field {
            background: #f9fafb;
            color: #6b7280;
            cursor: not-allowed;
            border-color: #e5e7eb;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 20px 24px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        .page-title p {
            color: #6b7280;
            margin: 4px 0 0 0;
            font-size: 14px;
        }
        .page-title-icon {
            font-size: 28px;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div style="padding: 0;">
                    <div class="page-header" style="margin-bottom: 20px; padding: 16px 24px;">
                        <div class="page-title">
                            <i class="bi bi-pencil-square page-title-icon"></i>
                            <div>
                                <h1>Редактирование платежа</h1>
                                <p><?= e($payment['document_number']) ?></p>
                            </div>
                        </div>
                        <a href="view.php?id=<?= $paymentId ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Отмена
                        </a>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Ошибки:</strong>
                            <ul style="margin: 8px 0 0 20px;">
                                <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-container">
                        <!-- Информация о платеже -->
                        <div class="form-section" style="padding: 16px 24px; margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div>
                                    <div style="font-size: 18px; font-weight: 600; color: #1f2937;"><?= e($payment['document_number']) ?></div>
                                    <div style="font-size: 13px; color: #6b7280; margin-top: 4px;">от <?= formatDate($payment['created_at']) ?></div>
                                </div>
                                <span class="badge" style="background: <?= e($payment['status_color']) ?>20; color: <?= e($payment['status_color']) ?>; font-size: 13px; padding: 6px 12px; border-radius: 6px; font-weight: 600;">
                                    <i class="bi bi-circle-fill" style="font-size: 8px; vertical-align: middle; margin-right: 6px;"></i>
                                    <?= e($payment['status_name']) ?>
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                                <div>
                                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Тип платежа</div>
                                    <div style="font-size: 14px; font-weight: 500; color: #1f2937;"><?= e($payment['payment_type_name']) ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Сумма</div>
                                    <div style="font-size: 14px; font-weight: 600; color: #1f2937;"><?= number_format($payment['amount'], 2, ',', ' ') ?> BYN</div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">НДС</div>
                                    <div style="font-size: 14px; font-weight: 500; color: #1f2937;"><?= $payment['vat_rate'] > 0 ? number_format($payment['vat_amount'], 2, ',', ' ') . ' BYN (' . $payment['vat_rate'] . '%)' : 'Без НДС' ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <!-- Основная информация -->
                            <div class="form-section">
                                <h3 class="form-section-title"><i class="bi bi-file-text"></i> Основная информация</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label><i class="bi bi-hash"></i> Номер документа</label>
                                        <input type="text" value="<?= e($payment['document_number']) ?>" class="readonly-field" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-calendar3"></i> Дата документа <span class="required">*</span></label>
                                        <input type="date" name="document_date" value="<?= e($payment['document_date']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-tag"></i> Тип платежа <span class="required">*</span></label>
                                        <select name="payment_type_id" required id="payment_type">
                                            <option value="">Выберите тип...</option>
                                            <?php foreach ($paymentTypes as $pt): ?>
                                            <option value="<?= $pt['id'] ?>" data-type="<?= $pt['type'] ?>" <?= $pt['id'] == $payment['payment_type_id'] ? 'selected' : '' ?>>
                                                <?= e($pt['name']) ?> (<?= $pt['type'] === 'income' ? 'Доход' : 'Расход' ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-currency-dollar"></i> Сумма платежа (BYN) <span class="required">*</span></label>
                                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" value="<?= e($payment['amount']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-percent"></i> Ставка НДС (%)</label>
                                        <input type="number" name="vat_rate" step="0.01" min="0" max="100" value="<?= e($payment['vat_rate']) ?>" id="vat_rate">
                                    </div>
                                    <div class="form-group full-width">
                                        <label><i class="bi bi-card-text"></i> Краткое описание</label>
                                        <textarea name="description" placeholder="Краткое описание платежа..."><?= e($payment['description']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Контрагенты и счета -->
                            <div class="form-section">
                                <h3 class="form-section-title"><i class="bi bi-people"></i> Контрагент и счета</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label><i class="bi bi-bank"></i> Банковский счет <span class="required">*</span></label>
                                        <select name="bank_account_id" required>
                                            <option value="">Выберите счет...</option>
                                            <?php foreach ($bankAccounts as $ba): ?>
                                            <option value="<?= $ba['id'] ?>" <?= $ba['id'] == $payment['bank_account_id'] ? 'selected' : '' ?>>
                                                <?= e($ba['account_holder']) ?> - <?= e($ba['account_number']) ?> (<?= e($ba['currency']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-person-badge"></i> Контрагент</label>
                                        <select name="contractor_id" id="contractor_id">
                                            <option value="">Выберите контрагента...</option>
                                            <?php foreach ($contractors as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $payment['contractor_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group full-width">
                                        <label><i class="bi bi-file-earmark-text"></i> Назначение платежа (официальное)</label>
                                        <textarea name="payment_purpose" placeholder="Введите официальное назначение платежа..." style="min-height: 100px;"><?= e($payment['payment_purpose']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Детализация -->
                            <div class="form-section">
                                <h3 class="form-section-title"><i class="bi bi-list-ul"></i> Детализация</h3>
                                <div class="form-grid">
                                    <div class="form-group" id="expense_article_group" style="display: <?= $payment['flow_type'] === 'expense' ? 'block' : 'none' ?>;">
                                        <label><i class="bi bi-pie-chart"></i> Статья затрат</label>
                                        <select name="expense_article_id">
                                            <option value="">Выберите статью...</option>
                                            <?php foreach ($expenseArticles as $ea): ?>
                                            <option value="<?= $ea['id'] ?>" <?= $ea['id'] == $payment['expense_article_id'] ? 'selected' : '' ?>><?= e($ea['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="bi bi-folder"></i> Связь с заказом</label>
                                        <select name="order_id">
                                            <option value="">Не связано с заказом</option>
                                            <?php foreach ($orders as $o): ?>
                                            <option value="<?= $o['id'] ?>" <?= $o['id'] == $payment['order_id'] ? 'selected' : '' ?>><?= e($o['order_number']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group full-width">
                                        <label><i class="bi bi-link-45deg"></i> Ссылка на первичный документ</label>
                                        <input type="text" name="document_reference" placeholder="Счет, накладная, договор..." value="<?= e($payment['document_reference']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Действия -->
                            <div style="margin: 0 -24px -20px -24px; padding: 16px 24px; background: #f9fafb; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; display: flex; gap: 12px; justify-content: flex-end;">
                                <a href="view.php?id=<?= $paymentId ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-lg"></i> Отмена
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Сохранить изменения
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        // Показать/скрыть статью затрат в зависимости от типа платежа
        document.getElementById('payment_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const flowType = selectedOption.getAttribute('data-type');
            const expenseArticleGroup = document.getElementById('expense_article_group');
            
            if (flowType === 'expense') {
                expenseArticleGroup.style.display = 'block';
            } else {
                expenseArticleGroup.style.display = 'none';
            }
        });
        
        // Автоматический расчет НДС при изменении ставки
        document.getElementById('vat_rate').addEventListener('input', function() {
            const vatRate = parseFloat(this.value) || 0;
            const amountInput = document.querySelector('input[name="amount"]');
            if (amountInput) {
                const amount = parseFloat(amountInput.value) || 0;
                const vatAmount = amount * (vatRate / 100);
                console.log('НДС: ' + vatAmount.toFixed(2) + ' BYN');
            }
        });

        // Плавная прокрутка к ошибкам при их наличии
        document.addEventListener('DOMContentLoaded', function() {
            const errorAlert = document.querySelector('.alert-error');
            if (errorAlert) {
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
</body>
</html>
