<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['employee', 'qa', 'gl'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

$current_employee_id = $_SESSION['emp_id']; 

$emp_query = mysqli_query($conn, "SELECT lunch_window, name, annual_leave_balance, sick_leave_balance, shift_hour FROM employees WHERE emp_id = '$current_employee_id'");
$emp_data = mysqli_fetch_assoc($emp_query);
$lunch_window = $emp_data['lunch_window'] ?? '12:00-13:00';
$employee_name = $emp_data['name'] ?? "Employee #" . $current_employee_id;
$al_bal = $emp_data['annual_leave_balance'] ?? 14;
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

// CHECK EARLY LEAVE
$shift_parts = explode('-', $shift_hour);
$shift_end = isset($shift_parts[1]) ? trim($shift_parts[1]) : '18:00';
$is_early_clockout = (date('H:i') < $shift_end);

$leave_check_sql = "SELECT id FROM leave_requests WHERE emp_id = '$current_employee_id' AND status = 'approved' AND start_date <= CURDATE() AND end_date >= CURDATE() AND leave_type IN ('Half-Day Leave', 'Half-Day Leave (Before Lunch)', 'Half-Day Leave (After Lunch)', 'Early Leave')";
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
            
            mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, clock_in, status, flag) VALUES ('$current_employee_id', CURDATE(), NOW(), '$status', '$flag') ON DUPLICATE KEY UPDATE clock_in = NOW(), status = '$status', flag = '$flag'");
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
            
            if ($is_early && !$has_early_leave) {
                header("Location: gl_dashboard.php?msg=" . urlencode("Action Denied: You are not allowed to clock out before your shift ends."));
                exit();
            }

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
            header("Location: gl_dashboard.php?msg=" . urlencode("Meal In successful!"));
            exit();
        }
    }
    
    // FLOATING LEAVE APPLICATION SUBMISSION
    if (isset($_POST['submit_leave'])) {
        $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? $_POST['start_date']);
        
        $start_time = (isset($_POST['start_time']) && $_POST['start_time'] !== '') ? mysqli_real_escape_string($conn, $_POST['start_time']) : NULL;
        $end_time = (isset($_POST['end_time']) && $_POST['end_time'] !== '') ? mysqli_real_escape_string($conn, $_POST['end_time']) : NULL;
        
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $application_date = date('Y-m-d');
        
        $duration = isset($_POST['duration']) ? mysqli_real_escape_string($conn, $_POST['duration']) : '';
        
        $mc_path = "";
        $error = "";
        if (isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] == 0) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_info = pathinfo($_FILES['medical_certificate']['name']);
            $ext = strtolower($file_info['extension']);
            
            if (in_array($ext, $allowed_ext)) {
                $upload_dir = 'uploads/medical_certificates/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $new_filename = $current_employee_id . '_' . time() . '.' . $ext;
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
            $start_time_sql = $start_time ? "'$start_time'" : "NULL";
            $end_time_sql = $end_time ? "'$end_time'" : "NULL";
            $sql = "INSERT INTO leave_requests (emp_id, leave_type, start_date, start_time, end_date, end_time, duration, reason, application_date, mc_attachment, status) 
                    VALUES ('$current_employee_id', '$leave_type', '$start_date', $start_time_sql, '$end_date', $end_time_sql, '$duration', '$reason', '$application_date', '$mc_path', 'pending')";
            if (mysqli_query($conn, $sql)) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Group Leader Dashboard</title>
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
        .break-card { border: 1px solid #e9d5ff; box-shadow: 0 4px 12px rgba(168, 85, 247, 0.05); }

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
        body.dark-mode .break-card { border-color: #334155; }
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
        body.dark-mode .dropdown-content { background-color: #1e293b; border-color: #334155; }
        body.dark-mode .dropdown-item { color: #f8fafc; }
        body.dark-mode .dropdown-item:hover { background-color: #334155; }
    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Welcome back, <?php echo htmlspecialchars($employee_name); ?> 👋</div>
            <div class="topbar-right">
                <?php if (!$has_clocked_in): ?>
                    <form method="POST" action="" style="display:inline; margin-right: 8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="clock_in" class="btn-primary">
                            Clock In (Start Day)
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="" style="display:inline; margin-right: 8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <?php if ($has_clocked_out): ?>
                            <button type="button" class="btn-secondary" style="cursor: not-allowed;">
                                Clocked Out
                            </button>
                        <?php else: ?>
                            <?php 
                            $onclick = "return confirm('Are you sure you want to clock out for today?');";
                            if ($is_early_clockout && !$has_early_leave) {
                                $onclick = "alert('Clock out not allowed before shift ends ($shift_end).'); return false;";
                            } else if ($is_early_clockout && $has_early_leave) {
                                $onclick = "return confirm('You are clocking out early due to your approved Half-Day/Early Leave. Are you sure?');";
                            }
                            ?>
                            <button type="submit" name="clock_out" class="btn-primary text-red-500" style="background-" onclick="<?php echo $onclick; ?>">
                                Clock Out
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
                <div class="avatar"><?php echo strtoupper(substr($employee_name, 0, 1)); ?></div>
            </div>
        </header>

        <div class="content">
            <?php if ($status_message): ?>
                <div class="bg-blue-50" style=" border: 1px solid #bfdbfe; color: #1d4ed8; padding: 14px 20px; border-radius: 12px; font-weight: 600;">
                    🔔 <?php echo $status_message; ?>
                </div>
            <?php endif; ?>

            <div class="info-cards">
                <div class="info-card">
                    <div class="info-icon" style="background: #e0f2fe; color: #3b82f6;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Your Assigned Lunchtime Window</div>
                        <div class="info-value" style="color: #3b82f6;"><?php echo htmlspecialchars($lunch_window); ?></div>
                    </div>
                    <a href="#" class="info-action">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        View Schedule
                    </a>
                </div>
                
                <div class="info-card">
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
    </main>

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
                3. Sick leave, unpaid leave, annual leave will be applied as per usual using this form.<br>
                4. Notify HR upon applying.
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" style="text-align: left;">
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
                        <select name="leave_type" id="modal_leave_type" required onchange="toggleLeaveModalFields()" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2352525b\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'6 9 12 15 18 9\'></polyline></svg>'); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px;">
                            <option value="" disabled selected>Please select</option>
                            <option value="Annual Leave">Annual Leave</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Unpaid Leave">Unpaid Leave</option>
                            <option value="Half-Day Leave (Before Lunch)">Half-Day Leave (Before Lunch)</option>
                            <option value="Half-Day Leave (After Lunch)">Half-Day Leave (After Lunch)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Duration of Leave</label>
                        <input type="text" name="duration" id="modal_duration" placeholder="e.g. 1 for whole day, 0.5 for half day" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>

                    <!-- Row 3: Start Date & End Date -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Date of Leave (Start)</label>
                        <input type="date" name="start_date" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>
                    <div id="modal_end_date_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700;  margin-bottom: 6px;"><span class="text-red-500" >*</span> Date of Leave (End)</label>
                        <input type="date" name="end_date" id="modal_end_date" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
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
                        <div class="bg-white" style="border: 1px dashed #fbbf24; border-radius: 8px; padding: 20px; text-align: center; beb; cursor: pointer; position: relative;">
                            <span class="text-amber-600" style=" font-size: 13px; font-weight: 600;">Paste or drag files here (or click to upload)</span>
                            <input type="file" name="medical_certificate" accept=".jpg,.jpeg,.png,.pdf" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;">
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="submit_leave" class="text-white" style="width: 100%; background: #3b82f6;  padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s;">Submit Application</button>
            </form>
        </div>
    </div>

    <script>
        // Inject PHP variables into JS for dynamic time calculations
        const shiftStart = '<?php $parts = explode("-", $shift_hour); echo trim($parts[0] ?? "09:00"); ?>';
        const shiftEnd = '<?php $parts = explode("-", $shift_hour); echo trim($parts[1] ?? "18:00"); ?>';
        const lunchStart = '<?php $parts = explode("-", $lunch_window); echo trim($parts[0] ?? "12:00"); ?>';
        const lunchEnd = '<?php $parts = explode("-", $lunch_window); echo trim($parts[1] ?? "13:00"); ?>';

        function toggleLeaveModalFields() {
            const type = document.getElementById('modal_leave_type').value;
            const endGroup = document.getElementById('modal_end_date_group');
            const endInput = document.getElementById('modal_end_date');
            
            const startTimeInput = document.getElementById('modal_start_time');
            const endTimeInput = document.getElementById('modal_end_time');
            const durationInput = document.getElementById('modal_duration');

            endGroup.style.display = 'block';
            endInput.required = true;

            if (type === 'Half-Day Leave' || type === 'Half-Day Leave (Before Lunch)' || type === 'Half-Day Leave (After Lunch)' || type === 'Early Leave' || type === 'Late In') {
                endGroup.style.display = 'none';
                endInput.required = false;
            }

            // Automate Time and Duration
            if (type === 'Half-Day Leave (Before Lunch)') {
                startTimeInput.value = shiftStart;
                endTimeInput.value = lunchStart;
                durationInput.value = '0.5';
            } else if (type === 'Half-Day Leave (After Lunch)') {
                startTimeInput.value = lunchEnd;
                endTimeInput.value = shiftEnd;
                durationInput.value = '0.5';
            } else if (type === 'Annual Leave' || type === 'Sick Leave' || type === 'Unpaid Leave') {
                startTimeInput.value = shiftStart;
                endTimeInput.value = shiftEnd;
                durationInput.value = '1';
            } else {
                startTimeInput.value = '';
                endTimeInput.value = '';
                durationInput.value = '';
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
</body>
</html>

