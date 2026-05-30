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
/* Contractor Detail Page Styles */
.contractor-detail-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 20px;
    padding: 24px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    border-radius: var(--border-radius);
    color: white;
    box-shadow: var(--shadow-md);
}

.contractor-detail-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.contractor-avatar-large {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    font-size: 32px;
    font-weight: 700;
    color: white;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.contractor-title h2 {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.contractor-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.contractor-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    opacity: 0.9;
}

.contractor-actions {
    display: flex;
    gap: 12px;
}

.btn-white {
    background: white;
    color: var(--primary-color);
    border: none;
    padding: 10px 20px;
    border-radius: var(--border-radius);
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all var(--transition-fast);
}

.btn-white:hover {
    background: var(--gray-100);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-outline-white {
    background: transparent;
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.5);
    padding: 10px 20px;
    border-radius: var(--border-radius);
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all var(--transition-fast);
}

.btn-outline-white:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: white;
}

/* Info Cards with Tables */
.contractor-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.detail-card {
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    transition: all var(--transition-fast);
}

.detail-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-3px);
}

.detail-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px 24px;
    background: linear-gradient(180deg, var(--gray-50) 0%, var(--gray-100) 100%);
    border-bottom: 2px solid var(--border-color);
}

.detail-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--primary-color);
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.detail-card-icon svg {
    width: 22px;
    height: 22px;
}

.detail-card-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-card-body {
    padding: 0;
    overflow-x: auto;
}

/* Beautiful Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table tbody tr {
    border-bottom: 1px solid var(--border-color);
    transition: background var(--transition-fast);
}

.data-table tbody tr:last-child {
    border-bottom: none;
}

.data-table tbody tr:hover {
    background: var(--gray-50);
}

.data-table td {
    padding: 16px 24px;
    vertical-align: top;
}

.data-table .label-cell {
    width: 40%;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--gray-50);
    border-right: 1px solid var(--border-color);
}

.data-table .value-cell {
    width: 60%;
    font-size: 15px;
    color: var(--text-primary);
    font-weight: 500;
    word-break: break-word;
    line-height: 1.6;
}

.data-table .value-cell a {
    color: var(--primary-color);
    text-decoration: none;
    transition: color var(--transition-fast);
    font-weight: 600;
}

.data-table .value-cell a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.data-table .empty-value {
    color: var(--text-secondary);
    font-style: italic;
    font-weight: 400;
}

/* Full Width Card */
.full-width-card {
    grid-column: 1 / -1;
}

/* Notes Section */
.notes-section {
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    padding: 0;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.notes-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px 24px;
    background: linear-gradient(180deg, #fef3c7 0%, #fde68a 100%);
    border-bottom: 2px solid #fcd34d;
}

.notes-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--warning-color);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.notes-title {
    font-size: 17px;
    font-weight: 700;
    color: #92400e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.notes-content {
    font-size: 15px;
    line-height: 1.8;
    color: var(--text-primary);
    background: #fffbeb;
    padding: 24px;
    border-left: 4px solid var(--warning-color);
}

/* Badge styles */
.badge-large {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.badge-customer {
    background: rgba(59, 130, 246, 0.15);
    color: #2563eb;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.badge-supplier {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.badge-both {
    background: rgba(168, 85, 247, 0.15);
    color: #9333ea;
    border: 1px solid rgba(168, 85, 247, 0.3);
}

/* Icon colors for different sections */
.icon-success { background: var(--success-color) !important; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3) !important; }
.icon-info { background: var(--info-color) !important; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important; }
.icon-warning { background: var(--warning-color) !important; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3) !important; }
.icon-gray { background: var(--gray-500) !important; box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3) !important; }

/* Responsive */
@media (max-width: 768px) {
    .contractor-detail-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .contractor-detail-info {
        flex-direction: column;
        align-items: center;
    }
    
    .contractor-sections {
        grid-template-columns: 1fr;
    }
    
    .contractor-actions {
        width: 100%;
        justify-content: center;
    }
    
    .data-table .label-cell,
    .data-table .value-cell {
        display: block;
        width: 100%;
        padding: 12px 16px;
    }
    
    .data-table .label-cell {
        border-right: none;
        border-bottom: 1px solid var(--border-color);
        background: var(--gray-100);
    }
    
    .data-table tbody tr {
        display: block;
    }
}
</style>

<div class="main-content">
    <!-- Header -->
    <div class="contractor-detail-header">
        <div class="contractor-detail-info">
            <div class="contractor-avatar-large">
                <?= mb_substr(mb_strtoupper($contractor['name']), 0, 1) ?>
            </div>
            <div class="contractor-title">
                <h2><?= e($contractor['name']) ?></h2>
                <div class="contractor-meta">
                    <?php if ($contractor['type'] === 'customer'): ?>
                        <span class="badge-large badge-customer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Заказчик
                        </span>
                    <?php elseif ($contractor['type'] === 'supplier'): ?>
                        <span class="badge-large badge-supplier">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                            Поставщик
                        </span>
                    <?php else: ?>
                        <span class="badge-large badge-both">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Заказчик и поставщик
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($contractor['inn'])): ?>
                        <div class="contractor-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>
                            ИНН: <?= e($contractor['inn']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="contractor-actions">
            <?php if (hasPermission('contractors.edit')): ?>
                <a href="edit.php?id=<?= $contractor['id'] ?>" class="btn-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Редактировать
                </a>
            <?php endif; ?>
            <a href="list.php" class="btn-outline-white">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Назад
            </a>
        </div>
    </div>

    <!-- Info Cards with Tables -->
    <div class="contractor-sections">
        <!-- Основная информация -->
        <div class="detail-card full-width-card">
            <div class="detail-card-header">
                <div class="detail-card-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </div>
                <div class="detail-card-title">Основная информация</div>
            </div>
            <div class="detail-card-body">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <td class="label-cell">Название</td>
                            <td class="value-cell"><?= e($contractor['name']) ?></td>
                        </tr>
                        <tr>
                            <td class="label-cell">Тип</td>
                            <td class="value-cell">
                                <?php if ($contractor['type'] === 'customer'): ?>
                                    👤 Заказчик
                                <?php elseif ($contractor['type'] === 'supplier'): ?>
                                    🚚 Поставщик
                                <?php else: ?>
                                    🔄 Заказчик и поставщик
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($contractor['inn'])): ?>
                        <tr>
                            <td class="label-cell">ИНН</td>
                            <td class="value-cell"><?= e($contractor['inn']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['kpp'])): ?>
                        <tr>
                            <td class="label-cell">КПП</td>
                            <td class="value-cell"><?= e($contractor['kpp']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['ogrn'])): ?>
                        <tr>
                            <td class="label-cell">ОГРН</td>
                            <td class="value-cell"><?= e($contractor['ogrn']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Контакты -->
        <div class="detail-card">
            <div class="detail-card-header">
                <div class="detail-card-icon icon-success">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                </div>
                <div class="detail-card-title">Контакты</div>
            </div>
            <div class="detail-card-body">
                <table class="data-table">
                    <tbody>
                        <?php if (!empty($contractor['contact_person'])): ?>
                        <tr>
                            <td class="label-cell">Контактное лицо</td>
                            <td class="value-cell"><?= e($contractor['contact_person']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['phone'])): ?>
                        <tr>
                            <td class="label-cell">Телефон</td>
                            <td class="value-cell">
                                <a href="tel:<?= e($contractor['phone']) ?>">📞 <?= e($contractor['phone']) ?></a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="label-cell">Телефон</td>
                            <td class="value-cell"><span class="empty-value">Не указан</span></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['email'])): ?>
                        <tr>
                            <td class="label-cell">Email</td>
                            <td class="value-cell">
                                <a href="mailto:<?= e($contractor['email']) ?>">✉️ <?= e($contractor['email']) ?></a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="label-cell">Email</td>
                            <td class="value-cell"><span class="empty-value">Не указан</span></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['website'])): ?>
                        <tr>
                            <td class="label-cell">Веб-сайт</td>
                            <td class="value-cell">
                                <a href="<?= e($contractor['website']) ?>" target="_blank">🌐 <?= e($contractor['website']) ?></a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="label-cell">Веб-сайт</td>
                            <td class="value-cell"><span class="empty-value">Не указан</span></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Адрес -->
        <div class="detail-card">
            <div class="detail-card-header">
                <div class="detail-card-icon icon-info">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div class="detail-card-title">Адрес</div>
            </div>
            <div class="detail-card-body">
                <table class="data-table">
                    <tbody>
                        <?php if (!empty($contractor['address'])): ?>
                        <tr>
                            <td class="label-cell">Юридический адрес</td>
                            <td class="value-cell"><?= nl2br(e($contractor['address'])) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="label-cell">Адрес</td>
                            <td class="value-cell"><span class="empty-value">Не указан</span></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($contractor['postal_code'])): ?>
                        <tr>
                            <td class="label-cell">Почтовый индекс</td>
                            <td class="value-cell"><?= e($contractor['postal_code']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Банковские реквизиты -->
        <div class="detail-card">
            <div class="detail-card-header">
                <div class="detail-card-icon icon-warning">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                </div>
                <div class="detail-card-title">Банковские реквизиты</div>
            </div>
            <div class="detail-card-body">
                <table class="data-table">
                    <tbody>
                        <?php if (!empty($contractor['bank_name'])): ?>
                        <tr>
                            <td class="label-cell">Банк</td>
                            <td class="value-cell"><?= e($contractor['bank_name']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['bik'])): ?>
                        <tr>
                            <td class="label-cell">БИК</td>
                            <td class="value-cell"><?= e($contractor['bik']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['rs'])): ?>
                        <tr>
                            <td class="label-cell">Расчетный счет</td>
                            <td class="value-cell"><?= e($contractor['rs']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($contractor['ks'])): ?>
                        <tr>
                            <td class="label-cell">Корр. счет</td>
                            <td class="value-cell"><?= e($contractor['ks']) ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (empty($contractor['bank_name']) && empty($contractor['bik']) && empty($contractor['rs']) && empty($contractor['ks'])): ?>
                        <tr>
                            <td class="label-cell">Реквизиты</td>
                            <td class="value-cell"><span class="empty-value">Не указаны</span></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Примечания -->
    <?php if (!empty($contractor['notes'])): ?>
    <div class="notes-section">
        <div class="notes-header">
            <div class="notes-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            </div>
            <div class="notes-title">Примечания</div>
        </div>
        <div class="notes-content">
            <?= nl2br(e($contractor['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Мета-информация -->
    <div class="detail-card full-width-card" style="margin-top: 20px;">
        <div class="detail-card-header">
            <div class="detail-card-icon icon-gray">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div class="detail-card-title">Системная информация</div>
        </div>
        <div class="detail-card-body">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td class="label-cell">ID</td>
                        <td class="value-cell">#<?= $contractor['id'] ?></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Дата создания</td>
                        <td class="value-cell"><?= date('d.m.Y H:i', strtotime($contractor['created_at'])) ?></td>
                    </tr>
                    <?php if (!empty($contractor['updated_at']) && $contractor['updated_at'] != $contractor['created_at']): ?>
                    <tr>
                        <td class="label-cell">Дата обновления</td>
                        <td class="value-cell"><?= date('d.m.Y H:i', strtotime($contractor['updated_at'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
