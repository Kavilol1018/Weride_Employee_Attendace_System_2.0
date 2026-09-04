<?php
session_start();
// Allow admin, hr, and tl roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$user_role = $_SESSION['role'];

// DATE FILTER
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-d');

$selected_employees = isset($_GET['employees']) ? $_GET['employees'] : [];
$emp_filter_sql = "";
if (!empty($selected_employees)) {
    $safe_emps = array_map(function($emp) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $emp) . "'";
    }, $selected_employees);
    $emp_filter_sql = " AND lb.employee_id IN (" . implode(',', $safe_emps) . ") ";
}

// Fetch employees for multi-select
$emp_dropdown_sql = "SELECT emp_id, name FROM employees ORDER BY name ASC";
$emp_dropdown_res = mysqli_query($conn, $emp_dropdown_sql);
$all_employees = [];
while($e = mysqli_fetch_assoc($emp_dropdown_res)){
    $all_employees[] = $e;
}

// SUMMARY STATS
$total_meals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lunch_breaks lb WHERE lb.status = 'returned' AND DATE(lb.break_start) BETWEEN '$start_date' AND '$end_date' $emp_filter_sql"))['cnt'];

$avg_result = mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end)) as avg_min FROM lunch_breaks lb WHERE lb.status = 'returned' AND DATE(lb.break_start) BETWEEN '$start_date' AND '$end_date' AND lb.break_end IS NOT NULL $emp_filter_sql");
$avg_row = mysqli_fetch_assoc($avg_result);
$avg_duration = $avg_row['avg_min'] ? round($avg_row['avg_min']) : 0;

$overtime_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lunch_breaks lb WHERE lb.status = 'returned' AND TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) > 60 AND DATE(lb.break_start) BETWEEN '$start_date' AND '$end_date' $emp_filter_sql"))['cnt'];

$within_limit = $total_meals - $overtime_count;

$total_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employees"))['cnt'];
$no_break_count = 0; // Not applicable for multi-select logic

// FULL REPORT DATA — all completed lunchtimes
$report_sql = "
    SELECT 
        lb.break_id,
        e.emp_id,
        e.name,
        e.employment_type,
        lb.break_start,
        lb.break_end,
        TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) as duration_min,
        lb.status
    FROM lunch_breaks lb
    LEFT JOIN employees e ON lb.employee_id = e.emp_id
    WHERE lb.status = 'returned' AND DATE(lb.break_start) BETWEEN '$start_date' AND '$end_date' $emp_filter_sql
    ORDER BY lb.break_start ASC
";
$report_result = mysqli_query($conn, $report_sql);

// Determine sidebar prefix based on role
$prefix = $user_role;
$sidebar_title = ucfirst($user_role) . ' Dashboard';
if ($user_role === 'admin') $sidebar_title = 'Admin Dashboard';
elseif ($user_role === 'hr') $sidebar_title = 'HR Dashboard';
elseif ($user_role === 'tl') $sidebar_title = 'TL Dashboard';

// Format for display (dd/mm/yyyy)
$display_date = date('d/m/Y', strtotime($start_date));
if ($start_date !== $end_date) {
    $display_date .= " - " . date('d/m/Y', strtotime($end_date));
}

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lunchtime_report_' . $start_date . '_to_' . $end_date . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Name', 'Type', 'Time Started', 'Time Ended', 'Duration (min)', 'Status'));
    
    if ($report_result && mysqli_num_rows($report_result) > 0) {
        mysqli_data_seek($report_result, 0);
        while ($row = mysqli_fetch_assoc($report_result)) {
            $duration = $row['duration_min'] ?? 0;
            $is_overtime = $duration > 60;
            $status = $is_overtime ? 'Overtime' : 'Within Limit';
            
            fputcsv($output, array(
                $row['emp_id'],
                $row['name'],
                $row['employment_type'],
                date('h:i A', strtotime($row['break_start'])),
                $row['break_end'] ? date('h:i A', strtotime($row['break_end'])) : '--',
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
    <title>WeRide - Lunchtime History | <?php echo $display_date; ?></title>
    <style>
        /* Base Reset & Typography */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        /* Sidebar */
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

        /* Print Report Header (hidden on screen, shown on print) */
        .print-header { display: none; }

        /* Calendar UI */
        .calendar-wrapper {
            background: var(--bg-card, #ffffff);
            padding: 16px;
            border-radius: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color, rgba(226, 232, 240, 0.5));
            margin-bottom: 24px;
            max-width: 400px;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .calendar-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .calendar-nav {
            display: flex;
            gap: 6px;
        }
        .btn-cal-nav {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: 0.2s;
            font-size: 12px;
        }
        .btn-cal-nav:hover {
            background: #e2e8f0;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .cal-day-header {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            padding-bottom: 4px;
        }
        .cal-day {
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            text-decoration: none;
            background: #f8fafc;
            border: 1px solid transparent;
            transition: 0.2s;
        }
        .cal-day:hover {
            background: #eff6ff;
            color: #3b82f6;
        }
        .cal-day.empty {
            background: transparent;
            pointer-events: none;
        }
        .cal-day.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .cal-day.today {
            border: 1px solid #3b82f6;
            color: #3b82f6;
        }
        .cal-day.today.active {
            color: white;
        }

        .btn-print { 
            background: #1e293b; color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex; align-items: center; gap: 6px;
        }
        .btn-print:hover { background: #0f172a; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15); }

        /* Summary Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .stat-card { background: #ffffff; padding: 20px 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto auto; column-gap: 16px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .stat-title { grid-column: 2; grid-row: 1; color: #94a3b8; font-size: 11px; font-weight: 700; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; align-self: end; }
        .stat-value { grid-column: 2; grid-row: 2; font-size: 24px; font-weight: 800; color: #0f172a; align-self: center; }
        .stat-subtitle { grid-column: 2; grid-row: 3; font-size: 11px; color: #94a3b8; margin-top: 2px; font-weight: 600; align-self: start; }
        .stat-icon { grid-column: 1; grid-row: 1 / span 3; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 0; }

        /* Report Table */
        .report-section { 
            background: #ffffff; 
            padding: 28px; 
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(226, 232, 240, 0.5);
        }
        .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .report-title { font-weight: 700; color: #0f172a; font-size: 18px; }
        .report-badge { background: #f1f5f9; padding: 6px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; color: #64748b; }

        .report-table { width: 100%; border-collapse: collapse; text-align: left; }
        .report-table thead { background: #f8fafc; }
        .report-table th { 
            padding: 14px 18px; 
            color: #64748b; 
            font-weight: 600; 
            font-size: 13px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            border-bottom: 2px solid #e2e8f0;
        }
        .report-table td { 
            padding: 14px 18px; 
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        .report-table tbody tr { transition: background 0.15s; }
        .report-table tbody tr:hover { background: #f8fafc; }
        .report-table tbody tr:last-child td { border-bottom: none; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-ok { background-color: #ecfdf5; color: #059669; }
        .badge-overtime { background-color: #fef2f2; color: #ef4444; }

        .row-number { color: #94a3b8; font-weight: 600; font-size: 13px; }

        /* Footer Summary */
        .report-footer { 
            margin-top: 16px; 
            padding-top: 16px; 
            border-top: 2px solid #e2e8f0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .footer-stat { font-size: 13px; color: #64748b; font-weight: 600; }
        .footer-stat span { color: #0f172a; font-weight: 800; }

        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state-text { font-size: 16px; font-weight: 600; }

        /* ========== PRINT STYLES ========== */
        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

            body { display: block; background: white; height: auto; }
            .sidebar, .topbar, .calendar-wrapper, .btn-print, .no-print { display: none !important; }
            .main-content { overflow: visible; }
            .content { padding: 0; }

            .print-header { 
                display: block !important; 
                text-align: center; 
                margin-bottom: 30px; 
                padding-bottom: 20px; 
                border-bottom: 3px solid #0f172a; 
            }
            .print-logo { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
            .print-subtitle { font-size: 14px; color: #64748b; margin-bottom: 10px; }
            .print-report-title { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
            .print-date { font-size: 14px; color: #475569; }
            .print-generated { font-size: 11px; color: #94a3b8; margin-top: 6px; }

            .stats-grid { 
                display: flex !important; 
                gap: 12px; 
                margin-bottom: 24px; 
                page-break-inside: avoid;
            }
            .stat-card { 
                flex: 1; 
                padding: 16px; 
                border: 1px solid #e2e8f0; 
                border-radius: 8px; 
                box-shadow: none;
                break-inside: avoid;
            }
            .stat-card:hover { transform: none; box-shadow: none; }
            .stat-icon { width: 32px; height: 32px; font-size: 16px; margin-bottom: 8px; }
            .stat-value { font-size: 24px; }

            .report-section { 
                box-shadow: none; 
                border: 1px solid #e2e8f0; 
                border-radius: 8px; 
                padding: 16px;
            }
            .report-table { font-size: 12px; }
            .report-table th { padding: 10px 12px; font-size: 11px; }
            .report-table td { padding: 10px 12px; font-size: 12px; }
            .report-table tbody tr:hover { background: transparent; }

            .report-footer { 
                page-break-inside: avoid; 
                margin-top: 20px; 
                padding-top: 12px;
            }

            /* Print Signature area */
            .print-signature { 
                display: flex !important; 
                justify-content: space-between; 
                margin-top: 60px; 
                padding-top: 20px;
            }
            .signature-block { 
                text-align: center; 
                width: 200px; 
            }
            .signature-line { 
                border-top: 1px solid #334155; 
                margin-bottom: 6px; 
                margin-top: 50px; 
            }
            .signature-label { 
                font-size: 12px; 
                color: #64748b; 
                font-weight: 600; 
            }
        }

        .print-signature { display: none; }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <!-- PRINT HEADER (only visible when printing) -->
        <div class="print-header">
            <div class="print-logo">WeRide</div>
            <div class="print-subtitle">Lunchtime Management System</div>
            <div class="print-report-title">Lunchtime Report</div>
            <div class="print-date"><?php echo $display_date; ?></div>
            <div class="print-generated">Generated on: <?php echo date('d/m/Y — h:i A'); ?> by <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo strtoupper($user_role); ?>)</div>
        </div>

        <header class="topbar">
            <div class="page-title">Lunchtime Report</div>
        </header>

        <div class="content">
            <!-- FILTER FORM -->
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
                        <div style="flex: 1; min-width: 250px;">
                            <label style="display:block; font-weight: 600; margin-bottom: 8px;">Filter by Employees</label>
                            <select name="employees[]" id="employee_select" multiple>
                                <?php foreach($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['emp_id']); ?>" <?php echo in_array($emp['emp_id'], $selected_employees) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['name']); ?> (<?php echo htmlspecialchars($emp['emp_id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="padding-top: 29px;">
                            <button type="submit" class="btn-print">Apply Filter</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- SUMMARY STAT CARDS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-blue-50" style=" color: #3b82f6;">🍽️</div>
                    <div class="stat-title">Total Meals</div>
                    <div class="stat-value"><?php echo $total_meals; ?></div>
                    <div class="stat-subtitle">Lunchtimes taken</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-green-50 text-green-600" >⏱️</div>
                    <div class="stat-title">Avg Duration</div>
                    <div class="stat-value"><?php echo $avg_duration; ?><span style="font-size: 14px; color: #94a3b8;"> min</span></div>
                    <div class="stat-subtitle">Average break time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-emerald-50 text-emerald-600" >✅</div>
                    <div class="stat-title">Within Limit</div>
                    <div class="stat-value text-emerald-600" ><?php echo $within_limit; ?></div>
                    <div class="stat-subtitle">Under 60 minutes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-red-50 text-red-500" >⚠️</div>
                    <div class="stat-title">Overtime</div>
                    <div class="stat-value" style="color: <?php echo $overtime_count > 0 ? '#ef4444' : '#0f172a'; ?>;"><?php echo $overtime_count; ?></div>
                    <div class="stat-subtitle">Over 60 minutes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-amber-100 text-amber-600" >🚫</div>
                    <div class="stat-title">No Break</div>
                    <div class="stat-value text-amber-600" ><?php echo $no_break_count; ?></div>
                    <div class="stat-subtitle">Did not take break</div>
                </div>
            </div>

            <!-- FULL REPORT TABLE -->
            <div class="report-section">
                <div class="report-header">
                    <h3 class="report-title">Lunchtime Records — <?php echo $display_date; ?></h3>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span class="report-badge"><?php echo $total_meals; ?> records</span>
                        <?php 
                        $export_params = http_build_query(array_merge($_GET, ['export_csv' => 1])); 
                        ?>
                        <a href="?<?php echo $export_params; ?>" class="no-print" style="background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 6px 14px; font-size: 13px; text-decoration: none; border-radius: 8px; font-weight: 600; box-shadow: none;">Export CSV</a>
                    </div>
                </div>

                <?php if ($report_result && mysqli_num_rows($report_result) > 0): ?>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee ID</th>

                                <th>Employee Name</th>
                                <th>Type</th>
                                <th>Time Started</th>
                                <th>Time Ended</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $row_num = 1;
                            while ($row = mysqli_fetch_assoc($report_result)) {
                                $duration = $row['duration_min'] ?? 0;
                                $is_overtime = $duration > 60;

                                $type_colors = [
                                    'Full time' => ['bg' => '#ecfdf5', 'text' => '#059669'],
                                    'Intern' => ['bg' => '#fdf4ff', 'text' => '#a855f7'],
                                ];
                                $color = $type_colors[$row['employment_type'] ?? ''] ?? ['bg' => '#f1f5f9', 'text' => '#475569'];

                                echo "<tr>";
                                echo "<td class='row-number'>" . $row_num++ . "</td>";
                                echo "<td style='font-weight: 600; color: #0f172a;'>#" . htmlspecialchars($row['emp_id']) . "</td>";
                                echo "<td style='font-weight: 500;'>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td><span style='background: {$color['bg']}; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; color: {$color['text']};'>" . htmlspecialchars($row['employment_type']) . "</span></td>";
                                echo "<td>" . date('h:i A', strtotime($row['break_start'])) . "</td>";
                                echo "<td>" . ($row['break_end'] ? date('h:i A', strtotime($row['break_end'])) : '--') . "</td>";
                                
                                $dur_style = $is_overtime ? "color: #ef4444; font-weight: 700;" : "color: #059669; font-weight: 600;";
                                echo "<td style='$dur_style'>" . $duration . " min" . ($is_overtime ? " ⚠️" : "") . "</td>";
                                
                                if ($is_overtime) {
                                    echo "<td><span class='badge badge-overtime'>Overtime</span></td>";
                                } else {
                                    echo "<td><span class='badge badge-ok'>Within Limit</span></td>";
                                }
                                
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- REPORT FOOTER SUMMARY -->
                    <div class="report-footer">
                        <div class="footer-stat">Total Meals: <span><?php echo $total_meals; ?></span></div>
                        <div class="footer-stat">Average: <span><?php echo $avg_duration; ?> min</span></div>
                        <div class="footer-stat">Within Limit: <span class="text-emerald-600" ><?php echo $within_limit; ?></span></div>
                        <div class="footer-stat">Overtime: <span class="text-red-500" ><?php echo $overtime_count; ?></span></div>
                        <div class="footer-stat">No Break Taken: <span class="text-amber-600" ><?php echo $no_break_count; ?></span></div>
                    </div>

                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <div class="empty-state-text">No lunchtime records found for <?php echo $display_date; ?>.</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SIGNATURE AREA (only visible in print) -->
            <div class="print-signature">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">Prepared By</div>
                </div>
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">Verified By</div>
                </div>
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">Approved By</div>
                </div>
            </div>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const element = document.getElementById('employee_select');
            const choices = new Choices(element, {
                removeItemButton: true,
                searchPlaceholderValue: "Search employees...",
                placeholder: true,
                placeholderValue: "Select employees"
            });
        });


</body>
</html>
