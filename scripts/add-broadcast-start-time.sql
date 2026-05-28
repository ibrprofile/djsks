-- Migration: add broadcast_start_time column to matches table
-- This separates "when the stream page opens" from "when the match actually starts"

ALTER TABLE `matches`
    ADD COLUMN IF NOT EXISTS `broadcast_start_time` DATETIME DEFAULT NULL
        COMMENT 'When the stream page becomes accessible (before match start_time)'
        AFTER `start_time`;
