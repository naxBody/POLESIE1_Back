<?php
/**
 * Bootstrap файл для инициализации приложения
 * Подключает конфигурацию, сессию и базовые функции
 */

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';

// Запускаем сессию если еще не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Создаем алиасы для констант (для обратной совместимости)
if (!defined('BASE_URL')) {
    define('BASE_URL', APP_URL);
}

// Проверяем авторизацию (если не на странице логина)
$currentPage = basename($_SERVER['PHP_SELF']);
$authPages = ['login.php', 'logout.php'];

if (!in_array($currentPage, $authPages) && !isset($_SESSION['user_id'])) {
    // Для API запросов возвращаем ошибку, для обычных - редирект
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// Инициализируем объект PDO
$pdo = getDbConnection();

// Проверка прав доступа (хелпер)
function checkAccess($requiredRole = null) {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/modules/auth/login.php');
        return false;
    }
    
    if ($requiredRole) {
        $user = getCurrentUser();
        if (!$user || $user['role_code'] !== $requiredRole) {
            redirect(BASE_URL . '/access_denied.php');
            return false;
        }
    }
    
    return true;
}
