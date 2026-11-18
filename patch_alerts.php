<?php
$files = ['admin_alerts.php', 'hr_alerts.php', 'gl_alerts.php'];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // Check if clocked_in logic already exists
    if (strpos($content, '$clock_in_sql') !== false) {
        continue;
    }

    // Insert clock_in_sql block right before leave_sql
    $clock_in_code = "
// Fetch clock-ins to ensure we don't give lunchtime violations to people who didn't clock in
\$clock_in_sql = \"SELECT emp_id, date FROM attendance_logs WHERE date BETWEEN '\$start_date' AND '\$end_date' AND clock_in IS NOT NULL\";
\$clock_in_res = mysqli_query(\$conn, \$clock_in_sql);
\$clocked_in = [];
if (\$clock_in_res) {
    while (\$r = mysqli_fetch_assoc(\$clock_in_res)) {
        \$clocked_in[\$r['emp_id']][\$r['date']] = true;
    }
}

// Fetch leaves";
    
    $content = str_replace("// Fetch leaves", ltrim($clock_in_code), $content);

    // Replace the Missed Lunchtime logic to include clock-in check
    $old_logic = "} elseif (!isset(\$breaks_by_emp_date[\$emp_id][\$date_str])) {";
    $new_logic = "} elseif (!isset(\$clocked_in[\$emp_id][\$date_str])) {
            // Did not clock in, do not give lunchtime violation!
        } elseif (!isset(\$breaks_by_emp_date[\$emp_id][\$date_str])) {";

    $content = str_replace($old_logic, $new_logic, $content);
    
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
