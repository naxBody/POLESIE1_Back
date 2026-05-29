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

$sql = "SELECT * FROM contractors WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR unp LIKE ? OR contact_person LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

if ($type) {
    $sql .= " AND type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY name ASC";

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
                    <div style="position: relative; flex: 1; min-width: 250px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" name="search" placeholder="Поиск по названию, УНП, контактному лицу..." value="<?= e($search) ?>" style="padding-left: 40px;">
                    </div>
                    <select name="type">
                        <option value="">Все типы</option>
                        <option value="customer" <?= $type === 'customer' ? 'selected' : '' ?>>Заказчик</option>
                        <option value="supplier" <?= $type === 'supplier' ? 'selected' : '' ?>>Поставщик</option>
                        <option value="both" <?= $type === 'both' ? 'selected' : '' ?>>Оба</option>
                    </select>
                    <button type="submit" class="btn btn-secondary">Найти</button>
                    <a href="list.php" class="btn btn-outline">Сброс</a>
                </div>
            </form>

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
