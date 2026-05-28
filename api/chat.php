<?php
/**
 * API чата матча
 * GET  ?action=messages&match_id=X[&after_id=Y&limit=50]  — получить сообщения
 * GET  ?action=poll&match_id=X&after_id=Y                 — long-poll новых сообщений
 * GET  ?action=check_user&user_id=X                       — проверить ник пользователя
 * POST action=send   — отправить сообщение
 * POST action=register_user — зарегистрировать ник
 * POST action=accept_rules  — принять правила
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ---- Конфигурация ----
define('CHAT_MAX_MSG_LEN',  300);
define('CHAT_RATE_PER_MIN', 20);   // максимум сообщений в минуту
define('CHAT_MSG_LIMIT',    60);   // сообщений за один запрос

// Список запрещённых паттернов (регекс)
$BANNED_PATTERNS = [
    // Нацистская символика / экстремизм
    '/\bнацист|nazi|hitler|гитлер|фюрер|зиг хайль|sieg heil|88\b|1488\b|\\bss\\b|свасти/ui',
    // Ненормативная лексика RU (базовый фильтр)
    '/\bхуй|пизд|ебан|ёбан|ёб|еб[ао]|сука|блядь|бля|мразь|ублюдок|мудак|пидор|педик|залупа/ui',
    // Матерная лексика EN
    '/\bfuck|shit|bitch|cunt|nigger|faggot|asshole/ui',
    // Спам-ссылки
    '/https?:\/\/(?!sportifymn\.com)/ui',
];

// Предупреждения — блокируем пользователя
function containsBannedContent(string $text, array &$patterns): bool {
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) return true;
    }
    return false;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$matchId = (int)($_GET['match_id'] ?? $_POST['match_id'] ?? 0);

// --- helpers ---
function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getUserId(): string {
    // Fingerprint: хэш от IP + User-Agent, уникальный но анонимный
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
       ?? $_SERVER['HTTP_X_REAL_IP']
       ?? $_SERVER['REMOTE_ADDR']
       ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Добавляем соль чтобы нельзя было обратно вычислить IP
    return 'u_' . substr(hash('sha256', $ip . '|' . $ua . '|SPORTIFY_SALT_2025'), 0, 32);
}

function isUserBanned(PDO $db, string $userId, int $matchId): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM chat_bans
        WHERE user_id = ?
          AND (match_id IS NULL OR match_id = ?)
          AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$userId, $matchId]);
    return (int)$stmt->fetchColumn() > 0;
}

function checkRateLimit(PDO $db, string $userId, int $matchId): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM chat_messages
        WHERE user_id = ? AND match_id = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
          AND is_deleted = 0
    ");
    $stmt->execute([$userId, $matchId]);
    return (int)$stmt->fetchColumn() < CHAT_RATE_PER_MIN;
}

function formatMessages(array $rows): array {
    return array_map(function($row) {
        return [
            'id'         => (int)$row['id'],
            'username'   => $row['username'],
            'message'    => $row['message'],
            'is_admin'   => (bool)$row['is_admin'],
            'is_pinned'  => (bool)$row['is_pinned'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);
}

// ========== GET-запросы ==========

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    switch ($action) {

        case 'check_user':
            $userId = getUserId();
            $stmt = $db->prepare("SELECT username, accepted_rules FROM chat_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            jsonOut([
                'user_id'        => $userId,
                'username'       => $user['username'] ?? null,
                'accepted_rules' => $user ? (bool)$user['accepted_rules'] : false,
            ]);

        case 'messages':
            if (!$matchId) jsonOut(['error' => 'match_id required'], 400);
            $afterId = (int)($_GET['after_id'] ?? 0);
            $limit   = min((int)($_GET['limit'] ?? CHAT_MSG_LIMIT), CHAT_MSG_LIMIT);

            // Закреплённые сообщения (всегда вверху)
            $pinStmt = $db->prepare("
                SELECT id, username, message, is_admin, is_pinned, created_at
                FROM chat_messages
                WHERE match_id = ? AND is_pinned = 1 AND is_deleted = 0
                ORDER BY created_at DESC LIMIT 3
            ");
            $pinStmt->execute([$matchId]);
            $pinned = formatMessages($pinStmt->fetchAll(PDO::FETCH_ASSOC));

            // Обычные сообщения
            if ($afterId > 0) {
                $stmt = $db->prepare("
                    SELECT id, username, message, is_admin, is_pinned, created_at
                    FROM chat_messages
                    WHERE match_id = ? AND is_deleted = 0 AND is_pinned = 0 AND id > ?
                    ORDER BY id ASC LIMIT ?
                ");
                $stmt->execute([$matchId, $afterId, $limit]);
            } else {
                $stmt = $db->prepare("
                    SELECT id, username, message, is_admin, is_pinned, created_at
                    FROM (
                        SELECT id, username, message, is_admin, is_pinned, created_at
                        FROM chat_messages
                        WHERE match_id = ? AND is_deleted = 0 AND is_pinned = 0
                        ORDER BY id DESC LIMIT ?
                    ) sub ORDER BY id ASC
                ");
                $stmt->execute([$matchId, $limit]);
            }
            $messages = formatMessages($stmt->fetchAll(PDO::FETCH_ASSOC));

            jsonOut([
                'pinned'   => $pinned,
                'messages' => $messages,
                'last_id'  => !empty($messages) ? end($messages)['id'] : $afterId,
            ]);

        case 'poll':
            // Long-poll: ждём новые сообщения до 25 секунд
            if (!$matchId) jsonOut(['error' => 'match_id required'], 400);
            $afterId = (int)($_GET['after_id'] ?? 0);
            $waited  = 0;
            $maxWait = 25;

            while ($waited < $maxWait) {
                $stmt = $db->prepare("
                    SELECT id, username, message, is_admin, is_pinned, created_at
                    FROM chat_messages
                    WHERE match_id = ? AND is_deleted = 0 AND id > ?
                    ORDER BY id ASC LIMIT 30
                ");
                $stmt->execute([$matchId, $afterId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    jsonOut([
                        'messages' => formatMessages($rows),
                        'last_id'  => (int)end($rows)['id'],
                    ]);
                }
                sleep(1);
                $waited++;
            }
            jsonOut(['messages' => [], 'last_id' => $afterId]);

        default:
            jsonOut(['error' => 'unknown action'], 400);
    }
}

// ========== POST-запросы ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $action ?: ($body['action'] ?? '');

    switch ($action) {

        case 'register_user':
            $userId   = getUserId();
            $username = trim($body['username'] ?? '');
            
            if (strlen($username) < 2 || strlen($username) > 32) {
                jsonOut(['error' => 'Никнейм должен быть от 2 до 32 символов'], 400);
            }
            if (!preg_match('/^[а-яёА-ЯЁa-zA-Z0-9_.\- ]+$/u', $username)) {
                jsonOut(['error' => 'Никнейм содержит недопустимые символы'], 400);
            }
            // Запрещённые ники
            if (preg_match('/^(admin|модератор|moderator|sportify|bot|system)/ui', $username)) {
                jsonOut(['error' => 'Этот никнейм зарезервирован'], 400);
            }

            try {
                // Проверяем уникальность ника (кроме текущего пользователя)
                $stmt = $db->prepare("SELECT user_id FROM chat_users WHERE username = ? AND user_id != ?");
                $stmt->execute([$username, $userId]);
                if ($stmt->fetch()) {
                    jsonOut(['error' => 'Этот никнейм уже занят'], 409);
                }

                $stmt = $db->prepare("
                    INSERT INTO chat_users (user_id, username, accepted_rules)
                    VALUES (?, ?, 0)
                    ON DUPLICATE KEY UPDATE username = VALUES(username)
                ");
                $stmt->execute([$userId, $username]);
                jsonOut(['success' => true, 'username' => $username]);
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    jsonOut(['error' => 'Этот никнейм уже занят'], 409);
                }
                error_log("chat register_user: " . $e->getMessage());
                jsonOut(['error' => 'Ошибка сервера'], 500);
            }

        case 'accept_rules':
            $userId = getUserId();
            $stmt = $db->prepare("UPDATE chat_users SET accepted_rules = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            jsonOut(['success' => true]);

        case 'send':
            global $BANNED_PATTERNS;

            $userId  = getUserId();
            $matchId = (int)($body['match_id'] ?? 0);
            $text    = trim($body['message'] ?? '');

            if (!$matchId) jsonOut(['error' => 'match_id required'], 400);
            if (strlen($text) === 0) jsonOut(['error' => 'Пустое сообщение'], 400);
            if (mb_strlen($text) > CHAT_MAX_MSG_LEN) {
                jsonOut(['error' => 'Сообщение слишком длинное (макс. ' . CHAT_MAX_MSG_LEN . ' символов)'], 400);
            }

            // Проверить: пользователь зарегистрирован и принял правила
            $stmt = $db->prepare("SELECT username, accepted_rules FROM chat_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $chatUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$chatUser) {
                jsonOut(['error' => 'Необходимо выбрать никнейм'], 401);
            }
            if (!$chatUser['accepted_rules']) {
                jsonOut(['error' => 'Необходимо принять правила чата'], 401);
            }

            // Проверить бан
            if (isUserBanned($db, $userId, $matchId)) {
                jsonOut(['error' => 'Вы заблокированы в чате'], 403);
            }

            // Rate limit
            if (!checkRateLimit($db, $userId, $matchId)) {
                jsonOut(['error' => 'Слишком много сообщений. Подождите немного.'], 429);
            }

            // Фильтрация контента
            if (containsBannedContent($text, $BANNED_PATTERNS)) {
                // Баним пользователя и уведомляем чат
                $db->prepare("
                    INSERT INTO chat_bans (user_id, match_id, reason, banned_by)
                    VALUES (?, ?, 'Автоматическая блокировка за нарушение правил', 'system')
                    ON DUPLICATE KEY UPDATE banned_at = NOW()
                ")->execute([$userId, $matchId]);

                // Системное сообщение о бане
                $banMsg = 'Пользователь ' . $chatUser['username'] . ' заблокирован за нарушение правил чата.';
                $db->prepare("
                    INSERT INTO chat_messages (match_id, user_id, username, message, is_admin)
                    VALUES (?, 'system', 'Модератор Bot', ?, 1)
                ")->execute([$matchId, $banMsg]);

                jsonOut(['error' => 'Ваше сообщение нарушает правила чата. Вы заблокированы.'], 403);
            }

            // Сохраняем сообщение
            $stmt = $db->prepare("
                INSERT INTO chat_messages (match_id, user_id, username, message, is_admin)
                VALUES (?, ?, ?, ?, 0)
            ");
            $stmt->execute([$matchId, $userId, $chatUser['username'], $text]);
            $newId = (int)$db->lastInsertId();

            jsonOut([
                'success'  => true,
                'id'       => $newId,
                'username' => $chatUser['username'],
                'message'  => $text,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

        default:
            jsonOut(['error' => 'unknown action'], 400);
    }
}

jsonOut(['error' => 'method not allowed'], 405);
