#!/usr/bin/env python3
"""
Генератор SQL-файла для импорта полной номенклатуры продукции ОАО «Полесьеэлектромаш»
На основе polesie_products.php генерирует database_updated.sql
"""
import re
import json

with open('/workspace/polesie_products.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Извлекаем все модели по ключевым словам
def extract_models():
    # Находим все строки с 'model' =>
    model_lines = []
    lines = content.split('\n')
    
    models_by_category = {
        'asynchronous_three_phase_general': [],
        'energy_efficient': [],
        'multispeed': [],
        'high_slip': [],
        'for_pumps': [],
        'for_gearboxes': [],
        'for_poultry': [],
        'for_railway': [],
        'explosion_proof': [],
        'single_phase': [],
        'centrifugal_household': [],
        'submersible_dirty': [],
        'other_items': [],
    }
    
    current_cat = None
    in_subcategory = False
    
    for i, line in enumerate(lines):
        # Определяем основную категорию
        if "'asynchronous_three_phase_general'" in line:
            current_cat = 'asynchronous_three_phase_general'
            in_subcategory = False
        elif "'energy_efficient'" in line:
            current_cat = 'energy_efficient'
            in_subcategory = False
        elif "'multispeed'" in line:
            current_cat = 'multispeed'
            in_subcategory = False
        elif "'single_phase'" in line:
            current_cat = 'single_phase'
            in_subcategory = False
        elif "'pumps'" in line and "=>" in line:
            current_cat = 'pumps'
            in_subcategory = False
        elif "'other_products'" in line:
            current_cat = 'other_products'
            in_subcategory = False
        
        # Определяем подкатегории в special_purpose
        if "'high_slip'" in line and "=>" in line:
            current_cat = 'high_slip'
            in_subcategory = True
        elif "'for_pumps'" in line and "=>" in line:
            current_cat = 'for_pumps'
            in_subcategory = True
        elif "'for_gearboxes'" in line and "=>" in line:
            current_cat = 'for_gearboxes'
            in_subcategory = True
        elif "'for_poultry'" in line and "=>" in line:
            current_cat = 'for_poultry'
            in_subcategory = True
        elif "'for_railway'" in line and "=>" in line:
            current_cat = 'for_railway'
            in_subcategory = True
        elif "'explosion_proof'" in line and "=>" in line:
            current_cat = 'explosion_proof'
            in_subcategory = True
        
        # Подкатегории pumps
        if "'centrifugal_household'" in line and "=>" in line:
            current_cat = 'centrifugal_household'
            in_subcategory = True
        elif "'submersible_dirty'" in line and "=>" in line:
            current_cat = 'submersible_dirty'
            in_subcategory = True
        
        if current_cat and current_cat not in ['other_products', 'pumps']:
            # Ищем модель
            model_match = re.search(r"'model'\s*=>\s*'([^']+)'", line)
            if model_match:
                model_name = model_match.group(1)
                # Собираем параметры из следующих строк
                params = {'model': model_name}
                
                # Собираем из текущей и следующих 20 строк
                for j in range(i, min(i+25, len(lines))):
                    check_line = lines[j]
                    param_matches = re.findall(r"'([^']+)'?\s*=>\s*([^,\]]+)", check_line)
                    for key, val in param_matches:
                        if key == 'model':
                            continue
                        val = val.strip().rstrip(',')
                        if val.startswith("'") and val.endswith("'"):
                            val = val[1:-1]
                        elif val.startswith('"') and val.endswith('"'):
                            val = val[1:-1]
                        else:
                            try:
                                val = float(val) if '.' in val else int(val)
                            except:
                                pass
                        params[key] = val
                    
                    # Конец массива
                    if '],' in check_line or '],' in check_line.replace(' ', ''):
                        break
                
                if len(params) > 1:  # Есть кроме model
                    models_by_category[current_cat].append(params)
        
        # Other products - другой формат
        if current_cat == 'other_products':
            name_match = re.search(r"'name'\s*=>\s*'([^']+)'", line)
            if name_match:
                item = {'name': name_match.group(1)}
                # Собираем параметры
                for j in range(i, min(i+15, len(lines))):
                    check_line = lines[j]
                    if "'models'" in check_line:
                        models_match = re.search(r"\[([^\]]+)\]", check_line)
                        if models_match:
                            item['models'] = [m.strip().strip("'\"") for m in models_match.group(1).split(',')]
                    if "'materials'" in check_line:
                        mats_match = re.search(r"\[([^\]]+)\]", check_line)
                        if mats_match:
                            item['materials'] = [m.strip().strip("'\"") for m in mats_match.group(1).split(',')]
                    if "'description'" in check_line:
                        desc_match = re.search(r"'description'\s*=>\s*'([^']+)'", check_line)
                        if desc_match:
                            item['description'] = desc_match.group(1)
                    if '],' in check_line and 'name' not in check_line:
                        break
                
                if 'name' in item:
                    models_by_category['other_items'].append(item)
    
    return models_by_category

models = extract_models()

print("Результаты парсинга:")
total = 0
for cat, items in models.items():
    print(f"  {cat}: {len(items)}")
    total += len(items)
print(f"Всего: {total}")

# Выводим первые несколько моделей для проверки
print("\nПримеры моделей (asynchronous_three_phase_general):")
for m in models['asynchronous_three_phase_general'][:3]:
    print(f"  {m.get('model')}: мощность={m.get('мощность_квт')} кВт")

print("\nПримеры моделей (energy_efficient):")
for m in models['energy_efficient'][:3]:
    print(f"  {m.get('model')}: мощность={m.get('мощность_квт')} кВт")

