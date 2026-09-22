<?php
// Extract employees from OLD SQL
$old_content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');

// Find INSERT INTO employees blocks - use escaped backtick
$pattern = '/INSERT INTO .employees.[^;]+/s';
preg_match_all($pattern, $old_content, $old_blocks);
echo "Old blocks found: " . count($old_blocks[0]) . "\n";

$old_employees = [];
foreach ($old_blocks[0] as $block) {
    // Match (id, 'emp_id', 'email_or_NULL', 'name', ...)
    preg_match_all('/\((\d+),\s*\'([^\']*)\',\s*(?:\'([^\']*)\'\s*|NULL\s*),\s*\'([^\']*)\'/m', $block, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $old_employees[] = ['id' => $m[1], 'emp_id' => $m[2], 'email' => isset($m[3]) ? $m[3] : '', 'name' => $m[4]];
    }
}

// Extract employees from NEW SQL
$new_content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');
preg_match_all($pattern, $new_content, $new_blocks);
echo "New blocks found: " . count($new_blocks[0]) . "\n";

$new_employees = [];
foreach ($new_blocks[0] as $block) {
    preg_match_all('/\((\d+),\s*\'([^\']*)\',\s*(?:\'([^\']*)\'\s*|NULL\s*),\s*\'([^\']*)\'/m', $block, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $new_employees[] = ['id' => $m[1], 'emp_id' => $m[2], 'email' => isset($m[3]) ? $m[3] : '', 'name' => $m[4]];
    }
}

echo "\n=== OLD EMPLOYEES (" . count($old_employees) . " total) ===\n";
foreach ($old_employees as $e) {
    echo "ID={$e['id']} | emp_id={$e['emp_id']} | email={$e['email']} | name={$e['name']}\n";
}

echo "\n=== NEW EMPLOYEES (" . count($new_employees) . " total) ===\n";
foreach ($new_employees as $e) {
    echo "ID={$e['id']} | emp_id={$e['emp_id']} | email={$e['email']} | name={$e['name']}\n";
}

// Find differences by matching on name
echo "\n=== DIFFERENCES (by name match) ===\n";
foreach ($new_employees as $ne) {
    foreach ($old_employees as $oe) {
        if (strtolower($ne['name']) === strtolower($oe['name'])) {
            if ($ne['emp_id'] !== $oe['emp_id'] || $ne['id'] !== $oe['id']) {
                echo "Name: {$ne['name']}\n";
                echo "  OLD: id={$oe['id']}, emp_id={$oe['emp_id']}\n";
                echo "  NEW: id={$ne['id']}, emp_id={$ne['emp_id']}\n\n";
            }
        }
    }
}
?>
