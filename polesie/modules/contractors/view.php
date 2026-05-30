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

/* Info Grid */
.contractor-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.info-card {
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    transition: all var(--transition-fast);
}

.info-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    background: linear-gradient(180deg, var(--gray-50) 0%, var(--gray-100) 100%);
    border-bottom: 1px solid var(--border-color);
}

.info-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--primary-color);
    color: white;
}

.info-card-icon svg {
    width: 20px;
    height: 20px;
}

.info-card-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.info-card-body {
    padding: 20px;
}

.info-row {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    width: 160px;
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    flex-shrink: 0;
}

.info-value {
    flex: 1;
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 500;
    word-break: break-word;
}

.info-value a {
    color: var(--primary-color);
    text-decoration: none;
    transition: color var(--transition-fast);
}

.info-value a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Notes Section */
.notes-section {
    background: var(--bg-primary);
    border-radius: var(--border-radius);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
}

.notes-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.notes-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--warning-color);
    color: white;
}

.notes-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
}

.notes-content {
    font-size: 14px;
    line-height: 1.7;
    color: var(--text-secondary);
    background: var(--gray-50);
    padding: 16px;
    border-radius: var(--border-radius);
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
    background: rgba(59, 130, 246, 0.1);
    color: var(--info-color);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.badge-supplier {
    background: rgba(34, 197, 94, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.badge-both {
    background: rgba(168, 85, 247, 0.1);
    color: #a855f7;
    border: 1px solid rgba(168, 85, 247, 0.2);
}

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
    
    .contractor-info-grid {
        grid-template-columns: 1fr;
    }
    
    .contractor-actions {
        width: 100%;
        justify-content: center;
    }
    
    .info-row {
        flex-direction: column;
        gap: 8px;
    }
    
    .info-label {
        width: 100%;
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

    <!-- Info Cards -->
    <div class="contractor-info-grid">
        <!-- Основная информация -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </div>
                <div class="info-card-title">Основная информация</div>
            </div>
            <div class="info-card-body">
                <div class="info-row">
                    <div class="info-label">Название</div>
                    <div class="info-value"><?= e($contractor['name']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Тип</div>
                    <div class="info-value">
                        <?php if ($contractor['type'] === 'customer'): ?>
                            Заказчик
                        <?php elseif ($contractor['type'] === 'supplier'): ?>
                            Поставщик
                        <?php else: ?>
                            Заказчик и поставщик
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($contractor['inn'])): ?>
                <div class="info-row">
                    <div class="info-label">ИНН</div>
                    <div class="info-value"><?= e($contractor['inn']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['kpp'])): ?>
                <div class="info-row">
                    <div class="info-label">КПП</div>
                    <div class="info-value"><?= e($contractor['kpp']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['ogrn'])): ?>
                <div class="info-row">
                    <div class="info-label">ОГРН</div>
                    <div class="info-value"><?= e($contractor['ogrn']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Контакты -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-icon" style="background: var(--success-color);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                </div>
                <div class="info-card-title">Контактная информация</div>
            </div>
            <div class="info-card-body">
                <?php if (!empty($contractor['contact_person'])): ?>
                <div class="info-row">
                    <div class="info-label">Контактное лицо</div>
                    <div class="info-value"><?= e($contractor['contact_person']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['phone'])): ?>
                <div class="info-row">
                    <div class="info-label">Телефон</div>
                    <div class="info-value">
                        <a href="tel:<?= e($contractor['phone']) ?>"><?= e($contractor['phone']) ?></a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['email'])): ?>
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">
                        <a href="mailto:<?= e($contractor['email']) ?>"><?= e($contractor['email']) ?></a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['website'])): ?>
                <div class="info-row">
                    <div class="info-label">Веб-сайт</div>
                    <div class="info-value">
                        <a href="<?= e($contractor['website']) ?>" target="_blank"><?= e($contractor['website']) ?></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Адрес -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-icon" style="background: var(--info-color);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div class="info-card-title">Адрес</div>
            </div>
            <div class="info-card-body">
                <?php if (!empty($contractor['address'])): ?>
                <div class="info-row">
                    <div class="info-label">Юридический адрес</div>
                    <div class="info-value"><?= nl2br(e($contractor['address'])) ?></div>
                </div>
                <?php else: ?>
                <div class="info-row">
                    <div class="info-label">Адрес</div>
                    <div class="info-value" style="color: var(--text-secondary);">Не указан</div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($contractor['postal_code'])): ?>
                <div class="info-row">
                    <div class="info-label">Почтовый индекс</div>
                    <div class="info-value"><?= e($contractor['postal_code']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Банковские реквизиты -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-icon" style="background: var(--warning-color);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                </div>
                <div class="info-card-title">Банковские реквизиты</div>
            </div>
            <div class="info-card-body">
                <?php if (!empty($contractor['bank_name'])): ?>
                <div class="info-row">
                    <div class="info-label">Банк</div>
                    <div class="info-value"><?= e($contractor['bank_name']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['bik'])): ?>
                <div class="info-row">
                    <div class="info-label">БИК</div>
                    <div class="info-value"><?= e($contractor['bik']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['rs'])): ?>
                <div class="info-row">
                    <div class="info-label">Расчетный счет</div>
                    <div class="info-value"><?= e($contractor['rs']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contractor['ks'])): ?>
                <div class="info-row">
                    <div class="info-label">Корр. счет</div>
                    <div class="info-value"><?= e($contractor['ks']) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($contractor['bank_name']) && empty($contractor['bik']) && empty($contractor['rs']) && empty($contractor['ks'])): ?>
                <div class="info-row">
                    <div class="info-label">Реквизиты</div>
                    <div class="info-value" style="color: var(--text-secondary);">Не указаны</div>
                </div>
                <?php endif; ?>
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
    <div class="info-card" style="margin-top: 20px;">
        <div class="info-card-header">
            <div class="info-card-icon" style="background: var(--gray-500);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div class="info-card-title">Системная информация</div>
        </div>
        <div class="info-card-body">
            <div class="info-row">
                <div class="info-label">ID</div>
                <div class="info-value">#<?= $contractor['id'] ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Дата создания</div>
                <div class="info-value"><?= date('d.m.Y H:i', strtotime($contractor['created_at'])) ?></div>
            </div>
            <?php if (!empty($contractor['updated_at']) && $contractor['updated_at'] != $contractor['created_at']): ?>
            <div class="info-row">
                <div class="info-label">Дата обновления</div>
                <div class="info-value"><?= date('d.m.Y H:i', strtotime($contractor['updated_at'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
