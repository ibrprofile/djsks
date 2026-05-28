<?php
/**
 * API для управления статусом сайта
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';

Security::setSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// GET - получить текущий статус
if ($method === 'GET') {
    try {
        try {
            $status = $db->fetchOne("SELECT * FROM site_status WHERE id = 1");
        } catch (Exception $e) {
            // Если таблицы нет, создаем её
            $db->query("CREATE TABLE IF NOT EXISTS `site_status` (
              `id` int unsigned NOT NULL,
              `is_closed` tinyint(1) NOT NULL DEFAULT '0',
              `close_message` varchar(255) DEFAULT NULL,
              `updated_by` int unsigned DEFAULT NULL,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $status = false;
        }
        
        if (!$status) {
            // Создаем запись, если её нет
            $db->query("INSERT INTO site_status (id, is_closed) VALUES (1, 0) ON DUPLICATE KEY UPDATE id = 1");
            $status = ['id' => 1, 'is_closed' => 0, 'close_message' => 'Сайт временно недоступен'];
        }
        
        json_success(['status' => $status]);
    } catch (Exception $e) {
        json_error($e->getMessage(), 500);
    }
}

// POST/PUT - обновить статус (только для админов)
if ($method === 'POST' || $method === 'PUT') {
    require_admin();
    
    try {
        $input = get_input_data();
        
        $is_closed = isset($input['is_closed']) ? (int)$input['is_closed'] : null;
        $close_message = isset($input['close_message']) ? trim($input['close_message']) : null;
        
        if ($is_closed === null) {
            json_error('Параметр is_closed обязателен', 400);
        }
        
        $admin_id = get_admin_id();
        
        $sql = "UPDATE site_status SET is_closed = ?, updated_by = ?";
        $params = [$is_closed, $admin_id];
        
        if ($close_message !== null) {
            $sql .= ", close_message = ?";
            $params[] = $close_message;
        }
        
        $sql .= " WHERE id = 1";
        
        $db->query($sql, $params);
        
        $action = $is_closed ? 'Сайт закрыт' : 'Сайт открыт';
        log_admin_action('site_status', $action, $admin_id);
        
        json_success([], $action);
    } catch (Exception $e) {
        json_error($e->getMessage(), 500);
    }
}

json_error('Method not allowed', 405);
