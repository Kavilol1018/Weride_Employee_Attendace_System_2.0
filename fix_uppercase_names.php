<?php
// One-time script to uppercase all employee names in the database
include 'db_connect.php';

$result = mysqli_query($conn, "UPDATE employees SET name = UPPER(name)");
if ($result) {
    $affected = mysqli_affected_rows($conn);
    echo "✅ Done! Updated $affected employee names to uppercase.";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}
?>
