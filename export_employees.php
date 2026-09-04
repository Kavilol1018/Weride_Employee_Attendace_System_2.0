<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

// FILTER VALUES
$filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';

// BUILD QUERY
$query = "SELECT emp_id, welabel_id, name, employment_type, role, lunch_window, group_leader_id FROM employees";
if ($filter_type) {
    $query .= " WHERE employment_type = '$filter_type'";
}
$query .= " ORDER BY id DESC";

$result = mysqli_query($conn, $query);

$filename = "employee_directory_export_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
// Add UTF-8 BOM for Excel compatibility
fputs($output, "\xEF\xBB\xBF");
fputcsv($output, ['Employee ID', 'WeLabel ID', 'Full Name', 'Employment Type', 'System Role', 'Lunchtime Window', 'Group Leader ID']);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
}
fclose($output);
exit();
?>

