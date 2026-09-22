<?php
$content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$p = strpos($content, 'INSERT INTO short_breaks');
$end = strpos($content, ';', $p);
$block = substr($content, $p, $end - $p);
preg_match_all('/\(\d+,\s*\'FT-2606-001\'.*?\)/', $block, $matches);
echo count($matches[0]) . " records found\n";
?>
