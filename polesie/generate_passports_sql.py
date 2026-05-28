#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Генерация SQL для импорта паспортов продуктов из passports.json в базу данных
"""

import json

# Чтение JSON файла с паспортами
with open('/workspace/polesie/passports.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

company = data.get('company', {})
passports = data.get('passports', [])

print(f"Всего паспортов: {len(passports)}")

sql_lines = []
sql_lines.append("-- ============================================")
sql_lines.append("-- ПОЛЕСЬЕ ПРОДАКШН: ПАСПОРТА ПРОДУКТОВ")
sql_lines.append("-- Импорт из passports.json")
sql_lines.append(f"-- Всего продуктов: {len(passports)}")
sql_lines.append("-- ============================================")
sql_lines.append("")
sql_lines.append("USE `polesie_production`;")
sql_lines.append("")
sql_lines.append("SET NAMES utf8mb4;")
sql_lines.append("SET FOREIGN_KEY_CHECKS = 0;")
sql_lines.append("")

# Сначала найдем все уникальные material_code из паспортов
all_material_codes = set()
for passport in passports:
    materials = passport.get('materials', {})
    for mat_key, mat_data in materials.items():
        all_material_codes.add(mat_data.get('material_code'))

print(f"Уникальных кодов материалов: {len(all_material_codes)}")

# Создадим временную таблицу для маппинга material_code -> material_id
sql_lines.append("-- Временная таблица для маппинга материалов по code")
sql_lines.append("DROP TABLE IF EXISTS `temp_material_map`;")
sql_lines.append("CREATE TEMPORARY TABLE `temp_material_map` (")
sql_lines.append("  `material_code` VARCHAR(50) NOT NULL,")
sql_lines.append("  `material_id` INT")
sql_lines.append(") ENGINE=Memory;")
sql_lines.append("")

# Заполним временную таблицу существующими материалами
sql_lines.append("-- Заполняем временную таблицу существующими материалами")
sql_lines.append("INSERT INTO `temp_material_map` (`material_code`, `material_id`)")
sql_lines.append("SELECT `code`, `id` FROM `materials`;")
sql_lines.append("")

# Для материалов которых нет в базе - добавим их
sql_lines.append("-- ============================================")
sql_lines.append("-- ДОБАВЛЕНИЕ НОВЫХ МАТЕРИАЛОВ ИЗ ПАСПОРТОВ")
sql_lines.append("-- ============================================")
sql_lines.append("")

# Собираем все уникальные материалы из паспортов
materials_to_add = {}
for passport in passports:
    materials = passport.get('materials', {})
    for mat_key, mat_data in materials.items():
        code = mat_data.get('material_code')
        if code and code not in materials_to_add:
            materials_to_add[code] = {
                'code': code,
                'name_full': mat_data.get('name', ''),
                'name_short': mat_data.get('name', '')[:50],
                'category_name': mat_data.get('category', '')
            }

print(f"Материалов для добавления: {len(materials_to_add)}")

# Получим ID категории для материалов или создадим новую
sql_lines.append("-- Добавляем категорию 'Комплектующие для продукции' если нет")
sql_lines.append("INSERT INTO `material_categories` (`name`, `code`, `description`)")
sql_lines.append("SELECT 'Комплектующие для продукции', 'PRODUCT_COMPONENTS', 'Материалы из паспортов продуктов'")
sql_lines.append("WHERE NOT EXISTS (SELECT 1 FROM `material_categories` WHERE `code` = 'PRODUCT_COMPONENTS');")
sql_lines.append("")

# Получим ID base_unit (штука = 1, кг = 2)
sql_lines.append("-- Получаем ID единиц измерения")
sql_lines.append("SET @unit_pcs = (SELECT id FROM base_units WHERE code = 'pcs' LIMIT 1);")
sql_lines.append("SET @unit_kg = (SELECT id FROM base_units WHERE code = 'kg' LIMIT 1);")
sql_lines.append("SET @unit_l = (SELECT id FROM base_units WHERE code = 'l' LIMIT 1);")
sql_lines.append("SET @mat_cat_components = (SELECT id FROM material_categories WHERE code = 'PRODUCT_COMPONENTS' LIMIT 1);")
sql_lines.append("")

# Добавляем новые материалы
if materials_to_add:
    sql_lines.append("-- Вставка новых материалов из паспортов")
    sql_lines.append("INSERT INTO `materials` (`code`, `name_full`, `name_short`, `category_id`, `base_unit_id`, `specifications`) VALUES")
    
    mat_values = []
    for code, mat in materials_to_add.items():
        # Определяем единицу измерения по названию
        unit_id = "@unit_pcs"
        if 'кг' in mat['name_full'] or 'чугун' in mat['name_full'].lower() or 'сталь' in mat['name_full'].lower() or 'медь' in mat['name_full'].lower():
            unit_id = "@unit_kg"
        elif 'л' in mat['name_full'] or 'лак' in mat['name_full'].lower():
            unit_id = "@unit_l"
        
        name_full = mat['name_full'].replace("'", "''")
        name_short = mat['name_short'][:50].replace("'", "''")
        
        mat_values.append(f"('{code}', '{name_full}', '{name_short}', @mat_cat_components, {unit_id}, '{{\"source\": \"passport\"}}')")
    
    sql_lines.append(",\n".join(mat_values) + ";")
    sql_lines.append("")

# Теперь обновим временную таблицу с новыми материалами
sql_lines.append("-- Обновляем временную таблицу после добавления новых материалов")
sql_lines.append("INSERT INTO `temp_material_map` (`material_code`, `material_id`)")
sql_lines.append("SELECT `code`, `id` FROM `materials` WHERE `code` NOT IN (SELECT `material_code` FROM `temp_material_map`);")
sql_lines.append("")

# ============================================
# ГЕНЕРАЦИЯ PASSPORTS ДЛЯ ПРОДУКТОВ
# ============================================
sql_lines.append("-- ============================================")
sql_lines.append("-- ДОБАВЛЕНИЕ ПАСПОРТОВ ПРОДУКТОВ")
sql_lines.append("-- ============================================")
sql_lines.append("")

# Сначала найдем products по article (SKU)
sql_lines.append("-- Временная таблица для маппинга SKU -> product_id")
sql_lines.append("DROP TABLE IF EXISTS `temp_product_map`;")
sql_lines.append("CREATE TEMPORARY TABLE `temp_product_map` (")
sql_lines.append("  `sku` VARCHAR(50) NOT NULL,")
sql_lines.append("  `product_id` INT")
sql_lines.append(") ENGINE=Memory;")
sql_lines.append("")

sql_lines.append("-- Заполняем временную таблицу существующими продуктами")
sql_lines.append("INSERT INTO `temp_product_map` (`sku`, `product_id`)")
sql_lines.append("SELECT `article`, `id` FROM `products`;")
sql_lines.append("")

# Собираем продукты которых нет в базе
products_to_add = {}
for passport in passports:
    sku = passport.get('sku')
    if sku:
        basic_info = passport.get('basic_info', {})
        if sku not in products_to_add:
            products_to_add[sku] = {
                'sku': sku,
                'name_full': basic_info.get('name_full', ''),
                'name_short': basic_info.get('name_short', ''),
                'code_gost': basic_info.get('code_gost', ''),
                'category': basic_info.get('category', ''),
                'category_code': basic_info.get('category_code', '')
            }

print(f"Продуктов в passports.json: {len(products_to_add)}")

# Добавим категории продуктов если нет
sql_lines.append("-- Добавляем категории продуктов если нет")
sql_lines.append("INSERT INTO `product_categories` (`name`, `code`, `description`)")
sql_lines.append("SELECT 'Электродвигатели асинхронные трехфазные', 'MOTORS_3PHASE', 'Трехфазные асинхронные двигатели'")
sql_lines.append("WHERE NOT EXISTS (SELECT 1 FROM `product_categories` WHERE `code` = 'MOTORS_3PHASE';")
sql_lines.append("")

# Добавляем недостающие продукты
if products_to_add:
    sql_lines.append("-- Вставка новых продуктов из паспортов")
    sql_lines.append("INSERT INTO `products` (`article`, `name`, `code_gost`, `category_id`, `base_unit_id`, `specifications`, `is_active`) VALUES")
    
    prod_values = []
    for sku, prod in products_to_add.items():
        name = prod['name_full'].replace("'", "''")
        code_gost = prod['code_gost'].replace("'", "''")
        
        # Формируем specifications JSON
        specs = {
            'source': 'passport_json',
            'category': prod['category'],
            'category_code': prod['category_code']
        }
        specs_json = json.dumps(specs, ensure_ascii=False).replace("'", "''")
        
        prod_values.append(f"('{sku}', '{name}', '{code_gost}', (SELECT id FROM product_categories WHERE code = 'MOTORS_3PHASE' LIMIT 1), 1, '{specs_json}', TRUE)")
    
    sql_lines.append(",\n".join(prod_values) + ";")
    sql_lines.append("")

# Обновляем временную таблицу
sql_lines.append("-- Обновляем временную таблицу после добавления новых продуктов")
sql_lines.append("INSERT INTO `temp_product_map` (`sku`, `product_id`)")
sql_lines.append("SELECT `article`, `id` FROM `products` WHERE `article` NOT IN (SELECT `sku` FROM `temp_product_map`);")
sql_lines.append("")

# ============================================
# ГЕНЕРАЦИЯ ЗАПИСЕЙ product_passports
# ============================================
sql_lines.append("-- ============================================")
sql_lines.append("-- ЗАПИСИ В product_passports")
sql_lines.append("-- ============================================")
sql_lines.append("")

sql_lines.append("-- Удаляем старые паспорта если есть (для чистоты)")
sql_lines.append("DELETE FROM `product_passports`;")
sql_lines.append("")

sql_lines.append("-- Вставка паспортов продуктов")
sql_lines.append("INSERT INTO `product_passports` (`product_id`, `total_weight_kg`, `warranty_months`, `is_serial_tracked`, `production_notes`, `quality_requirements`) VALUES")

passport_values = []
for passport in passports:
    sku = passport.get('sku')
    total_weight = passport.get('total_weight_kg', 0)
    warranty_months = passport.get('warranty_months', 24)
    is_serial_tracked = passport.get('is_serial_tracked', False)
    production_notes = passport.get('production_notes', [])
    quality_requirements = passport.get('quality_requirements', [])
    
    # Преобразуем массивы в JSON
    notes_json = json.dumps(production_notes, ensure_ascii=False).replace("'", "''")
    req_json = json.dumps(quality_requirements, ensure_ascii=False).replace("'", "''")
    
    is_serial = "TRUE" if is_serial_tracked else "FALSE"
    
    passport_values.append(f"((SELECT `product_id` FROM `temp_product_map` WHERE `sku` = '{sku}'), {total_weight}, {warranty_months}, {is_serial}, '{notes_json}', '{req_json}')")

sql_lines.append(",\n".join(passport_values) + ";")
sql_lines.append("")

# ============================================
# ГЕНЕРАЦИЯ product_passport_materials
# ============================================
sql_lines.append("-- ============================================")
sql_lines.append("-- МАТЕРИАЛЫ ПАСПОРТОВ (product_passport_materials)")
sql_lines.append("-- ============================================")
sql_lines.append("")

sql_lines.append("-- Удаляем старые материалы паспортов")
sql_lines.append("DELETE FROM `product_passport_materials`;")
sql_lines.append("")

sql_lines.append("-- Вставка материалов паспортов")
sql_lines.append("INSERT INTO `product_passport_materials` (`passport_id`, `material_id`, `quantity`, `unit`, `sort_order`, `notes`) VALUES")

mat_passport_values = []
sort_order = 0
for passport in passports:
    sku = passport.get('sku')
    materials = passport.get('materials', {})
    
    for mat_key, mat_data in materials.items():
        material_code = mat_data.get('material_code')
        quantity = mat_data.get('quantity', 0)
        unit = mat_data.get('unit', 'шт')
        mat_name = mat_data.get('name', '').replace("'", "''")
        
        sort_order += 1
        
        mat_passport_values.append(
            f"((SELECT pp.id FROM product_passports pp JOIN temp_product_map tpm ON pp.product_id = tpm.product_id WHERE tpm.sku = '{sku}'), "
            f"(SELECT material_id FROM temp_material_map WHERE material_code = '{material_code}'), "
            f"{quantity}, '{unit}', {sort_order}, '{mat_name}')"
        )
    
    sort_order = 0  # Сброс для каждого продукта

if mat_passport_values:
    sql_lines.append(",\n".join(mat_passport_values) + ";")
    sql_lines.append("")

# ============================================
# ОБНОВЛЕНИЕ specifications В products
# ============================================
sql_lines.append("-- ============================================")
sql_lines.append("-- ОБНОВЛЕНИЕ specifications В products")
sql_lines.append("-- ============================================")
sql_lines.append("")

for passport in passports:
    sku = passport.get('sku')
    specs = passport.get('specifications', {})
    
    if specs:
        specs_json = json.dumps(specs, ensure_ascii=False).replace("'", "''")
        sql_lines.append(f"-- Обновление {sku}")
        sql_lines.append(f"UPDATE `products` SET `specifications` = '{specs_json}' WHERE `article` = '{sku}';")
        sql_lines.append("")

# Завершение
sql_lines.append("-- ============================================")
sql_lines.append("-- ЗАВЕРШЕНИЕ")
sql_lines.append("-- ============================================")
sql_lines.append("")
sql_lines.append("SET FOREIGN_KEY_CHECKS = 1;")
sql_lines.append("")
sql_lines.append("-- Очистка временных таблиц")
sql_lines.append("DROP TABLE IF EXISTS `temp_material_map`;")
sql_lines.append("DROP TABLE IF EXISTS `temp_product_map`;")
sql_lines.append("")
sql_lines.append("-- Готово!")
sql_lines.append(f"-- Импортировано {len(passports)} паспортов продуктов")

# Запись SQL файла
output_path = '/workspace/polesie/sql/import_passports.sql'
with open(output_path, 'w', encoding='utf-8') as f:
    f.write("\n".join(sql_lines))

print(f"\nSQL файл создан: {output_path}")
print(f"Размер файла: {len('\n'.join(sql_lines))} байт")
print(f"Всего паспортов: {len(passports)}")
print(f"Всего материалов паспортов: {len(mat_passport_values)}")
