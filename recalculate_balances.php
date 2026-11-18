<?php
session_start();
require 'db_connect.php';

echo "<h2>Recalculating Leave Balances from March to December 2026...</h2>";

// 1. Reset everyone to default
echo "<h3>1. Resetting balances to defaults...</h3>";
mysqli_query($conn, "UPDATE employees SET annual_leave_balance = 8, sick_leave_balance = 14, unpaid_leave_balance = 0, hospitalization_leave_balance = 60 WHERE LOWER(employment_type) NOT LIKE '%intern%'");
mysqli_query($conn, "UPDATE employees SET unpaid_leave_balance = 0, annual_leave_balance = 0, sick_leave_balance = 0, hospitalization_leave_balance = 0 WHERE LOWER(employment_type) LIKE '%intern%'");

// Clean up any corrupted composite duplicate entries in attendance_logs
mysqli_query($conn, "UPDATE attendance_logs SET status = '0.5UPL' WHERE (status LIKE '%0.5%UPL%' OR status LIKE '%UPL%0.5%') AND status != '0.5UPL'");
mysqli_query($conn, "UPDATE attendance_logs SET status = '0.5 AL' WHERE (status LIKE '%0.5%AL%' OR status LIKE '%AL%0.5%') AND status != '0.5 AL' AND status != '0.5AL'");
mysqli_query($conn, "UPDATE attendance_logs SET status = '0.5 SL' WHERE (status LIKE '%0.5%SL%' OR status LIKE '%SL%0.5%') AND status != '0.5 SL' AND status != '0.5SL'");

// 2. Fetch all deductions from attendance_logs
echo "<h3>2. Scanning the calendar and applying exact deductions...</h3>";

$log_query = mysqli_query($conn, "
    SELECT a.emp_id, a.status 
    FROM attendance_logs a 
    JOIN employees e ON a.emp_id = e.emp_id
    WHERE a.status != 'P' AND a.status != 'A' AND a.status != 'WO' AND a.status != 'PH' AND a.status != 'BO' AND a.status != ''
    AND (e.onboarding_date IS NULL OR a.date >= e.onboarding_date)
");

$leave_totals = [];
while ($log = mysqli_fetch_assoc($log_query)) {
    $emp_id = $log['emp_id'];
    $status_str = trim($log['status']);
    
    if (!isset($leave_totals[$emp_id])) {
        $leave_totals[$emp_id] = ['al' => 0, 'sl' => 0, 'upl' => 0, 'hl' => 0];
    }
    
    $day_al = 0; $day_sl = 0; $day_upl = 0; $day_hl = 0;
    
    $parts = explode('/', $status_str);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === 'AL') $day_al += 1;
        elseif ($p === '0.5 AL' || $p === '0.5AL') $day_al += 0.5;
        elseif ($p === 'SL') $day_sl += 1;
        elseif ($p === '0.5 SL' || $p === '0.5SL') $day_sl += 0.5;
        elseif ($p === 'UPL') $day_upl += 1;
        elseif ($p === '0.5 UPL' || $p === '0.5UPL') $day_upl += 0.5;
        elseif ($p === 'HL') $day_hl += 1;
        elseif ($p === '0.5 HL' || $p === '0.5HL') $day_hl += 0.5;
    }
    
    // Cap any single day at 1.0 max per leave type
    if ($day_upl > 1.0) $day_upl = 1.0;
    if ($day_al > 1.0) $day_al = 1.0;
    if ($day_sl > 1.0) $day_sl = 1.0;
    if ($day_hl > 1.0) $day_hl = 1.0;
    
    $leave_totals[$emp_id]['al'] += $day_al;
    $leave_totals[$emp_id]['sl'] += $day_sl;
    $leave_totals[$emp_id]['upl'] += $day_upl;
    $leave_totals[$emp_id]['hl'] += $day_hl;
}

$updated_count = 0;
foreach ($leave_totals as $emp_id => $totals) {
    $total_al = (float)$totals['al'];
    $total_sl = (float)$totals['sl'];
    $total_upl = (float)$totals['upl'];
    $total_hl = (float)$totals['hl'];
    
    if ($total_al > 0 || $total_sl > 0 || $total_upl > 0 || $total_hl > 0) {
        // Fetch the freshly reset balances
        $emp_query = mysqli_query($conn, "SELECT name, employment_type, annual_leave_balance, sick_leave_balance, unpaid_leave_balance, hospitalization_leave_balance FROM employees WHERE emp_id = '$emp_id'");
        if ($emp_data = mysqli_fetch_assoc($emp_query)) {
            $is_intern = (strpos(strtolower($emp_data['employment_type'] ?? ''), 'intern') !== false);
            
            if ($is_intern) {
                $new_al = 0.0;
                $new_sl = 0.0;
                $new_hl = 0.0;
                $new_upl = $total_al + $total_sl + $total_upl + $total_hl;
            } else {
                $new_al = (float)$emp_data['annual_leave_balance'] - $total_al;
                $new_sl = (float)$emp_data['sick_leave_balance'] - $total_sl;
                $new_hl = (float)$emp_data['hospitalization_leave_balance'] - $total_hl;
                $extra_upl = 0;

                if ($new_al < 0) {
                    $extra_upl += abs($new_al);
                    $new_al = 0;
                }
                if ($new_sl < 0) {
                    $extra_upl += abs($new_sl);
                    $new_sl = 0;
                }
                if ($new_hl < 0) {
                    $extra_upl += abs($new_hl);
                    $new_hl = 0;
                }

                $new_upl = (float)$emp_data['unpaid_leave_balance'] + $total_upl + $extra_upl;
            }

            $update_query = "
                UPDATE employees 
                SET 
                    annual_leave_balance = $new_al,
                    sick_leave_balance = $new_sl,
                    unpaid_leave_balance = $new_upl,
                    hospitalization_leave_balance = $new_hl
                WHERE emp_id = '$emp_id'
            ";
            mysqli_query($conn, $update_query);
            $updated_count++;
            
            if ($emp_id === 'JSMY029' || stripos($emp_data['name'], 'Adriana') !== false) {
                echo "<p style='background:#e0f2fe; padding:8px 12px; border-radius:6px; font-weight:bold; color:#0369a1;'>✓ Updated {$emp_data['name']} (Intern): Annual Leave = {$new_al}, Sick Leave = {$new_sl}, Unpaid Leave = {$new_upl}</p>";
            }
        }
    }
}

echo "<h3 style='color:green;'>SUCCESS: Fully recalculated leave balances for $updated_count employees based on the imported calendar history!</h3>";
echo "<p>You can now check the <strong>Manage Employee > Leaves</strong> page to verify.</p>";
?>
