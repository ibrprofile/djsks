<?php
/**
 * API для получения актуального статуса матча
 * Возвращает JSON с информацией о текущем статусе, статусе трансляции и времени
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
// ОПТИМИЗАЦИЯ: Кэшируем ответ на 15 секунд для снижения нагрузки
header('Cache-Control: public, max-age=15');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 15) . ' GMT');

$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$matchId) {
    http_response_code(400);
    echo json_encode(['error' => 'Match ID required']);
    exit;
}

try {
    // Получаем матч
    $stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match) {
        http_response_code(404);
        echo json_encode(['error' => 'Match not found']);
        exit;
    }
    
    // Получаем связанную трансляцию (если есть)
    $stream_event = null;
    $stmt = $db->prepare("
        SELECT stream_key, hls_url, status 
        FROM streaming_events 
        WHERE match_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$matchId]);
    $stream_event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $now = time();
    $startTs = strtotime($match['start_time']);
    $closeTs = strtotime($match['auto_close_time']);
    $broadcastStartTs = !empty($match['broadcast_start_time']) 
        ? strtotime($match['broadcast_start_time']) 
        : $startTs;
    
    $isFinished = $now >= $closeTs;
    $isLive = $now >= $startTs && !$isFinished;
    $isUpcoming = $now < $startTs;
    $broadcastReady = $now >= $broadcastStartTs;
    $matchStarted = $now >= $startTs;
    
    $status = get_match_status($match);
    
    echo json_encode([
        'id' => $match['id'],
        'title' => e($match['title']),
        'status_type' => $status['type'],
        'status_label' => $status['label'],
        'is_live' => $isLive,
        'is_finished' => $isFinished,
        'is_upcoming' => $isUpcoming,
        'broadcast_ready' => $broadcastReady,
        'match_started' => $matchStarted,
        'start_ts' => $startTs,
        'close_ts' => $closeTs,
        'broadcast_ts' => $broadcastStartTs,
        'current_ts' => $now,
        'has_iframe' => !empty($match['iframe_code']),
        'iframe_code' => !empty($match['iframe_code']) ? $match['iframe_code'] : null,
        'stream_event' => $stream_event  // Добавили информацию о трансляции
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log("Match status API error: " . $e->getMessage());
}
?>
