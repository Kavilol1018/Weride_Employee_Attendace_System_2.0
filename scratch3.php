<?php
$files = [
    'c:\\Users\\PC\\Downloads\\u251904595_phase2_updated.sql',
    'c:\\xampp\\htdocs\\we-ride-system-main\\weride_database_export.sql',
    'c:\\xampp\\htdocs\\we-ride-system-main\\weride_database_export_updated.sql'
];

foreach ($files as $file) {
    $content = file_get_contents($file);

    // Remove all the ALTER TABLE additions at the bottom
    $lines = explode("\n", $content);
    $new_lines = [];
    foreach ($lines as $line) {
        if (strpos($line, 'ALTER TABLE employees ADD COLUMN') !== false) continue;
        if (strpos($line, 'ALTER TABLE employees MODIFY COLUMN') !== false) continue;
        $new_lines[] = $line;
    }
    $content = implode("\n", $new_lines);

    // Inject the columns into the CREATE TABLE statement for phase2 updated
    if (strpos($file, 'phase2_updated') !== false) {
        $content = preg_replace(
            '/(casual_leave_balance int\(11\) DEFAULT 7)(\r?\n\) ENGINE=InnoDB)/',
            ',\n  unpaid_leave_balance decimal(5,1) DEFAULT 0.0,\n  hospitalization_leave_balance decimal(5,1) DEFAULT 60.0',
            $content
        );
    }

    file_put_contents($file, $content);
}
echo "DONE";
?>
