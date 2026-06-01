<?php
/**
 * Модуль финансов и платежей - Главная страница
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

// Проверка авторизации
if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

// Проверка доступа к модулю
if (!canAccessModule('finance') && getCurrentUser()['role_code'] !== 'admin') {
    $_SESSION['error'] = 'У вас нет доступа к этому разделу';
    redirect(pageUrl('index.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

// Получение статистики по платежам
$stats = [];

// Всего платежей за месяц
$stmt = $pdo->query("
    SELECT COUNT(*) as count, SUM(amount) as total 
    FROM payment_documents 
    WHERE status = 'posted' 
    AND DATE_FORMAT(document_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$stats['month'] = $stmt->fetch();

// Доходы за месяц
$stmt = $pdo->query("
    SELECT COUNT(*) as count, SUM(pd.amount) as total 
    FROM payment_documents pd
    JOIN payment_types pt ON pd.payment_type_id = pt.id
    WHERE pd.status = 'posted' 
    AND pt.type = 'income'
    AND DATE_FORMAT(pd.document_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$stats['income'] = $stmt->fetch();

// Расходы за месяц
$stmt = $pdo->query("
    SELECT COUNT(*) as count, SUM(pd.amount) as total 
    FROM payment_documents pd
    JOIN payment_types pt ON pd.payment_type_id = pt.id
    WHERE pd.status = 'posted' 
    AND pt.type = 'expense'
    AND DATE_FORMAT(pd.document_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$stats['expense'] = $stmt->fetch();

// Сальдо
$stats['balance'] = [
    'count' => 0,
    'total' => ($stats['income']['total'] ?? 0) - ($stats['expense']['total'] ?? 0)
];

// Последние платежи
$recentPayments = $pdo->query("
    SELECT pd.*, 
        pt.name as payment_type_name, pt.type as flow_type,
        c.name as contractor_name,
        ba.account_number as bank_account,
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
    LEFT JOIN users u ON pd.created_by = u.id
    ORDER BY pd.created_at DESC
    LIMIT 10
")->fetchAll();

// Балансы по счетам
$accountsBalance = $pdo->query("
    SELECT * FROM bank_accounts 
    ORDER BY account_type, account_holder
")->fetchAll();

$pageTitle = 'Финансы и платежи';
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
        .finance-dashboard {
            padding: 24px;
        }
        .finance-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .finance-stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #60a5fa;
        }
        .finance-stat-card.income {
            border-left-color: #27ae60;
        }
        .finance-stat-card.expense {
            border-left-color: #e74c3c;
        }
        .finance-stat-card.balance {
            border-left-color: #3498db;
        }
        .finance-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .finance-stat-title {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }
        .finance-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
        }
        .finance-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .finance-stat-icon.income {
            background: #d1fae5;
        }
        .finance-stat-icon.expense {
            background: #fee2e2;
        }
        .finance-stat-icon.balance {
            background: #dbeafe;
        }
        .finance-content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .finance-content-grid {
                grid-template-columns: 1fr;
            }
        }
        .accounts-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .account-item {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .account-item:last-child {
            border-bottom: none;
        }
        .account-info h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            font-weight: 600;
        }
        .account-info p {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
        }
        .account-balance {
            text-align: right;
        }
        .account-balance .amount {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }
        .account-balance .currency {
            font-size: 12px;
            color: #6b7280;
        }
        .badge-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-income {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-expense {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Боковая панель -->
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <!-- Основной контент -->
        <div class="main-content">
            <!-- Верхняя панель -->
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <!-- Контентная область -->
            <div class="content-area">
                <div class="finance-dashboard">
                    <!-- Заголовок страницы -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                        <div>
                            <h1 style="font-size: 28px; font-weight: 700; color: #1f2937; margin: 0;"><i class="fas fa-wallet"></i> Финансы и платежи</h1>
                            <p style="color: #6b7280; margin: 8px 0 0 0;">Управление платежами и финансовыми документами</p>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <?php if (canCreateInModule('finance')): ?>
                            <a href="payment_create.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Новый платеж
                            </a>
                            <?php endif; ?>
                            <a href="reports.php" class="btn btn-secondary">
                                <i class="fas fa-chart-bar"></i> Отчеты
                            </a>
                        </div>
                    </div>
                    
                    <!-- Статистика -->
                    <div class="finance-stats-grid">
                        <div class="finance-stat-card">
                            <div class="finance-stat-header">
                                <div>
                                    <div class="finance-stat-title">Всего платежей за месяц</div>
                                    <div class="finance-stat-value"><?= number_format($stats['month']['count'] ?? 0, 0, ',', ' ') ?></div>
                                </div>
                                <div class="finance-stat-icon">📄</div>
                            </div>
                            <div style="font-size: 13px; color: #6b7280;">
                                На сумму <?= formatMoney($stats['month']['total'] ?? 0) ?>
                            </div>
                        </div>
                        
                        <div class="finance-stat-card income">
                            <div class="finance-stat-header">
                                <div>
                                    <div class="finance-stat-title">Доходы за месяц</div>
                                    <div class="finance-stat-value" style="color: #27ae60;"><?= formatMoney($stats['income']['total'] ?? 0) ?></div>
                                </div>
                                <div class="finance-stat-icon income">⬆️</div>
                            </div>
                            <div style="font-size: 13px; color: #6b7280;">
                                <?= $stats['income']['count'] ?? 0 ?> платежей
                            </div>
                        </div>
                        
                        <div class="finance-stat-card expense">
                            <div class="finance-stat-header">
                                <div>
                                    <div class="finance-stat-title">Расходы за месяц</div>
                                    <div class="finance-stat-value" style="color: #e74c3c;"><?= formatMoney($stats['expense']['total'] ?? 0) ?></div>
                                </div>
                                <div class="finance-stat-icon expense">⬇️</div>
                            </div>
                            <div style="font-size: 13px; color: #6b7280;">
                                <?= $stats['expense']['count'] ?? 0 ?> платежей
                            </div>
                        </div>
                        
                        <div class="finance-stat-card balance">
                            <div class="finance-stat-header">
                                <div>
                                    <div class="finance-stat-title">Сальдо за месяц</div>
                                    <div class="finance-stat-value" style="color: <?= ($stats['balance']['total'] ?? 0) >= 0 ? '#3498db' : '#e74c3c' ?>;">
                                        <?= formatMoney($stats['balance']['total'] ?? 0) ?>
                                    </div>
                                </div>
                                <div class="finance-stat-icon balance">⚖️</div>
                            </div>
                            <div style="font-size: 13px; color: #6b7280;">
                                Разница доходов и расходов
                            </div>
                        </div>
                    </div>
                    
                    <!-- Контент -->
                    <div class="finance-content-grid">
                        <!-- Последние платежи -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Последние платежи</h3>
                                <a href="list.php" class="btn btn-sm btn-secondary">Все платежи</a>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Номер</th>
                                                <th>Дата</th>
                                                <th>Тип</th>
                                                <th>Контрагент</th>
                                                <th>Сумма</th>
                                                <th>Статус</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentPayments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <a href="view.php?id=<?= $payment['id'] ?>" style="color: #3498db; text-decoration: none;">
                                                        <strong><?= e($payment['document_number']) ?></strong>
                                                    </a>
                                                </td>
                                                <td><?= formatDate($payment['document_date']) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $payment['flow_type'] === 'income' ? 'income' : 'expense' ?>" 
                                                          style="background: <?= $payment['flow_type'] === 'income' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $payment['flow_type'] === 'income' ? '#065f46' : '#991b1b' ?>;">
                                                        <?= $payment['flow_type'] === 'income' ? 'Доход' : 'Расход' ?>
                                                    </span>
                                                    <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                                        <?= e($payment['payment_type_name']) ?>
                                                    </div>
                                                </td>
                                                <td><?= e($payment['contractor_name'] ?? '—') ?></td>
                                                <td><strong><?= formatMoney($payment['amount']) ?></strong></td>
                                                <td>
                                                    <span class="badge" style="background: <?= e($payment['status_color']) ?>20; color: <?= e($payment['status_color']) ?>;">
                                                        <?= e($payment['status_name']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Счета -->
                        <div class="accounts-list">
                            <div style="padding: 16px; border-bottom: 1px solid #e5e7eb;">
                                <h3 style="margin: 0; font-size: 16px;">💳 Счета организации</h3>
                            </div>
                            <?php foreach ($accountsBalance as $account): ?>
                            <div class="account-item">
                                <div class="account-info">
                                    <h4><?= e($account['account_holder']) ?></h4>
                                    <p><?= e($account['bank_name']) ?></p>
                                    <p style="font-family: monospace;"><?= e($account['account_number']) ?></p>
                                </div>
                                <div class="account-balance">
                                    <div class="amount"><?= formatMoney($account['balance'], $account['currency']) ?></div>
                                    <div class="currency"><?= $account['currency'] ?></div>
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
</body>
</html>
