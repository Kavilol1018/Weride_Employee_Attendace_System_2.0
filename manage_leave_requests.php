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
        if ($role === 'gl') {
            mysqli_query($conn, "UPDATE leave_requests SET status = 'pending_hr' WHERE id = $leave_id");
            $status_message = "Leave request approved and forwarded to HR.";
        } else {
            mysqli_query($conn, "UPDATE leave_requests SET status = 'approved' WHERE id = $leave_id");
            
            $start_dt = new DateTime($start_date);
            $end_dt = new DateTime($end_date);
            $interval = $start_dt->diff($end_dt);
            $days = $interval->days + 1;
            
            // Dynamic 0.5 day deduction logic based on exact timing
            $lr_q = mysqli_query($conn, "SELECT start_time, end_time FROM leave_requests WHERE id = $leave_id");
            if ($lr_q && $lr_d = mysqli_fetch_assoc($lr_q)) {
                $l_st = $lr_d['start_time'];
                $l_et = $lr_d['end_time'];
                if ($days == 1 && !empty($l_st) && !empty($l_et)) {
                    $st_ts = strtotime($l_st);
                    $et_ts = strtotime($l_et);
                    // 4.5 hours * 3600 seconds = 16200 seconds
                    if ($et_ts > $st_ts && ($et_ts - $st_ts) <= 16200) {
                        $days = 0.5;
                    }
                }
            }
            
            $abbr = 'L';
            $lt = strtoupper($leave_type);
            if (strpos($lt, 'HALF') !== false) { $abbr = 'HD'; $days = 0.5; }
            elseif (strpos($lt, 'EMERGENCY') !== false) { $abbr = 'EMERGENCY'; $days = 0; }
            elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
            elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
            elseif (strpos($lt, 'UNPLANNED') !== false) { $abbr = 'UL'; }

            $current_dt = clone $start_dt;
            while ($current_dt <= $end_dt) {
                $date_str = $current_dt->format('Y-m-d');
                mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$abbr') ON DUPLICATE KEY UPDATE status = '$abbr'");
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
            }
            $status_message = "Leave request approved successfully.";
            $msg = "Your leave request ($leave_type for $start_date) was approved.";
            mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
        }
    } elseif ($action == 'reject') {
        $rejection_reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        mysqli_query($conn, "UPDATE leave_requests SET status = 'rejected', rejection_reason = '$rejection_reason' WHERE id = $leave_id");
        $status_message = "Leave request rejected.";
        
        $msg = "Your leave request ($leave_type for $start_date) was rejected. Reason: $rejection_reason";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
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
    
    $emp_check = mysqli_query($conn, "SELECT id, annual_leave_balance, sick_leave_balance, employment_type FROM employees WHERE emp_id = '$emp_id'");
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
                $start_dt = new DateTime($start_date);
                $fourteen_days_from_now = new DateTime();
                $fourteen_days_from_now->setTime(0, 0, 0);
                $fourteen_days_from_now->modify('+14 days');
                if ($start_dt < $fourteen_days_from_now) {
                    $error = "Annual Leave must be applied at least 14 days in advance.";
                } elseif ($duration > $al_bal) {
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
                    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                    $new_filename = $emp_id . '_' . time() . '.' . $ext;
                    $dest = $upload_dir . $new_filename;
                    if (move_uploaded_file($_FILES['medical_certificate']['tmp_name'], $dest)) {
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
                if (strpos($lt, 'HALF') !== false) { $abbr = 'HD'; }
                elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
                elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
                elseif (strpos($lt, 'UNPAID') !== false) { $abbr = 'UPL'; }
                elseif (strpos($lt, 'EMERGENCY') !== false) { $abbr = 'EMERGENCY'; }
                
                mysqli_query($conn, "INSERT INTO leave_requests (emp_id, leave_type, start_date, start_time, end_date, end_time, duration, reason, application_date, mc_attachment, status) VALUES ('$emp_id', '$leave_type', '$start_date', $start_time_sql, '$end_date', $end_time_sql, '$duration', '$reason', CURDATE(), '$mc_path', 'approved')");
                
                // Loop through each day and add attendance logs
                $start_dt = new DateTime($start_date);
                $end_dt = new DateTime($end_date);
                $current_dt = clone $start_dt;
                while ($current_dt <= $end_dt) {
                    $date_str = $current_dt->format('Y-m-d');
                    mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$abbr') ON DUPLICATE KEY UPDATE status = '$abbr'");
                    $current_dt->modify('+1 day');
                }
                
                if ($abbr === 'AL') {
                    mysqli_query($conn, "UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $duration) WHERE emp_id = '$emp_id'");
                } elseif ($abbr === 'SL') {
                    mysqli_query($conn, "UPDATE employees SET sick_leave_balance = GREATEST(0, sick_leave_balance - $duration) WHERE emp_id = '$emp_id'");
                }
                
                $status_message = "Leave applied on behalf successfully.";
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
if ($role === 'gl') {
    $where_clauses[] = "e.group_leader_id = '$current_emp_id'";
} elseif ($role === 'hr') {
    $where_clauses[] = "l.status != 'pending'";
}
$where_sql = implode(' AND ', $where_clauses);

$leaves_sql = "
    SELECT l.*, e.name, e.employment_type 
    FROM leave_requests l 
    JOIN employees e ON l.emp_id = e.emp_id 
    WHERE $where_sql
    ORDER BY CASE WHEN l.status IN ('pending', 'pending_hr') THEN 1 ELSE 2 END, l.created_at DESC
";
$leaves_result = mysqli_query($conn, $leaves_sql);

// FETCH EMPLOYEES FOR DROPDOWN
$dropdown_where = "1=1";
if ($role === 'gl') {
    $dropdown_where = "group_leader_id = '$current_emp_id'";
}
$emp_dropdown_sql = "SELECT emp_id, name FROM employees WHERE $dropdown_where ORDER BY name ASC";
$emp_dropdown_res = mysqli_query($conn, $emp_dropdown_sql);

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

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; margin-top: 10px;">
                <h2 class="section-header" style="margin:0;">Leave & Emergency Leave Requests</h2>
                <button onclick="document.getElementById('forceActionModal').style.display='flex'" class="text-white" style="background: #3b82f6;  border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer;">Apply Leave on Behalf</button>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
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
                                <tr>
                                    <td><b style="font-size: 15px;"><?php echo htmlspecialchars($row['name']); ?></b><br><span style="font-weight: 600; font-size: 12px;" class="text-muted">#<?php echo htmlspecialchars($row['emp_id']); ?></span></td>
                                    <td><span style="font-weight:700;" class="text-muted"><?php echo htmlspecialchars($row['leave_type']); ?></span></td>
                                    <td style="font-weight: 600;" class="text-muted"><?php echo date('d M Y', strtotime($row['start_date'])); ?> to <?php echo date('d M Y', strtotime($row['end_date'])); ?></td>
                                    <td style="font-weight: 600;" class="text-muted"><?php echo htmlspecialchars($row['half_day_type'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($row['mc_attachment'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['mc_attachment']); ?>" target="_blank" style="background: #eff6ff; color:#3b82f6; text-decoration:none; padding: 4px 10px; border-radius: 6px; font-size:12px; font-weight:700;">View File</a>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?php echo htmlspecialchars($row['reason']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo strtolower($row['status']); ?>"><?php echo strtoupper($row['status']); ?></span>
                                        <?php if ($row['status'] === 'rejected' && !empty($row['rejection_reason'])): ?>
                                            <div style="font-size: 11px; color: #ef4444; margin-top: 4px; line-height: 1.2;">Reason: <?= htmlspecialchars($row['rejection_reason']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($role === 'gl' && $row['status'] === 'pending') || ($role === 'hr' && $row['status'] === 'pending_hr') || (in_array($role, ['admin', 'tl']) && in_array($row['status'], ['pending', 'pending_hr']))): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="leave_action" value="1">
                                                <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="leave_type" value="<?php echo $row['leave_type']; ?>">
                                                <input type="hidden" name="start_date" value="<?php echo $row['start_date']; ?>">
                                                <input type="hidden" name="end_date" value="<?php echo $row['end_date']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                                                <input type="hidden" name="rejection_reason" value="">
                                                <button type="button" class="btn btn-reject" onclick="let r = prompt('Please provide a reason for rejection:'); if(r){ this.previousElementSibling.value = r; this.form.submit(); }">Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 13px; font-weight: 600;">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-slate-500" style="text-align:center;  padding:20px; font-weight: 600;">No leave requests found.</td></tr>
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
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <!-- Row 1: Select Employee & Application Date -->
                    <div>
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Select Employee</label>
                        <select name="force_emp_id" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
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
                        <input type="text" name="start_date" id="modal_start_date" placeholder="Select Date" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
                    </div>
                    <div id="modal_end_date_group">
                        <label class="text-slate-900" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;"><span class="text-red-500">*</span> Date of Leave (End)</label>
                        <input type="text" name="end_date" id="modal_end_date" placeholder="Select Date" required style="width: 100%; padding: 10px 15px; border: 1px solid #fbbf24; border-radius: 8px; font-size: 14px; outline: none; background: white; color: #334155;">
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
                        <div class="bg-white" style="border: 1px dashed #fbbf24; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; position: relative;">
                            <span class="text-amber-600" style="font-size: 13px; font-weight: 600;">Paste or drag files here (or click to upload)</span>
                            <input type="file" name="medical_certificate" accept=".jpg,.jpeg,.png,.pdf" style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;">
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
    function toggleLeaveModalFields(source = null) {
        const type = document.getElementById('modal_leave_type').value;
        const endGroup = document.getElementById('modal_end_date_group');
        const endInput = document.getElementById('modal_end_date');
        const durationInput = document.getElementById('modal_duration');
        const startTimeInput = document.getElementById('modal_start_time');
        const endTimeInput = document.getElementById('modal_end_time');
        const startDateInput = document.getElementById('modal_start_date');

        endGroup.style.display = 'block';
        endInput.required = true;

        if (type === 'Emergency Leave' || parseFloat(durationInput.value) <= 1) {
            endGroup.style.display = 'none';
            endInput.required = false;
        }

        if (type === 'Annual Leave') {
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
        if (source === 'type') {
            if (type === 'Annual Leave' || type === 'Sick Leave' || type === 'Unpaid Leave') {
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
        const publicHolidays = [
            '2026-01-01', // New Year's Day
            '2026-01-28', // Thaipusam
            '2026-02-12', // Chinese New Year (Day 1)
            '2026-02-13', // Chinese New Year (Day 2)
            '2026-03-20', // Hari Raya Aidilfitri (Day 1)
            '2026-03-21', // Hari Raya Aidilfitri (Day 2)
            '2026-05-01', // Labour Day
            '2026-05-24', // Wesak Day
            '2026-05-27', // Hari Raya Haji
            '2026-06-06', // Agong's Birthday
            '2026-07-07', // Awal Muharram
            '2026-08-31', // Merdeka Day
            '2026-09-16', // Malaysia Day
            '2026-11-08', // Deepavali
            '2026-12-25'  // Christmas
        ];

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

        flatpickr("#modal_start_date", fpConfig);
        flatpickr("#modal_end_date", fpConfig);
    </script>

</body>
</html>
