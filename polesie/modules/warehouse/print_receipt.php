<?php
/**
 * Универсальный файл печати документов поступления материалов
 * Автоматически определяет тип документа и выбирает соответствующий шаблон
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$pdo = getDbConnection();

if (!isset($_GET['id'])) {
    die("Не указан ID документа");
}

$docId = (int)$_GET['id'];

// Получаем данные документа для определения типа
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        dt.code as document_type_code
    FROM material_receipt_documents d
    LEFT JOIN receipt_document_types dt ON d.document_type_id = dt.id
    WHERE d.id = ?
");
$stmt->execute([$docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die("Документ не найден");
}

// Определяем тип документа и перенаправляем на соответствующий шаблон
$docTypeCode = strtoupper($document['document_type_code'] ?? '');

switch ($docTypeCode) {
    case 'TTN':
        // Товарно-транспортная накладная
        include __DIR__ . '/print_ttn.php';
        break;
    
    case 'INVOICE_COUNT':
        // Счет-фактура
        include __DIR__ . '/print_invoice.php';
        break;
    
    case 'ACT':
        // Акт приема-передачи
        include __DIR__ . '/print_act.php';
        break;
    
    case 'CERTIFICATE':
    case 'QUALITY_PASSPORT':
        // Сертификат или Паспорт качества
        include __DIR__ . '/print_certificate.php';
        break;
    
    default:
        // По умолчанию используем универсальный шаблон
        include __DIR__ . '/print_generic.php';
        break;
}
