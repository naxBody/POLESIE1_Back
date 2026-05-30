<?php
/**
 * Универсальный шаблон печати документов поступления материалов
 * Для документов, не имеющих специального шаблона
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$pdo = getDbConnection();

if (!isset($_GET['id'])) {
    die("Не указан ID документа");
}

$docId = (int)$_GET['id'];

// Получаем данные документа
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        dt.code as document_type_code,
        dt.name as document_type_name,
        u.full_name as created_by_name
    FROM material_receipt_documents d
    LEFT JOIN receipt_document_types dt ON d.document_type_id = dt.id
    LEFT JOIN users u ON d.created_by = u.id
    WHERE d.id = ?
");
$stmt->execute([$docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die("Документ не найден");
}

// Получаем материалы документа
$stmt = $pdo->prepare("
    SELECT 
        mi.id,
        mi.material_id,
        mi.quantity,
        mi.unit_price,
        mi.total_price,
        mi.batch_number,
        mi.certificate_number,
        m.name as material_name,
        m.specification,
        u.symbol as unit_symbol
    FROM material_receipt_items mi
    INNER JOIN materials m ON mi.material_id = m.id
    INNER JOIN base_units u ON m.base_unit_id = u.id
    WHERE mi.document_id = ?
    ORDER BY mi.id
");
$stmt->execute([$docId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Считаем общую сумму
$totalAmount = array_sum(array_column($items, 'total_price'));

// Данные организации
$companyName = "ОАО \"Полесьеэлектромаш\"";
$companyAddress = "224000, г. Брест, ул. Промышленная, 1";
$companyPhone = "+375 (162) 12-34-56";
$companyEmail = "info@polesie.by";
$companyUNP = "123456789";
$companyOKPO = "12345678";

// Определяем название документа
$docTitle = $document['document_type_name'] ?? 'Документ поступления';
$docSubtitle = '';
$docDateDisplay = date('d.m.Y', strtotime($document['created_at']));

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($docTitle) ?> № <?= htmlspecialchars($document['document_number']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        
        .document-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }
        
        /* Шапка документа */
        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 9pt;
            color: #333;
            line-height: 1.3;
        }
        
        .document-title-block {
            text-align: right;
        }
        
        .document-type {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .document-subtype {
            font-size: 10pt;
            color: #666;
            font-style: italic;
        }
        
        .document-number-date {
            margin-top: 10px;
            font-size: 11pt;
        }
        
        /* Основная информация */
        .main-info {
            margin: 20px 0;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
            border-bottom: 1px dotted #000;
            padding-left: 10px;
        }
        
        /* Таблица материалов */
        .materials-section {
            margin: 25px 0;
        }
        
        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .materials-table th,
        .materials-table td {
            border: 1px solid #000;
            padding: 8px 6px;
            text-align: left;
            font-size: 10pt;
        }
        
        .materials-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
        }
        
        .materials-table td.num {
            text-align: center;
            width: 40px;
        }
        
        .materials-table td.qty,
        .materials-table td.price,
        .materials-table td.total {
            text-align: right;
            width: 80px;
        }
        
        .materials-table tfoot td {
            font-weight: bold;
            background: #f9f9f9;
        }
        
        /* Итоговая сумма */
        .total-section {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #000;
            background: #fafafa;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .total-label {
            font-weight: bold;
        }
        
        .total-value {
            font-size: 12pt;
            font-weight: bold;
        }
        
        .total-in-words {
            margin-top: 10px;
            font-style: italic;
            color: #555;
        }
        
        /* Подписи */
        .signatures-section {
            margin: 40px 0 20px;
        }
        
        .signature-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 25px;
        }
        
        .signature-block {
            flex: 1;
        }
        
        .signature-role {
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }
        
        .signature-line {
            display: flex;
            align-items: flex-end;
        }
        
        .signature-position {
            flex: 2;
            border-bottom: 1px solid #000;
            padding-right: 10px;
            font-size: 10pt;
        }
        
        .signature-space {
            flex: 1;
            border-bottom: 1px solid #000;
            margin-left: 10px;
            height: 30px;
        }
        
        .signature-fio {
            flex: 1.5;
            border-bottom: 1px solid #000;
            margin-left: 10px;
            padding-left: 10px;
            font-size: 10pt;
        }
        
        /* Печать */
        .stamp-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stamp-placeholder {
            width: 120px;
            height: 120px;
            border: 2px dashed #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 9pt;
            text-align: center;
            transform: rotate(-15deg);
        }
        
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background: #fafafa;
            min-height: 80px;
        }
        
        .notes-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 10pt;
        }
        
        .notes-content {
            font-size: 10pt;
            color: #555;
            white-space: pre-wrap;
        }
        
        /* Футер */
        .document-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }
        
        /* Кнопки управления */
        .control-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        @media print {
            .control-panel {
                display: none;
            }
            
            body {
                background: #fff;
            }
            
            .document-container {
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            
            .stamp-placeholder {
                border-color: #999;
                color: #999;
            }
        }
    </style>
</head>
<body>
    <!-- Панель управления -->
    <div class="control-panel">
        <button class="btn btn-secondary" onclick="window.close()">Закрыть</button>
        <button class="btn btn-primary" onclick="window.print()">🖨 Печать</button>
    </div>
    
    <div class="document-container">
        <!-- Шапка документа -->
        <div class="document-header">
            <div class="company-info">
                <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
                <div class="company-details">
                    <?= nl2br(htmlspecialchars($companyAddress)) ?><br>
                    Тел.: <?= htmlspecialchars($companyPhone) ?><br>
                    E-mail: <?= htmlspecialchars($companyEmail) ?><br>
                    УНП: <?= htmlspecialchars($companyUNP) ?>, ОКПО: <?= htmlspecialchars($companyOKPO) ?>
                </div>
            </div>
            
            <div class="document-title-block">
                <div class="document-type"><?= htmlspecialchars($docTitle) ?></div>
                <?php if ($docSubtitle): ?>
                <div class="document-subtype"><?= htmlspecialchars($docSubtitle) ?></div>
                <?php endif; ?>
                <div class="document-number-date">
                    № <?= htmlspecialchars($document['document_number']) ?> от <?= $docDateDisplay ?>
                </div>
            </div>
        </div>
        
        <!-- Основная информация -->
        <div class="main-info">
            <?php if ($document['supplier_name']): ?>
            <div class="info-row">
                <div class="info-label">Поставщик:</div>
                <div class="info-value"><?= htmlspecialchars($document['supplier_name']) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($document['supplier_inn']): ?>
            <div class="info-row">
                <div class="info-label">УНП поставщика:</div>
                <div class="info-value"><?= htmlspecialchars($document['supplier_inn']) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($document['supplier_invoice_number']): ?>
            <div class="info-row">
                <div class="info-label">Счет-фактура:</div>
                <div class="info-value">
                    № <?= htmlspecialchars($document['supplier_invoice_number']) ?>
                    <?php if ($document['supplier_invoice_date']): ?>
                    от <?= date('d.m.Y', strtotime($document['supplier_invoice_date'])) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($document['ttn_number']): ?>
            <div class="info-row">
                <div class="info-label">ТТН №:</div>
                <div class="info-value"><?= htmlspecialchars($document['ttn_number']) ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <div class="info-label">Основание:</div>
                <div class="info-value"><?= htmlspecialchars($document['notes'] ?? 'Поступление материалов на склад') ?></div>
            </div>
        </div>
        
        <!-- Таблица материалов -->
        <div class="materials-section">
            <table class="materials-table">
                <thead>
                    <tr>
                        <th class="num">№</th>
                        <th>Наименование материала</th>
                        <th>Ед.изм.</th>
                        <th class="qty">Кол-во</th>
                        <th class="price">Цена</th>
                        <th class="total">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rowNum = 1;
                    foreach ($items as $item): 
                    ?>
                    <tr>
                        <td class="num"><?= $rowNum++ ?></td>
                        <td>
                            <?= htmlspecialchars($item['material_name']) ?>
                            <?php if ($item['batch_number']): ?>
                            <br><small style="color: #666;">Партия: <?= htmlspecialchars($item['batch_number']) ?></small>
                            <?php endif; ?>
                            <?php if ($item['certificate_number']): ?>
                            <br><small style="color: #666;">Сертификат: <?= htmlspecialchars($item['certificate_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['unit_symbol'] ?? 'шт') ?></td>
                        <td class="qty"><?= number_format(floatval($item['quantity']), 3, ',', ' ') ?></td>
                        <td class="price"><?= number_format(floatval($item['unit_price'] ?? 0), 2, ',', ' ') ?></td>
                        <td class="total"><?= number_format(floatval($item['total_price'] ?? 0), 2, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>Итого:</strong></td>
                        <td class="qty"><strong><?= number_format(array_sum(array_column($items, 'quantity')), 3, ',', ' ') ?></strong></td>
                        <td></td>
                        <td class="total"><strong><?= number_format($totalAmount, 2, ',', ' ') ?> BYN</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Итоговая сумма -->
        <div class="total-section">
            <div class="total-row">
                <span class="total-label">Всего на сумму:</span>
                <span class="total-value"><?= number_format($totalAmount, 2, ',', ' ') ?> BYN</span>
            </div>
            <div class="total-in-words">
                В том числе НДС не облагается / без учета НДС
            </div>
        </div>
        
        <!-- Подписи -->
        <div class="signatures-section">
            <div class="signature-row">
                <div class="signature-block">
                    <div class="signature-role">Отпустил:</div>
                    <div class="signature-line">
                        <div class="signature-position">(должность)</div>
                        <div class="signature-space"></div>
                        <div class="signature-fio"><?= htmlspecialchars($document['created_by_name'] ?? '_______________') ?></div>
                    </div>
                </div>
                
                <div class="signature-block" style="margin-left: 30px;">
                    <div class="signature-role">Принял:</div>
                    <div class="signature-line">
                        <div class="signature-position">(должность)</div>
                        <div class="signature-space"></div>
                        <div class="signature-fio">(подпись)</div>
                    </div>
                </div>
            </div>
            
            <div class="signature-row">
                <div class="signature-block">
                    <div class="signature-role">Провел:</div>
                    <div class="signature-line">
                        <div class="signature-position">Главный бухгалтер</div>
                        <div class="signature-space"></div>
                        <div class="signature-fio"><?= htmlspecialchars($document['created_by_name'] ?? '_______________') ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Печать -->
        <div class="stamp-section">
            <div class="stamp-placeholder">М.П.</div>
        </div>
        
        <!-- Примечание -->
        <?php if (!empty($document['notes'])): ?>
        <div class="notes-section">
            <div class="notes-title">Примечание:</div>
            <div class="notes-content"><?= htmlspecialchars($document['notes']) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Футер -->
        <div class="document-footer">
            Документ создан: <?= date('d.m.Y H:i', strtotime($document['created_at'])) ?> | 
            Статус: <?= $document['status'] == 1 ? 'Проведен' : 'Черновик' ?> | 
            Пользователь: <?= htmlspecialchars($document['created_by_name'] ?? 'Неизвестно') ?>
        </div>
    </div>
</body>
</html>
