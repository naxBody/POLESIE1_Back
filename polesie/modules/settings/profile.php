<?php
/**
 * Страница профиля пользователя
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

// Проверка авторизации
if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$error = '';
$success = '';

// Обработка формы обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($fullName)) {
        $error = 'Введите ФИО';
    } else {
        try {
            updateUser($user['id'], [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone
            ]);
            
            // Обновляем данные в сессии
            $user = getCurrentUser();
            $success = 'Профиль успешно обновлен';
            logActivity('profile_update', 'user', $user['id']);
        } catch (Exception $e) {
            $error = 'Ошибка обновления: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Профиль пользователя';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
    <div class="app-container">
        <!-- Боковая панель -->
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <!-- Основной контент -->
        <div class="main-content">
            <!-- Верхняя панель -->
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <!-- Контентная область -->
            <div class="content-area">
                <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <span>⚠️</span>
                    <?= e($error) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    <span>✅</span>
                    <?= e($success) ?>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">👤 Личная информация</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                                <div class="form-group">
                                    <label class="form-label" for="username">Логин</label>
                                    <input 
                                        type="text" 
                                        id="username" 
                                        name="username" 
                                        class="form-control" 
                                        value="<?= e($user['username']) ?>"
                                        disabled
                                        style="background: var(--gray-100);"
                                    >
                                    <small style="color: var(--text-muted);">Логин нельзя изменить</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="full_name">ФИО *</label>
                                    <input 
                                        type="text" 
                                        id="full_name" 
                                        name="full_name" 
                                        class="form-control" 
                                        value="<?= e($user['full_name']) ?>"
                                        required
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="email">Email</label>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        class="form-control" 
                                        value="<?= e($user['email'] ?? '') ?>"
                                        placeholder="example@mail.by"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="phone">Телефон</label>
                                    <input 
                                        type="tel" 
                                        id="phone" 
                                        name="phone" 
                                        class="form-control" 
                                        value="<?= e($user['phone'] ?? '') ?>"
                                        placeholder="+375 (XX) XXX-XX-XX"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Роль</label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        value="<?= e($user['role_name']) ?>"
                                        disabled
                                        style="background: var(--gray-100);"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Статус</label>
                                    <div style="padding: 12px 0;">
                                        <span class="badge badge-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                            <?= $user['is_active'] ? '✅ Активен' : '❌ Не активен' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 24px; display: flex; gap: 12px;">
                                <button type="submit" class="btn btn-primary">
                                    💾 Сохранить изменения
                                </button>
                                <a href="<?= pageUrl('index.php') ?>" class="btn btn-secondary">
                                    ← На главную
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Информация о пользователе -->
                <div class="card" style="margin-top: 24px;">
                    <div class="card-header">
                        <h3 class="card-title">📊 Информация об аккаунте</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                            <div style="padding: 16px; background: var(--gray-50); border-radius: var(--border-radius);">
                                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Дата регистрации</div>
                                <div style="font-weight: 600;"><?= formatDate($user['created_at']) ?></div>
                            </div>
                            <div style="padding: 16px; background: var(--gray-50); border-radius: var(--border-radius);">
                                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Последняя активность</div>
                                <div style="font-weight: 600;"><?= formatDate($user['created_at']) ?></div>
                            </div>
                            <div style="padding: 16px; background: var(--gray-50); border-radius: var(--border-radius);">
                                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">ID пользователя</div>
                                <div style="font-weight: 600;">#<?= $user['id'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
