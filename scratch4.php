<?php
$files = [
    'c:\\xampp\\htdocs\\we-ride-system-main\\employee_lunchtime_history.php',
    'c:\\xampp\\htdocs\\we-ride-system-main\\employee_breaktime_history.php',
    'c:\\xampp\\htdocs\\we-ride-system-main\\employee_attendance_history.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $content = str_replace(
        "\ = isset(\['start_date']) ? \['start_date'] : date('Y-m-d');",
        "\ = isset(\['start_date']) ? \['start_date'] : date('Y-m-01');",
        $content
    );
    file_put_contents($file, $content);
}
echo "DONE";
?>
