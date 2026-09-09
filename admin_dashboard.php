<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

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
    header("Location: hr_dashboard.php");
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
        $msg = "Your lunchtime override request was " . $action . " by HR.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('{$req_data['emp_id']}', '$msg')");
    }
    
    header("Location: hr_dashboard.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        .sidebar { width: 260px; background-color: #fff; border-right: none; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.02); z-index: 10; }
        .sidebar-header { padding: 30px 24px; font-size: 20px; font-weight: 800; color: #0f172a; }
        .nav-menu { list-style: none; padding: 10px 0; }
        .nav-item { padding: 14px 24px; cursor: pointer; color: #64748b; font-weight: 600; margin: 4px 16px; border-radius: 12px; transition: 0.2s; text-decoration: none; display: block; }
        .nav-item:hover { background-color: #f1f5f9; }
        .nav-item.active { background-color: #eff6ff; color: #3b82f6; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; scrollbar-gutter: stable; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        /* Stat Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .stat-card { background: #ffffff; padding: 20px 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto; column-gap: 16px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .stat-title { grid-column: 2; grid-row: 1; color: #94a3b8; font-size: 11px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; align-self: end; }
        .stat-value { grid-column: 2; grid-row: 2; font-size: 24px; font-weight: 800; color: #0f172a; align-self: start; }
        .stat-icon { grid-column: 1; grid-row: 1 / span 2; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 0; }

        /* Floating Table */
        .table-container { background: transparent; padding: 10px 0; }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .table-title { font-weight: 700; color: #0f172a; font-size: 18px; }
        .live-badge { display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; color: #059669; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        table { width: 100%; border-collapse: separate; border-spacing: 0 16px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 20px; background: #ffffff; border-top: 1px solid rgba(226,232,240,0.6); border-bottom: 1px solid rgba(226,232,240,0.6); transition: all 0.3s ease; }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226,232,240,0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226,232,240,0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        tr.clickable:hover td { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.08); cursor: pointer; z-index: 10; }

        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-block; }
        .bg-on-break { background-color: #fef3c7; color: #d97706; }
        .bg-available { background-color: #ecfdf5; color: #059669; }
        .elapsed-text { font-size: 13px; font-weight: 600; color: #94a3b8; }
        .elapsed-text.overtime { color: #ef4444; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.2); backdrop-filter: blur(6px); justify-content: center; align-items: center; z-index: 1000; }
        .modal-box { background: rgba(255,255,255,0.95); padding: 40px; border-radius: 24px; width: 440px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5); text-align: center; position: relative; animation: modalSlideIn 0.3s ease; }
        .close-btn { position: absolute; top: 20px; right: 25px; font-size: 28px; cursor: pointer; color: #94a3b8; transition: 0.2s; background: none; border: none; }
        .close-btn:hover { color: #ef4444; }
        .profile-pic { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; margin: 0 auto 15px auto; }
        .modal-btn { background-color: #ef4444; color: white; border: none; padding: 14px 20px; border-radius: 12px; cursor: pointer; margin-top: 25px; font-weight: 700; width: 100%; font-size: 15px; transition: 0.2s; }
        .modal-btn:hover { background-color: #dc2626; transform: translateY(-2px); }

        .info-block { background: #f8fafc; padding: 20px; border-radius: 16px; text-align: left; margin-top: 20px; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; color: #475569; }
        .info-row:not(:last-child) { border-bottom: 1px solid #e2e8f0; }
        .info-label { font-weight: 600; }
        .info-value { font-weight: 600; }

        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .refresh-bar { display: flex; align-items: center; gap: 8px; color: #94a3b8; font-size: 12px; font-weight: 600; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Dashboard</div>
            <div class="refresh-bar">
                <div class="live-dot"></div>
                Auto-refreshes every 30s
            </div>
        </header>

        <div class="content">

            <!-- DASHBOARD FILTERS -->
            <details style='background: white; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 16px; box-shadow: 0 2px 4px -1px rgba(0,0,0,0.03); width: max-content;'>
                <summary class="text-slate-600" style="padding: 8px 12px; font-size: 13px; font-weight: 600;  cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 14px;">⚙️</span> View Options
                    </div>
                    <span style="font-size: 10px; color: #94a3b8; margin-left: 12px;">▼</span>
                </summary>
                <div style="padding: 8px 12px 12px 12px; border-top: 1px solid #e2e8f0; margin-top: 4px;">
                    <div style='display: flex; gap: 15px; flex-wrap: wrap;'>
                        <label style='display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #475569; cursor: pointer;'>
                            <input type='checkbox' checked onchange='document.getElementById("card-availChart").style.display = this.checked ? "block" : "none"'> Show Staff Availability
                        </label>
                        <label style='display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #475569; cursor: pointer;'>
                            <input type='checkbox' checked onchange='document.getElementById("manpower-section").style.display = this.checked ? "block" : "none"'> Show Manpower Overview
                        </label>
                    </div>
                </div>
            </details>
            <style>
                details > summary::-webkit-details-marker { display: none; }
            </style>
            <!-- END DASHBOARD FILTERS -->
    


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

            <div style="display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap;">
                <div id="card-availChart" class="chart-card bg-white" style="flex: 0 0 320px; max-width: 100%; padding: 28px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03), 0 4px 10px -5px rgba(0,0,0,0.02); border: 1px solid rgba(226,232,240,0.5);">
                    <h3 class="text-slate-900" style=" font-size: 18px; margin-bottom: 20px; font-weight: 700;">Staff Availability</h3>
                    <canvas id="manpowerChart"></canvas>
                </div>

                <div id="manpower-section" class="table-container" style="flex: 1; min-width: 300px; padding: 0;">
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
                            <th>Time Started</th>
                            <th>Limit</th>
                            <th>Elapsed</th>
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
                                $onclick = $is_on_break ? "onclick=\"openFloatingBox('" . htmlspecialchars($row['name'], ENT_QUOTES) . "', '" . htmlspecialchars($row['employment_type'], ENT_QUOTES) . "', '$break_start_display', '$elapsed_minutes', '" . htmlspecialchars($row['emp_id'], ENT_QUOTES) . "', '" . htmlspecialchars($row['break_type'], ENT_QUOTES) . "', " . $limit_arg . ")\"" : "";
                                $row_class = $is_on_break ? "class='clickable'" : "";

                                $row_status = '';
                                if ($row['leave_status']) {
                                    $row_status = 'On Leave';
                                } else if ($row['absent_status'] === 'A') {
                                    $row_status = 'Absent';
                                } else if (empty($row['clock_in_time'])) {
                                    $row_status = 'Not Clocked In';
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
                                } else if ($is_on_break) {
                                    echo "<td><span class='badge bg-on-break'>" . htmlspecialchars($row['break_type']) . "</span></td>";
                                } else {
                                    echo "<td><span class='badge bg-available'>Available</span></td>";
                                }
                                
                                $c_in_display = $row['clock_in_time'] ? date('h:i A', strtotime($row['clock_in_time'])) : '--';
                                $c_out_display = $row['clock_out_time'] ? date('h:i A', strtotime($row['clock_out_time'])) : '--';
                                
                                echo "<td style='font-size: 13px; font-weight: 600; color: #475569;'>" . $c_in_display . "</td>";
                                echo "<td style='font-size: 13px; font-weight: 600; color: #475569;'>" . $c_out_display . "</td>";
                                
                                echo "<td style='color: " . ($is_on_break ? '#0f172a; font-weight: 600;' : '#94a3b8;') . "'>" . $break_start_display . "</td>";
                                echo "<td><span class='elapsed-text'>" . $limit_text . "</span></td>";
                                echo "<td><span class='$elapsed_class'>" . $elapsed_text . "</span></td>";
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
            </div>
        </div>
    </main>

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
                <button type="submit" name="force_end_break" class="modal-btn">⚡ Force Meal Out</button>
            
                    <div style='flex: 100%; display: flex; gap: 15px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0; flex-wrap: wrap;'>
                        <label style='display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer;'>
                            <input type='checkbox' checked onchange='document.getElementById("card-avgChart").style.display = this.checked ? "block" : "none"'> Show Avg Lunchtime
                        </label>
                        <label style='display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer;'>
                            <input type='checkbox' checked onchange='document.getElementById("card-vioChart").style.display = this.checked ? "block" : "none"'> Show Violations
                        </label>
                        <label style='display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer;'>
                            <input type='checkbox' checked onchange='document.getElementById("card-availChart").style.display = this.checked ? "block" : "none"'> Show Staff Availability
                        </label>
                        <label style='display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer;'>
                            <input type='checkbox' checked onchange='document.getElementById("manpower-section").style.display = this.checked ? "block" : "none"'> Show Manpower Overview
                        </label>
                    </div>
                </form>

        </div>
    </div>

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

        document.getElementById('employeeModal').addEventListener('click', function(e) {
            if (e.target === this) closeFloatingBox();
        });

        // Auto-refresh table every 30 seconds
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newSection = doc.getElementById('manpower-section');
                    if (newSection) {
                        document.getElementById('manpower-section').innerHTML = newSection.innerHTML;
                    }
                });
        }, 30000);

        const ctx = document.getElementById('manpowerChart').getContext('2d');
        new Chart(ctx, {
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
                cutout: '75%',
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
                                const visible = chart.getDataVisibility(i);
                                if (visible) {
                                    activeStatuses.push(label);
                                }
                            });
                            
                            // Filter the table rows
                            const rows = document.querySelectorAll('#manpower-section table tbody tr');
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
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                family: "'Segoe UI', Tahoma, sans-serif",
                                size: 13,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });

        // Desktop Notifications Polling
        if ("Notification" in window) {
            Notification.requestPermission();
            setInterval(async () => {
                try {
                    let response = await fetch('api/get_new_alerts.php');
                    let data = await response.json();
                    if (data.alerts && data.alerts.length > 0) {
                        data.alerts.forEach(msg => {
                            if (Notification.permission === "granted") {
                                new Notification("WeRide Alert", { body: msg });
                            }
                            if (typeof window.showToast === 'function') {
                                window.showToast('📢 ' + msg);
                            }
                        });
                    }
                } catch (e) {}
            }, 10000); // Check every 10 seconds
        }
    </script>
    <script src="assets/js/realtime_alerts.js"></script>
</body>
</html>


