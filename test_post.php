<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['emp_id'] = 'EMP001';
$_SESSION['role'] = 'admin';

$_SERVER["REQUEST_METHOD"] = "POST";
$_POST['table'] = 'short_breaks';
$_POST['record_id'] = 1; // Assuming 1 exists
$_POST['new_start_time'] = '12:00';
$_POST['new_end_time'] = '12:30';
$_POST['category'] = 'Short Break';
$_POST['return_url'] = 'breaktime_report.php';

try {
    ob_start();
    include 'edit_break_record.php';
    $out = ob_get_clean();
    echo "Output: " . $out;
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Error $e) {
    echo "Error: " . $e->getMessage();
}
?>
