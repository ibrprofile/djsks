<?php
/**
 * API для автоматического закрытия завершённых матчей
 * Вызывается cron-задачей каждые 5 минут
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Проверка токена для защиты от несанкционированного доступа
$cron_token = $_GET['token'] ?? '';
if ($cron_token !== CRON_TOKEN) {
    json_error('Unauthorized', 401);
}

try {
    $db = Database::getInstance();
    
    // Находим все матчи, у которых end_time прошло и статус не finished
    $sql = "UPDATE matches 
            SET status = 'finished', updated_at = NOW() 
            WHERE end_time IS NOT NULL 
            AND end_time <= NOW() 
            AND status != 'finished'";
    
    $affected = $db->update($sql);
    
    if ($affected > 0) {
        error_log("[Cron] Закрыто матчей: $affected");
    }
    
    json_success([
        'closed_matches' => $affected,
        'timestamp' => date('Y-m-d H:i:s')
    ], "Обработано матчей: $affected");
    
} catch (Exception $e) {
    error_log("[Cron] Error: " . $e->getMessage());
    json_error('Ошибка сервера', 500);
}
