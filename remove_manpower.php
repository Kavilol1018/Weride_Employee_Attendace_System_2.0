<?php
$files = ['admin_dashboard.php', 'hr_dashboard.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // 1. Remove MANPOWER SQL
    $content = preg_replace('/\/\/ MANPOWER DATA:.*?\/\/ LATE DATA:/s', '// LATE DATA:', $content);

    // 2. Remove checkbox
    $content = preg_replace('/<label class="dropdown-item">\s*<input type=\'checkbox\' checked onchange=\'document\.getElementById\("manpower-section"\).*?> Show Manpower Overview\s*<\/label>/s', '', $content);

    // 3. Remove flex container with manpowerChart and manpower-section
    $start_token = '<div style="display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap;">';
    $start_pos = strpos($content, $start_token);
    if ($start_pos !== false) {
        // Find the specific end token based on indentation
        $end_token = '                    </tbody>
                </table>
                </div>
            </div>';
        $end_pos = strpos($content, $end_token, $start_pos);
        if ($end_pos !== false) {
            $end_pos += strlen($end_token);
            $content = substr($content, 0, $start_pos) . substr($content, $end_pos);
        } else {
            // fallback if indentation varies
            $end_token = '</table>' . "\n" . '                </div>' . "\n" . '            </div>';
            $end_pos = strpos($content, $end_token, $start_pos);
            if ($end_pos !== false) {
                $end_pos += strlen($end_token);
                $content = substr($content, 0, $start_pos) . substr($content, $end_pos);
            }
        }
    }

    // 4. Remove Chart JS
    $content = preg_replace('/const ctx = document\.getElementById\(\'manpowerChart\'\)\.getContext\(\'2d\'\);.*?new Chart\(ctx, \{.*?\}\);/s', '', $content);
    
    // 5. Remove AJAX updates for manpower-section
    $content = preg_replace('/const newSection = doc\.getElementById\(\'manpower-section\'\);.*?if\s*\(newSection\)\s*\{.*?document\.getElementById\(\'manpower-section\'\)\.innerHTML = newSection\.innerHTML;.*?}/s', '', $content);

    // 6. Remove clickable rows logic for manpower
    $content = preg_replace('/const rows = document\.querySelectorAll\(\'#manpower-section table tbody tr\'\);.*?\}\);/s', '', $content);

    file_put_contents($file, $content);
    echo "Cleaned $file\n";
}
?>
