<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role = $_SESSION['role'];
$status_message = "";

// Handle Leave Requests (Early Leave, Half Day, Annual, Sick)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['leave_action'])) {
    $leave_id = (int)$_POST['leave_id'];
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $action = $_POST['action'];
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? mysqli_real_escape_string($conn, $_POST['end_date']) : $start_date;

    if ($action == 'approve') {
        mysqli_query($conn, "UPDATE leave_requests SET status = 'approved' WHERE id = $leave_id");
        
        $start_dt = new DateTime($start_date);
        $end_dt = new DateTime($end_date);
        $interval = $start_dt->diff($end_dt);
        $days = $interval->days + 1;
        
        $abbr = 'L';
        $lt = strtoupper($leave_type);
        if (strpos($lt, 'HALF') !== false) { $abbr = 'HD'; $days = 0.5; }
        elseif (strpos($lt, 'EARLY') !== false) { $abbr = 'EARLY'; $days = 0; }
        elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
        elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
        
        elseif (strpos($lt, 'UNPLANNED') !== false) { $abbr = 'UL'; }

        // Loop through each day of the leave and add it to the attendance logs
        $current_dt = clone $start_dt;
        while ($current_dt <= $end_dt) {
            $date_str = $current_dt->format('Y-m-d');
            mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$abbr') ON DUPLICATE KEY UPDATE status = '$abbr'");
            $current_dt->modify('+1 day');
        }

        // Deduct balance for appropriate leave types
        if ($abbr === 'AL') {
            mysqli_query($conn, "UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $days) WHERE emp_id = '$emp_id'");
        } elseif ($abbr === 'SL' || $abbr === 'UL') {
            mysqli_query($conn, "UPDATE employees SET sick_leave_balance = GREATEST(0, sick_leave_balance - $days) WHERE emp_id = '$emp_id'");
        } elseif ($abbr === 'CL') {
            
        }

        $status_message = "Leave request approved successfully.";
        $msg = "Your leave request ($leave_type for $start_date) was approved.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
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
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $half_day_type = isset($_POST['half_day_type']) ? mysqli_real_escape_string($conn, $_POST['half_day_type']) : 'N/A';

    $emp_check = mysqli_query($conn, "SELECT id FROM employees WHERE emp_id = '$emp_id'");
    if (mysqli_num_rows($emp_check) > 0) {
        if ($force_type == 'leave') {
            $leave_type = mysqli_real_escape_string($conn, $_POST['force_leave_type']);
            
            // Calculate Duration
            $start_dt = new DateTime($start_date);
            $end_dt = new DateTime($end_date);
            $interval = $start_dt->diff($end_dt);
            $days = $interval->days + 1;

            $abbr = 'L';
            $lt = strtoupper($leave_type);
            
            if (strpos($lt, 'HALF') !== false) { 
                $abbr = 'HD'; 
                $days = 0.5; 
            }
            elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
            elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
            
            elseif (strpos($lt, 'UNPAID') !== false) { $abbr = 'UPL'; }
            elseif (strpos($lt, 'EARLY') !== false) { 
                $abbr = 'EARLY'; 
                $days = 0; 
            }
            
            mysqli_query($conn, "INSERT INTO leave_requests (emp_id, leave_type, start_date, end_date, half_day_type, reason, status) VALUES ('$emp_id', '$leave_type', '$start_date', '$end_date', '$half_day_type', '$reason', 'approved')");
            
            // Loop through each day and add attendance logs
            $current_dt = clone $start_dt;
            while ($current_dt <= $end_dt) {
                $date_str = $current_dt->format('Y-m-d');
                mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$abbr') ON DUPLICATE KEY UPDATE status = '$abbr'");
                $current_dt->modify('+1 day');
            }
            
            if ($abbr === 'AL') {
                mysqli_query($conn, "UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $days) WHERE emp_id = '$emp_id'");
            } elseif ($abbr === 'SL' || $abbr === 'UL') {
                mysqli_query($conn, "UPDATE employees SET sick_leave_balance = GREATEST(0, sick_leave_balance - $days) WHERE emp_id = '$emp_id'");
            } elseif ($abbr === 'CL') {
                
            }
            $status_message = "Leave applied on behalf successfully.";
        }
    } else {
        $status_message = "Employee ID not found.";
    }
}

// FETCH LEAVE REQUESTS
$leaves_sql = "
    SELECT l.*, e.name, e.employment_type 
    FROM leave_requests l 
    JOIN employees e ON l.emp_id = e.emp_id 
    ORDER BY CASE WHEN l.status = 'pending' THEN 1 ELSE 2 END, l.created_at DESC
";
$leaves_result = mysqli_query($conn, $leaves_sql);

// FETCH EMPLOYEES FOR DROPDOWN
$emp_dropdown_sql = "SELECT emp_id, name FROM employees ORDER BY name ASC";
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
                <h2 class="section-header" style="margin:0;">Leave & Early Leave Requests</h2>
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
                                    <td><span class="badge bg-<?php echo strtolower($row['status']); ?>"><?php echo strtoupper($row['status']); ?></span></td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="leave_action" value="1">
                                                <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="leave_type" value="<?php echo $row['leave_type']; ?>">
                                                <input type="hidden" name="start_date" value="<?php echo $row['start_date']; ?>">
                                                <input type="hidden" name="end_date" value="<?php echo $row['end_date']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-approve">Approve</button>
                                            </form>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="leave_action" value="1">
                                                <input type="hidden" name="leave_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="leave_type" value="<?php echo $row['leave_type']; ?>">
                                                <input type="hidden" name="start_date" value="<?php echo $row['start_date']; ?>">
                                                <input type="hidden" name="end_date" value="<?php echo $row['end_date']; ?>">
                                                <input type="hidden" name="action" value="reject">
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
    </main>

    <!-- Force Action Modal -->
    <div id="forceActionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:1000;">
        <div class="modal-box bg-white" style=" padding:30px; border-radius:16px; width:450px; max-width:90%; position:relative; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <button type="button" class="close-btn" onclick="document.getElementById('forceActionModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:20px; cursor:pointer; color:#94a3b8;">&times;</button>
            <h3 class="modal-title text-slate-900" style="margin-top:0; margin-bottom:20px; ">Apply Leave on Behalf</h3>
            <form method="POST" action="">
                <input type="hidden" name="force_action" value="1">
                <input type="hidden" name="force_type" value="leave">
                
                <div style="margin-bottom:15px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Select Employee</label>
                    <select name="force_emp_id" required class="form-input bg-transparent" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; ">
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

                <div style="margin-bottom:15px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Leave Type</label>
                    <select name="force_leave_type" id="force_leave_type" required class="form-input bg-transparent" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; " onchange="toggleHalfDayOnBehalf()">
                        <option value="Annual Leave">Annual Leave</option>
                        <option value="Sick Leave">Sick Leave</option>
                        
                        <option value="Unpaid Leave">Unpaid Leave</option>
                        <option value="Half-Day Leave">Half-Day Leave</option>
                        <option value="Early Leave">Early Leave</option>
                    </select>
                </div>

                <div id="behalf_half_day_type" style="display: none; margin-bottom: 15px;">
                    <label class="text-slate-500" style="display: block; font-size: 13px; font-weight: 600;  margin-bottom: 5px;">Morning or Afternoon?</label>
                    <select name="half_day_type" class="form-input bg-transparent" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; ">
                        <option value="Morning">Morning</option>
                        <option value="Afternoon">Afternoon</option>
                    </select>
                </div>

                <div style="display: flex; gap: 15px; margin-bottom:15px;">
                    <div style="flex: 1;">
                        <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Start Date</label>
                        <input type="date" name="start_date" required class="form-input bg-transparent" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; ">
                    </div>
                    <div style="flex: 1;">
                        <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">End Date</label>
                        <input type="date" name="end_date" class="form-input bg-transparent" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; " title="Optional. Leave blank if 1 day.">
                    </div>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Reason</label>
                    <textarea name="reason" rows="2" required class="form-input bg-transparent" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; " placeholder="Provide a reason..."></textarea>
                </div>

                <button type="submit" class="btn-submit text-white" style="width:100%; padding:12px; background:#3b82f6;  border:none; border-radius:8px; font-weight:700; cursor:pointer;">Submit Force Action</button>
            </form>
        </div>
    </div>
    
    <script>
    function toggleHalfDayOnBehalf() {
        const type = document.getElementById('force_leave_type').value;
        const halfDayDiv = document.getElementById('behalf_half_day_type');
        if (type === 'Half-Day Leave') {
            halfDayDiv.style.display = 'block';
        } else {
            halfDayDiv.style.display = 'none';
        }
    }
    </script>

</body>
</html>
