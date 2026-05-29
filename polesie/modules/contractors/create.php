<?php
/**
 * Страница добавления контрагента
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();

$errors = [];
$success = false;
$formData = [
    'name' => '',
    'type' => 'customer',
    'unp' => '',
    'contact_person' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'notes' => ''
];

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'type' => $_POST['type'] ?? 'customer',
        'unp' => trim($_POST['unp'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];

    // Валидация
    if (empty($formData['name'])) {
        $errors[] = 'Введите название контрагента';
    }
    
    if (empty($formData['type'])) {
        $errors[] = 'Выберите тип контрагента';
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

    // Если нет ошибок, сохраняем в БД
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contractors 
                (name, type, unp, contact_person, phone, email, address, notes, created_at) 
                VALUES 
                (:name, :type, :unp, :contact_person, :phone, :email, :address, :notes, NOW())
            ");
            
            $stmt->execute([
                ':name' => $formData['name'],
                ':type' => $formData['type'],
                ':unp' => $formData['unp'] ?: null,
                ':contact_person' => $formData['contact_person'] ?: null,
                ':phone' => $formData['phone'] ?: null,
                ':email' => $formData['email'] ?: null,
                ':address' => $formData['address'] ?: null,
                ':notes' => $formData['notes'] ?: null
            ]);
            
            $success = true;
            $formData = [
                'name' => '',
                'type' => 'customer',
                'unp' => '',
                'contact_person' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'notes' => ''
            ];
            
            // Перенаправление после успешного добавления
            header('Location: list.php?success=created');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Добавить контрагента';

require_once BASE_PATH . '/includes/sidebar.php';
require_once BASE_PATH . '/includes/topbar.php';
?>

<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">

<style>
.form-container {
    max-width: 800px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group-full {
    grid-column: 1 / -1;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 14px;
    background: var(--bg-primary);
    transition: all var(--transition-fast);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

select.form-control {
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.alert {
    padding: 14px 18px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

.alert-error ul {
    margin: 0;
    padding-left: 20px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-title">
            <h2>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <line x1="19" y1="8" x2="19" y2="14"></line>
                    <line x1="22" y1="11" x2="16" y2="11"></line>
                </svg>
                Добавить контрагента
            </h2>
            <p>Создание нового контрагента в базе</p>
        </div>
        <div class="page-header-actions">
            <a href="list.php" class="btn btn-outline">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Назад к списку
            </a>
        </div>
    </div>

    <div class="card form-container">
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ Контрагент успешно добавлен!
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group-full">
                        <label class="form-label">Название контрагента *</label>
                        <input type="text" name="name" class="form-control" value="<?= e($formData['name']) ?>" placeholder="ООО «Пример»" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Тип контрагента *</label>
                        <select name="type" class="form-control" required>
                            <option value="customer" <?= $formData['type'] === 'customer' ? 'selected' : '' ?>>👤 Заказчик</option>
                            <option value="supplier" <?= $formData['type'] === 'supplier' ? 'selected' : '' ?>>🚚 Поставщик</option>
                            <option value="both" <?= $formData['type'] === 'both' ? 'selected' : '' ?>>🔄 Оба</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">УНП</label>
                        <input type="text" name="unp" class="form-control" value="<?= e($formData['unp']) ?>" placeholder="123456789" maxlength="9" pattern="[0-9]{9}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Контактное лицо</label>
                        <input type="text" name="contact_person" class="form-control" value="<?= e($formData['contact_person']) ?>" placeholder="Иванов И.И.">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="tel" name="phone" class="form-control" value="<?= e($formData['phone']) ?>" placeholder="+375 29 123-45-67">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($formData['email']) ?>" placeholder="info@example.com">
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">Адрес</label>
                        <input type="text" name="address" class="form-control" value="<?= e($formData['address']) ?>" placeholder="г. Минск, ул. Примерная, д. 1">
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">Примечание</label>
                        <textarea name="notes" class="form-control" placeholder="Дополнительная информация..."><?= e($formData['notes']) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Добавить контрагента
                    </button>
                    <a href="list.php" class="btn btn-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset('assets/js/main.js') ?>"></script>
</body>
</html>
