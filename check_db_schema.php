<?php
include 'db_connect.php';
$q=mysqli_query($conn, 'DESCRIBE leave_requests');
while($r=mysqli_fetch_assoc($q)) { print_r($r); }
?>
