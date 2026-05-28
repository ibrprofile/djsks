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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Финал Лиги чемпионов 2026 смотреть онлайн бесплатно | ПСЖ - Арсенал прямая трансляция | SPORTIFY TV</title>
    <meta name="description" content="Смотреть финал Лиги чемпионов УЕФА 2026 ПСЖ - Арсенал онлайн бесплатно в HD качестве. Прямая трансляция 30 мая в 19:00 МСК. Пушкаш Арена, Будапешт. Paris Saint-Germain vs Arsenal FC live stream free.">
    <meta name="keywords" content="финал лиги чемпионов 2026, смотреть финал лч онлайн, псж арсенал прямая трансляция, psg arsenal live stream, лига чемпионов финал смотреть бесплатно, псж арсенал онлайн, финал лч 2026 смотреть, paris saint germain arsenal, champions league final 2026, ucl final live, смотреть псж арсенал бесплатно, трансляция финала лиги чемпионов, будапешт финал лч, пушкаш арена финал, арсенал псж смотреть онлайн, лига чемпионов уефа финал, football live stream, футбол онлайн бесплатно, прямой эфир финал лч, смотреть футбол онлайн hd">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="author" content="SPORTIFY TV">
    <link rel="canonical" href="https://sportifymn.com/ucl-final/">
    
    <meta property="og:type" content="website">
    <meta property="og:title" content="Финал Лиги чемпионов 2026 | ПСЖ - Арсенал | Смотреть онлайн бесплатно">
    <meta property="og:description" content="Прямая трансляция финала Лиги чемпионов УЕФА 2026. ПСЖ против Арсенала. 30 мая, 19:00 МСК. Смотреть бесплатно в HD.">
    <meta property="og:image" content="https://sportifymn.com/uploads/final-b.png">
    <meta property="og:url" content="https://sportifymn.com/ucl-final/">
    <meta property="og:site_name" content="SPORTIFY TV">
    <meta property="og:locale" content="ru_RU">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Финал Лиги чемпионов 2026 | ПСЖ - Арсенал">
    <meta name="twitter:description" content="Смотреть финал ЛЧ онлайн бесплатно. ПСЖ - Арсенал, 30 мая 2026.">
    <meta name="twitter:image" content="https://sportifymn.com/uploads/final-b.png">
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SportsEvent",
        "name": "UEFA Champions League Final 2026 - PSG vs Arsenal",
        "description": "Финал Лиги чемпионов УЕФА 2026 года между Paris Saint-Germain и Arsenal FC на Пушкаш Арене в Будапеште",
        "startDate": "2026-05-30T19:00:00+03:00",
        "endDate": "2026-05-30T22:00:00+03:00",
        "location": {
            "@type": "Place",
            "name": "Puskas Arena",
            "address": {
                "@type": "PostalAddress",
                "addressLocality": "Budapest",
                "addressCountry": "Hungary"
            }
        },
        "competitor": [
            {
                "@type": "SportsTeam",
                "name": "Paris Saint-Germain",
                "alternateName": "PSG"
            },
            {
                "@type": "SportsTeam", 
                "name": "Arsenal FC",
                "alternateName": "Arsenal"
            }
        ],
        "organizer": {
            "@type": "Organization",
            "name": "UEFA"
        }
    }
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{font-family:'Inter',system-ui,sans-serif;background:#050a14;color:#e4e4e7;min-height:100vh;overflow-x:hidden;-webkit-font-smoothing:antialiased}
        
        .hero-section{position:relative;min-height:100vh;display:flex;flex-direction:column;overflow:hidden}
        .hero-video-bg{position:absolute;inset:0;z-index:0}
        .hero-video-bg video{width:100%;height:100%;object-fit:cover;filter:brightness(0.35)}
        .hero-video-bg::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(5,10,20,0.3) 0%,rgba(5,10,20,0.6) 50%,rgba(5,10,20,0.95) 100%)}
        
        .hero-content{position:relative;z-index:10;flex:1;display:flex;flex-direction:column;justify-content:center;padding:100px 24px 60px}
        .hero-inner{max-width:1100px;margin:0 auto;width:100%;text-align:center}
        
        .ucl-logo{margin-bottom:32px}
        .ucl-logo svg{height:64px;width:auto}
        
        .event-badge{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(90deg,#c9a227 0%,#f4d03f 50%,#c9a227 100%);color:#0a0f1e;padding:10px 24px;border-radius:100px;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;margin-bottom:28px;box-shadow:0 0 40px rgba(201,162,39,0.4)}
        
        .match-title{font-family:'Oswald',sans-serif;font-size:clamp(2.8rem,8vw,5.5rem);font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:3px;line-height:1.05;margin-bottom:20px;text-shadow:0 4px 60px rgba(0,0,0,0.8)}
        .match-title span{display:block;color:#c9a227}
        
        .match-meta{display:flex;align-items:center;justify-content:center;gap:24px;flex-wrap:wrap;margin-bottom:40px;font-size:15px;color:rgba(255,255,255,0.75)}
        .match-meta-item{display:flex;align-items:center;gap:8px}
        .match-meta-item svg{width:18px;height:18px;opacity:0.7}
        
        .teams-row{display:flex;align-items:center;justify-content:center;gap:clamp(24px,6vw,80px);margin-bottom:48px}
        .team-card{text-align:center;flex-shrink:0}
        .team-emblem{width:clamp(80px,18vw,140px);height:clamp(80px,18vw,140px);margin:0 auto 16px;background:rgba(255,255,255,0.05);border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid rgba(201,162,39,0.3);transition:all 0.4s cubic-bezier(0.4,0,0.2,1);overflow:hidden;backdrop-filter:blur(10px)}
        .team-emblem:hover{transform:scale(1.08);border-color:#c9a227;box-shadow:0 0 50px rgba(201,162,39,0.3)}
        .team-emblem img{width:70%;height:70%;object-fit:contain}
        .team-name{font-family:'Oswald',sans-serif;font-size:clamp(1.1rem,3vw,1.6rem);font-weight:600;color:#fff;text-transform:uppercase;letter-spacing:1px}
        .team-country{font-size:12px;color:rgba(255,255,255,0.5);margin-top:4px;letter-spacing:0.5px}
        
        .vs-badge{display:flex;flex-direction:column;align-items:center;gap:4px}
        .vs-text{font-family:'Oswald',sans-serif;font-size:clamp(1.5rem,4vw,2.2rem);font-weight:700;color:#c9a227;letter-spacing:2px}
        .vs-time{font-size:13px;color:rgba(255,255,255,0.6)}
        
        .score-live{display:none;align-items:center;gap:20px;background:rgba(0,0,0,0.5);padding:16px 36px;border-radius:16px;border:1px solid rgba(201,162,39,0.3)}
        .score-live.active{display:flex}
        .score-num{font-family:'Oswald',sans-serif;font-size:clamp(2.5rem,6vw,4rem);font-weight:700;color:#fff;min-width:60px;text-align:center}
        .score-divider{width:3px;height:40px;background:#c9a227;border-radius:2px}
        
        .cta-buttons{display:flex;align-items:center;justify-content:center;gap:16px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:10px;padding:16px 36px;border-radius:12px;font-size:15px;font-weight:600;text-decoration:none;transition:all 0.3s ease;cursor:pointer;border:none}
        .btn-primary{background:linear-gradient(135deg,#c9a227 0%,#e8c547 100%);color:#0a0f1e;box-shadow:0 4px 24px rgba(201,162,39,0.4)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(201,162,39,0.5)}
        .btn-secondary{background:rgba(255,255,255,0.08);color:#fff;border:1px solid rgba(255,255,255,0.15);backdrop-filter:blur(10px)}
        .btn-secondary:hover{background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.25)}
        .btn svg{width:20px;height:20px}
        
        .countdown-strip{background:rgba(0,0,0,0.4);backdrop-filter:blur(20px);border-top:1px solid rgba(201,162,39,0.15);padding:28px 24px;position:relative;z-index:10}
        .countdown-inner{max-width:800px;margin:0 auto;text-align:center}
        .countdown-label{font-size:12px;color:#c9a227;text-transform:uppercase;letter-spacing:3px;margin-bottom:16px;font-weight:600}
        .countdown-timer{display:flex;justify-content:center;gap:clamp(12px,3vw,24px)}
        .countdown-block{min-width:clamp(60px,12vw,90px)}
        .countdown-value{font-family:'Oswald',sans-serif;font-size:clamp(2rem,5vw,3.2rem);font-weight:700;color:#fff;line-height:1}
        .countdown-unit{font-size:11px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:1.5px;margin-top:8px}
        
        .main-content{position:relative;z-index:10;background:#050a14}
        .content-glow{position:absolute;top:0;left:50%;transform:translateX(-50%);width:100%;max-width:1000px;height:400px;background:radial-gradient(ellipse at center,rgba(201,162,39,0.08) 0%,transparent 70%);pointer-events:none}
        
        .container{max-width:1100px;margin:0 auto;padding:0 24px}
        
        .stream-section{padding:60px 0;display:none}
        .stream-section.active{display:block}
        .stream-player{background:#000;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.5);border:1px solid rgba(201,162,39,0.15)}
        .player-wrapper{position:relative;width:100%;aspect-ratio:16/9}
        .player-wrapper video{width:100%;height:100%;object-fit:contain;background:#000}
        .player-loader{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(0,0,0,0.9);z-index:10}
        .player-loader.hidden{display:none}
        .spinner{width:48px;height:48px;border:3px solid rgba(201,162,39,0.2);border-top-color:#c9a227;border-radius:50%;animation:spin 0.8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .loader-text{color:rgba(255,255,255,0.6);font-size:14px;margin-top:16px}
        .stream-live-badge{position:absolute;top:16px;left:16px;display:flex;align-items:center;gap:8px;background:rgba(220,38,38,0.9);padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;color:#fff;z-index:20}
        .live-dot{width:8px;height:8px;background:#fff;border-radius:50%;animation:pulse 1.5s infinite}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:0.4}}
        
        .info-section{padding:60px 0}
        .section-header{margin-bottom:32px}
        .section-title{font-family:'Oswald',sans-serif;font-size:clamp(1.5rem,4vw,2rem);font-weight:600;color:#fff;text-transform:uppercase;letter-spacing:1.5px;display:flex;align-items:center;gap:14px}
        .section-title::before{content:'';width:4px;height:32px;background:linear-gradient(180deg,#c9a227,#e8c547);border-radius:2px}
        
        .info-grid{display:grid;grid-template-columns:1fr;gap:24px}
        .info-card{background:linear-gradient(145deg,rgba(20,25,40,0.8),rgba(10,15,25,0.9));border:1px solid rgba(201,162,39,0.1);border-radius:16px;padding:clamp(24px,4vw,36px);backdrop-filter:blur(10px)}
        .info-card p{font-size:15px;line-height:1.85;color:rgba(255,255,255,0.8);margin-bottom:20px}
        .info-card p:last-child{margin-bottom:0}
        .info-card strong{color:#c9a227}
        
        .squads-section{padding:60px 0}
        .squads-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:24px}
        .squad-card{background:rgba(15,20,30,0.6);border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden}
        .squad-header{padding:20px 24px;display:flex;align-items:center;gap:16px}
        .squad-header.psg{background:linear-gradient(135deg,#004170 0%,#002040 100%)}
        .squad-header.arsenal{background:linear-gradient(135deg,#db0007 0%,#9c0006 100%)}
        .squad-emblem{width:44px;height:44px;background:rgba(255,255,255,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .squad-emblem img{width:70%;height:70%;object-fit:contain}
        .squad-info{flex:1}
        .squad-team{font-family:'Oswald',sans-serif;font-size:1.15rem;font-weight:600;color:#fff;text-transform:uppercase;letter-spacing:0.5px}
        .squad-coach{font-size:12px;color:rgba(255,255,255,0.65);margin-top:2px}
        .squad-body{padding:20px 24px}
        .position-group{margin-bottom:20px}
        .position-group:last-child{margin-bottom:0}
        .position-label{font-size:10px;color:#c9a227;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px;font-weight:600}
        .players-list{display:flex;flex-direction:column;gap:6px}
        .player-row{display:flex;align-items:center;gap:12px;padding:10px 14px;background:rgba(255,255,255,0.02);border-radius:8px;transition:background 0.2s}
        .player-row:hover{background:rgba(255,255,255,0.05)}
        .player-num{width:28px;height:28px;background:rgba(201,162,39,0.15);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#c9a227}
        .player-nm{flex:1;font-size:14px;color:#e4e4e7}
        .player-tag{font-size:9px;padding:4px 8px;border-radius:4px;text-transform:uppercase;font-weight:600;letter-spacing:0.5px}
        .player-tag.starter{background:rgba(34,197,94,0.15);color:#4ade80}
        .player-tag.sub{background:rgba(100,116,139,0.2);color:#94a3b8}
        
        .timeline-section{padding:60px 0}
        .timeline-card{background:rgba(15,20,30,0.6);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:24px}
        .timeline-empty{text-align:center;padding:48px 24px;color:rgba(255,255,255,0.4)}
        .timeline-list{display:flex;flex-direction:column}
        .timeline-item{display:flex;align-items:flex-start;gap:16px;padding:18px 0;border-bottom:1px solid rgba(255,255,255,0.04)}
        .timeline-item:last-child{border-bottom:none}
        .tl-minute{width:52px;height:52px;background:linear-gradient(135deg,rgba(201,162,39,0.2),rgba(201,162,39,0.1));border:2px solid #c9a227;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Oswald',sans-serif;font-size:1rem;font-weight:600;color:#c9a227;flex-shrink:0}
        .tl-content{flex:1;padding-top:4px}
        .tl-type{font-size:11px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
        .tl-desc{font-size:15px;color:#fff}
        .tl-team{font-size:12px;color:rgba(255,255,255,0.5);margin-top:4px}
        
        footer{background:rgba(5,10,20,0.95);border-top:1px solid rgba(255,255,255,0.05);padding:40px 24px;text-align:center}
        .footer-text{font-size:13px;color:rgba(255,255,255,0.4)}
        .footer-text a{color:#c9a227;text-decoration:none}
        
        .teaser-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.95);padding:24px}
        .teaser-modal.active{display:flex}
        .teaser-container{position:relative;width:100%;max-width:1000px;background:#000;border-radius:12px;overflow:hidden;box-shadow:0 20px 80px rgba(0,0,0,0.8)}
        .teaser-close{position:absolute;top:16px;right:16px;width:44px;height:44px;background:rgba(0,0,0,0.6);border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10;transition:background 0.2s}
        .teaser-close:hover{background:rgba(255,255,255,0.1)}
        .teaser-close svg{width:24px;height:24px;stroke:#fff}
        .teaser-video{width:100%;aspect-ratio:16/9}
        .teaser-video video{width:100%;height:100%;object-fit:cover}
        
        @media(max-width:900px){
            .squads-grid{grid-template-columns:1fr}
        }
        @media(max-width:640px){
            .hero-content{padding:80px 20px 40px}
            .teams-row{gap:16px}
            .cta-buttons{flex-direction:column;width:100%}
            .btn{width:100%;justify-content:center}
            .countdown-timer{gap:8px}
            .countdown-block{min-width:50px}
            .squad-body{padding:16px}
            .player-row{padding:8px 10px}
        }
        
        .fade-in{opacity:0;transform:translateY(20px);animation:fadeIn 0.6s ease forwards}
        @keyframes fadeIn{to{opacity:1;transform:translateY(0)}}
        .delay-1{animation-delay:0.1s}
        .delay-2{animation-delay:0.2s}
        .delay-3{animation-delay:0.3s}
        .delay-4{animation-delay:0.4s}
    </style>
</head>
<body>
    <section class="hero-section">
        <div class="hero-video-bg">
            <video autoplay muted loop playsinline id="bgVideo">
                <source src="/ucl-final-2026/tizer.mp4" type="video/mp4">
            </video>
        </div>
        
        <div class="hero-content">
            <div class="hero-inner">
                <div class="ucl-logo fade-in">
                    <svg viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M30 5L35 20H50L38 30L43 45L30 35L17 45L22 30L10 20H25L30 5Z" fill="#c9a227"/>
                        <path d="M70 5L75 20H90L78 30L83 45L70 35L57 45L62 30L50 20H65L70 5Z" fill="#c9a227" opacity="0.7"/>
                        <text x="100" y="42" font-family="Oswald" font-size="28" font-weight="700" fill="#fff" letter-spacing="2">UEFA</text>
                        <text x="100" y="55" font-family="Oswald" font-size="12" fill="rgba(255,255,255,0.6)" letter-spacing="3">CHAMPIONS LEAGUE</text>
                    </svg>
                </div>
                
                <div class="event-badge fade-in delay-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Final 2026
                </div>
                
                <h1 class="match-title fade-in delay-2">
                    PSG <span>vs</span> Arsenal
                </h1>
                
                <div class="match-meta fade-in delay-2">
                    <div class="match-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        30 мая 2026
                    </div>
                    <div class="match-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        19:00 МСК
                    </div>
                    <div class="match-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Пушкаш Арена, Будапешт
                    </div>
                </div>
                
                <div class="teams-row fade-in delay-3">
                    <div class="team-card">
                        <div class="team-emblem">
                            <img src="/uploads/teams/psg.png" alt="Paris Saint-Germain" onerror="this.style.display='none'">
                        </div>
                        <div class="team-name">Paris Saint-Germain</div>
                        <div class="team-country">Франция</div>
                    </div>
                    
                    <div class="vs-badge">
                        <?php if ($isLive || $isFinished): ?>
                        <div class="score-live active">
                            <span class="score-num"><?= (int)$psgScore ?></span>
                            <span class="score-divider"></span>
                            <span class="score-num"><?= (int)$arsenalScore ?></span>
                        </div>
                        <?php else: ?>
                        <span class="vs-text">VS</span>
                        <span class="vs-time">19:00</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="team-card">
                        <div class="team-emblem">
                            <img src="/uploads/teams/arsenal.png" alt="Arsenal FC" onerror="this.style.display='none'">
                        </div>
                        <div class="team-name">Arsenal</div>
                        <div class="team-country">Англия</div>
                    </div>
                </div>
                
                <div class="cta-buttons fade-in delay-4">
                    <?php if ($isLive && $hlsUrl): ?>
                    <a href="#stream" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Смотреть трансляцию
                    </a>
                    <?php else: ?>
                    <button class="btn btn-primary" onclick="openTeaser()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Смотреть тизер
                    </button>
                    <?php endif; ?>
                    <a href="#info" class="btn btn-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        О матче
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (!$isLive && !$isFinished): ?>
        <div class="countdown-strip">
            <div class="countdown-inner">
                <div class="countdown-label">До начала матча</div>
                <div class="countdown-timer" id="countdown">
                    <div class="countdown-block">
                        <div class="countdown-value" id="days">--</div>
                        <div class="countdown-unit">дней</div>
                    </div>
                    <div class="countdown-block">
                        <div class="countdown-value" id="hours">--</div>
                        <div class="countdown-unit">часов</div>
                    </div>
                    <div class="countdown-block">
                        <div class="countdown-value" id="minutes">--</div>
                        <div class="countdown-unit">минут</div>
                    </div>
                    <div class="countdown-block">
                        <div class="countdown-value" id="seconds">--</div>
                        <div class="countdown-unit">секунд</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </section>
    
    <main class="main-content">
        <div class="content-glow"></div>
        
        <?php if ($isLive && $hlsUrl): ?>
        <section class="stream-section active" id="stream">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Прямая трансляция</h2>
                </div>
                <div class="stream-player">
                    <div class="player-wrapper">
                        <video id="hlsPlayer" playsinline></video>
                        <div class="player-loader" id="playerLoader">
                            <div class="spinner"></div>
                            <div class="loader-text" id="loaderText">Подключение к трансляции...</div>
                        </div>
                        <div class="stream-live-badge">
                            <span class="live-dot"></span>
                            LIVE
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <section class="info-section" id="info">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">О финале</h2>
                </div>
                <div class="info-grid">
                    <div class="info-card">
                        <p><strong>Финал Лиги чемпионов УЕФА 2026 года</strong> — матч, который уже называют одним из самых ожидаемых европейских финалов последних лет. На легендарной «Пушкаш Арене» в Будапеште встретятся Paris Saint-Germain и Arsenal F.C. — две команды, прошедшие невероятно сложный путь ради главной футбольной ночи сезона.</p>
                        <p>Для парижан этот финал — шанс защитить титул и окончательно закрепить своё место среди элиты европейского футбола, а для лондонского клуба — возможность впервые в истории поднять над головой самый престижный клубный трофей Европы.</p>
                        <p>«Арсенал» под руководством <strong>Микеля Артеты</strong> провёл практически идеальный турнир, уверенно завершив общий этап без поражений. Лондонцы прошли через тяжёлые противостояния с «Байером», «Спортингом» и «Атлетико», каждый раз доказывая, что команда умеет не только красиво атаковать, но и терпеть под давлением.</p>
                        <p>Для «ПСЖ» этот финал имеет совершенно другой смысл. Парижский клуб уже успел войти в историю, выиграв турнир в прошлом сезоне, а теперь команда находится в шаге от того, чтобы стать лишь вторым клубом в современной эпохе Лиги чемпионов, сумевшим защитить титул после мадридского «Реала».</p>
                        <p>Впервые в истории решающий матч Лиги чемпионов пройдёт в Венгрии. Перед матчем состоится традиционное шоу открытия, хедлайнером церемонии станет группа <strong>The Killers</strong>.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <?php if (!empty($psgSquad) || !empty($arsenalSquad)): ?>
        <section class="squads-section" id="squads">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Составы команд</h2>
                </div>
                <div class="squads-grid">
                    <div class="squad-card">
                        <div class="squad-header psg">
                            <div class="squad-emblem">
                                <img src="/uploads/teams/psg.png" alt="PSG" onerror="this.style.display='none'">
                            </div>
                            <div class="squad-info">
                                <div class="squad-team">Paris Saint-Germain</div>
                                <?php if (!empty($uclMatch['psg_coach'])): ?>
                                <div class="squad-coach"><?= e($uclMatch['psg_coach']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="squad-body">
                            <?php foreach ($positionNames as $posKey => $posName): ?>
                                <?php if (!empty($psgGrouped[$posKey])): ?>
                                <div class="position-group">
                                    <div class="position-label"><?= $posName ?></div>
                                    <div class="players-list">
                                        <?php foreach ($psgGrouped[$posKey] as $player): ?>
                                        <div class="player-row">
                                            <div class="player-num"><?= (int)$player['number'] ?></div>
                                            <div class="player-nm"><?= e($player['name']) ?></div>
                                            <?php if ($player['is_starter']): ?>
                                            <span class="player-tag starter">Старт</span>
                                            <?php else: ?>
                                            <span class="player-tag sub">Запас</span>
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
                            <div class="squad-emblem">
                                <img src="/uploads/teams/arsenal.png" alt="Arsenal" onerror="this.style.display='none'">
                            </div>
                            <div class="squad-info">
                                <div class="squad-team">Arsenal</div>
                                <?php if (!empty($uclMatch['arsenal_coach'])): ?>
                                <div class="squad-coach"><?= e($uclMatch['arsenal_coach']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="squad-body">
                            <?php foreach ($positionNames as $posKey => $posName): ?>
                                <?php if (!empty($arsenalGrouped[$posKey])): ?>
                                <div class="position-group">
                                    <div class="position-label"><?= $posName ?></div>
                                    <div class="players-list">
                                        <?php foreach ($arsenalGrouped[$posKey] as $player): ?>
                                        <div class="player-row">
                                            <div class="player-num"><?= (int)$player['number'] ?></div>
                                            <div class="player-nm"><?= e($player['name']) ?></div>
                                            <?php if ($player['is_starter']): ?>
                                            <span class="player-tag starter">Старт</span>
                                            <?php else: ?>
                                            <span class="player-tag sub">Запас</span>
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
            </div>
        </section>
        <?php endif; ?>
        
        <?php if (!empty($timeline)): ?>
        <section class="timeline-section" id="timeline">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Ход матча</h2>
                </div>
                <div class="timeline-card">
                    <div class="timeline-list">
                        <?php foreach ($timeline as $event): ?>
                        <div class="timeline-item">
                            <div class="tl-minute"><?= (int)$event['minute'] ?>'</div>
                            <div class="tl-content">
                                <div class="tl-type"><?= e($event['event_type']) ?></div>
                                <div class="tl-desc"><?= e($event['description']) ?></div>
                                <?php if (!empty($event['team'])): ?>
                                <div class="tl-team"><?= $event['team'] === 'psg' ? 'ПСЖ' : 'Арсенал' ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php elseif ($isLive): ?>
        <section class="timeline-section" id="timeline">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Ход матча</h2>
                </div>
                <div class="timeline-card">
                    <div class="timeline-empty">События матча будут отображаться здесь</div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>
    
    <footer>
        <div class="footer-text">
            <a href="/">SPORTIFY TV</a> — Смотреть футбол онлайн бесплатно
        </div>
    </footer>
    
    <div class="teaser-modal" id="teaserModal">
        <div class="teaser-container">
            <button class="teaser-close" onclick="closeTeaser()">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="teaser-video">
                <video id="teaserVideo" controls playsinline>
                    <source src="/ucl-final-2026/tizer.mp4" type="video/mp4">
                </video>
            </div>
        </div>
    </div>
    
    <script>
    (function(){
        var matchTime = <?= $matchTs * 1000 ?>;
        var isLive = <?= $isLive ? 'true' : 'false' ?>;
        
        function updateCountdown(){
            var now = Date.now();
            var diff = matchTime - now;
            if(diff <= 0){
                document.getElementById('days').textContent = '0';
                document.getElementById('hours').textContent = '0';
                document.getElementById('minutes').textContent = '0';
                document.getElementById('seconds').textContent = '0';
                return;
            }
            var d = Math.floor(diff / 86400000);
            var h = Math.floor((diff % 86400000) / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var s = Math.floor((diff % 60000) / 1000);
            document.getElementById('days').textContent = d;
            document.getElementById('hours').textContent = h;
            document.getElementById('minutes').textContent = m;
            document.getElementById('seconds').textContent = s;
        }
        
        if(!isLive && document.getElementById('countdown')){
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
        
        <?php if ($isLive && $hlsUrl): ?>
        var video = document.getElementById('hlsPlayer');
        var loader = document.getElementById('playerLoader');
        var loaderText = document.getElementById('loaderText');
        var hlsUrl = <?= json_encode($hlsUrl) ?>;
        var hls = null;
        var retries = 0;
        var maxRetries = 6;
        
        function showLoader(msg){
            loader.classList.remove('hidden');
            if(msg) loaderText.textContent = msg;
        }
        function hideLoader(){
            loader.classList.add('hidden');
            video.controls = true;
        }
        
        function initStream(){
            if(video.canPlayType('application/vnd.apple.mpegurl')){
                video.src = hlsUrl;
                video.addEventListener('loadeddata', function(){hideLoader();video.play().catch(function(){});}, {once:true});
                video.addEventListener('error', handleError);
                return;
            }
            if(!window.Hls || !Hls.isSupported()){
                showLoader('Браузер не поддерживает HLS');
                return;
            }
            hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                maxBufferLength: 90,
                maxMaxBufferLength: 180,
                maxBufferSize: 180*1000*1000,
                maxBufferHole: 0.3,
                manifestLoadingTimeOut: 25000,
                manifestLoadingMaxRetry: 8,
                manifestLoadingRetryDelay: 500,
                levelLoadingTimeOut: 25000,
                levelLoadingMaxRetry: 6,
                fragLoadingTimeOut: 30000,
                fragLoadingMaxRetry: 6,
                startLevel: -1,
                abrEwmaDefaultEstimate: 800000,
                abrBandWidthFactor: 0.9,
                abrBandWidthUpFactor: 0.7,
                backBufferLength: 120,
                maxFragLookUpTolerance: 0.25,
                startFragPrefetch: true,
                testBandwidth: true,
                progressive: true
            });
            hls.loadSource(hlsUrl);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function(){
                showLoader('Загрузка...');
                retries = 0;
            });
            hls.on(Hls.Events.FRAG_BUFFERED, function(){
                hideLoader();
                video.play().catch(function(){});
            });
            hls.on(Hls.Events.ERROR, function(e, data){
                if(!data.fatal) return;
                if(data.type === Hls.ErrorTypes.NETWORK_ERROR){
                    if(retries < maxRetries){
                        retries++;
                        showLoader('Переподключение ('+retries+'/'+maxRetries+')...');
                        setTimeout(function(){hls.startLoad();}, 1500);
                    } else {
                        showLoader('Ошибка сети. Обновите страницу.');
                    }
                } else if(data.type === Hls.ErrorTypes.MEDIA_ERROR){
                    showLoader('Восстановление...');
                    hls.recoverMediaError();
                } else {
                    showLoader('Ошибка воспроизведения');
                }
            });
            video.addEventListener('waiting', function(){showLoader('Буферизация...');});
            video.addEventListener('playing', hideLoader);
            video.addEventListener('canplay', hideLoader);
        }
        
        function handleError(){
            if(retries < maxRetries){
                retries++;
                showLoader('Повтор ('+retries+')...');
                setTimeout(initStream, 2000);
            } else {
                showLoader('Не удалось подключиться');
            }
        }
        
        initStream();
        <?php endif; ?>
    })();
    
    function openTeaser(){
        document.getElementById('teaserModal').classList.add('active');
        document.getElementById('teaserVideo').play();
        document.body.style.overflow = 'hidden';
    }
    function closeTeaser(){
        document.getElementById('teaserModal').classList.remove('active');
        document.getElementById('teaserVideo').pause();
        document.body.style.overflow = '';
    }
    document.getElementById('teaserModal').addEventListener('click', function(e){
        if(e.target === this) closeTeaser();
    });
    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') closeTeaser();
    });
    </script>
</body>
</html>
