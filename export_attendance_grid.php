<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}

$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filter_team_lead = isset($_GET['team_lead']) ? mysqli_real_escape_string($conn, $_GET['team_lead']) : '';

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
$month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

// Filename for browser download
$filename = "Attendance_Report_{$month_name}_{$selected_year}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// CSV Headers
$headers = [
    'Employee ID', 'Employee Name', 'Employment Type', 'Team Lead Name', 'Onboarding Date',
    'Calendar Days', 'Working Days', 'Pay Days', 'Public Holidays', 'Weekends',
    'Before Onboarding', 'Present Days', 'Late Count', 'Emergency Leave Count', 'Absent Days', 'Sick Leave', 'Annual Leave', 'Unpaid Leave'
];

for ($d = 1; $d <= $days_in_month; $d++) {
    $day_name = date('D', mktime(0, 0, 0, $selected_month, $d, $selected_year));
    $headers[] = sprintf("%02d (%s)", $d, strtoupper($day_name));
}

fputcsv($output, $headers);

// Fetch employees
if (!empty($filter_team_lead)) {
    $where_sql = "WHERE group_leader_id = '$filter_team_lead' AND role NOT IN ('admin', 'hr')";
} else {
    $where_sql = "WHERE role NOT IN ('admin', 'hr')";
}
$emp_query = mysqli_query($conn, "SELECT emp_id, name, employment_type, onboarding_date, group_leader_id FROM employees $where_sql ORDER BY name ASC");

// Fetch all employee names for mapping leader names
$emp_names = [];
$all_emp_query = mysqli_query($conn, "SELECT emp_id, name FROM employees");
while ($row = mysqli_fetch_assoc($all_emp_query)) {
    $emp_names[$row['emp_id']] = $row['name'];
}

// Fetch Attendance logs
$attendance = [];
$att_query = mysqli_query($conn, "SELECT emp_id, DAY(date) as day, status FROM attendance_logs WHERE MONTH(date) = $selected_month AND YEAR(date) = $selected_year");
if ($att_query) {
    while ($row = mysqli_fetch_assoc($att_query)) {
        $attendance[$row['emp_id']][$row['day']] = $row['status'];
    }
}

// Fetch Approved Leaves
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
                $lt = strtoupper($row['leave_type']);
                
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

while ($emp = mysqli_fetch_assoc($emp_query)) {
    $emp_id = $emp['emp_id'];
    $onboarding_time = $emp['onboarding_date'] ? strtotime($emp['onboarding_date']) : 0;
    
    $calendar_days = $days_in_month;
    $weekend_count = 0;
    $public_holiday_count = 0;
    $before_onboarding_count = 0;
    $present_count = 0;
    $late_count = 0;
    $early_count = 0; // Emergency Leave count
    $absent_count = 0;
    $sick_leave_count = 0;
    $annual_leave_count = 0;
    $unpaid_leave_count = 0;
    
    $daily_cells = [];
    
    for ($d = 1; $d <= $days_in_month; $d++) {
        $current_date_time = mktime(0, 0, 0, $selected_month, $d, $selected_year);
        $is_weekend = (date('N', $current_date_time) >= 6);
        $is_before_onboarding = ($onboarding_time > 0 && $current_date_time < $onboarding_time);
        
        $status = '';
        // 1. Check manual overrides / existing logs
        if (isset($attendance[$emp_id][$d])) {
            $att_status = strtoupper(trim($attendance[$emp_id][$d]));
            if (in_array($att_status, ['P', 'PRESENT'])) {
                $status = 'P';
                $present_count++;
            } elseif ($att_status == 'LATE') {
                $status = 'LATE';
                $present_count++;
                $late_count++;
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
                $early_count++;
            } else {
                $status = $att_status;
                if (strpos($status, '0.5UPL') !== false || strpos($status, '0.5 UPL') !== false) {
                    $unpaid_leave_count += 0.5;
                    $present_count += 0.5;
                } elseif (strpos($status, 'UPL') !== false) {
                    $unpaid_leave_count += 1;
                }
            }
        } elseif (isset($public_holidays_db[$d])) {
            $status = 'PH';
            $public_holiday_count++;
        } elseif ($is_weekend) {
            $status = 'WO';
            $weekend_count++;
        } elseif ($is_before_onboarding) {
            $status = 'BO';
            $before_onboarding_count++;
        } elseif (isset($leaves[$emp_id][$d])) {
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
                $early_count++;
            }
        } else {
            if ($current_date_time <= strtotime("today")) {
                $status = 'A';
                $absent_count++;
            } else {
                $status = '';
            }
        }
        $daily_cells[] = ($status === 'BO') ? 'BO' : $status;
    }
    
    // Calendar Days is strictly the total days in the month
    $calendar_days = $days_in_month;
    $working_days = $present_count + $annual_leave_count + $sick_leave_count + $early_count;
    $pay_days = $calendar_days - $public_holiday_count - $weekend_count;
    
    $tl_name = isset($emp_names[$emp['group_leader_id']]) ? $emp_names[$emp['group_leader_id']] : '-';

    $row_data = array_merge([
        $emp['emp_id'],
        $emp['name'],
        $emp['employment_type'],
        $tl_name,
        $emp['onboarding_date'] ?? '-',
        $calendar_days,
        $working_days,
        $pay_days,
        $public_holiday_count,
        $weekend_count,
        $before_onboarding_count,
        $present_count,
        $late_count,
        $early_count,
        $absent_count,
        $sick_leave_count,
        $annual_leave_count,
        $unpaid_leave_count
    ], $daily_cells);
    
    fputcsv($output, $row_data);
}

fclose($output);
exit();
?>

