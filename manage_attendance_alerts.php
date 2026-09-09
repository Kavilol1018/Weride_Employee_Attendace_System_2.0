<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role_slug = $_SESSION['role'];
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-d');

$selected_employees = isset($_GET['employees']) ? $_GET['employees'] : [];
$emp_filter_sql = "";
if (!empty($selected_employees)) {
    $safe_emps = array_map(function($emp) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $emp) . "'";
    }, $selected_employees);
    $emp_filter_sql = " AND a.emp_id IN (" . implode(',', $safe_emps) . ") ";
}

$display_date = date('d/m/Y', strtotime($start_date));
if ($start_date !== $end_date) {
    $display_date .= " - " . date('d/m/Y', strtotime($end_date));
}

// For dropdown we need ALL employees
$emp_dropdown_list = [];
$emp_dropdown_sql = "SELECT emp_id, name FROM employees WHERE role = 'employee' ORDER BY name ASC";
$emp_dropdown_res = mysqli_query($conn, $emp_dropdown_sql);
while($e = mysqli_fetch_assoc($emp_dropdown_res)){
    $emp_dropdown_list[] = $e;
}

// Mark alerts as read when viewing this page
mysqli_query($conn, "UPDATE system_alerts SET is_read = 1 WHERE is_read = 0");

// Fetch violations
$violation_sql = "SELECT a.*, e.name, e.department 
                  FROM attendance_logs a 
                  JOIN employees e ON a.emp_id = e.emp_id 
                  WHERE a.date BETWEEN '$start_date' AND '$end_date' 
                  AND a.flag IN ('Late', 'Early', 'Both') 
                  AND NOT ((a.clock_in IS NULL OR a.clock_in = '') AND (a.clock_out IS NULL OR a.clock_out = ''))
                  $emp_filter_sql
                  ORDER BY a.date DESC, a.clock_in DESC";
$violation_result = mysqli_query($conn, $violation_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Violations - WeRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Select2 -->
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background-color: #f8fafc; color: #0f172a; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .page-title { font-size: 28px; font-weight: 700; color: #1e293b; }
        
        .filter-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 30px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 18px 20px; text-align: left; vertical-align: middle; }
        th { color: #64748b; font-weight: 600; background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px; }
        td { background: #ffffff; border-bottom: 1px solid rgba(226, 232, 240, 0.6); transition: all 0.3s ease; }
        tr:hover td { background: #f8fafc; }
        
        .btn { display: inline-flex; justify-content: center; align-items: center; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn:hover { background: #2563eb; }
        
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; }
        
        .badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
        .badge-late { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .badge-early { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }

        /* Responsive styles */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr 2fr auto; gap: 15px; align-items: end; }
        
        @media (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .main-content { padding: 20px; }
        }

        /* Select2 Theme Tweaks */
        .select2-container--default .select2-selection--multiple { border: 1px solid #cbd5e1; border-radius: 8px; min-height: 42px; padding: 2px 8px; }
        .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: #3b82f6; }

        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .filter-card { background: #1e293b; border-color: #334155; }
        body.dark-mode .form-control { background: #0f172a; border-color: #475569; color: white; }
        body.dark-mode th { background: #0f172a; color: #94a3b8; border-bottom-color: #334155; }
        body.dark-mode td { background: #1e293b; border-bottom-color: #334155; }
        body.dark-mode tr:hover td { background: #0f172a; }
        body.dark-mode .badge-late { background: rgba(217, 119, 6, 0.2); border-color: rgba(217, 119, 6, 0.3); }
        body.dark-mode .badge-early { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.3); }
        body.dark-mode .select2-container--default .select2-selection--multiple { background: #0f172a; border-color: #475569; }
        body.dark-mode .select2-dropdown { background: #1e293b; border-color: #475569; color: #f8fafc; }
        body.dark-mode .select2-container--default .select2-results__option[aria-selected=true] { background: #334155; }
        body.dark-mode .select2-container--default .select2-selection--multiple .select2-selection__choice { background: #334155; border-color: #475569; color: #f8fafc; }
        body.dark-mode .table-responsive { border-color: #334155; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Attendance Violations</h1>
                <p class="text-slate-500" style=" margin-top: 8px;">Monitoring Late Check-ins and Early Check-outs for <strong><?= $display_date ?></strong></p>
            </div>
            <div>
                <a href="attendance_report.php" class="btn bg-slate-200 text-slate-900" >Back to Full Report</a>
            </div>
        </div>

        <div class="filter-card">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label class="text-slate-600" style="display:block; font-weight: 600; margin-bottom: 8px; font-size:14px; ">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>
                    <div>
                        <label class="text-slate-600" style="display:block; font-weight: 600; margin-bottom: 8px; font-size:14px; ">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required>
                    </div>
                    <div>
                        <label class="text-slate-600" style="display:block; font-weight: 600; margin-bottom: 8px; font-size:14px; ">Filter by Employee</label>
                        <select name="employees[]" class="form-control select2" multiple="multiple" data-placeholder="Select employees...">
                            <?php foreach($emp_dropdown_list as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['emp_id']) ?>" <?= in_array($emp['emp_id'], $selected_employees) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['emp_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn" style="height: 42px; width: 100%;">Filter Results</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Violation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($violation_result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($violation_result)): ?>
                        <tr>
                            <td><strong><?= date('d M Y', strtotime($row['date'])) ?></strong></td>
                            <td>
                                <div class="text-slate-900" style="font-weight: 600; "><?= htmlspecialchars($row['name']) ?></div>
                                <div class="text-slate-500" style="font-size: 12px; "><?= htmlspecialchars($row['emp_id']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['department'] ?? 'General') ?></td>
                            <td style="color: <?= in_array($row['flag'], ['Late', 'Both']) ? '#d97706' : '#10b981' ?>; font-weight: 600;">
                                <?= $row['clock_in'] ? date('h:i A', strtotime($row['clock_in'])) : '-' ?>
                            </td>
                            <td style="color: <?= in_array($row['flag'], ['Early', 'Both']) ? '#ef4444' : '#10b981' ?>; font-weight: 600;">
                                <?= $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '-' ?>
                            </td>
                            <td>
                                <?php if($row['flag'] == 'Both'): ?>
                                    <span class="badge badge-late" style="margin-bottom:4px;">LATE IN</span><br>
                                    <span class="badge badge-early">EMERGENCY OUT</span>
                                <?php elseif($row['flag'] == 'Late'): ?>
                                    <span class="badge badge-late">LATE IN</span>
                                <?php elseif($row['flag'] == 'Early'): ?>
                                    <span class="badge badge-early">EMERGENCY OUT</span>
                                <?php else: ?>
                                    <span class="badge"><?= htmlspecialchars($row['flag']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-slate-500" style="text-align: center; padding: 40px; ">
                                <div style="font-size: 40px; margin-bottom: 10px;">🎉</div>
                                <div style="font-weight: 600; font-size: 16px;">No violations found!</div>
                                <div style="font-size: 14px; margin-top: 5px;">All check-ins and check-outs are within standard times.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            
        });
    </script>
</body>
</html>

