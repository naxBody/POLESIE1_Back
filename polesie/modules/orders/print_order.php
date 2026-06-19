<?php
/**
 * Печать заказа
 * Формирование печатной формы заказа покупателя
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$pdo = getDbConnection();

if (!isset($_GET['id'])) {
    die("Не указан ID заказа");
}

$orderId = (int)$_GET['id'];

// Получаем данные заказа
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        c.name as customer_name,
        c.inn as customer_inn,
        c.address as customer_address,
        c.phone as customer_phone,
        c.email as customer_email,
        c.contact_person as customer_contact,
        u.full_name as responsible_name,
        CASE o.status
            WHEN 'new' THEN 'Новый'
            WHEN 'processing' THEN 'В работе'
            WHEN 'ready' THEN 'Готов'
            WHEN 'shipped' THEN 'Отгружен'
            WHEN 'cancelled' THEN 'Отменен'
            ELSE o.status
        END as status_name
    FROM orders o
    LEFT JOIN contractors c ON o.customer_id = c.id
    LEFT JOIN users u ON o.responsible_user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Заказ не найден");
}

// Получаем позиции заказа
$itemsStmt = $pdo->prepare("
    SELECT 
        oi.*,
        p.article,
        p.name as product_name,
        bu.symbol as unit_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN base_units bu ON p.base_unit_id = bu.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

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
$orderDateDisplay = date('d.m.Y', strtotime($order['order_date']));
$createdDateDisplay = date('d.m.Y H:i', strtotime($order['created_at']));

// Сумма прописью
function sumInWordsBYN($sum) {
    $sum = floatval($sum);
    $wholePart = floor($sum);
    $fractionalPart = round(($sum - $wholePart) * 100);
    
    return number_format($sum, 2, ',', ' ') . ' (' . $wholePart . ' руб. ' . $fractionalPart . ' коп.)';
}

$totalAmount = floatval($order['total_amount'] ?? 0);
$totalInWords = sumInWordsBYN($totalAmount);

// Доставка
$deliveryDateDisplay = !empty($order['delivery_date']) ? date('d.m.Y', strtotime($order['delivery_date'])) : 'не указана';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ № <?= htmlspecialchars($order['order_number']) ?></title>
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
        
        .order-title-section {
            text-align: right;
        }
        
        .order-title {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .order-number-date {
            font-size: 11pt;
            margin-bottom: 5px;
        }
        
        /* Блок заказчика */
        .customer-block {
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
            width: 140px;
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
        }
        
        /* Таблица товаров */
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
            vertical-align: middle;
        }
        
        .items-table td.num {
            text-align: center;
            width: 40px;
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
        
        /* Итоговый блок */
        .total-block {
            margin: 25px 0;
            padding: 15px;
            border: 2px solid #000;
            background: #fafafa;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .total-row:last-child {
            margin-bottom: 0;
        }
        
        .total-label {
            font-weight: bold;
        }
        
        .total-value {
            font-size: 12pt;
            font-weight: bold;
        }
        
        .total-in-words {
            margin-top: 12px;
            font-style: italic;
            font-size: 9pt;
            color: #555;
        }
        
        /* Условия поставки */
        .delivery-block {
            margin: 25px 0;
            padding: 15px;
            border: 1px solid #ddd;
            background: #fff;
        }
        
        .delivery-title {
            font-weight: bold;
            margin-bottom: 10px;
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
        
        /* Примечание */
        .notes-block {
            margin-top: 20px;
            padding: 12px;
            border: 1px solid #ddd;
            background: #fafafa;
            min-height: 60px;
        }
        
        .notes-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 9pt;
        }
        
        .notes-content {
            font-size: 9pt;
            color: #555;
            white-space: pre-wrap;
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
        <button class="btn btn-secondary" onclick="window.close()">Закрыть</button>
        <button class="btn btn-secondary" onclick="window.history.back()">← Назад</button>
        <button class="btn btn-success" onclick="downloadAsPDF()">
            <i class="bi bi-file-earmark-pdf" style="margin-right: 6px;"></i>
            Скачать PDF
        </button>
    </div>
    
    <script>
        // Функция для скачивания в формате PDF через печать
        function downloadAsPDF() {
            window.print();
        }
        
        // Автоматическая подсказка при загрузке страницы
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                alert('Документ готов к экспорту!\n\nНажмите "Скачать PDF" и выберите "Сохранить как PDF" в диалоге печати браузера.\n\nФайл будет сохранён с полной разметкой документа.');
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
            <div class="order-title-section">
                <div class="order-title">ЗАКАЗ</div>
                <div class="order-number-date">
                    № <?= htmlspecialchars($order['order_number']) ?> от <?= $orderDateDisplay ?>
                </div>
                <div style="font-size: 9pt; color: #666;">
                    Статус: <?= htmlspecialchars($order['status_name']) ?>
                </div>
            </div>
        </div>
        
        <!-- Информация о заказчике -->
        <div class="customer-block">
            <div class="block-title">📋 Заказчик</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Наименование:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_name'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">УНП/ИНН:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_inn'] ?? '—') ?></span>
                </div>
                <?php if (!empty($order['customer_contact'])): ?>
                <div class="info-row">
                    <span class="info-label">Контактное лицо:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_contact']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['customer_phone'])): ?>
                <div class="info-row">
                    <span class="info-label">Телефон:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['customer_email'])): ?>
                <div class="info-row">
                    <span class="info-label">E-mail:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['customer_address'])): ?>
                <div class="info-row" style="grid-column: 1 / -1;">
                    <span class="info-label">Адрес:</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_address']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Информация о заказе -->
        <div class="delivery-block">
            <div class="delivery-title">📦 Информация о поставке</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Дата заказа:</span>
                    <span class="info-value"><?= $orderDateDisplay ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Дата доставки:</span>
                    <span class="info-value"><?= $deliveryDateDisplay ?></span>
                </div>
                <?php if (!empty($order['responsible_name'])): ?>
                <div class="info-row">
                    <span class="info-label">Ответственный:</span>
                    <span class="info-value"><?= htmlspecialchars($order['responsible_name']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['notes'])): ?>
                <div class="info-row" style="grid-column: 1 / -1;">
                    <span class="info-label">Примечание:</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($order['notes'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Товары -->
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="num">№</th>
                        <th>Артикул</th>
                        <th>Наименование</th>
                        <th style="text-align: center; width: 80px;">Ед.изм.</th>
                        <th style="text-align: right; width: 90px;">Кол-во</th>
                        <th style="text-align: right; width: 120px;">Цена, BYN</th>
                        <th style="text-align: right; width: 130px;">Сумма, BYN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rowNum = 1;
                    foreach ($items as $item): 
                    ?>
                    <tr>
                        <td class="num"><?= $rowNum++ ?></td>
                        <td><?= htmlspecialchars($item['article'] ?? '—') ?></td>
                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                            <?php if (!empty($item['description'])): ?>
                                <div style="font-size: 8pt; color: #666; margin-top: 3px;">
                                    <?= htmlspecialchars($item['description']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?= htmlspecialchars($item['unit_name'] ?? 'шт.') ?></td>
                        <td style="text-align: right;"><?= number_format($item['quantity'], 3, ',', ' ') ?></td>
                        <td style="text-align: right;"><?= number_format($item['price'], 2, ',', ' ') ?></td>
                        <td style="text-align: right;"><strong><?= number_format($item['total'], 2, ',', ' ') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align: right; padding: 12px;">ИТОГО:</td>
                        <td style="text-align: right; padding: 12px; font-size: 12pt;"><?= number_format($totalAmount, 2, ',', ' ') ?> BYN</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Итоговая сумма -->
        <div class="total-block">
            <div class="total-row">
                <span class="total-label">Общая сумма заказа:</span>
                <span class="total-value"><?= number_format($totalAmount, 2, ',', ' ') ?> BYN</span>
            </div>
            <div class="total-in-words">
                Сумма прописью: <?= $totalInWords ?>
            </div>
        </div>
        
        <!-- Подписи -->
        <div class="signatures-section">
            <div class="signature-row">
                <div class="signature-column">
                    <div class="signature-title">От Продавца:</div>
                    <div class="signature-line">
                        <div class="signature-position">Должность</div>
                        <div class="signature-space"></div>
                    </div>
                    <div class="signature-line">
                        <div class="signature-position">Подпись</div>
                        <div class="signature-space"></div>
                    </div>
                    <div class="signature-line">
                        <div class="signature-position">Ф.И.О.</div>
                        <div class="signature-space"></div>
                    </div>
                </div>
                
                <div class="signature-column">
                    <div class="signature-title">От Покупателя:</div>
                    <div class="signature-line">
                        <div class="signature-position">Должность</div>
                        <div class="signature-space"></div>
                    </div>
                    <div class="signature-line">
                        <div class="signature-position">Подпись</div>
                        <div class="signature-space"></div>
                    </div>
                    <div class="signature-line">
                        <div class="signature-position">Ф.И.О.</div>
                        <div class="signature-space"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Примечание -->
        <?php if (!empty($order['notes'])): ?>
        <div class="notes-block">
            <div class="notes-title">📝 Дополнительные условия:</div>
            <div class="notes-content"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Футер -->
        <div class="footer">
            <div>Документ сформирован автоматически <?= $createdDateDisplay ?></div>
            <div><?= htmlspecialchars($companyName) ?> • <?= htmlspecialchars($companyAddress) ?> • <?= htmlspecialchars($companyPhone) ?></div>
        </div>
    </div>
    
    <script>
        // Автоматическая печать при загрузке (опционально)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
