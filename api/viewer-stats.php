<?php
/**
 * API для статистики просмотров потоков
 * 
 * Запросы:
 * - POST: register  - Регистрация нового зрителя
 * - POST: heartbeat - Сообщить о активности
 * - POST: leave     - Зритель ушел
 * - GET:  stats     - Получить статистику потока
 * - GET:  timeline  - Получить историю для графика
 */

define('APP_ACCESS', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ViewerTracker.php';

header('Content-Type: application/json; charset=utf-8');

// ОПТИМИЗАЦИЯ: Кэшируем GET запросы для снижения нагрузки
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Cache 10 сек для статистики (за это время изменения не критичны)
    header('Cache-Control: public, max-age=10');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 10) . ' GMT');
}

$tracker = new ViewerTracker();
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$stream_key = $_GET['stream_key'] ?? $_POST['stream_key'] ?? null;

if ($action === 'all_stats') {
    try {
        $stats = $tracker->getActivestreamsStats();
        echo json_encode($stats);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        error_log("All stats error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to load stats']);
        exit;
    }
}

if (!$stream_key) {
    http_response_code(400);
    echo json_encode(['error' => 'stream_key is required']);
    exit;
}

try {
    switch ($action) {
        case 'register':
            // Регистрируем нового зрителя
            $viewer_uuid = $_POST['viewer_uuid'] ?? null;
            if (!$viewer_uuid) {
                http_response_code(400);
                echo json_encode(['error' => 'viewer_uuid is required']);
                exit;
            }

            $result = $tracker->registerViewer($stream_key, $viewer_uuid);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Viewer registered' : 'Failed to register viewer'
            ]);
            break;

        case 'heartbeat':
            // Обновляем последнюю активность
            $viewer_uuid = $_POST['viewer_uuid'] ?? null;
            if (!$viewer_uuid) {
                http_response_code(400);
                echo json_encode(['error' => 'viewer_uuid is required']);
                exit;
            }

            $result = $tracker->updateHeartbeat($stream_key, $viewer_uuid);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Heartbeat updated' : 'Failed to update heartbeat'
            ]);
            break;

        case 'leave':
            // Зритель уходит
            $viewer_uuid = $_POST['viewer_uuid'] ?? null;
            if (!$viewer_uuid) {
                http_response_code(400);
                echo json_encode(['error' => 'viewer_uuid is required']);
                exit;
            }

            $result = $tracker->markViewerLeft($stream_key, $viewer_uuid);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Viewer marked as left' : 'Failed to mark viewer as left'
            ]);
            break;

        case 'stats':
            // Получаем текущую статистику потока
            $online = $tracker->getOnlineCount($stream_key);
            $total = $tracker->getTotalUniqueViewers($stream_key);
            $peak = $tracker->getPeakOnlineCount($stream_key);
            $avg_duration = $tracker->getAverageDuration($stream_key);

            echo json_encode([
                'stream_key' => $stream_key,
                'online_now' => $online,
                'total_unique' => $total,
                'peak_online' => $peak,
                'avg_duration_seconds' => $avg_duration,
                'avg_duration_formatted' => ViewerTracker::formatDuration($avg_duration),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'timeline':
            // Получаем историю для графика
            $minutes = (int)($_GET['minutes'] ?? 120);
            $timeline = $tracker->getViewerStatsTimeline($stream_key, $minutes);

            echo json_encode([
                'stream_key' => $stream_key,
                'period_minutes' => $minutes,
                'data' => $timeline,
                'point_count' => count($timeline)
            ]);
            break;

        case 'regions':
            // Распределение зрителей по регионам
            $regions = $tracker->getRegionDistribution($stream_key);
            echo json_encode([
                'success' => true,
                'data' => $regions,
                'stream_key' => $stream_key
            ]);
            break;

        case 'save-stats':
            // Сохраняем текущую статистику (вызывается из cron)
            if (!isset($_POST['api_key']) || $_POST['api_key'] !== API_KEY) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid API key']);
                exit;
            }

            $result = $tracker->saveCurrentStats($stream_key);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Stats saved' : 'Failed to save stats'
            ]);
            break;

        case 'cleanup':
            // Чистим старые данные (вызывается из cron)
            if (!isset($_POST['api_key']) || $_POST['api_key'] !== API_KEY) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid API key']);
                exit;
            }

            $days = (int)($_POST['days'] ?? 30);
            $result = $tracker->cleanupOldData($days);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Cleanup completed' : 'Failed to cleanup'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Stream stats API error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}
