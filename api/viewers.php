<?php
/**
 * API для получения и обновления количества зрителей матча
 * Возвращает текущее количество зрителей с небольшими вариациями для реалистичности
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';

Security::setSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
// ОПТИМИЗАЦИЯ: Кэшируем ответ на 30 секунд для снижения нагрузки
header('Cache-Control: public, max-age=30');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 30) . ' GMT');

$db = Database::getInstance();

try {
    if (!isset($_GET['match_id'])) {
        json_error('ID матча не указан', 400);
    }
    
    $match_id = (int)$_GET['match_id'];
    
    $match = $db->fetchOne("SELECT viewers_count, status FROM matches WHERE id = ?", [$match_id]);
    
    if (!$match) {
        json_error('Матч не найден', 404);
    }
    
    $base_viewers = (int)$match['viewers_count'];
    $current_viewers = $base_viewers;
    
    if ($match['status'] === 'live') {
        // Для живых матчей - небольшие колебания (±5%)
        $variation_percent = 0.05;
        $max_variation = (int)($base_viewers * $variation_percent);
        $variation = rand(-$max_variation, $max_variation);
        $current_viewers = max(0, $base_viewers + $variation);
    } elseif ($match['status'] === 'scheduled') {
        // Для запланированных - счетчик не меняется
        $current_viewers = $base_viewers;
    } else {
        // Для завершенных - постепенное снижение
        $variation = rand(-100, -10);
        $current_viewers = max(0, $base_viewers + $variation);
    }
    
    // Обновляем реже: каждые 60 сек вместо каждый запрос
    $change_percent = abs($current_viewers - $base_viewers) / max($base_viewers, 1);
    if ($change_percent > 0.02 && rand(1, 5) === 1) {
        $db->update("UPDATE matches SET viewers_count = ? WHERE id = ?", [$current_viewers, $match_id]);
    }
    
    json_response([
        'viewers_count' => $current_viewers,
        'match_id' => $match_id,
        'status' => $match['status']
    ]);
    
} catch (Exception $e) {
    error_log("Viewers API Error: " . $e->getMessage());
    json_error('Ошибка сервера', 500);
}
?>
