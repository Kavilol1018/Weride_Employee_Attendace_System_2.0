import re

with open('c:\\Users\\PC\\Downloads\\u251904595_phase2_updated.sql', 'r') as f:
    content = f.read()

# Replace CREATE TABLE ending
content = re.sub(
    r'(\casual_leave_balance\ int\(11\) DEFAULT 7)(\r?\n\) ENGINE=InnoDB)',
    r'\1,\n  unpaid_leave_balance decimal(5,1) DEFAULT 0.0,\n  hospitalization_leave_balance decimal(5,1) DEFAULT 60.0\2',
    content
)

# Remove ALTER TABLE statements at the bottom, keep only UPDATE statements
content = re.sub(r'ALTER TABLE employees ADD COLUMN unpaid_leave_balance.*?\n', '', content)
content = re.sub(r'ALTER TABLE employees ADD COLUMN hospitalization_leave_balance.*?\n', '', content)
content = re.sub(r'ALTER TABLE employees MODIFY COLUMN nnual_leave_balance.*?\n', '', content)
content = re.sub(r'ALTER TABLE employees MODIFY COLUMN sick_leave_balance.*?\n', '', content)

with open('c:\\Users\\PC\\Downloads\\u251904595_phase2_updated.sql', 'w') as f:
    f.write(content)
