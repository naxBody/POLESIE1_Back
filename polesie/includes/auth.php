<?php
/**
 * Функции аутентификации и авторизации
 */

/**
 * Вход пользователя в систему
 */
function login($username, $password) {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.code as role_code, r.name as role_name 
        FROM users u 
        JOIN user_roles r ON u.role_id = r.id 
        WHERE u.username = ? AND u.is_active = TRUE
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    // Простое сравнение паролей без хеширования
    if ($user && $password === $user['password_hash']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_code'] = $user['role_code'];
        $_SESSION['role_id'] = $user['role_id'];
        
        // Обновление времени последнего входа (если колонка существует)
        try {
            $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
        } catch (PDOException $e) {
            // Игнорируем ошибку, если колонка last_login_at не существует
        }
        
        logActivity('login', 'user', $user['id']);
        
        return true;
    }
    
    return false;
}

/**
 * Выход из системы
 */
function logout() {
    if (isLoggedIn()) {
        logActivity('logout', 'user', $_SESSION['user_id']);
    }
    
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

/**
 * Регистрация нового пользователя
 */
function registerUser($data) {
    $pdo = getDbConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Проверка существования username
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$data['username']]);
        if ($checkStmt->fetch()) {
            throw new Exception('Пользователь с таким именем уже существует');
        }
        
        // Пароль без хеширования (простой текст)
        $passwordPlain = $data['password'];
        
        // Вставка пользователя
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, email, phone, role_id, department, position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['username'],
            $passwordPlain,
            $data['full_name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['role_id'],
            $data['department'] ?? null,
            $data['position'] ?? null
        ]);
        
        $userId = $pdo->lastInsertId();
        
        logActivity('user_create', 'user', $userId, null, $data);
        
        $pdo->commit();
        
        return $userId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Обновление данных пользователя
 */
function updateUser($userId, $data) {
    $pdo = getDbConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Получение текущих данных
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldData = $stmt->fetch();
        
        if (!$oldData) {
            throw new Exception('Пользователь не найден');
        }
        
        // Формирование запроса обновления
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['full_name', 'email', 'phone', 'role_id', 'department', 'position', 'is_active'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (!empty($fields)) {
            $values[] = $userId;
            $updateStmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
            $updateStmt->execute($values);
            
            logActivity('user_update', 'user', $userId, $oldData, $data);
        }
        
        $pdo->commit();
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Смена пароля
 */
function changePassword($userId, $oldPassword, $newPassword) {
    $pdo = getDbConnection();
    
    // Проверка старого пароля (простое сравнение)
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || $oldPassword !== $user['password_hash']) {
        return false;
    }
    
    // Обновление пароля (без хеширования)
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $updateStmt->execute([$newPassword, $userId]);
    
    logActivity('password_change', 'user', $userId);
    
    return true;
}

/**
 * Получение списка пользователей
 */
function getUsersList($filters = []) {
    $pdo = getDbConnection();
    
    $sql = "
        SELECT u.*, r.name as role_name, r.code as role_code
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filters['search'])) {
        $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    if (isset($filters['role_id']) && $filters['role_id'] !== '') {
        $sql .= " AND u.role_id = ?";
        $params[] = $filters['role_id'];
    }
    
    if (isset($filters['is_active'])) {
        $sql .= " AND u.is_active = ?";
        $params[] = $filters['is_active'];
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Получение пользователя по ID
 */
function getUserById($userId) {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.code as role_code
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetch();
}

/**
 * Получение списка ролей
 */
function getRolesList() {
    $pdo = getDbConnection();
    
    $stmt = $pdo->query("SELECT * FROM user_roles WHERE is_active = TRUE ORDER BY name");
    
    return $stmt->fetchAll();
}

/**
 * Проверка доступа пользователя к модулю
 */
function canAccessModule($module, $permission = 'can_view') {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // Администратор имеет доступ ко всему
    if ($user['role_code'] === 'admin') {
        return true;
    }
    
    // Проверяем права из JSON поля permissions
    $permissions = isset($user['permissions']) ? json_decode($user['permissions'], true) : null;
    
    if (!$permissions || !is_array($permissions)) {
        return false;
    }
    
    // Если есть флаг all: true - полный доступ
    if (isset($permissions['all']) && $permissions['all'] === true) {
        return true;
    }
    
    // Проверяем наличие модуля в правах
    $moduleMap = [
        'dashboard' => 'заказы',
        'orders' => 'заказы',
        'contractors' => 'контрагенты',
        'production' => 'производство',
        'products' => 'продукция',
        'warehouse' => 'склад',
        'materials' => 'материалы',
        'employees' => 'сотрудники',
        'quality' => 'контроль_качества',
        'reports' => 'отчеты',
        'settings' => 'настройки'
    ];
    
    $moduleName = isset($moduleMap[$module]) ? $moduleMap[$module] : $module;
    
    if (!isset($permissions[$moduleName])) {
        return false;
    }
    
    $moduleRights = $permissions[$moduleName];
    
    // Если права заданы как массив разрешений
    if (is_array($moduleRights)) {
        $permissionMap = [
            'can_view' => 'view',
            'can_create' => 'create',
            'can_edit' => 'edit',
            'can_delete' => 'delete'
        ];
        
        $requiredRight = isset($permissionMap[$permission]) ? $permissionMap[$permission] : 'view';
        return in_array($requiredRight, $moduleRights);
    }
    
    return false;
}

/**
 * Проверка права на создание в модуле
 */
function canCreateInModule($module) {
    return canAccessModule($module, 'can_create');
}

/**
 * Проверка права на редактирование в модуле
 */
function canEditInModule($module) {
    return canAccessModule($module, 'can_edit');
}

/**
 * Проверка права на удаление в модуле
 */
function canDeleteInModule($module) {
    return canAccessModule($module, 'can_delete');
}

/**
 * Получение доступных модулей для текущего пользователя
 */
function getAvailableModules() {
    $user = getCurrentUser();
    if (!$user) {
        return [];
    }
    
    // Администратор имеет доступ ко всем модулям
    if ($user['role_code'] === 'admin') {
        return ['dashboard', 'orders', 'contractors', 'production', 'products', 'warehouse', 'materials', 'employees', 'quality', 'reports', 'settings'];
    }
    
    // Проверяем права из JSON поля permissions
    $permissions = isset($user['permissions']) ? json_decode($user['permissions'], true) : null;
    
    if (!$permissions || !is_array($permissions)) {
        return [];
    }
    
    // Если есть флаг all: true - полный доступ
    if (isset($permissions['all']) && $permissions['all'] === true) {
        return ['dashboard', 'orders', 'contractors', 'production', 'products', 'warehouse', 'materials', 'employees', 'quality', 'reports', 'settings'];
    }
    
    $availableModules = [];
    $reverseModuleMap = [
        'заказы' => ['dashboard', 'orders'],
        'контрагенты' => ['contractors'],
        'производство' => ['production'],
        'продукция' => ['products'],
        'склад' => ['warehouse'],
        'материалы' => ['materials'],
        'сотрудники' => ['employees'],
        'контроль_качества' => ['quality'],
        'отчеты' => ['reports'],
        'настройки' => ['settings']
    ];
    
    foreach ($permissions as $moduleName => $rights) {
        if (isset($reverseModuleMap[$moduleName]) && is_array($rights) && in_array('view', $rights)) {
            $availableModules = array_merge($availableModules, $reverseModuleMap[$moduleName]);
        }
    }
    
    return array_unique($availableModules);
}

/**
 * Перенаправление при отсутствии доступа
 */
function requireModuleAccess($module) {
    if (!canAccessModule($module)) {
        $_SESSION['error'] = 'У вас нет доступа к этому разделу';
        redirect(pageUrl('index.php'));
    }
}
