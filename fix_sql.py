#!/usr/bin/env python3
import re

with open('/workspace/polesie/sql/database_updated.sql', 'r') as f:
    lines = f.readlines()

# Исправить все строки с JSON values - добавить NULL в конце перед закрывающей скобкой
count = 0
for i in range(len(lines)):
    line = lines[i]
    # Если есть "values": и строка заканчивается на }'), или }');
    if '"values":' in line:
        if line.rstrip().endswith('}"),'):
            lines[i] = line.replace('}"),'}, NULL),\n')
            count += 1
        elif line.rstrip().endswith('}");'):
            lines[i] = line.replace('}");'}, NULL);\n')
            count += 1

with open('/workspace/polesie/sql/database_updated.sql', 'w') as f:
    f.writelines(lines)

print(f"Исправлено {count} строк с JSON values")
