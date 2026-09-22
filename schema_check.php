<?php
include 'db_connect.php';
$res = mysqli_query($conn, 'DESCRIBE employees');
while($r = mysqli_fetch_assoc($res)) {
    echo $r['Field'] . ' ' . $r['Type'] . "\n";
}
