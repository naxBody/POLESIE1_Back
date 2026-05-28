#!/usr/bin/env python3
import re
import json

# Читаем polesie_products.php и извлекаем данные
with open('/workspace/polesie_products.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Парсим модели двигателей
models_data = {
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
    'other_products': [],
}

# Категория 1: АИР (общепромышленные)
air_pattern = r"\['model'\s*=>\s*'([^']+)',\s*'мощность_квт'\s*=>\s*([0-9.]+),\s*'частота_вращения_об_мин'\s*=>\s*(\d+),\s*'кпд_проц'\s*=>\s*([0-9.]+)"
matches = re.findall(air_pattern, content)

# Разбираем вручную по секциям
lines = content.split('\n')
current_category = None
current_model = {}
in_models_section = False

for i, line in enumerate(lines):
    # Определяем категорию
    if "'asynchronous_three_phase_general'" in line and "=>" in line:
        current_category = 'asynchronous_three_phase_general'
    elif "'energy_efficient'" in line and "=>" in line:
        current_category = 'energy_efficient'
    elif "'multispeed'" in line and "=>" in line:
        current_category = 'multispeed'
    elif "'high_slip'" in line and "=>" in line:
        current_category = 'high_slip'
    elif "'for_pumps'" in line and "=>" in line:
        current_category = 'for_pumps'
    elif "'for_gearboxes'" in line and "=>" in line:
        current_category = 'for_gearboxes'
    elif "'for_poultry'" in line and "=>" in line:
        current_category = 'for_poultry'
    elif "'for_railway'" in line and "=>" in line:
        current_category = 'for_railway'
    elif "'explosion_proof'" in line and "=>" in line:
        current_category = 'explosion_proof'
    elif "'single_phase'" in line and "=>" in line:
        current_category = 'single_phase'
    elif "'centrifugal_household'" in line and "=>" in line:
        current_category = 'centrifugal_household'
    elif "'submersible_dirty'" in line and "=>" in line:
        current_category = 'submersible_dirty'
    elif "'other_products'" in line and "=>" in line:
        current_category = 'other_products'
    
    # Собираем модели
    if current_category and current_category not in ['centrifugal_household', 'submersible_dirty', 'other_products']:
        model_match = re.search(r"'model'\s*=>\s*'([^']+)'", line)
        if model_match:
            if current_model:
                models_data[current_category].append(current_model)
            current_model = {'model': model_match.group(1)}
        
        # Собираем параметры
        if current_model:
            param_match = re.search(r"'([^']+)'?\s*=>\s*([^,\]]+)", line)
            if param_match and param_match.group(1) != 'model':
                key = param_match.group(1).strip()
                val = param_match.group(2).strip().rstrip(',')
                # Очищаем значение
                if val.startswith("'") and val.endswith("'"):
                    val = val[1:-1]
                elif val.startswith('"') and val.endswith('"'):
                    val = val[1:-1]
                else:
                    try:
                        if '.' in val:
                            val = float(val)
                        else:
                            val = int(val)
                    except:
                        pass
                current_model[key] = val

# Добавляем последний модель
if current_model and current_category:
    models_data[current_category].append(current_model)

print("Найдено моделей по категориям:")
for cat, models in models_data.items():
    print(f"  {cat}: {len(models)}")

