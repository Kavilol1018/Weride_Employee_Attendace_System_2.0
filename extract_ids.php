<?php
$old_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$new_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');

// Extract all employees from old sql
$mapping = [];
if (preg_match('/INSERT INTO employees[^;]+;/', $old_sql, $matches)) {
    $insert_block = $matches[0];
    // Match each row: (id, 'emp_id', ...)
    preg_match_all('/\((\d+),\s*\'([^\']+)\'/', $insert_block, $row_matches, PREG_SET_ORDER);
    foreach ($row_matches as $match) {
        $id = $match[1];
        $emp_id = $match[2];
        $mapping[$emp_id] = $id;
    }
}
print_r($mapping);
?>
