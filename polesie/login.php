<?php
/**
 * Страница входа в систему
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
session_start();

// Если уже авторизован - перенаправляем на главную
if (isLoggedIn()) {
    redirect(pageUrl('index.php'));
}

$error = '';
$success = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Введите логин и пароль';
    } elseif (login($username, $password)) {
        logActivity('login_success', 'user', null, null, ['username' => $username]);
        redirect(pageUrl('index.php'));
    } else {
        $error = 'Неверный логин или пароль';
        logActivity('login_failed', 'user', null, null, ['username' => $username]);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                </svg>
            </div>
            <h1 class="login-title">ОАО "Полесьеэлектромаш"</h1>
            <p class="login-subtitle">Система управления производством</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <?= e($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <?= e($success) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Логин</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="Введите логин"
                    value="<?= e($username ?? '') ?>"
                    required 
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <div style="position: relative;">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Введите пароль"
                        required
                        style="padding-right: 40px;"
                    >
                    <button type="button" onclick="togglePassword()" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center;">
                        <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="remember" style="width: 16px; height: 16px;">
                    <span style="font-size: 13px; color: var(--text-secondary);">Запомнить меня</span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 15px;">
                Войти в систему
            </button>
        </form>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = event.currentTarget;
            const eyeIcon = toggleButton.querySelector('.eye-icon');
            const eyeOffIcon = toggleButton.querySelector('.eye-off-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        }
    </script>
</body>
</html>
