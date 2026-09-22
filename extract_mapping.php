<?php
$old_content = file_get_contents('c:\\xampp\\htdocs\\we-ride-system-main\\old_kavines_utf8.sql');
$new_content = file_get_contents('c:\\xampp\\htdocs\\we-ride-system-main\\phase2_1_utf8.sql');

function getEmpIdMap($content) {
    $map = [];
    $p = strpos($content, 'INSERT INTO employees');
    if ($p !== false) {
        $end = strpos($content, ';', $p);
        $block = substr($content, $p, $end - $p);
        
        // Match tuples: (id, 'emp_id', ...)
        preg_match_all('/\(\s*(\d+)\s*,\s*\'([A-Z0-9\-]+)\'/', $block, $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {
            $map[$matches[1][$i]] = $matches[2][$i];
        }
    }
    return $map;
}

$old_map = getEmpIdMap($old_content);
$new_map = getEmpIdMap($new_content);

$final_mapping = [];
foreach ($new_map as $id => $new_emp_id) {
    if (isset($old_map[$id]) && $old_map[$id] !== $new_emp_id) {
        $final_mapping[$new_emp_id] = $old_map[$id];
    }
}

print_r($final_mapping);
?>
