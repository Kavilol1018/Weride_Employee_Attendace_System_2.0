<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['employee', 'qa', 'gl'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

$current_employee_id = $_SESSION['emp_id']; 

$emp_query = mysqli_query($conn, "SELECT name, onboarding_date, annual_leave_balance, sick_leave_balance, unpaid_leave_balance, hospitalization_leave_balance FROM employees WHERE emp_id = '$current_employee_id'");
$emp_data = mysqli_fetch_assoc($emp_query);

$employee_name = $emp_data['name'] ?? 'Employee';
$onboarding_time = !empty($emp_data['onboarding_date']) ? strtotime($emp_data['onboarding_date']) : 0;
$al_bal = $emp_data['annual_leave_balance'] ?? 8;
$sl_bal = $emp_data['sick_leave_balance'] ?? 14;
$upl_bal = $emp_data['unpaid_leave_balance'] ?? 0;
$hl_bal = $emp_data['hospitalization_leave_balance'] ?? 60;

// MONTHLY & WEEKLY EMPLOYEE STATISTICS (CALENDAR AWARE)
$selected_month = (int)date('n');
$selected_year = (int)date('Y');
$today_day = (int)date('j');

// 1. Fetch attendance logs for current month
$emp_att_logs = [];
$att_q = mysqli_query($conn, "SELECT DAY(date) as day, status, clock_in FROM attendance_logs WHERE emp_id = '$current_employee_id' AND MONTH(date) = $selected_month AND YEAR(date) = $selected_year");
if ($att_q) {
    while ($ar = mysqli_fetch_assoc($att_q)) {
        $emp_att_logs[$ar['day']] = [
            'status' => $ar['status'],
            'clock_in' => $ar['clock_in']
        ];
    }
}

// 2. Fetch approved leaves for current month
$emp_leaves = [];
$l_q = mysqli_query($conn, "SELECT start_date, end_date, leave_type, duration FROM leave_requests WHERE emp_id = '$current_employee_id' AND status = 'approved' AND (MONTH(start_date) = $selected_month OR MONTH(end_date) = $selected_month) AND (YEAR(start_date) = $selected_year OR YEAR(end_date) = $selected_year)");
if ($l_q) {
    while ($lr = mysqli_fetch_assoc($l_q)) {
        $l_start = strtotime($lr['start_date']);
        $l_end = strtotime($lr['end_date']);
        $l_dur = (float)($lr['duration'] ?? 1.0);
        $is_half = ($l_dur == 0.5);
        for ($ts = $l_start; $ts <= $l_end; $ts += 86400) {
            if ((int)date('n', $ts) === $selected_month && (int)date('Y', $ts) === $selected_year) {
                $ld = (int)date('j', $ts);
                $lt = strtoupper($lr['leave_type']);
                if (strpos($lt, 'ANNUAL') !== false) $abbr = 'AL';
                elseif (strpos($lt, 'SICK') !== false) $abbr = 'SL';
                elseif (strpos($lt, 'UNPAID') !== false) $abbr = 'UPL';
                elseif (strpos($lt, 'HOSPITAL') !== false) $abbr = 'HL';
                elseif (strpos($lt, 'EMERGENCY') !== false) $abbr = 'EMERGENCY';
                else $abbr = 'L';
                
                if ($is_half && in_array($abbr, ['AL', 'SL'])) {
                    $abbr = '0.5 ' . $abbr;
                } elseif ($is_half && $abbr === 'UPL') {
                    $abbr = '0.5UPL';
                } elseif ($is_half) {
                    $abbr = 'HD';
                }
                $emp_leaves[$ld] = $abbr;
            }
        }
    }
}

// 3. Fetch public holidays for current month
$public_holidays = [];
$ph_q = mysqli_query($conn, "SELECT DAY(holiday_date) as day FROM public_holidays WHERE MONTH(holiday_date) = $selected_month AND YEAR(holiday_date) = $selected_year");
if ($ph_q) {
    while ($ph = mysqli_fetch_assoc($ph_q)) {
        $public_holidays[$ph['day']] = true;
    }
}

$present_count = 0;
$absent_count = 0;

for ($d = 1; $d <= $today_day; $d++) {
    $day_ts = mktime(0, 0, 0, $selected_month, $d, $selected_year);
    $is_weekend = ((int)date('N', $day_ts) >= 6);
    $is_before_onboarding = ($onboarding_time > 0 && $day_ts < $onboarding_time);
    
    if ($is_before_onboarding) {
        continue;
    }
    
    if ($is_weekend || isset($public_holidays[$d])) {
        if (isset($emp_att_logs[$d])) {
            $att_st = strtoupper(trim($emp_att_logs[$d]['status'] ?? ''));
            $has_clock = !empty($emp_att_logs[$d]['clock_in']) && strpos($emp_att_logs[$d]['clock_in'], '0000-00-00') === false;
            if (in_array($att_st, ['P', 'PRESENT']) || $has_clock) {
                $present_count += 1;
            }
        }
        continue;
    }
    
    $is_half_leave = false;
    $is_full_leave = false;

    if (isset($emp_leaves[$d])) {
        $lst = $emp_leaves[$d];
        if (in_array($lst, ['0.5 AL', '0.5 SL', '0.5UPL', 'HD'])) {
            $is_half_leave = true;
        } else {
            $is_full_leave = true;
        }
    }

    $att_st = '';
    $has_clock = false;
    if (isset($emp_att_logs[$d])) {
        $att_st = strtoupper(trim($emp_att_logs[$d]['status'] ?? ''));
        $has_clock = !empty($emp_att_logs[$d]['clock_in']) && strpos($emp_att_logs[$d]['clock_in'], '0000-00-00') === false;

        if (in_array($att_st, ['0.5 AL', '0.5AL', '0.5 SL', '0.5SL', '0.5UPL', '0.5 UPL', 'HD', '0.5'])) {
            $is_half_leave = true;
        } elseif (in_array($att_st, ['AL', 'SL', 'UPL', 'HL', 'HOSP', 'EMERGENCY'])) {
            $is_full_leave = true;
        }
    }

    if ($is_half_leave) {
        $present_count += 0.5;
    } elseif ($is_full_leave) {
        // Approved full leave: neither present nor absent
    } elseif (in_array($att_st, ['P', 'PRESENT', 'LATE']) || $has_clock) {
        $present_count += 1;
    } elseif (in_array($att_st, ['A', 'ABSENT'])) {
        $absent_count += 1;
    } else {
        // Unexcused weekday absence
        $absent_count += 1;
    }
}

$late_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM attendance_logs WHERE emp_id = '$current_employee_id' AND MONTH(date) = $selected_month AND YEAR(date) = $selected_year AND flag IN ('Late', 'Both')"))['cnt'];

$early_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM attendance_logs WHERE emp_id = '$current_employee_id' AND MONTH(date) = $selected_month AND YEAR(date) = $selected_year AND status = 'EMERGENCY'"))['cnt'];

$weekly_hours_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(work_hours) as total_hrs FROM attendance_logs WHERE emp_id = '$current_employee_id' AND WEEK(date, 1) = WEEK(CURDATE(), 1) AND YEAR(date) = YEAR(CURDATE())"));
$weekly_work_hours = $weekly_hours_res['total_hrs'] ? round((float)$weekly_hours_res['total_hrs'], 1) : 0.0;
$weekly_target = 45.0;
$weekly_pct = min(100, round(($weekly_work_hours / $weekly_target) * 100));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Statistics - WeRide</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .stat-card { background: #ffffff; padding: 20px 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .stat-title { color: #94a3b8; font-size: 11px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 24px; font-weight: 800; color: #0f172a; }

        .progress-bar-bg { width: 100%; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; margin-top: 8px; }
        .progress-bar-fill { height: 100%; background: #3b82f6; border-radius: 5px; transition: width 0.3s; }
        
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .stat-card { background: #1e293b !important; border-color: #334155 !important; }
        body.dark-mode .page-title, body.dark-mode .stat-value { color: #f8fafc !important; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">My Statistics</div>
        </header>
        <div class="content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Days Present (This Month)</div>
                    <div class="stat-value" style="color: #10b981;"><?php echo $present_count; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Late</div>
                    <div class="stat-value" style="color: #f59e0b;"><?php echo $late_count; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Days Absent</div>
                    <div class="stat-value text-red-500" ><?php echo $absent_count; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-title">Leave Balances</div>
                    <div style="font-size: 14px; font-weight: 700; margin-top: 5px;">
                        AL: <span style="color:#3b82f6;"><?php echo $al_bal; ?></span> | 
                        SL: <span style="color:#10b981;"><?php echo $sl_bal; ?></span> | 
                        UPL: <span style="color:#f97316;"><?php echo $upl_bal; ?></span> |
                        HL: <span style="color:#8b5cf6;"><?php echo $hl_bal; ?></span>
                    </div>
                </div>
            </div>

            <div class="stat-card" style="margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="stat-title" style="margin:0;">Weekly Work Hours Tracker</div>
                    <div style="font-weight: 700; color: #3b82f6;"><?php echo $weekly_work_hours; ?> / <?php echo $weekly_target; ?> Hours (<?php echo $weekly_pct; ?>%)</div>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?php echo $weekly_pct; ?>%;"></div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
