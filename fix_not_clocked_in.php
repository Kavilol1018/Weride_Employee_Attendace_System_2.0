<?php
$dir = "c:\\xampp\\htdocs\\we-ride-system-main";
$files = glob($dir . "\\*.php");
foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    $content = str_replace(
        "<span class='badge' style='background:#dbeafe; color:#2563eb;'>Not Clocked In</span>",
        "<span class='badge' style='background:#dbeafe; color:#2563eb;'>Not Clocked In</span>",
        $content
    );

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated Not Clocked In in " . basename($file) . "\n";
    }
}
?>
