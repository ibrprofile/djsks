-- Добавление индексов для оптимизации производительности при большом количестве зрителей
-- Выполните этот скрипт после основной миграции БД

-- Индекс для оптимизации запросов активных зрителей
DROP INDEX IF EXISTS idx_stream_viewers_online ON stream_viewers;
CREATE INDEX idx_stream_viewers_online 
ON stream_viewers(stream_key, left_at, last_activity);

-- Индекс для быстрого поиска активности
DROP INDEX IF EXISTS idx_stream_viewers_activity ON stream_viewers;
CREATE INDEX idx_stream_viewers_activity 
ON stream_viewers(stream_key, last_activity);

-- Индекс для статистики потоков
DROP INDEX IF EXISTS idx_stream_stats_key ON stream_viewer_stats;
CREATE INDEX idx_stream_stats_key 
ON stream_viewer_stats(stream_key, timestamp);

-- Индекс для получения максимального онлайна
DROP INDEX IF EXISTS idx_stream_stats_peak ON stream_viewer_stats;
CREATE INDEX idx_stream_stats_peak 
ON stream_viewer_stats(stream_key, online_count);

-- Индекс для группировки по IP (для регионов)
DROP INDEX IF EXISTS idx_stream_viewers_ip ON stream_viewers;
CREATE INDEX idx_stream_viewers_ip 
ON stream_viewers(stream_key, ip_address);

-- Индекс для быстрого поиска по stream_key
DROP INDEX IF EXISTS idx_streaming_events_key ON streaming_events;
CREATE INDEX idx_streaming_events_key 
ON streaming_events(stream_key);

-- Индекс для сортировки по дате
DROP INDEX IF EXISTS idx_streaming_events_created ON streaming_events;
CREATE INDEX idx_streaming_events_created 
ON streaming_events(created_at, status);

-- ВАЖНО: Индекс для админ-панели чата (для быстрого поиска сообщений)
DROP INDEX IF EXISTS idx_chat_messages_match ON chat_messages;
CREATE INDEX idx_chat_messages_match 
ON chat_messages(match_id, id DESC);

-- Индекс для модерации чата по ID пользователя
DROP INDEX IF EXISTS idx_chat_bans_user ON chat_bans;
CREATE INDEX idx_chat_bans_user 
ON chat_bans(user_id, expires_at);

OPTIMIZE TABLE stream_viewers;
OPTIMIZE TABLE stream_viewer_stats;
OPTIMIZE TABLE streaming_events;
OPTIMIZE TABLE chat_messages;
OPTIMIZE TABLE chat_bans;
