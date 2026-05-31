<?php
/**
 * Просмотр контрагента
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$id = $_GET['id'] ?? 0;

if (!$id) {
    redirect(pageUrl('modules/contractors/list.php'));
}

// Получение информации о контрагенте
$stmt = $pdo->prepare("SELECT * FROM contractors WHERE id = ?");
$stmt->execute([$id]);
$contractor = $stmt->fetch();

if (!$contractor) {
    die('<div style="text-align: center; padding: 60px;"><h2>Контрагент не найден</h2><a href="list.php" class="btn btn-primary">Вернуться к списку</a></div>');
}

$pageTitle = $contractor['name'];

require_once BASE_PATH . '/includes/sidebar.php';
require_once BASE_PATH . '/includes/topbar.php';
?>

<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">

<style>
/* Contractor Detail Page - Simple Table Layout */
.contractor-detail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
}

.contractor-title h2 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 5px 0;
    color: var(--text-primary);
}

.contractor-type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 5px;
}

.contractor-type-badge.customer {
    background: rgba(59, 130, 246, 0.15);
    color: #2563eb;
}

.contractor-type-badge.supplier {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
}

.contractor-type-badge.both {
    background: rgba(168, 85, 247, 0.15);
    color: #9333ea;
}

.contractor-actions {
    display: flex;
    gap: 10px;
}

.btn-simple {
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-edit {
    background: var(--primary-color);
    color: white;
    border: none;
}

.btn-edit:hover {
    background: var(--primary-dark);
}

.btn-back {
    background: var(--gray-200);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-back:hover {
    background: var(--gray-300);
}

/* Main Table */
.contractor-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-primary);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.contractor-table th {
    background: var(--gray-100);
    padding: 14px 20px;
    text-align: left;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border-color);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.contractor-table td {
    padding: 12px 20px;
    vertical-align: top;
    border-bottom: 1px solid var(--border-color);
    font-size: 14px;
    line-height: 1.5;
}

.contractor-table td.label {
    width: 35%;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--gray-50);
}

.contractor-table td.value {
    width: 65%;
    color: var(--text-primary);
    font-weight: 500;
}

.contractor-table td.value a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
}

.contractor-table td.value a:hover {
    text-decoration: underline;
}

.section-icon {
    width: 18px;
    height: 18px;
    margin-right: 8px;
    vertical-align: middle;
    color: var(--text-secondary);
}

.contractor-table .section-row td {
    background: #fff;
    font-weight: 700;
    font-size: 15px;
    color: var(--text-primary);
    padding-top: 16px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--border-color);
    border-top: 2px solid var(--border-color);
}

.contractor-table .empty-value {
    color: var(--text-secondary);
    font-style: italic;
    font-weight: 400;
}

.contractor-table tr:last-child td {
    border-bottom: none;
}

.contractor-table tr:hover td {
    background: var(--gray-50);
}

.contractor-table tr.section-row:hover td {
    background: #fff;
}

/* Notes Row */
.notes-row td {
    background: #fffbeb !important;
    padding: 16px 20px;
}

.notes-content {
    font-size: 14px;
    line-height: 1.7;
    color: var(--text-primary);
}

/* Responsive */
@media (max-width: 768px) {
    .contractor-detail-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .contractor-actions {
        width: 100%;
    }
    
    .contractor-table td {
        display: block;
        width: 100%;
        padding: 10px 15px;
    }
    
    .contractor-table td.label {
        background: var(--gray-100);
        border-bottom: 1px solid var(--border-color);
    }
}
</style>

<div class="main-content">
    <!-- Header -->
    <div class="contractor-detail-header">
        <div class="contractor-title">
            <h2><?= e($contractor['name']) ?></h2>
            <?php if ($contractor['type'] === 'customer'): ?>
                <span class="contractor-type-badge customer">Заказчик</span>
            <?php elseif ($contractor['type'] === 'supplier'): ?>
                <span class="contractor-type-badge supplier">Поставщик</span>
            <?php else: ?>
                <span class="contractor-type-badge both">Заказчик и поставщик</span>
            <?php endif; ?>
        </div>
        
        <div class="contractor-actions">
            <?php if (hasPermission('contractors.edit')): ?>
                <a href="edit.php?id=<?= $contractor['id'] ?>" class="btn-simple btn-edit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Редактировать
                </a>
            <?php endif; ?>
            <a href="list.php" class="btn-simple btn-back">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Назад
            </a>
        </div>
    </div>

    <!-- Main Table -->
    <table class="contractor-table">
        <thead>
            <tr>
                <th colspan="2">Информация о контрагенте</th>
            </tr>
        </thead>
        <tbody>
            <!-- Основная информация -->
            <tr class="section-row">
                <td colspan="2">
                    <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Основная информация
                </td>
            </tr>
            <tr>
                <td class="label">Название</td>
                <td class="value"><?= e($contractor['name']) ?></td>
            </tr>
            <tr>
                <td class="label">Тип</td>
                <td class="value">
                    <?php if ($contractor['type'] === 'customer'): ?>
                        Заказчик
                    <?php elseif ($contractor['type'] === 'supplier'): ?>
                        Поставщик
                    <?php else: ?>
                        Заказчик и поставщик
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($contractor['inn'])): ?>
            <tr>
                <td class="label">ИНН</td>
                <td class="value"><?= e($contractor['inn']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['kpp'])): ?>
            <tr>
                <td class="label">КПП</td>
                <td class="value"><?= e($contractor['kpp']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['ogrn'])): ?>
            <tr>
                <td class="label">ОГРН</td>
                <td class="value"><?= e($contractor['ogrn']) ?></td>
            </tr>
            <?php endif; ?>
            
            <!-- Контакты -->
            <tr class="section-row">
                <td colspan="2">
                    <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    Контакты
                </td>
            </tr>
            <?php if (!empty($contractor['contact_person'])): ?>
            <tr>
                <td class="label">Контактное лицо</td>
                <td class="value"><?= e($contractor['contact_person']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['phone'])): ?>
            <tr>
                <td class="label">Телефон</td>
                <td class="value"><a href="tel:<?= e($contractor['phone']) ?>"><?= e($contractor['phone']) ?></a></td>
            </tr>
            <?php else: ?>
            <tr>
                <td class="label">Телефон</td>
                <td class="value"><span class="empty-value">Не указан</span></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['email'])): ?>
            <tr>
                <td class="label">Email</td>
                <td class="value"><a href="mailto:<?= e($contractor['email']) ?>"><?= e($contractor['email']) ?></a></td>
            </tr>
            <?php else: ?>
            <tr>
                <td class="label">Email</td>
                <td class="value"><span class="empty-value">Не указан</span></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['website'])): ?>
            <tr>
                <td class="label">Веб-сайт</td>
                <td class="value"><a href="<?= e($contractor['website']) ?>" target="_blank"><?= e($contractor['website']) ?></a></td>
            </tr>
            <?php else: ?>
            <tr>
                <td class="label">Веб-сайт</td>
                <td class="value"><span class="empty-value">Не указан</span></td>
            </tr>
            <?php endif; ?>
            
            <!-- Адрес -->
            <tr class="section-row">
                <td colspan="2">
                    <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    Адрес
                </td>
            </tr>
            <?php if (!empty($contractor['address'])): ?>
            <tr>
                <td class="label">Юридический адрес</td>
                <td class="value"><?= nl2br(e($contractor['address'])) ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <td class="label">Адрес</td>
                <td class="value"><span class="empty-value">Не указан</span></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['postal_code'])): ?>
            <tr>
                <td class="label">Почтовый индекс</td>
                <td class="value"><?= e($contractor['postal_code']) ?></td>
            </tr>
            <?php endif; ?>
            
            <!-- Примечания -->
            <?php if (!empty($contractor['notes'])): ?>
            <tr class="section-row">
                <td colspan="2">
                    <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Примечания
                </td>
            </tr>
            <tr class="notes-row">
                <td class="label">Заметки</td>
                <td class="value">
                    <div class="notes-content"><?= nl2br(e($contractor['notes'])) ?></div>
                </td>
            </tr>
            <?php endif; ?>
            
            <!-- Системная информация -->
            <tr class="section-row">
                <td colspan="2">
                    <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Системная информация
                </td>
            </tr>
            <tr>
                <td class="label">ID</td>
                <td class="value">#<?= $contractor['id'] ?></td>
            </tr>
            <tr>
                <td class="label">Дата создания</td>
                <td class="value"><?= date('d.m.Y H:i', strtotime($contractor['created_at'])) ?></td>
            </tr>
            <?php if (!empty($contractor['updated_at']) && $contractor['updated_at'] != $contractor['created_at']): ?>
            <tr>
                <td class="label">Дата обновления</td>
                <td class="value"><?= date('d.m.Y H:i', strtotime($contractor['updated_at'])) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
