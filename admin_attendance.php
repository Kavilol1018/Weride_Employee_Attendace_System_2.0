<?php
session_start();
require 'db_connect.php';

// Ensure user is management
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    if (isset($_POST['action']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized or session expired.']);
        exit();
    }
    header("Location: index.php");
    exit();
}

$role_slug = $_SESSION['role'];
$current_page = 'admin_attendance.php';

// Handle AJAX inline edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inline_edit') {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Check if record exists
    $check = mysqli_query($conn, "SELECT id FROM attendance_logs WHERE emp_id = '$emp_id' AND `date` = '$date'");
    
    if ($check && mysqli_num_rows($check) > 0) {
        $res = mysqli_query($conn, "UPDATE attendance_logs SET status = '$status' WHERE emp_id = '$emp_id' AND `date` = '$date'");
    } else {
        $res = mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, `date`, status) VALUES ('$emp_id', '$date', '$status')");
    }
    
    ob_clean(); // Clear any warnings/notices to ensure clean JSON
    header('Content-Type: application/json');
    if (isset($res) && $res) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'override_time') {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $clock_in = mysqli_real_escape_string($conn, $_POST['clock_in']);
    $clock_out = mysqli_real_escape_string($conn, $_POST['clock_out']);
    
    // Check if record exists
    $check = mysqli_query($conn, "SELECT id FROM attendance_logs WHERE emp_id = '$emp_id' AND `date` = '$date'");
    
    $clock_in_val = empty($clock_in) ? "NULL" : "'$date $clock_in'";
    $clock_out_val = empty($clock_out) ? "NULL" : "'$date $clock_out'";

    if ($check && mysqli_num_rows($check) > 0) {
        $res = mysqli_query($conn, "UPDATE attendance_logs SET clock_in = $clock_in_val, clock_out = $clock_out_val WHERE emp_id = '$emp_id' AND `date` = '$date'");
    } else {
        $res = mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, `date`, clock_in, clock_out, status) VALUES ('$emp_id', '$date', $clock_in_val, $clock_out_val, 'P')");
    }
    
    if (isset($res) && $res) {
        header("Location: admin_attendance.php?msg=" . urlencode("Times overridden successfully."));
    } else {
        header("Location: admin_attendance.php?error=" . urlencode(mysqli_error($conn)));
    }
    exit();
}

// Filter logic
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get number of days in selected month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);

// Fetch all employees
$employees = [];
$emp_names = [];
$all_leaders = [];
$emp_query = mysqli_query($conn, "SELECT emp_id, name, employment_type, onboarding_date, group_leader_id, role FROM employees ORDER BY name ASC");
if ($emp_query) {
    while ($row = mysqli_fetch_assoc($emp_query)) {
        $emp_names[$row['emp_id']] = $row['name'];
        if (!in_array($row['role'], ['admin', 'hr'])) {
            $employees[$row['emp_id']] = $row;
        }
    }
    foreach ($employees as $emp) {
        if (!empty($emp['group_leader_id']) && isset($emp_names[$emp['group_leader_id']])) {
            $all_leaders[$emp['group_leader_id']] = $emp_names[$emp['group_leader_id']];
        }
    }
}

// Fetch Attendance Logs (for P, A, WO, PH overrides)
// We will assume `status` column can hold P, A, WO, PH, UPL, AL, etc. from manual overrides
$attendance = [];
$att_query = mysqli_query($conn, "SELECT emp_id, DAY(date) as day, status FROM attendance_logs WHERE MONTH(date) = $selected_month AND YEAR(date) = $selected_year");
if ($att_query) {
    while ($row = mysqli_fetch_assoc($att_query)) {
        $attendance[$row['emp_id']][$row['day']] = $row['status'];
    }
}

// Fetch Leave Requests (Approved only)
$leaves = [];
$leave_query = mysqli_query($conn, "SELECT emp_id, start_date, end_date, leave_type, duration FROM leave_requests WHERE status = 'approved' AND (MONTH(start_date) = $selected_month OR MONTH(end_date) = $selected_month) AND YEAR(start_date) = $selected_year");
if ($leave_query) {
    while ($row = mysqli_fetch_assoc($leave_query)) {
        $emp_id = $row['emp_id'];
        $start = strtotime($row['start_date']);
        $end = strtotime($row['end_date']);
        $duration = (float)($row['duration'] ?? 1.0);
        $is_half = ($duration == 0.5);
        
        for ($i = $start; $i <= $end; $i += 86400) {
            if (date('n', $i) == $selected_month) {
                $day = (int)date('j', $i);
                // Map leave type to abbreviation
                $lt = strtoupper($row['leave_type']);
                if (strpos($lt, 'ANNUAL') !== false) $abbr = 'AL';
                elseif (strpos($lt, 'SICK') !== false) $abbr = 'SL';
                elseif (strpos($lt, 'UNPAID') !== false) $abbr = 'UPL';
                elseif (strpos($lt, 'HOSPITAL') !== false) $abbr = 'HL';
                else $abbr = 'L'; // Generic Leave
                
                if ($is_half && in_array($abbr, ['AL', 'SL'])) {
                    $abbr = '0.5 ' . $abbr;
                } elseif ($is_half && $abbr === 'UPL') {
                    $abbr = '0.5UPL';
                } elseif ($is_half) {
                    $abbr = 'HD';
                }
                
                $leaves[$emp_id][$day] = $abbr;
            }
        }
    }
}

// Fetch Public Holidays
$public_holidays_db = [];
$ph_query = mysqli_query($conn, "SELECT DAY(holiday_date) as day FROM public_holidays WHERE MONTH(holiday_date) = $selected_month AND YEAR(holiday_date) = $selected_year");
if ($ph_query) {
    while ($row = mysqli_fetch_assoc($ph_query)) {
        $public_holidays_db[$row['day']] = true;
    }
}

// Map short codes to colors based on screenshot
$color_map = [
    'P' => '#f0fdf4', // Present (keep as is)
    'WO' => '#574A11', // Weekend 
    'UPL' => '#2891A4', // Unpaid Leave 
    'PH' => '#2D532B', // Public Holiday
    'AL' => '#7131C6', // Annual Leave
    'SL' => '#E88C22', // Sick Leave
    'HL' => '#273656', // Hospital Leave
    'A' => '#fca5a5', // Absent (keep as is)
    'HD' => '#fde047', // Half Day (keep as is)
    '0.5' => '#fde047', // Half Day (numerical)
    '0.5 AL' => '#3C2068', // Half-day annual leave
    '0.5 SL' => '#784911', // Half-day sick leave
    '0.5UPL' => '#1D3E47', // Half-day unpaid leave
    'EMERGENCY' => '#fbcfe8', // Emergency Leave (keep as is)
    'BO' => '#e5e7eb' // Before onboarding
];

$text_color_map = [
    'WO' => '#ffffff',
    'UPL' => '#ffffff',
    'PH' => '#ffffff',
    'AL' => '#ffffff',
    'SL' => '#ffffff',
    'HL' => '#ffffff',
    '0.5 AL' => '#ffffff',
    '0.5 SL' => '#ffffff',
    '0.5UPL' => '#ffffff',
    'BO' => '#333333'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Grid - WeRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; color: #0f172a; }
        .main-content { flex: 1; padding: 20px; overflow: hidden; display: flex; flex-direction: column; height: 100vh; box-sizing: border-box; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; color: #0f172a; }
        
        .filters { display: flex; gap: 10px; align-items: center; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;}
        .form-select, .btn { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .btn { background: #3b82f6; color: white; border: none; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #2563eb; }
        .btn-outline { background: white; color: #3b82f6; border: 1px solid #3b82f6; }
        
        .table-container { 
            flex: 1; 
            overflow: auto; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
            position: relative;
        }
        
        table { border-collapse: collapse; width: 100%; white-space: nowrap; font-size: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 14px 10px; text-align: center; }
        .date-col { min-width: 45px; }
        
        /* Sticky Headers & Columns */
        thead th { 
            position: sticky; 
            top: 0; 
            background: #f1f5f9; 
            z-index: 10; 
            font-weight: 600;
        }
        
        /* Sticky Left Columns */
        .sticky-col-1 { position: sticky; left: 0; background: white; z-index: 5; text-align: left; min-width: 200px; font-weight: 600; cursor: pointer; }
        .sticky-col-2 { position: sticky; left: 220px; background: white; z-index: 5; cursor: pointer; }
        .sticky-col-3 { position: sticky; left: 320px; background: white; z-index: 5; cursor: pointer; }
        .sticky-col-4 { position: sticky; left: 450px; background: white; z-index: 5; cursor: pointer; }
        
        /* Z-index fix for top-left intersection */
        thead th.sticky-col-1, thead th.sticky-col-2, thead th.sticky-col-3, thead th.sticky-col-4 {
            z-index: 20;
            background: #e2e8f0;
        }
        
        .summary-header { background: #dcfce7 !important; color: #166534; }
        
    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Attendance Grid</h1>
        </div>
        
        <div class="filters" style="display: block;">
            <form method="GET" style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                <div>
                    <label style="display:block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Month</label>
                    <select name="month" class="form-select" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 14px; min-width: 150px;">
                        <?php for($m=1; $m<=12; ++$m): ?>
                            <option value="<?=$m?>" <?= $m == $selected_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Year</label>
                    <select name="year" class="form-select" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 14px; min-width: 100px;">
                        <?php for($y=date('Y')-2; $y<=date('Y')+1; ++$y): ?>
                            <option value="<?=$y?>" <?= $y == $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Team Lead</label>
                    <select name="team_lead" class="form-select" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 14px; min-width: 180px;">
                        <option value="">All Team Leads</option>
                        <?php foreach($all_leaders as $gl_id => $gl_name): ?>
                            <option value="<?= htmlspecialchars($gl_id) ?>" <?= (isset($_GET['team_lead']) && $_GET['team_lead'] == $gl_id) ? 'selected' : '' ?>><?= htmlspecialchars($gl_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="padding-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="bg-blue-600 text-white" style="  border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">Apply Filter</button>
                    <a href="export_attendance_grid.php?month=<?=$selected_month?>&year=<?=$selected_year?><?= isset($_GET['team_lead']) ? '&team_lead='.urlencode($_GET['team_lead']) : '' ?>" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; text-decoration: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); display: inline-block; box-sizing: border-box;">Download CSV</a>
                    <button type="button" onclick="document.getElementById('importModal').style.display='flex'" style="background: white; color: #3b82f6; border: 1px solid #3b82f6; padding: 9px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; box-sizing: border-box;">+ Import CSV Override</button>
                    <button type="button" onclick="document.getElementById('overrideModal').style.display='flex'" style="background: #3b82f6; color: white; border: none; padding: 9px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; box-sizing: border-box;">+ Override Times</button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="sticky-col-1 sortable" rowspan="2" onclick="sortTable(0)" title="Click to sort">Employee Name &#9662;</th>
                        <th class="sticky-col-2 sortable" rowspan="2" onclick="sortTable(1)" title="Click to sort">Onboarding Date &#9662;</th>
                        <th class="sticky-col-3 sortable" rowspan="2" onclick="sortTable(2)" title="Click to sort">Employment Type &#9662;</th>
                        <th class="sticky-col-4 sortable" rowspan="2" onclick="sortTable(3)" title="Click to sort">Team Lead &#9662;</th>
                        
                        <th colspan="11" class="summary-header">SUMMARY OF THE MONTH</th>
                        
                        <?php for($d=1; $d<=$days_in_month; $d++): 
                            $day_name = date('D', mktime(0,0,0, $selected_month, $d, $selected_year));
                        ?>
                            <th><?= strtoupper($day_name) ?></th>
                        <?php endfor; ?>
                    </tr>
                    <tr>
                        <th class="summary-header">Calendar Days</th>
                        <th class="summary-header">Working Days</th>
                        <th class="summary-header">Pay Days</th>
                        <th class="summary-header">Public Holiday</th>
                        <th class="summary-header">Weekend</th>
                        <th class="summary-header">Before Onboarding</th>
                        <th class="summary-header">Present</th>
                        <th class="summary-header">Absent</th>
                        <th class="summary-header">Sick Leave</th>
                        <th class="summary-header">Annual Leave</th>
                        <th class="summary-header">Emergency Leave</th>
                        
                        <?php for($d=1; $d<=$days_in_month; $d++): ?>
                            <th class="date-col"><?= sprintf("%02d", $d) ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $filter_team_lead = isset($_GET['team_lead']) ? $_GET['team_lead'] : '';
                    foreach ($employees as $emp_id => $emp): 
                        if ($filter_team_lead && $emp['group_leader_id'] !== $filter_team_lead) continue;
                        $tl_name = isset($emp_names[$emp['group_leader_id']]) ? $emp_names[$emp['group_leader_id']] : '-';
                    
                        // Calculate summary values for this employee
                        $calendar_days = $days_in_month;
                        $weekend_count = 0;
                        $public_holiday_count = 0;
                        $before_onboarding_count = 0;
                        $present_count = 0;
                        $absent_count = 0;
                        $sick_leave_count = 0;
                        $annual_leave_count = 0;
                        $emergency_leave_count = 0;
                        
                        $onboarding_time = $emp['onboarding_date'] ? strtotime($emp['onboarding_date']) : 0;
                        
                        $daily_status = [];
                        
                        for ($d=1; $d<=$days_in_month; $d++) {
                            $current_date_time = mktime(0,0,0, $selected_month, $d, $selected_year);
                            $is_weekend = (date('N', $current_date_time) >= 6);
                            $is_before_onboarding = ($onboarding_time > 0 && $current_date_time < $onboarding_time);
                            
                            $status = '';
                            
                            // 1. Check before onboarding
                            if ($is_before_onboarding) {
                                $status = 'BO';
                                $before_onboarding_count++;
                            } 
                            // 2. Check manual overrides / existing logs
                            elseif (isset($attendance[$emp_id][$d])) {
                                $att_status = strtoupper($attendance[$emp_id][$d]);
                                if (in_array($att_status, ['P', 'PRESENT'])) {
                                    $status = 'P';
                                    $present_count++;
                                } elseif (in_array($att_status, ['A', 'ABSENT'])) {
                                    $status = 'A';
                                    $absent_count++;
                                } elseif ($att_status == 'PH') {
                                    $status = 'PH';
                                    $public_holiday_count++;
                                } elseif ($att_status == 'WO') {
                                    $status = 'WO';
                                    $weekend_count++;
                                } elseif ($att_status == 'AL') {
                                    $status = 'AL';
                                    $annual_leave_count++;
                                } elseif ($att_status == 'SL') {
                                    $status = 'SL';
                                    $sick_leave_count++;
                                } elseif ($att_status == '0.5 AL') {
                                    $status = '0.5 AL';
                                    $present_count += 0.5;
                                    $annual_leave_count += 0.5;
                                } elseif ($att_status == '0.5 SL') {
                                    $status = '0.5 SL';
                                    $present_count += 0.5;
                                    $sick_leave_count += 0.5;
                                } elseif ($att_status == '0.5UPL') {
                                    $status = '0.5UPL';
                                    $present_count += 0.5;
                                } elseif (in_array($att_status, ['HD', '0.5'])) {
                                    $status = $att_status;
                                    $present_count += 0.5;
                                } elseif ($att_status == 'EMERGENCY') {
                                    $status = 'EMERGENCY';
                                    $emergency_leave_count++;
                                } else {
                                    $status = $att_status; // UPL, HL, etc. from override
                                }
                            } 
                            // 3. Check Public Holidays from DB
                            elseif (isset($public_holidays_db[$d])) {
                                $status = 'PH';
                                $public_holiday_count++;
                            }
                            // 4. Check weekends
                            elseif ($is_weekend) {
                                $status = 'WO';
                                $weekend_count++;
                            } 
                            // 5. Check leaves
                            elseif (isset($leaves[$emp_id][$d])) {
                                $status = $leaves[$emp_id][$d];
                                if ($status == 'AL') $annual_leave_count++;
                                elseif ($status == 'SL') $sick_leave_count++;
                            } 
                            // 6. Default to Absent if it's a weekday in the past without records
                            else {
                                if ($current_date_time <= strtotime("today")) {
                                    $status = 'A'; // Absent
                                    $absent_count++;
                                } else {
                                    $status = ''; // Future day, leave blank
                                }
                            }
                            
                            $daily_status[$d] = $status;
                        }
                        
                        // Math logic
                        $working_days = $present_count + $annual_leave_count + $sick_leave_count; // Simplified logic, adjust as needed
                        $pay_days = $working_days + $public_holiday_count + $weekend_count; 
                        
                    ?>
                    <tr>
                        <td class="sticky-col-1" style="font-weight: 600;"><?= htmlspecialchars($emp['name']) ?></td>
                        <td class="sticky-col-2"><?= $emp['onboarding_date'] ? date('d M y', strtotime($emp['onboarding_date'])) : '-' ?></td>
                        <td class="sticky-col-3"><?= htmlspecialchars($emp['employment_type']) ?></td>
                        <td class="sticky-col-4"><?= htmlspecialchars($tl_name) ?></td>
                        
                        <td><?= $calendar_days ?></td>
                        <td><?= $working_days ?></td>
                        <td><?= $pay_days ?></td>
                        <td><?= $public_holiday_count ?></td>
                        <td><?= $weekend_count ?></td>
                        <td><?= $before_onboarding_count ?></td>
                        <td><?= $present_count ?></td>
                        <td><?= $absent_count ?></td>
                        <td><?= $sick_leave_count ?></td>
                        <td><?= $annual_leave_count ?></td>
                        <td><?= $emergency_leave_count ?></td>
                        
                        <?php for($d=1; $d<=$days_in_month; $d++): 
                            $st = $daily_status[$d];
                            $bg = isset($color_map[$st]) ? $color_map[$st] : '#ffffff';
                            $tc = isset($text_color_map[$st]) ? $text_color_map[$st] : '#333333';
                            $text = $st;
                            $cell_date = sprintf("%04d-%02d-%02d", $selected_year, $selected_month, $d);
                        ?>
                            <td class="editable-cell date-col" data-emp="<?= htmlspecialchars($emp_id) ?>" data-date="<?= $cell_date ?>" data-val="<?= $text ?>" data-status="<?= $st ?>" style="background-color: <?= $bg ?>; color: <?= $tc ?>; font-weight: 600; cursor: pointer; position: relative;">
                                <?= $text ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; padding:24px; border-radius:12px; width:400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <h2 class="text-slate-900" style="margin-top:0; ">Import Attendance Overrides</h2>
            <p class="text-slate-500" style=" font-size:14px;">Upload your monthly calendar CSV export to override the attendance records.</p>
            <form action="import_attendance.php" method="POST" enctype="multipart/form-data">
                <div style="display: flex; gap: 15px; margin-top: 15px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Month</label>
                        <select name="month" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                            <?php
                            for ($m=1; $m<=12; $m++) {
                                $monthName = date('F', mktime(0, 0, 0, $m, 10));
                                $selected = ($m == $selected_month) ? 'selected' : '';
                                echo "<option value='$m' $selected>$monthName</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Year</label>
                        <select name="year" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                            <?php
                            for ($y=$selected_year-1; $y<=$selected_year+2; $y++) {
                                $selected = ($y == $selected_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <input type="file" name="csv_file" accept=".csv" required style="display:block; margin:20px 0; width:100%;">
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('importModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
    


    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const colorMap = <?= json_encode($color_map) ?>;
        const textColorMap = <?= json_encode($text_color_map) ?>;
        
        document.querySelectorAll('.editable-cell').forEach(cell => {
            cell.addEventListener('click', function() {
                // If it's the BO pre-onboarding cell, don't allow edit
                if (this.dataset.status === 'BO') return;
                
                // If already editing, ignore
                if (this.querySelector('select')) return;
                
                const currentVal = this.dataset.val || '';
                const empId = this.dataset.emp;
                const date = this.dataset.date;
                
                const select = document.createElement('select');
                select.style.width = '100%';
                select.style.height = '100%';
                select.style.border = '2px solid #3b82f6';
                select.style.borderRadius = '4px';
                select.style.padding = '2px 0px';
                select.style.background = 'white';
                select.style.color = 'black';
                select.style.fontWeight = 'bold';
                select.style.textAlign = 'center';
                select.style.appearance = 'none'; // hide native arrow
                select.style.cursor = 'pointer';
                select.style.position = 'absolute';
                select.style.top = '0';
                select.style.left = '0';
                select.style.boxSizing = 'border-box';
                
                const options = ['', 'P', '0.5 AL', '0.5 SL', '0.5UPL', 'A', 'WO', 'PH', 'AL', 'SL', 'UPL', 'HL'];
                options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.text = opt === '' ? '(Clear)' : opt;
                    if (opt === currentVal) option.selected = true;
                    
                    if (opt === '') {
                        option.style.backgroundColor = '#ffffff';
                        option.style.color = '#000000';
                    } else if (colorMap[opt]) {
                        option.style.backgroundColor = colorMap[opt];
                        option.style.color = textColorMap[opt] || '#000000';
                    }
                    
                    select.appendChild(option);
                });
                
                this.innerHTML = '';
                this.appendChild(select);
                select.focus();
                
                const saveEdit = () => {
                    const newVal = select.value;
                    this.innerHTML = 'Saving...';
                    
                    fetch('admin_attendance', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            'action': 'inline_edit',
                            'emp_id': empId,
                            'date': date,
                            'status': newVal
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            this.dataset.val = newVal;
                            this.innerHTML = newVal;
                            // Note: To see math updates, user will need to refresh. 
                            // But background color can be updated locally.
                            let newBg = colorMap[newVal] || '#ffffff';
                            let newTc = textColorMap[newVal] || '#333333';
                            this.style.backgroundColor = newBg;
                            this.style.color = newTc;
                        } else {
                            this.innerHTML = currentVal;
                            alert("Failed to save: " + (data.error || "Unknown error"));
                        }
                    })
                    .catch((err) => {
                        this.innerHTML = currentVal;
                        alert("Error communicating with server: " + err.message);
                    });
                };
                
                select.addEventListener('blur', saveEdit);
                select.addEventListener('change', saveEdit);
            });
        });
    });
    </script>
    
    <script>
    function sortTable(n) {
        var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
        table = document.querySelector(".table-container table");
        switching = true;
        dir = "asc"; 
        
        while (switching) {
            switching = false;
            rows = table.querySelectorAll("tbody tr");
            for (i = 0; i < (rows.length - 1); i++) {
                shouldSwitch = false;
                x = rows[i].getElementsByTagName("TD")[n];
                y = rows[i + 1].getElementsByTagName("TD")[n];
                
                let valX = x.innerText.toLowerCase();
                let valY = y.innerText.toLowerCase();
                
                if (n === 1) { // Date column
                    let dateX = valX === '-' ? 0 : new Date(valX).getTime();
                    let dateY = valY === '-' ? 0 : new Date(valY).getTime();
                    valX = dateX;
                    valY = dateY;
                }

                if (dir == "asc") {
                    if (valX > valY) {
                        shouldSwitch = true;
                        break;
                    }
                } else if (dir == "desc") {
                    if (valX < valY) {
                        shouldSwitch = true;
                        break;
                    }
                }
            }
            if (shouldSwitch) {
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
                switchcount++; 
            } else {
                if (switchcount == 0 && dir == "asc") {
                    dir = "desc";
                    switching = true;
                }
            }
        }
    }
    </script>

    <!-- OVERRIDE MODAL -->
    <div id="overrideModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:1000;">
        <div class="modal-box" style="background:#fff; padding:30px; border-radius:16px; width:400px; max-width:90%; position:relative; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <button type="button" onclick="document.getElementById('overrideModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:20px; cursor:pointer; color:#94a3b8;">&times;</button>
            <h3 style="margin-top:0; margin-bottom:20px; color:#0f172a;">Override Clock In/Out</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="override_time">
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:600; font-size:13px; color:#475569; margin-bottom:5px;">Employee</label>
                    <select name="emp_id" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none;">
                        <option value="" disabled selected>-- Select an employee --</option>
                        <?php foreach($emp_names as $e_id => $e_name): ?>
                            <option value="<?= htmlspecialchars($e_id) ?>"><?= htmlspecialchars($e_name) ?> (<?= htmlspecialchars($e_id) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:600; font-size:13px; color:#475569; margin-bottom:5px;">Date</label>
                    <input type="date" name="date" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none;">
                </div>

                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-weight:600; font-size:13px; color:#475569; margin-bottom:5px;">Clock In Time</label>
                        <input type="time" name="clock_in" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-weight:600; font-size:13px; color:#475569; margin-bottom:5px;">Clock Out Time</label>
                        <input type="time" name="clock_out" style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1; outline:none;">
                    </div>
                </div>

                <button type="submit" style="width:100%; padding:12px; background:#3b82f6; color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer;">Save Override</button>
            </form>
        </div>
    </div>

</body>
</html>
