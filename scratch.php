<?php
$content = file_get_contents('c:\\Users\\PC\\Downloads\\u251904595_phase2_updated.sql');

$content = preg_replace(
    '/(.*?casual_leave_balance int\(11\) DEFAULT 7)(\r?\n\) ENGINE=InnoDB)/',
    ',\n  unpaid_leave_balance decimal(5,1) DEFAULT 0.0,\n  hospitalization_leave_balance decimal(5,1) DEFAULT 60.0',
    $content
);

$content = preg_replace('/ALTER TABLE employees ADD COLUMN unpaid_leave_balance.*?\n/', '', $content);
$content = preg_replace('/ALTER TABLE employees ADD COLUMN hospitalization_leave_balance.*?\n/', '', $content);
$content = preg_replace('/ALTER TABLE employees MODIFY COLUMN nnual_leave_balance.*?\n/', '', $content);
$content = preg_replace('/ALTER TABLE employees MODIFY COLUMN sick_leave_balance.*?\n/', '', $content);

file_put_contents('c:\\Users\\PC\\Downloads\\u251904595_phase2_updated.sql', $content);
echo "DONE";
?>
