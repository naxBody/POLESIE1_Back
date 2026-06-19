<?php
/**
 * Страница добавления сотрудника
 * /polesie/modules/employees/add.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

// Проверка авторизации
if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$errors = [];
$success = false;
$formData = [
    'full_name' => '',
    'position' => '',
    'department' => '',
    'email' => '',
    'phone' => '',
    'hire_date' => date('Y-m-d'),
    'salary' => '',
    'status' => 'active',
    'create_user' => 0,
    'username' => '',
    'password' => '',
    'role_id' => ''
];

// Получаем роли для выпадающего списка
$rolesStmt = $pdo->query("SELECT id, name, code FROM user_roles ORDER BY name");
$roles = $rolesStmt->fetchAll();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'position' => trim($_POST['position'] ?? ''),
        'department' => $_POST['department'] ?? '',
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'hire_date' => $_POST['hire_date'] ?? '',
        'salary' => trim($_POST['salary'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
        'create_user' => isset($_POST['create_user']) ? 1 : 0,
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'role_id' => $_POST['role_id'] ?? ''
    ];

    // Валидация
    if (empty($formData['full_name'])) {
        $errors[] = 'Введите ФИО сотрудника';
    }
    
    if (empty($formData['position'])) {
        $errors[] = 'Введите должность';
    }
    
    if (empty($formData['department'])) {
        $errors[] = 'Выберите отдел';
    }
    
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email';
    }
    
    if (!empty($formData['phone'])) {
        $cleanPhone = preg_replace('/[^\d+]/', '', $formData['phone']);
        if (strlen($cleanPhone) < 10) {
            $errors[] = 'Введите корректный номер телефона';
        }
    }
    
    if (empty($formData['hire_date'])) {
        $errors[] = 'Выберите дату приема на работу';
    }

    // Валидация для создания пользователя
    if ($formData['create_user']) {
        if (empty($formData['username'])) {
            $errors[] = 'Введите логин для пользователя системы';
        }
        
        if (empty($formData['password'])) {
            $errors[] = 'Введите пароль для пользователя системы';
        } elseif (strlen($formData['password']) < 6) {
            $errors[] = 'Пароль должен быть не менее 6 символов';
        }
        
        if (empty($formData['role_id'])) {
            $errors[] = 'Выберите роль для пользователя системы';
        }
    }

    // Если нет ошибок, сохраняем в БД
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Сначала создаем запись сотрудника
            $stmt = $pdo->prepare("
                INSERT INTO employees 
                (full_name, position, department, email, phone, hire_date, salary, is_active, created_at) 
                VALUES 
                (:full_name, :position, :department, :email, :phone, :hire_date, :salary, :is_active, NOW())
            ");
            
            $stmt->execute([
                ':full_name' => $formData['full_name'],
                ':position' => $formData['position'],
                ':department' => $formData['department'],
                ':email' => $formData['email'] ?: null,
                ':phone' => $formData['phone'] ?: null,
                ':hire_date' => $formData['hire_date'],
                ':salary' => $formData['salary'] ?: null,
                ':is_active' => $formData['status'] !== 'terminated' ? 1 : 0
            ]);
            
            $employeeId = $pdo->lastInsertId();
            
            // Если нужно создать пользователя системы
            if ($formData['create_user']) {
                // Проверяем, не занят ли username
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
                $checkStmt->execute([':username' => $formData['username']]);
                
                if ($checkStmt->fetch()) {
                    throw new Exception('Пользователь с таким логином уже существует');
                }
                
                // Создаем пользователя
                $userStmt = $pdo->prepare("
                    INSERT INTO users 
                    (username, password_hash, full_name, email, phone, role_id, department, position, is_active, created_at) 
                    VALUES 
                    (:username, :password_hash, :full_name, :email, :phone, :role_id, :department, :position, 1, NOW())
                ");
                
                $userStmt->execute([
                    ':username' => $formData['username'],
                    ':password_hash' => password_hash($formData['password'], PASSWORD_DEFAULT),
                    ':full_name' => $formData['full_name'],
                    ':email' => $formData['email'] ?: null,
                    ':phone' => $formData['phone'] ?: null,
                    ':role_id' => $formData['role_id'],
                    ':department' => $formData['department'],
                    ':position' => $formData['position']
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Связываем сотрудника с пользователем
                $linkStmt = $pdo->prepare("UPDATE employees SET user_id = :user_id WHERE id = :id");
                $linkStmt->execute([
                    ':user_id' => $userId,
                    ':id' => $employeeId
                ]);
            }
            
            $pdo->commit();
            $success = true;
            $formData = [
                'full_name' => '',
                'position' => '',
                'department' => '',
                'email' => '',
                'phone' => '',
                'hire_date' => date('Y-m-d'),
                'salary' => '',
                'status' => 'active',
                'create_user' => 0,
                'username' => '',
                'password' => '',
                'role_id' => ''
            ];
            
            // Перенаправление через 2 секунды
            header("Refresh: 2; URL=" . pageUrl('modules/employees/list.php'));
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Добавление сотрудника';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1.25rem 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .breadcrumb {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
            font-size: 0.875rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "/";
            padding: 0 0.5rem;
            color: #6c757d;
        }
        
        .breadcrumb-item a {
            text-decoration: none;
            color: #007bff;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-item.active {
            color: #6c757d;
        }
        
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: #fafbfc;
            border-radius: 8px 8px 0 0;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid #e9ecef;
            background: #fafbfc;
            border-radius: 0 0 8px 8px;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-label .required {
            color: #dc3545;
            margin-left: 0.25rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        
        .form-control-lg {
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        
        .form-select {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            background: #fff;
            font-size: 0.95rem;
            cursor: pointer;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        }
        
        .form-text {
            font-size: 0.825rem;
            color: #6c757d;
            margin-top: 0.375rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }
        
        .btn-primary {
            background: #007bff;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.25rem;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -0.75rem;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 0.75rem;
        }
        
        .col-md-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 0 0.75rem;
        }
        
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .card-footer {
                flex-direction: column;
            }
            
            .card-footer .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="content-wrapper">
                    <!-- Header страницы -->
                    <div class="page-header">
                        <div>
                            <h1 class="page-title">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                Добавление сотрудника
                            </h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="<?= pageUrl('index.php') ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>Главная</a></li>
                                <li class="breadcrumb-item"><a href="<?= pageUrl('modules/employees/list.php') ?>">Сотрудники</a></li>
                                <li class="breadcrumb-item active">Добавление</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Сообщения об успехе/ошибках -->
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <div>
                                <strong>Успешно!</strong> Сотрудник добавлен. Перенаправление в список сотрудников...
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <div>
                                <strong>Ошибки:</strong>
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Форма добавления сотрудника -->
                    <form method="POST" action="">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                        <polyline points="10 9 9 9 8 9"></polyline>
                                    </svg>
                                    Информация о сотруднике
                                </h3>
                            </div>
                            
                            <div class="card-body">
                                <!-- ФИО -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="form-label" for="full_name">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="12" cy="7" r="4"></circle>
                                                </svg>
                                                ФИО <span class="required">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control form-control-lg" 
                                                   id="full_name" 
                                                   name="full_name" 
                                                   value="<?= htmlspecialchars($formData['full_name']) ?>"
                                                   placeholder="Иванов Иван Иванович" 
                                                   required>
                                            <small class="form-text">Полное имя сотрудника</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Должность и Отдел -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="position">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                                </svg>
                                                Должность <span class="required">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="position" 
                                                   name="position" 
                                                   value="<?= htmlspecialchars($formData['position']) ?>"
                                                   placeholder="Менеджер по продажам" 
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="department">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <path d="M3 21h18M5 21V7l8-4 8 4v14M8 21v-4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4"></path>
                                                </svg>
                                                Отдел <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="department" name="department" required>
                                                <option value="">Выберите отдел</option>
                                                <option value="administration" <?= $formData['department'] === 'administration' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                                        <line x1="3" y1="9" x2="21" y2="9"></line>
                                                        <line x1="9" y1="21" x2="9" y2="9"></line>
                                                    </svg>
                                                    Администрация
                                                </option>
                                                <option value="sales" <?= $formData['department'] === 'sales' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                                    </svg>
                                                    Отдел продаж
                                                </option>
                                                <option value="marketing" <?= $formData['department'] === 'marketing' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <line x1="18" y1="20" x2="18" y2="10"></line>
                                                        <line x1="12" y1="20" x2="12" y2="4"></line>
                                                        <line x1="6" y1="20" x2="6" y2="14"></line>
                                                    </svg>
                                                    Маркетинг
                                                </option>
                                                <option value="production" <?= $formData['department'] === 'production' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                                                    </svg>
                                                    Производство
                                                </option>
                                                <option value="warehouse" <?= $formData['department'] === 'warehouse' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                                    </svg>
                                                    Склад
                                                </option>
                                                <option value="logistics" <?= $formData['department'] === 'logistics' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <rect x="1" y="3" width="15" height="13"></rect>
                                                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                                    </svg>
                                                    Логистика
                                                </option>
                                                <option value="accounting" <?= $formData['department'] === 'accounting' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                    </svg>
                                                    Бухгалтерия
                                                </option>
                                                <option value="hr" <?= $formData['department'] === 'hr' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="9" cy="7" r="4"></circle>
                                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                                    </svg>
                                                    HR-отдел
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email и Телефон -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="email">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                    <polyline points="22,6 12,13 2,6"></polyline>
                                                </svg>
                                                Email
                                            </label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   name="email" 
                                                   value="<?= htmlspecialchars($formData['email']) ?>"
                                                   placeholder="employee@polesie.by">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="phone">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                                </svg>
                                                Телефон
                                            </label>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?= htmlspecialchars($formData['phone']) ?>"
                                                   placeholder="+375 (29) 123-45-67">
                                            <small class="form-text">Формат: +375 (XX) XXX-XX-XX</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Дата приема и Зарплата -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="hire_date">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                                </svg>
                                                Дата приема <span class="required">*</span>
                                            </label>
                                            <input type="date" 
                                                   class="form-control" 
                                                   id="hire_date" 
                                                   name="hire_date" 
                                                   value="<?= htmlspecialchars($formData['hire_date']) ?>"
                                                   required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="salary">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <path d="M20 7h-3a2 2 0 0 1-2-2V2"></path>
                                                    <path d="M9 2v3a2 2 0 0 1-2 2H4"></path>
                                                    <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                                                    <circle cx="12" cy="14" r="2"></circle>
                                                </svg>
                                                Зарплата (BYN)
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="salary" 
                                                   name="salary" 
                                                   value="<?= htmlspecialchars($formData['salary']) ?>"
                                                   placeholder="2500"
                                                   min="0"
                                                   step="0.01">
                                        </div>
                                    </div>
                                </div>

                                <!-- Статус -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="form-label" for="status">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                                </svg>
                                                Статус
                                            </label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                    </svg>
                                                    Работает
                                                </option>
                                                <option value="vacation" <?= $formData['status'] === 'vacation' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M17 18a5 5 0 0 0-10 0"></path>
                                                        <line x1="12" y1="2" x2="12" y2="4"></line>
                                                        <path d="M5 9c0 4 3 7 7 7s7-3 7-7"></path>
                                                        <path d="M12 16v4"></path>
                                                        <path d="M8 20h8"></path>
                                                    </svg>
                                                    В отпуске
                                                </option>
                                                <option value="sick" <?= $formData['status'] === 'sick' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                                                    </svg>
                                                    На больничном
                                                </option>
                                                <option value="terminated" <?= $formData['status'] === 'terminated' ? 'selected' : '' ?>>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                                    </svg>
                                                    Уволен
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Секция создания пользователя системы -->
                        <div class="card" style="margin-top: 1.5rem; border: 2px solid #007bff;">
                            <div class="card-header" style="background: #e7f3ff; border-bottom: 2px solid #007bff;">
                                <h3 class="card-title" style="color: #0056b3;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                        <path d="M8 11h8"></path>
                                        <path d="M12 7v8"></path>
                                    </svg>
                                    Доступ к системе
                                </h3>
                            </div>
                            
                            <div class="card-body">
                                <div class="alert" style="background: #e7f3ff; border: 1px solid #b3d9ff; color: #0056b3; margin-bottom: 1.25rem;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-right: 0.5rem;">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    <div>
                                        <strong>Обратите внимание:</strong> Заполните данные сотрудника выше. 
                                        Если сотрудник должен иметь доступ к системе, отметьте чекбокс ниже и заполните поля для создания учетной записи.
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group" style="padding: 1rem; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                <input type="checkbox" 
                                                       id="create_user" 
                                                       name="create_user" 
                                                       value="1"
                                                       <?= $formData['create_user'] ? 'checked' : '' ?>
                                                       onchange="toggleUserFields()"
                                                       style="width: 20px; height: 20px; cursor: pointer;">
                                                <span style="font-weight: 600; font-size: 1rem; color: #1a1a1a;">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                    </svg>
                                                    Создать учетную запись пользователя системы
                                                </span>
                                            </label>
                                            <small class="form-text" style="margin-left: 28px; display: block;">
                                                Отметьте этот пункт, если сотрудник должен иметь логин и пароль для входа в систему
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div id="userFields" style="<?= $formData['create_user'] ? '' : 'display: none;' ?>">
                                    <hr style="margin: 1.5rem 0; border: none; border-top: 2px dashed #dee2e6;">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="username">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="12" cy="7" r="4"></circle>
                                                    </svg>
                                                    Логин <span class="required">*</span>
                                                </label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="username" 
                                                       name="username" 
                                                       value="<?= htmlspecialchars($formData['username']) ?>"
                                                       placeholder="ivanov.i">
                                                <small class="form-text">Уникальное имя пользователя для входа в систему</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="password">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                        <circle cx="12" cy="16" r="1"></circle>
                                                    </svg>
                                                    Пароль <span class="required">*</span>
                                                </label>
                                                <input type="password" 
                                                       class="form-control" 
                                                       id="password" 
                                                       name="password" 
                                                       value=""
                                                       placeholder="••••••••"
                                                       minlength="6">
                                                <small class="form-text">Минимум 6 символов. Рекомендуется использовать заглавные буквы и цифры</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="form-label" for="role_id">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;">
                                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                                    </svg>
                                                    Роль в системе <span class="required">*</span>
                                                </label>
                                                <select class="form-select" id="role_id" name="role_id">
                                                    <option value="">Выберите роль</option>
                                                    <?php foreach ($roles as $role): ?>
                                                        <option value="<?= $role['id'] ?>" <?= $formData['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($role['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="form-text">Роль определяет права доступа пользователя в системе (например, менеджер, администратор, бухгалтер)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer" style="margin-top: 1.5rem;">
                            <a href="<?= pageUrl('modules/employees/list.php') ?>" class="btn btn-secondary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="19" y1="12" x2="5" y2="12"></line>
                                    <polyline points="12 19 5 12 12 5"></polyline>
                                </svg>
                                Назад к списку
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Сохранить сотрудника
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleUserFields() {
        const checkbox = document.getElementById('create_user');
        const fields = document.getElementById('userFields');
        fields.style.display = checkbox.checked ? 'block' : 'none';
        
        // Делаем поля обязательными только если чекбокс отмечен
        const username = document.getElementById('username');
        const password = document.getElementById('password');
        const roleId = document.getElementById('role_id');
        
        if (checkbox.checked) {
            username.setAttribute('required', 'required');
            password.setAttribute('required', 'required');
            roleId.setAttribute('required', 'required');
        } else {
            username.removeAttribute('required');
            password.removeAttribute('required');
            roleId.removeAttribute('required');
        }
    }
    </script>
    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
