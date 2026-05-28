<?php
/**
 * Класс для обеспечения безопасности приложения
 */

class Security {
    
    /**
     * Генерация CSRF токена
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Проверка CSRF токена
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Очистка входных данных
     */
    public static function sanitize($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }
        
        $data = trim($data);
        
        switch ($type) {
            case 'int':
                return filter_var($data, FILTER_VALIDATE_INT) !== false ? (int)$data : 0;
            
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT) !== false ? (float)$data : 0.0;
            
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL) !== false ? $data : '';
            
            case 'url':
                return filter_var($data, FILTER_VALIDATE_URL) !== false ? $data : '';
            
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            case 'string':
            default:
                return htmlspecialchars(strip_tags($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Валидация email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Валидация URL
     */
    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Хеширование пароля
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ALGO, ['cost' => PASSWORD_COST]);
    }
    
    /**
     * Проверка пароля
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Получение IP адреса клиента
     */
    public static function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }
    
    /**
     * Проверка попыток входа (защита от брутфорса)
     */
    public static function checkLoginAttempts($identifier) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        
        $now = time();
        $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($timestamp) use ($now) {
            return ($now - $timestamp) < LOGIN_ATTEMPT_WINDOW;
        });
        
        return count($_SESSION['login_attempts']) < MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Запись попытки входа
     */
    public static function recordLoginAttempt($identifier, $success = false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!$success) {
            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = [];
            }
            $_SESSION['login_attempts'][] = time();
        } else {
            $_SESSION['login_attempts'] = [];
        }
    }
    
    /**
     * Генерация случайного токена
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Безопасное сравнение строк
     */
    public static function timingSafeEquals($a, $b) {
        return hash_equals((string)$a, (string)$b);
    }
    
    /**
     * Защита от XSS
     */
    public static function escapeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'escapeOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Установка безопасных заголовков
     */
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        if (SESSION_SECURE) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
?>
