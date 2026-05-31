# 🎭 Система ролей и прав доступа - ОАО "Полесьеэлектромаш"

## 📋 Описание

Реализована полноценная система разделения прав доступа на основе ролей. Каждый пользователь видит только те разделы и функции, которые необходимы для его работы.

## ✨ Что реализовано

### 1. **7 ролей пользователей** с различными правами доступа:
- 🔷 **Администратор** - полный доступ ко всем функциям
- 🟠 **Директор** - просмотр всех разделов + редактирование заказов
- 🟡 **Менеджер по продажам** - работа с заказами и контрагентами
- 🟢 **Технолог** - управление производством и продукцией
- 🔵 **Кладовщик** - ТОЛЬКО склад и материалы
- 🟣 **Рабочий** - ТОЛЬКО производственные задания
- 🩷 **Контроль качества** - проверки ОТК

### 2. **Динамическое меню**
Боковая панель автоматически адаптируется под роль пользователя:
- Кладовщик видит только раздел "Склад"
- Рабочий видит только раздел "Производство" 
- Менеджер видит только "Заказы" и "Контрагенты"

### 3. **Адаптивная панель управления (Dashboard)**
Статистика отображается в зависимости от роли:
- **Кладовщик**: материалы на складе, низкие остатки, поступления
- **Рабочий**: мои задания в работе, задания к выполнению, выполнено за сегодня
- **Остальные**: полная статистика по заказам и производству

### 4. **Проверка прав на уровне кода**
Функции для проверки прав доступа:
```php
canAccessModule('warehouse')      // Проверка доступа к модулю
canCreateInModule('orders')       // Проверка права на создание
canEditInModule('production')     // Проверка права на редактирование
canDeleteInModule('materials')    // Проверка права на удаление
getAvailableModules()             // Список доступных модулей
requireModuleAccess('warehouse')  // Перенаправление при отсутствии доступа
```

## 🚀 Установка

### Шаг 1: Выполните SQL скрипт

```bash
mysql -u root -p polesie_production < sql/setup_roles.sql
```

Или через phpMyAdmin:
1. Откройте phpMyAdmin
2. Выберите базу данных `polesie_production`
3. Перейдите во вкладку "SQL"
4. Вставьте содержимое файла `sql/setup_roles.sql`
5. Нажмите "Вперёд"

### Шаг 2: Проверьте установку

```sql
-- Проверка ролей
SELECT * FROM user_roles;

-- Проверка прав
SELECT r.name as role_name, rmp.module, rmp.can_view, rmp.can_create, rmp.can_edit, rmp.can_delete
FROM user_roles r
JOIN role_module_permissions rmp ON r.id = rmp.role_id
ORDER BY r.name, rmp.module;

-- Проверка пользователей
SELECT u.username, u.full_name, r.name as role_name
FROM users u
JOIN user_roles r ON u.role_id = r.id;
```

## 🔐 Тестовые учетные записи

| Роль | Логин | Пароль | Доступные разделы |
|------|-------|--------|-------------------|
| 🔷 Администратор | `admin` | `admin123` | Все разделы |
| 🟠 Директор | `director` | `director123` | Просмотр всех разделов |
| 🟡 Менеджер | `ivanov` | `manager123` | Заказы, Контрагенты |
| 🟢 Технолог | `petrov` | `tech123` | Производство, Продукция |
| 🔵 Кладовщик | `sidorov` | `store123` | Склад, Материалы |
| 🟣 Рабочий | `worker1` | `worker123` | Производственные задания |
| 🩷 Контроль качества | `quality1` | `quality123` | Контроль качества |

## 📊 Примеры использования

### Для кладовщика (`sidorov / store123`)

**Видит в меню:**
- 📊 Панель управления
- 📦 Склад (все подразделы)

**Может делать:**
- ✅ Просматривать материалы
- ✅ Создавать поступления материалов
- ✅ Редактировать остатки
- ✅ Просматривать продукцию

**НЕ видит и НЕ может:**
- ❌ Заказы
- ❌ Контрагенты
- ❌ Производство (планирование)
- ❌ Сотрудники

### Для рабочего (`worker1 / worker123`)

**Видит в меню:**
- 📊 Панель управления
- 🏭 Производство

**Может делать:**
- ✅ Просматривать производственные задания
- ✅ Обновлять статус выполнения
- ✅ Просматривать требуемые материалы

**НЕ видит и НЕ может:**
- ❌ Склад (операции)
- ❌ Заказы
- ❌ Контрагенты
- ❌ Планирование производства

### Для менеджера (`ivanov / manager123`)

**Видит в меню:**
- 📊 Панель управления
- 📦 Заказы
- ➕ Новый заказ
- 🏢 Контрагенты
- 🔧 Продукция (просмотр)

**Может делать:**
- ✅ Создавать заказы
- ✅ Редактировать заказы
- ✅ Управлять контрагентами
- ✅ Просматривать продукцию

**НЕ видит и НЕ может:**
- ❌ Склад (операции)
- ❌ Контроль качества
- ❌ Планирование производства

## 🗄️ Структура базы данных

### Таблица `user_roles`
```sql
CREATE TABLE `user_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,          -- Название роли
  `code` VARCHAR(50) NOT NULL UNIQUE,    -- Код роли
  `description` TEXT,                     -- Описание
  `permissions` JSON,                     -- JSON с правами
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Таблица `role_module_permissions`
```sql
CREATE TABLE `role_module_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,                 -- Ссылка на роль
  `module` VARCHAR(50) NOT NULL,          -- Код модуля
  `can_view` BOOLEAN DEFAULT FALSE,       -- Право просмотра
  `can_create` BOOLEAN DEFAULT FALSE,     -- Право создания
  `can_edit` BOOLEAN DEFAULT FALSE,       -- Право редактирования
  `can_delete` BOOLEAN DEFAULT FALSE,     -- Право удаления
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Таблица `users` (обновленная)
```sql
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `phone` VARCHAR(50),
  `role_id` INT,                          -- Ссылка на роль
  `department` VARCHAR(100),              -- Отдел
  `position` VARCHAR(100),                -- Должность
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` TIMESTAMP NULL          -- Время последнего входа
);
```

## 💻 Использование в коде

### В sidebar.php (боковая панель)
```php
<?php
// Получаем доступные модули для текущего пользователя
$availableModules = getAvailableModules();
?>

<!-- Показываем раздел только если есть доступ -->
<?php if (in_array('warehouse', $availableModules)): ?>
<div class="sidebar-nav-section">
    <div class="sidebar-nav-title">Склад</div>
    <?php if (canAccessModule('warehouse')): ?>
    <a href="<?= pageUrl('modules/warehouse/materials.php') ?>">
        📦 Материалы
    </a>
    <?php endif; ?>
    <?php if (canCreateInModule('warehouse')): ?>
    <a href="<?= pageUrl('modules/warehouse/receipt.php') ?>">
        📥 Поступление
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>
```

### Проверка прав в модулях
```php
<?php
require_once __DIR__ . '/../../includes/auth.php';

// Проверка доступа к модулю
if (!canAccessModule('warehouse')) {
    $_SESSION['error'] = 'У вас нет доступа к этому разделу';
    redirect(pageUrl('index.php'));
}

// Проверка права на создание
<?php if (canCreateInModule('orders')): ?>
    <a href="create.php" class="btn btn-primary">+ Создать заказ</a>
<?php endif; ?>

// Проверка права на редактирование
<?php if (canEditInModule('production')): ?>
    <button class="btn btn-sm btn-edit">Редактировать</button>
<?php endif; ?>
```

### В index.php (адаптивная статистика)
```php
<?php
// Для кладовщика - только складская статистика
if ($user['role_code'] === 'storekeeper') {
    $stmt = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock > 0");
    $stats['total_orders'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock");
    $stats['orders_in_progress'] = $stmt->fetchColumn();
    
// Для рабочего - только производственные задания
} elseif ($user['role_code'] === 'worker') {
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM production_tasks 
        WHERE worker_id = {$user['id']} AND status = 'in_progress'
    ");
    $stats['total_orders'] = $stmt->fetchColumn();
    
// Для остальных - полная статистика
} else {
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $stats['total_orders'] = $stmt->fetchColumn();
}
```

## ➕ Добавление новой роли

### 1. Создайте роль
```sql
INSERT INTO user_roles (name, code, description, permissions) VALUES
('Бухгалтер', 'accountant', 'Финансовый учёт', '{}');
```

### 2. Настройте права по модулям
```sql
SET @role_id = LAST_INSERT_ID();

INSERT INTO role_module_permissions (role_id, module, can_view, can_create, can_edit, can_delete) VALUES
(@role_id, 'dashboard', TRUE, FALSE, FALSE, FALSE),
(@role_id, 'orders', TRUE, FALSE, FALSE, FALSE),
(@role_id, 'reports', TRUE, TRUE, FALSE, FALSE),
(@role_id, 'contractors', TRUE, FALSE, FALSE, FALSE);
```

### 3. Создайте пользователя
```sql
INSERT INTO users (username, password_hash, full_name, role_id, department, position) VALUES
('buhgalter', 'pass123', 'Бухгалтер Ольга', @role_id, 'Бухгалтерия', 'Бухгалтер');
```

## 🔒 Безопасность

- ✅ Все проверки прав выполняются на сервере
- ✅ Боковая панель скрывает недоступные разделы
- ✅ При попытке прямого доступа к URL без прав происходит перенаправление
- ✅ Администратор имеет полный доступ ко всем функциям
- ✅ Ведется логирование времени последнего входа

## 📁 Измененные файлы

| Файл | Изменения |
|------|-----------|
| `sql/setup_roles.sql` | ⭐ Новый файл - SQL скрипт установки ролей |
| `includes/auth.php` | Добавлены функции проверки прав |
| `includes/sidebar.php` | Адаптивное меню по ролям |
| `index.php` | Адаптивная статистика по ролям |
| `login.php` | Обновленный список тестовых пользователей |
| `docs/ROLES_AND_PERMISSIONS.md` | ⭐ Новая документация |

## 📞 Поддержка

При возникновении вопросов обращайтесь к системному администратору или изучите документацию в папке `docs/`.

---

**ОАО "Полесьеэлектромаш"** © 2024  
Система управления производством v1.0
