<?php
function ensure_utf8($str) {
    if (substr($str, 0, 2) === "\xFF\xFE" || substr($str, 0, 2) === "\xFE\xFF") {
        return mb_convert_encoding($str, 'UTF-8', 'UTF-16LE');
    }
    return $str;
}

function extractMapping($file) {
    $sql = ensure_utf8(file_get_contents($file));
    $p = strpos($sql, 'INSERT INTO employees');
    if ($p === false) {
        die("No employees in $file\n");
    }
    $end = strpos($sql, ';', $p);
    $block = substr($sql, $p, $end - $p);
    
    // The format is: (300, 'IN-2606-011', '', 'Human Resource', ...)
    preg_match_all('/\(\s*(\d+)\s*,\s*\'([A-Z0-9\-]+)\'/', $block, $matches);
    
    $map = [];
    for ($i = 0; $i < count($matches[1]); $i++) {
        $map[$matches[1][$i]] = $matches[2][$i];
    }
    return $map;
}

$old_map = extractMapping('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$new_map = extractMapping('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');

$revert_mapping = [];
foreach ($new_map as $id => $new_emp_id) {
    if (isset($old_map[$id]) && $old_map[$id] !== $new_emp_id) {
        $revert_mapping[$new_emp_id] = $old_map[$id];
    }
}

echo "Found " . count($revert_mapping) . " employees to revert.\n";

$tables = [
    'access_logs' => 'emp_id',
    'attendance_logs' => 'emp_id',
    'break_change_requests' => 'emp_id',
    'break_override_requests' => 'emp_id',
    'deleted_employees' => 'emp_id',
    'employees' => 'emp_id',
    'employee_notifications' => 'emp_id',
    'lunch_breaks' => 'employee_id',
    'short_breaks' => 'emp_id',
    'leaves' => 'emp_id',
    'leave_requests' => 'emp_id',
    'employee_stats' => 'emp_id'
];

$sql_out = "-- Revert employee IDs from new (FT-xxx) to old (IN-xxx)\n\n";

// Disable foreign key checks just in case
$sql_out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($revert_mapping as $new_id => $old_id) {
    $sql_out .= "-- Reverting $new_id to $old_id\n";
    foreach ($tables as $table => $column) {
        $sql_out .= "UPDATE $table SET $column = '$old_id' WHERE $column = '$new_id';\n";
    }
    $sql_out .= "\n";
}

$sql_out .= "SET FOREIGN_KEY_CHECKS=1;\n";

file_put_contents('revert_emp_ids.sql', $sql_out);
echo "Generated revert_emp_ids.sql\n";
?>
