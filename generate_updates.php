<?php
$csv = array_map('str_getcsv', file('c:/xampp/htdocs/we-ride-system-main/new_employees_data.csv'));
$header = array_shift($csv);

$sql = "ALTER TABLE `employees` MODIFY `annual_leave_balance` DECIMAL(5,1) DEFAULT 8.0;\n";
$sql .= "ALTER TABLE `employees` MODIFY `sick_leave_balance` DECIMAL(5,1) DEFAULT 14.0;\n\n";

// Mapping for Group Leaders based on the CSV
$gl_mapping = [
    'MAGESWARY A/P VADIVELLO' => 'FT-2606-023',
    'NUR SALMA BINTI MUHAMMAD SYAFIQ SOH' => 'FT-2608-001',
    'UMA SHIVANESHA A/L OMARPATHY' => 'FT-1008-001',
    'THANANIY A/P SELVANATHAN' => 'FT-2608-002',
    'WONG HUI XIN' => 'TL001'
];

foreach ($csv as $row) {
    if (empty($row[0])) continue;
    
    $emp_id = trim($row[0]);
    $name = addslashes(trim($row[1]));
    $welabel_id = trim($row[2]);
    $gl_name = trim($row[3]);
    $onboarding_date = trim($row[4]);
    $employment_type = trim($row[5]);
    $balance_al = (float)trim($row[6]);
    $balance_sl = (float)trim($row[7]);
    $shift_time = trim($row[8]);
    $lunch_time = trim($row[9]);
    $status = trim($row[10]); // E.g., 'Active'
    $offboarding_date = trim($row[11]);
    $position = strtolower(trim($row[12]));
    $separation_type = trim($row[13]);
    
    // Group Leader ID resolution
    $group_leader_id = isset($gl_mapping[$gl_name]) ? $gl_mapping[$gl_name] : 'NULL';
    $gl_val = $group_leader_id === 'NULL' ? "NULL" : "'$group_leader_id'";

    // Map position to role
    $role = 'employee';
    if (strpos($position, 'group lead') !== false) $role = 'gl';
    elseif (strpos($position, 'qa') !== false) $role = 'qa';
    elseif (strpos($position, 'team lead') !== false) $role = 'tl';
    elseif (strpos($position, 'hr') !== false) $role = 'hr';
    elseif (strpos($position, 'admin') !== false) $role = 'admin';
    
    // Format dates (e.g. 3-Aug-26 -> 2026-08-03)
    if (!empty($onboarding_date)) {
        $date = DateTime::createFromFormat('j-M-y', $onboarding_date);
        $formatted_date = $date ? $date->format('Y-m-d') : "NULL";
    } else {
        $formatted_date = "NULL";
    }
    
    $onboarding_val = $formatted_date === "NULL" ? "NULL" : "'$formatted_date'";

    $query = "UPDATE `employees` SET " .
             "`name` = '$name', " .
             "`welabel_id` = '$welabel_id', " .
             "`employment_type` = '$employment_type', " .
             "`annual_leave_balance` = '$balance_al', " .
             "`sick_leave_balance` = '$balance_sl', " .
             "`shift_hour` = '$shift_time', " .
             "`lunch_window` = '$lunch_time', " .
             "`role` = '$role', " .
             "`group_leader_id` = $gl_val, " .
             "`onboarding_date` = $onboarding_val " .
             "WHERE `emp_id` = '$emp_id';\n";
             
    $sql .= $query;
}

file_put_contents('c:/xampp/htdocs/we-ride-system-main/update_hostinger.sql', $sql);
echo "Generated update_hostinger.sql successfully.\n";
