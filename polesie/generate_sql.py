import json

# Чтение JSON файла с продукцией
with open('/workspace/polesie/production.json', 'r', encoding='utf-8') as f:
    d = json.load(f)

cats = d.get('categories', [])

# Собираем все продукты и категории
all_products = []
product_categories = []
parent_categories = set()

for cat in cats:
    parent_cat_id = cat.get('id')
    parent_cat_name = cat.get('name_ru')
    parent_cat_code = cat.get('code')
    parent_categories.add((parent_cat_id, parent_cat_name, parent_cat_code))
    
    for subcat in cat.get('subcategories', []):
        subcat_id = subcat.get('id')
        subcat_name = subcat.get('name_ru')
        subcat_code = subcat.get('code')
        product_categories.append({
            'id': subcat_id,
            'parent_id': parent_cat_id,
            'name': subcat_name,
            'code': subcat_code
        })
        
        for prod in subcat.get('products', []):
            specs = prod.get('specs', {})
            
            # Преобразуем массивы в строки через запятую
            def array_to_str(val):
                if isinstance(val, list):
                    return ','.join(map(str, val))
                return val
            
            all_products.append({
                'id': prod.get('id'),
                'sku': prod.get('sku'),
                'code_gost': prod.get('code_gost', ''),
                'name_full': prod.get('name_full'),
                'name_short': prod.get('name_short'),
                'category_id': subcat_id,
                # Основные характеристики
                'power_kw_min': specs.get('power_kw_min'),
                'power_kw_max': specs.get('power_kw_max'),
                'power_kw': specs.get('power_kw'),
                'rpm': specs.get('rpm'),
                'shaft_height_mm': specs.get('shaft_height_mm'),
                'voltage_v': specs.get('voltage_v'),
                'frequency_hz': specs.get('frequency_hz'),
                'efficiency_class': specs.get('efficiency_class'),
                'climate_versions': array_to_str(specs.get('climate_versions')),
                'mounting_versions': array_to_str(specs.get('mounting_versions')),
                'protection_class': array_to_str(specs.get('protection_class')),
                'type': specs.get('type'),
                'application': specs.get('application'),
                'housing_material': specs.get('housing_material'),
                'impeller_material': specs.get('impeller_material'),
                'flow_rate_m3_h': specs.get('flow_rate_m3_h'),
                'head_m': specs.get('head_m'),
                'max_immersion_depth_m': specs.get('max_immersion_depth_m'),
                'max_solid_size_mm': specs.get('max_solid_size_mm'),
                'explosion_protection': specs.get('explosion_protection'),
                'capacitor_included': specs.get('capacitor_included'),
                'standard': specs.get('standard'),
                'material': specs.get('material'),
                'production_method': specs.get('production_method'),
                'custom_made': specs.get('custom_made'),
                'speeds': specs.get('speeds'),
                'slip_percent': specs.get('slip_percent'),
                'weight_range_kg': specs.get('weight_range_kg'),
                'is_serial_tracked': prod.get('is_serial_tracked', False),
                'warranty_months': prod.get('warranty_months', 24),
                'is_bestseller': prod.get('is_bestseller', False),
                'serial_number': prod.get('serial_number', '')
            })

# Генерируем SQL
sql_lines = []
sql_lines.append("-- ============================================")
sql_lines.append("-- ПОЛЕСЬЕ ПРОДАКШН: ВСЯ ПРОДУКЦИЯ (64 товара)")
sql_lines.append("-- Все свойства вынесены в отдельные колонки")
sql_lines.append("-- ============================================")
sql_lines.append("")
sql_lines.append("USE `polesie_production`;")
sql_lines.append("")

# Сначала добавим родительские категории (если их нет)
sql_lines.append("-- Родительские категории продукции")
sql_lines.append("INSERT INTO `product_categories` (`id`, `parent_id`, `name`, `code`, `description`) VALUES")
parent_cat_sql = []
for pc_id, pc_name, pc_code in sorted(parent_categories, key=lambda x: x[0]):
    desc = f"Категория: {pc_name}"
    parent_cat_sql.append(f"({pc_id}, NULL, '{pc_name}', '{pc_code}', '{desc}')")
sql_lines.append(",\n".join(parent_cat_sql) + ";")
sql_lines.append("")

# Добавим подкатегории
sql_lines.append("-- Подкатегории продукции")
sql_lines.append("INSERT INTO `product_categories` (`id`, `parent_id`, `name`, `code`, `description`) VALUES")
subcat_sql = []
for sc in product_categories:
    desc = f"Подкатегория: {sc['name']}"
    subcat_sql.append(f"({sc['id']}, {sc['parent_id']}, '{sc['name']}', '{sc['code']}', '{desc}')")
sql_lines.append(",\n".join(subcat_sql) + ";")
sql_lines.append("")

# Теперь создадим новую таблицу products со всеми колонками
sql_lines.append("-- ============================================")
sql_lines.append("-- Обновленная таблица products с отдельными колонками")
sql_lines.append("-- ============================================")
sql_lines.append("")
sql_lines.append("DROP TABLE IF EXISTS `products`;")
sql_lines.append("")
sql_lines.append("CREATE TABLE `products` (")
sql_lines.append("  `id` INT AUTO_INCREMENT PRIMARY KEY,")
sql_lines.append("  `article` VARCHAR(50) NOT NULL UNIQUE,")
sql_lines.append("  `code_gost` VARCHAR(50),")
sql_lines.append("  `name_full` VARCHAR(300) NOT NULL,")
sql_lines.append("  `name_short` VARCHAR(150),")
sql_lines.append("  `category_id` INT,")
sql_lines.append("  `base_unit_id` INT DEFAULT 1,")
sql_lines.append("  -- Электрические характеристики")
sql_lines.append("  `power_kw_min` DECIMAL(10,3),")
sql_lines.append("  `power_kw_max` DECIMAL(10,3),")
sql_lines.append("  `power_kw` DECIMAL(10,3),")
sql_lines.append("  `rpm` INT,")
sql_lines.append("  `voltage_v` VARCHAR(50),")
sql_lines.append("  `frequency_hz` INT DEFAULT 50,")
sql_lines.append("  `efficiency_class` VARCHAR(10),")
sql_lines.append("  -- Механические характеристики")
sql_lines.append("  `shaft_height_mm` INT,")
sql_lines.append("  `climate_versions` VARCHAR(200),")
sql_lines.append("  `mounting_versions` VARCHAR(300),")
sql_lines.append("  `protection_class` VARCHAR(50),")
sql_lines.append("  -- Дополнительные характеристики")
sql_lines.append("  `type` VARCHAR(100),")
sql_lines.append("  `application` VARCHAR(200),")
sql_lines.append("  `housing_material` VARCHAR(100),")
sql_lines.append("  `impeller_material` VARCHAR(100),")
sql_lines.append("  `flow_rate_m3_h` DECIMAL(10,2),")
sql_lines.append("  `head_m` DECIMAL(10,2),")
sql_lines.append("  `max_immersion_depth_m` DECIMAL(10,2),")
sql_lines.append("  `max_solid_size_mm` INT,")
sql_lines.append("  `explosion_protection` VARCHAR(50),")
sql_lines.append("  `capacitor_included` BOOLEAN,")
sql_lines.append("  `standard` VARCHAR(100),")
sql_lines.append("  `material` VARCHAR(100),")
sql_lines.append("  `production_method` VARCHAR(100),")
sql_lines.append("  `custom_made` BOOLEAN,")
sql_lines.append("  `speeds` INT,")
sql_lines.append("  `slip_percent` DECIMAL(5,2),")
sql_lines.append("  `weight_range_kg` VARCHAR(50),")
sql_lines.append("  -- Серийные номера и гарантия")
sql_lines.append("  `is_serial_tracked` BOOLEAN DEFAULT FALSE,")
sql_lines.append("  `warranty_months` INT DEFAULT 24,")
sql_lines.append("  `is_bestseller` BOOLEAN DEFAULT FALSE,")
sql_lines.append("  `serial_number` VARCHAR(100),")
sql_lines.append("  -- Изображение и цена")
sql_lines.append("  `image` VARCHAR(255),")
sql_lines.append("  `base_price` DECIMAL(15,2),")
sql_lines.append("  `currency` CHAR(3) DEFAULT 'BYN',")
sql_lines.append("  `is_active` BOOLEAN DEFAULT TRUE,")
sql_lines.append("  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,")
sql_lines.append("  CONSTRAINT `fk_prod_category_new` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,")
sql_lines.append("  CONSTRAINT `fk_prod_unit_new` FOREIGN KEY (`base_unit_id`) REFERENCES `base_units`(`id`) ON DELETE SET NULL")
sql_lines.append(") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")
sql_lines.append("")

# Вставка всех продуктов
sql_lines.append("-- ============================================")
sql_lines.append("-- Вставка всех 64 продуктов")
sql_lines.append("-- ============================================")
sql_lines.append("")
sql_lines.append("INSERT INTO `products` (")
sql_lines.append("  `id`, `article`, `code_gost`, `name_full`, `name_short`, `category_id`, `base_unit_id`,")
sql_lines.append("  `power_kw_min`, `power_kw_max`, `power_kw`, `rpm`, `voltage_v`, `frequency_hz`,")
sql_lines.append("  `efficiency_class`, `shaft_height_mm`, `climate_versions`, `mounting_versions`,")
sql_lines.append("  `protection_class`, `type`, `application`, `housing_material`, `impeller_material`,")
sql_lines.append("  `flow_rate_m3_h`, `head_m`, `max_immersion_depth_m`, `max_solid_size_mm`,")
sql_lines.append("  `explosion_protection`, `capacitor_included`, `standard`, `material`,")
sql_lines.append("  `production_method`, `custom_made`, `speeds`, `slip_percent`, `weight_range_kg`,")
sql_lines.append("  `is_serial_tracked`, `warranty_months`, `is_bestseller`, `serial_number`,")
sql_lines.append("  `image`, `base_price`, `currency`, `is_active`)")
sql_lines.append("VALUES")

def escape_str(s):
    if s is None:
        return "NULL"
    return "'" + str(s).replace("'", "''") + "'"

def escape_bool(b):
    if b is None:
        return "FALSE"
    return "TRUE" if b else "FALSE"

def escape_decimal(d):
    if d is None:
        return "NULL"
    return str(d)

product_sql = []
for p in all_products:
    # Генерация имени изображения
    img_name = f"product_{p['sku'].lower().replace('-', '_')}.jpg"
    # Базовая цена (примерная, на основе мощности)
    power = p['power_kw'] or p['power_kw_min'] or 1.0
    base_price = round(power * 300, 2)
    
    values = [
        str(p['id']),
        f"'{p['sku']}'",
        escape_str(p['code_gost']),
        escape_str(p['name_full']),
        escape_str(p['name_short']),
        str(p['category_id']),
        "1",  # base_unit_id = штука
        escape_decimal(p['power_kw_min']),
        escape_decimal(p['power_kw_max']),
        escape_decimal(p['power_kw']),
        str(p['rpm']) if p['rpm'] else "NULL",
        escape_str(p['voltage_v']),
        str(p['frequency_hz']) if p['frequency_hz'] else "NULL",
        escape_str(p['efficiency_class']),
        str(p['shaft_height_mm']) if p['shaft_height_mm'] else "NULL",
        escape_str(p['climate_versions']),
        escape_str(p['mounting_versions']),
        escape_str(p['protection_class']),
        escape_str(p['type']),
        escape_str(p['application']),
        escape_str(p['housing_material']),
        escape_str(p['impeller_material']),
        escape_decimal(p['flow_rate_m3_h']),
        escape_decimal(p['head_m']),
        escape_decimal(p['max_immersion_depth_m']),
        str(p['max_solid_size_mm']) if p['max_solid_size_mm'] else "NULL",
        escape_str(p['explosion_protection']),
        escape_bool(p['capacitor_included']),
        escape_str(p['standard']),
        escape_str(p['material']),
        escape_str(p['production_method']),
        escape_bool(p['custom_made']),
        str(p['speeds']) if p['speeds'] else "NULL",
        escape_decimal(p['slip_percent']),
        escape_str(p['weight_range_kg']),
        escape_bool(p['is_serial_tracked']),
        str(p['warranty_months']),
        escape_bool(p['is_bestseller']),
        escape_str(p['serial_number']),
        f"'{img_name}'",
        str(base_price),
        "'BYN'",
        "TRUE"
    ]
    product_sql.append("(" + ", ".join(values) + ")")

sql_lines.append(",\n".join(product_sql) + ";")
sql_lines.append("")
sql_lines.append("-- ============================================")
sql_lines.append(f"-- Всего продуктов: {len(all_products)}")
sql_lines.append("-- ============================================")

# Запись SQL файла
with open('/workspace/polesie/sql/all_products.sql', 'w', encoding='utf-8') as f:
    f.write("\n".join(sql_lines))

print(f"SQL файл создан: /workspace/polesie/sql/all_products.sql")
print(f"Всего продуктов: {len(all_products)}")
print(f"Всего категорий: {len(parent_categories)}")
print(f"Всего подкатегорий: {len(product_categories)}")
