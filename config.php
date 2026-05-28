

<?php
// Защита от прямого доступа
defined('APP_ACCESS') or define('APP_ACCESS', true);

define('API_KEY', 'dhsjsoksncnswowefn2dn');

// Настройки БД
define('DB_HOST', getenv('DATABASE_HOST') ?: 'localhost');
define('DB_PORT', getenv('DATABASE_PORT') ?: '3306');
define('DB_NAME', getenv('DATABASE_NAME') ?: 'sportify');
define('DB_USER', getenv('DATABASE_USER') ?: 'sportify');
define('DB_PASS', getenv('DATABASE_PASSWORD') ?: 'sportify');
define('DB_CHARSET', 'utf8mb4');

// Настройки сессии
define('SESSION_LIFETIME', 86400); // 24 часа
define('ADMIN_SESSION_NAME', 'sportify_admin_sid');
define('SESSION_SECURE', false); // Установить true для HTTPS

// Настройки безопасности
define('PASSWORD_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_COST', 12);
define('CSRF_TOKEN_LENGTH', 32);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 минут

// Временная зона
date_default_timezone_set('Europe/Moscow');

// Отображение ошибок (отключить на продакшене)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Рейт лимит для API
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // Запросов
define('RATE_LIMIT_WINDOW', 60); // За 60 секund

// Отправка ответов с компрессией
define('ENABLE_COMPRESSION', true);

// Контактный email
define('CONTACT_EMAIL', 'sportify-tv@proton.me');

// Базовый URL
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
define('SITE_NAME', 'Sportify TV | Смотреть футбол онлайн бесплатно');
define('SITE_DESCRIPTION', 'Смотрите прямые трансляции футбольных матчей онлайн бесплатно в HD качестве');

// Лимиты
define('MATCHES_PER_PAGE', 50);
define('CHAT_MESSAGES_LIMIT', 100);
define('MAX_MESSAGE_LENGTH', 1000);

define('AVAILABLE_LEAGUES', [
    'АПЛ',
    'Ла Лига',
    'Бундеслига',
    'Серия А',
    'Лига 1',
    'Лига Чемпионов',
    'Англия',
    'Испания',
    'Германия',
    'Италия',
    'Франция'
]);

// Токен для защиты cron-задач (сгенерируйте свой уникальный)
define('CRON_TOKEN', getenv('CRON_TOKEN') ?: 'change_this_token_in_production');

define('APL_REDIRECT_URL', 'https://p.sportifymn.com/match.php');

// Kinescope API
define('KINESCOPE_API_TOKEN', '92e6ee55-3f6d-4091-b46a-5a5f732f707d');
define('KINESCOPE_API_URL', 'https://api.kinescope.io/v1');

?>
