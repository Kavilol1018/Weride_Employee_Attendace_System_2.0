<?php
$files = [
    'c:\\xampp\\htdocs\\we-ride-system-main\\weride_database_export.sql',
    'c:\\xampp\\htdocs\\we-ride-system-main\\weride_database_export_updated.sql'
];

foreach ($files as $file) {
    $content = file_get_contents($file);

    $content = preg_replace('/ALTER TABLE employees ADD COLUMN unpaid_leave_balance.*?\n/', '', $content);
    $content = preg_replace('/ALTER TABLE employees ADD COLUMN hospitalization_leave_balance.*?\n/', '', $content);
    $content = preg_replace('/ALTER TABLE employees MODIFY COLUMN nnual_leave_balance.*?\n/', '', $content);
    $content = preg_replace('/ALTER TABLE employees MODIFY COLUMN sick_leave_balance.*?\n/', '', $content);

    file_put_contents($file, $content);
}
echo "DONE";
?>
