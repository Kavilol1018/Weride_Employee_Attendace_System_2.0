<?php
$old_sql_lines = file('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$new_sql_lines = file('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');

$mapping = [];
$in_block = false;
foreach ($old_sql_lines as $line) {
    if (strpos($line, 'INSERT INTO employees') !== false) {
        $in_block = true;
        continue;
    }
    if ($in_block) {
        // match: (300, 'FT-2606-001',
        if (preg_match('/^\((\d+),\s*\'([^\']+)\'/', trim($line), $matches)) {
            $mapping[$matches[2]] = $matches[1];
        }
        if (strpos($line, ';') !== false && !preg_match('/,\s*$/', trim($line))) {
            $in_block = false;
        }
    }
}

// Now replace in new SQL
$in_block = false;
foreach ($new_sql_lines as $k => $line) {
    if (strpos($line, 'INSERT INTO employees') !== false) {
        $in_block = true;
        continue;
    }
    if ($in_block) {
        if (preg_match('/^\((\d+),\s*\'([^\']+)\'(.*)$/', trim($line), $matches)) {
            $emp_id = $matches[2];
            $rest = $matches[3];
            if (isset($mapping[$emp_id])) {
                $old_id = $mapping[$emp_id];
                $new_line = "(" . $old_id . ", '" . $emp_id . "'" . $rest . "\n";
                $new_sql_lines[$k] = $new_line;
            }
        }
        if (strpos($line, ';') !== false && !preg_match('/,\s*$/', trim($line))) {
            $in_block = false;
        }
    }
}

file_put_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1_mapped.sql', implode("", $new_sql_lines));
echo "Mapped " . count($mapping) . " employees.\n";
?>
