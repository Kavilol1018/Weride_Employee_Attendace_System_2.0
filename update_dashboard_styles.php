<?php
$files = [
    'admin_dashboard.php', 
    'hr_dashboard.php', 
    'gl_live_attendance.php', 
    'tl_dashboard.php', 
    'gl_dashboard.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // 1. Add uppercase to the name cells
    // The name cells usually look like: <td style='font-weight: 600; color: #0f172a;'>
    // Or sometimes just font-weight: 600; ... Let's be robust and target the exact htmlspecialchars($row['name'])
    
    // For name column
    $content = preg_replace(
        '/<td style=\'([^\']+)\'>(?=" \. htmlspecialchars\(\$row\[\'name\'\]\))/', 
        '<td style=\'$1; text-transform: uppercase;\'>', 
        $content
    );
    
    // Just in case it's double quotes
    $content = preg_replace(
        '/<td style="([^"]+)">(?=\s*<\?= htmlspecialchars\(\$row\[\'name\'\]\))/', 
        '<td style="$1; text-transform: uppercase;">', 
        $content
    );

    // Also some might be written as:
    // echo "<td style='font-weight: 600; color: #0f172a; text-transform: uppercase;'>" . htmlspecialchars($row['name']) . "</td>";
    // We can just add text-transform: uppercase to it.
    $content = str_replace(
        "<td style='font-weight: 600; color: #0f172a;'>\" . htmlspecialchars(\$row['name'])", 
        "<td style='font-weight: 600; color: #0f172a; text-transform: uppercase;'>\" . htmlspecialchars(\$row['name'])", 
        $content
    );

    // 2. Capitalize the employment type badge
    // echo "<td><span style='background: {$color['bg']}; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: {$color['text']};'>" . htmlspecialchars($row['employment_type']) . "</span></td>";
    $content = str_replace(
        "font-weight: 600; color: {\$color['text']};'>\" . htmlspecialchars(\$row['employment_type'])", 
        "font-weight: 600; color: {\$color['text']}; text-transform: capitalize;'>\" . htmlspecialchars(\$row['employment_type'])", 
        $content
    );

    file_put_contents($file, $content);
    echo "Updated $file\n";
}
?>
