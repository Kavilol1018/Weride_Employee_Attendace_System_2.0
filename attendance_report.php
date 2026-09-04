<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}

$role_slug = $_SESSION['role'];
$current_page = 'attendance_report.php';

$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search_emp = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where_clauses = ["MONTH(al.date) = $selected_month", "YEAR(al.date) = $selected_year"];
if (!empty($selected_status)) {
    $where_clauses[] = "al.status = '$selected_status'";
}
if (!empty($search_emp)) {
    $where_clauses[] = "(e.name LIKE '%$search_emp%' OR e.emp_id LIKE '%$search_emp%')";
}
$where_sql = implode(" AND ", $where_clauses);

// Handle direct CSV download
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $month_name = date('F', mktime(0, 0, 0, $selected_month, 1));
    $filename = "Attendance_Report_{$month_name}_{$selected_year}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee ID', 'Employee Name', 'Employment Type', 'Date', 'Clock In', 'Clock Out', 'Work Hours', 'Status', 'Notes']);
    
    $exp_sql = "SELECT al.*, e.name, e.employment_type FROM attendance_logs al JOIN employees e ON al.emp_id = e.emp_id WHERE $where_sql ORDER BY al.date DESC, e.name ASC";
    $exp_res = mysqli_query($conn, $exp_sql);
    
    while ($r = mysqli_fetch_assoc($exp_res)) {
        fputcsv($out, [
            $r['emp_id'],
            $r['name'],
            $r['employment_type'],
            $r['date'],
            $r['clock_in'] ? date('h:i A', strtotime($r['clock_in'])) : '-',
            $r['clock_out'] ? date('h:i A', strtotime($r['clock_out'])) : '-',
            $r['work_hours'] . ' hrs',
            $r['status'],
            $r['notes'] ?? ''
        ]);
    }
    fclose($out);
    exit();
}

$logs_sql = "SELECT al.*, e.name, e.employment_type FROM attendance_logs al JOIN employees e ON al.emp_id = e.emp_id WHERE $where_sql ORDER BY al.date DESC, e.name ASC";
$logs_res = mysqli_query($conn, $logs_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - WeRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; color: #0f172a; }
        .main-content { flex: 1; padding: 25px; box-sizing: border-box; overflow-y: auto; height: 100vh; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 700; }
        
        .filters { display: flex; gap: 10px; align-items: center; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; flex-wrap: wrap; }
        .form-select, .form-control { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        
        .btn { padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 14px; }
        .btn:hover { background: #2563eb; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 20px; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; font-size: 14px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #64748b; }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .badge-present { background: #dcfce7; color: #166534; }
        .badge-late { background: #fef3c7; color: #92400e; }
        .badge-early { background: #e0e7ff; color: #3730a3; }
        .badge-absent { background: #fee2e2; color: #991b1b; }
        
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .filters, body.dark-mode .card { background: #1e293b; border-color: #334155; }
        body.dark-mode th, body.dark-mode td { border-color: #334155; }
        body.dark-mode th { background: #0f172a; color: #94a3b8; }
    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Attendance History & Detailed Reports</h1>
        </div>

        <div class="filters">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                <select name="month" class="form-select">
                    <?php for($m=1; $m<=12; ++$m): ?>
                        <option value="<?=$m?>" <?= $m == $selected_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>

                <select name="year" class="form-select">
                    <?php for($y=date('Y')-2; $y<=date('Y')+1; ++$y): ?>
                        <option value="<?=$y?>" <?= $y == $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>

                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="P" <?= $selected_status === 'P' ? 'selected' : '' ?>>Present (On-Time)</option>
                    <option value="LATE" <?= $selected_status === 'LATE' ? 'selected' : '' ?>>Late</option>
                    <option value="EARLY" <?= $selected_status === 'EARLY' ? 'selected' : '' ?>>Early Leave</option>
                    <option value="A" <?= $selected_status === 'A' ? 'selected' : '' ?>>Absent</option>
                </select>

                <input type="text" name="search" class="form-control" placeholder="Search Employee..." value="<?= htmlspecialchars($search_emp) ?>">

                <button type="submit" class="btn">Filter</button>
                <a href="?month=<?=$selected_month?>&year=<?=$selected_year?>&status=<?=urlencode($selected_status)?>&action=export" class="btn btn-success">📊 Export CSV</a>
            </form>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Employment Type</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Work Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_res && mysqli_num_rows($logs_res) > 0): ?>
                        <?php while ($r = mysqli_fetch_assoc($logs_res)): 
                            $st = strtoupper($r['status']);
                            $badge_cls = 'badge-present';
                            if ($st === 'LATE') $badge_cls = 'badge-late';
                            elseif ($st === 'EARLY') $badge_cls = 'badge-early';
                            elseif ($st === 'A') $badge_cls = 'badge-absent';
                        ?>
                            <tr>
                                <td><?= date('d M Y (D)', strtotime($r['date'])) ?></td>
                                <td><b>#<?= htmlspecialchars($r['emp_id']) ?></b></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars($r['employment_type']) ?></td>
                                <td><?= $r['clock_in'] ? date('h:i A', strtotime($r['clock_in'])) : '-' ?></td>
                                <td><?= $r['clock_out'] ? date('h:i A', strtotime($r['clock_out'])) : '-' ?></td>
                                <td><?= $r['work_hours'] ?> hrs</td>
                                <td><span class="badge <?= $badge_cls ?>"><?= $st ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-slate-500" style="text-align: center;  padding: 30px;">No attendance records found for this filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

