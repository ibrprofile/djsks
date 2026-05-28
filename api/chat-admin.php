<?php
/**
 * Административное API чата
 * POST action=ban_user      — заблокировать пользователя
 * POST action=unban_user    — разблокировать
 * POST action=delete_msg    — удалить сообщение
 * POST action=pin_msg       — закрепить / открепить сообщение
 * POST action=send_admin    — отправить сообщение от имени администратора
 * GET  action=messages      — получить сообщения (с удалёнными, для модерации)
 * GET  action=bans          — список банов
 */

session_start();
define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

require_admin();

$db = Database::getInstance()->getConnection();

function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$matchId = (int)($_GET['match_id'] ?? $_POST['match_id'] ?? 0);

// ---- GET ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {

        case 'messages':
            if (!$matchId) jsonOut(['error' => 'match_id required'], 400);
            $limit  = min((int)($_GET['limit'] ?? 100), 200);
            $offset = (int)($_GET['offset'] ?? 0);
            $stmt = $db->prepare("
                SELECT id, user_id, username, message, is_admin, is_pinned, is_deleted, created_at
                FROM chat_messages
                WHERE match_id = ?
                ORDER BY id DESC LIMIT ? OFFSET ?
            ");
            $stmt->execute([$matchId, $limit, $offset]);
            jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));

        case 'bans':
            $stmt = $db->prepare("
                SELECT b.*, u.username
                FROM chat_bans b
                LEFT JOIN chat_users u ON u.user_id = b.user_id
                WHERE (b.expires_at IS NULL OR b.expires_at > NOW())
                ORDER BY b.banned_at DESC
                LIMIT 200
            ");
            $stmt->execute();
            jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));

        default:
            jsonOut(['error' => 'unknown action'], 400);
    }
}

// ---- POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $body['action'] ?? $action;

    switch ($action) {

        case 'ban_user':
            $userId  = $body['user_id'] ?? '';
            $mid     = (int)($body['match_id'] ?? 0);
            $reason  = trim($body['reason'] ?? 'Нарушение правил');
            $global  = !empty($body['global']);

            if (!$userId) jsonOut(['error' => 'user_id required'], 400);

            $db->prepare("
                INSERT INTO chat_bans (user_id, match_id, reason, banned_by)
                VALUES (?, ?, ?, 'admin')
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), banned_at = NOW(), expires_at = NULL
            ")->execute([$userId, $global ? null : ($mid ?: null), $reason]);

            // Системное сообщение в чат
            if ($mid) {
                $stmt = $db->prepare("SELECT username FROM chat_users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $uname = $stmt->fetchColumn() ?: 'пользователь';

                $db->prepare("
                    INSERT INTO chat_messages (match_id, user_id, username, message, is_admin)
                    VALUES (?, 'system', 'Модератор Bot', ?, 1)
                ")->execute([$mid, 'Пользователь ' . $uname . ' заблокирован. Причина: ' . $reason]);
            }
            jsonOut(['success' => true]);

        case 'unban_user':
            $userId = $body['user_id'] ?? '';
            if (!$userId) jsonOut(['error' => 'user_id required'], 400);
            $db->prepare("DELETE FROM chat_bans WHERE user_id = ?")->execute([$userId]);
            jsonOut(['success' => true]);

        case 'delete_msg':
            $msgId = (int)($body['msg_id'] ?? 0);
            if (!$msgId) jsonOut(['error' => 'msg_id required'], 400);
            $db->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE id = ?")->execute([$msgId]);
            jsonOut(['success' => true]);

        case 'pin_msg':
            $msgId  = (int)($body['msg_id'] ?? 0);
            $pinned = !empty($body['pinned']) ? 1 : 0;
            if (!$msgId) jsonOut(['error' => 'msg_id required'], 400);
            // Сначала убираем закрепление с других
            if ($pinned && $matchId) {
                $db->prepare("UPDATE chat_messages SET is_pinned = 0 WHERE match_id = ?")->execute([$matchId]);
            }
            $db->prepare("UPDATE chat_messages SET is_pinned = ? WHERE id = ?")->execute([$pinned, $msgId]);
            jsonOut(['success' => true]);

        case 'send_admin':
            $mid     = (int)($body['match_id'] ?? 0);
            $text    = trim($body['message'] ?? '');
            $adminName = trim($body['admin_name'] ?? 'Администратор');
            if (!$mid || !$text) jsonOut(['error' => 'match_id и message обязательны'], 400);
            if (mb_strlen($text) > 500) jsonOut(['error' => 'Слишком длинное сообщение'], 400);

            $db->prepare("
                INSERT INTO chat_messages (match_id, user_id, username, message, is_admin)
                VALUES (?, 'admin', ?, ?, 1)
            ")->execute([$mid, $adminName, $text]);
            jsonOut(['success' => true, 'id' => (int)$db->lastInsertId()]);

        default:
            jsonOut(['error' => 'unknown action'], 400);
    }
}

jsonOut(['error' => 'method not allowed'], 405);
