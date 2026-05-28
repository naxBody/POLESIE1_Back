#!/usr/bin/env python3
"""
Генератор SQL-файла для импорта полной номенклатуры продукции ОАО «Полесьеэлектромаш»
"""
import re
import json

with open('/workspace/polesie_products.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Парсинг моделей
def parse_models_from_category(cat_name, content):
    models = []
    pattern = rf"// (?:Габарит|Модели)[^\n]*\n([\s\S]*?)(?=// |\])"
    
    # Находим секцию категории
    cat_start = content.find(f"'{cat_name}'")
    if cat_start == -1:
        return models
    
    # Ищем все модели в этой категории
    model_pattern = r"\['model'\s*=>\s*'([^']+)'[^]]*\]"
    
    section_start = content.find('[', cat_start)
    bracket_count = 0
    section_end = section_start
    
    for i in range(section_start, len(content)):
        if content[i] == '[':
            bracket_count += 1
        elif content[i] == ']':
            bracket_count -= 1
            if bracket_count == 0:
                section_end = i + 1
                break
    
    section = content[section_start:section_end]
    
    # Находим все массивы моделей
    model_arrays = re.findall(r'\[[\s\S]*?\]', section)
    
    for arr in model_arrays:
        model_match = re.search(r"'model'\s*=>\s*'([^']+)'", arr)
        if model_match:
            model = {'model': model_match.group(1)}
            # Извлекаем все параметры
            params = re.findall(r"'([^']+)'?\s*=>\s*([^,\]]+)", arr)
            for key, val in params:
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
                model[key] = val
            models.append(model)
    
    return models

# Простой парсер для извлечения всех моделей
def extract_all_models():
    lines = content.split('\n')
    categories = {
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
    }
    
    current_cat = None
    current_model = {}
    in_model_array = False
    array_depth = 0
    
    for line in lines:
        # Определяем категорию
        for cat_key in categories.keys():
            if f"'{cat_key}'" in line and "=>" in line:
                current_cat = cat_key
                break
        
        if current_cat is None:
            continue
        
        # Начало массива модели
        if '[' in line and 'model' not in line and current_cat:
            in_model_array = True
            array_depth = line.count('[') - line.count(']')
            current_model = {}
        
        if in_model_array:
            array_depth += line.count('[') - line.count(']')
            
            # Извлекаем модель
            model_match = re.search(r"'model'\s*=>\s*'([^']+)'", line)
            if model_match:
                current_model['model'] = model_match.group(1)
            
            # Извлекаем параметры
            params = re.findall(r"'([^']+)'?\s*=>\s*([^,\]]+)", line)
            for key, val in params:
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
                current_model[key] = val
            
            # Конец массива модели
            if array_depth <= 0 and current_model:
                if 'model' in current_model:
                    categories[current_cat].append(current_model)
                current_model = {}
                in_model_array = False
    
    # Добавляем последний если остался
    if current_model and 'model' in current_model:
        categories[current_cat].append(current_model)
    
    return categories

cats = extract_all_models()
print("Категории и количество моделей:")
total = 0
for cat, models in cats.items():
    print(f"  {cat}: {len(models)}")
    total += len(models)
print(f"Всего: {total}")

