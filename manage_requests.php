<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role = $_SESSION['role'];
$status_message = "";

// Handle Break Change Requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['break_change_action'])) {
    $request_id = (int)$_POST['request_id'];
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $action = $_POST['action'];
    $requested_window = mysqli_real_escape_string($conn, $_POST['requested_window']);

    if ($action == 'approve') {
        mysqli_query($conn, "UPDATE break_change_requests SET status = 'approved' WHERE id = $request_id");
        mysqli_query($conn, "UPDATE employees SET lunch_window = '$requested_window' WHERE emp_id = '$emp_id'");
        $status_message = "Break change request approved successfully.";
        
        $msg = "Your request to change your lunch window to $requested_window was approved.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
    } elseif ($action == 'reject') {
        mysqli_query($conn, "UPDATE break_change_requests SET status = 'rejected' WHERE id = $request_id");
        $status_message = "Break change request rejected.";
        
        $msg = "Your request to change your lunch window to $requested_window was rejected.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
    }
}


// Handle Force Actions (Admin, HR, TL)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['force_action'])) {
    $emp_id = mysqli_real_escape_string($conn, $_POST['force_emp_id']);
    $force_type = mysqli_real_escape_string($conn, $_POST['force_type']);
    $target_date = mysqli_real_escape_string($conn, $_POST['target_date']); // Date for leave
    $target_datetime = mysqli_real_escape_string($conn, $_POST['target_datetime']); // Datetime for meals

    $emp_check = mysqli_query($conn, "SELECT id FROM employees WHERE emp_id = '$emp_id'");
    if (mysqli_num_rows($emp_check) > 0) {
        if ($force_type == 'meal_in') {
            mysqli_query($conn, "INSERT INTO lunch_breaks (emp_id, break_start, status) VALUES ('$emp_id', '$target_datetime', 'on_break')");
            $status_message = "Forced Meal In recorded.";
        } elseif ($force_type == 'meal_out') {
            mysqli_query($conn, "UPDATE lunch_breaks SET break_end = '$target_datetime', status = 'returned' WHERE emp_id = '$emp_id' AND status = 'on_break' ORDER BY break_start DESC LIMIT 1");
            $status_message = "Forced Meal Out recorded.";
        } 
    } else {
        $status_message = "Employee ID not found.";
    }
}

$gl_filter = $role === 'gl' ? "WHERE e.group_leader_id = '{$_SESSION['emp_id']}'" : "";

// FETCH BREAK REQUESTS
$requests_sql = "
    SELECT r.*, e.name, e.employment_type 
    FROM break_change_requests r 
    JOIN employees e ON r.emp_id = e.emp_id 
    $gl_filter
    ORDER BY CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END, r.created_at DESC
";
$requests_result = mysqli_query($conn, $requests_sql);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Manage Requests</title>
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
            <div class="page-title">Manage Employee Requests</div>
        </header>

        <div class="content">
            <?php if ($status_message): ?>
                <div class="toast-success">
                    ✅ <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <!-- BREAK CHANGE REQUESTS -->
            <h2 class="section-header">Lunch Window Change Requests</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Current Window</th>
                            <th>Requested Window</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests_result && mysqli_num_rows($requests_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($requests_result)): ?>
                                <tr>
                                    <td><b class="text-slate-900" style=" font-size: 15px;"><?php echo htmlspecialchars($row['name']); ?></b><br><span class="text-slate-500" style=" font-weight: 600; font-size: 12px;">#<?php echo htmlspecialchars($row['emp_id']); ?></span></td>
                                    <td class="text-slate-500" style=" font-weight: 600;"><?php echo htmlspecialchars($row['current_window']); ?></td>
                                    <td class="text-slate-900" style=" font-weight: 700;"><?php echo htmlspecialchars($row['requested_window']); ?></td>
                                    <td><span class="badge bg-<?php echo strtolower($row['status']); ?>"><?php echo strtoupper($row['status']); ?></span></td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="break_change_action" value="1">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="requested_window" value="<?php echo $row['requested_window']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-approve">Approve</button>
                                            </form>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="break_change_action" value="1">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="requested_window" value="<?php echo $row['requested_window']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-reject">Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 13px; font-weight: 600;">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-slate-500" style="text-align:center;  padding:20px; font-weight: 600;">No break change requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>



        </div>
    </main>
    <!-- Force Action Modal -->
    <div id="forceActionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:1000;">
        <div class="bg-white" style=" padding:30px; border-radius:16px; width:400px; max-width:90%; position:relative; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <button type="button" onclick="document.getElementById('forceActionModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:20px; cursor:pointer; color:#94a3b8;">&times;</button>
            <h3 class="text-slate-900" style="margin-top:0; margin-bottom:20px; ">Apply Force Action</h3>
            <form method="POST" action="">
                <input type="hidden" name="force_action" value="1">
                
                <div style="margin-bottom:15px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Employee ID</label>
                    <input type="text" name="force_emp_id" required class="text-slate-900" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; ">
                </div>

                <div style="margin-bottom:15px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Action Type</label>
                    <select name="force_type" id="force_type" required class="text-slate-900" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; " onchange="toggleForceFields()">
                        <option value="meal_in">Force Meal In</option>
                        <option value="meal_out">Force Meal Out</option>
                        
                    </select>
                </div>

                <div id="force_datetime_field" style="margin-bottom:15px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Target Date & Time</label>
                    <input type="datetime-local" name="target_datetime" id="target_datetime" class="text-slate-900" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; ">
                </div>

                <div id="force_date_field" style="display:none; margin-bottom:15px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Target Date</label>
                    <input type="date" name="target_date" id="target_date" class="text-slate-900" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; ">
                </div>

                <div id="force_leave_type_field" style="display:none; margin-bottom:20px;">
                    <label class="text-slate-500" style="display:block; font-weight:600; font-size:13px;  margin-bottom:5px;">Leave Type</label>
                    <select name="force_leave_type" class="text-slate-900" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none; ">
                        <option value="Annual Leave">Annual Leave</option>
                        <option value="Sick Leave">Sick Leave</option>
                        <option value="Unplanned Leave">Unplanned Leave</option>
                        <option value="Half Day - AM">Half Day - AM</option>
                        <option value="Half Day - PM">Half Day - PM</option>
                        
                    </select>
                </div>

                <button type="submit" class="text-white" style="width:100%; background:#3b82f6;  border:none; padding:12px; border-radius:8px; font-weight:bold; cursor:pointer;">Submit Action</button>
            </form>
        </div>
    </div>

    <script>
    function toggleForceFields() {
        const type = document.getElementById('force_type').value;
        if (type === 'leave') {
            document.getElementById('force_datetime_field').style.display = 'none';
            document.getElementById('force_date_field').style.display = 'block';
            document.getElementById('force_leave_type_field').style.display = 'block';
            document.getElementById('target_date').required = true;
            document.getElementById('target_datetime').required = false;
        } else {
            document.getElementById('force_datetime_field').style.display = 'block';
            document.getElementById('force_date_field').style.display = 'none';
            document.getElementById('force_leave_type_field').style.display = 'none';
            document.getElementById('target_datetime').required = true;
            document.getElementById('target_date').required = false;
        }
    }
    // Set default datetime to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('target_datetime').value = now.toISOString().slice(0,16);
    document.getElementById('target_date').value = now.toISOString().slice(0,10);
    </script>
</body>
</html>

