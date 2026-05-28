<?php
/**
 * Класс для управления рейт-лимитами
 * ОПТИМИЗАЦИЯ: используем in-memory storage вместо файлов
 * Это избегает 100+ file writes/sec при высокой нагрузке
 */

class RateLimit {
    private static $memory_storage = [];
    private static $last_cleanup = 0;
    
    public static function checkLimit($key, $max_requests = 100, $window_seconds = 60) {
        if (!defined('RATE_LIMIT_ENABLED') || !RATE_LIMIT_ENABLED) {
            return true;
        }
        
        $now = time();
        $hash_key = md5($key);
        
        // Периодическая очистка старых данных (раз в минуту)
        if ($now - self::$last_cleanup > 60) {
            self::cleanup($window_seconds);
            self::$last_cleanup = $now;
        }
        
        // Инициализация ключа если не существует
        if (!isset(self::$memory_storage[$hash_key])) {
            self::$memory_storage[$hash_key] = [];
        }
        
        // Отфильтровываем старые запросы
        $requests = array_filter(self::$memory_storage[$hash_key], function($ts) use ($now, $window_seconds) {
            return ($now - $ts) < $window_seconds;
        });
        
        if (count($requests) >= $max_requests) {
            return false;
        }
        
        $requests[] = $now;
        self::$memory_storage[$hash_key] = $requests;
        
        return true;
    }
    
    public static function getRemainingRequests($key, $max_requests = 100, $window_seconds = 60) {
        if (!defined('RATE_LIMIT_ENABLED') || !RATE_LIMIT_ENABLED) {
            return $max_requests;
        }
        
        $now = time();
        $hash_key = md5($key);
        
        if (!isset(self::$memory_storage[$hash_key])) {
            return $max_requests;
        }
        
        $requests = array_filter(self::$memory_storage[$hash_key], function($ts) use ($now, $window_seconds) {
            return ($now - $ts) < $window_seconds;
        });
        
        return max(0, $max_requests - count($requests));
    }
    
    private static function cleanup($window_seconds) {
        $now = time();
        foreach (self::$memory_storage as $key => $timestamps) {
            self::$memory_storage[$key] = array_filter($timestamps, function($ts) use ($now, $window_seconds) {
                return ($now - $ts) < $window_seconds;
            });
            if (empty(self::$memory_storage[$key])) {
                unset(self::$memory_storage[$key]);
            }
        }
    }
}
