<?php
/**
 * Печать Акта приема-передачи материалов
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

// Получаем данные документа
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        dt.name as document_type_name,
        dt.code as document_type_code,
        c.name as supplier_name,
        c.inn as supplier_inn,
        c.address as supplier_address,
        c.phone as supplier_phone,
        c.email as supplier_email,
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
    die("Документ не найден");
}

// Получаем позиции документа
$itemsStmt = $pdo->prepare("
    SELECT 
        i.*,
        m.code as material_code,
        m.name_full as material_name,
        m.name_short as material_short,
        mc.name as category_name,
        bu.symbol as unit_symbol
    FROM material_receipt_items i
    JOIN materials m ON i.material_id = m.id
    LEFT JOIN material_categories mc ON m.category_id = mc.id
    LEFT JOIN base_units bu ON m.base_unit_id = bu.id
    WHERE i.receipt_document_id = ?
    ORDER BY i.row_number
");
$itemsStmt->execute([$docId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Данные организации
$companyName = "ОАО «Полесьеэлектромаш»";
$companyAddress = "225644, Брестская область, г. Лунинец, ул. Красная, 179";
$companyPhone = "+375 1647 2-78-09";
$companyEmail = "polesie@electromash.by";
$companyUNP = "400086359";
$companyOKPO = "01032356";

// Даты
$docDateDisplay = date('d.m.Y', strtotime($document['document_date']));
$createdDateDisplay = date('d.m.Y H:i', strtotime($document['created_at']));

// Сумма прописью
function sumInWordsBYN($sum) {
    $sum = floatval($sum);
    $wholePart = floor($sum);
    $fractionalPart = round(($sum - $wholePart) * 100);
    
    return number_format($sum, 2, ',', ' ') . ' (' . $wholePart . ' руб. ' . $fractionalPart . ' коп.)';
}

$totalAmount = floatval($document['total_amount'] ?? 0);
$totalInWords = sumInWordsBYN($totalAmount);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Акт № <?= htmlspecialchars($document['document_number']) ?></title>
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
            padding: 5mm;
        }
        
        /* Заголовок */
        .act-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .act-title {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .act-subtitle {
            font-size: 11pt;
            margin-bottom: 10px;
        }
        
        .act-number-date {
            font-size: 11pt;
        }
        
        /* Преамбула */
        .preamble {
            margin-bottom: 20px;
            text-align: justify;
            line-height: 1.5;
        }
        
        .preamble-text {
            margin-bottom: 10px;
        }
        
        /* Таблица материалов */
        .items-section {
            margin: 25px 0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px 6px;
            text-align: left;
            font-size: 10pt;
        }
        
        .items-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
        }
        
        .items-table td.num {
            text-align: center;
            width: 35px;
        }
        
        .items-table td.qty,
        .items-table td.price,
        .items-table td.total {
            text-align: right;
        }
        
        .items-table tfoot td {
            font-weight: bold;
            background: #fafafa;
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
            font-size: 10pt;
            color: #555;
        }
        
        /* Подписи сторон */
        .parties-signatures {
            margin: 30px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .party-block {
            width: 48%;
        }
        
        .party-title {
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
            font-size: 11pt;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        
        .party-info {
            font-size: 9pt;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .signature-line {
            display: flex;
            align-items: flex-end;
            margin-bottom: 8px;
        }
        
        .signature-position {
            flex: 2;
            border-bottom: 1px solid #000;
            padding-right: 10px;
            font-size: 9pt;
        }
        
        .signature-space {
            flex: 1;
            border-bottom: 1px solid #000;
            margin-left: 10px;
            height: 35px;
        }
        
        .signature-fio {
            flex: 1.5;
            border-bottom: 1px solid #000;
            margin-left: 10px;
            padding-left: 10px;
            font-size: 9pt;
        }
        
        /* Печати */
        .stamps-section {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stamp-placeholder {
            width: 100px;
            height: 100px;
            border: 2px dashed #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 8pt;
            text-align: center;
            transform: rotate(-15deg);
        }
        
        /* Примечание */
        .notes-section {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            background: #fafafa;
            min-height: 50px;
        }
        
        .notes-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 9pt;
        }
        
        .notes-content {
            font-size: 9pt;
            color: #555;
            white-space: pre-wrap;
        }
        
        /* Футер */
        .document-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 7pt;
            color: #999;
        }
        
        /* Кнопки */
        .control-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
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
            }
        }
    </style>
</head>
<body>
    <div class="control-panel">
        <button class="btn btn-secondary" onclick="window.close()">Закрыть</button>
        <button class="btn btn-primary" onclick="window.print()">🖨 Печать</button>
    </div>
    
    <div class="document-container">
        <!-- Заголовок -->
        <div class="act-header">
            <div class="act-title">АКТ</div>
            <div class="act-subtitle">приема-передачи материалов</div>
            <div class="act-number-date">
                № <?= htmlspecialchars($document['document_number']) ?> от <?= $docDateDisplay ?>
            </div>
        </div>
        
        <!-- Преамбула -->
        <div class="preamble">
            <div class="preamble-text">
                Мы, нижеподписавшиеся, представитель грузоотправителя 
                <?php if ($document['supplier_name']): ?>
                    <strong><?= htmlspecialchars($document['supplier_name']) ?></strong>
                <?php else: ?>
                    —
                <?php endif; ?>
                с одной стороны, и представитель грузополучателя 
                <strong><?= htmlspecialchars($companyName) ?></strong> 
                с другой стороны, составили настоящий акт о том, что 
                грузоотправитель передал, а грузополучатель принял следующие материалы:
            </div>
        </div>
        
        <!-- Основание -->
        <?php if ($document['supplier_invoice_number'] || $document['ttn_number']): ?>
        <div style="margin-bottom: 15px; font-size: 10pt;">
            <strong>Основание:</strong>
            <?php if ($document['supplier_invoice_number']): ?>
                Счет-фактура № <?= htmlspecialchars($document['supplier_invoice_number']) ?>
                <?php if ($document['supplier_invoice_date']): ?>
                    от <?= date('d.m.Y', strtotime($document['supplier_invoice_date'])) ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($document['ttn_number']): ?>
                <?php if ($document['supplier_invoice_number']): ?>; <?php endif; ?>
                ТТН № <?= htmlspecialchars($document['ttn_number']) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Таблица материалов -->
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="num">№</th>
                        <th>Наименование материала</th>
                        <th>Ед.изм.</th>
                        <th>Кол-во</th>
                        <th>Цена, BYN</th>
                        <th>Сумма, BYN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNum = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="num"><?= $rowNum++ ?></td>
                        <td>
                            <?= htmlspecialchars($item['material_name']) ?>
                            <?php if (!empty($item['batch_number'])): ?>
                                <br><small style="color:#666;">Партия: <?= htmlspecialchars($item['batch_number']) ?></small>
                            <?php endif; ?>
                            <?php if (!empty($item['certificate_number'])): ?>
                                <br><small style="color:#666;">Сертификат: <?= htmlspecialchars($item['certificate_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['unit_symbol'] ?? 'шт.') ?></td>
                        <td class="qty"><?= number_format($item['quantity'], 3, ',', ' ') ?></td>
                        <td class="price"><?= number_format($item['unit_price'] ?? 0, 2, ',', ' ') ?></td>
                        <td class="total"><?= number_format($item['total_price'] ?? 0, 2, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;">Итого передано:</td>
                        <td class="qty"><?= number_format(array_sum(array_column($items, 'quantity')), 3, ',', ' ') ?></td>
                        <td></td>
                        <td class="total"><?= number_format($totalAmount, 2, ',', ' ') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Итоговая сумма -->
        <div class="total-section">
            <div class="total-row">
                <span class="total-label">Общая стоимость переданных материалов:</span>
                <span class="total-value"><?= number_format($totalAmount, 2, ',', ' ') ?> BYN</span>
            </div>
            <div class="total-in-words">
                Сумма прописью: <?= $totalInWords ?>
            </div>
            <div class="total-in-words" style="margin-top: 5px;">
                Без налога (НДС не облагается)
            </div>
        </div>
        
        <!-- Заключение -->
        <div class="preamble" style="margin-top: 20px;">
            <div class="preamble-text">
                Материалы приняты в полном объеме, претензий по количеству, качеству и ассортименту 
                у грузополучателя нет.
            </div>
        </div>
        
        <!-- Подписи сторон -->
        <div class="parties-signatures">
            <div class="party-block">
                <div class="party-title">ГРУЗООТПРАВИТЕЛЬ</div>
                <div class="party-info">
                    <?php if ($document['supplier_name']): ?>
                        <?= htmlspecialchars($document['supplier_name']) ?><br>
                        <?php if ($document['supplier_address']): ?>
                            <?= htmlspecialchars($document['supplier_address']) ?><br>
                        <?php endif; ?>
                        <?php if ($document['supplier_inn']): ?>
                            УНП: <?= htmlspecialchars($document['supplier_inn']) ?>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
                <div class="signature-line">
                    <span class="signature-position">(должность)</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio">(подпись)</span>
                </div>
            </div>
            
            <div class="party-block">
                <div class="party-title">ГРУЗОПОЛУЧАТЕЛЬ</div>
                <div class="party-info">
                    <?= htmlspecialchars($companyName) ?><br>
                    <?= htmlspecialchars($companyAddress) ?><br>
                    УНП: <?= htmlspecialchars($companyUNP) ?>, ОКПО: <?= htmlspecialchars($companyOKPO) ?>
                </div>
                <div class="signature-line">
                    <span class="signature-position">(должность)</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio">(подпись)</span>
                </div>
                <?php if ($document['posted_by_name']): ?>
                <div class="signature-line" style="margin-top: 10px;">
                    <span class="signature-position">Ответственный:</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio"><?= htmlspecialchars($document['posted_by_name']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Печати -->
        <div class="stamps-section">
            <div class="stamp-placeholder">М.П.<br>Отправителя</div>
            <div class="stamp-placeholder">М.П.<br>Получателя</div>
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
            Документ сформирован в системе учета ОАО «Полесьеэлектромаш»
            &nbsp;|&nbsp; Статус: <?= $document['status'] === 'posted' ? 'Проведен' : 'Черновик' ?>
            &nbsp;|&nbsp; Создан: <?= $createdDateDisplay ?>
        </div>
    </div>
</body>
</html>
