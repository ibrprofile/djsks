<?php
/**
 * Cron скрипт: Сохраняет текущую статистику зрителей каждую минуту
 * 
 * Установка в crontab:
 * * * * * * curl -s https://yourdomain.com/cron/save-viewer-stats.php?api_key=YOUR_API_KEY > /dev/null 2>&1
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
    $db = Database::getInstance()->getConnection();
    $tracker = new ViewerTracker();

    // Получаем все активные потоки
    $stmt = $db->prepare("
        SELECT DISTINCT stream_key 
        FROM streaming_events 
        WHERE status IN ('pending', 'live') 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($streams)) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'No active streams', 'timestamp' => date('Y-m-d H:i:s')]);
        exit;
    }

    // ОПТИМИЗАЦИЯ: Batch INSERT вместо N отдельных запросов
    // Генерируем один большой INSERT ON DUPLICATE KEY UPDATE
    $values = [];
    $params = [];
    
    foreach ($streams as $stream) {
        $online = $tracker->getOnlineCount($stream['stream_key']);
        $total = $tracker->getTotalUniqueViewers($stream['stream_key']);
        
        $values[] = "(?, NOW(), ?, ?)";
        $params[] = $stream['stream_key'];
        $params[] = $online;
        $params[] = $total;
    }

    // Batch INSERT (одним запросом вместо N)
    $stmt = $db->prepare("
        INSERT INTO stream_viewer_stats 
        (stream_key, timestamp, online_count, total_unique_today)
        VALUES " . implode(',', $values) . "
        ON DUPLICATE KEY UPDATE 
        online_count = VALUES(online_count),
        total_unique_today = VALUES(total_unique_today)
    ");
    $stmt->execute($params);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Batch stats saved for " . count($streams) . " streams",
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Cron save-viewer-stats error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
