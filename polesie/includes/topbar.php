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
        <div class="topbar-breadcrumb-wrapper">
            <div class="topbar-breadcrumb">
                <a href="<?= pageUrl('index.php') ?>">Главная</a>
                <span class="topbar-breadcrumb-separator">/</span>
                <span><?= e($pageTitle) ?></span>
            </div>
        </div>
    </div>
    
    <div class="topbar-right">
        <!-- Профиль -->
        <div style="position: relative;">
            <div class="topbar-action" onclick="toggleProfileMenu()" style="width: auto; padding: 8px 12px; gap: 8px;">
                <div style="width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary-color), #3b82f6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; font-weight: 600; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);">
                    <?= mb_substr($user['full_name'], 0, 1) ?>
                </div>
                <span style="font-size: 13px; font-weight: 500;"><?= e($user['full_name']) ?></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            
            <!-- Выпадающее меню профиля -->
            <div id="profileMenu" style="position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); min-width: 180px; display: none; z-index: 1000; overflow: hidden;">
                <div style="padding: 16px; border-bottom: 1px solid var(--border-color); background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), transparent);">
                    <div style="font-weight: 600; font-size: 14px;"><?= e($user['full_name']) ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;"><?= e($user['role_name']) ?></div>
                </div>
                <a href="<?= pageUrl('logout.php') ?>" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #ef4444; font-size: 13px; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.05)'" onmouseout="this.style.background='transparent'" onclick="return confirm('Вы уверены, что хотите выйти?')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Выйти
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
