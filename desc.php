<?php
include 'db_connect.php';
$res = mysqli_query($conn, 'DESCRIBE employees');
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>
