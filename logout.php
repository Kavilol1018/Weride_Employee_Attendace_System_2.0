<?php
session_start();
include_once __DIR__ . '/db_connect.php';

// Remove persistent remember token from database and clear cookie
if (!empty($_COOKIE['weride_remember'])) {
    $parts = explode(':', $_COOKIE['weride_remember']);
    if (count($parts) === 2) {
        $selector = mysqli_real_escape_string($conn, $parts[0]);
        mysqli_query($conn, "DELETE FROM remember_tokens WHERE selector = '$selector'");
    }
    setcookie('weride_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Clear session completely
session_unset();
session_destroy();

header("Location: index.php");
exit();
?>
