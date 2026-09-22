<?php
$old_lines = file('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$mapping = [];
$in_block = false;
foreach ($old_lines as $line) {
    if (strpos($line, 'INSERT INTO employees') !== false) {
        $in_block = true;
        continue;
    }
    if ($in_block) {
        if (preg_match('/^\((\d+),\s*\'([^\']+)\'/', trim($line), $matches)) {
            $mapping[$matches[2]] = $matches[1];
        }
        if (strpos($line, ';') !== false && substr(trim($line), -1) === ';') {
            $in_block = false;
        }
    }
}
echo "Mapped " . count($mapping) . " employees.\n";

$new_lines = file('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql', FILE_IGNORE_NEW_LINES);
$in_block = false;
foreach ($new_lines as $k => $line) {
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
                $new_lines[$k] = "(" . $old_id . ", '" . $emp_id . "'" . $rest;
            }
        }
        if (strpos($line, ';') !== false && substr(trim($line), -1) === ';') {
            $in_block = false;
        }
    }
}
file_put_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1_mapped.sql', implode("\n", $new_lines));
echo "Saved successfully.";
?>
