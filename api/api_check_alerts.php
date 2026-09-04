<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include '../db_connect.php';
/** @var mysqli $conn */

$last_check = isset($_SESSION['last_alert_check']) ? $_SESSION['last_alert_check'] : date('Y-m-d H:i:s', strtotime('-1 minute'));
$now = date('Y-m-d H:i:s');
$last_time = date('H:i', strtotime($last_check));
$now_time = date('H:i', strtotime($now));

$violations = [];

// 1. OVERTIME VIOLATIONS
$sql_overtime = "SELECT e.name, TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) as duration
        FROM lunch_breaks lb
        JOIN employees e ON lb.employee_id = e.emp_id
        WHERE lb.status = 'returned' 
        AND TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) > 60 
        AND lb.break_end > '$last_check'
        AND lb.break_end <= '$now'";

$res_overtime = mysqli_query($conn, $sql_overtime);
if ($res_overtime) {
    while ($row = mysqli_fetch_assoc($res_overtime)) {
        $row['type'] = 'overtime';
        $violations[] = $row;
    }
}

// 2. MISSED WINDOW VIOLATIONS
// Employees who have no break today, and their window end time just passed between $last_check and $now.
$sql_missed = "SELECT emp_id, name, lunch_window FROM employees WHERE role = 'employee'";
$res_missed = mysqli_query($conn, $sql_missed);
if ($res_missed) {
    while ($emp = mysqli_fetch_assoc($res_missed)) {
        $window = $emp['lunch_window'] ?? '12:00-13:00';
        $window_parts = explode('-', $window);
        $end_time = $window_parts[1] ?? '13:00';
        
        // If window ended exactly between last check and now
        if ($end_time >= $last_time && $end_time < $now_time) {
            // Check if they took a break today
            $emp_id = $emp['emp_id'];
            $check_break = mysqli_query($conn, "SELECT id FROM lunch_breaks WHERE employee_id = '$emp_id' AND DATE(break_start) = CURDATE()");
            if (mysqli_num_rows($check_break) == 0) {
                $violations[] = [
                    'name' => $emp['name'],
                    'type' => 'missed',
                    'window' => $window
                ];
            }
        }
    }
}

$_SESSION['last_alert_check'] = $now;

echo json_encode(['violations' => $violations]);
?>

