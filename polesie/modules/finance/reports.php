<?php
/**
 * Модуль финансов и платежей - Отчеты
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

// Параметры отчета
$reportType = $_GET['type'] ?? 'cashflow';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Движение денежных средств
$cashFlowData = $pdo->prepare("
    SELECT * FROM v_cash_flow 
    WHERE document_date BETWEEN ? AND ?
    ORDER BY document_date DESC, document_number DESC
");
$cashFlowData->execute([$dateFrom, $dateTo]);
$cashFlow = $cashFlowData->fetchAll();

// Итого
$totalIncome = array_sum(array_column($cashFlow, 'income_amount'));
$totalExpense = array_sum(array_column($cashFlow, 'expense_amount'));

// По контрагентам
$contractorData = $pdo->query("SELECT * FROM v_payments_by_contractor ORDER BY total_amount DESC LIMIT 20")->fetchAll();

// По месяцам (динамика)
$monthlyData = $pdo->query("
    SELECT 
        period,
        SUM(CASE WHEN payment_type = 'income' THEN total_amount ELSE 0 END) as income,
        SUM(CASE WHEN payment_type = 'expense' THEN total_amount ELSE 0 END) as expense
    FROM v_payments_summary
    GROUP BY period
    ORDER BY period DESC
    LIMIT 12
")->fetchAll();

$pageTitle = 'Финансовые отчеты';
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
        .reports-container {
            padding: 24px;
        }
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0;
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.2s;
        }
        .tab:hover {
            color: #3498db;
        }
        .tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        .summary-item {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
        }
        .summary-item.income {
            background: #d1fae5;
        }
        .summary-item.expense {
            background: #fee2e2;
        }
        .summary-item.balance {
            background: #dbeafe;
        }
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .summary-label {
            font-size: 13px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .chart-placeholder {
            height: 300px;
            background: #f9fafb;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
        }
        .filters-row {
            display: flex;
            gap: 16px;
            align-items: end;
            margin-bottom: 20px;
        }
        .filter-field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        .filter-field input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="reports-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <div>
                            <h1 style="font-size: 24px; font-weight: 700; color: #1f2937; margin: 0;">📊 Финансовые отчеты</h1>
                            <p style="color: #6b7280; margin: 4px 0 0 0;">Аналитика и отчетность по платежам</p>
                        </div>
                        <a href="index.php" class="btn btn-secondary">🏠 На главную</a>
                    </div>
                    
                    <!-- Вкладки -->
                    <div class="tabs">
                        <a href="?type=cashflow" class="tab <?= $reportType === 'cashflow' ? 'active' : '' ?>">Движение денежных средств</a>
                        <a href="?type=contractors" class="tab <?= $reportType === 'contractors' ? 'active' : '' ?>">По контрагентам</a>
                        <a href="?type=monthly" class="tab <?= $reportType === 'monthly' ? 'active' : '' ?>">Динамика по месяцам</a>
                    </div>
                    
                    <!-- Фильтры для DMS -->
                    <?php if ($reportType === 'cashflow'): ?>
                    <form method="GET" class="report-card">
                        <input type="hidden" name="type" value="cashflow">
                        <div class="filters-row">
                            <div class="filter-field">
                                <label>Период с</label>
                                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                            </div>
                            <div class="filter-field">
                                <label>Период по</label>
                                <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary" style="align-self: flex-end;">🔍 Применить</button>
                            <a href="?type=cashflow" class="btn btn-secondary" style="align-self: flex-end;">Сбросить</a>
                        </div>
                    </form>
                    
                    <!-- Итого за период -->
                    <div class="summary-grid">
                        <div class="summary-item income">
                            <div class="summary-value" style="color: #27ae60;"><?= formatMoney($totalIncome) ?></div>
                            <div class="summary-label">Доходы</div>
                        </div>
                        <div class="summary-item expense">
                            <div class="summary-value" style="color: #e74c3c;"><?= formatMoney($totalExpense) ?></div>
                            <div class="summary-label">Расходы</div>
                        </div>
                        <div class="summary-item balance">
                            <div class="summary-value" style="color: <?= ($totalIncome - $totalExpense) >= 0 ? '#3498db' : '#e74c3c' ?>;">
                                <?= formatMoney($totalIncome - $totalExpense) ?>
                            </div>
                            <div class="summary-label">Сальдо</div>
                        </div>
                    </div>
                    
                    <!-- Таблица DMS -->
                    <div class="report-card">
                        <h3 style="margin: 0 0 16px 0;">Движение денежных средств</h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Документ</th>
                                        <th>Тип</th>
                                        <th>Контрагент</th>
                                        <th>Назначение</th>
                                        <th>Доход</th>
                                        <th>Расход</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cashFlow as $item): ?>
                                    <tr>
                                        <td><?= formatDate($item['document_date']) ?></td>
                                        <td><strong><?= e($item['document_number']) ?></strong></td>
                                        <td>
                                            <span style="font-size: 11px; padding: 4px 8px; border-radius: 4px; background: <?= $item['flow_type'] === 'income' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $item['flow_type'] === 'income' ? '#065f46' : '#991b1b' ?>;">
                                                <?= e($item['payment_type_name']) ?>
                                            </span>
                                        </td>
                                        <td><?= e($item['contractor_name'] ?? '—') ?></td>
                                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($item['description'] ?? '—') ?></td>
                                        <td style="color: #27ae60;"><?= $item['income_amount'] > 0 ? formatMoney($item['income_amount']) : '—' ?></td>
                                        <td style="color: #e74c3c;"><?= $item['expense_amount'] > 0 ? formatMoney($item['expense_amount']) : '—' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f9fafb; font-weight: 600;">
                                        <td colspan="5" style="text-align: right;">Итого:</td>
                                        <td style="color: #27ae60;"><?= formatMoney($totalIncome) ?></td>
                                        <td style="color: #e74c3c;"><?= formatMoney($totalExpense) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <?php elseif ($reportType === 'contractors'): ?>
                    <!-- По контрагентам -->
                    <div class="report-card">
                        <h3 style="margin: 0 0 16px 0;">Расчеты с контрагентами</h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Контрагент</th>
                                        <th>ИНН</th>
                                        <th>Тип</th>
                                        <th>Кол-во платежей</th>
                                        <th>Общая сумма</th>
                                        <th>Первый платеж</th>
                                        <th>Последний платеж</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contractorData as $item): ?>
                                    <tr>
                                        <td><strong><?= e($item['contractor_name']) ?></strong></td>
                                        <td><?= e($item['contractor_inn'] ?? '—') ?></td>
                                        <td>
                                            <span style="font-size: 11px; padding: 4px 8px; border-radius: 4px; background: <?= $item['payment_type'] === 'income' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $item['payment_type'] === 'income' ? '#065f46' : '#991b1b' ?>;">
                                                <?= $item['payment_type'] === 'income' ? 'Покупатель' : 'Поставщик' ?>
                                            </span>
                                        </td>
                                        <td><?= $item['payments_count'] ?></td>
                                        <td><strong><?= formatMoney($item['total_amount']) ?></strong></td>
                                        <td><?= formatDate($item['first_payment']) ?></td>
                                        <td><?= formatDate($item['last_payment']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php elseif ($reportType === 'monthly'): ?>
                    <!-- Динамика по месяцам -->
                    <div class="report-card">
                        <h3 style="margin: 0 0 16px 0;">Динамика доходов и расходов по месяцам</h3>
                        
                        <!-- Простая визуализация -->
                        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
                            <?php foreach ($monthlyData as $month): ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 13px;">
                                    <span><?= e($month['period']) ?></span>
                                    <span>
                                        <span style="color: #27ae60;">+<?= formatMoney($month['income']) ?></span>
                                        /
                                        <span style="color: #e74c3c;">-<?= formatMoney($month['expense']) ?></span>
                                    </span>
                                </div>
                                <div style="display: flex; height: 20px; border-radius: 4px; overflow: hidden; background: #f3f4f6;">
                                    <?php 
                                    $maxAmount = max(array_column($monthlyData, 'income')) ?: 1;
                                    $incomeWidth = ($month['income'] / $maxAmount) * 100;
                                    $expenseWidth = ($month['expense'] / $maxAmount) * 100;
                                    ?>
                                    <div style="width: <?= $incomeWidth ?>%; background: #27ae60;" title="Доходы"></div>
                                    <div style="width: <?= $expenseWidth ?>%; background: #e74c3c;" title="Расходы"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
