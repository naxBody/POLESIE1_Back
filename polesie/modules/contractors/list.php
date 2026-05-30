<?php
/**
 * Список контрагентов
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$pageTitle = 'Контрагенты';

// Получение списка контрагентов
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';

$sql = "SELECT * FROM contractors WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR inn LIKE ? OR contact_person LIKE ? OR phone LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

if ($type) {
    $sql .= " AND type = ?";
    $params[] = $type;
}

// Сортировка
switch ($sort) {
    case 'name_desc':
        $sql .= " ORDER BY name DESC";
        break;
    case 'date_desc':
        $sql .= " ORDER BY created_at DESC";
        break;
    case 'date_asc':
        $sql .= " ORDER BY created_at ASC";
        break;
    case 'name_asc':
    default:
        $sql .= " ORDER BY name ASC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contractors = $stmt->fetchAll();

// Подсчет статистики
$statsSql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN type = 'customer' THEN 1 ELSE 0 END) as customers,
    SUM(CASE WHEN type = 'supplier' THEN 1 ELSE 0 END) as suppliers
    FROM contractors";
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
.contractors-stats {
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
.stat-card-mini.customers .stat-card-mini-value { color: var(--success-color); }
.stat-card-mini.suppliers .stat-card-mini-value { color: var(--info-color); }

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

/* Table action buttons - larger icons */
.data-table .btn-icon {
    width: 40px;
    height: 40px;
}

.data-table .btn-icon svg {
    width: 20px;
    height: 20px;
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

/* Contractor Avatar */
.contractor-avatar {
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

.contractor-name-cell {
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
    
    .contractors-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Контрагенты
            </h2>
            <p>База заказчиков и поставщиков</p>
        </div>
        <div class="page-header-actions">
            <?php if (hasPermission('contractors.create')): ?>
                <a href="create.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Добавить контрагента
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Статистика -->
    <div class="contractors-stats">
        <div class="stat-card-mini total">
            <div class="stat-card-mini-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-card-mini-label">Всего контрагентов</div>
        </div>
        <div class="stat-card-mini customers">
            <div class="stat-card-mini-value"><?= $stats['customers'] ?? 0 ?></div>
            <div class="stat-card-mini-label">Заказчиков</div>
        </div>
        <div class="stat-card-mini suppliers">
            <div class="stat-card-mini-value"><?= $stats['suppliers'] ?? 0 ?></div>
            <div class="stat-card-mini-label">Поставщиков</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Фильтры -->
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <!-- Поиск -->
                    <div style="position: relative; flex: 1; min-width: 250px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" name="search" placeholder="Название, ИНН, телефон..." value="<?= e($search) ?>" style="padding-left: 40px;">
                    </div>
                    
                    <!-- Тип контрагента -->
                    <select name="type" style="min-width: 150px;">
                        <option value="">Все типы</option>
                        <option value="customer" <?= $type === 'customer' ? 'selected' : '' ?>>Заказчик</option>
                        <option value="supplier" <?= $type === 'supplier' ? 'selected' : '' ?>>Поставщик</option>
                    </select>
                    
                    <!-- Сортировка -->
                    <select name="sort" style="min-width: 180px;">
                        <option value="name_asc" <?= ($sort ?? '') === 'name_asc' ? 'selected' : '' ?>>По названию (А-Я)</option>
                        <option value="name_desc" <?= ($sort ?? '') === 'name_desc' ? 'selected' : '' ?>>По названию (Я-А)</option>
                        <option value="date_desc" <?= ($sort ?? '') === 'date_desc' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="date_asc" <?= ($sort ?? '') === 'date_asc' ? 'selected' : '' ?>>Сначала старые</option>
                    </select>
                    
                    <!-- Кнопки действий -->
                    <button type="submit" class="btn btn-primary" title="Применить фильтры">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: middle;">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        Применить
                    </button>
                    <a href="list.php" class="btn btn-outline" title="Сбросить фильтры">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: middle;">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        Сброс
                    </a>
                </div>
            </form>

            <!-- Таблица контрагентов -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Название</th>
                            <th>Тип</th>
                            <th>ИНН</th>
                            <th>Контактное лицо</th>
                            <th>Контакты</th>
                            <th style="width: 120px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contractors as $c): ?>
                        <tr>
                            <td>
                                <div class="contractor-avatar">
                                    <?= mb_substr(mb_strtoupper($c['name']), 0, 1) ?>
                                </div>
                            </td>
                            <td><strong><?= e($c['name']) ?></strong></td>
                            <td>
                                <?php if ($c['type'] === 'customer'): ?>
                                    <span class="badge badge-info">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                        Заказчик
                                    </span>
                                <?php elseif ($c['type'] === 'supplier'): ?>
                                    <span class="badge badge-success">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                        Поставщик
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Оба</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($c['inn'] ?? '-') ?></td>
                            <td><?= e($c['contact_person'] ?? '-') ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php if (!empty($c['phone'])): ?>
                                        <a href="tel:<?= e($c['phone']) ?>" style="display: flex; align-items: center; color: var(--text-primary); text-decoration: none; font-size: 13px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; color: var(--text-secondary);"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            <?= e($c['phone']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($c['email'])): ?>
                                        <a href="mailto:<?= e($c['email']) ?>" style="display: flex; align-items: center; color: var(--text-primary); text-decoration: none; font-size: 13px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; color: var(--text-secondary);"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                            <?= e($c['email']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (empty($c['phone']) && empty($c['email'])): ?>
                                        <span style="color: var(--text-secondary); font-size: 13px;">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <?php if (hasPermission('contractors.view')): ?>
                                        <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-icon" title="Просмотр">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('contractors.edit')): ?>
                                        <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-icon" title="Редактировать">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('contractors.delete')): ?>
                                        <a href="delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-icon btn-danger" title="Удалить" onclick="return confirm('Вы уверены, что хотите удалить контрагента <?= e($c['name']) ?>?')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($contractors)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg></div>
                    <h3>Контрагенты не найдены</h3>
                    <p>Добавьте первого контрагента для начала работы</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
