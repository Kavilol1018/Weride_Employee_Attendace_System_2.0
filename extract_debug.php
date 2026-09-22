<?php
$old_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
$new_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');

function ensure_utf8($str) {
    if (substr($str, 0, 2) === "\xFF\xFE" || substr($str, 0, 2) === "\xFE\xFF") {
        return mb_convert_encoding($str, 'UTF-8', 'UTF-16LE');
    }
    return $str;
}

$old_sql = ensure_utf8($old_sql);
$new_sql = ensure_utf8($new_sql);

function printFirst5($content, $name) {
    $p = strpos($content, 'INSERT INTO employees');
    if ($p !== false) {
        $end = strpos($content, ';', $p);
        $block = substr($content, $p, $end - $p);
        preg_match_all('/\(\s*(\d+)\s*,\s*\'([A-Z0-9\-]+)\'/', $block, $matches);
        echo $name . " count: " . count($matches[1]) . "\n";
        for ($i = 0; $i < min(5, count($matches[1])); $i++) {
            echo $matches[1][$i] . " -> " . $matches[2][$i] . "\n";
        }
    } else {
        echo $name . " not found\n";
    }
}

printFirst5($old_sql, "OLD");
echo "\n";
printFirst5($new_sql, "NEW");
?>
