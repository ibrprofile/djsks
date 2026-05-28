-- Таблица сообщений чата
CREATE TABLE IF NOT EXISTS chat_messages (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id      INT UNSIGNED NOT NULL,
    user_id       VARCHAR(64) NOT NULL COMMENT 'Fingerprint устройства / IP-хэш',
    username      VARCHAR(32) NOT NULL,
    message       TEXT NOT NULL,
    is_admin      TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned     TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_match_created (match_id, created_at),
    INDEX idx_pinned (match_id, is_pinned),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица заблокированных пользователей чата
CREATE TABLE IF NOT EXISTS chat_bans (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       VARCHAR(64) NOT NULL,
    match_id      INT UNSIGNED NULL COMMENT 'NULL = бан для всех матчей',
    reason        VARCHAR(255) NOT NULL DEFAULT '',
    banned_by     VARCHAR(64) NOT NULL DEFAULT 'admin',
    banned_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME NULL COMMENT 'NULL = навсегда',
    INDEX idx_user_match (user_id, match_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица никнеймов (связь fingerprint → username, уникальность ника)
CREATE TABLE IF NOT EXISTS chat_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       VARCHAR(64) NOT NULL,
    username      VARCHAR(32) NOT NULL,
    accepted_rules TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_id (user_id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индекс для быстрой выборки последних сообщений
ALTER TABLE chat_messages ADD INDEX IF NOT EXISTS idx_match_notdel_created (match_id, is_deleted, created_at);
