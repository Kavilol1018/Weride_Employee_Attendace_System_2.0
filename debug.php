<?php
$line = "(35, 'FT-2606-001', NULL, 'ID ONE', '\\\.QPO9/GPCl.KQySa7w8x23QLy2.7GFPi6esReempO2', 'Full time', 'qa', '12:00-13:00', '2026-06-10 05:01:06', NULL, 0, NULL, 0, NULL, NULL, '2026-07-09 04:13:36'),\r";
if (preg_match('/^\((\d+),\s*\'([^\']+)\'(.*)$/', $line, $m)) {
    echo "Matched! " . $m[2];
} else {
    echo "Did not match!";
}
?>
