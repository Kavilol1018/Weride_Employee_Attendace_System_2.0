<?php
require 'db_connect.php';
$res = mysqli_query($conn, "SELECT DISTINCT shift_hour, COUNT(*) as cnt FROM employees GROUP BY shift_hour");
while ($r = mysqli_fetch_assoc($res)) {
    echo $r['shift_hour'] . ': ' . $r['cnt'] . PHP_EOL;
}
unlink(__FILE__);

