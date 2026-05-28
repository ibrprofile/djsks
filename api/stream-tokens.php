<?php
/**
 * API для валидации токенов HLS потоков
 * Обеспечивает защиту от несанкционированного доступа к потокам
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/StreamManager.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$stream_key = isset($_GET['stream_key']) ? trim($_GET['stream_key']) : '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action parameter']);
    exit;
}

$stream_manager = new StreamManager();

switch ($action) {
    case 'validate_token':
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing token parameter']);
            exit;
        }

        $token_record = $stream_manager->validateToken($token);
        
        if ($token_record) {
            echo json_encode([
                'valid' => true,
                'stream_key' => $token_record['stream_key'],
                'expires_at' => $token_record['expires_at']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
        }
        break;

    case 'create_token':
        if (empty($stream_key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing stream_key parameter']);
            exit;
        }

        $token_data = $stream_manager->generateHlsToken($stream_key);
        
        if ($token_data) {
            echo json_encode([
                'token' => $token_data['token'],
                'expires_at' => $token_data['expires_at'],
                'hls_url' => $token_data['hls_url']
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Stream not found']);
        }
        break;

    case 'get_event_status':
        if (empty($stream_key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing stream_key parameter']);
            exit;
        }

        $event = $stream_manager->getEventByKey($stream_key);
        
        if ($event) {
            echo json_encode([
                'id' => $event['id'],
                'name' => $event['name'],
                'status' => $event['status'],
                'match_id' => $event['match_id'],
                'started_at' => $event['started_at'],
                'ended_at' => $event['ended_at']
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Stream not found']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        break;
}
