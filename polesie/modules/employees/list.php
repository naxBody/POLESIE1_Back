<?php
/**
 * Список сотрудников
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Сотрудники';

$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT e.*, u.username, r.name as role_name 
        FROM employees e 
        LEFT JOIN users u ON e.user_id = u.id 
        LEFT JOIN user_roles r ON u.role_id = r.id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (e.full_name LIKE ? OR e.position LIKE ? OR e.email LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

if ($department) {
    $sql .= " AND e.department = ?";
    $params[] = $department;
}

if ($status !== '') {
    $sql .= " AND e.is_active = ?";
    $params[] = $status === 'active' ? 1 : 0;
}

$sql .= " ORDER BY e.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Подсчет статистики
$statsSql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM employees";
$statsStmt = $pdo->query($statsSql);
$stats = $statsStmt->fetch();

require_once BASE_PATH . '/includes/sidebar.php';
require_once BASE_PATH . '/includes/topbar.php';
?>

<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">

<style>
/* Page Header */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 20px;
    padding: 20px 0;
}

.page-header-title h2 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.page-header-title p {
    font-size: 13px;
    color: var(--text-secondary);
}

.page-header-actions {
    display: flex;
    gap: 12px;
}

/* Stats Cards */
.employees-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card-mini {
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    padding: 16px 20px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    transition: all var(--transition-fast);
}

.stat-card-mini:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.stat-card-mini-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 4px;
}

.stat-card-mini-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-card-mini.total .stat-card-mini-value { color: var(--primary-color); }
.stat-card-mini.active .stat-card-mini-value { color: var(--success-color); }
.stat-card-mini.inactive .stat-card-mini-value { color: var(--text-muted); }

/* Filter Form */
.filter-form {
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-row input[type="text"],
.filter-row select {
    padding: 10px 14px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 14px;
    background: var(--bg-primary);
    transition: all var(--transition-fast);
}

.filter-row input[type="text"]:focus,
.filter-row select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-row input[type="text"] {
    flex: 1;
    min-width: 250px;
}

.filter-row select {
    min-width: 180px;
    cursor: pointer;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.data-table thead th {
    padding: 14px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    background: linear-gradient(180deg, var(--gray-50) 0%, var(--gray-100) 100%);
    border-bottom: 2px solid var(--border-color);
}

.data-table tbody td {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    font-size: 14px;
}

.data-table tbody tr {
    transition: background var(--transition-fast);
}

.data-table tbody tr:hover {
    background: linear-gradient(90deg, rgba(37, 99, 235, 0.03) 0%, transparent 100%);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* Badges */
.badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    margin-right: 6px;
}

/* Buttons */
.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--gray-100);
    border: 1px solid var(--border-color);
    cursor: pointer;
    text-decoration: none;
    color: var(--text-secondary);
    transition: all var(--transition-fast);
}

.btn-icon:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: scale(1.1);
}

.btn-icon.delete:hover {
    background: var(--danger-color);
    border-color: var(--danger-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.empty-state p {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 24px;
}

/* Avatar */
.employee-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    color: white;
    font-weight: 600;
    font-size: 14px;
    margin-right: 12px;
}

.employee-name-cell {
    display: flex;
    align-items: center;
}

/* Responsive */
@media (max-width: 1024px) {
    .data-table {
        font-size: 13px;
    }
    
    .data-table thead th,
    .data-table tbody td {
        padding: 12px 10px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-row input[type="text"],
    .filter-row select {
        min-width: 100%;
    }
    
    .employees-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>👥 Сотрудники</h2>
            <p>Управление персоналом предприятия</p>
        </div>
        <div class="page-header-actions">
            <?php if (hasPermission('employees.create')): ?>
                <a href="add.php" class="btn btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Добавить сотрудника
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Статистика -->
    <div class="employees-stats">
        <div class="stat-card-mini total">
            <div class="stat-card-mini-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-card-mini-label">Всего сотрудников</div>
        </div>
        <div class="stat-card-mini active">
            <div class="stat-card-mini-value"><?= $stats['active'] ?? 0 ?></div>
            <div class="stat-card-mini-label">Работают</div>
        </div>
        <div class="stat-card-mini inactive">
            <div class="stat-card-mini-value"><?= $stats['inactive'] ?? 0 ?></div>
            <div class="stat-card-mini-label">Уволены</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Фильтры -->
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="🔍 Поиск по ФИО, должности, email..." value="<?= e($search) ?>">
                    <select name="department">
                        <option value="">Все отделы</option>
                        <option value="production" <?= $department === 'production' ? 'selected' : '' ?>>🏭 Производство</option>
                        <option value="quality" <?= $department === 'quality' ? 'selected' : '' ?>>✅ ОТК</option>
                        <option value="warehouse" <?= $department === 'warehouse' ? 'selected' : '' ?>>📦 Склад</option>
                        <option value="management" <?= $department === 'management' ? 'selected' : '' ?>>👔 Руководство</option>
                        <option value="sales" <?= $department === 'sales' ? 'selected' : '' ?>>📞 Отдел продаж</option>
                        <option value="it" <?= $department === 'it' ? 'selected' : '' ?>>💻 IT отдел</option>
                        <option value="hr" <?= $department === 'hr' ? 'selected' : '' ?>>👥 HR отдел</option>
                        <option value="accounting" <?= $department === 'accounting' ? 'selected' : '' ?>>📊 Бухгалтерия</option>
                    </select>
                    <select name="status">
                        <option value="">Все статусы</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Работают</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Уволены</option>
                    </select>
                    <button type="submit" class="btn btn-secondary">Найти</button>
                    <a href="list.php" class="btn btn-outline">Сброс</a>
                </div>
            </form>

            <!-- Таблица сотрудников -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Сотрудник</th>
                            <th>Должность</th>
                            <th>Отдел</th>
                            <th>Контакты</th>
                            <th>Роль в системе</th>
                            <th>Статус</th>
                            <th style="width: 120px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($employees as $e): 
                            $initials = mb_substr(preg_replace('/[^А-Яа-яЁё]/u', '', $e['full_name']), 0, 2);
                            if (empty($initials)) {
                                $initials = mb_substr($e['full_name'], 0, 2);
                            }
                        ?>
                        <tr>
                            <td style="color: var(--text-muted); font-size: 13px;"><?= $counter++ ?></td>
                            <td>
                                <div class="employee-name-cell">
                                    <div class="employee-avatar"><?= e(mb_strtoupper($initials)) ?></div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?= e($e['full_name']) ?></div>
                                        <?php if ($e['username']): ?>
                                            <div style="font-size: 12px; color: var(--text-muted);">@<?= e($e['username']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?= e($e['position']) ?></div>
                            </td>
                            <td>
                                <?php 
                                $deptLabels = [
                                    'production' => 'Производство',
                                    'quality' => 'ОТК',
                                    'warehouse' => 'Склад',
                                    'management' => 'Руководство',
                                    'sales' => 'Продажи',
                                    'it' => 'IT',
                                    'hr' => 'HR',
                                    'accounting' => 'Бухгалтерия',
                                ];
                                $deptLabel = $deptLabels[$e['department']] ?? e($e['department']);
                                ?>
                                <span class="badge badge-secondary"><?= $deptLabel ?></span>
                            </td>
                            <td>
                                <?php if ($e['phone']): ?>
                                    <a href="tel:<?= e($e['phone']) ?>" style="font-size: 13px; color: var(--text-secondary); text-decoration: none;">
                                        📞 <?= e($e['phone']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($e['email']): ?>
                                    <div style="font-size: 13px; color: var(--text-secondary);">
                                        ✉️ <?= e($e['email']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$e['phone'] && !$e['email']): ?>
                                    <span style="color: var(--text-muted); font-size: 13px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($e['role_name']): ?>
                                    <span class="badge badge-secondary"><?= e($e['role_name']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($e['is_active']): ?>
                                    <span class="badge badge-success">Работает</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Уволен</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <?php if (hasPermission('employees.edit')): ?>
                                        <a href="edit.php?id=<?= $e['id'] ?>" class="btn-icon" title="Редактировать">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('employees.delete')): ?>
                                        <a href="delete.php?id=<?= $e['id'] ?>" class="btn-icon delete" title="Удалить" onclick="return confirm('Вы уверены, что хотите удалить этого сотрудника?')">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($employees)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <h3>Сотрудники не найдены</h3>
                    <p>Измените параметры поиска или добавьте первого сотрудника</p>
                    <?php if (hasPermission('employees.create')): ?>
                        <a href="create.php" class="btn btn-primary">+ Добавить сотрудника</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
