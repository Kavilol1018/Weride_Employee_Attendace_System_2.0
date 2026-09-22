<?php
$old_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$new_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');

// find old block
$p = strpos($old_sql, 'INSERT INTO employees');
$end = strpos($old_sql, ';', $p);
$old_block = substr($old_sql, $p, $end - $p);

$mapping = [];
preg_match_all('/\((\d+),\s*\'([^\']+)\'/', $old_block, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $mapping[$m[2]] = $m[1];
}

echo "Mapped " . count($mapping) . " employees.\n";

// find new block
$p = strpos($new_sql, 'INSERT INTO employees');
$end = strpos($new_sql, ';', $p);
$new_block = substr($new_sql, $p, $end - $p);

$new_block = preg_replace_callback('/\((\d+),\s*\'([^\']+)\'/', function($m) use ($mapping) {
    if (isset($mapping[$m[2]])) {
        return '(' . $mapping[$m[2]] . ", '" . $m[2] . "'";
    }
    return $m[0];
}, $new_block);

$new_sql = substr_replace($new_sql, $new_block, $p, $end - $p);

file_put_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1_updated_ids.sql', $new_sql);
echo "Saved to u251904595_phase2_1_updated_ids.sql";
?>
