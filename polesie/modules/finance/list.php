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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
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
                            <h1 style="font-size: 24px; font-weight: 700; color: #1f2937; margin: 0;"><i class="bi bi-cash-stack"></i> Все платежи</h1>
                            <p style="color: #6b7280; margin: 4px 0 0 0;"><i class="bi bi-list-ul"></i> Реестр платежных документов</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <?php if (canCreateInModule('finance')): ?>
                            <a href="payment_create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Новый платеж</a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-secondary"><i class="bi bi-house"></i> На главную</a>
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
                                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Применить фильтры</button>
                                <a href="list.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i> Сбросить</a>
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
                                            <th style="width: 12%;"><i class="bi bi-file-earmark-text"></i> Номер</th>
                                            <th style="width: 10%;"><i class="bi bi-calendar3"></i> Дата</th>
                                            <th style="width: 18%;"><i class="bi bi-tags"></i> Тип / Категория</th>
                                            <th style="width: 20%;"><i class="bi bi-building"></i> Контрагент</th>
                                            <th style="width: 15%; text-align: right;"><i class="bi bi-cash-coin"></i> Сумма</th>
                                            <th style="width: 15%;"><i class="bi bi-patch-check"></i> Статус</th>
                                            <th style="width: 10%; text-align: center;"><i class="bi bi-gear"></i> Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($payments)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                                <i class="bi bi-inbox" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                                Платежи не найдены
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td style="vertical-align: middle;">
                                                <a href="view.php?id=<?= $payment['id'] ?>" style="color: #3498db; text-decoration: none;">
                                                    <strong><i class="bi bi-file-earmark-text"></i> <?= e($payment['document_number']) ?></strong>
                                                </a>
                                            </td>
                                            <td style="vertical-align: middle;">
                                                <span style="font-size: 13px; font-weight: 500; color: #374151;">
                                                    <i class="bi bi-calendar3"></i> <?= formatDate($payment['document_date']) ?>
                                                </span>
                                            </td>
                                            <td style="vertical-align: middle;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 11px; padding: 4px 10px; border-radius: 6px; background: <?= $payment['flow_type'] === 'income' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $payment['flow_type'] === 'income' ? '#065f46' : '#991b1b' ?>; font-weight: 600; white-space: nowrap;">
                                                        <i class="fas <?= $payment['flow_type'] === 'income' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i> <?= $payment['flow_type'] === 'income' ? 'Доход' : 'Расход' ?>
                                                    </span>
                                                    <span style="font-size: 13px; color: #4b5563; font-weight: 500;">
                                                        <i class="bi bi-tag"></i> <?= e($payment['payment_type_name']) ?>
                                                    </span>
                                                </div>
                                                <?php if ($payment['category']): ?>
                                                <div style="font-size: 11px; color: #9ca3af; margin-top: 2px;">
                                                    <i class="bi bi-folder"></i> <?= e($payment['category']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="vertical-align: middle;">
                                                <div style="font-weight: 600; color: #1f2937; font-size: 13px;">
                                                    <i class="bi bi-building"></i> <?= e($payment['contractor_name'] ?? '—') ?>
                                                </div>
                                                <?php if ($payment['contractor_inn']): ?>
                                                <div style="font-size: 11px; color: #9ca3af; margin-top: 2px;">
                                                    <i class="bi bi-person-badge"></i> ИНН: <?= e($payment['contractor_inn']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right; vertical-align: middle;">
                                                <span style="font-size: 15px; font-weight: 700; color: <?= $payment['flow_type'] === 'income' ? '#27ae60' : '#e74c3c' ?>; white-space: nowrap;">
                                                    <?= number_format($payment['amount'], 2, ',', ' ') ?> <?= $payment['currency'] ?>
                                                </span>
                                            </td>
                                            <td style="vertical-align: middle;">
                                                <span class="badge" style="background: <?= e($payment['status_color']) ?>20; color: <?= e($payment['status_color']) ?>; font-size: 11px; padding: 5px 10px; border-radius: 6px; font-weight: 600;">
                                                    <i class="fas <?= $payment['status'] === 'posted' ? 'fa-circle-check' : ($payment['status'] === 'approved' ? 'fa-user-check' : ($payment['status'] === 'pending' ? 'fa-clock' : ($payment['status'] === 'cancelled' ? 'fa-circle-xmark' : 'fa-file'))) ?>"></i> <?= e($payment['status_name']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center; vertical-align: middle;">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?= $payment['id'] ?>" class="btn btn-outline-primary" title="Просмотр">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (canEditInModule('finance') && $payment['status'] === 'draft'): ?>
                                                    <a href="payment_edit.php?id=<?= $payment['id'] ?>" class="btn btn-outline-warning" title="Редактировать">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
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
                                <i class="bi bi-bar-chart-fill"></i> Всего в списке: <?= count($payments) ?> платежей
                            </div>
                            <div style="display: flex; gap: 24px;">
                                <div>
                                    <span style="color: #6b7280; font-size: 13px;"><i class="bi bi-arrow-up-circle-fill" style="color: #27ae60;"></i> Доходы:</span>
                                    <strong style="color: #27ae60; margin-left: 8px;">
                                        <?= formatMoney(array_sum(array_filter(array_column($payments, 'amount'), function($key, $index) use ($payments) {
                                            return $payments[$index]['flow_type'] === 'income';
                                        }, ARRAY_FILTER_USE_BOTH))) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span style="color: #6b7280; font-size: 13px;"><i class="bi bi-arrow-down-circle-fill" style="color: #e74c3c;"></i> Расходы:</span>
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
