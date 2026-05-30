<?php
/**
 * Печать Сертификата/Паспорта качества
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        bu.symbol as unit_symbol,
        JSON_EXTRACT(m.specifications, '$.gost') as gost,
        JSON_EXTRACT(m.specifications, '$.grade') as grade,
        JSON_EXTRACT(m.specifications, '$.size') as size
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

// Определяем тип документа
$docTypeCode = strtoupper($document['document_type_code'] ?? '');
$isCertificate = in_array($docTypeCode, ['CERTIFICATE', 'QUALITY_PASSPORT']);
$docTitle = $isCertificate && $docTypeCode === 'QUALITY_PASSPORT' ? 'ПАСПОРТ КАЧЕСТВА' : 'СЕРТИФИКАТ';
$docSubtitle = $isCertificate && $docTypeCode === 'QUALITY_PASSPORT' ? '' : 'соответствия/качества';
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
            padding: 5mm;
        }
        
        /* Заголовок с рамкой */
        .cert-header {
            text-align: center;
            margin-bottom: 25px;
            border: 3px double #000;
            padding: 20px;
        }
        
        .cert-title {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .cert-subtitle {
            font-size: 11pt;
            font-style: italic;
            color: #555;
        }
        
        .cert-number-date {
            margin-top: 15px;
            font-size: 11pt;
            font-weight: bold;
        }
        
        /* Блок информации о документе */
        .info-block {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #000;
            background: #fafafa;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            width: 180px;
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
            border-bottom: 1px dotted #000;
            padding-left: 10px;
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
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
        }
        
        .items-table td.num {
            text-align: center;
            width: 35px;
        }
        
        .items-table td.qty {
            text-align: right;
        }
        
        /* Характеристики материалов */
        .specs-section {
            margin: 20px 0;
        }
        
        .specs-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 11pt;
        }
        
        .specs-list {
            list-style: none;
            padding-left: 0;
        }
        
        .specs-list li {
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }
        
        .specs-list li:before {
            content: "•";
            position: absolute;
            left: 5px;
            font-weight: bold;
        }
        
        /* Подтверждение качества */
        .quality-statement {
            margin: 25px 0;
            padding: 15px;
            border: 2px solid #000;
            background: #fff9e6;
            text-align: justify;
        }
        
        .quality-text {
            font-size: 11pt;
            line-height: 1.6;
        }
        
        .quality-highlight {
            font-weight: bold;
            text-transform: uppercase;
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
        .stamp-section {
            margin-top: 20px;
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
        
        .stamp-info {
            flex: 1;
            margin-left: 20px;
            font-size: 9pt;
            color: #666;
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
            
            .quality-statement {
                background: #fff;
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
        <div class="cert-header">
            <div class="cert-title"><?= htmlspecialchars($docTitle) ?></div>
            <?php if ($docSubtitle): ?>
            <div class="cert-subtitle"><?= htmlspecialchars($docSubtitle) ?></div>
            <?php endif; ?>
            <div class="cert-number-date">
                № <?= htmlspecialchars($document['document_number']) ?> от <?= $docDateDisplay ?>
            </div>
        </div>
        
        <!-- Информация о документе -->
        <div class="info-block">
            <?php if ($document['supplier_name']): ?>
            <div class="info-row">
                <span class="info-label">Производитель/Поставщик:</span>
                <span class="info-value"><?= htmlspecialchars($document['supplier_name']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($document['supplier_address']): ?>
            <div class="info-row">
                <span class="info-label">Адрес производителя:</span>
                <span class="info-value"><?= htmlspecialchars($document['supplier_address']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($document['supplier_invoice_number']): ?>
            <div class="info-row">
                <span class="info-label">Счет-фактура:</span>
                <span class="info-value">
                    № <?= htmlspecialchars($document['supplier_invoice_number']) ?>
                    <?php if ($document['supplier_invoice_date']): ?>
                        от <?= date('d.m.Y', strtotime($document['supplier_invoice_date'])) ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if ($document['ttn_number']): ?>
            <div class="info-row">
                <span class="info-label">ТТН №:</span>
                <span class="info-value"><?= htmlspecialchars($document['ttn_number']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="info-label">Дата выдачи:</span>
                <span class="info-value"><?= $docDateDisplay ?></span>
            </div>
        </div>
        
        <!-- Таблица материалов -->
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="num">№</th>
                        <th>Наименование продукции</th>
                        <th>Марка/Сорт</th>
                        <th>ГОСТ/ТУ</th>
                        <th>Номер партии</th>
                        <th>Кол-во</th>
                        <th>Ед.изм.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNum = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="num"><?= $rowNum++ ?></td>
                        <td><?= htmlspecialchars($item['material_name']) ?></td>
                        <td>
                            <?php 
                            $grade = isset($item['grade']) ? trim($item['grade'], '"\'') : '';
                            echo !empty($grade) ? htmlspecialchars($grade) : '—';
                            ?>
                        </td>
                        <td>
                            <?php 
                            $gost = isset($item['gost']) ? trim($item['gost'], '"\'') : '';
                            echo !empty($gost) ? htmlspecialchars($gost) : '—';
                            ?>
                        </td>
                        <td><?= htmlspecialchars($item['batch_number'] ?? '—') ?></td>
                        <td class="qty"><?= number_format($item['quantity'], 3, ',', ' ') ?></td>
                        <td><?= htmlspecialchars($item['unit_symbol'] ?? 'шт.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Характеристики -->
        <?php 
        $hasSpecs = false;
        foreach ($items as $item) {
            $size = isset($item['size']) ? trim($item['size'], '"\'') : '';
            if (!empty($size) || !empty($item['certificate_number'])) {
                $hasSpecs = true;
                break;
            }
        }
        ?>
        <?php if ($hasSpecs): ?>
        <div class="specs-section">
            <div class="specs-title">Характеристики продукции:</div>
            <ul class="specs-list">
                <?php foreach ($items as $item): 
                    $size = isset($item['size']) ? trim($item['size'], '"\'') : '';
                ?>
                    <?php if (!empty($size)): ?>
                    <li>
                        <strong><?= htmlspecialchars($item['material_name']) ?>:</strong> 
                        Размер/сечение: <?= htmlspecialchars($size) ?>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($item['certificate_number'])): ?>
                    <li>
                        <strong><?= htmlspecialchars($item['material_name']) ?>:</strong> 
                        Сертификат № <?= htmlspecialchars($item['certificate_number']) ?>
                        <?php if (!empty($item['certificate_date'])): ?>
                            от <?= date('d.m.Y', strtotime($item['certificate_date'])) ?>
                        <?php endif; ?>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- Подтверждение качества -->
        <div class="quality-statement">
            <div class="quality-text">
                Настоящим подтверждается, что вышеуказанная продукция изготовлена в соответствии 
                с требованиями нормативных документов, прошла все необходимые испытания и проверки, 
                и соответствует установленным стандартам качества.
            </div>
            <div class="quality-text" style="margin-top: 10px;">
                <span class="quality-highlight">Продукция признана годной</span> для использования 
                по назначению в соответствии с областью применения.
            </div>
            <?php if (!empty($document['notes'])): ?>
            <div class="quality-text" style="margin-top: 10px; font-style: italic;">
                Дополнительные сведения: <?= htmlspecialchars($document['notes']) ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Подписи -->
        <div class="signatures-section">
            <div class="signature-block">
                <div class="signature-title">Ответственное лицо производителя:</div>
                <div class="signature-line">
                    <span class="signature-position">(должность)</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio">(подпись)</span>
                </div>
            </div>
            
            <div class="signature-block">
                <div class="signature-title">Представитель получателя:</div>
                <div class="signature-line">
                    <span class="signature-position">(должность)</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio">(подпись)</span>
                </div>
            </div>
            
            <?php if ($document['posted_by_name']): ?>
            <div class="signature-block">
                <div class="signature-title">Принял на складе:</div>
                <div class="signature-line">
                    <span class="signature-position">Кладовщик</span>
                    <span class="signature-space"></span>
                    <span class="signature-fio"><?= htmlspecialchars($document['posted_by_name']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Печать -->
        <div class="stamp-section">
            <div class="stamp-placeholder">М.П.<br>Производителя</div>
            <div class="stamp-info">
                <p>Сертификат выдан на основании:</p>
                <p style="margin-top: 5px;">
                    - протокола испытаний<br>
                    - результатов входного контроля<br>
                    - сопроводительных документов производителя
                </p>
                <p style="margin-top: 10px; font-size: 8pt; color: #999;">
                    Дата создания документа: <?= $createdDateDisplay ?>
                </p>
            </div>
        </div>
        
        <!-- Футер -->
        <div class="document-footer">
            Документ сформирован в системе учета ОАО «Полесьеэлектромаш»
            &nbsp;|&nbsp; Статус: <?= $document['status'] === 'posted' ? 'Проведен' : 'Черновик' ?>
        </div>
    </div>
</body>
</html>
