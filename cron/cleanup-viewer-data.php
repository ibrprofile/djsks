<?php
/**
 * Cron скрипт: Очища старые данные просмотров (старше 30 дней)
 * 
 * Установка в crontab:
 * 0 2 * * * curl -s https://yourdomain.com/cron/cleanup-viewer-data.php?api_key=YOUR_API_KEY > /dev/null 2>&1
 * (Запускать в 2:00 ночи каждый день)
 */

define('APP_ACCESS', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ViewerTracker.php';

// Проверяем API key
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? null;
if (!$api_key || $api_key !== API_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

try {
    $tracker = new ViewerTracker();
    $days = (int)($_GET['days'] ?? 30);

    $result = $tracker->cleanupOldData($days);

    http_response_code(200);
    echo json_encode([
        'success' => $result,
        'message' => "Cleanup completed for data older than $days days",
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Cron cleanup-viewer-data error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
