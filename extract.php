<?php
$old_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$p = strpos($old_sql, 'INSERT INTO employees');
$end = strpos($old_sql, ';', $p);
$block = substr($old_sql, $p, $end - $p);
preg_match_all('/\((\d+),\s*\'([A-Z0-9\-]+)\'/', $block, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    echo $m[2] . " => " . $m[1] . "\n";
}
?>
