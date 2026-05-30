<?php
/**
 * Печать Счета-фактуры
 * ОАО "Полесьеэлектромаш"
 * Форма в соответствии с законодательством РБ
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
    <title>Счет-фактура № <?= htmlspecialchars($document['document_number']) ?></title>
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
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            background: #fff;
        }
        
        .document-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 5mm;
        }
        
        /* Заголовок */
        .invoice-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .invoice-title {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .invoice-number-date {
            font-size: 11pt;
            margin-top: 8px;
        }
        
        /* Таблица продавца/покупателя */
        .parties-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .parties-table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            width: 50%;
        }
        
        .party-title {
            font-weight: bold;
            text-align: center;
            background: #f5f5f5;
            padding: 4px;
            margin-bottom: 8px;
            font-size: 10pt;
        }
        
        .party-info {
            font-size: 9pt;
            line-height: 1.4;
        }
        
        .party-row {
            margin-bottom: 4px;
        }
        
        .party-label {
            font-weight: bold;
            display: inline-block;
            width: 70px;
        }
        
        /* Товарная таблица */
        .items-section {
            margin: 20px 0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: left;
            font-size: 9pt;
        }
        
        .items-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
            vertical-align: middle;
        }
        
        .items-table td.num {
            text-align: center;
            width: 30px;
        }
        
        .items-table td.qty,
        .items-table td.price,
        .items-table td.amount,
        .items-table td.vat,
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
            font-size: 9pt;
            color: #555;
        }
        
        /* Подписи */
        .signatures-section {
            margin: 30px 0;
        }
        
        .signature-block {
            margin-bottom: 20px;
        }
        
        .signature-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 10pt;
        }
        
        .signature-line {
            display: flex;
            align-items: flex-end;
            margin-bottom: 5px;
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
            height: 30px;
        }
        
        .signature-fio {
            flex: 1.5;
            border-bottom: 1px solid #000;
            margin-left: 10px;
            padding-left: 10px;
            font-size: 9pt;
        }
        
        /* Печать */
        .stamp-box {
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
        <div class="invoice-header">
            <div class="invoice-title">СЧЕТ-ФАКТУРА</div>
            <div class="invoice-number-date">
                № <?= htmlspecialchars($document['document_number']) ?> от <?= $docDateDisplay ?>
            </div>
        </div>
        
        <!-- Продавец и Покупатель -->
        <table class="parties-table">
            <tr>
                <td>
                    <div class="party-title">ПРОДАВЕЦ</div>
                    <div class="party-info">
                        <?php if ($document['supplier_name']): ?>
                            <div class="party-row">
                                <span class="party-label">Наименование:</span>
                                <?= htmlspecialchars($document['supplier_name']) ?>
                            </div>
                            <?php if ($document['supplier_address']): ?>
                            <div class="party-row">
                                <span class="party-label">Адрес:</span>
                                <?= htmlspecialchars($document['supplier_address']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($document['supplier_inn']): ?>
                            <div class="party-row">
                                <span class="party-label">УНП:</span>
                                <?= htmlspecialchars($document['supplier_inn']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($document['supplier_phone']): ?>
                            <div class="party-row">
                                <span class="party-label">Телефон:</span>
                                <?= htmlspecialchars($document['supplier_phone']) ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="party-title">ПОКУПАТЕЛЬ</div>
                    <div class="party-info">
                        <div class="party-row">
                            <span class="party-label">Наименование:</span>
                            <?= htmlspecialchars($companyName) ?>
                        </div>
                        <div class="party-row">
                            <span class="party-label">Адрес:</span>
                            <?= htmlspecialchars($companyAddress) ?>
                        </div>
                        <div class="party-row">
                            <span class="party-label">УНП:</span>
                            <?= htmlspecialchars($companyUNP) ?>
                        </div>
                        <div class="party-row">
                            <span class="party-label">ОКПО:</span>
                            <?= htmlspecialchars($companyOKPO) ?>
                        </div>
                        <div class="party-row">
                            <span class="party-label">Телефон:</span>
                            <?= htmlspecialchars($companyPhone) ?>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Основание -->
        <div style="margin-bottom: 15px; font-size: 9pt;">
            <?php if ($document['supplier_invoice_number']): ?>
                <strong>Основание:</strong> Счет-фактура продавца № <?= htmlspecialchars($document['supplier_invoice_number']) ?>
                <?php if ($document['supplier_invoice_date']): ?>
                    от <?= date('d.m.Y', strtotime($document['supplier_invoice_date'])) ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($document['ttn_number']): ?>
                <br><strong>ТТН №:</strong> <?= htmlspecialchars($document['ttn_number']) ?>
            <?php endif; ?>
        </div>
        
        <!-- Товарная таблица -->
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="num">№</th>
                        <th rowspan="2">Наименование товара</th>
                        <th rowspan="2">Ед.изм.</th>
                        <th rowspan="2">Кол-во</th>
                        <th rowspan="2">Цена за ед., BYN</th>
                        <th rowspan="2">Стоимость, BYN</th>
                        <th colspan="2">НДС</th>
                        <th rowspan="2">Всего, BYN</th>
                    </tr>
                    <tr>
                        <th>%</th>
                        <th>Сумма, BYN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNum = 1; foreach ($items as $item): 
                        $amount = floatval($item['total_price'] ?? 0);
                        $vatRate = 0; // Без НДС
                        $vatAmount = 0;
                        $totalWithVat = $amount;
                    ?>
                    <tr>
                        <td class="num"><?= $rowNum++ ?></td>
                        <td>
                            <?= htmlspecialchars($item['material_name']) ?>
                            <?php if (!empty($item['batch_number'])): ?>
                                <br><small style="color:#666;">Партия: <?= htmlspecialchars($item['batch_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['unit_symbol'] ?? 'шт.') ?></td>
                        <td class="qty"><?= number_format($item['quantity'], 3, ',', ' ') ?></td>
                        <td class="price"><?= number_format($item['unit_price'] ?? 0, 2, ',', ' ') ?></td>
                        <td class="amount"><?= number_format($amount, 2, ',', ' ') ?></td>
                        <td class="vat"><?= $vatRate ?>%</td>
                        <td class="vat"><?= number_format($vatAmount, 2, ',', ' ') ?></td>
                        <td class="total"><?= number_format($totalWithVat, 2, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align: right;">Итого:</td>
                        <td class="amount"><?= number_format($totalAmount, 2, ',', ' ') ?></td>
                        <td class="vat">—</td>
                        <td class="vat">0.00</td>
                        <td class="total"><?= number_format($totalAmount, 2, ',', ' ') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Итоговая сумма -->
        <div class="total-section">
            <div class="total-row">
                <span class="total-label">Всего к оплате:</span>
                <span class="total-value"><?= number_format($totalAmount, 2, ',', ' ') ?> BYN</span>
            </div>
            <div class="total-in-words">
                Сумма прописью: <?= $totalInWords ?>
            </div>
            <div class="total-in-words" style="margin-top: 5px;">
                Без налога (НДС не облагается)
            </div>
        </div>
        
        <!-- Подписи -->
        <div class="signatures-section">
            <div class="signature-block">
                <div class="signature-title">Продавец:</div>
                <div class="signature-line">
                    <span class="signature-position">(должность)</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio">(подпись)</span>
                </div>
            </div>
            
            <div class="signature-block">
                <div class="signature-title">Покупатель:</div>
                <div class="signature-line">
                    <span class="signature-position">(должность)</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio">(подпись)</span>
                </div>
            </div>
            
            <?php if ($document['posted_by_name']): ?>
            <div class="signature-block">
                <div class="signature-title">Ответственный:</div>
                <div class="signature-line">
                    <span class="signature-position">(должность)</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio"><?= htmlspecialchars($document['posted_by_name']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Печать -->
        <div class="stamp-box">
            <div class="stamp-placeholder">М.П.</div>
            <div style="font-size: 8pt; color: #999;">
                Документ создан: <?= $createdDateDisplay ?>
            </div>
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
        </div>
    </div>
</body>
</html>
