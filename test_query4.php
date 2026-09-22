<?php
$content = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
preg_match_all('/INSERT INTO short_breaks.*?VALUES\s*(.*?;)/s', $content, $matches);
$periods = [];
foreach ($matches[1] as $block) {
    preg_match_all('/^\(\d+,\s*\'[^\']+\',\s*\'([^\']*)\'/m', $block, $m);
    foreach ($m[1] as $p) {
        $periods[$p] = true;
    }
}
print_r(array_keys($periods));
?>
