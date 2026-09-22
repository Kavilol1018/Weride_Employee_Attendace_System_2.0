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

// Extract employees from CURRENT LIVE SQL
$new_content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1 (1).sql');
preg_match_all($pattern, $new_content, $new_blocks);

$new_employees = [];
foreach ($new_blocks[0] as $block) {
    preg_match_all('/\((\d+),\s*\'([^\']*)\',\s*(?:\'([^\']*)\'\s*|NULL\s*),\s*\'([^\']*)\'/m', $block, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $new_employees[] = ['id' => $m[1], 'emp_id' => $m[2], 'email' => isset($m[3]) ? $m[3] : '', 'name' => $m[4]];
    }
}

echo "OLD employees: " . count($old_employees) . "\n";
echo "CURRENT LIVE employees: " . count($new_employees) . "\n\n";

// Build map by email (lowercase) for matching
$old_by_email = [];
foreach ($old_employees as $e) {
    if (!empty($e['email'])) {
        $old_by_email[strtolower($e['email'])] = $e;
    }
}

// Build map by name (lowercase) for matching
$old_by_name = [];
foreach ($old_employees as $e) {
    $old_by_name[strtolower($e['name'])] = $e;
}

// Build map by emp_id
$old_by_empid = [];
foreach ($old_employees as $e) {
    $old_by_empid[$e['emp_id']] = $e;
}

echo "=== EMPLOYEE ID DIFFERENCES (CURRENT vs OLD) ===\n";
echo str_pad("Name", 40) . str_pad("CURRENT emp_id", 20) . str_pad("OLD emp_id", 20) . "Matched By\n";
echo str_repeat("-", 100) . "\n";

$changes = [];
$no_match = [];

foreach ($new_employees as $ne) {
    $matched_old = null;
    $match_method = '';
    
    // Try matching by email first
    if (!empty($ne['email'])) {
        $key = strtolower($ne['email']);
        if (isset($old_by_email[$key])) {
            $matched_old = $old_by_email[$key];
            $match_method = 'email';
        }
    }
    
    // Try matching by exact name
    if (!$matched_old) {
        $key = strtolower($ne['name']);
        if (isset($old_by_name[$key])) {
            $matched_old = $old_by_name[$key];
            $match_method = 'name';
        }
    }
    
    // Try matching by emp_id
    if (!$matched_old && isset($old_by_empid[$ne['emp_id']])) {
        $matched_old = $old_by_empid[$ne['emp_id']];
        $match_method = 'emp_id';
    }
    
    if ($matched_old) {
        if ($ne['emp_id'] !== $matched_old['emp_id']) {
            echo str_pad($ne['name'], 40) . str_pad($ne['emp_id'], 20) . str_pad($matched_old['emp_id'], 20) . $match_method . "\n";
            $changes[] = ['current_empid' => $ne['emp_id'], 'old_empid' => $matched_old['emp_id'], 'name' => $ne['name']];
        }
    } else {
        $no_match[] = $ne;
    }
}

echo "\n=== EMPLOYEES WITH NO MATCH IN OLD DB ===\n";
foreach ($no_match as $e) {
    echo "  emp_id={$e['emp_id']} | name={$e['name']} | email={$e['email']}\n";
}

echo "\n=== TOTAL CHANGES NEEDED: " . count($changes) . " ===\n";
foreach ($changes as $c) {
    echo "  {$c['current_empid']} -> {$c['old_empid']} ({$c['name']})\n";
}
?>
