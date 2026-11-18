<?php
$files = glob('*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'src="assets/js/theme_toggle.js?v=<?php echo time(); ?>"') !== false) {
        $content = str_replace(
            'src="assets/js/theme_toggle.js?v=<?php echo time(); ?>"', 
            'src="assets/js/theme_toggle.js?v=<?php echo time(); ?>"', 
            $content
        );
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
