<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role_slug = $_SESSION['role'];
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-d');
$search_name = isset($_GET['search_name']) ? mysqli_real_escape_string($conn, $_GET['search_name']) : '';

$where_clause = "a.date BETWEEN '$start_date' AND '$end_date'";
if ($search_name) {
    $where_clause .= " AND e.name LIKE '%$search_name%'";
}
// If TL, maybe restrict to their department? Let's assume standard access for now, or match HR/Admin.

$display_date = date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date));

// Fetch report - per-day breakdown with clock in/out
$report_sql = "SELECT e.emp_id, e.name, e.department,
               a.date,
               a.clock_in,
               a.clock_out,
               a.work_hours
               FROM employees e
               JOIN attendance_logs a ON e.emp_id = a.emp_id
               WHERE $where_clause
               ORDER BY e.name ASC, a.date DESC";
$report_result = mysqli_query($conn, $report_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Hours Report - WeRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        
        .badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-high { background: #ecfdf5; color: #059669; }
        .badge-low { background: #fef2f2; color: #ef4444; }

        /* Responsive styles */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        
        @media (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .main-content { padding: 20px; }
        }

        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .filter-card { background: #1e293b; border-color: #334155; }
        body.dark-mode .form-control { background: #0f172a; border-color: #475569; color: white; }
        body.dark-mode th { background: #0f172a; color: #94a3b8; border-bottom-color: #334155; }
        body.dark-mode td { background: #1e293b; border-bottom-color: #334155; }
        body.dark-mode tr:hover td { background: #0f172a; }
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
                <h1 class="page-title">Work Hours Report</h1>
                <p class="text-slate-500" style=" margin-top: 8px;">Review total worked hours between <strong><?= $display_date ?></strong></p>
            </div>
            <div>
                <button onclick="window.print()" class="btn bg-slate-200 text-slate-900" >🖨️ Print Report</button>
            </div>
        </div>

        <div class="filter-card no-print">
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
                        <label class="text-slate-600" style="display:block; font-weight: 600; margin-bottom: 8px; font-size:14px; ">Employee Name</label>
                        <input type="text" name="search_name" class="form-control" value="<?= htmlspecialchars($search_name) ?>" placeholder="Search by name...">
                    </div>
                    <div>
                        <button type="submit" class="btn" style="height: 42px; width: 100%;">Apply Filters</button>
                    </div>
                </div>
                <!-- Quick filters -->
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button type="button" class="btn bg-slate-100 text-slate-600" style="  padding: 6px 12px; font-size: 12px;" onclick="setDates(0)">Today</button>
                    <button type="button" class="btn bg-slate-100 text-slate-600" style="  padding: 6px 12px; font-size: 12px;" onclick="setDates(7)">Last 7 Days</button>
                    <button type="button" class="btn bg-slate-100 text-slate-600" style="  padding: 6px 12px; font-size: 12px;" onclick="setDates(30)">Last 30 Days</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours Worked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($report_result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($report_result)):
                            $wh = (float)$row['work_hours'];
                            $ci = !empty($row['clock_in']) ? date('h:i A', strtotime($row['clock_in'])) : '--';
                            $co = !empty($row['clock_out']) ? date('h:i A', strtotime($row['clock_out'])) : '--';
                        ?>
                        <tr>
                            <td style="font-weight: 500;" class="text-muted"><?= htmlspecialchars($row['emp_id']) ?></td>
                            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                            <td class="text-muted"><?= htmlspecialchars($row['department'] ?? 'General') ?></td>
                            <td style="font-weight: 600; color: #475569;"><?= date('d M Y', strtotime($row['date'])) ?></td>
                            <td>
                                <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 8px; font-size: 13px; font-weight: 600;"><?= $ci ?></span>
                            </td>
                            <td>
                                <span style="background: #fef2f2; color: #991b1b; padding: 4px 10px; border-radius: 8px; font-size: 13px; font-weight: 600;"><?= $co ?></span>
                            </td>
                            <td>
                                <?php if($wh < 7): ?>
                                    <span class="badge badge-low"><?= number_format($wh, 1) ?>h</span>
                                <?php else: ?>
                                    <span class="badge badge-high"><?= number_format($wh, 1) ?>h</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-slate-500" style="text-align: center; padding: 40px; ">
                                <div style="font-size: 40px; margin-bottom: 10px;">📉</div>
                                <div style="font-weight: 600; font-size: 16px;">No data found for this period.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function setDates(daysBack) {
            const end = new Date();
            const start = new Date();
            if (daysBack > 0) {
                start.setDate(end.getDate() - daysBack);
            }
            
            const formatDate = (date) => date.toISOString().split('T')[0];
            
            document.querySelector('input[name="start_date"]').value = formatDate(start);
            document.querySelector('input[name="end_date"]').value = formatDate(end);
            document.forms[0].submit();
        }
    </script>
</body>
</html>

