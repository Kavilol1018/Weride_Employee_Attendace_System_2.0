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

$testResult = null;
if (isset($_GET['send']) && !empty($subs)) {
    // Test send to the newest subscription
    $targetRes = mysqli_query($conn, "SELECT * FROM push_subscriptions ORDER BY id DESC LIMIT 1");
    if ($targetRes && $sub = mysqli_fetch_assoc($targetRes)) {
        $testResult = WebPushSender::sendToSubscription($sub, "WeRide Test", "Diagnostic test message", "weride_insides.php", "diag-" . time());
    }
}

echo json_encode([
    'total_subscriptions' => count($subs),
    'subscriptions' => $subs,
    'test_result' => $testResult
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
