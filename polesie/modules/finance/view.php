<?php
/**
 * Модуль финансов и платежей - Просмотр платежа
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
        c.name as contractor_name, c.inn as contractor_inn, c.address as contractor_address,
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
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                        <div style="display: flex; gap: 12px;">
                            <?php if (canEditInModule('finance')): ?>
                                <?php if ($payment['status'] === 'draft'): ?>
                                <a href="payment_edit.php?id=<?= $payment['id'] ?>" class="btn btn-primary">✏️ Редактировать</a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="pending">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Отправить на согласование?')">📤 На согласование</button>
                                </form>
                                <?php elseif ($payment['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success">✅ Утвердить</button>
                                </form>
                                <?php elseif ($payment['status'] === 'approved'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="post">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Провести платеж?')">💰 Провести</button>
                                </form>
                                <?php elseif ($payment['status'] === 'posted'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="unpost">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Отменить проведение?')">↩️ Отменить проведение</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return showCancelComment()">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="cancel_comment" id="cancel_comment" value="">
                                    <button type="submit" class="btn btn-danger">❌ Отменить</button>
                                </form>
                            <?php endif; ?>
                            <button onclick="window.print()" class="btn btn-secondary">🖨️ Печать</button>
                        </div>
                    </div>
                    
                    <div class="view-container">
                        <!-- Заголовок документа -->
                        <div class="document-header">
                            <div>
                                <div class="document-number">📄 <?= e($payment['document_number']) ?></div>
                                <div style="color: #6b7280; margin-top: 4px;">
                                    <span class="badge" style="background: <?= e($payment['status_color']) ?>20; color: <?= e($payment['status_color']) ?>; font-size: 13px; padding: 6px 12px;">
                                        <?= e($payment['status_name']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="document-meta">
                                <div style="font-size: 14px; color: #6b7280;">Дата документа</div>
                                <div style="font-size: 20px; font-weight: 600;"><?= formatDate($payment['document_date']) ?></div>
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <!-- Основная информация -->
                            <div class="info-card">
                                <div class="info-card-title">💰 Сумма</div>
                                <div class="amount-display"><?= formatMoney($payment['amount']) ?></div>
                                <?php if ($payment['vat_amount'] > 0): ?>
                                <div class="info-row">
                                    <span class="info-label">НДС:</span>
                                    <span class="info-value"><?= formatMoney($payment['vat_amount']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Ставка:</span>
                                    <span class="info-value"><?= $payment['vat_rate'] ?>%</span>
                                </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label">Валюта:</span>
                                    <span class="info-value"><?= $payment['currency'] ?></span>
                                </div>
                            </div>
                            
                            <!-- Тип платежа -->
                            <div class="info-card">
                                <div class="info-card-title">📋 Тип платежа</div>
                                <div class="info-row">
                                    <span class="info-label">Категория:</span>
                                    <span class="info-value"><?= $payment['flow_type'] === 'income' ? '⬆️ Доход' : '⬇️ Расход' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Тип:</span>
                                    <span class="info-value"><?= e($payment['payment_type_name']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Категория:</span>
                                    <span class="info-value"><?= e($payment['category']) ?></span>
                                </div>
                                <?php if ($payment['expense_article_name']): ?>
                                <div class="info-row">
                                    <span class="info-label">Статья затрат:</span>
                                    <span class="info-value"><?= e($payment['expense_article_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Статусы и даты -->
                            <div class="info-card">
                                <div class="info-card-title">📅 Статусы</div>
                                <div class="info-row">
                                    <span class="info-label">Создан:</span>
                                    <span class="info-value"><?= formatDate($payment['created_at']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Автор:</span>
                                    <span class="info-value"><?= e($payment['created_by_name']) ?></span>
                                </div>
                                <?php if ($payment['posted_at']): ?>
                                <div class="info-row">
                                    <span class="info-label">Проведен:</span>
                                    <span class="info-value"><?= formatDate($payment['posted_at']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Провел:</span>
                                    <span class="info-value"><?= e($payment['posted_by_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Контрагент и банк -->
                        <div class="info-grid">
                            <div class="info-card">
                                <div class="info-card-title">🏢 Контрагент</div>
                                <?php if ($payment['contractor_name']): ?>
                                <div class="info-row">
                                    <span class="info-label">Название:</span>
                                    <span class="info-value"><?= e($payment['contractor_name']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">ИНН:</span>
                                    <span class="info-value"><?= e($payment['contractor_inn'] ?? '—') ?></span>
                                </div>
                                <?php else: ?>
                                <div style="color: #6b7280; font-style: italic;">Не указан</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-card-title">🏦 Банковский счет</div>
                                <div class="info-row">
                                    <span class="info-label">Владелец:</span>
                                    <span class="info-value"><?= e($payment['account_holder']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Счет:</span>
                                    <span class="info-value" style="font-family: monospace;"><?= e($payment['bank_account']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Банк:</span>
                                    <span class="info-value"><?= e($payment['bank_name']) ?></span>
                                </div>
                                <?php if ($payment['bank_bic']): ?>
                                <div class="info-row">
                                    <span class="info-label">БИК:</span>
                                    <span class="info-value"><?= e($payment['bank_bic']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-card-title">🔗 Связи</div>
                                <?php if ($payment['order_number']): ?>
                                <div class="info-row">
                                    <span class="info-label">Заказ:</span>
                                    <span class="info-value"><a href="../orders/view.php?id=<?= $payment['order_id'] ?>"><?= e($payment['order_number']) ?></a></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($payment['document_reference']): ?>
                                <div class="info-row">
                                    <span class="info-label">Документ:</span>
                                    <span class="info-value"><?= e($payment['document_reference']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Назначение платежа -->
                        <div class="info-card" style="margin-bottom: 24px;">
                            <div class="info-card-title">📝 Назначение платежа</div>
                            <div style="color: #1f2937; line-height: 1.6;"><?= nl2br(e($payment['payment_purpose'] ?: $payment['description'] ?: 'Не указано')) ?></div>
                        </div>
                        
                        <!-- История изменений -->
                        <div class="timeline">
                            <div class="info-card-title" style="margin-bottom: 16px;">📜 История изменений</div>
                            <?php foreach ($history as $item): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?= date('d.m.Y H:i', strtotime($item['changed_at'])) ?></div>
                                <div class="timeline-content">
                                    <div style="font-weight: 600;"><?= e($item['changed_by_name']) ?></div>
                                    <div style="font-size: 13px; color: #6b7280;">
                                        <?= e($item['old_status'] ?? 'Создание') ?> → <strong><?= e($item['new_status']) ?></strong>
                                    </div>
                                    <?php if ($item['comment']): ?>
                                    <div style="font-size: 13px; margin-top: 4px; color: #374151;"><?= e($item['comment']) ?></div>
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
    </script>
</body>
</html>
