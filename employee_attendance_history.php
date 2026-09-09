<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$current_emp_id = $_SESSION['emp_id'];

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$display_date = date('M d, Y', strtotime($start_date));
if ($start_date !== $end_date) {
    $display_date .= " - " . date('M d, Y', strtotime($end_date));
}

// BUILD QUERY WITH FILTERS
$where_clauses = ["al.clock_in IS NOT NULL", "al.emp_id = '$current_emp_id'"];
$where_clauses[] = "DATE(al.clock_in) BETWEEN '$start_date' AND '$end_date'";

$where_sql = implode(' AND ', $where_clauses);

$history_sql = "
    SELECT 
        al.id, al.clock_in, al.clock_out,
        TIMESTAMPDIFF(MINUTE, al.clock_in, al.clock_out) as duration_min,
        al.status
    FROM attendance_logs al
    WHERE $where_sql
    ORDER BY al.clock_in DESC
";
$history_result = mysqli_query($conn, $history_sql);

// STATS
$today_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM attendance_logs WHERE clock_in IS NOT NULL AND emp_id = '$current_emp_id' AND DATE(clock_in) = CURDATE()"))['cnt'];
$avg_result = mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(MINUTE, clock_in, clock_out)) as avg_min FROM attendance_logs WHERE clock_in IS NOT NULL AND emp_id = '$current_emp_id' AND DATE(clock_in) = CURDATE() AND clock_out IS NOT NULL");
$avg_row = mysqli_fetch_assoc($avg_result);
$avg_duration = $avg_row['avg_min'] ? round($avg_row['avg_min'] / 60, 1) : 0;
$overtime_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM attendance_logs WHERE clock_in IS NOT NULL AND emp_id = '$current_emp_id' AND clock_out IS NULL AND DATE(clock_in) = CURDATE()"))['cnt'];

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=my_attendance_history_' . $start_date . '_to_' . $end_date . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Date', 'Time Started', 'Time Ended', 'Duration (hrs)', 'Status'));
    
    if ($history_result && mysqli_num_rows($history_result) > 0) {
        mysqli_data_seek($history_result, 0);
        while ($row = mysqli_fetch_assoc($history_result)) {
            $duration = isset($row['duration_min']) ? round($row['duration_min'] / 60, 1) : 0;
            $is_ongoing = empty($row['clock_out']);
            $status = $is_ongoing ? 'Ongoing' : 'Complete';
            
            fputcsv($output, array(
                date('M d, Y', strtotime($row['clock_in'])),
                date('h:i A', strtotime($row['clock_in'])),
                $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '--',
                $duration,
                $status
            ));
        }
    } else {
        fputcsv($output, array('No records found'));
    }
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - My Attendance History</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        .sidebar { width: 260px; background-color: #fff; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.02); z-index: 10; }
        .sidebar-header { padding: 30px 24px; font-size: 20px; font-weight: 800; color: #0f172a; }
        .nav-menu { list-style: none; padding: 10px 0; }
        .nav-item { padding: 14px 24px; cursor: pointer; color: #64748b; font-weight: 600; margin: 4px 16px; border-radius: 12px; transition: 0.2s; text-decoration: none; display: block; }
        .nav-item:hover { background-color: #f1f5f9; }
        .nav-item.active { background-color: #eff6ff; color: #3b82f6; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 30px; }
        /* Premium stat cards are styled in dark_mode.css */
        .stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
        .stat-subtitle { font-size: 12px; color: #94a3b8; margin-top: 6px; font-weight: 600; }

        .calendar-wrapper { background: var(--bg-card, #ffffff); padding: 24px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); border: 1px solid var(--border-color, rgba(226, 232, 240, 0.5)); margin-bottom: 24px; max-width: 450px; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .calendar-title { font-size: 18px; font-weight: 700; color: #0f172a; }
        .calendar-nav { display: flex; gap: 6px; }
        .btn-cal-nav { background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-weight: 600; color: #475569; text-decoration: none; transition: 0.2s; font-size: 12px; }
        .btn-cal-nav:hover { background: #e2e8f0; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .cal-day-header { text-align: center; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding-bottom: 4px; }
        .cal-day { height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 13px; font-weight: 600; color: #334155; text-decoration: none; background: #f8fafc; border: 1px solid transparent; transition: 0.2s; }
        .cal-day:hover { background: #eff6ff; color: #3b82f6; }
        .cal-day.empty { background: transparent; pointer-events: none; }
        .cal-day.active { background: #3b82f6; color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .cal-day.today { border: 1px solid #3b82f6; }

        .table-container { background: transparent; padding: 10px 0; }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .table-title { font-weight: 700; color: #0f172a; font-size: 18px; }
        .result-count { background: #f1f5f9; padding: 6px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; color: #64748b; }

        table { width: 100%; border-collapse: separate; border-spacing: 0 14px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 18px 20px; background: #ffffff; border-top: 1px solid rgba(226,232,240,0.6); border-bottom: 1px solid rgba(226,232,240,0.6); transition: all 0.3s ease; }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226,232,240,0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226,232,240,0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        tr:hover td { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.08); }

        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-block; }
        .badge-ok { background-color: #ecfdf5; color: #059669; }
        .badge-overtime { background-color: #fef2f2; color: #ef4444; }
        .badge-ongoing { background-color: #fffbeb; color: #d97706; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state-text { font-size: 16px; font-weight: 600; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">My Attendance History</div>
        </header>

        <div class="content">
            <div class="stats-grid">
                <div class="premium-stat-card">
                    <div class="icon-box" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                    <div class="info-area">
                        <div class="info-title">Days Present</div>
                        <div class="info-value"><?php echo $today_total; ?></div>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Days checked in</div>
                    </div>
                </div>
                <div class="premium-stat-card">
                    <div class="icon-box" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <div class="info-area">
                        <div class="info-title">Avg Working Hours</div>
                        <div class="info-value"><?php echo $avg_duration; ?><span style="font-size: 14px; color: #94a3b8; font-weight: normal;"> hrs</span></div>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Average hours worked</div>
                    </div>
                </div>
                <div class="premium-stat-card">
                    <div class="icon-box text-red-500" style="background: rgba(239, 68, 68, 0.1); ">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </div>
                    <div class="info-area">
                        <div class="info-title">Incomplete Shifts</div>
                        <div class="info-value" style="color: <?php echo $overtime_count > 0 ? '#ef4444' : 'inherit'; ?>;"><?php echo $overtime_count; ?></div>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Missing clock out</div>
                    </div>
                </div>
            </div>

            <div class="calendar-wrapper no-print" style="max-width: 100%;">
                <form method="GET" action="">
                    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                        <div>
                            <label style="display:block; font-weight: 600; margin-bottom: 8px;">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display:block; font-weight: 600; margin-bottom: 8px;">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;">
                        </div>
                        <div style="padding-top: 29px;">
                            <button type="submit" class="bg-blue-600 text-white" style="  border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">Apply Filter</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">My Records — <?php echo $display_date; ?></h3>
                    <?php $total_rows = $history_result ? mysqli_num_rows($history_result) : 0; ?>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span class="result-count"><?php echo $total_rows; ?> records</span>
                        <?php $export_params = http_build_query(array_merge($_GET, ['export_csv' => 1])); ?>
                        <a href="?<?php echo $export_params; ?>" style="background: transparent; color: #475569; padding: 6px 14px; font-size: 13px; text-decoration: none; border-radius: 8px; font-weight: 600; border: 1px solid #cbd5e1; transition: 0.2s;">Export CSV</a>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time Started</th>
                            <th>Time Ended</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($history_result && mysqli_num_rows($history_result) > 0) {
                            while ($row = mysqli_fetch_assoc($history_result)) {
                                $duration = isset($row['duration_min']) ? round($row['duration_min'] / 60, 1) : 0;
                                $is_ongoing = empty($row['clock_out']);
                                $duration_style = $is_ongoing ? "color: #d97706; font-weight: 700;" : "color: #059669; font-weight: 600;";
                                echo "<tr>";
                                echo "<td style='color: #0f172a; font-weight: 600;'>" . date('M d, Y', strtotime($row['clock_in'])) . "</td>";
                                echo "<td style='font-weight: 500;'>" . date('h:i A', strtotime($row['clock_in'])) . "</td>";
                                echo "<td style='font-weight: 500;'>" . ($row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '--') . "</td>";
                                echo "<td style='$duration_style'>" . $duration . " hrs" . ($is_ongoing ? " ⏳" : "") . "</td>";
                                echo "<td><span class='badge " . ($is_ongoing ? "badge-ongoing" : "badge-ok") . "'>" . ($is_ongoing ? "Ongoing" : "Complete") . "</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>
                                    <div class='empty-state'>
                                        <div class='empty-state-icon'>📭</div>
                                        <div class='empty-state-text'>No attendance records found. Try adjusting your filters.</div>
                                    </div>
                                  </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
