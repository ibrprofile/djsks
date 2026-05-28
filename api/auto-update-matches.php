<?php
/**
 * API для автоматического обновления статуса матчей
 * Вызывается по крону или вручную для обновления статусов
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance();
    
    // Находим все матчи со статусом 'scheduled', у которых время начала уже наступило
    $sql = "SELECT id, title, start_time, status 
            FROM matches 
            WHERE status = 'scheduled' 
            AND start_time <= NOW()";
    
    $matches_to_update = $db->fetchAll($sql);
    
    $updated_count = 0;
    
    foreach ($matches_to_update as $match) {
        // Обновляем статус на 'live'
        $update_sql = "UPDATE matches SET status = 'live', updated_at = NOW() WHERE id = ?";
        $db->update($update_sql, [$match['id']]);
        $updated_count++;
        
        error_log("[Auto-Update] Матч ID {$match['id']} ({$match['title']}) переведен в статус LIVE");
    }
    
    json_success([
        'updated' => $updated_count,
        'message' => "Обновлено матчей: $updated_count"
    ]);
    
} catch (Exception $e) {
    error_log("[Auto-Update] Ошибка: " . $e->getMessage());
    json_error('Ошибка обновления статусов', 500);
}
