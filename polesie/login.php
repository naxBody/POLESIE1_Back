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
            <div class="login-logo">⚡</div>
            <h1 class="login-title">ОАО "Полесьеэлектромаш"</h1>
            <p class="login-subtitle">Система управления производством</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
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
                    <button type="button" onclick="togglePassword()" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; font-size: 16px;">👁️</button>
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
        
        <div style="margin-top: 24px; text-align: center; font-size: 13px; color: var(--text-secondary);">
            <p>Тестовые учетные записи (актуальные данные из БД):</p>
            <div style="margin-top: 12px; display: grid; gap: 8px; max-width: 600px; margin-left: auto; margin-right: auto;">
                <div style="background: #f0f9ff; padding: 10px; border-radius: 6px; border-left: 3px solid #2563eb;">
                    <p style="margin: 0;"><strong>admin / admin123</strong> — Администратор Системы (полный доступ)</p>
                </div>
                <div style="background: #fdf4f0; padding: 10px; border-radius: 6px; border-left: 3px solid #ea580c;">
                    <p style="margin: 0;"><strong>director / director123</strong> — Директор Предприятия (просмотр всех разделов)</p>
                </div>
                <div style="background: #fef3c7; padding: 10px; border-radius: 6px; border-left: 3px solid #d97706;">
                    <p style="margin: 0;"><strong>ivanov / manager123</strong> — Иванов Иван, Менеджер по продажам (заказы, контрагенты)</p>
                </div>
                <div style="background: #dcfce7; padding: 10px; border-radius: 6px; border-left: 3px solid #16a34a;">
                    <p style="margin: 0;"><strong>petrov / tech123</strong> — Петров Петр, Технолог (производство, продукция)</p>
                </div>
                <div style="background: #dbeafe; padding: 10px; border-radius: 6px; border-left: 3px solid #2563eb;">
                    <p style="margin: 0;"><strong>sidorov / store123</strong> — Сидоров Сидор, Кладовщик (склад, материалы)</p>
                </div>
                <div style="background: #f3e8ff; padding: 10px; border-radius: 6px; border-left: 3px solid #9333ea;">
                    <p style="margin: 0;"><strong>worker1 / worker123</strong> — Рабочий Алексей (производственные задания)</p>
                </div>
                <div style="background: #fce7f3; padding: 10px; border-radius: 6px; border-left: 3px solid #db2777;">
                    <p style="margin: 0;"><strong>quality1 / quality123</strong> — Контролер Ольга, Инспектор по качеству (проверки ОТК)</p>
                </div>
                <div style="background: #fef9c3; padding: 10px; border-radius: 6px; border-left: 3px solid #ca8a04;">
                    <p style="margin: 0;"><strong>accountant1 / account123</strong> — Бухгалтер Елена (финансы, отчеты)</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('assets/js/main.js') ?>"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = event.currentTarget;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.textContent = '🔒';
            } else {
                passwordInput.type = 'password';
                toggleButton.textContent = '👁️';
            }
        }
    </script>
</body>
</html>
