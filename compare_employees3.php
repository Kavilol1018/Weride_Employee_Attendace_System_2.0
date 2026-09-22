<?php
// Extract employees from OLD SQL
$old_content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$pattern = '/INSERT INTO .employees.[^;]+/s';
preg_match_all($pattern, $old_content, $old_blocks);

$old_employees = [];
foreach ($old_blocks[0] as $block) {
    preg_match_all('/\((\d+),\s*\'([^\']*)\',\s*(?:\'([^\']*)\'\s*|NULL\s*),\s*\'([^\']*)\'/m', $block, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $old_employees[] = ['id' => $m[1], 'emp_id' => $m[2], 'email' => isset($m[3]) ? $m[3] : '', 'name' => $m[4]];
    }
}

// Extract employees from NEW SQL
$new_content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');
preg_match_all($pattern, $new_content, $new_blocks);

$new_employees = [];
foreach ($new_blocks[0] as $block) {
    preg_match_all('/\((\d+),\s*\'([^\']*)\',\s*(?:\'([^\']*)\'\s*|NULL\s*),\s*\'([^\']*)\'/m', $block, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $new_employees[] = ['id' => $m[1], 'emp_id' => $m[2], 'email' => isset($m[3]) ? $m[3] : '', 'name' => $m[4]];
    }
}

// Build maps by emp_id
$old_by_empid = [];
foreach ($old_employees as $e) {
    $old_by_empid[$e['emp_id']] = $e;
}
$new_by_empid = [];
foreach ($new_employees as $e) {
    $new_by_empid[$e['emp_id']] = $e;
}

// Find emp_ids in NEW that don't exist in OLD
echo "=== emp_ids ONLY in NEW ===\n";
foreach ($new_by_empid as $eid => $e) {
    if (!isset($old_by_empid[$eid])) {
        echo "  $eid (name={$e['name']}, id={$e['id']})\n";
    }
}

// Find emp_ids in OLD that don't exist in NEW
echo "\n=== emp_ids ONLY in OLD ===\n";
foreach ($old_by_empid as $eid => $e) {
    if (!isset($new_by_empid[$eid])) {
        echo "  $eid (name={$e['name']}, id={$e['id']})\n";
    }
}

// Find ID differences for same emp_id
echo "\n=== ID differences for SAME emp_id ===\n";
foreach ($new_by_empid as $eid => $ne) {
    if (isset($old_by_empid[$eid])) {
        $oe = $old_by_empid[$eid];
        if ($ne['id'] !== $oe['id']) {
            echo "  emp_id=$eid: OLD id={$oe['id']}, NEW id={$ne['id']} (name={$ne['name']})\n";
        }
    }
}

// Match by email for employees only in one side
echo "\n=== Possible matches by EMAIL ===\n";
$old_by_email = [];
foreach ($old_employees as $e) {
    if (!empty($e['email'])) $old_by_email[strtolower($e['email'])] = $e;
}
foreach ($new_employees as $ne) {
    if (!empty($ne['email']) && !isset($old_by_empid[$ne['emp_id']])) {
        $key = strtolower($ne['email']);
        if (isset($old_by_email[$key])) {
            $oe = $old_by_email[$key];
            echo "  Email: {$ne['email']}\n";
            echo "    OLD: emp_id={$oe['emp_id']}, id={$oe['id']}, name={$oe['name']}\n";
            echo "    NEW: emp_id={$ne['emp_id']}, id={$ne['id']}, name={$ne['name']}\n\n";
        }
    }
}
?>
