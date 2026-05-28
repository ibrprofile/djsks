<?php
/**
 * API для работы с матчами
 * Публичный API - только読取 доступ
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/RateLimit.php';

// Безопасные CORS заголовки
$allowed_origins = [
    getenv('ALLOWED_ORIGIN') ?: $_SERVER['HTTP_HOST']
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins) || in_array('*', $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// Enable gzip compression
if (ENABLE_COMPRESSION && strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
    ob_start('ob_gzhandler');
}

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

Security::setSecurityHeaders();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$client_ip = Security::getClientIP();

// Rate limiting для всех запросов
if (!RateLimit::checkLimit($client_ip, RATE_LIMIT_REQUESTS, RATE_LIMIT_WINDOW)) {
    http_response_code(429);
    json_error('Слишком много запросов. Попробуйте позже.', 429);
}

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
            
        case 'POST':
            require_admin();
            handlePost($db);
            break;
            
        case 'PUT':
            require_admin();
            handlePut($db);
            break;
            
        case 'DELETE':
            require_admin();
            handleDelete($db);
            break;
            
        default:
            json_error('Метод не поддерживается', 405);
    }
} catch (Exception $e) {
    error_log("[API] Match Error: " . $e->getMessage());
    json_error('Ошибка сервера: ' . $e->getMessage(), 500);
}

function handleGet($db) {
    // Получение одного матча
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $match = $db->fetchOne("SELECT * FROM matches WHERE id = ?", [$id]);
        
        if (!$match) {
            json_error('Матч не найден', 404);
        }
        
        json_success(['data' => $match]);
        return;
    }
    
    $status = isset($_GET['status']) && in_array($_GET['status'], ['scheduled', 'live', 'finished']) 
        ? $_GET['status'] 
        : null;
    
    $league = isset($_GET['league']) ? Security::sanitize($_GET['league']) : null;
    
    $sql = "SELECT * FROM matches WHERE 1=1";
    $params = [];
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    if ($league) {
        $sql .= " AND league = ?";
        $params[] = $league;
    }
    
    $sql .= " ORDER BY 
        CASE 
            WHEN status = 'live' THEN 0 
            WHEN status = 'scheduled' THEN 1 
            ELSE 2 
        END, 
        start_time DESC";
    
    $matches = $db->fetchAll($sql, $params);
    json_success(['data' => $matches]);
}

function handlePost($db) {
    $data = get_input_data();
    
    // Валидация
    $title = Security::sanitize($data['title'] ?? '');
    $team_a = Security::sanitize($data['team_a'] ?? '');
    $team_b = Security::sanitize($data['team_b'] ?? '');
    $start_time = $data['start_time'] ?? '';
    $hls_url = Security::sanitize($data['hls_url'] ?? '', 'url');
    $cover_image = Security::sanitize($data['cover_image'] ?? '', 'url');
    $telegram_widget = $data['telegram_widget'] ?? '';
    $status = in_array($data['status'] ?? '', ['scheduled', 'live', 'finished']) 
        ? $data['status'] 
        : 'scheduled';
    
    $league = isset($data['league']) && !empty($data['league']) 
        ? Security::sanitize($data['league']) 
        : null;
    $end_time = isset($data['end_time']) && !empty($data['end_time']) 
        ? $data['end_time'] 
        : null;
    
    if (empty($title) || empty($team_a) || empty($team_b) || empty($start_time)) {
        json_error('Заполните все обязательные поля');
    }
    
    $sql = "INSERT INTO matches 
            (title, team_a, team_b, start_time, end_time, hls_url, cover_image, telegram_widget, status, league, viewers_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
    
    $match_id = $db->insert($sql, [
        $title, $team_a, $team_b, $start_time, $end_time,
        $hls_url, $cover_image, $telegram_widget, $status, $league
    ]);
    
    log_admin_action('create_match', "Создан матч: $title");
    
    json_success(['id' => $match_id], 'Матч создан');
}

function handlePut($db) {
    $data = get_input_data();
    
    $id = (int)($data['id'] ?? 0);
    if ($id === 0) {
        json_error('ID не указан');
    }
    
    $existing = $db->fetchOne("SELECT id FROM matches WHERE id = ?", [$id]);
    if (!$existing) {
        json_error('Матч не найден', 404);
    }
    
    // Валидация
    $title = Security::sanitize($data['title'] ?? '');
    $team_a = Security::sanitize($data['team_a'] ?? '');
    $team_b = Security::sanitize($data['team_b'] ?? '');
    $start_time = $data['start_time'] ?? '';
    $hls_url = Security::sanitize($data['hls_url'] ?? '', 'url');
    $cover_image = Security::sanitize($data['cover_image'] ?? '', 'url');
    $telegram_widget = $data['telegram_widget'] ?? '';
    $status = in_array($data['status'] ?? '', ['scheduled', 'live', 'finished']) 
        ? $data['status'] 
        : 'scheduled';
    
    $league = isset($data['league']) && !empty($data['league']) 
        ? Security::sanitize($data['league']) 
        : null;
    $end_time = isset($data['end_time']) && !empty($data['end_time']) 
        ? $data['end_time'] 
        : null;
    
    if (empty($title) || empty($team_a) || empty($team_b) || empty($start_time)) {
        json_error('Заполните все обязательные поля');
    }
    
    $sql = "UPDATE matches 
            SET title = ?, team_a = ?, team_b = ?, start_time = ?, end_time = ?,
                hls_url = ?, cover_image = ?, telegram_widget = ?, 
                status = ?, league = ?, updated_at = NOW()
            WHERE id = ?";
    
    $db->update($sql, [
        $title, $team_a, $team_b, $start_time, $end_time,
        $hls_url, $cover_image, $telegram_widget, $status, $league, $id
    ]);
    
    log_admin_action('update_match', "Обновлён матч ID: $id");
    
    json_success([], 'Матч обновлён');
}

function handleDelete($db) {
    $data = get_input_data();
    
    $id = (int)($data['id'] ?? 0);
    if ($id === 0) {
        json_error('ID не указан');
    }
    
    $affected = $db->delete("DELETE FROM matches WHERE id = ?", [$id]);
    
    if ($affected === 0) {
        json_error('Матч не найден', 404);
    }
    
    log_admin_action('delete_match', "Удалён матч ID: $id");
    
    json_success([], 'Матч удалён');
}
