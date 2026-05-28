CREATE TABLE IF NOT EXISTS ucl_final_2026 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    is_live TINYINT(1) DEFAULT 0,
    is_finished TINYINT(1) DEFAULT 0,
    psg_score INT DEFAULT 0,
    arsenal_score INT DEFAULT 0,
    psg_coach VARCHAR(100) DEFAULT 'Луис Энрике',
    arsenal_coach VARCHAR(100) DEFAULT 'Микель Артета',
    stream_id INT NULL,
    match_date DATETIME DEFAULT '2026-05-30 19:00:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ucl_final_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team ENUM('psg', 'arsenal') NOT NULL,
    name VARCHAR(100) NOT NULL,
    number INT NULL,
    position ENUM('gk', 'def', 'mid', 'fwd') NOT NULL DEFAULT 'mid',
    position_order INT DEFAULT 0,
    is_starter TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ucl_final_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    minute INT NOT NULL,
    event_type ENUM('goal', 'yellow', 'red', 'sub', 'var', 'penalty', 'miss', 'other') NOT NULL DEFAULT 'other',
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    team ENUM('psg', 'arsenal', 'neutral') DEFAULT 'neutral',
    player_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO ucl_final_2026 (id, is_live, is_finished, psg_score, arsenal_score) VALUES (1, 0, 0, 0, 0) 
ON DUPLICATE KEY UPDATE id=id;
