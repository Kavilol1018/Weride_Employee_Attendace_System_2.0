<?php
$conn = mysqli_connect('localhost', 'root', '', 'u251904595_phase2');
if (!$conn) die('Failed');
$sql = "SELECT DISTINCT period FROM short_breaks";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
