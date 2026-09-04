<?php
include 'db_connect.php';
// Add columns just in case they are missing
mysqli_query($conn, "ALTER TABLE lunch_breaks ADD COLUMN category VARCHAR(100) DEFAULT 'Lunch'");
mysqli_query($conn, "ALTER TABLE lunch_breaks ADD COLUMN is_edited TINYINT(1) DEFAULT 0");
mysqli_query($conn, "ALTER TABLE short_breaks ADD COLUMN category VARCHAR(100) DEFAULT 'Short Break'");
mysqli_query($conn, "ALTER TABLE short_breaks ADD COLUMN is_edited TINYINT(1) DEFAULT 0");
echo "Done checking columns. ";
?>
