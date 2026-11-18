<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../db_connect.php';

if (!isset($_SESSION['emp_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$emp_id = $_SESSION['emp_id'] ?? $_SESSION['user_id'];

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid subscription payload']);
    exit();
}

$endpoint = mysqli_real_escape_string($conn, $data['endpoint']);
$p256dh = mysqli_real_escape_string($conn, $data['keys']['p256dh']);
$auth = mysqli_real_escape_string($conn, $data['keys']['auth']);
$userAgent = mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT'] ?? '');
$escapedEmpId = mysqli_real_escape_string($conn, $emp_id);

$sql = "INSERT INTO push_subscriptions (emp_id, endpoint, p256dh, auth, user_agent)
        VALUES ('$escapedEmpId', '$endpoint', '$p256dh', '$auth', '$userAgent')
        ON DUPLICATE KEY UPDATE 
            emp_id = VALUES(emp_id),
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            user_agent = VALUES(user_agent),
            updated_at = NOW()";

$result = mysqli_query($conn, $sql);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Push subscription saved successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
