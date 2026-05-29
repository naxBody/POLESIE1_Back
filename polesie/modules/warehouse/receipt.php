<?php
/**
 * Журнал поступления материалов (ТТН, накладные)
 * ОАО "Полесьеэлектромаш"
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
session_start();

if (!isLoggedIn()) {
    redirect(pageUrl('login.php'));
}

$user = getCurrentUser();
$pdo = getDbConnection();
$pageTitle = 'Поступление материалов';

// Получаем типы документов для отображения в фильтре
$docTypes = [];
try {
    $stmt = $pdo->query("SELECT id, name, name_ru, code FROM receipt_document_types WHERE is_active = TRUE ORDER BY sort_order");
    $docTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Игнорируем если таблица еще не создана
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-success {
            background: #16a34a;
            color: white;
        }
        .btn-success:hover {
            background: #15803d;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .filter-panel {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-field label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }
        .filter-field input,
        .filter-field select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .data-table th,
        .data-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
        }
        .data-table tr:hover {
            background: #f9fafb;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-draft {
            background: #fef3c7;
            color: #92400e;
        }
        .status-posted {
            background: #d1fae5;
            color: #065f46;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            color: #111827;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .modal-body {
            padding: 24px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 13px;
            color: #374151;
            font-weight: 500;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .items-table th,
        .items-table td {
            padding: 10px;
            border: 1px solid #e5e7eb;
        }
        .items-table th {
            background: #f9fafb;
            font-weight: 600;
            font-size: 13px;
        }
        .items-table input,
        .items-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .total-row {
            font-weight: 600;
            background: #f9fafb;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>
        
        <div class="content-area">
            <div class="page-header">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <button class="btn btn-primary" onclick="openNewDocumentModal()">
                    + Новый документ поступления
                </button>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <div class="filter-field">
                    <label>Статус</label>
                    <select id="filterStatus" onchange="loadDocuments()">
                        <option value="">Все</option>
                        <option value="draft">Черновик</option>
                        <option value="posted">Проведен</option>
                        <option value="cancelled">Отменен</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Тип документа</label>
                    <select id="filterDocType" onchange="loadDocuments()">
                        <option value="">Все типы</option>
                        <?php foreach ($docTypes as $dt): ?>
                            <option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>С даты</label>
                    <input type="date" id="filterDateFrom" onchange="loadDocuments()">
                </div>
                <div class="filter-field">
                    <label>По дату</label>
                    <input type="date" id="filterDateTo" onchange="loadDocuments()">
                </div>
                <div class="filter-field">
                    <label>&nbsp;</label>
                    <button class="btn btn-secondary" onclick="resetFilters()">Сбросить</button>
                </div>
            </div>
            
            <!-- Таблица документов -->
            <table class="data-table">
                <thead>
                    <tr>
                        <th>№ документа</th>
                        <th>Дата</th>
                        <th>Тип</th>
                        <th>Поставщик</th>
                        <th>Счет №</th>
                        <th>ТТН</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="documentsTableBody">
                    <tr><td colspan="9" class="loading">Загрузка...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Модальное окно документа -->
    <div class="modal-overlay" id="documentModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitle">Новый документ поступления</h2>
                <button class="modal-close" onclick="closeDocumentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="docId">
                
                <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin-bottom: 16px; border-left: 4px solid #0284c7;">
                    <h4 style="margin: 0 0 8px 0; color: #0369a1; font-size: 14px;">📋 Описание полей документа</h4>
                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #0c4a6e; line-height: 1.6;">
                        <li><strong>№ документа</strong> — номер накладной/ТТН (генерируется автоматически, если не указан)</li>
                        <li><strong>Тип документа</strong> — вид документа: ТТН, Накладная, Акт, Сертификат и т.д.</li>
                        <li><strong>Дата документа</strong> — дата оформления документа</li>
                        <li><strong>Поставщик</strong> — организация-поставщик материалов</li>
                        <li><strong>Счет-фактура №</strong> — номер счета от поставщика</li>
                        <li><strong>ТТН №</strong> — номер товарно-транспортной накладной</li>
                        <li><strong>Комментарий</strong> — дополнительная информация по документу</li>
                    </ul>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>№ документа *</label>
                        <input type="text" id="docNumber" placeholder="Автогенерация">
                    </div>
                    <div class="form-group">
                        <label>Тип документа *</label>
                        <select id="docType">
                            <option value="">Выберите тип</option>
                            <?php foreach ($docTypes as $dt): ?>
                                <option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Дата документа *</label>
                        <input type="date" id="docDate" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Поставщик *</label>
                        <select id="supplier" disabled>
                            <option value="">Загрузка...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Счет-фактура №</label>
                        <input type="text" id="invoiceNumber">
                    </div>
                    <div class="form-group">
                        <label>Дата счета</label>
                        <input type="date" id="invoiceDate">
                    </div>
                    <div class="form-group">
                        <label>ТТН №</label>
                        <input type="text" id="ttnNumber">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Комментарий</label>
                    <textarea id="docNotes" placeholder="Дополнительная информация"></textarea>
                </div>
                
                <h3 style="margin: 24px 0 12px;">Материалы</h3>
                <button class="btn btn-sm btn-secondary" onclick="addMaterialRow()">+ Добавить материал</button>
                
                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Материал</th>
                            <th style="width: 15%;">Количество</th>
                            <th style="width: 15%;">Цена</th>
                            <th style="width: 15%;">Сумма</th>
                            <th style="width: 15%;">Партия/Сертификат</th>
                            <th style="width: 10%;">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;">Итого:</td>
                            <td id="itemsTotal">0.00</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <span id="saveMessage" style="margin-right: auto; color: #16a34a;"></span>
                <button class="btn btn-secondary" onclick="closeDocumentModal()">Отмена</button>
                <button class="btn btn-success" id="btnPost" style="display: none;" onclick="postDocument()">Провести</button>
                <button class="btn btn-primary" onclick="saveDocument()">Сохранить</button>
            </div>
        </div>
    </div>
    
    <script>
        let formData = {};
        let currentItems = [];
        let formInitialized = false;
        
        // Загрузка документов
        function loadDocuments() {
            const status = document.getElementById('filterStatus').value;
            const docType = document.getElementById('filterDocType').value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;
            
            let url = 'api_material_receipt.php?action=list&limit=100';
            if (status) url += '&status=' + encodeURIComponent(status);
            if (docType) url += '&document_type_id=' + encodeURIComponent(docType);
            if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
            if (dateTo) url += '&date_to=' + encodeURIComponent(dateTo);
            
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    const tbody = document.getElementById('documentsTableBody');
                    if (!data.success || !data.data || data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="9" class="loading">Нет данных</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = data.data.map(doc => `
                        <tr>
                            <td><strong>${escapeHtml(doc.document_number)}</strong></td>
                            <td>${formatDate(doc.document_date)}</td>
                            <td>${escapeHtml(doc.document_type_name || '-')}</td>
                            <td>${escapeHtml(doc.supplier_name || '-')}</td>
                            <td>${escapeHtml(doc.supplier_invoice_number || '-')}</td>
                            <td>${escapeHtml(doc.ttn_number || '-')}</td>
                            <td>${formatMoney(doc.total_amount)}</td>
                            <td><span class="status-badge status-${doc.status}">${getStatusName(doc.status)}</span></td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-secondary" onclick="viewDocument(${doc.id})">👁</button>
                                ${doc.status === 'draft' ? `<button class="btn btn-sm btn-primary" onclick="editDocument(${doc.id})">✏</button>` : ''}
                                ${doc.status === 'draft' ? `<button class="btn btn-sm btn-success" onclick="postDocumentById(${doc.id})">✓</button>` : ''}
                                ${doc.status === 'posted' ? `<button class="btn btn-sm btn-danger" onclick="unpostDocument(${doc.id})">↩</button>` : ''}
                            </td>
                        </tr>
                    `).join('');
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('documentsTableBody').innerHTML = 
                        '<tr><td colspan="9" class="loading" style="color: red;">Ошибка загрузки</td></tr>';
                });
        }
        
        // Открытие модального окна нового документа
        function openNewDocumentModal() {
            document.getElementById('docId').value = '';
            document.getElementById('modalTitle').textContent = 'Новый документ поступления';
            document.getElementById('docNumber').value = '';
            document.getElementById('docType').value = '';
            document.getElementById('docDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('supplier').value = '';
            document.getElementById('invoiceNumber').value = '';
            document.getElementById('invoiceDate').value = '';
            document.getElementById('ttnNumber').value = '';
            document.getElementById('docNotes').value = '';
            
            currentItems = [];
            renderItemsTable();
            
            document.getElementById('btnPost').style.display = 'none';
            document.getElementById('documentModal').classList.add('active');
            
            // Загружаем справочники сразу при открытии окна
            loadFormDataIfNeeded();
        }
        
        // Загрузка данных формы если еще не загружены
        function loadFormDataIfNeeded() {
            if (formInitialized) return;
            
            fetch('api_material_receipt.php?action=form_data')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        formData = data.data;
                        formInitialized = true;
                        
                        // Заполняем селекты поставщиков
                        const supplierSelect = document.getElementById('supplier');
                        supplierSelect.disabled = false;
                        if (formData.suppliers && formData.suppliers.length > 0) {
                            supplierSelect.innerHTML = '<option value="">Выберите поставщика</option>' +
                                formData.suppliers.map(s => 
                                    `<option value="${s.id}">${escapeHtml(s.name)}</option>`
                                ).join('');
                        } else {
                            supplierSelect.innerHTML = '<option value="">Нет поставщиков (добавьте в справочнике)</option>';
                        }
                        
                        // Обновляем таблицу материалов если она открыта
                        if (currentItems.length > 0) {
                            renderItemsTable();
                        }
                    } else {
                        console.error('Ошибка получения данных формы:', data.error);
                        const supplierSelect = document.getElementById('supplier');
                        supplierSelect.disabled = false;
                        supplierSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
                    }
                })
                .catch(err => {
                    console.error('Ошибка загрузки данных формы:', err);
                    const supplierSelect = document.getElementById('supplier');
                    supplierSelect.disabled = false;
                    supplierSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
                });
        }
        
        // Добавление строки материала
        function addMaterialRow(material = null) {
            // Проверяем, загружены ли данные формы
            if (!formData.materials || formData.materials.length === 0) {
                // Если данные еще не загружены, пробуем загрузить
                if (!formInitialized) {
                    loadFormDataIfNeeded();
                    // Ждем загрузки и повторяем попытку
                    setTimeout(() => {
                        if (formData.materials && formData.materials.length > 0) {
                            addMaterialRow(material);
                        } else {
                            alert('Справочник материалов пуст или не загружен. Пожалуйста, добавьте материалы в систему.');
                        }
                    }, 1000);
                } else {
                    alert('Справочник материалов пуст. Добавьте хотя бы один материал в систему.');
                }
                return;
            }
            
            currentItems.push({
                material_id: material ? material.id : '',
                material_name: material ? material.name_full : '',
                quantity: 1,
                unit_price: 0,
                total_price: 0,
                batch_number: '',
                certificate_number: ''
            });
            renderItemsTable();
        }
        
        // Отрисовка таблицы материалов
        function renderItemsTable() {
            const tbody = document.getElementById('itemsTableBody');
            
            if (currentItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="loading">Добавьте материалы</td></tr>';
                document.getElementById('itemsTotal').textContent = '0.00';
                return;
            }
            
            // Проверяем, загружены ли данные формы
            if (!formData.materials || formData.materials.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="loading">Загрузка справочника материалов...</td></tr>';
                document.getElementById('itemsTotal').textContent = '0.00';
                return;
            }
            
            tbody.innerHTML = currentItems.map((item, index) => `
                <tr>
                    <td>
                        <select onchange="updateItem(${index}, 'material_id', this.value)" ${item.material_id ? 'disabled' : ''}>
                            <option value="">Выберите материал</option>
                            ${formData.materials.map(m => 
                                `<option value="${m.id}" ${item.material_id == m.id ? 'selected' : ''}>
                                    ${escapeHtml(m.name_full)} (${escapeHtml(m.code)}) - ост. ${m.current_stock} ${m.unit_symbol || 'шт'}
                                </option>`
                            ).join('')}
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.001" value="${item.quantity}" 
                            onchange="updateItem(${index}, 'quantity', this.value)">
                    </td>
                    <td>
                        <input type="number" step="0.01" value="${item.unit_price}" 
                            onchange="updateItem(${index}, 'unit_price', this.value)">
                    </td>
                    <td>${formatMoney(item.total_price)}</td>
                    <td>
                        <input type="text" placeholder="Партия/Сертификат" value="${escapeHtml(item.batch_number || item.certificate_number || '')}"
                            onchange="updateItem(${index}, 'batch_number', this.value)">
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="removeItem(${index})">✕</button>
                    </td>
                </tr>
            `).join('');
            
            // Считаем итог
            const total = currentItems.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
            document.getElementById('itemsTotal').textContent = formatMoney(total);
        }
        
        // Обновление элемента
        function updateItem(index, field, value) {
            const item = currentItems[index];
            
            if (field === 'material_id') {
                const material = formData.materials.find(m => m.id == value);
                if (material) {
                    item.material_id = material.id;
                    item.material_name = material.name_full;
                }
            } else if (field === 'quantity' || field === 'unit_price') {
                item[field] = parseFloat(value) || 0;
                item.total_price = item.quantity * item.unit_price;
            } else {
                item[field] = value;
            }
            
            renderItemsTable();
        }
        
        // Удаление элемента
        function removeItem(index) {
            currentItems.splice(index, 1);
            renderItemsTable();
        }
        
        // Сохранение документа
        function saveDocument() {
            const docId = document.getElementById('docId').value;
            
            if (!document.getElementById('docType').value) {
                alert('Выберите тип документа');
                return;
            }
            if (!document.getElementById('docDate').value) {
                alert('Укажите дату документа');
                return;
            }
            if (currentItems.length === 0) {
                alert('Добавьте хотя бы один материал');
                return;
            }
            
            const payload = {
                action: docId ? 'save' : 'create',
                id: docId || null,
                document_number: document.getElementById('docNumber').value || null,
                document_type_id: parseInt(document.getElementById('docType').value),
                document_date: document.getElementById('docDate').value,
                supplier_id: parseInt(document.getElementById('supplier').value) || null,
                supplier_invoice_number: document.getElementById('invoiceNumber').value || null,
                supplier_invoice_date: document.getElementById('invoiceDate').value || null,
                ttn_number: document.getElementById('ttnNumber').value || null,
                notes: document.getElementById('docNotes').value || null,
                items: currentItems.map(item => ({
                    material_id: parseInt(item.material_id),
                    quantity: parseFloat(item.quantity),
                    unit_price: parseFloat(item.unit_price),
                    total_price: parseFloat(item.total_price),
                    batch_number: item.batch_number || null,
                    certificate_number: item.certificate_number || null
                }))
            };
            
            fetch('api_material_receipt.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('saveMessage').textContent = data.message;
                    setTimeout(() => {
                        closeDocumentModal();
                        loadDocuments();
                    }, 1000);
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(err => {
                alert('Ошибка сохранения: ' + err);
            });
        }
        
        // Проведение документа
        function postDocument() {
            const docId = document.getElementById('docId').value;
            if (!docId) return;
            
            if (!confirm('Провести документ? Материалы будут оприходованы на склад.')) {
                return;
            }
            
            fetch('api_material_receipt.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'post', id: parseInt(docId) })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeDocumentModal();
                    loadDocuments();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            });
        }
        
        // Проведение документа по ID из таблицы
        function postDocumentById(id) {
            if (!confirm('Провести документ? Материалы будут оприходованы на склад.')) {
                return;
            }
            
            fetch('api_material_receipt.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'post', id: id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadDocuments();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            });
        }
        
        // Отмена проведения
        function unpostDocument(id) {
            if (!confirm('Отменить проведение документа? Остатки материалов будут уменьшены.')) {
                return;
            }
            
            fetch('api_material_receipt.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'unpost', id: id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadDocuments();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            });
        }
        
        // Просмотр документа
        function viewDocument(id) {
            fetch('api_material_receipt.php?action=get&id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    
                    const doc = data.data;
                    document.getElementById('docId').value = doc.id;
                    document.getElementById('modalTitle').textContent = 'Документ ' + doc.document_number;
                    document.getElementById('docNumber').value = doc.document_number;
                    document.getElementById('docType').value = doc.document_type_id || '';
                    document.getElementById('docDate').value = doc.document_date;
                    document.getElementById('supplier').value = doc.supplier_id || '';
                    document.getElementById('invoiceNumber').value = doc.supplier_invoice_number || '';
                    document.getElementById('invoiceDate').value = doc.supplier_invoice_date || '';
                    document.getElementById('ttnNumber').value = doc.ttn_number || '';
                    document.getElementById('docNotes').value = doc.notes || '';
                    
                    currentItems = doc.items || [];
                    
                    // Показываем кнопку проведения если черновик
                    document.getElementById('btnPost').style.display = doc.status === 'draft' ? 'inline-block' : 'none';
                    
                    document.getElementById('documentModal').classList.add('active');
                    
                    // Загружаем данные формы если еще не загружены
                    loadFormDataIfNeeded();
                    
                    // Отрисовываем таблицу после загрузки данных формы
                    setTimeout(() => renderItemsTable(), 100);
                });
        }
        
        // Редактирование документа
        function editDocument(id) {
            viewDocument(id);
        }
        
        // Закрытие модального окна
        function closeDocumentModal() {
            document.getElementById('documentModal').classList.remove('active');
            document.getElementById('saveMessage').textContent = '';
        }
        
        // Сброс фильтров
        function resetFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDocType').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            loadDocuments();
        }
        
        // Вспомогательные функции
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('ru-RU');
        }
        
        function formatMoney(amount) {
            if (!amount) return '0.00';
            return parseFloat(amount).toFixed(2) + ' BYN';
        }
        
        function getStatusName(status) {
            const names = {
                'draft': 'Черновик',
                'posted': 'Проведен',
                'cancelled': 'Отменен'
            };
            return names[status] || status;
        }
        
        // Закрытие по ESC и обработка кликов по модальному окну (после загрузки DOM)
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDocumentModal();
                }
            });
            
            const modalOverlay = document.getElementById('documentModal');
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeDocumentModal();
                    }
                });
            }
            
            const modalContent = document.querySelector('#documentModal .modal');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
        
        // Инициализация
        loadDocuments();
    </script>
</body>
</html>
