<?php
$old_file = 'c:\\xampp\\htdocs\\we-ride-system-main\\old_employees_utf8.txt';
$old_content = file_get_contents($old_file);

$mapping = [];
preg_match_all('/^\((\d+),\s*\'([^\']+)\'/m', $old_content, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $mapping[$m[2]] = $m[1];
}

echo "Mapped " . count($mapping) . " employees.\n";

$new_file = 'C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql';
$new_content = file_get_contents($new_file);

$in_employees = false;
$new_lines = explode("\n", $new_content);
foreach ($new_lines as $k => $line) {
    if (strpos($line, 'INSERT INTO employees') !== false) {
        $in_employees = true;
        continue;
    }
    if ($in_employees) {
        if (preg_match('/^\((\d+),\s*\'([^\']+)\'(.*)$/', $line, $m)) {
            $emp_id = $m[2];
            if (isset($mapping[$emp_id])) {
                $new_lines[$k] = "(" . $mapping[$emp_id] . ", '" . $emp_id . "'" . $m[3];
            }
        }
        if (strpos($line, ';') !== false && !preg_match('/VALUES\s*$/i', $line)) {
            $in_employees = false;
        }
    }
}

file_put_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1_mapped.sql', implode("\n", $new_lines));
echo "Successfully wrote mapped SQL.";
?>
