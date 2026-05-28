# Импорт данных из passports.json в базу данных

## Обзор

Этот документ описывает процесс импорта данных о паспортах продуктов из JSON файла в базу данных MySQL.

## Файлы

- `/workspace/polesie/passports.json` - исходный файл с данными паспортов (72 продукта)
- `/workspace/polesie/generate_passports_sql.py` - Python скрипт для генерации SQL
- `/workspace/polesie/sql/import_passports.sql` - сгенерированный SQL файл для импорта

## Структура данных в БД

### Таблицы

1. **product_categories** - Категории продуктов
   - Добавляется категория `MOTORS_3PHASE` (Электродвигатели асинхронные трехфазные)

2. **materials** - Материалы
   - Добавляются 20 новых материалов из паспортов
   - Категория: `PRODUCT_COMPONENTS` (Комплектующие для продукции)

3. **products** - Продукты
   - Добавляются 72 новых продукта из passports.json
   - Поле `specifications` содержит JSON с характеристиками

4. **product_passports** - Паспорта продуктов
   - Содержит основную информацию: вес, гарантия, серийный трекинг
   - `production_notes` - JSON массив заметок по производству
   - `quality_requirements` - JSON массив требований к качеству

5. **product_passport_materials** - Материалы паспортов
   - Связь паспорт-материал с количеством и единицами измерения

## Как использовать

### Вариант 1: Автоматическая генерация SQL

```bash
cd /workspace/polesie
python3 generate_passports_sql.py
```

### Вариант 2: Использование готового SQL файла

```bash
mysql -u root -p polesie_production < /workspace/polesie/sql/import_passports.sql
```

## Что делает скрипт

1. **Материалы**: 
   - Создает временную таблицу для маппинга material_code → material_id
   - Добавляет 20 новых материалов которых нет в базе
   - Обновляет маппинг

2. **Продукты**:
   - Создает временную таблицу для маппинга SKU → product_id
   - Добавляет категорию MOTORS_3PHASE если нет
   - Добавляет 72 новых продукта из passports.json
   - Обновляет маппинг

3. **Паспорта**:
   - Удаляет старые паспорта (для чистоты)
   - Вставляет 72 новых паспорта с:
     - total_weight_kg
     - warranty_months
     - is_serial_tracked
     - production_notes (JSON)
     - quality_requirements (JSON)

4. **Материалы паспортов**:
   - Удаляет старые записи
   - Вставляет 441 запись (материалы для каждого паспорта)

5. **Specifications**:
   - Обновляет поле specifications в products для каждого продукта

## Проверка после импорта

```sql
-- Проверить количество паспортов
SELECT COUNT(*) FROM product_passports;

-- Проверить материалы паспортов
SELECT pp.product_id, COUNT(ppm.id) as material_count
FROM product_passports pp
JOIN product_passport_materials ppm ON ppm.passport_id = pp.id
GROUP BY pp.product_id
LIMIT 10;

-- Посмотреть паспорт конкретного продукта
SELECT p.article, p.name, pp.total_weight_kg, pp.warranty_months
FROM product_passports pp
JOIN products p ON pp.product_id = p.id
WHERE p.article = 'AIR-071-2';

-- Посмотреть материалы паспорта
SELECT m.code, m.name_full, ppm.quantity, ppm.unit
FROM product_passport_materials ppm
JOIN materials m ON ppm.material_id = m.id
JOIN product_passports pp ON ppm.passport_id = pp.id
JOIN products p ON pp.product_id = p.id
WHERE p.article = 'AIR-071-2';
```

## Примечания

- Скрипт использует временные таблицы для эффективного маппинга
- Все JSON поля сохраняются в нативном формате MySQL JSON
- При повторном запуске старые данные удаляются для избежания дубликатов
- Материалы добавляются только если их нет в базе (по code)
