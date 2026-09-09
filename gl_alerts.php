<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gl') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-d');

$selected_employees = isset($_GET['employees']) ? $_GET['employees'] : [];
$emp_filter_sql = "";
if (!empty($selected_employees)) {
    $safe_emps = array_map(function($emp) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $emp) . "'";
    }, $selected_employees);
    $emp_filter_sql = " AND e.emp_id IN (" . implode(',', $safe_emps) . ") ";
}

$display_date = date('d/m/Y', strtotime($start_date));
if ($start_date !== $end_date) {
    $display_date .= " - " . date('d/m/Y', strtotime($end_date));
}

$violations = [];

// Fetch employees
$emp_sql = "SELECT e.emp_id, e.name, e.lunch_window FROM employees e WHERE e.role = 'employee' AND e.group_leader_id = '{$_SESSION['emp_id']}' $emp_filter_sql";
$emp_result = mysqli_query($conn, $emp_sql);
$employees = [];
$emp_dropdown_list = [];
while ($row = mysqli_fetch_assoc($emp_result)) {
    $employees[] = $row;
}
// For dropdown we need ALL employees
$emp_dropdown_sql = "SELECT emp_id, name FROM employees WHERE role = 'employee' AND group_leader_id = '{$_SESSION['emp_id']}' ORDER BY name ASC";
$emp_dropdown_res = mysqli_query($conn, $emp_dropdown_sql);
while($e = mysqli_fetch_assoc($emp_dropdown_res)){
    $emp_dropdown_list[] = $e;
}

// Fetch all breaks in range
$break_sql = "SELECT employee_id, DATE(break_start) as b_date, break_start, break_end, status,
              TIMESTAMPDIFF(MINUTE, break_start, CURRENT_TIMESTAMP) as current_duration,
              TIMESTAMPDIFF(MINUTE, break_start, break_end) as finished_duration
              FROM lunch_breaks 
              WHERE DATE(break_start) BETWEEN '$start_date' AND '$end_date'";
$break_result = mysqli_query($conn, $break_sql);
$breaks_by_emp_date = [];
while ($row = mysqli_fetch_assoc($break_result)) {
    $breaks_by_emp_date[$row['employee_id']][$row['b_date']] = $row;
}

// Fetch absences
$absent_sql = "SELECT emp_id, date FROM attendance_logs WHERE date BETWEEN '$start_date' AND '$end_date' AND status = 'A'";
$absent_res = mysqli_query($conn, $absent_sql);
$absences = [];
if ($absent_res) {
    while ($r = mysqli_fetch_assoc($absent_res)) {
        $absences[$r['emp_id']][$r['date']] = true;
    }
}

// Fetch leaves
$leave_sql = "SELECT emp_id, start_date, end_date, leave_type FROM leave_requests WHERE status = 'approved' AND start_date <= '$end_date' AND end_date >= '$start_date'";
$leave_res = mysqli_query($conn, $leave_sql);
$leaves = [];
if ($leave_res) {
    while ($r = mysqli_fetch_assoc($leave_res)) {
        $ls = new DateTime($r['start_date']);
        $le = new DateTime($r['end_date']);
        while ($ls <= $le) {
            $d = $ls->format('Y-m-d');
            if ($d >= $start_date && $d <= $end_date) {
                $leaves[$r['emp_id']][$d] = $r['leave_type'];
            }
            $ls->modify('+1 day');
        }
    }
}

$current_date_obj = new DateTime($start_date);
$end_date_obj = new DateTime($end_date);
$today_str = date('Y-m-d');
$current_time = date('H:i');

foreach ($employees as $e) {
    $emp_id = $e['emp_id'];
    $window = $e['lunch_window'] ?? '12:00-13:00';
    $window_parts = explode('-', $window);
    $end_time = $window_parts[1] ?? '13:00';

    $loop_date = clone $current_date_obj;
    while ($loop_date <= $end_date_obj) {
        $date_str = $loop_date->format('Y-m-d');
        $is_today = ($date_str === $today_str);
        $is_past = ($date_str < $today_str);
        $is_future = ($date_str > $today_str);

        if ($is_future) {
            $loop_date->modify('+1 day');
            continue;
        }

        $violation_type = null;
        $violation_details = '';

        if (isset($leaves[$emp_id][$date_str])) {
            $loop_date->modify('+1 day');
            continue;
        }

        if (isset($absences[$emp_id][$date_str])) {
            $violation_type = 'Absent';
            $violation_details = 'Employee was absent on ' . date('d/m/Y', strtotime($date_str));
        } elseif (!isset($breaks_by_emp_date[$emp_id][$date_str])) {
            // Never started a break on this date
            if ($is_past) {
                $violation_type = 'Missed Lunchtime';
                $violation_details = 'Did not log a lunchtime on ' . date('d/m/Y', strtotime($date_str));
            } elseif ($is_today && $current_time > $end_time) {
                $violation_type = 'Missed Lunchtime';
                $violation_details = 'Window ended at ' . $end_time;
            }
        } else {
            $b = $breaks_by_emp_date[$emp_id][$date_str];
            if ($b['status'] == 'returned') {
                if ($b['finished_duration'] > 60) {
                    $violation_type = 'Overtime Lunchtime';
                    $violation_details = $b['finished_duration'] . ' minutes total on ' . date('d/m/Y', strtotime($date_str));
                }
            } elseif ($b['status'] == 'on_break') {
                if ($b['current_duration'] > 60) {
                    $violation_type = 'Overtime Lunchtime (Ongoing)';
                    $violation_details = $b['current_duration'] . ' minutes so far';
                }
            }
        }

        if ($violation_type) {
            $v_row = $e;
            $v_row['violation_type'] = $violation_type;
            $v_row['violation_details'] = $violation_details;
            $v_row['date_str'] = $date_str;
            $violations[] = $v_row;
        }
        
        $loop_date->modify('+1 day');
    }
}

// Sort violations by date descending
usort($violations, function($a, $b) {
    return strcmp($b['date_str'], $a['date_str']);
});

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lunchtime_violations_' . $start_date . '_to_' . $end_date . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Date', 'Employee ID', 'Full Name', 'Assigned Window', 'Violation Type', 'Details'));
    
    if (count($violations) > 0) {
        foreach ($violations as $v) {
            fputcsv($output, array(date('M d, Y', strtotime($v['date_str'])), $v['emp_id'], $v['name'], $v['lunch_window'], $v['violation_type'], $v['violation_details']));
        }
    } else {
        fputcsv($output, array('No violations for this date range'));
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
    <title>WeRide - TL Violations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        /* Sidebar */
        .sidebar { width: 260px; background-color: #fff; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.02); z-index: 10; }
        .sidebar-header { padding: 30px 24px; font-size: 20px; font-weight: 800; color: #0f172a; text-transform: capitalize; }
        .nav-menu { list-style: none; padding: 10px 0; }
        .nav-item { padding: 14px 24px; cursor: pointer; color: #64748b; font-weight: 600; margin: 4px 16px; border-radius: 12px; transition: 0.2s; text-decoration: none; display: block; }
        .nav-item:hover { background-color: #f1f5f9; }
        .nav-item.active { background-color: #eff6ff; color: #3b82f6; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        .table-container { background: transparent; padding: 10px 0; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 14px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 18px 20px; background: #ffffff; border-top: 1px solid rgba(226, 232, 240, 0.6); border-bottom: 1px solid rgba(226, 232, 240, 0.6); transition: all 0.3s ease; vertical-align: middle; }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226, 232, 240, 0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226, 232, 240, 0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        tr:hover td { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.08); }
        
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .empty-state-text { color: #64748b; font-weight: 500; font-size: 16px; }
    
        /* Calendar UI */
        .calendar-wrapper {
            background: var(--bg-card, #ffffff);
            padding: 16px;
            border-radius: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color, rgba(226, 232, 240, 0.5));
            margin-bottom: 24px;
            max-width: 380px;
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
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    
    

        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Lunchtime Violations — <?php echo $display_date; ?></div>
            <?php $export_params = http_build_query(array_merge($_GET, ['export_csv' => 1])); ?><a href="?<?php echo $export_params; ?>" style="background: transparent; color: #475569; border: 1px solid #cbd5e1; padding: 10px 16px; font-size: 14px; text-decoration: none; border-radius: 8px; font-weight: 600; box-shadow: none;">Export CSV</a>
        </header>

                <div class="content">
            <!-- CALENDAR INTERFACE -->
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
                            <label style="display:block; font-weight: 600; margin-bottom: 8px;">Filter by Employee</label>
                            <select name="employees[]" id="employee_select" multiple>
                                <?php foreach ($emp_dropdown_list as $emp): ?>
                                    <?php $selected = in_array($emp['emp_id'], $selected_employees) ? 'selected' : ''; ?>
                                    <option value="<?php echo htmlspecialchars($emp['emp_id']); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="padding-top: 29px;">
                            <button type="submit" class="bg-blue-600 text-white" style="  border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 14px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">Apply Filter</button>
                        </div>
                    </div>
                </form>
            </div>
                <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee ID</th>
                            <th>Full Name</th>
                            <th>Assigned Window</th>
                            <th>Violation Type</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($violations) > 0) {
                            foreach ($violations as $v) {
                                $vtype = htmlspecialchars($v['violation_type']);
                                $vdetails = htmlspecialchars($v['violation_details']);
                                $vbadge = '';
                                if (strpos($vtype, 'Missed') !== false) {
                                    $vbadge = "<span style='background: #fef2f2; color: #ef4444; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-transform: uppercase;'>$vtype</span>";
                                } else {
                                    $vbadge = "<span style='background: #fff7ed; color: #f97316; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-transform: uppercase;'>$vtype</span>";
                                }

                                echo "<tr>";
                                echo "<td style='color: #0f172a; font-weight: 600;'>" . date('M d, Y', strtotime($v['date_str'])) . "</td>";
                                echo "<td style='font-weight: 600; color: #0f172a;'>#" . htmlspecialchars($v['emp_id']) . "</td>";
                                echo "<td style='font-weight: 500;'>" . htmlspecialchars($v['name']) . "</td>";
                                echo "<td style='color: #64748b; font-weight: 500;'>" . htmlspecialchars($v['lunch_window']) . "</td>";
                                echo "<td>" . $vbadge . "</td>";
                                echo "<td style='color: #475569;'>" . $vdetails . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>
                                    <div class='empty-state'>
                                        <div class='empty-state-icon'>🎉</div>
                                        <div class='empty-state-text'>No violations on " . $display_date . ". Everyone followed the schedule!</div>
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
