-- Active: 1779784392021@@127.0.0.1@3306
<?php
session_start();
include __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'tl')) {
    echo json_encode(['alerts' => []]);
    exit();
}

$alerts = [];
$query = mysqli_query($conn, "SELECT id, message FROM system_alerts WHERE is_read = 0");
while ($row = mysqli_fetch_assoc($query)) {
    $alerts[] = $row['message'];
    mysqli_query($conn, "UPDATE system_alerts SET is_read = 1 WHERE id = " . $row['id']);
}

echo json_encode(['alerts' => $alerts]);
?>
