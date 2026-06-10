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
    'notes' => ''
];

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
        'notes' => trim($_POST['notes'] ?? '')
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

    // Если нет ошибок, сохраняем в БД
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO employees 
                (full_name, position, department, email, phone, hire_date, salary, is_active, notes, created_at) 
                VALUES 
                (:full_name, :position, :department, :email, :phone, :hire_date, :salary, :is_active, :notes, NOW())
            ");
            
            $stmt->execute([
                ':full_name' => $formData['full_name'],
                ':position' => $formData['position'],
                ':department' => $formData['department'],
                ':email' => $formData['email'] ?: null,
                ':phone' => $formData['phone'] ?: null,
                ':hire_date' => $formData['hire_date'],
                ':salary' => $formData['salary'] ?: null,
                ':is_active' => $formData['status'] !== 'terminated' ? 1 : 0,
                ':notes' => $formData['notes'] ?: null
            ]);
            
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
                'notes' => ''
            ];
            
            // Перенаправление через 2 секунды
            header("Refresh: 2; URL=" . pageUrl('modules/employees/list.php'));
            
        } catch (PDOException $e) {
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
                                <span style="font-size: 1.75rem;">👤</span>
                                Добавление сотрудника
                            </h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="<?= pageUrl('index.php') ?>">Главная</a></li>
                                <li class="breadcrumb-item"><a href="<?= pageUrl('modules/employees/list.php') ?>">Сотрудники</a></li>
                                <li class="breadcrumb-item active">Добавление</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Сообщения об успехе/ошибках -->
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <span style="font-size: 1.25rem;">✅</span>
                            <div>
                                <strong>Успешно!</strong> Сотрудник добавлен. Перенаправление в список сотрудников...
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <span style="font-size: 1.25rem;">⚠️</span>
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
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span>📋</span>
                                Информация о сотруднике
                            </h3>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="card-body">
                                <!-- ФИО -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="form-label" for="full_name">
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
                                                Отдел <span class="required">*</span>
                                            </label>
                                            <select class="form-select" id="department" name="department" required>
                                                <option value="">Выберите отдел</option>
                                                <option value="administration" <?= $formData['department'] === 'administration' ? 'selected' : '' ?>>🏢 Администрация</option>
                                                <option value="sales" <?= $formData['department'] === 'sales' ? 'selected' : '' ?>>💼 Отдел продаж</option>
                                                <option value="marketing" <?= $formData['department'] === 'marketing' ? 'selected' : '' ?>>📈 Маркетинг</option>
                                                <option value="production" <?= $formData['department'] === 'production' ? 'selected' : '' ?>>🏭 Производство</option>
                                                <option value="warehouse" <?= $formData['department'] === 'warehouse' ? 'selected' : '' ?>>📦 Склад</option>
                                                <option value="logistics" <?= $formData['department'] === 'logistics' ? 'selected' : '' ?>>🚚 Логистика</option>
                                                <option value="accounting" <?= $formData['department'] === 'accounting' ? 'selected' : '' ?>>💰 Бухгалтерия</option>
                                                <option value="hr" <?= $formData['department'] === 'hr' ? 'selected' : '' ?>>👥 HR-отдел</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email и Телефон -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="email">
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
                                                Статус
                                            </label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>✅ Работает</option>
                                                <option value="vacation" <?= $formData['status'] === 'vacation' ? 'selected' : '' ?>>🏖️ В отпуске</option>
                                                <option value="sick" <?= $formData['status'] === 'sick' ? 'selected' : '' ?>>🤒 На больничном</option>
                                                <option value="terminated" <?= $formData['status'] === 'terminated' ? 'selected' : '' ?>>❌ Уволен</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Заметки -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="form-label" for="notes">
                                                Заметки
                                            </label>
                                            <textarea class="form-control"
                                                      id="notes"
                                                      name="notes"
                                                      rows="4"
                                                      placeholder="Дополнительная информация о сотруднике..."><?= htmlspecialchars($formData['notes']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer">
                                <a href="<?= pageUrl('modules/employees/list.php') ?>" class="btn btn-secondary">
                                    ← Назад к списку
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    💾 Сохранить сотрудника
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
