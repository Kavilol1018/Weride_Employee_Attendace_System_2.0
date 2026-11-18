<?php
$dir = new RecursiveDirectoryIterator('.');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
foreach($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    if(strpos($content, 'H:i') !== false) {
        $new_content = str_replace('H:i', 'H:i', $content);
        file_put_contents($path, $new_content);
        echo 'Updated: ' . $path . PHP_EOL;
    }
}
echo "Done replacing H:i with H:i\n";
?>
