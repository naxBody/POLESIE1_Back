<?php
/**
 * Контроль качества (ОТК)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Контроль качества';

$status = $_GET['status'] ?? '';

$sql = "SELECT pt.*, pt.quantity_plan as task_quantity, p.name as product_name, 
               u.full_name as inspector_name,
               qc.id as check_id, qc.status as result, qc.check_date as inspection_date,
               CASE WHEN qc.id IS NOT NULL THEN TRUE ELSE FALSE END as is_inspected
        FROM production_tasks pt 
        JOIN products p ON pt.product_id = p.id 
        LEFT JOIN quality_checks qc ON pt.id = qc.task_id
        LEFT JOIN users u ON qc.inspector_id = u.id
        WHERE pt.status IN ('in_progress', 'completed')";
$params = [];

if ($status === 'pending') {
    $sql .= " AND qc.id IS NULL";
} elseif ($status === 'passed') {
    $sql .= " AND qc.status = 'pass'";
} elseif ($status === 'failed') {
    $sql .= " AND qc.status = 'fail'";
}

$sql .= " ORDER BY pt.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inspections = $stmt->fetchAll();
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
        <!-- Боковая панель -->
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <!-- Основной контент -->
        <div class="main-content">
            <!-- Верхняя панель -->
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <!-- Контентная область -->
            <div class="content-area">
                <div class="content">
                    <div class="page-header">
                        <div class="page-header-title">
                            <h2>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 10px;">
                                    <path d="M12 2L3 7V12C3 17.52 6.84 22.74 12 24C17.16 22.74 21 17.52 21 12V7L12 2Z" fill="url(#shield-gradient)" stroke="#4CAF50" stroke-width="1.5"/>
                                    <path d="M9 12L11 14L15 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <defs>
                                        <linearGradient id="shield-gradient" x1="3" y1="2" x2="21" y2="24">
                                            <stop offset="0%" stop-color="#66BB6A"/>
                                            <stop offset="100%" stop-color="#43A047"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                                ОТК - Контроль качества
                            </h2>
                            <p>Проверка качества продукции</p>
                        </div>
                        <div class="page-header-actions">
                            <?php if (hasPermission('quality.create')): ?>
                                <a href="create.php" class="btn btn-primary">+ Проверка</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <select name="status" style="width: 200px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                        <option value="">Все</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Ожидают проверки</option>
                        <option value="passed" <?= $status === 'passed' ? 'selected' : '' ?>>Пройдено</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Не пройдено</option>
                    </select>
                    <button type="submit" class="btn btn-secondary">Фильтр</button>
                    <a href="list.php" class="btn btn-outline">Сброс</a>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Задание</th>
                        <th>Продукция</th>
                        <th>Кол-во</th>
                        <th>Статус задания</th>
                        <th>Результат ОТК</th>
                        <th>Инспектор</th>
                        <th>Дата проверки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inspections as $i): ?>
                    <tr>
                        <td><strong>#<?= $i['task_id'] ?? '' ?></strong></td>
                        <td><?= e($i['product_name']) ?></td>
                        <td><?= $i['task_quantity'] ?? '' ?></td>
                        <td>
                            <?php if (($i['task_status'] ?? '') === 'completed'): ?>
                                <span class="badge badge-success">Завершено</span>
                            <?php else: ?>
                                <span class="badge badge-info">В работе</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$i['is_inspected']): ?>
                                <span class="badge badge-warning">Ожидает</span>
                            <?php elseif ($i['result'] === 'passed'): ?>
                                <span class="badge badge-success">✓ Пройдено</span>
                            <?php else: ?>
                                <span class="badge badge-danger">✗ Не пройдено</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($i['inspector_name'] ?? '—') ?></td>
                        <td><?= $i['inspection_date'] ? date('d.m.Y H:i', strtotime($i['inspection_date'])) : '—' ?></td>
                        <td>
                            <?php if (!$i['is_inspected'] && hasPermission('quality.create')): ?>
                                <a href="create.php?task=<?= (int)$i['task_id'] ?>" class="btn-icon" title="Проверить">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 2L3 7V12C3 17.52 6.84 22.74 12 24C17.16 22.74 21 17.52 21 12V7L12 2Z" fill="#66BB6A" stroke="#4CAF50" stroke-width="1.5"/>
                                        <path d="M9 12L11 14L15 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            <?php elseif ($i['is_inspected'] && hasPermission('quality.view')): ?>
                                <a href="view.php?id=<?= $i['id'] ?>" class="btn-icon" title="Просмотр">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12C2.73 16.39 7 19.5 12 19.5C17 19.5 21.27 16.39 23 12C21.27 7.61 17 4.5 12 4.5ZM12 17C9.24 17 7 14.76 7 12C7 9.24 9.24 7 12 7C14.76 7 17 9.24 17 12C17 14.76 14.76 17 12 17ZM12 9C10.34 9 9 10.34 9 12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12C15 10.34 13.66 9 12 9Z" fill="#2196F3" stroke="#1976D2" stroke-width="1.5"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($inspections)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L3 7V12C3 17.52 6.84 22.74 12 24C17.16 22.74 21 17.52 21 12V7L12 2Z" fill="#E8F5E9" stroke="#4CAF50" stroke-width="1.5"/>
                            <path d="M9 12L11 14L15 10" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>Нет заданий для проверки</h3>
                    <p>Задания появятся после начала производства</p>
                </div>
            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
