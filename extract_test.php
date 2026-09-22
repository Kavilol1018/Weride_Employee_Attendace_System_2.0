<?php
$old_content = file_get_contents('c:\\xampp\\htdocs\\we-ride-system-main\\old_kavines_utf8.sql');
$p = strpos($old_content, 'INSERT INTO employees');
if ($p !== false) {
    $end = strpos($old_content, ';', $p);
    $block = substr($old_content, $p, $end - $p);
    echo "BLOCK FOUND. LENGTH: " . strlen($block) . "\n";
    preg_match_all('/\(\s*(\d+)\s*,\s*\'([A-Z0-9\-]+)\'/', $block, $matches);
    echo "MATCHES: " . count($matches[1]) . "\n";
} else {
    echo "INSERT INTO employees not found\n";
}
?>
