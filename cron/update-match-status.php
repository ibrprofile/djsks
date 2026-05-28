#!/usr/bin/env php
<?php
/**
 * Cron скрипт для автоматического обновления статусов матчей
 * Запускается каждую минуту через crontab
 * 
 * Добавьте в crontab:
 * * * * * /usr/bin/php /path/to/your/project/cron/update-match-status.php >> /path/to/logs/cron.log 2>&1
 */

// Устанавливаем правильный путь к корневой директории
$root = dirname(__DIR__);
define('APP_ACCESS', true);

require_once $root . '/config.php';
require_once $root . '/includes/Database.php';
require_once $root . '/includes/functions.php';

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
        
        echo date('[Y-m-d H:i:s]') . " Матч ID {$match['id']} ({$match['title']}) переведен в статус LIVE\n";
    }
    
    if ($updated_count > 0) {
        echo date('[Y-m-d H:i:s]') . " Всего обновлено матчей: $updated_count\n";
    } else {
        echo date('[Y-m-d H:i:s]') . " Нет матчей для обновления\n";
    }
    
    exit(0);
    
} catch (Exception $e) {
    echo date('[Y-m-d H:i:s]') . " ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}
