<?php
/**
 * Менеджер для управления потоковыми трансляциями
 * Обеспечивает создание, управление и защиту HLS потоков
 */

defined('APP_ACCESS') or die('Direct access not allowed');

class StreamManager {
    private $db;
    private $srs_url = 'https://sportifymn.com/srs-api';
    private $hls_base = 'https://potok.sportifymn.com/live';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Создает новое событие трансляции
     */
    public function createEvent($name, $match_id = null, $created_by = null) {
        try {
            $stream_key = $this->generateStreamKey();
            $hls_url = $this->hls_base . '/' . $stream_key . '.m3u8';

            $stmt = $this->db->prepare("
                INSERT INTO streaming_events 
                (name, stream_key, hls_url, match_id, status, created_by)
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");

            $stmt->execute([
                $name,
                $stream_key,
                $hls_url,
                $match_id ?: null,
                $created_by ?: null
            ]);

            return [
                'id' => $this->db->lastInsertId(),
                'stream_key' => $stream_key,
                'rtmp_url' => 'rtmp://potok.sportifymn.com/live',
                'hls_url' => $hls_url,
                'status' => 'pending'
            ];
        } catch (PDOException $e) {
            error_log("StreamManager::createEvent error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получить событие по ID
     */
    public function getEvent($event_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM streaming_events WHERE id = ?
        ");
        $stmt->execute([$event_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получить событие по stream_key
     */
    public function getEventByKey($stream_key) {
        $stmt = $this->db->prepare("
            SELECT * FROM streaming_events WHERE stream_key = ?
        ");
        $stmt->execute([$stream_key]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получить все события трансляции (с фильтрацией)
     */
    public function getAllEvents($filter = []) {
        $query = "SELECT * FROM streaming_events";
        $params = [];

        if (!empty($filter['status'])) {
            $query .= " AND status = ?";
            $params[] = $filter['status'];
        }

        if (!empty($filter['match_id'])) {
            $query .= " AND match_id = ?";
            $params[] = $filter['match_id'];
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Генерирует защищенный токен для доступа к HLS потоку
     * TTL: 24 часа
     */
    public function generateHlsToken($stream_key, $client_ip = null) {
        try {
            $event = $this->getEventByKey($stream_key);
            if (!$event) {
                return null;
            }

            if (!$client_ip && isset($_SERVER['REMOTE_ADDR'])) {
                $client_ip = $_SERVER['REMOTE_ADDR'];
            }

            $token = bin2hex(random_bytes(64));
            $expires = date('Y-m-d H:i:s', time() + (24 * 3600));

            $stmt = $this->db->prepare("
                INSERT INTO hls_tokens 
                (stream_key, token, client_ip, expires_at)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $stream_key,
                $token,
                $client_ip,
                $expires
            ]);

            return [
                'token' => $token,
                'expires_at' => $expires,
                'hls_url' => $event['hls_url'] . '?token=' . $token
            ];
        } catch (PDOException $e) {
            error_log("StreamManager::generateHlsToken error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Проверяет валидность токена
     */
    public function validateToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM hls_tokens 
                WHERE token = ? 
                AND expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $token_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token_record) {
                return null;
            }

            $stmt = $this->db->prepare("
                UPDATE hls_tokens SET used_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$token_record['id']]);

            return $token_record;
        } catch (PDOException $e) {
            error_log("StreamManager::validateToken error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Обновляет статус события трансляции
     */
    public function updateEventStatus($event_id, $status) {
        try {
            $valid_statuses = ['pending', 'live', 'finished'];
            if (!in_array($status, $valid_statuses)) {
                return false;
            }

            $started_at = null;
            $ended_at = null;

            if ($status === 'live') {
                $started_at = date('Y-m-d H:i:s');
            } elseif ($status === 'finished') {
                $ended_at = date('Y-m-d H:i:s');
            }

            $stmt = $this->db->prepare("
                UPDATE streaming_events 
                SET status = ?, started_at = COALESCE(started_at, ?), ended_at = ?
                WHERE id = ?
            ");

            return $stmt->execute([
                $status,
                $started_at,
                $ended_at,
                $event_id
            ]);
        } catch (PDOException $e) {
            error_log("StreamManager::updateEventStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаляет событие трансляции
     */
    public function deleteEvent($event_id) {
        try {
            $event = $this->getEvent($event_id);
            if (!$event) {
                return false;
            }

            $stmt = $this->db->prepare("
                DELETE FROM hls_tokens WHERE stream_key = ?
            ");
            $stmt->execute([$event['stream_key']]);

            $stmt = $this->db->prepare("
                DELETE FROM streaming_events WHERE id = ?
            ");

            return $stmt->execute([$event_id]);
        } catch (PDOException $e) {
            error_log("StreamManager::deleteEvent error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Генерирует уникальный stream_key
     */
    private function generateStreamKey() {
        do {
            $key = 'stream_' . bin2hex(random_bytes(12));
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM streaming_events WHERE stream_key = ?
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } while ($result['count'] > 0);

        return $key;
    }

    /**
     * Очистка истекших токенов (вызывается периодически)
     */
    public function cleanupExpiredTokens() {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM hls_tokens WHERE expires_at < NOW()
            ");
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("StreamManager::cleanupExpiredTokens error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить текущее количество активных трансляций
     */
    public function getActiveStreamsCount() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM streaming_events 
            WHERE status IN ('pending', 'live')
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }
}
