<?php
// Compare employee IDs between old DB (u251904595_kavinesganesen) and new DB (u251904595_phase2_1)

// Extract emp_ids from SQL file
function extractEmpIds(string $file) {
    $content = file_get_contents($file);
    $employees = [];
    
    // Find the employees INSERT block
    if (preg_match("/INSERT INTO `employees`.*?VALUES\s*\n(.*?);\s*\n/s", $content, $match)) {
        $data = $match[1];
        // Match each row - emp_id is the 2nd value in each row
        if (preg_match_all("/\(\d+,\s*'([^']+)',/", $data, $ids)) {
            foreach ($ids[1] as $id) {
                $employees[$id] = true;
            }
        }
    }
    return $employees;
}

// Extract names too
function extractEmpData(string $file) {
    $content = file_get_contents($file);
    $employees = [];
    
    if (preg_match("/INSERT INTO `employees`.*?VALUES\s*\n(.*?);\s*\n/s", $content, $match)) {
        $data = $match[1];
        // Match emp_id (2nd), name (4th), employment_type (6th), role (7th)
        if (preg_match_all("/\(\d+,\s*'([^']*)',\s*'?([^',]*)'?,\s*'([^']*)',/", $data, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $employees[$m[1]] = [
                    'welabel' => $m[2],
                    'name' => $m[3]
                ];
            }
        }
    }
    return $employees;
}

$old_file = 'c:/xampp/htdocs/we-ride-system-main/u251904595_old.sql';
$new_file = 'c:/xampp/htdocs/we-ride-system-main/u251904595_phase2_1.sql';

$old_ids = extractEmpIds($old_file);
$new_ids = extractEmpIds($new_file);

$old_data = extractEmpData($old_file);
$new_data = extractEmpData($new_file);

echo "=== OLD DATABASE (u251904595_kavinesganesen) ===\n";
echo "Total employees: " . count($old_ids) . "\n";
echo "Employee IDs: " . implode(', ', array_keys($old_ids)) . "\n\n";

echo "=== NEW DATABASE (u251904595_phase2_1) ===\n";
echo "Total employees: " . count($new_ids) . "\n";
echo "Employee IDs: " . implode(', ', array_keys($new_ids)) . "\n\n";

echo "=== ONLY IN OLD (removed from new) ===\n";
$only_old = array_diff_key($old_ids, $new_ids);
if (empty($only_old)) {
    echo "None\n";
} else {
    foreach (array_keys($only_old) as $id) {
        $name = isset($old_data[$id]) ? $old_data[$id]['name'] : '?';
        echo "  - $id ($name)\n";
    }
}
echo "\n";

echo "=== ONLY IN NEW (added in new) ===\n";
$only_new = array_diff_key($new_ids, $old_ids);
if (empty($only_new)) {
    echo "None\n";
} else {
    foreach (array_keys($only_new) as $id) {
        $name = isset($new_data[$id]) ? $new_data[$id]['name'] : '?';
        echo "  - $id ($name)\n";
    }
}
echo "\n";

echo "=== IN BOTH (common) ===\n";
$common = array_intersect_key($old_ids, $new_ids);
echo "Total common: " . count($common) . "\n";
