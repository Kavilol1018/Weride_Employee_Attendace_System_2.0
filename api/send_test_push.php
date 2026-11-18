<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../db_connect.php';
include_once __DIR__ . '/../vapid_config.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['emp_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit();
}

$currentEmpId = $_SESSION['emp_id'] ?? $_SESSION['user_id'];
$target = isset($_REQUEST['target']) ? $_REQUEST['target'] : $currentEmpId;
$title = isset($_REQUEST['title']) ? $_REQUEST['title'] : 'WeRide Attendance';
$body = isset($_REQUEST['body']) ? $_REQUEST['body'] : '🔔 Test notification! Your WeRide alerts are now connected with the company logo.';
$url = isset($_REQUEST['url']) ? $_REQUEST['url'] : './';

$sentCount = WebPushSender::sendToEmployee($conn, $target, $title, $body, $url, 'weride-test-' . time());

echo json_encode([
    'success' => ($sentCount > 0),
    'sent_count' => $sentCount,
    'message' => $sentCount > 0 ? "Test push notification sent successfully to $sentCount device(s)!" : "No registered mobile devices found for account '$target'. Please open the WeRide app on your phone and tap 'Enable Mobile Push Alerts' in the notifications menu."
]);
