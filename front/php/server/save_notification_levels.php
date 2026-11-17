<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/php/templates/security.php';
require dirname(__FILE__).'/../server/init.php';

header('Content-Type: application/json; charset=utf-8');

$configPath = '/data/config/notification_levels.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($configPath)) {
        echo file_get_contents($configPath);
    } else {
        echo json_encode([
            "default_levels" => [
                "down_devices" => "interrupt",
                "new_devices" => "warning",
                "events" => "info"
            ],
            "device_overrides" => []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if ($decoded === null) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
        exit;
    }

    if (file_put_contents($configPath, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        // Also update user_notifications.json synchronously
        $userNotifPath = '/tmp/api/user_notifications.json';
        $tblNotifPath = '/tmp/api/table_notifications.json';
        if (file_exists($userNotifPath) && file_exists($tblNotifPath)) {
            $userItems = json_decode(file_get_contents($userNotifPath), true) ?: [];
            $tblRaw = json_decode(file_get_contents($tblNotifPath), true);
            $tblItems = isset($tblRaw['data']) ? $tblRaw['data'] : (is_array($tblRaw) ? $tblRaw : []);
            
            $reportMap = [];
            foreach ($tblItems as $row) {
                if (isset($row['guid'])) {
                    $reportMap[$row['guid']] = $row;
                }
            }

            $defs = isset($decoded['default_levels']) ? $decoded['default_levels'] : ['events' => 'info', 'new_devices' => 'warning', 'down_devices' => 'interrupt'];
            $overrides = isset($decoded['device_overrides']) ? $decoded['device_overrides'] : [];
            $weightMap = ['info' => 1, 'alert' => 2, 'warning' => 3, 'interrupt' => 4];
            $weightToLevel = [1 => 'info', 2 => 'alert', 3 => 'warning', 4 => 'interrupt'];

            foreach ($userItems as &$uItem) {
                if (isset($uItem['content']) && strpos($uItem['content'], 'Report:') !== false) {
                    $parts = explode('::', str_replace('Report:', '', $uItem['content']));
                    $guid = trim($parts[0]);
                    if (isset($reportMap[$guid])) {
                        $jd = json_decode($reportMap[$guid]['json'], true) ?: [];
                        $highestWeight = 1;

                        if (!empty($jd['down_devices'])) {
                            $baseL = isset($defs['down_devices']) ? $defs['down_devices'] : 'interrupt';
                            $highestWeight = max($highestWeight, isset($weightMap[$baseL]) ? $weightMap[$baseL] : 4);
                        }
                        if (!empty($jd['new_devices'])) {
                            $baseL = isset($defs['new_devices']) ? $defs['new_devices'] : 'warning';
                            $highestWeight = max($highestWeight, isset($weightMap[$baseL]) ? $weightMap[$baseL] : 3);
                        }
                        if (!empty($jd['events'])) {
                            $baseL = isset($defs['events']) ? $defs['events'] : 'info';
                            $highestWeight = max($highestWeight, isset($weightMap[$baseL]) ? $weightMap[$baseL] : 1);
                        }

                        $allInv = array_merge($jd['down_devices'] ?? [], $jd['new_devices'] ?? [], $jd['events'] ?? []);
                        foreach ($allInv as $inv) {
                            $mac = strtolower($inv['eveMac'] ?? '');
                            if (isset($overrides[$mac]['level'])) {
                                $ovL = $overrides[$mac]['level'];
                                $highestWeight = max($highestWeight, isset($weightMap[$ovL]) ? $weightMap[$ovL] : 1);
                            }
                        }

                        $uItem['level'] = isset($weightToLevel[$highestWeight]) ? $weightToLevel[$highestWeight] : 'info';
                    }
                }
            }

            file_put_contents($userNotifPath, json_encode($userItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        echo json_encode(["status" => "success", "message" => "Settings saved and user notifications updated"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to write to configuration file"]);
    }
    exit;
}
