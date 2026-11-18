import os
import re

files = ['admin_employees.php', 'hr_employees.php', 'tl_employees.php', 'gl_employees.php']
for file in files:
    if not os.path.exists(file):
        continue
        
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. POST Logic
    content = re.sub(
        r'(\$lunch_window\s*=\s*mysqli_real_escape_string[^;]+;)', 
        r'\1\n    $shift_hour = isset($_POST["shift_hour"]) ? mysqli_real_escape_string($conn, $_POST["shift_hour"]) : "09:00-18:00";',
        content
    )
    
    # 2. INSERT
    content = re.sub(
        r'(role,\s*lunch_window)(,\s*group_leader_id)', 
        r'\1, shift_hour\2',
        content
    )
    content = re.sub(
        r'(\'\$role\',\s*\'\$lunch_window\')(\s*,\s*\'\$group_leader_id\')',
        r"\1, '$shift_hour'\2",
        content
    )
    
    # 3. UPDATE
    content = re.sub(
        r'(lunch_window=\'\$lunch_window\')',
        r"\1, shift_hour='$shift_hour'",
        content
    )

    # 4. Modals
    dropdown = """
                <label class="form-label">Work Hours Window (Shift Hour)</label>
                <select name="shift_hour" class="form-input shift-hour-select" required>
                    <option value="08:00-17:00">08:00 AM - 05:00 PM</option>
                    <option value="09:00-18:00">09:00 AM - 06:00 PM</option>
                    <option value="10:00-19:00">10:00 AM - 07:00 PM</option>
                    <option value="11:00-20:00">11:00 AM - 08:00 PM</option>
                    <option value="12:00-21:00">12:00 PM - 09:00 PM</option>
                    <option value="13:00-22:00">01:00 PM - 10:00 PM</option>
                </select>
"""
    content = re.sub(
        r'(<label class="form-label">Lunchtime Window</label>)',
        dropdown.strip("\n") + r'\n\1',
        content
    )

    # 5. JS openEditModal function definition
    content = content.replace(
        "function openEditModal(id, welabel, name, type, role, lunch, gl) {",
        "function openEditModal(id, welabel, name, type, role, lunch, gl, shift) {"
    )
    
    content = content.replace(
        "document.getElementById('edit_lunch_window').value = lunch;",
        "document.getElementById('edit_lunch_window').value = lunch;\n            let shiftSelects = document.querySelectorAll('#editModal .shift-hour-select'); if(shiftSelects.length) shiftSelects[0].value = shift;"
    )

    # 6. JS openEditModal call
    content = re.sub(
        r"(openEditModal\([^)]+htmlspecialchars\(\$row\['group_leader_id'\]\).*?)\)",
        r'\1, "<?= htmlspecialchars($row[\'shift_hour\'] ?? \'09:00-18:00\') ?>")',
        content
    )
    
    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)
        
    print(f"Successfully updated {file}")
