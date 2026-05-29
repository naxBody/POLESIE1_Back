-- Таблица СОТРУДНИКИ (расширенная информация о пользователях)
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `full_name` VARCHAR(100) NOT NULL,
  `position` VARCHAR(100),
  `department` ENUM('production', 'quality', 'warehouse', 'management', 'sales', 'it', 'hr', 'accounting') DEFAULT 'production',
  `phone` VARCHAR(50),
  `email` VARCHAR(100),
  `hire_date` DATE,
  `salary` DECIMAL(10,2),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Добавление тестовых сотрудников
INSERT INTO `employees` (`user_id`, `full_name`, `position`, `department`, `phone`, `email`, `hire_date`, `is_active`) VALUES
(NULL, 'Александр Иванов', 'Генеральный директор', 'management', '+375 (29) 123-45-67', 'ivanov@polesie.by', '2020-01-15', 1),
(NULL, 'Дмитрий Петров', 'Технолог', 'production', '+375 (29) 234-56-78', 'petrov@polesie.by', '2020-03-20', 1),
(NULL, 'Елена Сидорова', 'Бухгалтер', 'accounting', '+375 (29) 345-67-89', 'sidorova@polesie.by', '2019-06-10', 1),
(NULL, 'Сергей Козлов', 'Начальник склада', 'warehouse', '+375 (29) 456-78-90', 'kozlov@polesie.by', '2021-02-01', 1),
(NULL, 'Ольга Новикова', 'Менеджер по продажам', 'sales', '+375 (29) 567-89-01', 'novikova@polesie.by', '2021-05-15', 1),
(NULL, 'Алексей Морозов', 'Программист', 'it', '+375 (29) 678-90-12', 'morozov@polesie.by', '2022-01-10', 1),
(NULL, 'Наталья Волкова', 'Инспектор ОТК', 'quality', '+375 (29) 789-01-23', 'volkova@polesie.by', '2020-08-25', 1),
(NULL, 'Иван Соколов', 'HR-менеджер', 'hr', '+375 (29) 890-12-34', 'sokolov@polesie.by', '2021-11-01', 1),
(NULL, 'Мария Лебедева', 'Оператор станка ЧПУ', 'production', '+375 (29) 901-23-45', 'lebedeva@polesie.by', '2022-03-15', 1),
(NULL, 'Павел Кравцов', 'Водитель погрузчика', 'warehouse', '+375 (29) 012-34-56', 'kravtsov@polesie.by', '2021-07-20', 1),
(NULL, 'Татьяна Медведева', 'Главный инженер', 'production', '+375 (29) 111-22-33', 'medvedeva@polesie.by', '2019-04-01', 1),
(NULL, 'Андрей Федоров', 'Зам. директора по производству', 'management', '+375 (29) 222-33-44', 'fedorov@polesie.by', '2018-09-15', 1),
(NULL, 'Светлана Павлова', 'Специалист по закупкам', 'sales', '+375 (29) 333-44-55', 'pavlova@polesie.by', '2020-11-20', 1),
(NULL, 'Виктор Титов', 'Системный администратор', 'it', '+375 (29) 444-55-66', 'titov@polesie.by', '2021-08-10', 1),
(NULL, 'Юлия Орлова', 'Уволенный сотрудник', 'production', '+375 (29) 555-66-77', 'orlova@polesie.by', '2019-01-15', 0);
