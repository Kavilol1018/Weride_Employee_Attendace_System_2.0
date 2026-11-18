<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role = $_SESSION['role'];
$status_message = "";

// Handle Leave Requests (Emergency Leave, Half Day, Annual, Sick)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['leave_action'])) {
    $leave_id = (int)$_POST['leave_id'];
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $action = $_POST['action'];
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? mysqli_real_escape_string($conn, $_POST['end_date']) : $start_date;

    if ($action == 'approve') {
        if (in_array($role, ['gl', 'tl'])) {
            mysqli_query($conn, "UPDATE leave_requests SET status = 'pending_hr' WHERE id = $leave_id");
            $status_message = "Leave request approved and forwarded to HR.";
            notify_employee($conn, $emp_id, "Leave Request Update", "Your $leave_type request ($start_date) was approved by your leader and forwarded to HR.", "employee_leave_history.php");
        } else {
            mysqli_query($conn, "UPDATE leave_requests SET status = 'approved' WHERE id = $leave_id");
            
            // Fetch the stored duration directly from the leave request (safely)
            $lr_data_q = @mysqli_query($conn, "SELECT duration, start_date, end_date FROM leave_requests WHERE id = $leave_id");
            $lr_data = $lr_data_q ? mysqli_fetch_assoc($lr_data_q) : null;
            
            if ($lr_data && !empty($lr_data['duration']) && (float)$lr_data['duration'] > 0) {
                $days = (float)$lr_data['duration'];
            } else {
                $fallback_start = $lr_data['start_date'] ?? $start_date;
                $fallback_end = $lr_data['end_date'] ?? $end_date ?? $fallback_start;
                
                $start_dt = new DateTime($fallback_start);
                $end_dt = new DateTime($fallback_end);
                $days = 0;
                $current_dt = clone $start_dt;
                while ($current_dt <= $end_dt) {
                    $dow = (int)$current_dt->format('w');
                    if ($dow !== 0 && $dow !== 6) $days++; // Skip weekends
                    $current_dt->modify('+1 day');
                }
            }
            $abbr = 'L';
            $lt = strtoupper($leave_type);
            $is_half = ($days == 0.5 || strpos($lt, 'HALF') !== false);
            if ($is_half) { $days = 0.5; }
            
            if (strpos($lt, 'EMERGENCY') !== false) { $abbr = 'AL'; }
            elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
            elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
            elseif (strpos($lt, 'UNPLANNED') !== false) { $abbr = 'UL'; }
            elseif (strpos($lt, 'UNPAID') !== false) { $abbr = 'UPL'; }
            elseif (strpos($lt, 'HOSPITALIZATION') !== false) { $abbr = 'HL'; }

            if ($is_half && ($abbr === 'AL' || $abbr === 'SL')) {
                $log_status = '0.5 ' . $abbr;
            } elseif ($is_half && $abbr === 'UPL') {
                $log_status = '0.5UPL';
            } else {
                $log_status = $is_half ? 'HD' : $abbr;
            }

            $start_dt = new DateTime($start_date);
            $end_dt = new DateTime($end_date);

            $current_dt = clone $start_dt;
            while ($current_dt <= $end_dt) {
                $day_of_week = (int)$current_dt->format('w'); // 0=Sun, 6=Sat
                if ($day_of_week !== 0 && $day_of_week !== 6) {
                    $date_str = $current_dt->format('Y-m-d');
                    $concat_sql = "IF(status LIKE '0.5%' AND '$log_status' LIKE '0.5%' AND status != '$log_status' AND status NOT LIKE '%$log_status%', CONCAT(status, ' / ', '$log_status'), '$log_status')";
                    mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status, source) VALUES ('$emp_id', '$date_str', '$log_status', 'leave_form') ON DUPLICATE KEY UPDATE status = $concat_sql, source = 'leave_form'");
                }
                $current_dt->modify('+1 day');
            }

            $emp_q = mysqli_query($conn, "SELECT employment_type FROM employees WHERE emp_id = '$emp_id'");
            $emp_d = mysqli_fetch_assoc($emp_q);
            $emp_type = strtolower($emp_d['employment_type'] ?? 'full_time');

            if ($abbr === 'AL') {
                mysqli_query($conn, "UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $days) WHERE emp_id = '$emp_id'");
            } elseif ($abbr === 'SL' || $abbr === 'UL') {
                if ($emp_type !== 'intern') {
                    mysqli_query($conn, "UPDATE employees SET sick_leave_balance = GREATEST(0, sick_leave_balance - $days) WHERE emp_id = '$emp_id'");
                }
            } elseif ($abbr === 'UPL') {
                mysqli_query($conn, "UPDATE employees SET unpaid_leave_balance = unpaid_leave_balance + $days WHERE emp_id = '$emp_id'");
            } elseif ($abbr === 'HL') {
                if ($emp_type !== 'intern') {
                    mysqli_query($conn, "UPDATE employees SET hospitalization_leave_balance = GREATEST(0, hospitalization_leave_balance - $days) WHERE emp_id = '$emp_id'");
                }
            }
            $status_message = "Leave request approved successfully.";
            $msg = "Your leave request ($leave_type for $start_date) was approved.";
            notify_employee($conn, $emp_id, "Leave Request Approved", $msg, "employee_leave_history.php");
        }
    } elseif ($action == 'reject') {
        $rejection_topic = mysqli_real_escape_string($conn, $_POST['rejection_topic'] ?? 'Other');
        $rejection_reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        mysqli_query($conn, "UPDATE leave_requests SET status = 'rejected', rejection_topic = '$rejection_topic', rejection_reason = '$rejection_reason' WHERE id = $leave_id");
        $status_message = "Leave request rejected.";
        
        $msg = "Your leave request ($leave_type for $start_date) was rejected. Topic: $rejection_topic. Reason: $rejection_reason";
        notify_employee($conn, $emp_id, "Leave Request Update", $msg, "employee_leave_history.php");
    } elseif ($action == 'cancel') {
        $lr_data_q = @mysqli_query($conn, "SELECT * FROM leave_requests WHERE id = $leave_id AND status = 'approved'");
        if ($lr_data = mysqli_fetch_assoc($lr_data_q)) {
            $duration = (float)$lr_data['duration'];
            if ($duration <= 0) {
                $s = new DateTime($lr_data['start_date']);
                $e = new DateTime($lr_data['end_date']);
                $duration = 0;
                while ($s <= $e) {
                    $dow = (int)$s->format('w');
                    if ($dow !== 0 && $dow !== 6) $duration++;
                    $s->modify('+1 day');
                }
            }
            
            $lt = strtoupper($lr_data['leave_type']);
            $abbr = 'L';
            if (strpos($lt, 'EMERGENCY') !== false) { $abbr = 'AL'; }
            elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
            elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
            elseif (strpos($lt, 'UNPLANNED') !== false) { $abbr = 'UL'; }
            elseif (strpos($lt, 'UNPAID') !== false) { $abbr = 'UPL'; }
            elseif (strpos($lt, 'HOSPITALIZATION') !== false) { $abbr = 'HL'; }

            $emp_q = mysqli_query($conn, "SELECT employment_type FROM employees WHERE emp_id = '$emp_id'");
            $emp_d = mysqli_fetch_assoc($emp_q);
            $emp_type = strtolower($emp_d['employment_type'] ?? 'full_time');

            if ($abbr === 'AL') {
                mysqli_query($conn, "UPDATE employees SET annual_leave_balance = annual_leave_balance + $duration WHERE emp_id = '$emp_id'");
            } elseif ($abbr === 'SL' || $abbr === 'UL') {
                if ($emp_type !== 'intern') {
                    mysqli_query($conn, "UPDATE employees SET sick_leave_balance = sick_leave_balance + $duration WHERE emp_id = '$emp_id'");
                }
            } elseif ($abbr === 'UPL') {
                mysqli_query($conn, "UPDATE employees SET unpaid_leave_balance = GREATEST(0, unpaid_leave_balance - $duration) WHERE emp_id = '$emp_id'");
            } elseif ($abbr === 'HL') {
                if ($emp_type !== 'intern') {
                    mysqli_query($conn, "UPDATE employees SET hospitalization_leave_balance = hospitalization_leave_balance + $duration WHERE emp_id = '$emp_id'");
                }
            }
            
            $s = new DateTime($lr_data['start_date']);
            $e = new DateTime($lr_data['end_date']);
            while ($s <= $e) {
                $dow = (int)$s->format('w');
                if ($dow !== 0 && $dow !== 6) {
                    $d_str = $s->format('Y-m-d');
                    mysqli_query($conn, "DELETE FROM attendance_logs WHERE emp_id = '$emp_id' AND date = '$d_str' AND clock_in IS NULL AND clock_out IS NULL AND status != 'P'");
                    mysqli_query($conn, "UPDATE attendance_logs SET status = 'P' WHERE emp_id = '$emp_id' AND date = '$d_str' AND (clock_in IS NOT NULL OR clock_out IS NOT NULL)");
                }
                $s->modify('+1 day');
            }

            mysqli_query($conn, "UPDATE leave_requests SET status = 'cancelled' WHERE id = $leave_id");
            $status_message = "Leave request cancelled and balances restored.";
            $msg = "Your leave request (" . $lr_data['leave_type'] . " for " . $lr_data['start_date'] . ") was cancelled.";
            mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
        }
    }
}

// Handle Force Actions (Admin, HR, TL) - Apply Leave on Behalf
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['force_action'])) {
    $emp_id = mysqli_real_escape_string($conn, $_POST['force_emp_id']);
    $force_type = mysqli_real_escape_string($conn, $_POST['force_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = !empty($_POST['end_date']) ? mysqli_real_escape_string($conn, $_POST['end_date']) : $start_date;
    $start_time = !empty($_POST['start_time']) ? mysqli_real_escape_string($conn, $_POST['start_time']) : null;
    $end_time = !empty($_POST['end_time']) ? mysqli_real_escape_string($conn, $_POST['end_time']) : null;
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $duration = isset($_POST['duration']) ? (float)$_POST['duration'] : 1.0;
    
    $emp_check = mysqli_query($conn, "SELECT id, name, annual_leave_balance, sick_leave_balance, employment_type FROM employees WHERE emp_id = '$emp_id'");
    if ($emp_row = mysqli_fetch_assoc($emp_check)) {
        if ($force_type == 'leave') {
            $leave_type = mysqli_real_escape_string($conn, $_POST['force_leave_type']);
            $al_bal = $emp_row['annual_leave_balance'];
            $sl_bal = $emp_row['sick_leave_balance'];
            $employment_type = $emp_row['employment_type'];
            
            $error = "";
            if (strtolower($employment_type) === 'intern' && strpos(strtoupper($leave_type), 'UNPAID') === false) {
                $error = "Interns are only eligible for Unpaid Leave.";
            }
            if ($leave_type === 'Annual Leave' && empty($error)) {
                // 14-days restriction removed for force application on behalf
                if ($duration > $al_bal) {
                    $error = "Cannot apply for more Annual Leave days ($duration) than employee's remaining balance ($al_bal).";
                }
            } elseif ($leave_type === 'Sick Leave' && empty($error) && $duration > $sl_bal) {
                $error = "Cannot apply for more Sick Leave days ($duration) than employee's remaining balance ($sl_bal).";
            }
            
            $mc_path = '';
            if (isset($_FILES['medical_certificate']) && $_FILES['medical_certificate']['error'] == 0) {
                $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
                $file_info = pathinfo($_FILES['medical_certificate']['name']);
                $ext = strtolower($file_info['extension']);
                
                if (in_array($ext, $allowed_ext)) {
                    $upload_dir = 'uploads/medical_certificates/';
                    $upload_abs_dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $upload_dir;
                    if (!is_dir($upload_abs_dir)) { mkdir($upload_abs_dir, 0755, true); }
                    $emp_name = $emp_row['name'];
                    $safe_name = preg_replace('/[^A-Za-z0-9]/', '', $emp_name);
                    $new_filename = $safe_name . '_' . $start_date . '_' . $end_date . '.' . $ext;
                    $dest_abs = $upload_abs_dir . $new_filename;
                    $dest = $upload_dir . $new_filename;
                    if (move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $dest_abs)) {
                        $mc_path = $dest;
                    } else {
                        $error = "Failed to upload medical certificate.";
                    }
                } else {
                    $error = "Invalid file type. Only JPG, PNG, and PDF allowed.";
                }
            }

            if (empty($error)) {
                $capacity_check = check_leave_capacity($conn, $start_date, $end_date, $emp_id);
                if ($capacity_check !== true) {
                    $error = $capacity_check;
                }
            }
            
            if (empty($error)) {
                $start_time_sql = $start_time ? "'$start_time'" : "NULL";
                $end_time_sql = $end_time ? "'$end_time'" : "NULL";
                
                $lt = strtoupper($leave_type);
                $abbr = 'L';
                $is_half = ($duration == 0.5 || strpos($lt, 'HALF') !== false);
                if ($is_half) { $duration = 0.5; }
                
                if (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
                elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
                elseif (strpos($lt, 'UNPAID') !== false) { $abbr = 'UPL'; }
                elseif (strpos($lt, 'EMERGENCY') !== false) { $abbr = 'AL'; }
                elseif (strpos($lt, 'HOSPITALIZATION') !== false) { $abbr = 'HL'; }
                
                if ($is_half && ($abbr === 'AL' || $abbr === 'SL')) {
                    $log_status = '0.5 ' . $abbr;
                } elseif ($is_half && $abbr === 'UPL') {
                    $log_status = '0.5UPL';
                } else {
                    $log_status = $is_half ? 'HD' : $abbr;
                }
                
                $initial_status = in_array($_SESSION['role'], ['gl', 'tl']) ? 'pending_hr' : 'approved';

                mysqli_query($conn, "INSERT INTO leave_requests (emp_id, leave_type, start_date, start_time, end_date, end_time, duration, reason, application_date, mc_attachment, status) VALUES ('$emp_id', '$leave_type', '$start_date', $start_time_sql, '$end_date', $end_time_sql, '$duration', '$reason', CURDATE(), '$mc_path', '$initial_status')");
                
                if ($initial_status === 'approved') {
                    // Loop through each day and add attendance logs
                    $start_dt = new DateTime($start_date);
                    $end_dt = new DateTime($end_date);
                    $current_dt = clone $start_dt;
                    while ($current_dt <= $end_dt) {
                        $day_of_week = (int)$current_dt->format('w'); // 0=Sun, 6=Sat
                        if ($day_of_week !== 0 && $day_of_week !== 6) {
                            $date_str = $current_dt->format('Y-m-d');
                            $concat_sql = "IF(status LIKE '0.5%' AND '$log_status' LIKE '0.5%' AND status != '$log_status' AND status NOT LIKE '%$log_status%', CONCAT(status, ' / ', '$log_status'), '$log_status')";
                            mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status, source) VALUES ('$emp_id', '$date_str', '$log_status', 'leave_form') ON DUPLICATE KEY UPDATE status = $concat_sql, source = 'leave_form'");
                        }
                        $current_dt->modify('+1 day');
                    }
                    
                    if ($abbr === 'AL') {
                        mysqli_query($conn, "UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $duration) WHERE emp_id = '$emp_id'");
                    } elseif ($abbr === 'SL') {
                        mysqli_query($conn, "UPDATE employees SET sick_leave_balance = GREATEST(0, sick_leave_balance - $duration) WHERE emp_id = '$emp_id'");
                    } elseif ($abbr === 'UPL') {
                        mysqli_query($conn, "UPDATE employees SET unpaid_leave_balance = unpaid_leave_balance + $duration WHERE emp_id = '$emp_id'");
                    } elseif ($abbr === 'HL') {
                        mysqli_query($conn, "UPDATE employees SET hospitalization_leave_balance = GREATEST(0, hospitalization_leave_balance - $duration) WHERE emp_id = '$emp_id'");
                    }
                    
                    $status_message = "Leave applied on behalf successfully and approved.";
                } else {
                    $status_message = "Leave applied on behalf successfully and forwarded to HR.";
                }
            } else {
                $status_message = $error;
            }
        }
    } else {
        $status_message = "Employee ID not found.";
    }
}

// FETCH LEAVE REQUESTS
$where_clauses = ["1=1"];
$current_emp_id = $_SESSION['emp_id'] ?? '';
if (in_array($role, ['gl', 'tl'])) {
    $where_clauses[] = "e.group_leader_id = '$current_emp_id'";
} elseif ($role === 'hr') {
    $where_clauses[] = "l.status != 'pending'";
}
$where_sql = implode(' AND ', $where_clauses);

$leaves_sql = "
    SELECT l.*, COALESCE(e.name, l.emp_id) as name, e.employment_type 
    FROM leave_requests l 
    LEFT JOIN employees e ON l.emp_id = e.emp_id 
    WHERE $where_sql
    ORDER BY CASE WHEN l.status IN ('pending', 'pending_hr') THEN 1 ELSE 2 END, l.created_at DESC
";
$leaves_result = mysqli_query($conn, $leaves_sql);

// FETCH EMPLOYEES FOR DROPDOWN
$dropdown_where = "1=1";
if (in_array($role, ['gl', 'tl'])) {
    $dropdown_where = "group_leader_id = '$current_emp_id'";
}
$emp_dropdown_sql = "SELECT emp_id, name, annual_leave_balance, sick_leave_balance, employment_type FROM employees WHERE $dropdown_where ORDER BY name ASC";
$emp_dropdown_res = mysqli_query($conn, $emp_dropdown_sql);

// PREPARE EMPLOYEE DATA FOR JS
$employee_data_js = [];
if ($emp_dropdown_res) {
    mysqli_data_seek($emp_dropdown_res, 0);
    while ($e = mysqli_fetch_assoc($emp_dropdown_res)) {
        $employee_data_js[$e['emp_id']] = [
            'alBal' => (float)($e['annual_leave_balance'] ?? 0),
            'slBal' => (float)($e['sick_leave_balance'] ?? 0),
            'type' => strtolower($e['employment_type'])
        ];
    }
    mysqli_data_seek($emp_dropdown_res, 0); // Reset pointer for dropdown HTML loop
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Manage Leave Requests</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #f8fafc; color: #334155; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #f8fafc; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }
        .section-header { font-size: 20px; color: #0f172a; font-weight: 700; margin-bottom: 10px; margin-top: 10px; }
        
        .table-container { background: transparent; padding: 10px 0; margin-bottom: 30px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 16px; text-align: left; font-size: 14px; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 20px; background: #ffffff; border-top: 1px solid rgba(226,232,240,0.6); border-bottom: 1px solid rgba(226,232,240,0.6); }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226,232,240,0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226,232,240,0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-block; text-transform: uppercase; }
        .bg-pending { background-color: #fef3c7; color: #d97706; }
        .bg-pending_hr { background-color: #e0e7ff; color: #3730a3; }
        .bg-approved { background-color: #ecfdf5; color: #059669; }
        .bg-rejected { background-color: #fef2f2; color: #ef4444; }

        .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; }
        .btn-approve { background: #10b981; color: white; margin-right: 8px; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; }
        
        .toast-success { background: #ecfdf5; color: #059669; padding: 16px 20px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #a7f3d0; }
        
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content, body.dark-mode .topbar { background-color: #0f172a; }
        body.dark-mode .page-title, body.dark-mode .section-header { color: #f8fafc; }
        body.dark-mode .dropdown-menu { background-color: #1e293b; border-color: #334155; }
        body.dark-mode .dropdown-item { color: #f8fafc; }
        body.dark-mode .dropdown-item:hover { background-color: #334155; }
        body.dark-mode .modal, body.dark-mode .modal-box { background-color: #1e293b !important; border-color: #334155 !important; }
        body.dark-mode .modal h2, body.dark-mode .modal-box h2 { color: #60a5fa !important; }
        body.dark-mode .modal label, body.dark-mode .modal-box label { color: #cbd5e1 !important; }
        body.dark-mode .modal input, body.dark-mode .modal select, body.dark-mode .modal-box input, body.dark-mode .modal-box select { background-color: #0f172a !important; color: #f8fafc !important; border-color: #475569 !important; }
        body.dark-mode .modal .bg-amber-100, body.dark-mode .modal-box .bg-amber-100 { background-color: #451a03 !important; color: #fcd34d !important; }
        body.dark-mode td { background-color: #1e293b; border-color: #334155; }
        body.dark-mode td:first-child { border-left-color: #334155; }
        body.dark-mode td:last-child { border-right-color: #334155; }
        body.dark-mode .badge.bg-pending { background-color: #78350f; color: #fcd34d; }
        body.dark-mode .badge.bg-approved { background-color: #064e3b; color: #6ee7b7; }
        body.dark-mode .badge.bg-rejected { background-color: #7f1d1d; color: #fca5a5; }

        /* Search Bar Styles */
        .search-container {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .search-input {
            padding: 9px 36px 9px 38px;
            border: 1px solid #cbd5e1;
            border-radius: 24px;
            width: 320px;
            font-size: 13.5px;
            outline: none;
            background: #ffffff;
            color: #0f172a;
            transition: all 0.2s ease;
        }
        .search-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .search-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: #94a3b8;
            pointer-events: none;
            transition: color 0.2s ease;
        }
        .search-container:focus-within .search-icon {
            color: #3b82f6;
        }
        .clear-search-btn {
            display: none;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 18px;
            padding: 2px;
            line-height: 1;
            transition: color 0.2s;
        }
        .clear-search-btn:hover {
            color: #ef4444;
        }
        
        body.dark-mode .search-input {
            background-color: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
        body.dark-mode .search-input::placeholder {
            color: #64748b;
        }
        body.dark-mode .search-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        body.dark-mode .clear-search-btn:hover {
            color: #f87171;
        }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Manage Leave Requests</div>
        </header>

        <div class="content">
            <?php if ($status_message): ?>
                <div class="toast-success">
                    ✅ <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; margin-top: 10px; flex-wrap: wrap; gap: 15px;">
                <h2 class="section-header" style="margin:0;">Leave & Emergency Leave Requests</h2>
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div class="search-container">
                        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="searchInput" class="search-input" oninput="searchLeaveRequests()" placeholder="Search employee, ID, leave type, reason...">
                        <button type="button" id="clearSearchBtn" class="clear-search-btn" onclick="clearSearch()" title="Clear search">&times;</button>
                    </div>
                    <button onclick="document.getElementById('forceActionModal').style.display='flex'" class="text-white" style="background: #3b82f6; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);">
                        Apply Leave on Behalf
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Time of Submission</th>
                            <th>Period</th>
                            <th>Half Day</th>
                            <th>MC / Proof</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leaves_result && mysqli_num_rows($leaves_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($leaves_result)): ?>
                                <tr class="leave-row">
                                    <td><b style="font-size: 15px;"><?php echo htmlspecialchars($row['name']); ?></b><br><span style="font-weight: 600; font-size: 12px;" class="text-muted">#<?php echo htmlspecialchars($row['emp_id']); ?></span></td>
                                    <td><span style="font-weight:700;" class="text-muted"><?php echo htmlspecialchars($row['leave_type']); ?></span></td>
                                    <td style="font-weight: 600; font-size: 13px; white-space: nowrap;" class="text-muted">
                                        <?php 
                                            $sub_time = (!empty($row['created_at']) && $row['created_at'] !== '0000-00-00 00:00:00') ? $row['created_at'] : null;
                                            if ($sub_time): 
                                        ?>
                                            <span><?php echo date('d M Y', strtotime($sub_time)); ?></span><br>
                                            <span style="font-size: 11px; font-weight: 600; color: #64748b;"><?php echo date('h:i A', strtotime($sub_time)); ?></span>
                                        <?php elseif (!empty($row['application_date']) && $row['application_date'] !== '0000-00-00'): ?>
                                            <span><?php echo date('d M Y', strtotime($row['application_date'])); ?></span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 600;" class="text-muted"><?php echo date('d M Y', strtotime($row['start_date'])); ?> to <?php echo date('d M Y', strtotime($row['end_date'])); ?></td>
                                    <td style="font-weight: 600;" class="text-muted"><?php echo (isset($row['duration']) && (float)$row['duration'] == 0.5) ? 'Yes' : 'N/A'; ?></td>
                                    <td>
                                        <?php if (!empty($row['mc_attachment'])): ?>
                                            <?php 
                                                $mc_url = $row['mc_attachment'];
                                                if (!empty($mc_url) && strpos($mc_url, 'http') !== 0) {
                                                    $mc_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($mc_url, '/');
                                                }
                                            ?>
                                            <a href="<?php echo htmlspecialchars($mc_url); ?>" target="_blank" style="background: #eff6ff; color:#3b82f6; text-decoration:none; padding: 4px 10px; border-radius: 6px; font-size:12px; font-weight:700;">View File</a>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?php echo htmlspecialchars($row['reason']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo strtolower($row['status']); ?>"><?php echo strtoupper($row['status']); ?></span>
                                        <?php if ($row['status'] === 'rejected' && !empty($row['rejection_topic'])): ?>
                                            <div style="font-size: 11px; color: #ef4444; margin-top: 4px; line-height: 1.2; font-weight: 600;">Rejection Topic: <?= htmlspecialchars($row['rejection_topic']) ?></div>
                                            <?php if (!empty($row['rejection_reason'])): ?>
                                                <a href="javascript:void(0)" onclick="viewRejectionReason(`<?= htmlspecialchars(addslashes($row['rejection_reason'])) ?>`)" style="color: #dc2626; text-decoration: underline; font-size: 10px;">View Description</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((in_array($role, ['gl', 'tl']) && $row['status'] === 'pending') || ($role === 'hr' && $row['status'] === 'pending_hr') || ($role === 'admin' && in_array($row['status'], ['pending', 'pending_hr']))): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="leave_action" value="1">
                                                <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="leave_type" value="<?php echo $row['leave_type']; ?>">
                                                <input type="hidden" name="start_date" value="<?php echo $row['start_date']; ?>">
                                                <input type="hidden" name="end_date" value="<?php echo $row['end_date']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                                                <button type="button" class="btn btn-reject" onclick="openRejectModal('<?php echo $row['id']; ?>', '<?php echo $row['emp_id']; ?>', '<?php echo $row['leave_type']; ?>', '<?php echo $row['start_date']; ?>', '<?php echo $row['end_date']; ?>')">Reject</button>
                                            </form>
                                        <?php elseif ($row['status'] === 'approved'): ?>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this approved leave and restore the balance?');">
                                                <input type="hidden" name="leave_action" value="1">
                                                <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="leave_type" value="<?php echo $row['leave_type']; ?>">
                                                <input type="hidden" name="start_date" value="<?php echo $row['start_date']; ?>">
                                                <button type="submit" name="action" value="cancel" class="btn btn-reject" style="background:#f59e0b;">Cancel Leave</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 13px; font-weight: 600;">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-slate-500" style="text-align:center;  padding:20px; font-weight: 600;">No leave requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    
    <!-- Force Action Modal -->
    <div id="forceActionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:1000;">
        <div class="modal-box bg-white" style=" padding:30px; border-radius:20px; width:700px; max-width:90%; position:relative; box-shadow:0 10px 25px rgba(0,0,0,0.1); max-height:90vh; overflow-y:auto; border: 2px dashed #3b82f6;">
            <button type="button" class="close-btn" onclick="document.getElementById('forceActionModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer; color:#9a3412; font-weight: bold;">&times;</button>
            <h2 style="color: #3b82f6; font-size: 22px; font-weight: 700; text-align: center; margin-bottom: 8px;">Apply Leave on Behalf</h2>
            <div class="bg-amber-100" style="color: #9a3412; font-size: 13px; text-align: left; font-weight: 500; margin-bottom: 25px; line-height: 1.5; padding: 12px; border-radius: 8px;">
                1. Submit leave application on behalf of your team member.<br>
                2. Ensure all required details are completed accurately. Incomplete application will be immediately rejected.<br>
                3. Sick leave, unpaid leave, annual leave will be applied as per usual using this form. (Max 5 employees company-wide on Annual Leave per day).
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" style="text-align: left;">
                <input type="hidden" name="force_action" value="1">
                <input type="hidden" name="force_type" value="leave">
                
                <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <!-- Row 1: Select Employee & Application Date -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Select Employee</label>
                        <select name="force_emp_id" required onchange="toggleLeaveModalFields('emp')" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                            <option value="" disabled selected>-- Select an employee --</option>
                            <?php 
                            if ($emp_dropdown_res) {
                                mysqli_data_seek($emp_dropdown_res, 0);
                                while ($e = mysqli_fetch_assoc($emp_dropdown_res)) {
                                    echo '<option value="' . htmlspecialchars($e['emp_id']) . '">' . htmlspecialchars($e['name']) . ' (' . htmlspecialchars($e['emp_id']) . ')</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Application Date</label>
                        <input type="text" value="<?php echo date('m/d/Y'); ?>" readonly style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; background: #f8fafc; color: #64748b; outline: none;">
                    </div>

                    <!-- Row 2: Leave Type & Duration -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Type of Leave</label>
                        <select name="force_leave_type" id="modal_leave_type" required onchange="toggleLeaveModalFields('type')" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                            <option value="" disabled selected>Please select</option>
                            <option value="Annual Leave">Annual Leave</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Unpaid Leave">Unpaid Leave</option>
                            <option value="Hospitalization Leave">Hospitalization Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Duration of Leave</label>
                        <input type="number" name="duration" id="modal_duration" step="0.5" min="0.5" oninput="toggleLeaveModalFields('duration')" placeholder="e.g. 1.5" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>

                    <!-- Row 3: Start Date & End Date -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Date of Leave (Start)</label>
                        <input type="text" name="start_date" id="modal_start_date" placeholder="Select Date" required readonly style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155; cursor: pointer;">
                    </div>
                    <div id="modal_end_date_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Date of Leave (End)</label>
                        <input type="text" name="end_date" id="modal_end_date" placeholder="Select Date" required readonly style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155; cursor: pointer;">
                    </div>

                    <!-- Row 3.5: Start Time & End Time -->
                    <div id="modal_start_time_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Time of Leave (Start)</label>
                        <input type="time" name="start_time" id="modal_start_time" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>
                    <div id="modal_end_time_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Time of Leave (End)</label>
                        <input type="time" name="end_time" id="modal_end_time" style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>
                    
                    <!-- Row 4: Reason (Full Width) -->
                    <div style="grid-column: 1 / -1;">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Reason for Leave</label>
                        <input type="text" name="reason" placeholder="Type here" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>

                    <!-- Row 5: Document Upload (Full Width) -->
                    <div id="modal_mc_upload_group" style="grid-column: 1 / -1;">
                        <label class="text-slate-500" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 4px;">Supporting Document</label>
                        <p style="font-size: 11px; color: #94a3b8; margin-bottom: 8px;">Employees applying for sick leave are required to submit a valid Medical Certificate (MC).</p>
                        <div class="bg-white" style="border: 1px dashed #fbbf24; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; position: relative;" ondragover="event.preventDefault();" ondrop="event.preventDefault(); this.querySelector('input').files = event.dataTransfer.files; this.querySelector('input').dispatchEvent(new Event('change'));">
                            <span class="text-amber-600" style="font-size: 13px; font-weight: 600;">Paste or drag files here (or click to upload)</span>
                            <input type="file" name="medical_certificate" accept=".jpg,.jpeg,.png,.pdf" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer; z-index: 10;" onchange="this.previousElementSibling.textContent = this.files[0] ? this.files[0].name : 'Paste or drag files here (or click to upload)';">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit text-white" style="width:100%; padding:14px; background:#3b82f6; border:none; border-radius:8px; font-size: 16px; font-weight:700; cursor:pointer; transition: 0.2s;">Submit Force Action</button>
            </form>
        </div>
    </div>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        const employeeData = <?php echo json_encode($employee_data_js); ?>;
        
        function toggleLeaveModalFields(source = null) {
            const type = document.querySelector('select[name="force_leave_type"]').value;
            const empId = document.querySelector('select[name="force_emp_id"]').value;
            
            const startGroup = document.getElementById('modal_start_date_group');
            const endGroup = document.getElementById('modal_end_date_group');
            const timeGroup = document.getElementById('modal_time_group');
            const durationGroup = document.getElementById('modal_duration_group');
            
            const startDateInput = document.getElementById('modal_start_date');
            const endInput = document.getElementById('modal_end_date');
            const startTimeInput = document.getElementById('modal_start_time');
            const endTimeInput = document.getElementById('modal_end_time');
            const durationInput = document.getElementById('modal_duration');
            
            let alBal = 0, slBal = 0, empType = 'full_time';
            if (empId && employeeData[empId]) {
                alBal = employeeData[empId].alBal;
                slBal = employeeData[empId].slBal;
                empType = employeeData[empId].type;
            }

            // Disable options if no balance
            const typeSelect = document.getElementById('modal_leave_type');
            const alOption = typeSelect.querySelector('option[value="Annual Leave"]');
            const slOption = typeSelect.querySelector('option[value="Sick Leave"]');
            const elOption = typeSelect.querySelector('option[value="Emergency Leave"]');
            
            if (alOption) {
                if (alBal <= 0 || empType === 'intern') {
                    alOption.disabled = true;
                    alOption.textContent = empType === 'intern' ? 'Annual Leave (Not eligible)' : 'Annual Leave (0 balance)';
                    if (type === 'Annual Leave') typeSelect.value = '';
                } else {
                    alOption.disabled = false;
                    alOption.textContent = 'Annual Leave';
                }
            }
            if (elOption) {
                if (alBal <= 0 || empType === 'intern') {
                    elOption.disabled = true;
                    elOption.textContent = empType === 'intern' ? 'Emergency Leave (Not eligible)' : 'Emergency Leave (0 balance)';
                    if (type === 'Emergency Leave') typeSelect.value = '';
                } else {
                    elOption.disabled = false;
                    elOption.textContent = 'Emergency Leave';
                }
            }
            if (slOption) {
                if (slBal <= 0 || empType === 'intern') {
                    slOption.disabled = true;
                    slOption.textContent = empType === 'intern' ? 'Sick Leave (Not eligible)' : 'Sick Leave (0 balance)';
                    if (type === 'Sick Leave') typeSelect.value = '';
                } else {
                    slOption.disabled = false;
                    slOption.textContent = 'Sick Leave';
                }
            }

            const currentType = typeSelect.value;

            endGroup.style.display = 'block';
            endInput.required = true;
            
            if (currentType === 'Annual Leave' || currentType === 'Emergency Leave') {
                durationInput.max = alBal;
                durationInput.min = "0.5";
            } else if (currentType === 'Sick Leave') {
                durationInput.max = slBal;
                durationInput.min = "0.5";
            } else {
                durationInput.removeAttribute('max');
                durationInput.removeAttribute('min');
            }

            if (currentType === 'Emergency Leave' || parseFloat(durationInput.value) <= 1) {
                endGroup.style.display = 'none';
                endInput.required = false;
            }

            if (currentType === 'Annual Leave') {
                let d = new Date();
                d.setDate(d.getDate() + 14);
                let minDateStr = d.toISOString().split('T')[0];
                if (startDateInput) startDateInput.min = minDateStr;
                if (endInput) endInput.min = minDateStr;
            } else {
                if (startDateInput) startDateInput.removeAttribute('min');
                if (endInput) endInput.removeAttribute('min');
            }

            // Automate Time and Duration
            if (source === 'type' || source === 'emp') {
                if (currentType === 'Annual Leave' || currentType === 'Sick Leave' || currentType === 'Unpaid Leave') {
                    startTimeInput.value = '09:00';
                    endTimeInput.value = '18:00';
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
            if (document.getElementById('modal_end_date_group').style.display === 'none') {
                end = start;
            } else if (!end) {
                return;
            }

            let [sy, sm, sd] = start.split('-').map(Number);
            let startDate = new Date(sy, sm - 1, sd);
            let [ey, em, ed] = end.split('-').map(Number);
            let endDate = new Date(ey, em - 1, ed);
            
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
            
            if (days === 1 && durationInput.value === '0.5') {
                // User explicitly requested half day on a single date, do not overwrite
            } else {
                durationInput.value = days;
            }
            
            // Trigger the toggle to ensure UI responds to the updated duration if needed
            toggleLeaveModalFields('duration');
        }

        const fpConfig = {
            dateFormat: "Y-m-d",
            disableMobile: true,
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

        window.fpStart = flatpickr("#modal_start_date", fpConfig);
        window.fpEnd = flatpickr("#modal_end_date", fpConfig);
        </script>
        
        <!-- Reject Leave Modal -->
        <div id="rejectLeaveModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:1000;">
            <div class="modal-box bg-white" style="padding:30px; border-radius:20px; width:450px; max-width:90%; position:relative; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
                <button type="button" class="close-btn" onclick="document.getElementById('rejectLeaveModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer; color:#ef4444;">&times;</button>
                <h2 style="color: #ef4444; font-size: 20px; font-weight: 700; margin-bottom: 20px;">Reject Leave Request</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="leave_action" value="1">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="leave_id" id="rej_leave_id" value="">
                    <input type="hidden" name="emp_id" id="rej_emp_id" value="">
                    <input type="hidden" name="leave_type" id="rej_leave_type" value="">
                    <input type="hidden" name="start_date" id="rej_start_date" value="">
                    <input type="hidden" name="end_date" id="rej_end_date" value="">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Rejection Topic</label>
                        <select name="rejection_topic" class="form-input" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;" required>
                            <option value="">-- Select a Topic --</option>
                            <option value="Insufficient Leave Balance">Insufficient Leave Balance</option>
                            <option value="High Workload / Team Shortage">High Workload / Team Shortage</option>
                            <option value="Invalid Reason / Missing Document">Invalid Reason / Missing Document</option>
                            <option value="Late Notice">Late Notice</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Description (Reason)</label>
                        <textarea name="rejection_reason" class="form-input" rows="4" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; resize: none;" required placeholder="Please provide specific details..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-reject" style="width: 100%; padding: 12px; border-radius: 8px; font-size: 15px;">Confirm Rejection</button>
                </form>
            </div>
        </div>

        <script>
            function openRejectModal(leaveId, empId, leaveType, startDate, endDate) {
                document.getElementById('rej_leave_id').value = leaveId;
                document.getElementById('rej_emp_id').value = empId;
                document.getElementById('rej_leave_type').value = leaveType;
                document.getElementById('rej_start_date').value = startDate;
                document.getElementById('rej_end_date').value = endDate;
                document.getElementById('rejectLeaveModal').style.display = 'flex';
            }
        </script>
        <div id="rejectionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:1000;">
        <div class="modal-box bg-white" style="padding:30px; border-radius:20px; width:400px; max-width:90%; position:relative; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <button class="close-btn" onclick="document.getElementById('rejectionModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer; color:#ef4444;">&times;</button>
              <h2 style="color: #ef4444; font-size: 18px; font-weight: 700; margin-bottom: 15px;">Rejection Description</h2>
              <div id="rejectionDescriptionText" style="font-size: 14px; color: #475569; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; text-align: justify; max-height: 300px; overflow-y: auto; padding-right: 5px;"></div>
        </div>
    </div>
    
    <script>
        function viewRejectionReason(reason) {
            document.getElementById('rejectionDescriptionText').innerText = reason;
            document.getElementById('rejectionModal').style.display = 'flex';
        }

        function searchLeaveRequests() {
            const input = document.getElementById("searchInput").value.trim().toLowerCase();
            const clearBtn = document.getElementById("clearSearchBtn");
            if (clearBtn) {
                clearBtn.style.display = input.length > 0 ? "block" : "none";
            }
            
            const rows = document.querySelectorAll("tbody tr.leave-row");
            let matchCount = 0;
            
            rows.forEach(row => {
                const text = (row.textContent || row.innerText || "").toLowerCase();
                if (text.indexOf(input) > -1) {
                    row.style.setProperty("display", "", "important");
                    matchCount++;
                } else {
                    row.style.setProperty("display", "none", "important");
                }
            });

            let noMatchRow = document.getElementById("noSearchResultsRow");
            if (!noMatchRow) {
                const tbody = document.querySelector(".table-container table tbody");
                if (tbody) {
                    noMatchRow = document.createElement("tr");
                    noMatchRow.id = "noSearchResultsRow";
                    noMatchRow.innerHTML = '<td colspan="9" style="text-align: center; padding: 30px; font-weight: 600; color: #94a3b8;">No matching leave requests found.</td>';
                    tbody.appendChild(noMatchRow);
                }
            }
            if (noMatchRow) {
                noMatchRow.style.display = (rows.length > 0 && matchCount === 0) ? "" : "none";
            }
        }

        function clearSearch() {
            const input = document.getElementById("searchInput");
            if (input) {
                input.value = "";
                searchLeaveRequests();
                input.focus();
            }
        }
    </script>
</body>
</html>
