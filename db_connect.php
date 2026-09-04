<?php
mysqli_report(MYSQLI_REPORT_OFF);
$host = "localhost";
$username = "u251904595_weride";
$password = "Weride_2026";
$database = "u251904595_lunchbreak";

$conn = @mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    // Fallback to local XAMPP default credentials
    $conn = @mysqli_connect($host, "root", "", $database);
}

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// FORCE CORRECT TIMEZONE GLOBALLY
date_default_timezone_set('Asia/Kuala_Lumpur');
mysqli_query($conn, "SET time_zone = '+08:00'");

// 1 Hour Session Timeout Logic
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    $timeout_duration = 3600; // 1 hour in seconds
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        
        // Return JSON if it looks like an AJAX request
        if (isset($_POST['action']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Session expired. Please refresh the page and log in again.']);
            exit();
        }
        
        header("Location: index.php?error=" . urlencode("Session expired due to 1 hour of inactivity. Please log in again."));
        exit();
    }
    $_SESSION['last_activity'] = time();
}

$role_slug = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auto Schema Migration Setup
include_once __DIR__ . '/db_setup.php';
