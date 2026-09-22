<?php
$content = file_get_contents('c:\\xampp\\htdocs\\we-ride-system-main\\phase2_1_utf8.sql');
$p = strpos($content, 'CREATE TABLE short_breaks');
if ($p !== false) {
    $end = strpos($content, ';', $p);
    echo substr($content, $p, $end - $p);
} else {
    echo "Not found";
}
?>
