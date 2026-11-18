<?php
mysqli_report(MYSQLI_REPORT_OFF);
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    // Local XAMPP Credentials
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "u251904595_lunchbreak"; 
} else {
    // Hostinger Live Credentials
    $host = "localhost"; // Usually localhost on Hostinger as well
    $username = "u251904595_phase2_1";
    $password = "Weridekl1234"; 
    $database = "u251904595_phase2_1";
}

$conn = @mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// FORCE CORRECT TIMEZONE GLOBALLY
date_default_timezone_set('Asia/Kuala_Lumpur');
mysqli_query($conn, "SET time_zone = '+08:00'");



// Ensure session is active with 30-day lifetime
if (session_status() === PHP_SESSION_NONE) {
    $cookie_lifetime = 60 * 60 * 24 * 30; // 30 days
    ini_set('session.gc_maxlifetime', $cookie_lifetime);
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => $cookie_lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

// Auto Schema Migration Setup (run once per session to maintain high page loading speed)
if (empty($_SESSION['db_setup_done'])) {
    include_once __DIR__ . '/db_setup.php';
    $_SESSION['db_setup_done'] = true;
}

// Check for Persistent "Stay Logged In" Cookie if session is empty
if (empty($_SESSION['user_id']) && !empty($_COOKIE['weride_remember'])) {
    $cookie_parts = explode(':', $_COOKIE['weride_remember']);
    if (count($cookie_parts) === 2) {
        $selector = mysqli_real_escape_string($conn, $cookie_parts[0]);
        $validator = $cookie_parts[1];
        
        $token_query = mysqli_query($conn, "SELECT id, emp_id, token_hash, expires_at FROM remember_tokens WHERE selector = '$selector' AND expires_at > NOW() LIMIT 1");
        if ($token_query && mysqli_num_rows($token_query) > 0) {
            $token_row = mysqli_fetch_assoc($token_query);
            if (hash_equals($token_row['token_hash'], hash('sha256', $validator))) {
                // Token matches! Auto-login the employee
                $saved_emp_id = mysqli_real_escape_string($conn, $token_row['emp_id']);
                $emp_query = mysqli_query($conn, "SELECT id, emp_id, name, role, profile_picture FROM employees WHERE emp_id = '$saved_emp_id' LIMIT 1");
                if ($emp_query && $emp_row = mysqli_fetch_assoc($emp_query)) {
                    $_SESSION['user_id'] = $emp_row['id'];
                    $_SESSION['emp_id'] = $emp_row['emp_id'];
                    $_SESSION['name'] = $emp_row['name'];
                    $_SESSION['role'] = $emp_row['role'];
                    $_SESSION['profile_picture'] = $emp_row['profile_picture'] ?? null;
                    if (empty($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                }
            }
        }
    }
}

$role_slug = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Global helper to insert in-app notification AND send Direct Web Push to employee's mobile/desktop devices
 */
function notify_employee($conn, $emp_id, $title, $message, $url = './', $tag = null) {
    if (!$conn || empty($emp_id)) return false;
    $escaped_emp = mysqli_real_escape_string($conn, $emp_id);
    $escaped_msg = mysqli_real_escape_string($conn, $message);
    @mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message, is_read, created_at) VALUES ('$escaped_emp', '$escaped_msg', 0, NOW())");
    
    if (file_exists(__DIR__ . '/vapid_config.php')) {
        require_once __DIR__ . '/vapid_config.php';
        WebPushSender::sendToEmployee($conn, $emp_id, $title, $message, $url, $tag ?: ('notif-' . time()));
    }
    return true;
}

function check_leave_capacity(mysqli $conn, string $start_date, string $end_date, string $emp_id, string $leave_type = 'Annual Leave') {
    // Capacity limit of 5 only applies to Annual Leave
    if (!empty($leave_type) && strpos(strtoupper($leave_type), 'ANNUAL') === false) {
        return true;
    }
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

// --- SCHEDULED CHANGES LOGIC ---
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS scheduled_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(50),
    change_type VARCHAR(50),
    new_value VARCHAR(100),
    effective_date DATE,
    applied TINYINT(1) DEFAULT 0
)");

// Apply any scheduled changes that are due today or earlier
$sch_query = mysqli_query($conn, "SELECT id, emp_id, change_type, new_value FROM scheduled_changes WHERE effective_date <= CURDATE() AND applied = 0");
if ($sch_query && mysqli_num_rows($sch_query) > 0) {
    while ($sch = mysqli_fetch_assoc($sch_query)) {
        $e_id = mysqli_real_escape_string($conn, $sch['emp_id']);
        $val = mysqli_real_escape_string($conn, $sch['new_value']);
        $ctype = $sch['change_type'];
        
        if ($ctype == 'shift_hour') {
            mysqli_query($conn, "UPDATE employees SET shift_hour = '$val' WHERE emp_id = '$e_id'");
        } elseif ($ctype == 'lunch_window') {
            mysqli_query($conn, "UPDATE employees SET lunch_window = '$val' WHERE emp_id = '$e_id'");
        }
        
        $s_id = $sch['id'];
        mysqli_query($conn, "UPDATE scheduled_changes SET applied = 1 WHERE id = $s_id");
    }
}
