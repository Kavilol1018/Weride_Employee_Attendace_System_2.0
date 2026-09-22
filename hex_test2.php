<?php
$old_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$p1 = strpos($old_sql, 'INSERT INTO employees');
$p2 = strpos($old_sql, 'INSERT INTO employees');
echo "p1: " . var_export($p1, true) . "\n";
echo "p2: " . var_export($p2, true) . "\n";

$pos = strpos($old_sql, 'employees');
if ($pos !== false) {
    echo substr($old_sql, $pos - 20, 50);
}
?>
