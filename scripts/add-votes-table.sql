-- Таблица голосований за исход матча
CREATE TABLE IF NOT EXISTS match_votes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    match_id    INT NOT NULL,
    ip_hash     VARCHAR(64) NOT NULL,
    vote        ENUM('1','draw','2') NOT NULL,
    created_at  DATETIME DEFAULT NOW(),
    updated_at  DATETIME DEFAULT NOW(),
    UNIQUE KEY uq_match_ip (match_id, ip_hash),
    INDEX idx_match (match_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Флаг "без ничьей" в матчах (для матчей на вылет)
ALTER TABLE matches
    ADD COLUMN IF NOT EXISTS no_draw_vote TINYINT(1) NOT NULL DEFAULT 0;
