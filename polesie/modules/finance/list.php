<?php
/**
 * Модуль финансов и платежей - Список всех платежей
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

// Фильтры
$filters = [
    'status' => $_GET['status'] ?? '',
    'type' => $_GET['type'] ?? '',
    'contractor_id' => $_GET['contractor_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Построение запроса
$sql = "
    SELECT pd.*, 
        pt.name as payment_type_name, pt.type as flow_type, pt.category,
        c.name as contractor_name, c.inn as contractor_inn,
        ba.account_number as bank_account, ba.account_holder,
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
    WHERE 1=1
";

$params = [];

if ($filters['status']) {
    $sql .= " AND pd.status = ?";
    $params[] = $filters['status'];
}

if ($filters['type']) {
    $sql .= " AND pt.type = ?";
    $params[] = $filters['type'];
}

if ($filters['contractor_id']) {
    $sql .= " AND pd.contractor_id = ?";
    $params[] = $filters['contractor_id'];
}

if ($filters['date_from']) {
    $sql .= " AND pd.document_date >= ?";
    $params[] = $filters['date_from'];
}

if ($filters['date_to']) {
    $sql .= " AND pd.document_date <= ?";
    $params[] = $filters['date_to'];
}

if ($filters['search']) {
    $sql .= " AND (pd.document_number LIKE ? OR pd.description LIKE ? OR c.name LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY pd.document_date DESC, pd.document_number DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Списки для фильтров
$paymentTypes = $pdo->query("SELECT DISTINCT type, category FROM payment_types ORDER BY type, category")->fetchAll();
$contractors = $pdo->query("SELECT id, name FROM contractors ORDER BY name")->fetchAll();

$pageTitle = 'Все платежи';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filters-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            padding-top: 24px;
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
                    <!-- Заголовок -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <div>
                            <h1 style="font-size: 24px; font-weight: 700; color: #1f2937; margin: 0;"><i class="fas fa-file-invoice-dollar"></i> Все платежи</h1>
                            <p style="color: #6b7280; margin: 4px 0 0 0;"><i class="fas fa-list"></i> Реестр платежных документов</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <?php if (canCreateInModule('finance')): ?>
                            <a href="payment_create.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Новый платеж</a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-secondary"><i class="fas fa-home"></i> На главную</a>
                        </div>
                    </div>
                    
                    <!-- Фильтры -->
                    <div class="filters-panel">
                        <form method="GET" action="">
                            <div class="filters-grid">
                                <div class="filter-group">
                                    <label>Поиск</label>
                                    <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="№ документа, описание...">
                                </div>
                                <div class="filter-group">
                                    <label>Статус</label>
                                    <select name="status">
                                        <option value="">Все статусы</option>
                                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Черновик</option>
                                        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>На согласовании</option>
                                        <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Утвержден</option>
                                        <option value="posted" <?= $filters['status'] === 'posted' ? 'selected' : '' ?>>Проведен</option>
                                        <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Тип потока</label>
                                    <select name="type">
                                        <option value="">Все типы</option>
                                        <option value="income" <?= $filters['type'] === 'income' ? 'selected' : '' ?>>Доход</option>
                                        <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Расход</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Контрагент</label>
                                    <select name="contractor_id">
                                        <option value="">Все контрагенты</option>
                                        <?php foreach ($contractors as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $filters['contractor_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Дата с</label>
                                    <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                                </div>
                                <div class="filter-group">
                                    <label>Дата по</label>
                                    <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                                </div>
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Применить фильтры</button>
                                <a href="list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Сбросить</a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Таблица -->
                    <div class="card">
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-file-invoice"></i> Номер</th>
                                            <th><i class="far fa-calendar"></i> Дата</th>
                                            <th><i class="fas fa-tags"></i> Тип</th>
                                            <th><i class="fas fa-building"></i> Контрагент</th>
                                            <th><i class="fas fa-coins"></i> Сумма</th>
                                            <th><i class="fas fa-percent"></i> НДС</th>
                                            <th><i class="fas fa-university"></i> Счет</th>
                                            <th><i class="fas fa-circle-check"></i> Статус</th>
                                            <th><i class="fas fa-cog"></i> Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($payments)): ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 40px; color: #6b7280;">
                                                <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                                Платежи не найдены
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <a href="view.php?id=<?= $payment['id'] ?>" style="color: #3498db; text-decoration: none;">
                                                    <strong><i class="fas fa-file-invoice-dollar"></i> <?= e($payment['document_number']) ?></strong>
                                                </a>
                                            </td>
                                            <td><i class="far fa-calendar-alt"></i> <?= formatDate($payment['document_date']) ?></td>
                                            <td>
                                                <span style="font-size: 11px; padding: 4px 8px; border-radius: 4px; background: <?= $payment['flow_type'] === 'income' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $payment['flow_type'] === 'income' ? '#065f46' : '#991b1b' ?>; font-weight: 600;">
                                                    <i class="fas <?= $payment['flow_type'] === 'income' ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= $payment['flow_type'] === 'income' ? 'Доход' : 'Расход' ?>
                                                </span>
                                                <div style="font-size: 11px; color: #6b7280; margin-top: 4px;"><i class="fas fa-tag"></i> <?= e($payment['payment_type_name']) ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><i class="fas fa-building"></i> <?= e($payment['contractor_name'] ?? '—') ?></div>
                                                <?php if ($payment['contractor_inn']): ?>
                                                <div style="font-size: 11px; color: #6b7280;"><i class="fas fa-id-card"></i> ИНН: <?= e($payment['contractor_inn']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong style="color: <?= $payment['flow_type'] === 'income' ? '#27ae60' : '#e74c3c' ?>;"><i class="fas fa-money-bill-wave"></i> <?= formatMoney($payment['amount']) ?></strong></td>
                                            <td><?= $payment['vat_amount'] > 0 ? '<i class="fas fa-percent"></i> ' . formatMoney($payment['vat_amount']) : '<span style="color: #9ca3af;"><i class="fas fa-minus"></i></span>' ?></td>
                                            <td>
                                                <div style="font-size: 11px;"><i class="fas fa-user-tag"></i> <?= e($payment['account_holder']) ?></div>
                                                <div style="font-size: 10px; font-family: monospace; color: #6b7280;"><i class="fas fa-credit-card"></i> <?= e($payment['bank_account']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: <?= e($payment['status_color']) ?>20; color: <?= e($payment['status_color']) ?>; font-size: 11px; padding: 4px 8px; border-radius: 4px;">
                                                    <i class="fas <?= $payment['status'] === 'posted' ? 'fa-circle-check' : ($payment['status'] === 'approved' ? 'fa-user-check' : ($payment['status'] === 'pending' ? 'fa-clock' : ($payment['status'] === 'cancelled' ? 'fa-circle-xmark' : 'fa-file'))) ?>"></i> <?= e($payment['status_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 4px;">
                                                    <a href="view.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-secondary" title="Просмотр"><i class="fas fa-eye"></i></a>
                                                    <?php if (canEditInModule('finance') && $payment['status'] === 'draft'): ?>
                                                    <a href="payment_edit.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-primary" title="Редактировать"><i class="fas fa-pen-to-square"></i></a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Итого -->
                    <?php if (!empty($payments)): ?>
                    <div style="margin-top: 16px; padding: 16px; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-weight: 600; color: #374151;">
                                <i class="fas fa-chart-bar"></i> Всего в списке: <?= count($payments) ?> платежей
                            </div>
                            <div style="display: flex; gap: 24px;">
                                <div>
                                    <span style="color: #6b7280; font-size: 13px;"><i class="fas fa-arrow-up" style="color: #27ae60;"></i> Доходы:</span>
                                    <strong style="color: #27ae60; margin-left: 8px;">
                                        <?= formatMoney(array_sum(array_filter(array_column($payments, 'amount'), function($key, $index) use ($payments) {
                                            return $payments[$index]['flow_type'] === 'income';
                                        }, ARRAY_FILTER_USE_BOTH))) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span style="color: #6b7280; font-size: 13px;"><i class="fas fa-arrow-down" style="color: #e74c3c;"></i> Расходы:</span>
                                    <strong style="color: #e74c3c; margin-left: 8px;">
                                        <?= formatMoney(array_sum(array_filter(array_column($payments, 'amount'), function($key, $index) use ($payments) {
                                            return $payments[$index]['flow_type'] === 'expense';
                                        }, ARRAY_FILTER_USE_BOTH))) ?>
                                    </strong>
                                </div>
                            </div>
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
