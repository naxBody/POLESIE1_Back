<?php
/**
 * Печать платежа
 * Формирование печатной формы платежного документа
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$pdo = getDbConnection();

if (!isset($_GET['id'])) {
    die("Не указан ID платежа");
}

$paymentId = (int)$_GET['id'];

// Получаем данные платежа
$stmt = $pdo->prepare("
    SELECT 
        pd.*,
        pt.name as payment_type_name, pt.type as flow_type, pt.category,
        c.name as contractor_name, c.inn as contractor_inn, c.address as contractor_address, 
        c.email as contractor_email, c.phone as contractor_phone,
        ba.account_number as bank_account, ba.account_holder, ba.bank_name, ba.bank_bic,
        u.full_name as created_by_name, u2.full_name as posted_by_name,
        ea.name as expense_article_name,
        o.order_number,
        CASE pd.status
            WHEN 'draft' THEN 'Черновик'
            WHEN 'pending' THEN 'На согласовании'
            WHEN 'approved' THEN 'Утвержден'
            WHEN 'posted' THEN 'Проведен'
            WHEN 'cancelled' THEN 'Отменен'
            ELSE pd.status
        END as status_name
    FROM payment_documents pd
    JOIN payment_types pt ON pd.payment_type_id = pt.id
    LEFT JOIN contractors c ON pd.contractor_id = c.id
    JOIN bank_accounts ba ON pd.bank_account_id = ba.id
    LEFT JOIN users u ON pd.created_by = u.id
    LEFT JOIN users u2 ON pd.posted_by = u2.id
    LEFT JOIN expense_articles ea ON pd.expense_article_id = ea.id
    LEFT JOIN orders o ON pd.order_id = o.id
    WHERE pd.id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("Платеж не найден");
}

// Данные организации
$companyName = "ОАО «Полесьеэлектромаш»";
$companyAddress = "225644, Брестская область, г. Лунинец, ул. Красная, 179";
$companyPhone = "+375 1647 2-78-09";
$companyEmail = "polesie@electromash.by";
$companyUNP = "400086359";
$companyOKPO = "01032356";
$companyBank = "ОАО «АСБ Беларусбанк»";
$companyAccount = "BY00AKBB30120000000000000000";
$companyBIC = "AKBBBY2X";

// Даты
$documentDateDisplay = date('d.m.Y', strtotime($payment['document_date']));
$createdDateDisplay = date('d.m.Y H:i', strtotime($payment['created_at']));

// Сумма прописью
function sumInWordsBYN($sum) {
    $sum = floatval($sum);
    $wholePart = floor($sum);
    $fractionalPart = round(($sum - $wholePart) * 100);
    
    return number_format($sum, 2, ',', ' ') . ' (' . $wholePart . ' руб. ' . $fractionalPart . ' коп.)';
}

$totalAmount = floatval($payment['amount'] ?? 0);
$totalInWords = sumInWordsBYN($totalAmount);

// Тип документа
$documentTitle = $payment['flow_type'] === 'income' ? 'ПЛАТЕЖНОЕ ПОРУЧЕНИЕ (входящее)' : 'ПЛАТЕЖНОЕ ПОРУЧЕНИЕ (исходящее)';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Платеж № <?= htmlspecialchars($payment['document_number']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        
        /* Шапка */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .logo-section {
            flex: 1;
        }
        
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            font-size: 9pt;
            line-height: 1.3;
            color: #555;
        }
        
        .document-title-section {
            text-align: right;
        }
        
        .document-title {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .document-number-date {
            font-size: 11pt;
            margin-bottom: 5px;
        }
        
        /* Основной блок информации */
        .info-block {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #000;
            background: #fafafa;
        }
        
        .block-title {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        
        .info-row {
            display: flex;
        }
        
        .info-label {
            font-weight: bold;
            width: 160px;
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
        }
        
        /* Блок суммы */
        .amount-block {
            margin: 25px 0;
            padding: 15px;
            border: 2px solid #000;
            background: #fafafa;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .amount-row:last-child {
            margin-bottom: 0;
        }
        
        .amount-label {
            font-weight: bold;
        }
        
        .amount-value {
            font-size: 12pt;
            font-weight: bold;
        }
        
        .amount-in-words {
            margin-top: 12px;
            font-style: italic;
            font-size: 9pt;
            color: #555;
        }
        
        /* Назначение платежа */
        .purpose-block {
            margin: 25px 0;
            padding: 15px;
            border: 1px solid #ddd;
            background: #fff;
        }
        
        .purpose-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .purpose-content {
            font-size: 10pt;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        /* Подписи */
        .signatures-section {
            margin: 35px 0;
        }
        
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        
        .signature-column {
            width: 48%;
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
            flex: 1;
            border-bottom: 1px solid #000;
            padding-right: 10px;
            font-size: 9pt;
        }
        
        .signature-space {
            width: 120px;
            border-bottom: 1px solid #000;
            margin-left: 10px;
            height: 30px;
        }
        
        /* Место для печати */
        .stamp-area {
            width: 100px;
            height: 100px;
            border: 2px dashed #999;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 8pt;
            text-align: center;
            margin: 20px auto;
        }
        
        /* Футер */
        .footer {
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
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.25);
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-info {
            background: #0891b2;
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
            
            .btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="control-panel">
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="bi bi-x-lg"></i> Закрыть
        </button>
        <button class="btn btn-secondary" onclick="window.history.back()">
            <i class="bi bi-arrow-left-short"></i> Назад
        </button>
        <button class="btn btn-primary" onclick="downloadPDF()">
            <i class="bi bi-file-earmark-arrow-down-fill"></i> Скачать файл
        </button>
        <button class="btn btn-success" onclick="printDocument()">
            <i class="bi bi-printer-fill"></i> Печать
        </button>
    </div>

    <!-- Подключаем библиотеку html2pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <script>
        // Функция для скачивания документа в формате PDF с сохранением разметки
        function downloadPDF() {
            const element = document.querySelector('.document-container');
            const documentNumber = '<?= htmlspecialchars($payment['document_number']) ?>';

            const opt = {
                margin:       [10, 10, 10, 10], // отступы: верх, лево, низ, право (в мм)
                filename:     `Platezh_${documentNumber}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Генерируем PDF и скачиваем
            html2pdf().set(opt).from(element).save();
        }

        // Функция для печати документа
        function printDocument() {
            window.print();
        }

        // Автоматическая подсказка при загрузке страницы
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                alert('Документ готов к экспорту!\n\n• Нажмите "Скачать файл" чтобы сохранить документ с полной разметкой.\n• Или нажмите "Печать" для быстрой печати.');
            }, 500);
        });
    </script>
    
    <div class="document-container">
        <!-- Шапка -->
        <div class="header">
            <div class="logo-section">
                <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
                <div class="company-info">
                    <div>УНП: <?= htmlspecialchars($companyUNP) ?>, ОКПО: <?= htmlspecialchars($companyOKPO) ?></div>
                    <div><?= htmlspecialchars($companyAddress) ?></div>
                    <div>Тел.: <?= htmlspecialchars($companyPhone) ?>, E-mail: <?= htmlspecialchars($companyEmail) ?></div>
                </div>
            </div>
            <div class="document-title-section">
                <div class="document-title"><?= $documentTitle ?></div>
                <div class="document-number-date">
                    № <?= htmlspecialchars($payment['document_number']) ?> от <?= $documentDateDisplay ?>
                </div>
                <div style="font-size: 9pt; color: #666;">
                    Статус: <?= htmlspecialchars($payment['status_name']) ?>
                </div>
            </div>
        </div>
        
        <!-- Основная информация -->
        <div class="info-block">
            <div class="block-title">Основная информация</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Тип операции:</span>
                    <span class="info-value"><strong><?= $payment['flow_type'] === 'income' ? 'Доход' : 'Расход' ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Тип платежа:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['payment_type_name']) ?> (<?= htmlspecialchars($payment['category']) ?>)</span>
                </div>
                <?php if ($payment['contractor_name']): ?>
                <div class="info-row">
                    <span class="info-label">Контрагент:</span>
                    <span class="info-value"><strong><?= htmlspecialchars($payment['contractor_name']) ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">ИНН контрагента:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['contractor_inn'] ?? '—') ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Банк получателя:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['bank_name']) ?> (БИК: <?= htmlspecialchars($payment['bank_bic']) ?>)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Счет:</span>
                    <span class="info-value" style="font-family: monospace;"><?= htmlspecialchars($payment['bank_account']) ?></span>
                </div>
                <?php if ($payment['expense_article_name']): ?>
                <div class="info-row">
                    <span class="info-label">Статья затрат:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['expense_article_name']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($payment['order_number']): ?>
                <div class="info-row">
                    <span class="info-label">Заказ:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['order_number']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($payment['document_reference']): ?>
                <div class="info-row" style="grid-column: 1 / -1;">
                    <span class="info-label">Основание:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['document_reference']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Информация о контрагенте (если указан) -->
        <?php if ($payment['contractor_name']): ?>
        <div class="info-block">
            <div class="block-title">Реквизиты контрагента</div>
            <div class="info-grid">
                <div class="info-row" style="grid-column: 1 / -1;">
                    <span class="info-label">Наименование:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['contractor_name']) ?></span>
                </div>
                <?php if ($payment['contractor_inn']): ?>
                <div class="info-row">
                    <span class="info-label">УНП/ИНН:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['contractor_inn']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($payment['contractor_address']): ?>
                <div class="info-row" style="grid-column: 1 / -1;">
                    <span class="info-label">Адрес:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['contractor_address']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($payment['contractor_phone']): ?>
                <div class="info-row">
                    <span class="info-label">Телефон:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['contractor_phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($payment['contractor_email']): ?>
                <div class="info-row">
                    <span class="info-label">E-mail:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['contractor_email']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Банковские реквизиты -->
        <div class="info-block">
            <div class="block-title">Банковские реквизиты</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Владелец счета:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['account_holder']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Номер счета:</span>
                    <span class="info-value" style="font-family: monospace;"><?= htmlspecialchars($payment['bank_account']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Банк:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['bank_name']) ?></span>
                </div>
                <?php if ($payment['bank_bic']): ?>
                <div class="info-row">
                    <span class="info-label">БИК:</span>
                    <span class="info-value"><?= htmlspecialchars($payment['bank_bic']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Сумма -->
        <div class="amount-block">
            <div class="amount-row">
                <span class="amount-label">Сумма платежа:</span>
                <span class="amount-value"><?= number_format($totalAmount, 2, ',', ' ') ?> <?= $payment['currency'] ?></span>
            </div>
            <?php if ($payment['vat_amount'] > 0): ?>
            <div class="amount-row">
                <span class="amount-label">В том числе НДС (<?= $payment['vat_rate'] ?>%):</span>
                <span class="amount-value"><?= number_format($payment['vat_amount'], 2, ',', ' ') ?> <?= $payment['currency'] ?></span>
            </div>
            <?php else: ?>
            <div class="amount-row">
                <span class="amount-label">Без НДС</span>
            </div>
            <?php endif; ?>
            <div class="amount-in-words">
                Сумма прописью: <?= $totalInWords ?>
            </div>
        </div>
        
        <!-- Назначение платежа -->
        <div class="purpose-block">
            <div class="purpose-title">Назначение платежа</div>
            <div class="purpose-content"><?= nl2br(htmlspecialchars($payment['payment_purpose'] ?: $payment['description'] ?: 'Не указано')) ?></div>
        </div>
        
        <!-- Подписи -->
        <div class="signatures-section">
            <div class="signature-row">
                <div class="signature-column">
                    <div class="signature-title">Руководитель</div>
                    <div class="signature-line">
                        <span class="signature-position">Генеральный директор</span>
                        <span class="signature-space"></span>
                    </div>
                </div>
                <div class="signature-column">
                    <div class="signature-title">Главный бухгалтер</div>
                    <div class="signature-line">
                        <span class="signature-position">Главный бухгалтер</span>
                        <span class="signature-space"></span>
                    </div>
                </div>
            </div>
            
            <div class="stamp-area">
                <i class="bi bi-stamp" style="font-size: 40px; opacity: 0.3;"></i>
                <div style="margin-top: 5px;">М.П.</div>
            </div>
        </div>
        
        <!-- Футер -->
        <div class="footer">
            Документ создан: <?= $createdDateDisplay ?> | 
            Автор: <?= htmlspecialchars($payment['created_by_name']) ?> | 
            Статус: <?= htmlspecialchars($payment['status_name']) ?>
        </div>
    </div>
</body>
</html>
