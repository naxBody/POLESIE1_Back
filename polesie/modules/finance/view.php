<?php
/**
 * Модуль финансов и платежей - Просмотр платежа с печатью
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

if (!canAccessModule('finance') && getCurrentUser()['role_code'] !== 'admin') {
    $_SESSION['error'] = 'У вас нет доступа к этому разделу';
    redirect(pageUrl('index.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

// Получение ID платежа
$paymentId = $_GET['id'] ?? 0;

if (!$paymentId) {
    $_SESSION['error'] = 'Платеж не найден';
    redirect(pageUrl('modules/finance/list.php'));
}

// Получение данных платежа
$stmt = $pdo->prepare("
    SELECT pd.*, 
        pt.name as payment_type_name, pt.type as flow_type, pt.category,
        c.name as contractor_name, c.inn as contractor_inn, c.address as contractor_address, c.email as contractor_email, c.phone as contractor_phone,
        ba.account_number as bank_account, ba.account_holder, ba.bank_name, ba.bank_bic,
        u.full_name as created_by_name, u2.full_name as posted_by_name,
        ea.name as expense_article_name,
        o.order_number,
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
    LEFT JOIN users u ON pd.created_by = u.id
    LEFT JOIN users u2 ON pd.posted_by = u2.id
    LEFT JOIN expense_articles ea ON pd.expense_article_id = ea.id
    LEFT JOIN orders o ON pd.order_id = o.id
    WHERE pd.id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = 'Платеж не найден';
    redirect(pageUrl('modules/finance/list.php'));
}

// Функция для суммы прописью (упрощенная)
function sumInWordsBYN($sum) {
    $sum = floatval($sum);
    $wholePart = floor($sum);
    $fractionalPart = round(($sum - $wholePart) * 100);
    return number_format($sum, 2, ',', ' ') . ' (' . $wholePart . ' руб. ' . $fractionalPart . ' коп.)';
}

$totalInWords = sumInWordsBYN($payment['amount']);

$pageTitle = 'Платеж №' . $payment['document_number'];
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
        .view-container {
            max-width: 100%;
            margin: 0;
            padding: 0 20px;
        }
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .info-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 20px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .info-value {
            font-weight: 500;
            color: #1f2937;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Стили для печати */
        @page {
            size: A4;
            margin: 15mm;
        }
        
        .print-area {
            background: white;
            padding: 5mm;
            margin-bottom: 24px;
            display: none;
            max-width: 210mm;
            margin-left: auto;
            margin-right: auto;
        }
        .print-area.show {
            display: block;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .print-company-info {
            flex: 1;
        }
        
        .print-company-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .print-company-details {
            font-size: 9pt;
            line-height: 1.3;
            color: #555;
        }
        
        .print-document-title {
            text-align: right;
        }
        
        .print-title-text {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .print-document-number {
            font-size: 11pt;
            margin-bottom: 5px;
        }
        
        .print-section {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #000;
            background: #fafafa;
        }
        
        .print-section-title {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .print-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        
        .print-info-row {
            display: flex;
        }
        
        .print-info-label {
            font-weight: bold;
            width: 140px;
            flex-shrink: 0;
        }
        
        .print-info-value {
            flex: 1;
        }
        
        .print-amount-block {
            margin: 25px 0;
            padding: 15px;
            border: 2px solid #000;
            background: #fafafa;
        }
        
        .print-amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .print-amount-row:last-child {
            margin-bottom: 0;
        }
        
        .print-amount-value {
            font-size: 12pt;
            font-weight: bold;
        }
        
        .print-amount-words {
            margin-top: 12px;
            font-style: italic;
            font-size: 9pt;
            color: #555;
        }
        
        .print-signatures {
            margin: 35px 0;
        }
        
        .print-signature-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        
        .print-signature-column {
            width: 48%;
        }
        
        .print-signature-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 10pt;
        }
        
        .print-signature-line {
            display: flex;
            align-items: flex-end;
            margin-bottom: 5px;
        }
        
        .print-signature-position {
            flex: 1;
            border-bottom: 1px solid #000;
            padding-right: 10px;
            font-size: 9pt;
        }
        
        .print-signature-space {
            width: 120px;
            border-bottom: 1px solid #000;
            margin-left: 10px;
            height: 30px;
        }
        
        .print-stamp-area {
            width: 100px;
            height: 100px;
            border: 2px dashed #999;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 8pt;
            text-align: center;
            margin: 20px auto;
        }
        
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }
        
        /* Кнопки управления */
        .control-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn-print {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        
        .btn-print:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.25);
        }
        
        .btn-print-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-print-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-print-success {
            background: #059669;
            color: white;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <?php if (isset($message) && $message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;" class="no-print">
                    <div>
                        <a href="list.php" class="btn btn-secondary" style="margin-bottom: 10px;"><i class="bi bi-arrow-left"></i> Назад к списку</a>
                    </div>
                    <div style="display: flex; gap: 10px;">
                            <?php if (canEditInModule('finance')): ?>
                                <?php if ($payment['status'] === 'draft'): ?>
                                <a href="payment_edit.php?id=<?= $payment['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Редактировать</a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="pending">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Отправить на согласование?')"><i class="bi bi-send"></i> На согласование</button>
                                </form>
                                <?php elseif ($payment['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success"><i class="bi bi-patch-check-fill"></i> Утвердить</button>
                                </form>
                                <?php elseif ($payment['status'] === 'approved'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="post">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Провести платеж?')"><i class="bi bi-credit-card"></i> Провести</button>
                                </form>
                                <?php elseif ($payment['status'] === 'posted'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="unpost">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Отменить проведение?')"><i class="bi bi-arrow-counterclockwise"></i> Отменить проведение</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return showCancelComment()">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="cancel_comment" id="cancel_comment" value="">
                                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle-fill"></i> Отменить</button>
                                </form>
                            <?php endif; ?>
                            <button onclick="togglePrintPreview()" class="btn btn-secondary"><i class="bi bi-printer"></i> Печать</button>
                            <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                    </div>
                </div>
                    
                    <!-- Область для печати -->
                    <div class="print-area" id="printArea">
                        <div class="print-header">
                            <div class="print-company-info">
                                <h3><i class="bi bi-buildings"></i> ОАО "Полесьеэлектромаш"</h3>
                                <p>УНП: 123456789 | ОКПО: 37654321</p>
                                <p>246000, г. Гомель, ул. Советская, д. 10</p>
                                <p>Тел: +375 (232) 12-34-56 | Email: info@polesie.by</p>
                                <p>Р/с: BY00 AKBB 3012 0000 0000 0000 0000</p>
                            </div>
                            <div class="print-company-logo">
                                <i class="fas fa-bolt" style="color: #3498db;"></i>
                            </div>
                        </div>
                        
                        <div class="print-document-title">
                            <?= $payment['flow_type'] === 'income' ? 'ПЛАТЕЖНОЕ ПОРУЧЕНИЕ №' : 'ПЛАТЕЖНОЕ ПОРУЧЕНИЕ №' ?>
                            <?= e($payment['document_number']) ?>
                        </div>
                        <div style="text-align: center; font-size: 12px; color: #6b7280; margin-bottom: 20px;">
                            от <?= formatDate($payment['document_date']) ?>
                        </div>
                        
                        <table class="print-details-table">
                            <tr>
                                <td class="label">Тип операции:</td>
                                <td><strong><?= $payment['flow_type'] === 'income' ? 'Доход' : 'Расход' ?></strong></td>
                            </tr>
                            <tr>
                                <td class="label">Тип платежа:</td>
                                <td><?= e($payment['payment_type_name']) ?> (<?= e($payment['category']) ?>)</td>
                            </tr>
                            <?php if ($payment['contractor_name']): ?>
                            <tr>
                                <td class="label">Контрагент:</td>
                                <td><strong><?= e($payment['contractor_name']) ?></strong></td>
                            </tr>
                            <tr>
                                <td class="label">ИНН контрагента:</td>
                                <td><?= e($payment['contractor_inn'] ?? '—') ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="label">Банк получателя:</td>
                                <td><?= e($payment['bank_name']) ?> (БИК: <?= e($payment['bank_bic']) ?>)</td>
                            </tr>
                            <tr>
                                <td class="label">Счет:</td>
                                <td style="font-family: monospace;"><?= e($payment['bank_account']) ?></td>
                            </tr>
                            <?php if ($payment['expense_article_name']): ?>
                            <tr>
                                <td class="label">Статья затрат:</td>
                                <td><?= e($payment['expense_article_name']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($payment['order_number']): ?>
                            <tr>
                                <td class="label">Заказ:</td>
                                <td><?= e($payment['order_number']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($payment['document_reference']): ?>
                            <tr>
                                <td class="label">Основание:</td>
                                <td><?= e($payment['document_reference']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        
                        <div class="print-amount-box">
                            <div class="amount"><?= formatMoney($payment['amount']) ?></div>
                            <?php if ($payment['vat_amount'] > 0): ?>
                            <div class="words">В том числе НДС (<?= $payment['vat_rate'] ?>%): <?= formatMoney($payment['vat_amount']) ?></div>
                            <?php else: ?>
                            <div class="words">Без НДС</div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin: 20px 0;">
                            <strong style="font-size: 13px; color: #6b7280;">Назначение платежа:</strong>
                            <p style="font-size: 13px; line-height: 1.6; margin: 8px 0 0 0;"><?= nl2br(e($payment['payment_purpose'] ?: $payment['description'] ?: 'Не указано')) ?></p>
                        </div>
                        
                        <div class="print-signatures">
                            <div class="print-signature-block">
                                <div class="label">Руководитель</div>
                                <div class="name">_________________</div>
                                <div class="position">Генеральный директор</div>
                            </div>
                            <div class="print-signature-block">
                                <div class="label">Главный бухгалтер</div>
                                <div class="name">_________________</div>
                                <div class="position">Главный бухгалтер</div>
                            </div>
                            <div class="print-stamp-area">
                                <i class="fas fa-stamp" style="font-size: 40px; opacity: 0.3;"></i>
                                <div style="margin-top: 5px;">М.П.</div>
                            </div>
                        </div>
                        
                        <div class="print-footer">
                            Документ создан: <?= date('d.m.Y H:i', strtotime($payment['created_at'])) ?> | 
                            Автор: <?= e($payment['created_by_name']) ?> | 
                            Статус: <?= e($payment['status_name']) ?>
                        </div>
                    </div>
                    
                    <div class="view-container">
                        <!-- Основная информация о платеже - как в документе -->
                        <div class="info-card">
                            <div class="info-card-title"><i class="bi bi-file-earmark-text"></i> Основная информация</div>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div>
                                    <div class="info-label">Номер документа</div>
                                    <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);"><?= e($payment['document_number']) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Дата документа</div>
                                    <div style="font-size: 16px; font-weight: 500; color: var(--text-primary);"><?= formatDate($payment['document_date']) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Тип операции</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= $payment['flow_type'] === 'income' ? 'Доход' : 'Расход' ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Статус</div>
                                    <span class="badge" style="background: <?= e($payment['status_color']) ?>20; color: <?= e($payment['status_color']) ?>; font-size: 13px; padding: 6px 12px; border-radius: 6px; font-weight: 600;">
                                        <i class="fas <?= $payment['status'] === 'posted' ? 'fa-circle-check' : ($payment['status'] === 'approved' ? 'fa-user-check' : ($payment['status'] === 'pending' ? 'fa-clock' : ($payment['status'] === 'cancelled' ? 'fa-circle-xmark' : 'fa-file'))) ?>"></i>
                                        <?= e($payment['status_name']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Сумма и назначение -->
                        <div class="info-card">
                            <div class="info-card-title">Сумма и назначение платежа</div>
                            <div style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin: 12px 0;"><?= formatMoney($payment['amount']) ?> <?= $payment['currency'] ?></div>
                            <?php if ($payment['vat_amount'] > 0): ?>
                            <div style="display: flex; gap: 24px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #f3f4f6;">
                                <div>
                                    <div class="info-label">НДС</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= formatMoney($payment['vat_amount']) ?> (<?= $payment['vat_rate'] ?>%)</div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #f3f4f6;">
                                <div class="info-label">Без НДС</div>
                            </div>
                            <?php endif; ?>
                            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #f3f4f6;">
                                <div class="info-label">Назначение платежа</div>
                                <div style="color: var(--text-primary); line-height: 1.6; margin-top: 8px;"><?= nl2br(e($payment['payment_purpose'] ?: $payment['description'] ?: 'Не указано')) ?></div>
                            </div>
                        </div>
                        
                        <!-- Контрагент -->
                        <div class="info-card">
                            <div class="info-card-title">Контрагент</div>
                            <?php if ($payment['contractor_name']): ?>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div>
                                    <div class="info-label">Название</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['contractor_name']) ?></div>
                                </div>
                                <?php if ($payment['contractor_inn']): ?>
                                <div>
                                    <div class="info-label">ИНН</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['contractor_inn']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($payment['contractor_address']): ?>
                                <div>
                                    <div class="info-label">Адрес</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['contractor_address']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($payment['contractor_phone']): ?>
                                <div>
                                    <div class="info-label">Телефон</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['contractor_phone']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div style="color: var(--text-secondary); font-style: italic;"><i class="bi bi-info-circle"></i> Контрагент не указан</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Банковские реквизиты -->
                        <div class="info-card">
                            <div class="info-card-title">Банковские реквизиты</div>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div>
                                    <div class="info-label">Владелец счета</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['account_holder']) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Номер счета</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary); font-family: monospace;"><?= e($payment['bank_account']) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Банк</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['bank_name']) ?></div>
                                </div>
                                <?php if ($payment['bank_bic']): ?>
                                <div>
                                    <div class="info-label">БИК</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['bank_bic']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Классификация -->
                        <div class="info-card">
                            <div class="info-card-title">Классификация</div>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div>
                                    <div class="info-label">Тип платежа</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['payment_type_name']) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Категория</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['category']) ?></div>
                                </div>
                                <?php if ($payment['expense_article_name']): ?>
                                <div>
                                    <div class="info-label">Статья затрат</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['expense_article_name']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Связанные документы -->
                        <div class="info-card">
                            <div class="info-card-title">Связанные документы</div>
                            <?php if ($payment['order_number'] || $payment['document_reference']): ?>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <?php if ($payment['order_number']): ?>
                                <div>
                                    <div class="info-label">Заказ</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><a href="../orders/view.php?id=<?= $payment['order_id'] ?>" style="color: #3498db; text-decoration: none;"><i class="bi bi-box-arrow-up-right"></i> <?= e($payment['order_number']) ?></a></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($payment['document_reference']): ?>
                                <div>
                                    <div class="info-label">Основание</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['document_reference']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div style="color: var(--text-secondary); font-style: italic;"><i class="bi bi-info-circle"></i> Связи отсутствуют</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Авторы и даты -->
                        <div class="info-card">
                            <div class="info-card-title">Авторы и даты</div>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div>
                                    <div class="info-label">Создан</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= formatDate($payment['created_at']) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Автор</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['created_by_name']) ?></div>
                                </div>
                                <?php if ($payment['posted_at']): ?>
                                <div>
                                    <div class="info-label">Проведен</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= formatDate($payment['posted_at']) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Провел</div>
                                    <div style="font-size: 15px; font-weight: 500; color: var(--text-primary);"><?= e($payment['posted_by_name']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        function showCancelComment() {
            const comment = prompt('Введите причину отмены:', '');
            if (comment === null) return false;
            document.getElementById('cancel_comment').value = comment || 'Без комментария';
            return true;
        }
        
        function togglePrintPreview() {
            const printArea = document.getElementById('printArea');
            printArea.classList.toggle('show');
            if (printArea.classList.contains('show')) {
                printArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    </script>
</body>
</html>
