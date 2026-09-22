<?php
$old_file = 'c:\\xampp\\htdocs\\we-ride-system-main\\old_employees_utf8.txt';
$old_content = file_get_contents($old_file);
$old_map = [];
preg_match_all('/^\((\d+),\s*\'([^\']+)\'/m', $old_content, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $old_map[$m[2]] = $m[1];
}

$new_file = 'C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql';
$new_content = file_get_contents($new_file);
$p = strpos($new_content, 'INSERT INTO employees');
$end = strpos($new_content, ';', $p);
$new_block = substr($new_content, $p, $end - $p);
$new_map = [];
preg_match_all('/^\((\d+),\s*\'([^\']+)\'/m', $new_block, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $new_map[$m[2]] = $m[1];
}

foreach ($new_map as $emp_id => $new_id) {
    if (isset($old_map[$emp_id])) {
        if ($old_map[$emp_id] != $new_id) {
            echo "Mismatch for $emp_id: Old=$old_map[$emp_id], New=$new_id\n";
        }
    } else {
        echo "$emp_id is only in NEW\n";
    }
}
?>
