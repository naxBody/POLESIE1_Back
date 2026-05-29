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
                (full_name, position, department, email, phone, hire_date, salary, status, notes, created_at) 
                VALUES 
                (:full_name, :position, :department, :email, :phone, :hire_date, :salary, :status, :notes, NOW())
            ");
            
            $stmt->execute([
                ':full_name' => $formData['full_name'],
                ':position' => $formData['position'],
                ':department' => $formData['department'],
                ':email' => $formData['email'] ?: null,
                ':phone' => $formData['phone'] ?: null,
                ':hire_date' => $formData['hire_date'],
                ':salary' => $formData['salary'] ?: null,
                ':status' => $formData['status'],
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

$pageTitle = 'Добавить сотрудника';
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
        .content-header { background: #fff; padding: 1.5rem; border-bottom: 1px solid #e9ecef; margin-bottom: 1.5rem; }
        .content-wrapper { padding: 1.5rem; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e9ecef; font-weight: 600; }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-control { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ced4da; border-radius: 4px; }
        .form-control-lg { padding: 0.75rem 1rem; font-size: 1rem; }
        .form-select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ced4da; border-radius: 4px; background: #fff; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #007bff; color: #fff; }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .text-danger { color: #dc3545; }
        .text-muted { color: #6c757d; font-size: 0.875rem; }
        .row { display: flex; flex-wrap: wrap; margin: 0 -0.5rem; }
        .col-md-6 { flex: 0 0 50%; max-width: 50%; padding: 0 0.5rem; }
        .col-md-12 { flex: 0 0 100%; max-width: 100%; padding: 0 0.5rem; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 1rem; }
        .mt-2 { margin-top: 0.5rem; }
        .me-1 { margin-right: 0.25rem; }
        .me-2 { margin-right: 0.5rem; }
        .float-right { float: right; }
        .breadcrumb { list-style: none; padding: 0; margin: 0; display: flex; }
        .breadcrumb-item + .breadcrumb-item::before { content: "/"; padding: 0 0.5rem; color: #6c757d; }
        .breadcrumb-item a { text-decoration: none; color: #007bff; }
        .form-text { font-size: 0.875rem; margin-top: 0.25rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include BASE_PATH . '/includes/topbar.php'; ?>
            
            <div class="content-area">
                <div class="content-wrapper">
                    <!-- Header -->
                    <div class="content-header">
                        <div class="container-fluid">
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <h1 class="m-0 text-dark">
                                        <i class="fas fa-user-plus me-2"></i>Добавить сотрудника
                                    </h1>
                                </div>
                                <div class="col-sm-6">
                                    <ol class="breadcrumb float-right">
                                        <li class="breadcrumb-item"><a href="<?= pageUrl('index.php') ?>">Главная</a></li>
                                        <li class="breadcrumb-item"><a href="<?= pageUrl('modules/employees/list.php') ?>">Сотрудники</a></li>
                                        <li class="breadcrumb-item active">Добавить</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main content -->
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-lg-8 mx-auto">
                                
                                <?php if ($success): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Успешно!</strong> Сотрудник добавлен. Перенаправление...
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <strong>Ошибки:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="card card-primary card-outline">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-file-alt me-2"></i>Информация о сотруднике
                                        </h3>
                                    </div>
                                    
                                    <form method="POST" action="">
                                        <div class="card-body">
                                            
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="full_name">
                                                            <i class="fas fa-user me-1"></i>ФИО <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="text" 
                                                               class="form-control form-control-lg" 
                                                               id="full_name" 
                                                               name="full_name" 
                                                               value="<?= htmlspecialchars($formData['full_name']) ?>"
                                                               placeholder="Иванов Иван Иванович" 
                                                               required>
                                                        <small class="form-text text-muted">Полное имя сотрудника</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="position">
                                                            <i class="fas fa-briefcase me-1"></i>Должность <span class="text-danger">*</span>
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
                                                        <label for="department">
                                                            <i class="fas fa-building me-1"></i>Отдел <span class="text-danger">*</span>
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

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="email">
                                                            <i class="fas fa-envelope me-1"></i>Email
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
                                                        <label for="phone">
                                                            <i class="fas fa-phone me-1"></i>Телефон
                                                        </label>
                                                        <input type="tel" 
                                                               class="form-control" 
                                                               id="phone" 
                                                               name="phone" 
                                                               value="<?= htmlspecialchars($formData['phone']) ?>"
                                                               placeholder="+375 (29) 123-45-67">
                                                        <small class="form-text text-muted">Формат: +375 (XX) XXX-XX-XX</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="hire_date">
                                                            <i class="fas fa-calendar-alt me-1"></i>Дата приема <span class="text-danger">*</span>
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
                                                        <label for="salary">
                                                            <i class="fas fa-dollar-sign me-1"></i>Зарплата (BYN)
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

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="status">
                                                            <i class="fas fa-user-tag me-1"></i>Статус
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

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="notes">
                                                            <i class="fas fa-sticky-note me-1"></i>Заметки
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
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Сохранить сотрудника
                                            </button>
                                            <a href="<?= pageUrl('modules/employees/list.php') ?>" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left me-2"></i>Назад к списку
                                            </a>
                                        </div>
                                    </form>
                                </div>
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

<div class="content-wrapper">
    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-user-plus me-2"></i>Добавить сотрудника
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Главная</a></li>
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/employees/list.php">Сотрудники</a></li>
                        <li class="breadcrumb-item active">Добавить</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Успешно!</strong> Сотрудник добавлен. Перенаправление...
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Ошибки:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-file-alt me-2"></i>Информация о сотруднике
                            </h3>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="card-body">
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="full_name">
                                                <i class="fas fa-user me-1"></i>ФИО <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" 
                                                   class="form-control form-control-lg" 
                                                   id="full_name" 
                                                   name="full_name" 
                                                   value="<?= htmlspecialchars($formData['full_name']) ?>"
                                                   placeholder="Иванов Иван Иванович" 
                                                   required>
                                            <small class="form-text text-muted">Полное имя сотрудника</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="position">
                                                <i class="fas fa-briefcase me-1"></i>Должность <span class="text-danger">*</span>
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
                                            <label for="department">
                                                <i class="fas fa-building me-1"></i>Отдел <span class="text-danger">*</span>
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

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">
                                                <i class="fas fa-envelope me-1"></i>Email
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
                                            <label for="phone">
                                                <i class="fas fa-phone me-1"></i>Телефон
                                            </label>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?= htmlspecialchars($formData['phone']) ?>"
                                                   placeholder="+375 (29) 123-45-67">
                                            <small class="form-text text-muted">Формат: +375 (XX) XXX-XX-XX</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hire_date">
                                                <i class="fas fa-calendar-alt me-1"></i>Дата приема <span class="text-danger">*</span>
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
                                            <label for="salary">
                                                <i class="fas fa-dollar-sign me-1"></i>Зарплата (BYN)
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

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="status">
                                                <i class="fas fa-user-tag me-1"></i>Статус
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

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="notes">
                                                <i class="fas fa-sticky-note me-1"></i>Заметки
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
                            
                            <div class="card-footer d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>/modules/employees/list.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Назад к списку
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-1"></i>Сохранить сотрудника
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<style>
.content-wrapper {
    background-color: #f4f6f9;
    min-height: 100vh;
}

.card {
    border: none;
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    margin-bottom: 1rem;
}

.card-primary.card-outline {
    border-top: 3px solid #007bff;
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solid #e9ecef;
    padding: 1rem 1.25rem;
}

.card-body {
    padding: 1.25rem;
}

.form-control, .form-select {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
}

.form-control:focus, .form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.form-control-lg {
    padding: 0.5rem 1rem;
    font-size: 1.05rem;
}

.btn {
    border-radius: 0.375rem;
    font-weight: 500;
}

.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    background-color: #0056b3;
    border-color: #004085;
}

.alert {
    border-radius: 0.375rem;
    border: none;
}

.breadcrumb {
    background-color: transparent;
    padding: 0;
}

.text-danger {
    color: #dc3545 !important;
}

.form-text {
    font-size: 0.875em;
    color: #6c757d;
}

@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .btn-lg {
        padding: 0.5rem 1rem;
        font-size: 1rem;
    }
}
</style>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/polesie/includes/footer.php'; ?>
