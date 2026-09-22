<?php
$old_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');
echo "OLD HEX: " . bin2hex(substr($old_sql, 0, 20)) . "\n";
$new_sql = file_get_contents('C:\\Users\\PC\\Downloads\\u251904595_phase2_1.sql');
echo "NEW HEX: " . bin2hex(substr($new_sql, 0, 20)) . "\n";
?>
