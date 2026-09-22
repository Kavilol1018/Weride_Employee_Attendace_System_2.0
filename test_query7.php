<?php
$content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');
$p = strpos($content, 'INSERT INTO short_breaks');
$end = strpos($content, ';', $p);
$block = substr($content, $p, $end - $p);
preg_match_all('/\(\d+,\s*\'FT-2606-001\'.*?\)/', $block, $matches);
foreach ($matches[0] as $m) {
    echo $m . "\n";
}
?>
