<?php
session_start();
require 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['action']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized or session expired.']);
        exit();
    }
    header("Location: index.php");
    exit();
}

$role_slug = strtolower($_SESSION['role'] ?? 'employee');
$current_page = 'admin_attendance.php';
$can_edit_calendar = in_array($role_slug, ['admin', 'hr', 'tl']);

// Handle AJAX inline edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inline_edit') {
    if (!$can_edit_calendar) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Only Admin, HR, and Team Leaders can make changes in the calendar.']);
        exit();
    }
    
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Check if record exists
    $check = mysqli_query($conn, "SELECT id, status, source FROM attendance_logs WHERE emp_id = '$emp_id' AND `date` = '$date'");
    $old_status = '';
    $old_source = 'manual';
    $source_col_exists = ($check !== false && !mysqli_error($conn));
    
    if ($check && mysqli_num_rows($check) > 0) {
        $row = mysqli_fetch_assoc($check);
        $old_status = $row['status'];
        $old_source = $row['source'] ?? 'manual';
        if ($source_col_exists) {
            $res = mysqli_query($conn, "UPDATE attendance_logs SET status = '$status', source = 'manual' WHERE emp_id = '$emp_id' AND `date` = '$date'");
        } else {
            $res = mysqli_query($conn, "UPDATE attendance_logs SET status = '$status' WHERE emp_id = '$emp_id' AND `date` = '$date'");
        }
    } elseif (!$check) {
        // SELECT failed (e.g. source column missing) - retry without source
        $check2 = mysqli_query($conn, "SELECT id, status FROM attendance_logs WHERE emp_id = '$emp_id' AND `date` = '$date'");
        $source_col_exists = false;
        if ($check2 && mysqli_num_rows($check2) > 0) {
            $row = mysqli_fetch_assoc($check2);
            $old_status = $row['status'];
            $res = mysqli_query($conn, "UPDATE attendance_logs SET status = '$status' WHERE emp_id = '$emp_id' AND `date` = '$date'");
        } else {
            $res = mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, `date`, status) VALUES ('$emp_id', '$date', '$status')");
        }
    } else {
        if ($source_col_exists) {
            $res = mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, `date`, status, source) VALUES ('$emp_id', '$date', '$status', 'manual')");
        } else {
            $res = mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, `date`, status) VALUES ('$emp_id', '$date', '$status')");
        }
    }
    
    // Adjust leave balances incrementally based on old vs new status.
    if ($old_status !== $status) {
        $al_diff = 0;
        $sl_diff = 0;
        $upl_diff = 0;
        $hl_diff = 0;
        
        // Remove old status impact (give back what was consumed)
        $old_parts = explode('/', $old_status);
        foreach ($old_parts as $op) {
            $op = trim($op);
            if ($op === 'AL') $al_diff += 1;
            elseif ($op === '0.5 AL' || $op === '0.5AL') $al_diff += 0.5;
            elseif ($op === 'SL') $sl_diff += 1;
            elseif ($op === '0.5 SL' || $op === '0.5SL') $sl_diff += 0.5;
            elseif ($op === 'UPL') $upl_diff -= 1;
            elseif ($op === '0.5 UPL' || $op === '0.5UPL') $upl_diff -= 0.5;
            elseif ($op === 'HL') $hl_diff += 1;
        }

        // Add new status impact (consume leave)
        $new_parts = explode('/', $status);
        foreach ($new_parts as $np) {
            $np = trim($np);
            if ($np === 'AL') $al_diff -= 1;
            elseif ($np === '0.5 AL' || $np === '0.5AL') $al_diff -= 0.5;
            elseif ($np === 'SL') $sl_diff -= 1;
            elseif ($np === '0.5 SL' || $np === '0.5SL') $sl_diff -= 0.5;
            elseif ($np === 'UPL') $upl_diff += 1;
            elseif ($np === '0.5 UPL' || $np === '0.5UPL') $upl_diff += 0.5;
            elseif ($np === 'HL') $hl_diff -= 1;
        }
        
        if ($al_diff != 0 || $sl_diff != 0 || $upl_diff != 0 || $hl_diff != 0) {
            mysqli_query($conn, "
                UPDATE employees 
                SET 
                    annual_leave_balance = GREATEST(0, annual_leave_balance + ($al_diff)),
                    sick_leave_balance = GREATEST(0, sick_leave_balance + ($sl_diff)),
                    unpaid_leave_balance = GREATEST(0, unpaid_leave_balance + ($upl_diff)),
                    hospitalization_leave_balance = GREATEST(0, hospitalization_leave_balance + ($hl_diff))
                WHERE emp_id = '$emp_id'
            ");
        }
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
    'P' => '#ffffff', // White
    'WO' => '#716018', // Olive
    'UPL' => '#0891b2', // Teal
    'PH' => '#166534', // Dark Green
    'AL' => '#7e22ce', // Bright Purple
    'SL' => '#ea580c', // Orange
    'HL' => '#334155', // Dark Gray/Blue
    'A' => '#fca5a5', // Light Pink/Red
    'HD' => '#fde047',
    '0.5' => '#fde047',
    '0.5 AL' => '#4c1d95', // Dark Purple
    '0.5AL' => '#4c1d95',
    '0.5 SL' => '#713f12', // Brown
    '0.5SL' => '#713f12',
    '0.5UPL' => '#164e63', // Dark Teal
    '0.5 UPL' => '#164e63',
    'EMERGENCY' => '#fbcfe8',
    'BO' => '#e5e7eb'
];

$text_color_map = [
    'P' => '#333333',
    'WO' => '#ffffff',
    'UPL' => '#ffffff',
    'PH' => '#ffffff',
    'AL' => '#ffffff',
    'SL' => '#ffffff',
    'HL' => '#ffffff',
    '0.5 AL' => '#ffffff',
    '0.5AL' => '#ffffff',
    '0.5 SL' => '#ffffff',
    '0.5SL' => '#ffffff',
    '0.5UPL' => '#ffffff',
    '0.5 UPL' => '#ffffff',
    'A' => '#000000',
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
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8fafc; margin: 0; display: flex; color: #0f172a; }
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
                    <?php if ($role_slug !== 'employee'): ?>
                    <a href="export_attendance_grid.php?month=<?=$selected_month?>&year=<?=$selected_year?><?= isset($_GET['team_lead']) ? '&team_lead='.urlencode($_GET['team_lead']) : '' ?>" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; text-decoration: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); display: inline-block; box-sizing: border-box;">Download CSV</a>
                    <?php endif; ?>
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
                        
                        <th colspan="12" class="summary-header">SUMMARY OF THE MONTH</th>
                        
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
                        <th class="summary-header">Unpaid Leave</th>
                        
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
                        $weekend_count = 0;
                        $public_holiday_count = 0;
                        $before_onboarding_count = 0;
                        $present_count = 0;
                        $absent_count = 0;
                        $sick_leave_count = 0;
                        $annual_leave_count = 0;
                        $emergency_leave_count = 0;
                        $unpaid_leave_count = 0;
                        
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
                                $att_status = strtoupper(trim($attendance[$emp_id][$d]));
                                if (in_array($att_status, ['P', 'PRESENT', 'LATE'])) {
                                    $status = ($att_status == 'LATE') ? 'LATE' : 'P';
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
                                } elseif (in_array($att_status, ['0.5 AL', '0.5AL'])) {
                                    $status = '0.5 AL';
                                    $present_count += 0.5;
                                    $annual_leave_count += 0.5;
                                } elseif (in_array($att_status, ['0.5 SL', '0.5SL'])) {
                                    $status = '0.5 SL';
                                    $present_count += 0.5;
                                    $sick_leave_count += 0.5;
                                } elseif (in_array($att_status, ['0.5UPL', '0.5 UPL'])) {
                                    $status = '0.5UPL';
                                    $present_count += 0.5;
                                    $unpaid_leave_count += 0.5;
                                } elseif ($att_status == 'UPL') {
                                    $status = 'UPL';
                                    $unpaid_leave_count++;
                                } elseif (in_array($att_status, ['HD', '0.5'])) {
                                    $status = $att_status;
                                    $present_count += 0.5;
                                } elseif ($att_status == 'EMERGENCY') {
                                    $status = 'EMERGENCY';
                                    $emergency_leave_count++;
                                } else {
                                    $status = $att_status; // UPL, HL, etc. from override
                                    if (strpos($status, '0.5UPL') !== false || strpos($status, '0.5 UPL') !== false) {
                                        $unpaid_leave_count += 0.5;
                                        $present_count += 0.5;
                                    } elseif (strpos($status, 'UPL') !== false) {
                                        $unpaid_leave_count += 1;
                                    }
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
                                if ($status == 'AL') {
                                    $annual_leave_count++;
                                } elseif ($status == 'SL') {
                                    $sick_leave_count++;
                                } elseif ($status == 'UPL') {
                                    $unpaid_leave_count++;
                                } elseif (in_array($status, ['0.5 AL', '0.5AL'])) {
                                    $present_count += 0.5;
                                    $annual_leave_count += 0.5;
                                } elseif (in_array($status, ['0.5 SL', '0.5SL'])) {
                                    $present_count += 0.5;
                                    $sick_leave_count += 0.5;
                                } elseif (in_array($status, ['0.5UPL', '0.5 UPL'])) {
                                    $present_count += 0.5;
                                    $unpaid_leave_count += 0.5;
                                } elseif (in_array($status, ['HD', '0.5'])) {
                                    $present_count += 0.5;
                                } elseif ($status == 'EMERGENCY') {
                                    $emergency_leave_count++;
                                }
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
                        
                        // Calendar Days is strictly the total days in the month
                        $calendar_days = $days_in_month;
                        $working_days = $present_count + $annual_leave_count + $sick_leave_count + $emergency_leave_count;
                        $pay_days = $calendar_days - $public_holiday_count - $weekend_count; 
                        
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
                        <td><?= $unpaid_leave_count ?></td>
                        
                        <?php for($d=1; $d<=$days_in_month; $d++): 
                            $st = $daily_status[$d];
                            $bg = isset($color_map[$st]) ? $color_map[$st] : '#ffffff';
                            $tc = isset($text_color_map[$st]) ? $text_color_map[$st] : '#333333';
                            $text = $st;
                            $cell_date = sprintf("%04d-%02d-%02d", $selected_year, $selected_month, $d);
                            $editable_class = $can_edit_calendar ? 'editable-cell' : '';
                        ?>
                            <td class="<?= $editable_class ?> date-col" data-emp="<?= htmlspecialchars($emp_id) ?>" data-date="<?= $cell_date ?>" data-val="<?= $text ?>" data-status="<?= $st ?>" style="background-color: <?= $bg ?>; color: <?= $tc ?>; font-weight: 600; <?= $can_edit_calendar ? 'cursor: pointer;' : '' ?> position: relative;">
                                <?= $text ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                    
                    fetch(window.location.pathname, {
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

</body>
</html>
