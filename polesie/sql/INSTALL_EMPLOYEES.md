# Инструкция по установке таблицы сотрудников

## 📋 Описание

Файл `create_employees_table.sql` создаёт таблицу `employees` для хранения расширенной информации о сотрудниках предприятия и добавляет 15 тестовых записей.

## 🔧 Установка

### Вариант 1: Через командную строку (рекомендуется)

```bash
# Перейдите в директорию проекта
cd C:\xampp\htdocs\POLESIE1_Back\polesie

# Выполните SQL-файл
mysql -u root -p polesie_production < sql/create_employees_table.sql
```

### Вариант 2: Через phpMyAdmin

1. Откройте phpMyAdmin: http://localhost/phpmyadmin
2. Выберите базу данных `polesie_production`
3. Перейдите на вкладку "Импорт"
4. Выберите файл `sql/create_employees_table.sql`
5. Нажмите "Вперёд"

### Вариант 3: Прямое выполнение SQL

1. Откройте phpMyAdmin: http://localhost/phpmyadmin
2. Выберите базу данных `polesie_production`
3. Перейдите на вкладку "SQL"
4. Скопируйте содержимое файла `create_employees_table.sql`
5. Нажмите "Вперёд"

## ✅ Проверка установки

После установки выполните запрос:

```sql
SELECT COUNT(*) as total FROM employees;
```

Должно вернуться значение **15**.

Также можно просмотреть всех сотрудников:

```sql
SELECT id, full_name, position, department, is_active 
FROM employees 
ORDER BY full_name;
```

## 📊 Структура таблицы employees

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | ID записи (автоинкремент) |
| user_id | INT | Связь с таблицей users (опционально) |
| full_name | VARCHAR(100) | ФИО сотрудника |
| position | VARCHAR(100) | Должность |
| department | ENUM | Отдел (production, quality, warehouse, management, sales, it, hr, accounting) |
| phone | VARCHAR(50) | Телефон |
| email | VARCHAR(100) | Email |
| hire_date | DATE | Дата приёма на работу |
| salary | DECIMAL(10,2) | Зарплата |
| is_active | BOOLEAN | Активен/уволен |
| created_at | TIMESTAMP | Дата создания записи |
| updated_at | TIMESTAMP | Дата обновления записи |

## 👥 Тестовые данные

Создано 15 сотрудников в различных отделах:

- **Руководство (management)**: 2 сотрудника
- **Производство (production)**: 4 сотрудника  
- **Бухгалтерия (accounting)**: 1 сотрудник
- **Склад (warehouse)**: 2 сотрудника
- **Продажи (sales)**: 2 сотрудника
- **IT отдел (it)**: 2 сотрудника
- **ОТК (quality)**: 1 сотрудник
- **HR отдел (hr)**: 1 сотрудник

Один сотрудник помечен как уволенный (`is_active = 0`).

## 🔗 Связь с другими таблицами

Таблица `employees` связана с таблицей `users` через поле `user_id`:
- Если у сотрудника есть учётная запись в системе - указывается `user_id`
- Если учётной записи нет - поле остаётся NULL
- При удалении пользователя связанные записи удаляются каскадно (ON DELETE CASCADE)

## 🎯 Использование в коде

Пример получения списка сотрудников:

```php
$pdo = getDbConnection();
$stmt = $pdo->query("SELECT * FROM employees WHERE is_active = 1 ORDER BY full_name");
$employees = $stmt->fetchAll();
```

## ⚠️ Важно

- Файл использует конструкцию `CREATE TABLE IF NOT EXISTS`, поэтому безопасен для повторного запуска
- Данные INSERT добавляются каждый раз при выполнении файла
- Перед повторным выполнением очистите таблицу: `TRUNCATE TABLE employees;`

---

**Дата создания**: 2024  
**Версия**: 1.0
