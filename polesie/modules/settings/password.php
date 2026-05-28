<?php
/**
 * Страница смены пароля пользователя
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

// Обработка формы смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Заполните все поля';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Новые пароли не совпадают';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } else {
        try {
            if (changePassword($user['id'], $oldPassword, $newPassword)) {
                $success = 'Пароль успешно изменён';
                logActivity('password_change', 'user', $user['id']);
            } else {
                $error = 'Неверный текущий пароль';
            }
        } catch (Exception $e) {
            $error = 'Ошибка: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Смена пароля';
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
                
                <div class="card" style="max-width: 600px;">
                    <div class="card-header">
                        <h3 class="card-title">🔒 Смена пароля</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label" for="old_password">Текущий пароль *</label>
                                <input 
                                    type="password" 
                                    id="old_password" 
                                    name="old_password" 
                                    class="form-control" 
                                    required
                                    autofocus
                                >
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="new_password">Новый пароль *</label>
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="form-control" 
                                    required
                                    minlength="6"
                                >
                                <small style="color: var(--text-muted);">Минимум 6 символов</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Подтверждение пароля *</label>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    required
                                    minlength="6"
                                >
                            </div>
                            
                            <div style="margin-top: 24px; display: flex; gap: 12px;">
                                <button type="submit" class="btn btn-primary">
                                    🔑 Изменить пароль
                                </button>
                                <a href="<?= pageUrl('index.php') ?>" class="btn btn-secondary">
                                    ← На главную
                                </a>
                            </div>
                        </form>
                        
                        <div style="margin-top: 24px; padding: 16px; background: var(--gray-50); border-radius: var(--border-radius); border-left: 3px solid var(--info-color);">
                            <h4 style="margin-bottom: 8px; font-size: 14px;">💡 Требования к паролю:</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--text-secondary);">
                                <li>Минимум 6 символов</li>
                                <li>Рекомендуется использовать буквы и цифры</li>
                                <li>Не используйте простые пароли (123456, password)</li>
                                <li>Регулярно меняйте пароль для безопасности</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
