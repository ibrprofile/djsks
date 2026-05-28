<?php
/**
 * Автоматическое закрытие матчей через 3.5 часа после начала
 * Запускайте этот скрипт каждые 5-10 минут через cron
 * 
 * Пример crontab:
 * */5 * * * * /usr/bin/php /path/to/your/project/cron/auto-close-matches.php
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();

try {
    // Находим все live матчи, которые идут уже более 3.5 часов
    $sql = "SELECT id, title, start_time 
            FROM matches 
            WHERE status = 'live' 
            AND start_time <= DATE_SUB(NOW(), INTERVAL 210 MINUTE)";
    
    $matches = $db->fetchAll($sql);
    
    if (empty($matches)) {
        echo "[" . date('Y-m-d H:i:s') . "] Нет матчей для закрытия\n";
        exit;
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Найдено матчей для закрытия: " . count($matches) . "\n";
    
    // Закрываем каждый матч
    foreach ($matches as $match) {
        $updateSql = "UPDATE matches SET status = 'finished', updated_at = NOW() WHERE id = ?";
        $db->update($updateSql, [$match['id']]);
        
        echo "  ✓ Закрыт матч ID {$match['id']}: {$match['title']}\n";
        
        // Логирование
        error_log("[Auto-Close] Match ID {$match['id']} closed automatically after 3.5 hours");
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Автозакрытие завершено\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("[Auto-Close Error] " . $e->getMessage());
    exit(1);
}
?>
