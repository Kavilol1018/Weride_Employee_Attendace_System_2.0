<?php
$conn = mysqli_connect('127.0.0.1', 'root', '', 'we_ride');
$res = mysqli_query($conn, "DESCRIBE employees");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
