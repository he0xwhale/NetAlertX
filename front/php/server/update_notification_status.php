<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/php/templates/security.php';
require dirname(__FILE__).'/../server/init.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

$raw = file_get_contents('php://input');
$decoded = json_decode($raw, true);
if (!$decoded || !isset($decoded['status'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload"]);
    exit;
}

$targetStatus = $decoded['status'];
if (!in_array($targetStatus, ['unread', 'pending', 'resolved'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid status"]);
    exit;
}

$guids = [];
if (isset($decoded['guid'])) {
    $guids[] = $decoded['guid'];
} elseif (isset($decoded['guids']) && is_array($decoded['guids'])) {
    $guids = $decoded['guids'];
}

if (empty($guids)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing guid(s)"]);
    exit;
}

$userNotifPath = '/tmp/api/user_notifications.json';
if (!file_exists($userNotifPath)) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Notifications file not found"]);
    exit;
}

$items = json_decode(file_get_contents($userNotifPath), true) ?: [];
$updated = 0;

foreach ($items as &$item) {
    if (isset($item['guid']) && in_array($item['guid'], $guids)) {
        $item['status'] = $targetStatus;
        $item['read'] = ($targetStatus === 'unread') ? 0 : 1;
        $updated++;
    }
}

if (file_put_contents($userNotifPath, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(["status" => "success", "updated" => $updated]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to write notifications file"]);
}
?>
