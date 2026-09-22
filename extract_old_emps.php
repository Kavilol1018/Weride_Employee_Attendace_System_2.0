<?php
$content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$p = strpos($content, 'INSERT INTO employees');
$end = strpos($content, ';', $p);
$block = substr($content, $p, $end - $p);
file_put_contents('old_employees_inserts.txt', $block);
?>
