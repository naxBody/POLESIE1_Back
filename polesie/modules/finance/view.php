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

// История изменений статуса
$statusHistory = $pdo->prepare("
    SELECT psh.*, u.full_name as changed_by_name
    FROM payment_status_history psh
    JOIN users u ON psh.changed_by = u.id
    WHERE psh.payment_document_id = ?
    ORDER BY psh.changed_at DESC
");
$statusHistory->execute([$paymentId]);
$history = $statusHistory->fetchAll();

// Обработка действий
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && canEditInModule('finance')) {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'approve' && $payment['status'] === 'pending') {
            $stmt = $pdo->prepare("UPDATE payment_documents SET status = 'approved' WHERE id = ?");
            $stmt->execute([$paymentId]);
            
            $stmt = $pdo->prepare("
                INSERT INTO payment_status_history (payment_document_id, old_status, new_status, changed_by, comment)
                VALUES (?, 'pending', 'approved', ?, 'Платеж утвержден')
            ");
            $stmt->execute([$paymentId, $user['id']]);
            
            $message = 'Платеж утвержден';
            $messageType = 'success';
            
        } elseif ($action === 'post' && $payment['status'] === 'approved') {
            // Вызов хранимой процедуры для проведения
            $stmt = $pdo->prepare("CALL post_payment(?, ?)");
            $stmt->execute([$paymentId, $user['id']]);
            
            $message = 'Платеж проведен';
            $messageType = 'success';
            
        } elseif ($action === 'unpost' && $payment['status'] === 'posted') {
            // Вызов хранимой процедуры для отмены проведения
            $stmt = $pdo->prepare("CALL unpost_payment(?, ?)");
            $stmt->execute([$paymentId, $user['id']]);
            
            $message = 'Проведение платежа отменено';
            $messageType = 'success';
            
        } elseif ($action === 'cancel') {
            $stmt = $pdo->prepare("UPDATE payment_documents SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$paymentId]);
            
            $stmt = $pdo->prepare("
                INSERT INTO payment_status_history (payment_document_id, old_status, new_status, changed_by, comment)
                VALUES (?, ?, 'cancelled', ?, 'Платеж отменен: ' . ?)
            ");
            $comment = $_POST['cancel_comment'] ?? 'Без комментария';
            $stmt->execute([$paymentId, $payment['status'], $user['id'], $comment]);
            
            $message = 'Платеж отменен';
            $messageType = 'success';
        }
        
        $pdo->commit();
        
        // Обновление данных
        $stmt = $pdo->prepare("SELECT status FROM payment_documents WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = array_merge($payment, $stmt->fetch());
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Ошибка: ' . $e->getMessage();
        $messageType = 'error';
    }
}

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
            max-width: 1200px;
            margin: 0 auto;
        }
        .document-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .document-number {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
        }
        .document-meta {
            text-align: right;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .info-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6b7280;
            font-size: 13px;
        }
        .info-value {
            font-weight: 500;
            color: #1f2937;
            text-align: right;
        }
        .amount-display {
            font-size: 32px;
            font-weight: 700;
            color: <?= $payment['flow_type'] === 'income' ? '#27ae60' : '#e74c3c' ?>;
            margin: 16px 0;
        }
        .timeline {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .timeline-item {
            display: flex;
            gap: 16px;
            padding: 12px 0;
            border-left: 2px solid #e5e7eb;
            padding-left: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3498db;
            position: absolute;
            left: -7px;
            top: 16px;
        }
        .timeline-date {
            font-size: 12px;
            color: #6b7280;
            min-width: 140px;
        }
        .timeline-content {
            flex: 1;
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
                background: white;
                padding: 20mm;
            }
            .no-print {
                display: none !important;
            }
            .print-header {
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .print-company-info {
                font-size: 10pt;
                line-height: 1.4;
            }
            .print-document-title {
                text-align: center;
                font-size: 14pt;
                font-weight: bold;
                margin: 20px 0;
            }
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            .print-table td, .print-table th {
                border: 1px solid #000;
                padding: 8px;
                font-size: 10pt;
            }
            .print-signatures {
                margin-top: 40px;
                display: flex;
                justify-content: space-between;
            }
            .print-signature-block {
                width: 45%;
                border-top: 1px solid #000;
                padding-top: 5px;
                font-size: 10pt;
            }
            .print-stamp {
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
        }
        
        .print-area {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
            display: none;
        }
        .print-area.show {
            display: block;
        }
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .print-company-logo {
            width: 80px;
            height: 80px;
            background: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        .print-company-info h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #1f2937;
        }
        .print-company-info p {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
        }
        .print-document-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .print-details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .print-details-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }
        .print-details-table td.label {
            font-weight: 600;
            color: #6b7280;
            width: 40%;
        }
        .print-amount-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .print-amount-box .amount {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        .print-amount-box .words {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
            font-style: italic;
        }
        .print-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            gap: 30px;
        }
        .print-signature-block {
            flex: 1;
            border-top: 1px solid #9ca3af;
            padding-top: 8px;
        }
        .print-signature-block .label {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .print-signature-block .name {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
        }
        .print-signature-block .position {
            font-size: 11px;
            color: #6b7280;
        }
        .print-stamp-area {
            width: 120px;
            height: 120px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 10px;
            text-align: center;
            flex-shrink: 0;
        }
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
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
                    <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;" class="no-print">
                        <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Назад к списку</a>
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
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Провести платеж?')"><i class="bi bi-cash-stack"></i> Провести</button>
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
                        <!-- Заголовок документа -->
                        <div class="document-header">
                            <div>
                                <div class="document-number"><i class="bi bi-cash-stack"></i> <?= e($payment['document_number']) ?></div>
                                <div style="color: #6b7280; margin-top: 4px;">
                                    <span class="badge" style="background: <?= e($payment['status_color']) ?>20; color: <?= e($payment['status_color']) ?>; font-size: 13px; padding: 6px 12px; border-radius: 6px;">
                                        <i class="fas <?= $payment['status'] === 'posted' ? 'fa-circle-check' : ($payment['status'] === 'approved' ? 'fa-user-check' : ($payment['status'] === 'pending' ? 'fa-clock' : ($payment['status'] === 'cancelled' ? 'fa-circle-xmark' : 'fa-file'))) ?>"></i>
                                        <?= e($payment['status_name']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="document-meta">
                                <div style="font-size: 14px; color: #6b7280;"><i class="bi bi-calendar3"></i> Дата документа</div>
                                <div style="font-size: 20px; font-weight: 600;"><?= formatDate($payment['document_date']) ?></div>
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <!-- Основная информация -->
                            <div class="info-card">
                                <div class="info-card-title"><i class="bi bi-cash-coin"></i> Сумма платежа</div>
                                <div class="amount-display"><?= formatMoney($payment['amount']) ?></div>
                                <?php if ($payment['vat_amount'] > 0): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-percent"></i> НДС:</span>
                                    <span class="info-value"><?= formatMoney($payment['vat_amount']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-tag"></i> Ставка:</span>
                                    <span class="info-value"><?= $payment['vat_rate'] ?>%</span>
                                </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-currency-dollar"></i> Валюта:</span>
                                    <span class="info-value"><?= $payment['currency'] ?></span>
                                </div>
                            </div>
                            
                            <!-- Тип платежа -->
                            <div class="info-card">
                                <div class="info-card-title"><i class="bi bi-tags"></i> Классификация</div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-arrow-right"></i> Поток:</span>
                                    <span class="info-value"><?= $payment['flow_type'] === 'income' ? '<span style="color: #27ae60;"><i class="bi bi-arrow-up-circle-fill"></i> Доход</span>' : '<span style="color: #e74c3c;"><i class="bi bi-arrow-down-circle-fill"></i> Расход</span>' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-list-ul"></i> Тип:</span>
                                    <span class="info-value"><?= e($payment['payment_type_name']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-folder"></i> Категория:</span>
                                    <span class="info-value"><?= e($payment['category']) ?></span>
                                </div>
                                <?php if ($payment['expense_article_name']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-clipboard-list"></i> Статья затрат:</span>
                                    <span class="info-value"><?= e($payment['expense_article_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Статусы и даты -->
                            <div class="info-card">
                                <div class="info-card-title"><i class="bi bi-calendar3"></i> Информация о документе</div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-plus-lg"></i> Создан:</span>
                                    <span class="info-value"><?= formatDate($payment['created_at']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-person"></i> Автор:</span>
                                    <span class="info-value"><?= e($payment['created_by_name']) ?></span>
                                </div>
                                <?php if ($payment['posted_at']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-check2-all"></i> Проведен:</span>
                                    <span class="info-value"><?= formatDate($payment['posted_at']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-person-check"></i> Провел:</span>
                                    <span class="info-value"><?= e($payment['posted_by_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Контрагент и банк -->
                        <div class="info-grid">
                            <div class="info-card">
                                <div class="info-card-title"><i class="bi bi-building"></i> Контрагент</div>
                                <?php if ($payment['contractor_name']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-signature"></i> Название:</span>
                                    <span class="info-value"><?= e($payment['contractor_name']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-person-badge"></i> ИНН:</span>
                                    <span class="info-value"><?= e($payment['contractor_inn'] ?? '—') ?></span>
                                </div>
                                <?php if ($payment['contractor_address']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-geo-alt"></i> Адрес:</span>
                                    <span class="info-value" style="text-align: left;"><?= e($payment['contractor_address']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($payment['contractor_phone']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-telephone"></i> Телефон:</span>
                                    <span class="info-value"><?= e($payment['contractor_phone']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div style="color: #6b7280; font-style: italic;"><i class="bi bi-info-circle"></i> Контрагент не указан</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-card-title"><i class="bi bi-bank"></i> Банковские реквизиты</div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-person-badge"></i> Владелец:</span>
                                    <span class="info-value"><?= e($payment['account_holder']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-credit-card"></i> Счет:</span>
                                    <span class="info-value" style="font-family: monospace;"><?= e($payment['bank_account']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-building"></i> Банк:</span>
                                    <span class="info-value"><?= e($payment['bank_name']) ?></span>
                                </div>
                                <?php if ($payment['bank_bic']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-code-slash"></i> БИК:</span>
                                    <span class="info-value"><?= e($payment['bank_bic']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-card-title"><i class="bi bi-link-45deg"></i> Связанные документы</div>
                                <?php if ($payment['order_number']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-cart3"></i> Заказ:</span>
                                    <span class="info-value"><a href="../orders/view.php?id=<?= $payment['order_id'] ?>" style="color: #3498db; text-decoration: none;"><i class="bi bi-box-arrow-up-right"></i> <?= e($payment['order_number']) ?></a></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($payment['document_reference']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="bi bi-file-earmark-text"></i> Документ-основание:</span>
                                    <span class="info-value"><?= e($payment['document_reference']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!$payment['order_number'] && !$payment['document_reference']): ?>
                                <div style="color: #6b7280; font-style: italic;"><i class="bi bi-info-circle"></i> Связи отсутствуют</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Назначение платежа -->
                        <div class="info-card" style="margin-bottom: 24px;">
                            <div class="info-card-title"><i class="bi bi-file-earmark-text"></i> Назначение платежа</div>
                            <div style="color: #1f2937; line-height: 1.6; padding: 10px 0;"><?= nl2br(e($payment['payment_purpose'] ?: $payment['description'] ?: 'Не указано')) ?></div>
                        </div>
                        
                        <!-- История изменений -->
                        <div class="timeline">
                            <div class="info-card-title" style="margin-bottom: 16px;"><i class="bi bi-clock-history"></i> История изменений статуса</div>
                            <?php foreach ($history as $item): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <i class="bi bi-clock"></i> <?= date('d.m.Y H:i', strtotime($item['changed_at'])) ?>
                                </div>
                                <div class="timeline-content">
                                    <div style="font-weight: 600; color: #1f2937;"><i class="bi bi-person"></i> <?= e($item['changed_by_name']) ?></div>
                                    <div style="font-size: 13px; color: #6b7280; margin: 4px 0;">
                                        <span style="color: #95a5a6;"><?= e($item['old_status'] ?? 'Создание') ?></span>
                                        <i class="fas fa-arrow-right" style="margin: 0 6px; font-size: 10px;"></i>
                                        <strong style="color: <?= $item['new_status'] === 'posted' ? '#27ae60' : ($item['new_status'] === 'approved' ? '#3498db' : ($item['new_status'] === 'pending' ? '#f39c12' : ($item['new_status'] === 'cancelled' ? '#e74c3c' : '#95a5a6'))) ?>"><?= e($item['new_status']) ?></strong>
                                    </div>
                                    <?php if ($item['comment']): ?>
                                    <div style="font-size: 13px; margin-top: 6px; color: #374151; background: #f9fafb; padding: 8px; border-radius: 6px; border-left: 3px solid #3498db;">
                                        <i class="bi bi-chat-left-text"></i> <?= e($item['comment']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
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
