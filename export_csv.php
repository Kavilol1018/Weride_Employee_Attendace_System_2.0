<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

// FILTER VALUES
$filter_employee = isset($_GET['employee']) ? mysqli_real_escape_string($conn, $_GET['employee']) : '';
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';

// BUILD QUERY WITH FILTERS
$where_clauses = ["lb.status = 'returned'"];

if ($filter_employee) {
    $where_clauses[] = "(e.name LIKE '%$filter_employee%' OR e.emp_id LIKE '%$filter_employee%')";
}
if ($filter_date_from) {
    $where_clauses[] = "DATE(lb.break_start) >= '$filter_date_from'";
}
if ($filter_date_to) {
    $where_clauses[] = "DATE(lb.break_start) <= '$filter_date_to'";
}

$where_sql = implode(' AND ', $where_clauses);

$history_sql = "
    SELECT 
        e.emp_id, e.name, e.employment_type,
        DATE(lb.break_start) as break_date,
        TIME(lb.break_start) as start_time, 
        TIME(lb.break_end) as end_time,
        TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) as duration_min,
        CASE 
            WHEN TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) > 60 THEN 'Overtime'
            ELSE 'Within Limit'
        END as break_status
    FROM lunch_breaks lb
    LEFT JOIN employees e ON lb.employee_id = e.emp_id
    WHERE $where_sql
    ORDER BY lb.break_start DESC
";
$result = mysqli_query($conn, $history_sql);

$filename = "lunchtime_history_export_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, ['Employee ID', 'Full Name', 'Employment Type', 'Date', 'Start Time', 'End Time', 'Duration (min)', 'Status']);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
}
fclose($output);
exit();
?>
