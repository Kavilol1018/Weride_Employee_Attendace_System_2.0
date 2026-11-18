<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}

$filter_date = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : date('Y-m-d');
$filter_dept = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';

$dept_filter_sql = "";
if ($filter_dept) {
    $dept_filter_sql = "AND e.department = '$filter_dept'";
}

$emp_filter_sql = "";
if ($_SESSION['role'] === 'tl' || $_SESSION['role'] === 'gl') {
    $emp_filter_sql = "AND e.group_leader_id = '" . $_SESSION['emp_id'] . "'";
}

$filename = "Manpower_Overview_{$filter_date}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// CSV Headers
$headers = [
    'Employee Name', 'Date', 'Clock In', 'Clock Out', 'Status', 'Elapsed (Late)'
];
fputcsv($output, $headers);

$manpower_sql = "
    SELECT 
        e.id,
        e.emp_id, e.name, 
        e.employment_type,
        e.shift_hour,
        COALESCE(lb.break_start, sb.break_start) as break_start,
        CASE 
            WHEN lb.status = 'on_break' THEN 'on_break'
            WHEN sb.status = 'on_break' THEN 'on_break'
            ELSE 'returned'
        END as break_status,
        CASE 
            WHEN lb.status = 'on_break' THEN 'Lunch Break'
            WHEN sb.status = 'on_break' THEN 'Short Break'
            ELSE ''
        END as break_type,
        COALESCE(lb.break_id, sb.id) as break_id,
        (SELECT status FROM attendance_logs WHERE emp_id = e.emp_id AND date = '$filter_date' AND status = 'A' LIMIT 1) as absent_status,
        (SELECT clock_in FROM attendance_logs WHERE emp_id = e.emp_id AND date = '$filter_date' LIMIT 1) as clock_in_time,
        (SELECT clock_out FROM attendance_logs WHERE emp_id = e.emp_id AND date = '$filter_date' LIMIT 1) as clock_out_time,
        (SELECT leave_type FROM leave_requests WHERE emp_id = e.emp_id AND status = 'approved' AND start_date <= '$filter_date' AND end_date >= '$filter_date' LIMIT 1) as leave_status,
        (SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, COALESCE(break_end, NOW()))) FROM short_breaks WHERE emp_id = e.emp_id AND DATE(break_start) = '$filter_date') as total_sb_elapsed_sec,
        (SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, COALESCE(break_end, NOW()))) FROM lunch_breaks WHERE employee_id = e.emp_id AND DATE(break_start) = '$filter_date') as total_lb_elapsed_sec,
        (SELECT SUM(duration) FROM break_override_requests WHERE emp_id = e.emp_id AND status = 'approved' AND DATE(created_at) = '$filter_date') as total_override_mins
    FROM employees e
    LEFT JOIN lunch_breaks lb ON e.emp_id = lb.employee_id AND lb.status = 'on_break'
    LEFT JOIN short_breaks sb ON e.emp_id = sb.emp_id AND sb.status = 'on_break'
    WHERE 1=1 $dept_filter_sql $emp_filter_sql AND e.role NOT IN ('admin', 'hr')
    ORDER BY (lb.status = 'on_break' OR sb.status = 'on_break') DESC, e.name ASC
";
$manpower_result = mysqli_query($conn, $manpower_sql);

if ($manpower_result && mysqli_num_rows($manpower_result) > 0) {
    while ($row = mysqli_fetch_assoc($manpower_result)) {
        $shift_parts = explode('-', $row['shift_hour'] ?? '09:00 - 18:00');
        $shift_start = trim($shift_parts[0]);
        
        $late_status_display = '--';
        $late_elapsed_display = '0 min';
        
        if ($row['clock_in_time']) {
            $clock_in_dt = new DateTime($row['clock_in_time']);
            $clock_in_time_only = $clock_in_dt->format('H:i');
            $shift_start_dt = DateTime::createFromFormat('H:i', $shift_start);
            if (!$shift_start_dt) $shift_start_dt = DateTime::createFromFormat('H:i:s', $shift_start . ':00');
            if ($shift_start_dt) {
                if ($clock_in_time_only > $shift_start_dt->format('H:i')) {
                    $late_status_display = 'Late';
                    $clock_in_minutes = ((int)$clock_in_dt->format('H') * 60) + (int)$clock_in_dt->format('i');
                    $shift_start_minutes = ((int)$shift_start_dt->format('H') * 60) + (int)$shift_start_dt->format('i');
                    $late_mins = $clock_in_minutes - $shift_start_minutes;
                    if ($late_mins > 0) $late_elapsed_display = $late_mins . ' min';
                } else {
                    $late_status_display = 'On Time';
                }
            }
        }

        $c_in_display = $row['clock_in_time'] ? date('H:i', strtotime($row['clock_in_time'])) : '--';
        $c_out_display = $row['clock_out_time'] ? date('H:i', strtotime($row['clock_out_time'])) : '--';

        fputcsv($output, [
            $row['name'],
            date('d M Y', strtotime($filter_date)),
            $c_in_display,
            $c_out_display,
            $late_status_display,
            $late_elapsed_display
        ]);
    }
}

fclose($output);
exit();
?>
