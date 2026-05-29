<?php
/**
 * Удаление контрагента
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();

// Проверка прав
if (!hasPermission('contractors.delete')) {
    redirect(pageUrl('modules/contractors/list.php'));
}

$pdo = getDbConnection();
$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM contractors WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Можно добавить логирование ошибки
    }
}

redirect(pageUrl('modules/contractors/list.php'));
