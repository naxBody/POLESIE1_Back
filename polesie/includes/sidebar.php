<!-- Боковая панель навигации -->
<?php
// Подключаем Font Awesome для иконок, если еще не подключено
if (!isset($GLOBALS['font_awesome_loaded'])) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' . "\n";
    $GLOBALS['font_awesome_loaded'] = true;
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                </svg>
            </div>
            <span class="sidebar-logo-text">Полесьеэлектромаш</span>
        </div>
    </div>
    
    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <div class="sidebar-user-avatar">
                <?= mb_substr($user['full_name'], 0, 1) ?>
            </div>
            <div class="sidebar-user-details">
                <div class="sidebar-user-name"><?= e($user['full_name']) ?></div>
                <div class="sidebar-user-role"><?= e($user['role_name']) ?></div>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Основное -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Основное</div>
            <?php
            // Получаем полный путь относительно корня приложения
            $currentPath = $_SERVER['PHP_SELF'];
            // Извлекаем относительный путь после /polesie/ (регистронезависимо)
            if (preg_match('#/polesie/(.*)$#i', $currentPath, $matches)) {
                $relativePath = $matches[1];
            } else {
                $relativePath = ltrim($currentPath, '/');
            }
            
            // Получаем доступные модули для текущего пользователя
            $availableModules = getAvailableModules();
            ?>
            <?php if ($user['role_code'] === 'director' || $user['role_code'] === 'admin'): ?>
            <a href="<?= pageUrl('index.php') ?>" class="sidebar-nav-item <?= $relativePath === 'index.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span>
                <span>Панель управления</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Заказы - для менеджеров и тех, у кого есть доступ к модулю orders -->
        <?php if ($user['role_code'] === 'manager' || $user['role_code'] === 'admin' || in_array('orders', $availableModules) || canAccessModule('orders')): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Заказы</div>
            <a href="<?= pageUrl('modules/orders/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/orders/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-box"></i></span>
                <span>Все заказы</span>
            </a>
            <a href="<?= pageUrl('modules/orders/create.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/orders/create.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span>
                <span>Новый заказ</span>
            </a>
            <a href="<?= pageUrl('modules/contractors/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/contractors/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-building"></i></span>
                <span>Контрагенты</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Производство - для технологов и тех, у кого есть доступ к модулю production -->
        <?php if ($user['role_code'] === 'technologist' || $user['role_code'] === 'admin' || in_array('production', $availableModules) || canAccessModule('production')): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Производство</div>
            <a href="<?= pageUrl('modules/production/execute.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/production/execute.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-industry"></i></span>
                <span>Исполнение производства</span>
            </a>
            <a href="<?= pageUrl('modules/production/plan.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/production/plan.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                <span>План выпуска</span>
            </a>
            <a href="<?= pageUrl('modules/products/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/products/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-tools"></i></span>
                <span>Продукция</span>
            </a>
            <a href="<?= pageUrl('modules/products/passports.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/products/passports.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-file-alt"></i></span>
                <span>Паспорта продуктов</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Склад - для кладовщиков и тех, у кого есть доступ к модулю warehouse -->
        <?php if ($user['role_code'] === 'storekeeper' || $user['role_code'] === 'admin' || in_array('warehouse', $availableModules) || canAccessModule('warehouse')): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Склад</div>
            <a href="<?= pageUrl('modules/warehouse/materials.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/materials.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-boxes"></i></span>
                <span>Материалы</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/receipt.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/receipt.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-dolly"></i></span>
                <span>Поступление материалов</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-warehouse"></i></span>
                <span>Остатки на складе</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/docs.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/docs.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                <span>Документы и справочники</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Контроль качества - доступно инспекторам ОТК и админам -->
        <?php if ($user['role_code'] === 'inspector' || $user['role_code'] === 'admin' || in_array('quality', $availableModules)): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Контроль качества</div>
            <?php if (canAccessModule('quality') || $user['role_code'] === 'admin'): ?>
            <a href="<?= pageUrl('modules/quality/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/quality/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span>
                <span>Проверки</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Сотрудники - доступно директору и админам -->
        <?php if ($user['role_code'] === 'director' || $user['role_code'] === 'admin' || in_array('employees', $availableModules)): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Сотрудники</div>
            <?php if (canAccessModule('employees') || $user['role_code'] === 'admin'): ?>
            <a href="<?= pageUrl('modules/employees/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/employees/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-users"></i></span>
                <span>Все сотрудники</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Финансы - доступно бухгалтеру и админам -->
        <?php if ($user['role_code'] === 'accountant' || $user['role_code'] === 'admin' || in_array('finance', $availableModules)): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Финансы</div>
            <a href="<?= pageUrl('modules/finance/index.php') ?>" class="sidebar-nav-item <?= strpos($relativePath, 'modules/finance/') === 0 ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span>
                <span>Платежи</span>
            </a>
            <a href="<?= pageUrl('modules/finance/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/finance/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                <span>Все платежи</span>
            </a>
            <a href="<?= pageUrl('modules/finance/reports.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/finance/reports.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                <span>Отчеты</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
</div>
