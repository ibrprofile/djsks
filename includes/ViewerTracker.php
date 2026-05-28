<?php
/**
 * Менеджер статистики просмотров потоков
 * Отслеживание уникальных зрителей, онлайна в реальном времени и истории
 */

defined('APP_ACCESS') or die('Direct access not allowed');

class ViewerTracker {
    private $db;
    private $heartbeat_timeout = 120; // 2 минуты
    private $stats_interval = 60;     // Сохранять статистику каждую минуту

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Регистрирует начало просмотра потока
     * ОПТИМИЗАЦИЯ: используем ON DUPLICATE KEY UPDATE вместо 2 запросов
     */
    public function registerViewer($stream_key, $viewer_uuid, $ip_address = null, $user_agent = null) {
        try {
            if (!$ip_address && isset($_SERVER['REMOTE_ADDR'])) {
                $ip_address = $this->sanitizeIp($_SERVER['REMOTE_ADDR']);
            }
            if (!$user_agent && isset($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = substr($_SERVER['HTTP_USER_AGENT'], 0, 500);
            }

            // ОПТИМИЗАЦИЯ: ON DUPLICATE KEY UPDATE вместо SELECT + UPDATE/INSERT (1 запрос вместо 2)
            $stmt = $this->db->prepare("
                INSERT INTO stream_viewers 
                (stream_key, viewer_uuid, ip_address, user_agent, started_at, last_activity)
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    last_activity = NOW()
            ");

            return $stmt->execute([$stream_key, $viewer_uuid, $ip_address, $user_agent]);
        } catch (PDOException $e) {
            error_log("ViewerTracker::registerViewer error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновляет time последней активности (heartbeat)
     */
    public function updateHeartbeat($stream_key, $viewer_uuid) {
        try {
            $stmt = $this->db->prepare("
                UPDATE stream_viewers 
                SET last_activity = NOW()
                WHERE stream_key = ? AND viewer_uuid = ? AND left_at IS NULL
            ");
            return $stmt->execute([$stream_key, $viewer_uuid]);
        } catch (PDOException $e) {
            error_log("ViewerTracker::updateHeartbeat error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Помечает зрителя как отключился
     */
    public function markViewerLeft($stream_key, $viewer_uuid) {
        try {
            $stmt = $this->db->prepare("
                UPDATE stream_viewers 
                SET left_at = NOW(), 
                    duration_seconds = UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(started_at)
                WHERE stream_key = ? AND viewer_uuid = ? AND left_at IS NULL
            ");
            return $stmt->execute([$stream_key, $viewer_uuid]);
        } catch (PDOException $e) {
            error_log("ViewerTracker::markViewerLeft error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получает количество онлайн зрителей СЕЙЧАС
     */
    public function getOnlineCount($stream_key) {
        try {
            $timeout = $this->heartbeat_timeout;
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT viewer_uuid) as count 
                FROM stream_viewers 
                WHERE stream_key = ? 
                AND left_at IS NULL 
                AND last_activity > DATE_SUB(NOW(), INTERVAL $timeout SECOND)
            ");
            $stmt->execute([$stream_key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (PDOException $e) {
            error_log("ViewerTracker::getOnlineCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получает количество уникальных зрителей всего
     */
    public function getTotalUniqueViewers($stream_key) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT viewer_uuid) as count 
                FROM stream_viewers 
                WHERE stream_key = ?
            ");
            $stmt->execute([$stream_key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (PDOException $e) {
            error_log("ViewerTracker::getTotalUniqueViewers error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получает пиковое количество онлайна
     */
    public function getPeakOnlineCount($stream_key) {
        try {
            $stmt = $this->db->prepare("
                SELECT MAX(online_count) as peak 
                FROM stream_viewer_stats 
                WHERE stream_key = ?
            ");
            $stmt->execute([$stream_key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['peak'] ?? 0);
        } catch (PDOException $e) {
            error_log("ViewerTracker::getPeakOnlineCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получает среднее время просмотра
     */
    public function getAverageDuration($stream_key) {
        try {
            $stmt = $this->db->prepare("
                SELECT AVG(duration_seconds) as avg_duration 
                FROM stream_viewers 
                WHERE stream_key = ? AND duration_seconds > 0
            ");
            $stmt->execute([$stream_key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['avg_duration'] ?? 0);
        } catch (PDOException $e) {
            error_log("ViewerTracker::getAverageDuration error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получает статистику онлайна за временной период (для графика)
     * Возвращает массив: [{recorded_at, viewers_count}, ...]
     */
    public function getViewerStatsTimeline($stream_key, $minutes_back = 120) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    timestamp AS recorded_at,
                    online_count AS viewers_count
                FROM stream_viewer_stats 
                WHERE stream_key = ? 
                AND timestamp > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY timestamp ASC
            ");
            $stmt->execute([$stream_key, $minutes_back]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ViewerTracker::getViewerStatsTimeline error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Сохраняет текущую статистику (вызывается периодически через cron/API)
     */
    public function saveCurrentStats($stream_key) {
        try {
            $online = $this->getOnlineCount($stream_key);
            $total = $this->getTotalUniqueViewers($stream_key);

            $stmt = $this->db->prepare("
                INSERT INTO stream_viewer_stats 
                (stream_key, timestamp, online_count, total_unique_today)
                VALUES (?, NOW(), ?, ?)
                ON DUPLICATE KEY UPDATE 
                online_count = VALUES(online_count),
                total_unique_today = VALUES(total_unique_today)
            ");

            return $stmt->execute([$stream_key, $online, $total]);
        } catch (PDOException $e) {
            error_log("ViewerTracker::saveCurrentStats error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Очистка старых данных (зрители, которые давно ушли)
     */
    public function cleanupOldData($days = 30) {
        try {
            // Удаляем зрителей старше N дней
            $stmt = $this->db->prepare("
                DELETE FROM stream_viewers 
                WHERE left_at IS NOT NULL 
                AND left_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);

            // Удаляем статистику старше N дней
            $stmt = $this->db->prepare("
                DELETE FROM stream_viewer_stats 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);

            return true;
        } catch (PDOException $e) {
            error_log("ViewerTracker::cleanupOldData error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получает все активные потоки с их статистикой (оптимизированный запрос)
     * Использует LEFT JOIN вместо коррелированных подзапросов для производительности
     */
    public function getActivestreamsStats() {
        try {
            $timeout = $this->heartbeat_timeout;
            $stmt = $this->db->prepare("
                SELECT 
                    se.stream_key,
                    se.name,
                    se.match_id,
                    se.status,
                    COALESCE(t1.online_now, 0) AS online_now,
                    COALESCE(t2.total_unique, 0) AS total_unique,
                    COALESCE(t3.peak_online, 0) AS peak_online,
                    COALESCE(t4.avg_duration, 0) AS avg_duration
                FROM streaming_events se
                LEFT JOIN (
                    SELECT stream_key, COUNT(DISTINCT viewer_uuid) as online_now
                    FROM stream_viewers
                    WHERE left_at IS NULL
                      AND last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND)
                    GROUP BY stream_key
                ) t1 ON se.stream_key = t1.stream_key
                LEFT JOIN (
                    SELECT stream_key, COUNT(DISTINCT viewer_uuid) as total_unique
                    FROM stream_viewers
                    GROUP BY stream_key
                ) t2 ON se.stream_key = t2.stream_key
                LEFT JOIN (
                    SELECT stream_key, MAX(online_count) as peak_online
                    FROM stream_viewer_stats
                    GROUP BY stream_key
                ) t3 ON se.stream_key = t3.stream_key
                LEFT JOIN (
                    SELECT stream_key, AVG(duration_seconds) as avg_duration
                    FROM stream_viewers
                    WHERE duration_seconds > 0
                    GROUP BY stream_key
                ) t4 ON se.stream_key = t4.stream_key
                ORDER BY se.created_at DESC
            ");
            $stmt->execute([$timeout]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['avg_duration_formatted'] = self::formatDuration((int)($r['avg_duration'] ?? 0));
            }
            return $rows;
        } catch (PDOException $e) {
            error_log("ViewerTracker::getActivestreamsStats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Подсчитывает активных зрителей из таблицы и обновляет streaming_events
     */
    public function updateStreamViewerCount($stream_key) {
        try {
            $online = $this->getOnlineCount($stream_key);
            $stmt = $this->db->prepare("
                UPDATE streaming_events 
                SET viewer_count = ? 
                WHERE stream_key = ?
            ");
            return $stmt->execute([$online, $stream_key]);
        } catch (PDOException $e) {
            error_log("ViewerTracker::updateStreamViewerCount error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Форматирует время в читаемый вид
     */
    public static function formatDuration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf("%dh %dm", $hours, $minutes);
        } elseif ($minutes > 0) {
            return sprintf("%dm %ds", $minutes, $secs);
        } else {
            return sprintf("%ds", $secs);
        }
    }

    /**
     * Очищает и валидирует IP адрес
     */
    private function sanitizeIp($ip) {
        // Если за прокси, берем реальный IP
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Базовая валидация
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        return null;
    }

    /**
     * Получает страну по IP адресу (используя FirstOctet -> простой метод)
     * Если есть Cloudflare, будет использовать CF_IPCOUNTRY
     */
    private function getCountryFromIP($ip_address) {
        if (empty($ip_address) || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
            return 'Unknown';
        }

        // Проверяем локальные IP
        if (in_array($ip_address, ['127.0.0.1', '0.0.0.0', 'localhost', '::1'])) {
            return 'Local';
        }

        // Используем простое отображение первого октета на регионы (не идеально, но быстро)
        // Это временное решение до использования GeoIP2
        $first_octet = (int)explode('.', $ip_address)[0];
        
        // Приблизительные диапазоны (для демонстрации)
        $regions = [
            '1-25' => 'USA',
            '26-50' => 'Europe',
            '51-75' => 'Europe',
            '76-100' => 'Europe/Russia',
            '101-125' => 'Asia',
            '126-150' => 'Asia',
            '151-175' => 'Australia/Asia',
            '176-200' => 'Europe',
            '201-223' => 'World',
        ];

        foreach ($regions as $range => $region) {
            list($start, $end) = explode('-', $range);
            if ($first_octet >= (int)$start && $first_octet <= (int)$end) {
                return $region;
            }
        }

        return 'Unknown';
    }

    /**
     * Получает распределение зрителей по регионам
     * Возвращает массив [{country: 'Country', viewers: count}, ...]
     */
    public function getRegionDistribution($stream_key) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ip_address,
                    COUNT(DISTINCT viewer_uuid) as viewer_count
                FROM stream_viewers
                WHERE stream_key = ?
                  AND started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY ip_address
                ORDER BY viewer_count DESC
                LIMIT 1000
            ");
            $stmt->execute([$stream_key]);
            $ips = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Группируем по странам
            $regions = [];
            foreach ($ips as $row) {
                $country = $this->getCountryFromIP($row['ip_address']);
                
                if (!isset($regions[$country])) {
                    $regions[$country] = 0;
                }
                $regions[$country] += (int)$row['viewer_count'];
            }

            // Сортируем по количеству
            arsort($regions);
            
            // Форматируем ответ
            $result = [];
            $count = 0;
            foreach ($regions as $country => $viewers) {
                if ($count >= 20) break; // Максимум 20 регионов
                $result[] = [
                    'country' => $country,
                    'viewers' => $viewers
                ];
                $count++;
            }

            return $result;
        } catch (PDOException $e) {
            error_log("ViewerTracker::getRegionDistribution error: " . $e->getMessage());
            return [];
        }
    }
}
