<?php
$content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');
$p = strpos($content, 'CREATE TABLE lunch_breaks');
$end = strpos($content, ';', $p);
echo substr($content, $p, $end - $p);
?>
