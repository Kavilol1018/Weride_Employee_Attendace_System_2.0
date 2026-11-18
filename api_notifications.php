<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['emp_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$current_emp_id = mysqli_real_escape_string($conn, $_SESSION['emp_id']);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper for relative time
function get_api_relative_time($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    if ($diff < 604800) return floor($diff / 86400) . "d ago";
    return date("M j, Y", $time);
}

if ($action === 'mark_read') {
    mysqli_query($conn, "UPDATE employee_notifications SET is_read = 1 WHERE emp_id = '$current_emp_id'");
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'get') {
    $notifications = [];
    $unread_cnt = 0;
    
    $res = mysqli_query($conn, "
        SELECT id, message, is_read, created_at 
        FROM employee_notifications 
        WHERE emp_id = '$current_emp_id' 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    if ($res) {
        while ($n = mysqli_fetch_assoc($res)) {
            $n['time_formatted'] = get_api_relative_time($n['created_at']);
            $notifications[] = $n;
            if ((int)$n['is_read'] === 0) {
                $unread_cnt++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_cnt
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit();
