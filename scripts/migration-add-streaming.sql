-- Migration: Add streaming support to existing database
-- Run this if you already have SPORTIFY tables created

-- 1. Alter matches table to add new columns
ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `broadcast_start_time` datetime AFTER `start_time`;
ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `no_draw_vote` tinyint(1) DEFAULT 0 AFTER `created_at`;

-- 2. Create streaming_events table
CREATE TABLE IF NOT EXISTS `streaming_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11),
  `name` varchar(255) NOT NULL,
  `stream_key` varchar(64) NOT NULL UNIQUE,
  `hls_url` varchar(500) NOT NULL,
  `status` enum('pending','live','finished') DEFAULT 'pending',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime,
  `ended_at` datetime,
  `viewer_count` int(11) DEFAULT 0,
  `created_by` varchar(255),
  PRIMARY KEY (`id`),
  UNIQUE KEY `stream_key` (`stream_key`),
  KEY `match_id` (`match_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create hls_tokens table for stream protection
CREATE TABLE IF NOT EXISTS `hls_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stream_key` varchar(64) NOT NULL,
  `token` varchar(128) NOT NULL UNIQUE,
  `client_ip` varchar(45),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `used_at` datetime,
  PRIMARY KEY (`id`),
  KEY `stream_key` (`stream_key`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`),
  FOREIGN KEY (`stream_key`) REFERENCES `streaming_events` (`stream_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Done! Your database is now ready for streaming.
