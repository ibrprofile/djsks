-- КРИТИЧЕСКИЕ ИНДЕКСЫ ДЛЯ ОПТИМИЗАЦИИ ПРОИЗВОДИТЕЛЬНОСТИ
-- Этот скрипт добавляет индексы для наиболее часто используемых запросов

-- Таблица matches
ALTER TABLE matches 
  ADD INDEX IF NOT EXISTS idx_status_start_time (status, start_time),
  ADD INDEX IF NOT EXISTS idx_auto_close_time (auto_close_time),
  ADD INDEX IF NOT EXISTS idx_broadcast_start_time (broadcast_start_time);

-- Таблица streaming_events
ALTER TABLE streaming_events
  ADD INDEX IF NOT EXISTS idx_match_id_created (match_id, created_at DESC),
  ADD INDEX IF NOT EXISTS idx_stream_key (stream_key);

-- Таблица stream_viewers
ALTER TABLE stream_viewers
  ADD INDEX IF NOT EXISTS idx_stream_key_left (stream_key, left_at),
  ADD INDEX IF NOT EXISTS idx_stream_key_last_activity (stream_key, last_activity),
  ADD INDEX IF NOT EXISTS idx_viewer_uuid (viewer_uuid);

-- Таблица stream_viewer_stats
ALTER TABLE stream_viewer_stats
  ADD INDEX IF NOT EXISTS idx_stream_key_created (stream_key, created_at DESC),
  ADD INDEX IF NOT EXISTS idx_stream_key_online (stream_key, online_count DESC);

-- УДАЛЕНИЕ НЕИСПОЛЬЗУЕМЫХ ТАБЛИЦ И ИНДЕКСОВ
-- После удаления чата, эти таблицы больше не нужны

-- Удаляем таблицы чата (опционально - если больше не нужны)
-- DROP TABLE IF EXISTS chat_messages;
-- DROP TABLE IF EXISTS chat_bans;
-- DROP TABLE IF EXISTS chat_users;

-- Удаляем таблицу голосов (опционально - если больше не нужны)
-- DROP TABLE IF EXISTS match_votes;

-- КЭШИРОВАНИЕ
-- Для оптимизации API response кэшируйте результаты на 10-60 сек:
-- GET /api/match-status.php          — кэш на 15 сек
-- GET /api/viewer-stats.php?action=stats  — кэш на 60 сек
-- GET /api/viewers.php               — кэш на 30 сек

-- ОПТИМИЗАЦИЯ CONNECTION POOL
-- Убедитесь что в config.php установлено PDO::ATTR_PERSISTENT => true
