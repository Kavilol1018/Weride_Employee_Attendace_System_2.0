<?php
$content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');
$p = strpos($content, 'CREATE TABLE lunch_breaks');
if ($p === false) $p = strpos($content, 'CREATE TABLE lunch_breaks');
if ($p !== false) {
    $end = strpos($content, ';', $p);
    echo substr($content, $p, $end - $p);
} else {
    echo "Not found";
}
?>
