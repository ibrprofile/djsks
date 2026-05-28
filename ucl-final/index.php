<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/StreamManager.php';

Security::setSecurityHeaders();

$db = Database::getInstance()->getConnection();

$matchDate = '2026-05-30 19:00:00';
$matchTs = strtotime($matchDate);
$now = time();
$isLive = false;
$isFinished = false;

$uclMatch = null;
try {
    $stmt = $db->query("SELECT * FROM ucl_final_2026 LIMIT 1");
    $uclMatch = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if ($uclMatch) {
    $isLive = (bool)$uclMatch['is_live'];
    $isFinished = (bool)$uclMatch['is_finished'];
}

$streamEvent = null;
$hlsUrl = null;
if ($uclMatch && !empty($uclMatch['stream_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM streaming_events WHERE id = ?");
        $stmt->execute([$uclMatch['stream_id']]);
        $streamEvent = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($streamEvent) {
            $hlsUrl = $streamEvent['hls_url'];
        }
    } catch (PDOException $e) {}
}

$psgSquad = [];
$arsenalSquad = [];
try {
    $stmt = $db->query("SELECT * FROM ucl_final_players WHERE team = 'psg' ORDER BY position_order, name");
    $psgSquad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->query("SELECT * FROM ucl_final_players WHERE team = 'arsenal' ORDER BY position_order, name");
    $arsenalSquad = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$timeline = [];
try {
    $stmt = $db->query("SELECT * FROM ucl_final_timeline ORDER BY minute ASC, id ASC");
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$psgScore = $uclMatch['psg_score'] ?? 0;
$arsenalScore = $uclMatch['arsenal_score'] ?? 0;

function groupByPosition($players) {
    $positions = ['gk' => [], 'def' => [], 'mid' => [], 'fwd' => []];
    foreach ($players as $p) {
        $pos = $p['position'] ?? 'mid';
        if (isset($positions[$pos])) {
            $positions[$pos][] = $p;
        }
    }
    return $positions;
}

$psgGrouped = groupByPosition($psgSquad);
$arsenalGrouped = groupByPosition($arsenalSquad);

$positionNames = [
    'gk' => 'Вратари',
    'def' => 'Защитники', 
    'mid' => 'Полузащитники',
    'fwd' => 'Нападающие'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Финал Лиги чемпионов 2026 | ПСЖ - Арсенал | SPORTIFY</title>
    <meta name="description" content="Смотрите финал Лиги чемпионов УЕФА 2026: ПСЖ - Арсенал. 30 мая, 19:00 МСК. Пушкаш Арена, Будапешт.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
    <style>
        :root {
            --ucl-dark: #0a1128;
            --ucl-navy: #0d1b3e;
            --ucl-blue: #1e3a8a;
            --ucl-gold: #d4af37;
            --ucl-gold-light: #f0d978;
            --ucl-silver: #c0c0c0;
            --ucl-white: #ffffff;
            --ucl-text: #e8eaed;
            --ucl-muted: #8b95a5;
            --psg-red: #e30613;
            --psg-blue: #004170;
            --arsenal-red: #ef0107;
            --arsenal-gold: #9c824a;
            --font-display: 'Barlow Condensed', sans-serif;
            --font-body: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--ucl-dark);
            color: var(--ucl-text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .ucl-bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            background: 
                radial-gradient(ellipse 100% 80% at 20% 0%, rgba(30,58,138,0.4) 0%, transparent 50%),
                radial-gradient(ellipse 80% 60% at 80% 100%, rgba(212,175,55,0.15) 0%, transparent 40%),
                linear-gradient(180deg, var(--ucl-dark) 0%, #071025 100%);
        }

        .ucl-stars {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }

        .ucl-stars::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background-image: 
                radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,0.3), transparent),
                radial-gradient(2px 2px at 40% 70%, rgba(255,255,255,0.2), transparent),
                radial-gradient(1px 1px at 90% 40%, rgba(255,255,255,0.3), transparent),
                radial-gradient(2px 2px at 60% 20%, rgba(212,175,55,0.4), transparent),
                radial-gradient(1px 1px at 10% 60%, rgba(255,255,255,0.2), transparent),
                radial-gradient(2px 2px at 70% 80%, rgba(212,175,55,0.3), transparent);
            animation: starsFloat 120s linear infinite;
        }

        @keyframes starsFloat {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-50%, -50%); }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(10,17,40,0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(212,175,55,0.2);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 800;
            color: var(--ucl-gold);
            text-decoration: none;
            letter-spacing: 1px;
        }

        .header-nav {
            display: flex;
            gap: 24px;
        }

        .header-nav a {
            color: var(--ucl-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-nav a:hover { color: var(--ucl-gold); }

        .ucl-hero {
            position: relative;
            padding: 60px 0 40px;
            text-align: center;
            overflow: hidden;
        }

        .ucl-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--ucl-gold) 0%, var(--ucl-gold-light) 50%, var(--ucl-gold) 100%);
            color: var(--ucl-dark);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(212,175,55,0.4);
        }

        .ucl-badge svg {
            width: 16px;
            height: 16px;
        }

        .ucl-title {
            font-family: var(--font-display);
            font-size: clamp(2rem, 6vw, 3.5rem);
            font-weight: 800;
            color: var(--ucl-white);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 16px;
            text-shadow: 0 4px 30px rgba(0,0,0,0.5);
        }

        .ucl-subtitle {
            font-size: 16px;
            color: var(--ucl-muted);
            margin-bottom: 8px;
        }

        .ucl-venue {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--ucl-gold);
            font-size: 14px;
            font-weight: 600;
        }

        .teams-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .team-block {
            text-align: center;
            flex: 1;
            max-width: 280px;
            min-width: 200px;
        }

        .team-crest {
            width: 100px;
            height: 100px;
            margin: 0 auto 16px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(212,175,55,0.3);
            font-size: 40px;
            transition: transform 0.3s, border-color 0.3s;
        }

        .team-block:hover .team-crest {
            transform: scale(1.05);
            border-color: var(--ucl-gold);
        }

        .team-block.psg .team-crest { background: linear-gradient(135deg, var(--psg-blue), var(--psg-red)); }
        .team-block.arsenal .team-crest { background: linear-gradient(135deg, var(--arsenal-red), #9c0000); }

        .team-name {
            font-family: var(--font-display);
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--ucl-white);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .team-country {
            font-size: 13px;
            color: var(--ucl-muted);
            margin-top: 4px;
        }

        .vs-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .vs-text {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            color: var(--ucl-gold);
        }

        .score-display {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(0,0,0,0.4);
            padding: 12px 28px;
            border-radius: 12px;
            border: 1px solid rgba(212,175,55,0.3);
        }

        .score-num {
            font-family: var(--font-display);
            font-size: 3rem;
            font-weight: 800;
            color: var(--ucl-white);
            min-width: 50px;
            text-align: center;
        }

        .score-sep {
            font-family: var(--font-display);
            font-size: 2rem;
            color: var(--ucl-gold);
        }

        .countdown-section {
            background: linear-gradient(135deg, rgba(30,58,138,0.3) 0%, rgba(13,27,62,0.5) 100%);
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 40px;
            text-align: center;
        }

        .countdown-label {
            font-size: 14px;
            color: var(--ucl-gold);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .countdown-item {
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(212,175,55,0.3);
            border-radius: 12px;
            padding: 16px 20px;
            min-width: 80px;
        }

        .countdown-num {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--ucl-white);
            line-height: 1;
        }

        .countdown-unit {
            font-size: 11px;
            color: var(--ucl-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 6px;
        }

        .player-section {
            margin-bottom: 48px;
        }

        .player-box {
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0,0,0,0.4);
        }

        .player-container {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
        }

        .player-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .player-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.7);
            z-index: 10;
            transition: opacity 0.3s;
        }

        .player-overlay.hidden { opacity: 0; pointer-events: none; }

        .play-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ucl-gold) 0%, var(--ucl-gold-light) 100%);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 30px rgba(212,175,55,0.5);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .play-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 40px rgba(212,175,55,0.7);
        }

        .play-btn svg {
            width: 32px;
            height: 32px;
            fill: var(--ucl-dark);
            margin-left: 4px;
        }

        .player-status {
            margin-top: 16px;
            font-size: 14px;
            color: var(--ucl-muted);
        }

        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid rgba(212,175,55,0.2);
            border-top-color: var(--ucl-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--ucl-white);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 28px;
            background: var(--ucl-gold);
            border-radius: 2px;
        }

        .match-info-section {
            margin-bottom: 48px;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(30,58,138,0.2) 0%, rgba(13,27,62,0.4) 100%);
            border: 1px solid rgba(212,175,55,0.15);
            border-radius: 16px;
            padding: 28px;
        }

        .info-card p {
            font-size: 15px;
            line-height: 1.8;
            color: var(--ucl-text);
            margin-bottom: 16px;
        }

        .info-card p:last-child { margin-bottom: 0; }

        .squads-section {
            margin-bottom: 48px;
        }

        .squads-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .squad-card {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(212,175,55,0.15);
            border-radius: 16px;
            overflow: hidden;
        }

        .squad-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .squad-header.psg { background: linear-gradient(135deg, var(--psg-blue), rgba(0,65,112,0.5)); }
        .squad-header.arsenal { background: linear-gradient(135deg, var(--arsenal-red), rgba(156,0,0,0.5)); }

        .squad-crest {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .squad-team-name {
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--ucl-white);
            text-transform: uppercase;
        }

        .squad-coach {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
        }

        .squad-body {
            padding: 20px 24px;
        }

        .position-group {
            margin-bottom: 20px;
        }

        .position-group:last-child { margin-bottom: 0; }

        .position-title {
            font-size: 11px;
            color: var(--ucl-gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .player-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .player-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            transition: background 0.2s;
        }

        .player-item:hover { background: rgba(255,255,255,0.08); }

        .player-number {
            width: 28px;
            height: 28px;
            background: rgba(212,175,55,0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--ucl-gold);
        }

        .player-name {
            font-size: 14px;
            color: var(--ucl-text);
            flex: 1;
        }

        .player-starter {
            font-size: 10px;
            padding: 3px 8px;
            background: rgba(16,185,129,0.2);
            color: #34d399;
            border-radius: 4px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .player-sub {
            font-size: 10px;
            padding: 3px 8px;
            background: rgba(107,114,128,0.2);
            color: #9ca3af;
            border-radius: 4px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .timeline-section {
            margin-bottom: 48px;
        }

        .timeline-card {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(212,175,55,0.15);
            border-radius: 16px;
            padding: 24px;
        }

        .timeline-empty {
            text-align: center;
            padding: 40px;
            color: var(--ucl-muted);
        }

        .timeline-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .timeline-item:last-child { border-bottom: none; }

        .timeline-minute {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--ucl-blue), var(--ucl-navy));
            border: 2px solid var(--ucl-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            color: var(--ucl-white);
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-event {
            font-weight: 600;
            color: var(--ucl-white);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timeline-event .icon { font-size: 18px; }

        .event-goal .icon { color: #22c55e; }
        .event-yellow .icon { color: #eab308; }
        .event-red .icon { color: #ef4444; }
        .event-sub .icon { color: #3b82f6; }

        .timeline-detail {
            font-size: 13px;
            color: var(--ucl-muted);
        }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ef4444;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            animation: livePulse 2s ease-in-out infinite;
        }

        .live-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        footer {
            background: rgba(0,0,0,0.4);
            border-top: 1px solid rgba(212,175,55,0.15);
            padding: 32px 20px;
            text-align: center;
            margin-top: 48px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .footer-link {
            color: var(--ucl-muted);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-link:hover { color: var(--ucl-gold); }

        .footer-copy {
            font-size: 13px;
            color: var(--ucl-muted);
        }

        @media (max-width: 900px) {
            .squads-grid { grid-template-columns: 1fr; }
            .teams-display { gap: 16px; }
            .team-crest { width: 80px; height: 80px; font-size: 32px; }
            .team-name { font-size: 1.4rem; }
        }

        @media (max-width: 600px) {
            .ucl-hero { padding: 40px 0 24px; }
            .ucl-title { font-size: 1.8rem; }
            .countdown-item { min-width: 65px; padding: 12px 14px; }
            .countdown-num { font-size: 1.8rem; }
            .score-num { font-size: 2rem; }
            .team-block { min-width: 140px; }
            .header-nav { gap: 16px; }
            .header-nav span { display: none; }
        }
    </style>
</head>
<body>
    <div class="ucl-bg"></div>
    <div class="ucl-stars"></div>

    <header>
        <div class="header-content">
            <a href="/" class="logo">SPORTIFY</a>
            <nav class="header-nav">
                <a href="https://t.me/+G7ItfFblqe85MmEy" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
                    <span>Telegram</span>
                </a>
                <a href="/help.php"><span>Помощь</span></a>
            </nav>
        </div>
    </header>

    <main>
        <section class="ucl-hero">
            <div class="container">
                <div class="ucl-badge">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    ФИНАЛ ЛИГИ ЧЕМПИОНОВ УЕФА 2026
                </div>

                <h1 class="ucl-title">ПСЖ — Арсенал</h1>
                <p class="ucl-subtitle">30 мая 2026 · 19:00 МСК</p>
                <p class="ucl-venue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Пушкаш Арена, Будапешт
                </p>

                <div class="teams-display">
                    <div class="team-block psg">
                        <div class="team-crest">⚜️</div>
                        <div class="team-name">Paris Saint-Germain</div>
                        <div class="team-country">Франция</div>
                    </div>

                    <div class="vs-block">
                        <?php if ($isLive || $isFinished): ?>
                        <div class="score-display">
                            <span class="score-num"><?php echo $psgScore; ?></span>
                            <span class="score-sep">:</span>
                            <span class="score-num"><?php echo $arsenalScore; ?></span>
                        </div>
                        <?php if ($isLive): ?>
                        <span class="live-badge">LIVE</span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="vs-text">VS</span>
                        <?php endif; ?>
                    </div>

                    <div class="team-block arsenal">
                        <div class="team-crest">🔴</div>
                        <div class="team-name">Arsenal F.C.</div>
                        <div class="team-country">Англия</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container">

            <?php if (!$isLive && !$isFinished && $now < $matchTs): ?>
            <section class="countdown-section" id="countdownSection">
                <div class="countdown-label">До начала матча</div>
                <div class="countdown-timer" id="countdown" data-target="<?php echo $matchTs; ?>">
                    <div class="countdown-item">
                        <div class="countdown-num" id="cd-days">00</div>
                        <div class="countdown-unit">дней</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-num" id="cd-hours">00</div>
                        <div class="countdown-unit">часов</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-num" id="cd-mins">00</div>
                        <div class="countdown-unit">минут</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-num" id="cd-secs">00</div>
                        <div class="countdown-unit">секунд</div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="player-section">
                <div class="player-box">
                    <div class="player-container" id="playerContainer">
                        <?php if ($isLive && $hlsUrl): ?>
                        <video id="videoPlayer" playsinline></video>
                        <div class="player-overlay" id="playerOverlay">
                            <div class="loading-spinner" id="loadingSpinner"></div>
                            <p class="player-status" id="playerStatus">Подключение к трансляции...</p>
                        </div>
                        <?php elseif (!$isLive && !$isFinished): ?>
                        <video id="teaserPlayer" playsinline poster="/ucl-final-2026/poster.jpg"></video>
                        <div class="player-overlay" id="playerOverlay">
                            <button class="play-btn" id="playTeaserBtn">
                                <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </button>
                            <p class="player-status">Смотреть тизер финала</p>
                        </div>
                        <?php else: ?>
                        <div class="player-overlay">
                            <p class="player-status">Матч завершён</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="match-info-section">
                <h2 class="section-title">О матче</h2>
                <div class="info-card">
                    <p>Финал Лиги чемпионов УЕФА 2026 года — матч, который уже называют одним из самых ожидаемых европейских финалов последних лет. На легендарной «Пушкаш Арене» в Будапеште встретятся Paris Saint-Germain и Arsenal F.C. — две команды, прошедшие невероятно сложный путь ради главной футбольной ночи сезона.</p>
                    <p>Для парижан этот финал — шанс защитить титул и окончательно закрепить своё место среди элиты европейского футбола, а для лондонского клуба — возможность впервые в истории поднять над головой самый престижный клубный трофей Европы.</p>
                    <p>«Арсенал» под руководством Микеля Артеты провёл практически идеальный турнир, уверенно завершив общий этап без поражений. Лондонцы вернулись в финал Лиги чемпионов впервые с 2006 года — спустя два десятилетия ожиданий.</p>
                    <p>Перед матчем состоится традиционное шоу открытия, хедлайнером церемонии станет группа The Killers.</p>
                </div>
            </section>

            <?php if (!empty($psgSquad) || !empty($arsenalSquad)): ?>
            <section class="squads-section">
                <h2 class="section-title">Составы команд</h2>
                <div class="squads-grid">
                    <div class="squad-card">
                        <div class="squad-header psg">
                            <div class="squad-crest">⚜️</div>
                            <div>
                                <div class="squad-team-name">ПСЖ</div>
                                <div class="squad-coach"><?php echo e($uclMatch['psg_coach'] ?? 'Луис Энрике'); ?></div>
                            </div>
                        </div>
                        <div class="squad-body">
                            <?php foreach ($positionNames as $pos => $posName): ?>
                            <?php if (!empty($psgGrouped[$pos])): ?>
                            <div class="position-group">
                                <div class="position-title"><?php echo $posName; ?></div>
                                <div class="player-list">
                                    <?php foreach ($psgGrouped[$pos] as $player): ?>
                                    <div class="player-item">
                                        <span class="player-number"><?php echo $player['number'] ?? '-'; ?></span>
                                        <span class="player-name"><?php echo e($player['name']); ?></span>
                                        <?php if ($player['is_starter']): ?>
                                        <span class="player-starter">Старт</span>
                                        <?php else: ?>
                                        <span class="player-sub">Запас</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="squad-card">
                        <div class="squad-header arsenal">
                            <div class="squad-crest">🔴</div>
                            <div>
                                <div class="squad-team-name">Арсенал</div>
                                <div class="squad-coach"><?php echo e($uclMatch['arsenal_coach'] ?? 'Микель Артета'); ?></div>
                            </div>
                        </div>
                        <div class="squad-body">
                            <?php foreach ($positionNames as $pos => $posName): ?>
                            <?php if (!empty($arsenalGrouped[$pos])): ?>
                            <div class="position-group">
                                <div class="position-title"><?php echo $posName; ?></div>
                                <div class="player-list">
                                    <?php foreach ($arsenalGrouped[$pos] as $player): ?>
                                    <div class="player-item">
                                        <span class="player-number"><?php echo $player['number'] ?? '-'; ?></span>
                                        <span class="player-name"><?php echo e($player['name']); ?></span>
                                        <?php if ($player['is_starter']): ?>
                                        <span class="player-starter">Старт</span>
                                        <?php else: ?>
                                        <span class="player-sub">Запас</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="timeline-section">
                <h2 class="section-title">Хронология матча</h2>
                <div class="timeline-card">
                    <?php if (empty($timeline)): ?>
                    <div class="timeline-empty">
                        <p>События матча появятся здесь после начала игры</p>
                    </div>
                    <?php else: ?>
                    <div class="timeline-list">
                        <?php foreach ($timeline as $event): 
                            $eventClass = '';
                            $icon = '⚽';
                            switch ($event['event_type']) {
                                case 'goal': $eventClass = 'event-goal'; $icon = '⚽'; break;
                                case 'yellow': $eventClass = 'event-yellow'; $icon = '🟨'; break;
                                case 'red': $eventClass = 'event-red'; $icon = '🟥'; break;
                                case 'sub': $eventClass = 'event-sub'; $icon = '🔄'; break;
                                case 'var': $icon = '📺'; break;
                                case 'penalty': $icon = '⚽'; $eventClass = 'event-goal'; break;
                                default: $icon = '📌';
                            }
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-minute"><?php echo $event['minute']; ?>'</div>
                            <div class="timeline-content">
                                <div class="timeline-event <?php echo $eventClass; ?>">
                                    <span class="icon"><?php echo $icon; ?></span>
                                    <?php echo e($event['title']); ?>
                                </div>
                                <?php if (!empty($event['description'])): ?>
                                <div class="timeline-detail"><?php echo e($event['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="/" class="footer-link">Главная</a>
                <a href="/help.php" class="footer-link">Помощь</a>
                <a href="https://t.me/+G7ItfFblqe85MmEy" target="_blank" class="footer-link">Telegram</a>
            </div>
            <p class="footer-copy">&copy; <?php echo date('Y'); ?> SPORTIFY. Все права защищены.</p>
        </div>
    </footer>

    <script>
    (function() {
        var countdownEl = document.getElementById('countdown');
        if (countdownEl) {
            var targetTs = parseInt(countdownEl.dataset.target, 10) * 1000;
            function updateCountdown() {
                var now = Date.now();
                var diff = Math.max(0, Math.floor((targetTs - now) / 1000));
                var d = Math.floor(diff / 86400);
                var h = Math.floor((diff % 86400) / 3600);
                var m = Math.floor((diff % 3600) / 60);
                var s = diff % 60;
                document.getElementById('cd-days').textContent = d < 10 ? '0' + d : d;
                document.getElementById('cd-hours').textContent = h < 10 ? '0' + h : h;
                document.getElementById('cd-mins').textContent = m < 10 ? '0' + m : m;
                document.getElementById('cd-secs').textContent = s < 10 ? '0' + s : s;
                if (diff <= 0) location.reload();
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        var teaserBtn = document.getElementById('playTeaserBtn');
        var teaserPlayer = document.getElementById('teaserPlayer');
        var teaserOverlay = document.getElementById('playerOverlay');

        if (teaserBtn && teaserPlayer) {
            teaserBtn.addEventListener('click', function() {
                teaserPlayer.src = '/ucl-final-2026/tizer.mp4';
                teaserPlayer.controls = true;
                teaserPlayer.play();
                teaserOverlay.classList.add('hidden');
            });
        }

        <?php if ($isLive && $hlsUrl): ?>
        var videoPlayer = document.getElementById('videoPlayer');
        var overlay = document.getElementById('playerOverlay');
        var spinner = document.getElementById('loadingSpinner');
        var status = document.getElementById('playerStatus');
        var hlsUrl = <?php echo json_encode($hlsUrl); ?>;

        function initPlayer() {
            if (videoPlayer.canPlayType('application/vnd.apple.mpegurl')) {
                videoPlayer.src = hlsUrl;
                videoPlayer.addEventListener('loadedmetadata', onReady);
                videoPlayer.addEventListener('error', onError);
            } else if (window.Hls && Hls.isSupported()) {
                var hls = new Hls({
                    enableWorker: true,
                    lowLatencyMode: false,
                    maxBufferLength: 60,
                    maxMaxBufferLength: 120,
                    maxBufferSize: 120 * 1000 * 1000,
                    manifestLoadingTimeOut: 30000,
                    manifestLoadingMaxRetry: 6,
                    levelLoadingTimeOut: 30000,
                    fragLoadingTimeOut: 30000,
                    startLevel: -1,
                    backBufferLength: 90,
                    maxFragLookUpTolerance: 0.5
                });

                hls.loadSource(hlsUrl);
                hls.attachMedia(videoPlayer);
                hls.on(Hls.Events.MANIFEST_PARSED, onReady);
                hls.on(Hls.Events.ERROR, function(e, data) {
                    if (data.fatal) {
                        if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                            status.textContent = 'Переподключение...';
                            setTimeout(function() { hls.startLoad(); }, 2000);
                        } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                            hls.recoverMediaError();
                        } else {
                            onError();
                        }
                    }
                });
            } else {
                status.textContent = 'Браузер не поддерживает воспроизведение';
                spinner.style.display = 'none';
            }
        }

        function onReady() {
            overlay.classList.add('hidden');
            videoPlayer.controls = true;
            videoPlayer.play().catch(function() {});
        }

        function onError() {
            status.textContent = 'Ошибка загрузки трансляции';
            spinner.style.display = 'none';
        }

        initPlayer();
        <?php endif; ?>

    })();
    </script>
</body>
</html>
