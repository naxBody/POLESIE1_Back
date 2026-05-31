<!-- Верхняя панель -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-title-wrapper">
            <div class="topbar-icon">
                <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" width="30" height="30">
                    <defs>
                        <linearGradient id="topbarLogoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#60a5fa;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#2563eb;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <!-- Звезда/искра -->
                    <path d="M16 2L18.4 7L24 7L19.6 11L21 16L16 13L11 16L12.4 11L8 7L13.6 7L16 2Z" fill="url(#topbarLogoGradient)" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" opacity="0.9"/>
                    <!-- Молния -->
                    <path d="M16.5 14L14.5 18L17 18L15 22" stroke="var(--primary-color)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" opacity="0.8"/>
                </svg>
            </div>
            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
        </div>
        <div class="topbar-breadcrumb">
            <a href="<?= pageUrl('index.php') ?>">Главная</a>
            <span class="topbar-breadcrumb-separator">/</span>
            <span><?= e($pageTitle) ?></span>
        </div>
    </div>
    
    <div class="topbar-right">
        <!-- Профиль -->
        <div style="position: relative;">
            <div class="topbar-action" onclick="toggleProfileMenu()" style="width: auto; padding: 8px 12px; gap: 8px;">
                <div style="width: 28px; height: 28px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 13px; font-weight: 600;">
                    <?= mb_substr($user['full_name'], 0, 1) ?>
                </div>
                <span style="font-size: 13px; font-weight: 500;"><?= e($user['full_name']) ?></span>
                <span style="color: var(--text-muted);">▼</span>
            </div>
            
            <!-- Выпадающее меню профиля -->
            <div id="profileMenu" style="position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--border-radius); box-shadow: var(--shadow-lg); min-width: 200px; display: none; z-index: 1000;">
                <div style="padding: 12px 16px; border-bottom: 1px solid var(--border-color);">
                    <div style="font-weight: 600;"><?= e($user['full_name']) ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary);"><?= e($user['role_name']) ?></div>
                </div>
                <a href="<?= pageUrl('settings/profile.php') ?>" style="display: block; padding: 10px 16px; color: var(--text-primary); font-size: 13px;">
                    👤 Профиль
                </a>
                <a href="<?= pageUrl('settings/password.php') ?>" style="display: block; padding: 10px 16px; color: var(--text-primary); font-size: 13px;">
                    🔒 Смена пароля
                </a>
                <div style="border-top: 1px solid var(--border-color);"></div>
                <a href="<?= pageUrl('logout.php') ?>" style="display: block; padding: 10px 16px; color: var(--danger-color); font-size: 13px;" onclick="return confirm('Вы уверены, что хотите выйти?')">
                    🚪 Выйти
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Закрытие меню при клике вне его
document.addEventListener('click', function(event) {
    const profileMenu = document.getElementById('profileMenu');
    const target = event.target.closest('.topbar-action');
    if (!target && profileMenu.style.display === 'block') {
        profileMenu.style.display = 'none';
    }
});
</script>
