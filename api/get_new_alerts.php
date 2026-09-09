-- Active: 1779784392021@@127.0.0.1@3306
<?php
session_start();
include __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    echo json_encode(['alerts' => []]);
    exit();
}

if (!isset($_SESSION['last_alert_id'])) {
    $max_res = mysqli_query($conn, "SELECT MAX(id) as m FROM system_alerts");
    $max_row = mysqli_fetch_assoc($max_res);
    $_SESSION['last_alert_id'] = $max_row['m'] ? $max_row['m'] : 0;
}

$alerts = [];

// --- AUTO LATE DETECTION ---
$current_time = date('H:i');
$today = date('Y-m-d');
$day_of_week = date('N'); // 1 (Mon) to 7 (Sun)

if ($day_of_week < 6) { // Not a weekend
    // Check public holidays
    $ph_sql = "SELECT id FROM public_holidays WHERE holiday_date = '$today'";
    $ph_res = mysqli_query($conn, $ph_sql);
    $is_ph = mysqli_num_rows($ph_res) > 0;
    
    if (!$is_ph) {
        $late_sql = "
            SELECT e.emp_id, e.name, e.shift_hour 
            FROM employees e 
            LEFT JOIN attendance_logs a ON e.emp_id = a.emp_id AND a.date = '$today'
            LEFT JOIN leave_requests lr ON e.emp_id = lr.emp_id AND lr.status = 'approved' AND '$today' BETWEEN lr.start_date AND lr.end_date
            WHERE e.role = 'employee' 
            AND a.id IS NULL 
            AND lr.id IS NULL
        ";
        $late_res = mysqli_query($conn, $late_sql);
        
        if ($late_res) {
            while ($emp = mysqli_fetch_assoc($late_res)) {
                $shift = !empty($emp['shift_hour']) ? $emp['shift_hour'] : '09:00-18:00';
                $shift_parts = explode('-', $shift);
                $shift_start = trim($shift_parts[0]);
                
                if ($current_time > $shift_start) {
                    $insert_sql = "INSERT IGNORE INTO attendance_logs (emp_id, date, status, flag) VALUES ('{$emp['emp_id']}', '$today', 'A', 'Late')";
                    mysqli_query($conn, $insert_sql);
                    
                    if (mysqli_affected_rows($conn) > 0) {
                        $alert_msg = mysqli_real_escape_string($conn, "AUTO-ALERT: {$emp['name']} ({$emp['emp_id']}) is late for their $shift_start shift!");
                        mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
                    }
                }
            }
        }
    }
}
// ---------------------------

$last_shown = $_SESSION['last_alert_id'];
$query = mysqli_query($conn, "SELECT id, message FROM system_alerts WHERE id > $last_shown ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($query)) {
    $alerts[] = $row['message'];
    $_SESSION['last_alert_id'] = $row['id'];
}

echo json_encode(['alerts' => $alerts]);
?>
