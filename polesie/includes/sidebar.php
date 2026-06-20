<!-- Боковая панель навигации -->
<?php
// Функция для получения SVG иконки по коду
function getSidebarIcon($iconCode) {
    $icons = [
        'dashboard' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'orders' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>',
        'new-order' => '<path d="M12 5v14M5 12h14"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>',
        'contractors' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'production' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
        'execute' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'plan' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
        'products' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'passports' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>',
        'warehouse' => '<path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/>',
        'materials' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
        'receipt' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>',
        'docs' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line>',
        'quality' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
        'employees' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'finance' => '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
        'payments' => '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
        'reports' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
    ];
    
    return $icons[$iconCode] ?? '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>';
}

function renderSidebarIcon($iconCode) {
    $iconPath = getSidebarIcon($iconCode);
    echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $iconPath . '</svg>';
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('dashboard'); ?></span>
                <span>Панель управления</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Заказы - для менеджеров и тех, у кого есть доступ к модулю orders -->
        <?php if ($user['role_code'] === 'manager' || $user['role_code'] === 'admin' || in_array('orders', $availableModules) || canAccessModule('orders')): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Заказы</div>
            <a href="<?= pageUrl('modules/orders/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/orders/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('orders'); ?></span>
                <span>Все заказы</span>
            </a>
            <a href="<?= pageUrl('modules/orders/create.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/orders/create.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('new-order'); ?></span>
                <span>Новый заказ</span>
            </a>
            <a href="<?= pageUrl('modules/contractors/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/contractors/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('contractors'); ?></span>
                <span>Контрагенты</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Производство - для технологов и тех, у кого есть доступ к модулю production -->
        <?php if ($user['role_code'] === 'technologist' || $user['role_code'] === 'admin' || in_array('production', $availableModules) || canAccessModule('production')): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Производство</div>
            <a href="<?= pageUrl('modules/production/execute.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/production/execute.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('execute'); ?></span>
                <span>Исполнение производства</span>
            </a>
            <a href="<?= pageUrl('modules/production/plan.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/production/plan.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('plan'); ?></span>
                <span>План выпуска</span>
            </a>
            <a href="<?= pageUrl('modules/products/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/products/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('products'); ?></span>
                <span>Продукция</span>
            </a>
            <a href="<?= pageUrl('modules/products/passports.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/products/passports.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('passports'); ?></span>
                <span>Паспорта продуктов</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Склад - для кладовщиков и тех, у кого есть доступ к модулю warehouse -->
        <?php if ($user['role_code'] === 'storekeeper' || $user['role_code'] === 'admin' || in_array('warehouse', $availableModules) || canAccessModule('warehouse')): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Склад</div>
            <a href="<?= pageUrl('modules/warehouse/materials.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/materials.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('materials'); ?></span>
                <span>Материалы</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/receipt.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/receipt.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('receipt'); ?></span>
                <span>Поступление материалов</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('warehouse'); ?></span>
                <span>Остатки на складе</span>
            </a>
            <a href="<?= pageUrl('modules/warehouse/docs.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/warehouse/docs.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('docs'); ?></span>
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
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('quality'); ?></span>
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
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('employees'); ?></span>
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
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('finance'); ?></span>
                <span>Платежи</span>
            </a>
            <a href="<?= pageUrl('modules/finance/list.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/finance/list.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('payments'); ?></span>
                <span>Все платежи</span>
            </a>
            <a href="<?= pageUrl('modules/finance/reports.php') ?>" class="sidebar-nav-item <?= $relativePath === 'modules/finance/reports.php' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><?php renderSidebarIcon('reports'); ?></span>
                <span>Отчеты</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
</div>
