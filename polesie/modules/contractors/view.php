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

.contractor-table .section-row td {
    background: var(--primary-light);
    font-weight: 700;
    font-size: 15px;
    color: var(--primary-dark);
    padding-top: 16px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--border-color);
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
    background: var(--primary-light);
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
                    ✏️ Редактировать
                </a>
            <?php endif; ?>
            <a href="list.php" class="btn-simple btn-back">
                ← Назад
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
                <td colspan="2">📋 Основная информация</td>
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
                <td colspan="2">📞 Контакты</td>
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
                <td colspan="2">📍 Адрес</td>
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
            
            <!-- Банковские реквизиты -->
            <tr class="section-row">
                <td colspan="2">🏦 Банковские реквизиты</td>
            </tr>
            <?php if (!empty($contractor['bank_name'])): ?>
            <tr>
                <td class="label">Банк</td>
                <td class="value"><?= e($contractor['bank_name']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['bik'])): ?>
            <tr>
                <td class="label">БИК</td>
                <td class="value"><?= e($contractor['bik']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['rs'])): ?>
            <tr>
                <td class="label">Расчетный счет</td>
                <td class="value"><?= e($contractor['rs']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($contractor['ks'])): ?>
            <tr>
                <td class="label">Корр. счет</td>
                <td class="value"><?= e($contractor['ks']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (empty($contractor['bank_name']) && empty($contractor['bik']) && empty($contractor['rs']) && empty($contractor['ks'])): ?>
            <tr>
                <td class="label">Реквизиты</td>
                <td class="value"><span class="empty-value">Не указаны</span></td>
            </tr>
            <?php endif; ?>
            
            <!-- Примечания -->
            <?php if (!empty($contractor['notes'])): ?>
            <tr class="section-row">
                <td colspan="2">📝 Примечания</td>
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
                <td colspan="2">⚙️ Системная информация</td>
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
