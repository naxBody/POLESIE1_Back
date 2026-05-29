<?php
/**
 * API для оприходования материалов
 * Обработка документов поступления (ТТН, накладные и др.)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

session_start();

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$pdo = getDbConnection();
$user = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - получение списка документов или данных для формы
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        // Список документов поступления
        if ($action === 'list') {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $status = $_GET['status'] ?? '';
            $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            
            $where = ['1=1'];
            $params = [];
            
            if ($status) {
                $where[] = "d.status = ?";
                $params[] = $status;
            }
            if ($supplierId) {
                $where[] = "d.supplier_id = ?";
                $params[] = $supplierId;
            }
            if ($dateFrom) {
                $where[] = "d.document_date >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo) {
                $where[] = "d.document_date <= ?";
                $params[] = $dateTo;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "SELECT 
                        d.*,
                        dt.name as document_type_name,
                        dt.code as document_type_code,
                        c.name as supplier_name,
                        u.full_name as created_by_name,
                        p.full_name as posted_by_name
                    FROM material_receipt_documents d
                    LEFT JOIN receipt_document_types dt ON d.document_type_id = dt.id
                    LEFT JOIN contractors c ON d.supplier_id = c.id
                    LEFT JOIN users u ON d.created_by = u.id
                    LEFT JOIN users p ON d.posted_by = p.id
                    WHERE $whereClause
                    ORDER BY d.document_date DESC, d.id DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Получаем общее количество
            $countSql = "SELECT COUNT(*) as total FROM material_receipt_documents d WHERE $whereClause";
            $countStmt = $pdo->prepare(str_replace('?', '?', $countSql));
            $countParams = array_slice($params, 0, count($params) - 2);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch()['total'];
            
            echo json_encode([
                'success' => true,
                'data' => $documents,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        }
        
        // Получение одного документа
        elseif ($action === 'get' && isset($_GET['id'])) {
            $docId = (int)$_GET['id'];
            
            $stmt = $pdo->prepare("
                SELECT 
                    d.*,
                    dt.name as document_type_name,
                    dt.code as document_type_code,
                    c.name as supplier_name,
                    c.inn as supplier_inn,
                    u.full_name as created_by_name,
                    p.full_name as posted_by_name
                FROM material_receipt_documents d
                LEFT JOIN receipt_document_types dt ON d.document_type_id = dt.id
                LEFT JOIN contractors c ON d.supplier_id = c.id
                LEFT JOIN users u ON d.created_by = u.id
                LEFT JOIN users p ON d.posted_by = p.id
                WHERE d.id = ?
            ");
            $stmt->execute([$docId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                echo json_encode(['success' => false, 'error' => 'Document not found']);
                exit;
            }
            
            // Получаем позиции документа
            $itemsStmt = $pdo->prepare("
                SELECT 
                    i.*,
                    m.code as material_code,
                    m.name_full as material_name,
                    bu.symbol as unit_symbol
                FROM material_receipt_items i
                JOIN materials m ON i.material_id = m.id
                LEFT JOIN base_units bu ON m.base_unit_id = bu.id
                WHERE i.receipt_document_id = ?
                ORDER BY i.row_number
            ");
            $itemsStmt->execute([$docId]);
            $document['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $document]);
        }
        
        // Данные для формы (справочники)
        elseif ($action === 'form_data') {
            // Типы документов
            $docTypesStmt = $pdo->query("SELECT id, name, name_ru, code FROM receipt_document_types WHERE is_active = TRUE ORDER BY sort_order");
            $docTypes = $docTypesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Поставщики
            $suppliersStmt = $pdo->query("SELECT id, name, inn FROM contractors WHERE type IN ('supplier', 'both') AND is_active = TRUE ORDER BY name");
            $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Материалы
            $materialsStmt = $pdo->query("
                SELECT 
                    m.id, m.code, m.name_full, m.name_short,
                    mc.name as category_name,
                    bu.symbol as unit_symbol,
                    m.current_stock
                FROM materials m
                LEFT JOIN material_categories mc ON m.category_id = mc.id
                LEFT JOIN base_units bu ON m.base_unit_id = bu.id
                WHERE m.is_active = TRUE OR m.is_active IS NULL
                ORDER BY m.name_full
            ");
            $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'document_types' => $docTypes,
                    'suppliers' => $suppliers,
                    'materials' => $materials
                ]
            ]);
        }
        
        else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    }
    
    // POST - создание/обновление документа
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }
        
        $action = $input['action'] ?? 'create';
        
        // Создание нового документа поступления
        if ($action === 'create' || $action === 'save') {
            $pdo->beginTransaction();
            
            try {
                $docId = $input['id'] ?? null;
                $isUpdate = $docId !== null;
                
                // Основные данные документа
                $documentNumber = $input['document_number'] ?? null;
                $documentTypeId = isset($input['document_type_id']) ? (int)$input['document_type_id'] : null;
                $documentDate = $input['document_date'] ?? date('Y-m-d');
                $supplierId = isset($input['supplier_id']) ? (int)$input['supplier_id'] : null;
                $supplierInvoiceNumber = $input['supplier_invoice_number'] ?? null;
                $supplierInvoiceDate = $input['supplier_invoice_date'] ?? null;
                $ttnNumber = $input['ttn_number'] ?? null;
                $notes = $input['notes'] ?? null;
                $attachments = isset($input['attachments']) ? json_encode($input['attachments']) : null;
                
                // Генерация номера документа если не указан
                if (!$documentNumber) {
                    $year = date('Y');
                    $month = date('m');
                    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(document_number, '-', -1) AS UNSIGNED)) as max_num FROM material_receipt_documents WHERE document_number LIKE ?");
                    $stmt->execute(["PR-$year$month-%"]);
                    $result = $stmt->fetch();
                    $nextNum = ($result['max_num'] ?? 0) + 1;
                    $documentNumber = "PR-$year$month-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
                }
                
                if ($isUpdate) {
                    // Обновление существующего документа
                    $updateSql = "UPDATE material_receipt_documents SET
                        document_number = ?,
                        document_type_id = ?,
                        document_date = ?,
                        supplier_id = ?,
                        supplier_invoice_number = ?,
                        supplier_invoice_date = ?,
                        ttn_number = ?,
                        notes = ?,
                        attachments = ?,
                        updated_at = NOW()
                    WHERE id = ?";
                    
                    $stmt = $pdo->prepare($updateSql);
                    $stmt->execute([
                        $documentNumber, $documentTypeId, $documentDate, $supplierId,
                        $supplierInvoiceNumber, $supplierInvoiceDate, $ttnNumber,
                        $notes, $attachments, $docId
                    ]);
                } else {
                    // Создание нового документа
                    $insertSql = "INSERT INTO material_receipt_documents (
                        document_number, document_type_id, document_date, supplier_id,
                        supplier_invoice_number, supplier_invoice_date, ttn_number,
                        notes, attachments, created_by, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
                    
                    $stmt = $pdo->prepare($insertSql);
                    $stmt->execute([
                        $documentNumber, $documentTypeId, $documentDate, $supplierId,
                        $supplierInvoiceNumber, $supplierInvoiceDate, $ttnNumber,
                        $notes, $attachments, $user['id']
                    ]);
                    
                    $docId = $pdo->lastInsertId();
                }
                
                // Обработка позиций документа
                if (isset($input['items']) && is_array($input['items'])) {
                    // Удаляем старые позиции при обновлении
                    if ($isUpdate) {
                        $deleteStmt = $pdo->prepare("DELETE FROM material_receipt_items WHERE receipt_document_id = ?");
                        $deleteStmt->execute([$docId]);
                    }
                    
                    // Вставляем новые позиции
                    $itemInsertSql = "INSERT INTO material_receipt_items (
                        receipt_document_id, material_id, row_number, quantity,
                        unit_price, total_price, batch_number, manufacturer,
                        certificate_number, certificate_date, expiry_date, storage_location, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $itemStmt = $pdo->prepare($itemInsertSql);
                    
                    foreach ($input['items'] as $index => $item) {
                        $materialId = (int)$item['material_id'];
                        $quantity = (float)$item['quantity'];
                        $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : null;
                        $totalPrice = isset($item['total_price']) ? (float)$item['total_price'] : ($unitPrice * $quantity);
                        
                        $itemStmt->execute([
                            $docId,
                            $materialId,
                            $index + 1,
                            $quantity,
                            $unitPrice,
                            $totalPrice,
                            $item['batch_number'] ?? null,
                            $item['manufacturer'] ?? null,
                            $item['certificate_number'] ?? null,
                            $item['certificate_date'] ?? null,
                            $item['expiry_date'] ?? null,
                            $item['storage_location'] ?? null,
                            $item['notes'] ?? null
                        ]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => $isUpdate ? 'Документ обновлен' : 'Документ создан',
                    'data' => ['id' => $docId, 'document_number' => $documentNumber]
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        
        // Проведение документа (оприходование материалов)
        elseif ($action === 'post') {
            $docId = isset($input['id']) ? (int)$input['id'] : null;
            
            if (!$docId) {
                echo json_encode(['success' => false, 'error' => 'Document ID required']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Проверяем статус документа
                $checkStmt = $pdo->prepare("SELECT status FROM material_receipt_documents WHERE id = ?");
                $checkStmt->execute([$docId]);
                $doc = $checkStmt->fetch();
                
                if (!$doc) {
                    throw new Exception('Документ не найден');
                }
                
                if ($doc['status'] === 'posted') {
                    throw new Exception('Документ уже проведен');
                }
                
                if ($doc['status'] === 'cancelled') {
                    throw new Exception('Нельзя провести отмененный документ');
                }
                
                // Получаем позиции документа
                $itemsStmt = $pdo->prepare("
                    SELECT i.*, m.current_stock, m.base_unit_id
                    FROM material_receipt_items i
                    JOIN materials m ON i.material_id = m.id
                    WHERE i.receipt_document_id = ?
                ");
                $itemsStmt->execute([$docId]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($items)) {
                    throw new Exception('Документ не содержит позиций');
                }
                
                // Обновляем остатки по каждому материалу
                $transactionSql = "INSERT INTO warehouse_transactions (
                    transaction_type, document_type, document_id, material_id,
                    quantity, unit_id, price, total_amount, batch_number,
                    from_location, to_location, previous_stock, new_stock,
                    notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $transStmt = $pdo->prepare($transactionSql);
                
                foreach ($items as $item) {
                    $previousStock = (float)$item['current_stock'];
                    $quantity = (float)$item['quantity'];
                    $newStock = $previousStock + $quantity;
                    
                    // Обновляем текущий остаток материала
                    $updateStockSql = "UPDATE materials SET current_stock = ?, updated_at = NOW() WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateStockSql);
                    $updateStmt->execute([$newStock, $item['material_id']]);
                    
                    // Создаем запись о складской операции
                    $transStmt->execute([
                        'receipt',           // тип операции - приход
                        'material_receipt',  // тип документа
                        $docId,              // ID документа
                        $item['material_id'],
                        $quantity,           // положительное количество - приход
                        $item['base_unit_id'],
                        $item['unit_price'],
                        $item['total_price'],
                        $item['batch_number'],
                        null,                // from_location
                        $item['storage_location'],
                        $previousStock,
                        $newStock,
                        'Оприходование по документу',
                        $user['id']
                    ]);
                }
                
                // Обновляем статус документа
                $updateStatusSql = "UPDATE material_receipt_documents 
                    SET status = 'posted', posted_by = ?, posted_at = NOW() 
                    WHERE id = ?";
                $statusStmt = $pdo->prepare($updateStatusSql);
                $statusStmt->execute([$user['id'], $docId]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Документ проведен. Материалы оприходованы.'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
        
        // Отмена проведения документа
        elseif ($action === 'unpost') {
            $docId = isset($input['id']) ? (int)$input['id'] : null;
            
            if (!$docId) {
                echo json_encode(['success' => false, 'error' => 'Document ID required']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Проверяем статус документа
                $checkStmt = $pdo->prepare("SELECT status FROM material_receipt_documents WHERE id = ?");
                $checkStmt->execute([$docId]);
                $doc = $checkStmt->fetch();
                
                if (!$doc) {
                    throw new Exception('Документ не найден');
                }
                
                if ($doc['status'] !== 'posted') {
                    throw new Exception('Можно отменить только проведенный документ');
                }
                
                // Получаем позиции документа
                $itemsStmt = $pdo->prepare("
                    SELECT i.*, m.current_stock
                    FROM material_receipt_items i
                    JOIN materials m ON i.material_id = m.id
                    WHERE i.receipt_document_id = ?
                ");
                $itemsStmt->execute([$docId]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Восстанавливаем остатки (вычитаем)
                foreach ($items as $item) {
                    $previousStock = (float)$item['current_stock'];
                    $quantity = (float)$item['quantity'];
                    $newStock = $previousStock - $quantity;
                    
                    $updateStockSql = "UPDATE materials SET current_stock = ?, updated_at = NOW() WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateStockSql);
                    $updateStmt->execute([$newStock, $item['material_id']]);
                }
                
                // Удаляем складские операции
                $deleteTransSql = "DELETE FROM warehouse_transactions WHERE document_type = 'material_receipt' AND document_id = ?";
                $deleteStmt = $pdo->prepare($deleteTransSql);
                $deleteStmt->execute([$docId]);
                
                // Обновляем статус документа
                $updateStatusSql = "UPDATE material_receipt_documents 
                    SET status = 'draft', posted_by = NULL, posted_at = NULL 
                    WHERE id = ?";
                $statusStmt = $pdo->prepare($updateStatusSql);
                $statusStmt->execute([$docId]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Проведение документа отменено'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
        
        else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    }
    
    // DELETE - удаление документа
    elseif ($method === 'DELETE') {
        $docId = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$docId) {
            echo json_encode(['success' => false, 'error' => 'Document ID required']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        try {
            // Проверяем статус документа
            $checkStmt = $pdo->prepare("SELECT status FROM material_receipt_documents WHERE id = ?");
            $checkStmt->execute([$docId]);
            $doc = $checkStmt->fetch();
            
            if (!$doc) {
                throw new Exception('Документ не найден');
            }
            
            if ($doc['status'] === 'posted') {
                throw new Exception('Сначала отмените проведение документа');
            }
            
            // Удаляем документ (позиции удалятся каскадом)
            $deleteSql = "DELETE FROM material_receipt_documents WHERE id = ?";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute([$docId]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Документ удален']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    else {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    error_log("Material Receipt API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
