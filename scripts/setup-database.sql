-- Инициализация базы данных Sportify
-- Создание таблиц и начальных данных

CREATE DATABASE IF NOT EXISTS sportify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sportify;

-- Таблица админ-пользователей
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица матчей
CREATE TABLE IF NOT EXISTS `matches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `team_a` VARCHAR(100) NOT NULL,
  `team_b` VARCHAR(100) NOT NULL,
  `league` VARCHAR(100) DEFAULT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME DEFAULT NULL,
  `cover_image` VARCHAR(500) DEFAULT NULL,
  `kinescope_video_id` VARCHAR(100) DEFAULT NULL,
  `hls_url` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('scheduled', 'live', 'finished') NOT NULL DEFAULT 'scheduled',
  `viewers_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `telegram_widget` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_start_time` (`start_time`),
  INDEX `idx_league` (`league`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица баннеров
CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `button_text` VARCHAR(100) NOT NULL,
  `button_link` VARCHAR(500) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_active_order` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица статуса сайта
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка начальных настроек
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('is_open', '1'),
('close_message', 'Сайт временно недоступен. Мы скоро вернемся!')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
