<?php
/**
 * Класс для кэширования результатов
 * ОПТИМИЗАЦИЯ: Гибридный кэш (память + файлы) для максимальной производительности
 */

class Cache {
    private static $cache_dir = __DIR__ . '/../cache';
    private static $memory_cache = [];  // In-memory cache для текущей сессии
    
    public static function get($key) {
        // Сначала проверяем память (instant access)
        if (isset(self::$memory_cache[$key])) {
            if (self::$memory_cache[$key]['expires'] === 0 || self::$memory_cache[$key]['expires'] > time()) {
                return self::$memory_cache[$key]['value'];
            } else {
                unset(self::$memory_cache[$key]);
            }
        }
        
        // Потом файловый кэш
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if ($data['expires'] && $data['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        // Кэшируем в памяти для последующих обращений
        self::$memory_cache[$key] = $data;
        
        return $data['value'] ?? null;
    }
    
    public static function set($key, $value, $ttl = 3600) {
        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created_at' => time()
        ];
        
        // Сохраняем в памяти
        self::$memory_cache[$key] = $data;
        
        // Сохраняем в файл (асинхронно, если возможно)
        self::ensureCacheDir();
        $file = self::getCacheFile($key);
        
        // ОПТИМИЗАЦИЯ: используем fopen+fwrite с блокировкой только при записи
        $fp = fopen($file, 'w');
        if ($fp) {
            flock($fp, LOCK_EX | LOCK_NB);  // Non-blocking lock
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
    
    public static function delete($key) {
        // Удаляем из памяти
        unset(self::$memory_cache[$key]);
        
        // Удаляем файл
        $file = self::getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public static function flush() {
        // Очищаем память
        self::$memory_cache = [];
        
        // Очищаем файлы
        $files = glob(self::$cache_dir . '/*.json');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    
    private static function getCacheFile($key) {
        return self::$cache_dir . '/' . md5($key) . '.json';
    }
    
    private static function ensureCacheDir() {
        if (!is_dir(self::$cache_dir)) {
            @mkdir(self::$cache_dir, 0755, true);
        }
    }
}
