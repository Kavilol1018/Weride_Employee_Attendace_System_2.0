<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../db_connect.php';
include_once __DIR__ . '/../vapid_config.php';

$res = mysqli_query($conn, "SELECT id, emp_id, endpoint, p256dh, auth, user_agent, created_at, updated_at FROM push_subscriptions ORDER BY id DESC");
$subs = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $urlParts = parse_url($r['endpoint']);
        $subs[] = [
            'id' => $r['id'],
            'emp_id' => $r['emp_id'],
            'host' => $urlParts['host'] ?? 'unknown',
            'endpoint_preview' => substr($r['endpoint'], 0, 60) . '...',
            'p256dh_len' => strlen($r['p256dh']),
            'auth_len' => strlen($r['auth']),
            'user_agent' => $r['user_agent'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at']
        ];
    }
}

$results = [];
if (isset($_GET['send_all'])) {
    $q = mysqli_query($conn, "SELECT id, emp_id, endpoint, p256dh, auth FROM push_subscriptions ORDER BY id DESC");
    while ($sub = mysqli_fetch_assoc($q)) {
        $host = parse_url($sub['endpoint'], PHP_URL_HOST);
        $res = WebPushSender::sendToSubscription($sub, "WeRide Test", "Test alert with logo", "weride_insides.php", "test-" . time());
        $results[] = [
            'id' => $sub['id'],
            'emp_id' => $sub['emp_id'],
            'host' => $host,
            'success' => $res['success'],
            'statusCode' => $res['statusCode'],
            'error' => $res['error'],
            'response' => $res['response']
        ];
    }
}

echo json_encode([
    'total_subscriptions' => count($subs),
    'subscriptions' => $subs,
    'send_results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
