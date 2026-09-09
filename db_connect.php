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

function check_leave_capacity($conn, $start_date, $end_date, $emp_id) {
    try {
        $gl_query = mysqli_query($conn, "SELECT IFNULL(NULLIF(group_leader_id, ''), emp_id) as gl_id FROM employees WHERE emp_id = '$emp_id'");
        $gl_row = mysqli_fetch_assoc($gl_query);
        if (!$gl_row) return true; // Safety check
        
        $gl_id = $gl_row['gl_id'];
        
        $start_dt = new DateTime($start_date);
        $end_dt = new DateTime($end_date);
        $end_dt->modify('+1 day'); // Make interval inclusive of end date
        
        $period = new DatePeriod($start_dt, new DateInterval('P1D'), $end_dt);
        
        foreach ($period as $dt) {
            $date_str = $dt->format('Y-m-d');
            
            $query = "SELECT COUNT(DISTINCT l.emp_id) as count 
                      FROM leave_requests l
                      JOIN employees e ON l.emp_id = e.emp_id
                      WHERE l.leave_type = 'Annual Leave'
                      AND l.status IN ('approved', 'pending', 'pending_hr') 
                      AND l.start_date <= '$date_str' AND l.end_date >= '$date_str'
                      AND (e.emp_id = '$gl_id' OR e.group_leader_id = '$gl_id')";
            $result = mysqli_query($conn, $query);
            
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                if ($row['count'] >= 5) {
                    return "Action Denied: The Annual Leave capacity (5 people) for your team on " . $dt->format('d M Y') . " has already been reached. Please choose different dates.";
                }
            }
        }
    } catch (Exception $e) {
        return "Invalid date format.";
    }
    return true;
}
