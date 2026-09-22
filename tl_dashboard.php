<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tl') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

$current_employee_id = $_SESSION['emp_id']; 

$emp_query = mysqli_query($conn, "SELECT employment_type, lunch_window, name, annual_leave_balance, sick_leave_balance, shift_hour FROM employees WHERE emp_id = '$current_employee_id'");
$emp_data = mysqli_fetch_assoc($emp_query);
$employment_type = $emp_data['employment_type'] ?? 'full_time';
$lunch_window = $emp_data['lunch_window'] ?? '12:00-13:00';
$employee_name = $emp_data['name'] ?? "Employee #" . $current_employee_id;
$al_bal = $emp_data['annual_leave_balance'] ?? 8;
$sl_bal = $emp_data['sick_leave_balance'] ?? 14;
$shift_hour = $emp_data['shift_hour'] ?? '09:00-18:00';

// FETCH TOTAL APPROVED OVERRIDE DURATION TODAY
$override_query_limit = mysqli_query($conn, "SELECT SUM(duration) as total_override FROM break_override_requests WHERE emp_id = '$current_employee_id' AND status = 'approved' AND DATE(created_at) = CURDATE()");
$override_row_limit = mysqli_fetch_assoc($override_query_limit);
$total_override_mins = $override_row_limit['total_override'] ? (int)$override_row_limit['total_override'] : 0;
$total_lunch_limit = 60 + $total_override_mins;

// VALIDATE IP (COMPANY WIFI)
$allowed_ips = ['127.0.0.1', '::1', '10.22.7.117', '211.25.36.214', '113.211.196.97', '182.62.34.248'];
$user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
if (strpos($user_ip, ',') !== false) { $user_ip = explode(',', $user_ip)[0]; }
$user_ip = trim($user_ip);
$is_valid_ip = in_array($user_ip, $allowed_ips) || strpos($user_ip, '10.22.') === 0 || strpos($user_ip, 'fe80:') === 0 || strpos($user_ip, '211.25.') === 0 || strpos($user_ip, '113.211.') === 0 || strpos($user_ip, '182.62.') === 0;

// VALIDATE TIME (LUNCH WINDOW)
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_time = date('H:i');
$window_parts = explode('-', $lunch_window);
$start_time = $window_parts[0];
$end_time = $window_parts[1];
$is_valid_time = ($current_time >= $start_time && $current_time <= $end_time);

// CHECK OVERRIDE FOR TODAY
$override_query = mysqli_query($conn, "SELECT id FROM break_override_requests WHERE emp_id = '$current_employee_id' AND status IN ('approved', 'pending') AND DATE(created_at) = CURDATE()");
$has_override = mysqli_num_rows($override_query) > 0;

// PREVIOUS LUNCH USAGE
$lb_usage_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total_sec FROM lunch_breaks WHERE employee_id = '$current_employee_id' AND DATE(break_start) = CURDATE() AND status = 'returned'";
$lb_usage_res = mysqli_fetch_assoc(mysqli_query($conn, $lb_usage_sql));
$previous_breaks_elapsed_sec = $lb_usage_res['total_sec'] ? (int)$lb_usage_res['total_sec'] : 0;

// TODAY ATTENDANCE LOG (CLOCK IN / OUT)
$today_att_res = mysqli_query($conn, "SELECT * FROM attendance_logs WHERE emp_id = '$current_employee_id' AND date = CURDATE()");
$today_att = mysqli_fetch_assoc($today_att_res);
$has_clocked_in = !empty($today_att['clock_in']);
$has_clocked_out = !empty($today_att['clock_out']);

// CHECK EMERGENCY LEAVE
$shift_parts = explode('-', $shift_hour);
$shift_end = isset($shift_parts[1]) ? trim($shift_parts[1]) : '18:00';
$is_early_clockout = (date('H:i') < $shift_end);

$leave_check_sql = "SELECT id FROM leave_requests WHERE emp_id = '$current_employee_id' AND status = 'approved' AND start_date <= CURDATE() AND end_date >= CURDATE() AND leave_type IN ('Half-Day Leave', 'Half-Day Leave (Before Lunch)', 'Half-Day Leave (After Lunch)', 'Emergency Leave')";
$has_early_leave = mysqli_num_rows(mysqli_query($conn, $leave_check_sql)) > 0;

// DEVICE VALIDATION
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_mobile = preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $user_agent);

// POST ACTIONS
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    // CLOCK IN ACTION
    if (isset($_POST['clock_in'])) {
        if (!$is_valid_ip) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Company WiFi required."));
            exit();
        }
        if ($is_mobile) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Clock In must be done on a workstation, not a mobile device."));
            exit();
        }
        
        // Get employee data if not already fetched
            $emp_data_res = mysqli_query($conn, "SELECT shift_hour FROM employees WHERE emp_id = '$current_employee_id'");
            $emp_data = mysqli_fetch_assoc($emp_data_res);
            $shift = !empty($emp_data['shift_hour']) ? $emp_data['shift_hour'] : '09:00-18:00';
            $shift_parts = explode('-', $shift);
            $shift_start = trim($shift_parts[0]);
            
            $is_late = (date('H:i') > $shift_start);
            $flag = $is_late ? 'Late' : 'None';
            $status = 'P';
            
            if ($has_clocked_in) {
                // Re-clock-in: only clear clock_out, preserve original clock_in
                mysqli_query($conn, "UPDATE attendance_logs SET clock_out = NULL, status = '$status' WHERE emp_id = '$current_employee_id' AND date = CURDATE()");
            } else {
                // First clock-in of the day
                mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, clock_in, clock_out, status, flag) VALUES ('$current_employee_id', CURDATE(), NOW(), NULL, '$status', '$flag') ON DUPLICATE KEY UPDATE clock_in = NOW(), clock_out = NULL, status = '$status', flag = '$flag'");
                if ($is_late) {
                    $alert_msg = mysqli_real_escape_string($conn, "$employee_name ($current_employee_id) has clocked in late at " . date('H:i') . ".");
                    mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
                }
            }
            header("Location: gl_dashboard.php?msg=" . urlencode("Clocked In successfully!"));
            exit();
    }

    // CLOCK OUT ACTION
    if (isset($_POST['clock_out'])) {
        if (!$is_valid_ip) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Company WiFi required."));
            exit();
        }
        if ($is_mobile) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Clock Out must be done on a workstation, not a mobile device."));
            exit();
        }
        if ($has_clocked_in) {
            $emp_data_res = mysqli_query($conn, "SELECT shift_hour FROM employees WHERE emp_id = '$current_employee_id'");
            $emp_data = mysqli_fetch_assoc($emp_data_res);
            $shift = !empty($emp_data['shift_hour']) ? $emp_data['shift_hour'] : '09:00-18:00';
            $shift_parts = explode('-', $shift);
            $shift_end = isset($shift_parts[1]) ? trim($shift_parts[1]) : '18:00';
            
            $is_early = (date('H:i') < $shift_end);
            


            $current_flag = $today_att['flag'] ?? 'None';
            
            if ($is_early) {
                if ($current_flag == 'Late') {
                    $new_flag = 'Both';
                } else {
                    $new_flag = 'Early';
                }
            } else {
                $new_flag = $current_flag;
            }

            $status = $today_att['status'] ?? 'P';
            mysqli_query($conn, "UPDATE attendance_logs SET clock_out = NOW(), status = '$status', flag = '$new_flag', work_hours = GREATEST(0, TIMESTAMPDIFF(MINUTE, clock_in, NOW())/60.0) WHERE emp_id = '$current_employee_id' AND date = CURDATE()");
            
            if ($is_early) {
                $alert_msg = mysqli_real_escape_string($conn, "$employee_name ($current_employee_id) has clocked out early at " . date('H:i') . ".");
                mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
            }
            
            // Auto-end any active breaks
            mysqli_query($conn, "UPDATE lunch_breaks SET status = 'returned', break_end = CURRENT_TIMESTAMP WHERE employee_id = '$current_employee_id' AND status = 'on_break'");
            
            $break_query = mysqli_query($conn, "SELECT id, TIMESTAMPDIFF(MINUTE, break_start, NOW()) as duration_mins FROM short_breaks WHERE emp_id = '$current_employee_id' AND status = 'on_break'");
            while ($break_row = mysqli_fetch_assoc($break_query)) {
                $duration_mins = (int)$break_row['duration_mins'];
                $bid = $break_row['id'];
                mysqli_query($conn, "UPDATE short_breaks SET break_end = CURRENT_TIMESTAMP, duration_min = $duration_mins, status = 'returned' WHERE id = $bid");
            }

            header("Location: gl_dashboard.php?msg=" . urlencode("Clocked Out successfully!"));
            exit();
        }
    }



    if (isset($_POST['request_override'])) {
        $type = isset($_POST['req_category']) ? mysqli_real_escape_string($conn, $_POST['req_category']) : 'Override';
        $reason_text = mysqli_real_escape_string($conn, $_POST['reason']);
        $reason = $type . " - " . $reason_text;
        $duration = isset($_POST['duration']) && $_POST['duration'] !== '' ? (int)$_POST['duration'] : 60;
        mysqli_query($conn, "INSERT INTO break_override_requests (emp_id, reason, duration, status) VALUES ('$current_employee_id', '$reason', $duration, 'pending')");
        
        $alert_msg = mysqli_real_escape_string($conn, "New Override Request from $employee_name ($current_employee_id): $reason");
        mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
        
        if (isset($_POST['force_start'])) {
            $sql = "INSERT INTO lunch_breaks (employee_id, status) VALUES ('$current_employee_id', 'on_break')";
            mysqli_query($conn, $sql);
            header("Location: gl_dashboard.php?msg=" . urlencode("Override sent and Meal Out started!"));
            exit();
        } else {
            header("Location: gl_dashboard.php?msg=" . urlencode("Override request sent to your manager."));
            exit();
        }
    }

    if (isset($_POST['start_break'])) {
        if (!$is_valid_ip) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Company WiFi required."));
            exit();
        }
        $sql = "INSERT INTO lunch_breaks (employee_id, status) VALUES ('$current_employee_id', 'on_break')";
        if (mysqli_query($conn, $sql)) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Meal Out successful!"));
            exit();
        }
    } elseif (isset($_POST['end_break'])) {
        if (!$is_valid_ip) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Company WiFi required."));
            exit();
        }
        $sql = "UPDATE lunch_breaks SET status = 'returned', break_end = CURRENT_TIMESTAMP WHERE employee_id = '$current_employee_id' AND status = 'on_break'";
        if (mysqli_query($conn, $sql)) {
            $check_sql = "SELECT TIMESTAMPDIFF(MINUTE, break_start, break_end) as duration FROM lunch_breaks WHERE employee_id = '$current_employee_id' AND DATE(break_start) = CURDATE() ORDER BY id DESC LIMIT 1";
            $res = mysqli_query($conn, $check_sql);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                if ($row['duration'] > 60) {
                    $alert_msg = mysqli_real_escape_string($conn, "$employee_name ($current_employee_id) returned from lunch late (" . $row['duration'] . " mins).");
                    mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
                }
            }
            
            header("Location: gl_dashboard.php?msg=" . urlencode("Meal In successful!"));
            exit();
        }
    }
    
    // FLOATING LEAVE APPLICATION SUBMISSION
    if (isset($_POST['submit_leave'])) {
        $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date = !empty($_POST['end_date']) ? mysqli_real_escape_string($conn, $_POST['end_date']) : $start_date;
        
        $start_time = (isset($_POST['start_time']) && $_POST['start_time'] !== '') ? mysqli_real_escape_string($conn, $_POST['start_time']) : NULL;
        $end_time = (isset($_POST['end_time']) && $_POST['end_time'] !== '') ? mysqli_real_escape_string($conn, $_POST['end_time']) : NULL;
        
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $application_date = date('Y-m-d');
        
        $duration = isset($_POST['duration']) ? mysqli_real_escape_string($conn, $_POST['duration']) : '';
        
        $mc_path = "";
        $error = "";
        
        $start_dt = new DateTime($start_date);
        $end_dt = new DateTime($end_date);
        $interval = $start_dt->diff($end_dt);
        
        if (strtolower($employment_type) === 'intern' && strpos(strtoupper($leave_type), 'UNPAID') === false) {
            $error = "Interns are only eligible for Unpaid Leave.";
        }
        
        if (strpos(strtoupper($leave_type), 'ANNUAL') !== false && empty($error)) {
            $fourteen_days_from_now = new DateTime();
            $fourteen_days_from_now->setTime(0, 0, 0);
            $fourteen_days_from_now->modify('+14 days');
            if ($start_dt < $fourteen_days_from_now) {
                $error = "Annual Leave must be applied at least 14 days in advance.";
            }
            if ($duration > $al_bal) {
                $error = "You cannot apply for more Annual Leave days ($duration) than your remaining balance ($al_bal).";
            }
        }

        if (strpos(strtoupper($leave_type), 'SICK') !== false && empty($error)) {
            if ($duration > $sl_bal) {
                $error = "You cannot apply for more Sick Leave days ($duration) than your remaining balance ($sl_bal).";
            }
        }
        
        if (isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] == 0) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_info = pathinfo($_FILES['medical_certificate']['name']);
            $ext = strtolower($file_info['extension']);
            
            if (in_array($ext, $allowed_ext)) {
                $upload_dir = 'uploads/medical_certificates/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $safe_name = preg_replace('/[^A-Za-z0-9]/', '', $employee_name);
                $new_filename = $safe_name . '_' . $start_date . '_' . $end_date . '.' . $ext;
                $dest = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $dest)) {
                    $mc_path = $dest;
                } else {
                    $error = "Failed to upload medical certificate.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
            }
        }

        if (empty($error)) {
            $capacity_check = check_leave_capacity($conn, $start_date, $end_date, $current_employee_id);
            if ($capacity_check !== true) {
                header("Location: gl_dashboard.php?msg=" . urlencode($capacity_check));
                exit();
            }

            $start_time_sql = $start_time ? "'$start_time'" : "NULL";
            $end_time_sql = $end_time ? "'$end_time'" : "NULL";
            $sql = "INSERT INTO leave_requests (emp_id, leave_type, start_date, start_time, end_date, end_time, duration, reason, application_date, mc_attachment, status) 
                    VALUES ('$current_employee_id', '$leave_type', '$start_date', $start_time_sql, '$end_date', $end_time_sql, '$duration', '$reason', '$application_date', '$mc_path', 'pending_hr')";
            if (mysqli_query($conn, $sql)) {
                $alert_msg = mysqli_real_escape_string($conn, "$employee_name ($current_employee_id) requested $leave_type.");
                mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");

                header("Location: gl_dashboard.php?msg=" . urlencode("Leave request submitted successfully!"));
                exit();
            } else {
                header("Location: gl_dashboard.php?msg=" . urlencode("Database error: " . mysqli_error($conn)));
                exit();
            }
        } else {
            header("Location: gl_dashboard.php?msg=" . urlencode($error));
            exit();
        }
    }
    
    if (isset($_POST['start_short_break'])) {
        if (!$is_valid_ip) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Company WiFi required."));
            exit();
        }
        $sql = "INSERT INTO short_breaks (emp_id, period, status) VALUES ('$current_employee_id', 'pre_lunch', 'on_break')";
        mysqli_query($conn, $sql);
        header("Location: gl_dashboard.php");
        exit();
    } elseif (isset($_POST['end_short_break'])) {
        if (!$is_valid_ip) {
            header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: Company WiFi required."));
            exit();
        }
        $break_query = mysqli_query($conn, "SELECT id, TIMESTAMPDIFF(MINUTE, break_start, NOW()) as duration_mins FROM short_breaks WHERE emp_id = '$current_employee_id' AND status = 'on_break'");
        if ($break_row = mysqli_fetch_assoc($break_query)) {
            $duration_mins = (int)$break_row['duration_mins'];
            $bid = $break_row['id'];
            mysqli_query($conn, "UPDATE short_breaks SET break_end = CURRENT_TIMESTAMP, duration_min = $duration_mins, status = 'returned' WHERE id = $bid");
            
            if ($duration_mins > 30) {
                $alert_msg = mysqli_real_escape_string($conn, "$employee_name ($current_employee_id) returned from break late (" . $duration_mins . " mins).");
                mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
            }
        }
        header("Location: gl_dashboard.php");
        exit();
    }
}

// CHECK CURRENT LUNCH STATUS
$check_sql = "SELECT *, TIMESTAMPDIFF(SECOND, break_start, NOW()) as elapsed_sec FROM lunch_breaks WHERE employee_id = '$current_employee_id' AND status = 'on_break' ORDER BY break_start DESC LIMIT 1";
$check_res = mysqli_query($conn, $check_sql);
$is_on_break = mysqli_num_rows($check_res) > 0;
$current_break_info = $is_on_break ? mysqli_fetch_assoc($check_res) : null;

// SHORT BREAK STATUS
$check_sb_sql = "SELECT *, TIMESTAMPDIFF(SECOND, break_start, NOW()) as elapsed_sec FROM short_breaks WHERE emp_id = '$current_employee_id' AND status = 'on_break' ORDER BY break_start DESC LIMIT 1";
$check_sb_res = mysqli_query($conn, $check_sb_sql);
$is_on_short_break = mysqli_num_rows($check_sb_res) > 0;

// MONTHLY & WEEKLY EMPLOYEE STATISTICS MOVED TO employee_stats.php

$status_message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : "";

// --- DASHBOARD FILTERS ---
$filter_date = isset($_GET['filter_date']) ? mysqli_real_escape_string($conn, $_GET['filter_date']) : date('Y-m-d');
$filter_dept = isset($_GET['filter_dept']) ? mysqli_real_escape_string($conn, $_GET['filter_dept']) : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($conn, $_GET['filter_status']) : '';
$filter_emp_id = isset($_GET['filter_emp_id']) ? mysqli_real_escape_string($conn, $_GET['filter_emp_id']) : '';

$dept_filter_sql = '';
if ($filter_dept) {
    $dept_filter_sql = " AND e.department = '$filter_dept' ";
}
$emp_filter_sql = '';
if ($filter_emp_id) {
    $emp_filter_sql = " AND e.emp_id = '$filter_emp_id' ";
}
$gl_filter_sql = $dept_filter_sql . $emp_filter_sql;

// Fetch departments for dropdown
$dept_sql = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != ''";
$dept_res = mysqli_query($conn, $dept_sql);
$departments = [];
while ($d = mysqli_fetch_assoc($dept_res)) {
    $departments[] = $d['department'];
}
// -------------------------

// FORCE END BREAK LOGIC
if (isset($_POST['force_end_break'])) {
    $break_employee_id = mysqli_real_escape_string($conn, $_POST['break_employee_id']);
    mysqli_query($conn, "UPDATE lunch_breaks SET break_end = CURRENT_TIMESTAMP, status = 'returned' WHERE employee_id = '$break_employee_id' AND status = 'on_break'");
    mysqli_query($conn, "UPDATE short_breaks SET break_end = CURRENT_TIMESTAMP, status = 'returned' WHERE emp_id = '$break_employee_id' AND status = 'on_break'");
    header("Location: tl_dashboard.php");
    exit();
}

// PROCESS OVERRIDES
if (isset($_POST['process_override'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("CSRF validation failed.");
    $req_id = (int)$_POST['req_id'];
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    mysqli_query($conn, "UPDATE break_override_requests SET status = '$action' WHERE id = $req_id");
    
    // Add Notification
    $req_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT emp_id FROM break_override_requests WHERE id = $req_id"));
    if ($req_data) {
        $msg = "Your lunchtime override request was " . $action . " by your manager.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('{$req_data['emp_id']}', '$msg')");
    }
    
    header("Location: tl_dashboard.php");
    exit();
}

// Fetch pending overrides
$pending_overrides = mysqli_query($conn, "SELECT r.*, e.name FROM break_override_requests r JOIN employees e ON r.emp_id = e.emp_id WHERE r.status = 'pending'");

// STATS QUERIES
$total_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employees e WHERE 1=1 $dept_filter_sql"))['cnt'];

$on_lunch_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lunch_breaks WHERE status = 'on_break'"))['cnt'];
$on_short_break_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM short_breaks WHERE status = 'on_break'"))['cnt'];
$on_break_count = $on_lunch_count + $on_short_break_count;

// Calculate true available count (Clocked in, not clocked out, not on break)
$available_count = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT e.emp_id) as cnt
    FROM employees e
    JOIN attendance_logs al ON e.emp_id = al.emp_id AND al.date = '$filter_date' AND al.clock_in IS NOT NULL AND (al.clock_out IS NULL OR al.clock_out = '0000-00-00 00:00:00') AND al.status != 'A'
    LEFT JOIN leave_requests lr ON e.emp_id = lr.emp_id AND lr.status = 'approved' AND lr.start_date <= '$filter_date' AND lr.end_date >= '$filter_date'
    LEFT JOIN lunch_breaks lb ON e.emp_id = lb.employee_id AND lb.status = 'on_break'
    LEFT JOIN short_breaks sb ON e.emp_id = sb.emp_id AND sb.status = 'on_break'
    WHERE lr.id IS NULL AND lb.break_id IS NULL AND sb.id IS NULL
    $gl_filter_sql
"))['cnt'];

$absent_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM attendance_logs al JOIN employees e ON al.emp_id = e.emp_id WHERE al.date = '$filter_date' AND al.status = 'A' $gl_filter_sql"))['cnt'];
$leave_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT lr.emp_id) as cnt FROM leave_requests lr JOIN employees e ON lr.emp_id = e.emp_id WHERE lr.status = 'approved' AND lr.start_date <= '$filter_date' AND lr.end_date >= '$filter_date' $gl_filter_sql"))['cnt'];

$not_clocked_in_count = $total_employees - $available_count - $on_break_count - $absent_count - $leave_count;
if ($not_clocked_in_count < 0) $not_clocked_in_count = 0;

// MANPOWER DATA: All employees with their current break status
$manpower_sql = "
    SELECT 
        e.id,
        e.emp_id, 
        e.name, 
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
    WHERE 1=1 $dept_filter_sql $emp_filter_sql
    ORDER BY (lb.status = 'on_break' OR sb.status = 'on_break') DESC, e.name ASC
";
$manpower_result = mysqli_query($conn, $manpower_sql);

// TODAY'S COMPLETED BREAKS
$today_breaks = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lunch_breaks WHERE status = 'returned' AND DATE(break_start) = CURDATE()"))['cnt'];
// 1. Average Break Time by Employment Type
$avg_sql = "SELECT e.employment_type, AVG(TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end)) as avg_duration
            FROM lunch_breaks lb JOIN employees e ON lb.employee_id = e.emp_id
            WHERE lb.status = 'returned' AND DATE(lb.break_start) = CURDATE()
            GROUP BY e.employment_type";
$avg_result = mysqli_query($conn, $avg_sql);
$chart_labels = [];
$chart_data = [];
while ($r = mysqli_fetch_assoc($avg_result)) {
    $chart_labels[] = $r['employment_type'];
    $chart_data[] = round($r['avg_duration']);
}

// 2. Violations over last 7 days
$vio_sql = "SELECT DATE(lb.break_start) as break_date, COUNT(*) as count
            FROM lunch_breaks lb 
            WHERE lb.status = 'returned' AND TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) > 60
            AND DATE(lb.break_start) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(lb.break_start) ORDER BY break_date ASC";
$vio_result = mysqli_query($conn, $vio_sql);
$vio_labels = [];
$vio_data = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $vio_labels[] = date('M d', strtotime($d));
    $vio_data[$d] = 0;
}
while ($r = mysqli_fetch_assoc($vio_result)) {
    $vio_data[$r['break_date']] = $r['count'];
}
$vio_data_values = array_values($vio_data);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Team Leader Dashboard</title>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #f8fafc; color: #334155; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #f8fafc; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; }
        .page-title { font-size: 24px; font-weight: 700; color: #0f172a; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .btn-primary { background-color: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-danger { background-color: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .btn-secondary { background-color: #e2e8f0; color: #64748b; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: not-allowed; font-size: 14px; }
        .avatar { width: 35px; height: 35px; background-color: #1e293b; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .content { padding: 0 40px 40px 40px; display: flex; flex-direction: column; gap: 20px; }

        .info-cards { display: flex; gap: 20px; }
        .info-card { flex: 1; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 15px; position: relative; }
        .info-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .info-icon svg { width: 20px; height: 20px; }
        .info-text { flex: 1; }
        .info-label { font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { font-size: 18px; font-weight: 700; }
        .info-subtext { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .info-action { position: absolute; right: 20px; top: 20px; font-size: 13px; color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .info-action:hover { color: #64748b; }

        .action-cards { display: flex; gap: 20px; }
        .action-card { flex: 1; background: #ffffff; border-radius: 16px; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; position: relative; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
        .lunch-card { border: 1px solid #fee2e2; border-left: 4px solid #ef4444; }
        .break-card { border: 1px solid #e9d5ff; border-left: 4px solid #a855f7; box-shadow: 0 4px 12px rgba(168, 85, 247, 0.05); }

        .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; border-radius: 20px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 30px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; }
        .dot.green { background-color: #22c55e; }
        .dot.yellow { background-color: #eab308; }

        .timer-circle { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .timer-circle.red { background: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; }
        .timer-circle.purple { background: #faf5ff; border: 1px solid #f3e8ff; color: #a855f7; }
        .timer-circle svg { width: 32px; height: 32px; }

        .timer-display { font-size: 48px; font-weight: 800; color: #0f172a; line-height: 1; margin-bottom: 8px; }
        .timer-subtext { font-size: 14px; color: #64748b; font-weight: 500; margin-bottom: 30px; }

        .action-form { width: 100%; max-width: 400px; display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .btn-action { width: 100%; padding: 16px; border-radius: 12px; font-size: 16px; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; }
        .btn-action:disabled { background-color: #e2e8f0 !important; color: #94a3b8 !important; cursor: not-allowed; }
        .meal-out { background-color: #ef4444; color: white; }
        .meal-out:hover { background-color: #dc2626; }
        .meal-in { background-color: #10b981; color: white; }
        .meal-in:hover { background-color: #059669; }
        
        .break-out { background-color: #a855f7; color: white; }
        .break-out:hover { background-color: #9333ea; }
        .break-in { background-color: #10b981; color: white; }
        .break-in:hover { background-color: #059669; }

        .warning-text { color: #ef4444; font-size: 12px; font-weight: 600; margin-top: 5px; }
        .card-footer-link { margin-top: 20px; }
        .card-footer-link a { color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .card-footer-link a:hover { color: #475569; }

        .bottom-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
        .btn-leave { background-color: #e2e8f0; color: #0f172a; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-leave:hover { background-color: #cbd5e1; }
        
        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal { background: #fffcf0; border: 2px dashed #f59e0b; border-radius: 16px; padding: 30px; width: 90%; max-width: 400px; position: relative; text-align: center; }
        .modal-close { position: absolute; top: 15px; right: 15px; font-size: 20px; font-weight: bold; color: #78350f; cursor: pointer; background: none; border: none; }
        .modal-title { color: #d97706; font-size: 20px; font-weight: 700; margin-bottom: 5px; }
        .modal-subtitle { color: #92400e; font-size: 13px; margin-bottom: 20px; line-height: 1.4; font-weight: 500; }
        .modal-input { width: 100%; padding: 12px; border: 1px solid #fcd34d; border-radius: 8px; margin-bottom: 15px; font-size: 14px; color: #451a03; background: #ffffff; outline: none; }
        .modal-input:focus { border-color: #f59e0b; }
        .btn-modal-orange { width: 100%; padding: 14px; background: #f59e0b; color: white; font-weight: 700; border: none; border-radius: 8px; margin-bottom: 10px; cursor: pointer; font-size: 15px; transition: 0.2s; }
        .btn-modal-orange:hover { background: #d97706; }
        .btn-modal-red { width: 100%; padding: 14px; background: #ef4444; color: white; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; transition: 0.2s; }
        .btn-modal-red:hover { background: #dc2626; }
        
        /* Dropdown Styles */
        .dropdown-container { position: relative; display: inline-block; margin-right: 8px; }
        .dropdown-btn { background-color: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 6px; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: #ffffff; min-width: 160px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); border-radius: 8px; z-index: 100; overflow: hidden; border: 1px solid #e2e8f0; margin-top: 4px; }
        .dropdown-container:hover .dropdown-content { display: block; }
        .dropdown-item { padding: 12px 16px; text-decoration: none; display: block; text-align: left; cursor: pointer; border: none; width: 100%; background: none; font-size: 14px; font-weight: 600; color: #334155; transition: 0.2s; }
        .dropdown-item:hover { background-color: #f1f5f9; }
        .dropdown-item.text-blue { color: #3b82f6; }
        .dropdown-item.text-red { color: #ef4444; }
        
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content, body.dark-mode .topbar { background-color: #0f172a; }
        body.dark-mode .page-title, body.dark-mode .timer-display { color: #f8fafc; }
        body.dark-mode .info-card, body.dark-mode .action-card { background-color: #1e293b; border-color: #334155; }
        body.dark-mode .lunch-card { border-color: #334155; border-left-color: #ef4444; }
        body.dark-mode .break-card { border-color: #334155; border-left-color: #a855f7; }
        body.dark-mode .status-pill { background-color: #0f172a; border-color: #334155; }
        body.dark-mode .timer-circle.red { background-color: #450a0a; border-color: #7f1d1d; }
        body.dark-mode .timer-circle.purple { background-color: #3b0764; border-color: #581c87; }
        body.dark-mode .btn-secondary { background-color: #334155; color: #94a3b8; }
        body.dark-mode .btn-action:disabled { background-color: #334155 !important; color: #64748b !important; }
        
        body.dark-mode .modal { background: #1e293b; border-color: #d97706; }
        body.dark-mode .modal-title { color: #fbbf24; }
        body.dark-mode .modal-subtitle { color: #94a3b8; }
        body.dark-mode .modal-close { color: #94a3b8; }
        body.dark-mode .modal-input { background: #0f172a; border-color: #475569; color: #f8fafc; }
        body.dark-mode .modal-input:focus { border-color: #fbbf24; }
        body.dark-mode .dropdown-menu { background-color: #1e293b; border-color: #334155; }
        body.dark-mode .dropdown-item { color: #f8fafc; }
        body.dark-mode .dropdown-item:hover { background-color: #334155; }
        body.dark-mode .modal, body.dark-mode .modal-box { background-color: #1e293b !important; border-color: #334155 !important; }
        body.dark-mode .modal h2, body.dark-mode .modal-box h2 { color: #60a5fa !important; }
        body.dark-mode .modal label, body.dark-mode .modal-box label { color: #cbd5e1 !important; }
        body.dark-mode .modal input, body.dark-mode .modal select, body.dark-mode .modal-box input, body.dark-mode .modal-box select { background-color: #0f172a !important; color: #f8fafc !important; border-color: #475569 !important; }
        body.dark-mode .modal .bg-amber-100, body.dark-mode .modal-box .bg-amber-100 { background-color: #451a03 !important; color: #fcd34d !important; }
    
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .stat-card { background: #ffffff; padding: 20px 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto; column-gap: 16px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .table-container { background: transparent; padding: 10px 0; }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .live-badge { display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; color: #059669; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 16px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 20px; background: #ffffff; color: #334155; transition: 0.2s; position: relative; }
        tr.clickable:hover td { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.08); cursor: pointer; z-index: 10; }
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-block; }
        .bg-on-break { background-color: #fef3c7; color: #d97706; }
        .bg-available { background-color: #ecfdf5; color: #059669; }
        .elapsed-text { font-size: 13px; font-weight: 600; color: #94a3b8; }
        .elapsed-text.overtime { color: #ef4444; }
        .modal-box { background: rgba(255,255,255,0.95); padding: 40px; border-radius: 24px; width: 440px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5); text-align: center; position: relative; animation: modalSlideIn 0.3s ease; }
        .close-btn { position: absolute; top: 20px; right: 25px; font-size: 28px; cursor: pointer; color: #94a3b8; transition: 0.2s; background: none; border: none; }
        .close-btn:hover { color: #ef4444; }
        .profile-pic { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; margin: 0 auto 15px auto; }
        .modal-btn { background-color: #ef4444; color: white; border: none; padding: 14px 20px; border-radius: 12px; cursor: pointer; margin-top: 25px; font-weight: 700; width: 100%; font-size: 15px; transition: 0.2s; }
        .modal-btn:hover { background-color: #dc2626; transform: translateY(-2px); }
        .modal-btn:disabled { background-color: #cbd5e1; cursor: not-allowed; transform: none; }
        .info-block { background: #f8fafc; padding: 20px; border-radius: 16px; text-align: left; margin-top: 20px; }
        .info-value { font-weight: 600; }
</style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    </head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Welcome back, <?php echo htmlspecialchars($employee_name); ?> 👋</div>
            <div class="topbar-right">
                <?php if (!$has_clocked_in || $has_clocked_out): ?>
                    <form method="POST" action="" style="display:inline; margin-right: 8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="clock_in" class="btn-primary">
                            <?php echo $has_clocked_out ? 'Clock In Again' : 'Clock In (Start Day)'; ?>
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="" style="display:inline; margin-right: 8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <?php if (!$has_clocked_out): ?>
                            <?php 
                            if ($is_early_clockout && $has_early_leave) {
                                $onclick = "return confirm('You are clocking out early due to your approved Half-Day/Emergency Leave. Are you sure?');";
                            } else {
                                $onclick = "return confirm('Are you sure you want to clock out for today?');";
                            }
                            ?>
                            <button type="submit" name="clock_out" class="btn-primary text-red-500" onclick="<?php echo $onclick; ?>">
                                Clock Out
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
                <a href="profile.php" class="avatar" style="text-decoration: none;"><?php echo strtoupper(substr($employee_name, 0, 1)); ?></a>
            </div>
        </header>

        <div class="content">
            <?php if ($status_message): ?>
                <div class="bg-blue-50" style="border: 1px solid #bfdbfe; color: #1d4ed8; padding: 14px 20px; border-radius: 12px; font-weight: 600;">
                    🔔 <?php echo $status_message; ?>
                </div>
            <?php endif; ?>

            <div class="info-cards">
                <div class="info-card" style="border-left: 4px solid #3b82f6;">
                    <div class="info-icon" style="background: #e0f2fe; color: #3b82f6;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Your Assigned Lunchtime Window</div>
                        <div class="info-value" style="color: #3b82f6;"><?php echo htmlspecialchars($lunch_window); ?></div>
                    </div>

                </div>
                
                <div class="info-card" style="border-left: 4px solid #f97316;">
                    <div class="info-icon" style="background: #ffedd5; color: #f97316;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Assigned Shift Hour</div>
                        <div class="info-value" style="color: #f97316;"><?php echo htmlspecialchars($shift_hour); ?></div>
                    </div>
                </div>

                <div class="info-card" style="border-left: 4px solid #10b981;">
                    <div class="info-icon" style="background: #ecfdf5; color: #10b981;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Today's Attendance</div>
                        <div class="info-value" style="color: #10b981; font-size: 14px;">
                            <?php 
                                $ci = !empty($today_att['clock_in']) ? date('h:i A', strtotime($today_att['clock_in'])) : '--:--';
                                $co = !empty($today_att['clock_out']) ? date('h:i A', strtotime($today_att['clock_out'])) : '--:--';
                                echo "IN: $ci <br> OUT: $co";
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-cards">
                <!-- Lunch Break Card -->
                <div class="action-card lunch-card">
                    <div class="status-pill">
                        <span class="dot <?php echo $is_on_break ? 'yellow' : 'green'; ?>"></span> 
                        <?php echo $is_on_break ? 'On Lunch Break' : 'You are Available'; ?>
                    </div>
                    
                    <div class="timer-circle red">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    
                    <?php 
                        $total_lunch_sec = $total_lunch_limit * 60;
                        $remaining_sec = $total_lunch_sec - $previous_breaks_elapsed_sec;
                        if ($is_on_break) {
                            $remaining_sec -= $current_break_info['elapsed_sec'];
                        }
                        $remaining_sec = max(0, $remaining_sec);
                        $rem_min = floor($remaining_sec / 60);
                        $rem_sec = $remaining_sec % 60;
                    ?>
                    <div class="timer-display" id="lunch-timer" data-seconds="<?php echo $remaining_sec; ?>" data-active="<?php echo $is_on_break ? '1' : '0'; ?>"><?php echo sprintf("%02d:%02d", $rem_min, $rem_sec); ?></div>
                    <div class="timer-subtext">Remaining out of <?php echo $total_lunch_limit; ?> min</div>
                    
                    <form method="POST" action="" class="action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <?php if ($is_on_break): ?>
                            <button type="submit" name="end_break" class="btn-action meal-in">Meal In</button>
                        <?php else: ?>
                            <?php 
                                $can_meal_out = $has_clocked_in && !$has_clocked_out && ($is_valid_time || $has_override);
                            ?>
                            <button type="submit" name="start_break" class="btn-action meal-out" <?php echo !$can_meal_out ? 'disabled' : ''; ?>>Meal Out</button>
                            <?php if (!$is_valid_time && !$has_override): ?>
                                <div class="warning-text">You cannot Meal Out outside your assigned window.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                    
                    <div class="card-footer-link">
                        <a href="#" onclick="document.getElementById('overrideModal').style.display='flex'; return false;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Request / Force Start Override
                        </a>
                    </div>
                </div>

                <!-- Short Break Card -->
                <div class="action-card break-card">
                    <div class="status-pill">
                        <span class="dot <?php echo $is_on_short_break ? 'yellow' : 'green'; ?>"></span> 
                        <?php echo $is_on_short_break ? 'On Short Break' : 'You are Available'; ?>
                    </div>
                    
                    <div class="timer-circle purple">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    
                    <?php 
                        $total_sb_limit = 30; // 30 mins
                        $sb_usage_sql = "SELECT SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)) as total_sec FROM short_breaks WHERE emp_id = '$current_employee_id' AND DATE(break_start) = CURDATE() AND status = 'returned'";
                        $sb_usage_res = mysqli_fetch_assoc(mysqli_query($conn, $sb_usage_sql));
                        $used_sb_sec = $sb_usage_res['total_sec'] ? (int)$sb_usage_res['total_sec'] : 0;
                        
                        $remaining_sb_sec = ($total_sb_limit * 60) - $used_sb_sec;
                        if ($is_on_short_break) {
                            $check_sb_row = mysqli_fetch_assoc($check_sb_res);
                            $remaining_sb_sec -= $check_sb_row ? $check_sb_row['elapsed_sec'] : 0;
                        }
                        $remaining_sb_sec = max(0, $remaining_sb_sec);
                        $rem_sb_min = floor($remaining_sb_sec / 60);
                        $rem_sb_sec = $remaining_sb_sec % 60;
                    ?>
                    <div class="timer-display" id="short-break-timer" data-seconds="<?php echo $remaining_sb_sec; ?>" data-active="<?php echo $is_on_short_break ? '1' : '0'; ?>"><?php echo sprintf("%02d:%02d", $rem_sb_min, $rem_sb_sec); ?></div>
                    <div class="timer-subtext">Remaining Short Break Time</div>
                    
                    <form method="POST" action="" class="action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <?php if ($is_on_short_break): ?>
                            <button type="submit" name="end_short_break" class="btn-action break-in">Return</button>
                        <?php else: ?>
                            <button type="submit" name="start_short_break" class="btn-action break-out" <?php echo (!$has_clocked_in || $has_clocked_out) ? 'disabled' : ''; ?>>Take Short Break</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="bottom-actions">
                <a href="#" onclick="document.getElementById('leaveModal').style.display='flex'; return false;" class="btn-leave">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    Request Leave / Half-Day
                </a>
            </div>

        </div>
    
<!-- STAT CARDS -->
            <div class="stats-grid">
                <div class="premium-stat-card">
                    <div class="icon-box" style="color: #3b82f6; font-size: 24px;">👥</div>
                    <div class="info-area">
                        <div class="info-title">Total Employees</div>
                        <div class="info-value"><?php echo $total_employees; ?></div>
                    </div>
                </div>
                <div class="premium-stat-card">
                    <div class="icon-box text-amber-600" style=" font-size: 24px;">🍽️</div>
                    <div class="info-area">
                        <div class="info-title">Currently On Break</div>
                        <div class="info-value text-amber-600" ><?php echo $on_break_count; ?></div>
                    </div>
                </div>
                <div class="premium-stat-card">
                    <div class="icon-box text-emerald-600" style=" font-size: 24px;">✅</div>
                    <div class="info-area">
                        <div class="info-title">Available</div>
                        <div class="info-value text-emerald-600" ><?php echo $available_count; ?></div>
                    </div>
                </div>
                <div class="premium-stat-card">
                    <div class="icon-box text-green-600" style=" font-size: 24px;">📊</div>
                    <div class="info-area">
                        <div class="info-title">Meals Today</div>
                        <div class="info-value"><?php echo $today_breaks; ?></div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 350px 1fr; gap: 24px; align-items: start; margin-bottom: 30px;">
                <div id="card-availChart" class="stat-card" style="padding: 20px; height: 100%;">
                    <h3 style="font-size: 16px; margin-bottom: 15px;">Staff Availability</h3>
                    <div style="position: relative; height: 250px; width: 100%;">
                        <canvas id="availChart"></canvas>
                    </div>
                </div>
                
                <!-- MANPOWER TABLE -->
                <div id="manpower-section" class="table-container" style="padding: 0;">
                <div class="table-header">
                    <h3 class="table-title">Manpower Overview</h3>
                    <span class="live-badge"><span class="live-dot"></span> LIVE</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Status</th>
                            <th>Elapsed</th>
                            <th>Limit</th>
                            <th>Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($manpower_result) > 0) {
                            while ($row = mysqli_fetch_assoc($manpower_result)) {
                                $is_on_break = ($row['break_status'] === 'on_break');
                                $elapsed_text = '--';
                                $elapsed_class = 'elapsed-text';
                                $break_start_display = '--';

                                $shift_parts = explode('-', $row['shift_hour'] ?? '09:00 - 18:00');
                                $shift_start = trim($shift_parts[0]);
                                $late_status_display = '--';
                                $late_elapsed_display = '--';
                                $late_status_color = '#94a3b8';
                                if ($row['clock_in_time']) {
                                    $clock_in_dt = new DateTime($row['clock_in_time']);
                                    $clock_in_time_only = $clock_in_dt->format('H:i');
                                    $shift_start_dt = DateTime::createFromFormat('H:i', $shift_start);
                                    if (!$shift_start_dt) $shift_start_dt = DateTime::createFromFormat('H:i:s', $shift_start . ':00');
                                    if ($shift_start_dt) {
                                        if ($clock_in_time_only > $shift_start_dt->format('H:i')) {
                                            $late_status_display = 'Late';
                                            $late_status_color = '#ef4444';
                                            $clock_in_minutes = ((int)$clock_in_dt->format('H') * 60) + (int)$clock_in_dt->format('i');
                                            $shift_start_minutes = ((int)$shift_start_dt->format('H') * 60) + (int)$shift_start_dt->format('i');
                                            $late_mins = $clock_in_minutes - $shift_start_minutes;
                                            if ($late_mins > 0) $late_elapsed_display = $late_mins . ' min';
                                        } else {
                                            $late_status_display = 'On Time';
                                            $late_status_color = '#10b981';
                                            $late_elapsed_display = '0 min';
                                        }
                                    }
                                }

                                $total_override_mins = (int)$row['total_override_mins'];
                                $lb_limit_mins = 60 + $total_override_mins;
                                
                                $limit_text = "L: $lb_limit_mins | S: 30";
                                
                                $sb_elapsed_sec = (int)$row['total_sb_elapsed_sec'];
                                $lb_elapsed_sec = (int)$row['total_lb_elapsed_sec'];
                                
                                $sb_remaining_sec = (30 * 60) - $sb_elapsed_sec;
                                $lb_remaining_sec = ($lb_limit_mins * 60) - $lb_elapsed_sec;

                                $sb_remaining = floor($sb_remaining_sec / 60);
                                $lb_remaining = floor($lb_remaining_sec / 60);

                                $sb_elapsed = 30 - $sb_remaining;
                                $lb_elapsed = $lb_limit_mins - $lb_remaining;

                                if ($sb_remaining_sec < 0) {
                                    $sb_remaining = -floor(abs($sb_remaining_sec) / 60);
                                    $sb_elapsed = 30 + abs($sb_remaining);
                                }
                                if ($lb_remaining_sec < 0) {
                                    $lb_remaining = -floor(abs($lb_remaining_sec) / 60);
                                    $lb_elapsed = $lb_limit_mins + abs($lb_remaining);
                                }
                                
                                $rem_text = "";
                                if ($lb_remaining < 0) $rem_text .= "L: <span style='color: #ef4444'>" . abs($lb_remaining) . "m over</span> | ";
                                else $rem_text .= "L: " . $lb_remaining . "m | ";
                                
                                if ($sb_remaining < 0) $rem_text .= "S: <span style='color: #ef4444'>" . abs($sb_remaining) . "m over</span>";
                                else $rem_text .= "S: " . $sb_remaining . "m";

                                if ($is_on_break && $row['break_start']) {
                                    $break_start_display = date('h:i A', strtotime($row['break_start']));
                                    if ($row['break_type'] === 'Short Break') {
                                        $elapsed_text = $sb_elapsed . ' min';
                                        if ($sb_elapsed >= 30) $elapsed_class = 'elapsed-text overtime';
                                    } else {
                                        $elapsed_text = $lb_elapsed . ' min';
                                        if ($lb_elapsed >= $lb_limit_mins) $elapsed_class = 'elapsed-text overtime';
                                    }
                                }

                                $type_colors = [
                                    'Full time' => ['bg' => '#ecfdf5', 'text' => '#059669'],
                                    'Intern' => ['bg' => '#fdf4ff', 'text' => '#a855f7'],
                                ];
                                $color = $type_colors[$row['employment_type']] ?? ['bg' => '#f1f5f9', 'text' => '#475569'];

                                $elapsed_minutes = ($row['break_type'] === 'Short Break') ? $sb_elapsed : $lb_elapsed;
                                $limit_arg = ($row['break_type'] === 'Short Break') ? 30 : $lb_limit_mins;
                                $onclick = $is_on_break 
                                    ? "onclick=\"openFloatingBox('" . htmlspecialchars($row['name'], ENT_QUOTES) . "', '" . htmlspecialchars($row['employment_type'], ENT_QUOTES) . "', '$break_start_display', '$elapsed_minutes', '" . htmlspecialchars($row['emp_id'], ENT_QUOTES) . "', '" . htmlspecialchars($row['break_type'], ENT_QUOTES) . "', " . $limit_arg . ")\"" 
                                    : "";
                                $row_class = $is_on_break ? "class='clickable'" : "";

                                $has_clocked_out = !empty($row['clock_out_time']) && strpos($row['clock_out_time'], '0000-00-00') === false;
                                $row_status = '';
                                if ($row['leave_status']) {
                                    $row_status = 'On Leave';
                                } else if ($row['absent_status'] === 'A') {
                                    $row_status = 'Absent';
                                } else if (empty($row['clock_in_time'])) {
                                    $row_status = 'Not Clocked In';
                                } else if ($has_clocked_out) {
                                    $shift_parts_end = explode('-', $row['shift_hour'] ?? '09:00 - 18:00');
                                    $shift_end = trim($shift_parts_end[1] ?? '18:00');
                                    $clock_out_dt = new DateTime($row['clock_out_time']);
                                    $clock_out_time_only = $clock_out_dt->format('H:i');
                                    $shift_end_dt = DateTime::createFromFormat('H:i', $shift_end);
                                    if (!$shift_end_dt) $shift_end_dt = DateTime::createFromFormat('H:i:s', $shift_end . ':00');
                                    if ($shift_end_dt && $clock_out_time_only < $shift_end_dt->format('H:i')) {
                                        $row_status = 'Left Early';
                                    } else {
                                        $row_status = 'Unavailable';
                                    }
                                } else if ($is_on_break) {
                                    $row_status = 'On Break';
                                } else {
                                    $row_status = 'Available';
                                }

                                echo "<tr $row_class $onclick data-status='" . htmlspecialchars($row_status) . "'>";
                                echo "<td style='font-weight: 600; color: #0f172a;'>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td><span style='background: {$color['bg']}; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: {$color['text']};'>" . htmlspecialchars($row['employment_type']) . "</span></td>";
                                
                                if ($row['leave_status']) {
                                    echo "<td><span class='badge' style='background:#fef2f2; color:#b91c1c;'>On Leave</span></td>";
                                } else if ($row['absent_status'] === 'A') {
                                    echo "<td><span class='badge' style='background:#fef2f2; color:#ef4444;'>Absent</span></td>";
                                } else if (empty($row['clock_in_time'])) {
                                    echo "<td><span class='badge' style='background:#f1f5f9; color:#64748b;'>Not Clocked In</span></td>";
                                } else if ($has_clocked_out) {
                                    if ($row_status === 'Left Early') {
                                        echo "<td><span class='badge' style='background:#fef3c7; color:#d97706;'>Left Early</span></td>";
                                    } else {
                                        echo "<td><span class='badge' style='background:#e2e8f0; color:#475569;'>Unavailable</span></td>";
                                    }
                                } else if ($is_on_break) {
                                    echo "<td><span class='badge bg-on-break'>" . htmlspecialchars($row['break_type']) . "</span></td>";
                                } else {
                                    echo "<td><span class='badge bg-available'>Available</span></td>";
                                }
                                
                                $c_in_display = $row['clock_in_time'] ? date('h:i A', strtotime($row['clock_in_time'])) : '--';
                                $c_out_display = $row['clock_out_time'] ? date('h:i A', strtotime($row['clock_out_time'])) : '--';
                                
                                echo "<td style='font-size: 13px; font-weight: 600; color: #475569;'>" . $c_in_display . "</td>";
                                echo "<td style='font-size: 13px; font-weight: 600; color: #475569;'>" . $c_out_display . "</td>";
                                
                                echo "<td><span class='badge' style='background: " . str_replace('#', 'rgba(', $late_status_color) . ",0.1); color: " . $late_status_color . ";'>" . $late_status_display . "</span></td>";
                                echo "<td><span class='elapsed-text' style='color: " . ($late_status_display === 'Late' ? '#ef4444' : '#94a3b8') . "; font-weight: 700;'>" . $late_elapsed_display . "</span></td>";
                                echo "<td><span class='elapsed-text'>" . $limit_text . "</span></td>";
                                echo "<td><span class='elapsed-text'>" . $rem_text . "</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align: center; padding: 60px; color: #94a3b8; font-weight: 600;'>No employees registered yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            </div> <!-- End of Grid -->
        </div>
    
</main>

    <!-- EMPLOYEE BREAK DETAIL MODAL -->
    <div id="employeeModal" class="modal-overlay">
        <div class="modal-box">
            <button class="close-btn" onclick="closeFloatingBox()">&times;</button>
            <div class="profile-pic bg-amber-100 text-amber-600" id="modalAvatar" >K</div>
            <h2 id="modalName" class="text-slate-900" style=" margin-bottom: 5px;">Employee Name</h2>
            <p id="modalDept" class="text-slate-500" style=" font-weight: 500; margin-bottom: 0;">Department</p>
            
            <div class="info-block">
                <div class="info-row">
                    <span class="info-label">Lunchtime Started</span>
                    <span class="info-value" id="modalTime">--:--</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time Elapsed</span>
                    <span class="info-value text-amber-600" id="modalElapsed" >-- min</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value text-amber-600"  id="modalBreakType">🍽️ On Break</span>
                </div>
            </div>

            <form method="POST" action="" id="forceEndForm">
                <input type="hidden" name="break_employee_id" id="modalEmpId">
                <button type="submit" name="force_end_break" class="modal-btn">Force Stop</button>
            </form>
        </div>
    </div>

    
<!-- Override Modal -->
    <div class="modal-overlay" id="overrideModal">
        <div class="modal">
            <button class="modal-close" onclick="document.getElementById('overrideModal').style.display='none'">×</button>
            <h2 class="modal-title">Force Start / Override</h2>
            <p class="modal-subtitle">You missed your assigned lunch window. Provide a reason to request a manager override.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="request_override" value="1">
                
                <select name="req_category" class="modal-input" required>
                    <option value="" disabled selected>Select Override Type</option>
                    <option value="Meeting">Overrunning meeting</option>
                    <option value="Personal">Personal Emergency</option>
                    <option value="Workload">Heavy Workload</option>
                    <option value="Other">Other</option>
                </select>
                
                <input type="text" name="reason" class="modal-input" placeholder="e.g. Overrunning meeting with client" required>
                
                <input type="number" name="duration" class="modal-input" placeholder="Extra time in mins (put 0 if just going early)" required min="0">
                
                <button type="submit" class="btn-modal-orange">Submit Request Only</button>
                <button type="submit" name="force_start" value="1" class="btn-modal-red">Request & Force Start Now</button>
            </form>
        </div>
    </div>
    
    <!-- Leave Modal -->
    <div class="modal-overlay" id="leaveModal">
        <div class="modal" style="background-color: #fffdf4; border: 2px dashed #3b82f6; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 30px; position: relative; max-width: 700px;">
            <button onclick="document.getElementById('leaveModal').style.display='none'" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 20px; color: #9a3412; cursor: pointer; font-weight: bold;">×</button>
            <h2 style="color: #3b82f6; font-size: 22px; font-weight: 700; text-align: center; margin-bottom: 8px;">Leave Application</h2>
            <div class="bg-amber-100" style="color: #9a3412; font-size: 13px; text-align: left; font-weight: 500; margin-bottom: 25px; line-height: 1.5;  padding: 12px; border-radius: 8px;">
                1. Submit your leave application 14 days in advance through this form.<br>
                2. Ensure all required details are completed accurately. Incomplete application will be immediately rejected.<br>
                3. Sick leave, unpaid leave, annual leave will be applied as per usual using this form. (Max 5 employees company-wide on Annual Leave per day).<br>
                4. Notify HR upon applying.
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" style="text-align: left;" id="leaveModalForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <!-- Row 1: Name & App Date -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Full Name of Employee</label>
                        <input type="text" value="<?php echo htmlspecialchars($employee_name); ?>" readonly style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; background: #f8fafc; color: #64748b; outline: none;">
                    </div>
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Application Date</label>
                        <input type="text" value="<?php echo date('m/d/Y'); ?>" readonly style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; background: #f8fafc; color: #64748b; outline: none;">
                    </div>
                    
                    <!-- Row 2: Leave Type & Duration -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Type of Leave</label>
                        <select name="leave_type" id="modal_leave_type" required onchange="toggleLeaveModalFields('type')" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2352525b\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px;">
                            <option value="" disabled selected>Please select</option>
                            <?php if (strtolower($employment_type) !== 'intern'): ?>
                            <option value="Annual Leave" <?php echo ($al_bal <= 0) ? 'disabled' : ''; ?>>Annual Leave <?php echo ($al_bal <= 0) ? '(Quota Depleted)' : ''; ?></option>
                            <option value="Sick Leave" <?php echo ($sl_bal <= 0) ? 'disabled' : ''; ?>>Sick Leave <?php echo ($sl_bal <= 0) ? '(Quota Depleted)' : ''; ?></option>
                            <option value="Emergency Leave" <?php echo ($al_bal <= 0) ? 'disabled' : ''; ?>>Emergency Leave <?php echo ($al_bal <= 0) ? '(Quota Depleted)' : ''; ?></option>
                            <option value="Hospitalization Leave">Hospitalization Leave</option>
                            <?php endif; ?>
                            <option value="Unpaid Leave">Unpaid Leave</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Duration of Leave</label>
                        <input type="number" name="duration" id="modal_duration" step="0.5" min="0.5" oninput="toggleLeaveModalFields('duration')" placeholder="e.g. 1.5" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>

                    <!-- Row 3: Start Date & End Date -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Date of Leave (Start)</label>
                        <input type="text" name="start_date" id="modal_start_date" placeholder="Select Date" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>
                    <div id="modal_end_date_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Date of Leave (End)</label>
                        <input type="text" name="end_date" id="modal_end_date" placeholder="Select Date" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>

                    <!-- Row 3.5: Start Time & End Time -->
                    <div id="modal_start_time_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;">Time of Leave (Start)</label>
                        <input type="time" name="start_time" id="modal_start_time" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>
                    <div id="modal_end_time_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;">Time of Leave (End)</label>
                        <input type="time" name="end_time" id="modal_end_time" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>
                    
                    <!-- Row 4: Reason (Full Width) -->
                    <div style="grid-column: 1 / -1;">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Reason for Leave</label>
                        <input type="text" name="reason" placeholder="Type here" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>

                    <!-- Row 5: Document Upload (Full Width) -->
                    <div id="modal_mc_upload_group" style="grid-column: 1 / -1;">
                        <label class="text-slate-500" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 4px;">Supporting Document</label>
                        <p style="font-size: 11px; color: #94a3b8; margin-bottom: 8px;">Employees applying for sick leave are required to submit a valid Medical Certificate (MC) issued and signed by a registered Malaysia medical practitioner.</p>
                        <div class="bg-white" style="border: 1px dashed #fbbf24; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; position: relative;" ondragover="event.preventDefault();" ondrop="event.preventDefault(); this.querySelector('input').files = event.dataTransfer.files; this.querySelector('input').dispatchEvent(new Event('change'));">
                            <span class="text-amber-600" style=" font-size: 13px; font-weight: 600;">Paste or drag files here (or click to upload)</span>
                            <input type="file" name="medical_certificate" accept=".jpg,.jpeg,.png,.pdf" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer; z-index: 10;" onchange="this.previousElementSibling.textContent = this.files[0] ? this.files[0].name : 'Paste or drag files here (or click to upload)';">
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="submit_leave" class="text-white" style="width: 100%; background: #3b82f6;  padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s;">Submit Application</button>
            </form>
        </div>
    </div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Inject PHP variables into JS for dynamic time calculations
        const shiftStart = '<?php $parts = explode("-", $shift_hour); echo trim($parts[0] ?? "09:00"); ?>';
        const shiftEnd = '<?php $parts = explode("-", $shift_hour); echo trim($parts[1] ?? "18:00"); ?>';
        const lunchStart = '<?php $parts = explode("-", $lunch_window); echo trim($parts[0] ?? "12:00"); ?>';
        const lunchEnd = '<?php $parts = explode("-", $lunch_window); echo trim($parts[1] ?? "13:00"); ?>';

        function toggleLeaveModalFields(source = null) {
            const type = document.getElementById('modal_leave_type').value;
            const endGroup = document.getElementById('modal_end_date_group');
            const endInput = document.getElementById('modal_end_date');
            
            const startTimeInput = document.getElementById('modal_start_time');
            const endTimeInput = document.getElementById('modal_end_time');
            const durationInput = document.getElementById('modal_duration');

            endGroup.style.display = 'block';
            endInput.required = true;

            if (type === 'Emergency Leave' || type === 'Late In' || parseFloat(durationInput.value) <= 1) {
                endGroup.style.display = 'none';
                endInput.required = false;
            }

            const alBal = <?php echo $al_bal ?? 0; ?>;
            const slBal = <?php echo $sl_bal ?? 0; ?>;
            const startDateInput = document.getElementById('modal_start_date');
            
            if (type === 'Annual Leave') {
                durationInput.max = alBal;
                let d = new Date();
                d.setDate(d.getDate() + 14);
                let minDateStr = d.toISOString().split('T')[0];
                if (startDateInput) startDateInput.min = minDateStr;
                if (endInput) endInput.min = minDateStr;
                // Update Flatpickr minDate
                if (typeof fpStart !== 'undefined') fpStart.set('minDate', minDateStr);
                if (typeof fpEnd !== 'undefined') fpEnd.set('minDate', minDateStr);
            } else if (type === 'Emergency Leave') {
                durationInput.max = alBal;
                if (startDateInput) startDateInput.removeAttribute('min');
                if (endInput) endInput.removeAttribute('min');
                if (typeof fpStart !== 'undefined') fpStart.set('minDate', null);
                if (typeof fpEnd !== 'undefined') fpEnd.set('minDate', null);
            } else if (type === 'Sick Leave') {
                durationInput.max = slBal;
                if (startDateInput) startDateInput.removeAttribute('min');
                if (endInput) endInput.removeAttribute('min');
                if (typeof fpStart !== 'undefined') fpStart.set('minDate', null);
                if (typeof fpEnd !== 'undefined') fpEnd.set('minDate', null);
            } else {
                durationInput.removeAttribute('max');
                if (startDateInput) startDateInput.removeAttribute('min');
                if (endInput) endInput.removeAttribute('min');
            }

            // Automate Time and Duration
            if (source === 'type') {
                if (type === 'Annual Leave' || type === 'Sick Leave' || type === 'Unpaid Leave') {
                    startTimeInput.value = shiftStart;
                    endTimeInput.value = shiftEnd;
                    if (startDateInput && startDateInput.value) {
                        if (typeof calculateDuration === 'function') calculateDuration();
                    } else {
                        durationInput.value = '1';
                    }
                } else {
                    startTimeInput.value = '';
                    endTimeInput.value = '';
                    durationInput.value = '';
                }
            }
        }

        // Live Countdown Logic for Active Breaks
        setInterval(function() {
            document.querySelectorAll('.timer-display[data-active="1"]').forEach(function(el) {
                let sec = parseInt(el.getAttribute('data-seconds'));
                if (sec > 0) {
                    sec--;
                    el.setAttribute('data-seconds', sec);
                    let m = Math.floor(sec / 60);
                    let s = sec % 60;
                    el.innerText = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
                }
            });
        }, 1000);
    </script>

    <?php if (date('N') == 5 && $has_clocked_in && !$has_clocked_out): ?>
    <!-- Friday Reminder Popup -->
    <div id="friday-reminder" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:20px; padding:40px 36px; max-width:420px; width:90%; text-align:center; box-shadow:0 25px 60px rgba(0,0,0,0.2); position:relative;">
            <div style="font-size:48px; margin-bottom:16px;">📅</div>
            <h2 style="font-size:22px; font-weight:800; color:#0f172a; margin-bottom:10px;">It's Friday! 🎉</h2>
            <p style="font-size:14px; color:#64748b; line-height:1.7; margin-bottom:24px;">
                Don't forget &mdash; today is the only day you can request a <strong>Shift Hour</strong> or <strong>Lunchtime Window</strong> change.
                <br><br>Head to your <strong>Profile &amp; Settings</strong> if you'd like to make a request!
            </p>
            <a href="profile.php" style="display:inline-block; background:#3b82f6; color:#fff; padding:12px 28px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; margin-right:10px;">Go to Profile</a>
            <button onclick="dismissFridayReminder()" style="background:#f1f5f9; color:#475569; padding:12px 28px; border-radius:10px; font-weight:700; font-size:14px; border:none; cursor:pointer;">Dismiss</button>
        </div>
    </div>
    <script>
        function dismissFridayReminder() {
            document.getElementById('friday-reminder').style.display = 'none';
            sessionStorage.setItem('friday_reminded', '1');
        }
    </script>
    <?php endif; ?>

    <script>
        // Flatpickr Initialization
        const publicHolidays = <?php
            $ph_result = mysqli_query($conn, "SELECT holiday_date FROM public_holidays ORDER BY holiday_date ASC");
            $ph_dates = [];
            while ($ph_row = mysqli_fetch_assoc($ph_result)) {
                $ph_dates[] = $ph_row['holiday_date'];
            }
            echo json_encode($ph_dates);
        ?>;

        function calculateDuration() {
            const start = document.getElementById('modal_start_date').value;
            let end = document.getElementById('modal_end_date').value;
            const durationInput = document.getElementById('modal_duration');
            
            if (!start) return;
            if (document.getElementById('modal_end_date_group').style.visibility === 'hidden' || document.getElementById('modal_end_date_group').style.display === 'none') {
                end = start;
            } else if (!end) {
                return;
            }

            let startDate = new Date(start);
            let endDate = new Date(end);
            
            if (endDate < startDate) {
                durationInput.value = '';
                return;
            }

            let days = 0;
            let current = new Date(startDate);
            while (current <= endDate) {
                let dayOfWeek = current.getDay();
                let dateString = current.getFullYear() + '-' + 
                                 String(current.getMonth() + 1).padStart(2, '0') + '-' + 
                                 String(current.getDate()).padStart(2, '0');
                
                if (dayOfWeek !== 0 && dayOfWeek !== 6 && !publicHolidays.includes(dateString)) {
                    days++;
                }
                current.setDate(current.getDate() + 1);
            }
            
            durationInput.value = days;
        }

        const fpConfig = {
            dateFormat: "Y-m-d",
            disable: [
                function(date) {
                    // Disable Saturday (6) and Sunday (0)
                    if (date.getDay() === 0 || date.getDay() === 6) {
                        return true;
                    }
                    // Disable Public Holidays
                    const dateString = date.getFullYear() + '-' + 
                                     String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                                     String(date.getDate()).padStart(2, '0');
                    return publicHolidays.includes(dateString);
                }
            ],
            onChange: function(selectedDates, dateStr, instance) {
                calculateDuration();
            }
        };

        const fpStart = flatpickr("#modal_start_date", fpConfig);
        const fpEnd = flatpickr("#modal_end_date", fpConfig);


        if (!sessionStorage.getItem('friday_reminded') && document.getElementById('friday-reminder')) {
            document.getElementById('friday-reminder').style.display = 'flex';
        }

        <?php if (!empty($status_message)): ?>
            if ("Notification" in window) {
                if (Notification.permission === "granted") {
                    new Notification("WeRide Status Update", { body: <?php echo json_encode($status_message); ?> });
                } else if (Notification.permission !== "denied") {
                    Notification.requestPermission().then(permission => {
                        if (permission === "granted") {
                            new Notification("WeRide Status Update", { body: <?php echo json_encode($status_message); ?> });
                        }
                    });
                }
            }
        <?php endif; ?>
    </script>
<script>

        function openFloatingBox(name, dept, timeOut, elapsed, empId, breakType, limit) {
            document.getElementById('modalName').innerText = name;
            document.getElementById('modalDept').innerText = dept;
            document.getElementById('modalTime').innerText = timeOut;
            document.getElementById('modalEmpId').value = empId;
            document.getElementById('modalAvatar').innerText = name.charAt(0).toUpperCase();

            let icon = breakType === 'Lunch Break' ? '🍽️' : '☕';
            document.getElementById('modalBreakType').innerText = icon + ' ' + breakType;

            var elapsedEl = document.getElementById('modalElapsed');
            elapsedEl.innerText = elapsed + ' min';
            if (parseInt(elapsed) >= limit) {
                elapsedEl.style.color = '#ef4444';
                elapsedEl.innerText = elapsed + ' min ⚠️ Overtime!';
            } else {
                elapsedEl.style.color = '#d97706';
            }

            document.getElementById('employeeModal').style.display = 'flex';
        }

        function closeFloatingBox() {
            document.getElementById('employeeModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('employeeModal').addEventListener('click', function(e) {
            if (e.target === this) closeFloatingBox();
        });



        const availCtx = document.getElementById('availChart').getContext('2d');
        new Chart(availCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'On Break', 'Absent', 'On Leave', 'Not Clocked In'],
                datasets: [{
                    data: [<?php echo $available_count; ?>, <?php echo $on_break_count; ?>, <?php echo $absent_count; ?>, <?php echo $leave_count; ?>, <?php echo $not_clocked_in_count; ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#94a3b8', '#cbd5e1'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        onClick: function(e, legendItem, legend) {
                            const index = legendItem.index;
                            const chart = legend.chart;
                            
                            // Default behavior: toggle visibility
                            if (chart.isDatasetVisible(0)) {
                                chart.toggleDataVisibility(index);
                                chart.update();
                            }
                            
                            // Determine which statuses are currently visible
                            const activeStatuses = [];
                            chart.data.labels.forEach((label, i) => {
                                // getDataVisibility defaults to true if undefined
                                const visible = chart.getDataVisibility(i);
                                if (visible) {
                                    activeStatuses.push(label);
                                }
                            });
                            
                            // Filter the table rows
                            const rows = document.querySelectorAll('.table-container table tbody tr');
                            let visibleCount = 0;
                            rows.forEach(row => {
                                // Skip the "No employees registered" row
                                if (row.cells.length === 1) return;
                                
                                const status = row.getAttribute('data-status');
                                if (activeStatuses.includes(status)) {
                                    row.style.display = '';
                                    visibleCount++;
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                        },
                        labels: {
                            padding: 20, usePointStyle: true, pointStyle: 'circle'
                        }
                    }
                }
            }
        });

        setInterval(function() {
            fetch(window.location.href, { cache: 'no-store' })
                .then(response => response.text())
                .then(html => {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    
                    var newTable = doc.querySelector('.table-container tbody');
                    if(newTable) {
                        document.querySelector('.table-container tbody').innerHTML = newTable.innerHTML;
                    }
                    
                    var newStats = doc.querySelectorAll('.premium-stat-card .info-value');
                    var oldStats = document.querySelectorAll('.premium-stat-card .info-value');
                    if(newStats.length === oldStats.length) {
                        for(let i = 0; i < newStats.length; i++) {
                            oldStats[i].innerHTML = newStats[i].innerHTML;
                        }
                    }
                });
        }, 30000);
    
</script>
</body>
</html>
