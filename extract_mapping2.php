<?php
$old_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$new_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');

// Detect encoding and convert to UTF-8 if necessary
function ensure_utf8($str) {
    if (substr($str, 0, 2) === "\xFF\xFE" || substr($str, 0, 2) === "\xFE\xFF") {
        return mb_convert_encoding($str, 'UTF-8', 'UTF-16LE');
    }
    return $str; // Assume utf-8
}

$old_sql = ensure_utf8($old_sql);
$new_sql = ensure_utf8($new_sql);

function getEmpIdMap($content) {
    $map = [];
    $p = strpos($content, 'INSERT INTO employees');
    if ($p !== false) {
        $end = strpos($content, ';', $p);
        $block = substr($content, $p, $end - $p);
        preg_match_all('/\(\s*(\d+)\s*,\s*\'([A-Z0-9\-]+)\'/', $block, $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {
            $map[$matches[1][$i]] = $matches[2][$i];
        }
    }
    return $map;
}

$old_map = getEmpIdMap($old_sql);
$new_map = getEmpIdMap($new_sql);

$final_mapping = [];
foreach ($new_map as $id => $new_emp_id) {
    if (isset($old_map[$id]) && $old_map[$id] !== $new_emp_id) {
        $final_mapping[$new_emp_id] = $old_map[$id];
    }
}

echo "Found " . count($final_mapping) . " mappings.\n";
file_put_contents('emp_mappings.json', json_encode($final_mapping));
?>
