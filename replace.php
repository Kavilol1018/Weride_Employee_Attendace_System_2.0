<?php
$dir = new RecursiveDirectoryIterator('c:/xampp/htdocs/we-ride-system-main');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
foreach($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    if (strpos($content, 'Emergency Leave') !== false || strpos($content, 'emergency leave') !== false || strpos($content, 'EMERGENCY') !== false) {
        $content = str_replace('Emergency Leave', 'Emergency Leave', $content);
        $content = str_replace('emergency leave', 'emergency leave', $content);
        // Be careful with EMERGENCY -> EMERGENCY, it might break EMERGENCY badge CSS if there's any.
        // Actually, $leave_status === 'EMERGENCY' is used in report logic.
        $content = str_replace("'EMERGENCY'", "'EMERGENCY'", $content);
        $content = str_replace('"EMERGENCY"', '"EMERGENCY"', $content);
        $content = str_replace('EMERGENCY', 'EMERGENCY', $content); // In case of bare EMERGENCY string
        
        file_put_contents($path, $content);
        $count++;
    }
}
echo "Replaced in $count files.";
?>
