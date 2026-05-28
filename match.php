<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/StreamManager.php';

Security::setSecurityHeaders();

$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$matchId) { header('Location: /'); exit; }

$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) { header('Location: /'); exit; }

$streamEvent = null;
$hlsUrl = null;
$hlsTokenUrl = null;
try {
    $streamStmt = $db->prepare("SELECT * FROM streaming_events WHERE match_id = ? ORDER BY created_at DESC LIMIT 1");
    $streamStmt->execute([$matchId]);
    $streamEvent = $streamStmt->fetch(PDO::FETCH_ASSOC);
    if ($streamEvent) {
        $hlsTokenUrl = $streamEvent['hls_url'];
    }
} catch (Exception $e) {
    error_log("Error fetching stream event: " . $e->getMessage());
}

$status   = get_match_status($match);
$now      = time();
$startTs  = strtotime($match['start_time']);
$closeTs  = strtotime($match['auto_close_time']);

$broadcastStartTs = !empty($match['broadcast_start_time'])
    ? strtotime($match['broadcast_start_time'])
    : $startTs;

$isFinished      = $now >= $closeTs;
$isLive          = $now >= $startTs && !$isFinished;
$isUpcoming      = $now < $startTs;
$broadcastReady  = $now >= $broadcastStartTs;
$noDrawVote      = !empty($match['no_draw_vote']);
$matchStarted    = $now >= $startTs;

$parts = explode(' - ', $match['title'], 2);
$team1 = trim($parts[0] ?? 'Команда 1');
$team2 = trim($parts[1] ?? 'Команда 2');

$otherStmt = $db->prepare("SELECT * FROM matches WHERE id != ? AND auto_close_time > NOW() ORDER BY start_time ASC LIMIT 3");
$otherStmt->execute([$matchId]);
$otherMatches = $otherStmt->fetchAll(PDO::FETCH_ASSOC);

$shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/match.php?id=' . $matchId;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <?php echo generate_meta_tags(e($match['title']), 'Смотреть трансляцию матча ' . e($match['title']) . ' онлайн бесплатно'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?php echo asset('style.css'); ?>">
    
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
</head>
<body>

    <header>
        <div class="header-content">
            <a href="<?php echo url(); ?>" class="logo">SPORTIFY</a>
            <nav class="header-nav">
                <a href="https://t.me/+G7ItfFblqe85MmEy" target="_blank" rel="noopener noreferrer" class="header-nav-link">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
                    Канал
                </a>
                <a href="<?php echo url('help.php'); ?>" class="header-nav-link">Помощь</a>
            </nav>
        </div>
    </header>

    <!-- MATCH HERO -->
    <div class="match-page-hero">
        <div class="container" style="padding-top:22px;padding-bottom:22px;">
            <div style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                <a href="<?php echo url(); ?>" style="text-decoration:none;color:var(--color-primary);font-weight:500;">Трансляции</a>
                <span>›</span>
                <span><?php echo e($match['title']); ?></span>
            </div>
            <div class="match-hero-meta">
                <?php if ($isLive): ?>
                    <span class="live-badge">LIVE</span>
                <?php elseif ($isFinished): ?>
                    <span class="status-badge status-finished">Завершен</span>
                <?php else: ?>
                    <span class="status-badge status-upcoming">Скоро начнется</span>
                <?php endif; ?>
                <span class="match-datetime">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <?php echo format_match_time($match['start_time']); ?>
                </span>
            </div>
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <h1 class="match-hero-title"><?php echo e($match['title']); ?></h1>
                <!-- Share button -->
                <button class="share-btn" id="shareBtn" data-url="<?php echo e($shareUrl); ?>" data-title="<?php echo e($match['title']); ?>" title="Поделиться">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    <span>Поделиться</span>
                </button>
            </div>
        </div>
    </div>

    <main>
        <div class="container" style="padding-top:24px;">

            <!-- NOTIFICATION BAR -->
            <div class="match-notification <?php echo $isFinished ? 'finished' : ($isLive ? 'live' : 'info'); ?>" id="matchNotification">
                <span class="notif-icon">
                    <?php if ($isFinished): ?>
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    <?php elseif ($isLive): ?>
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/></svg>
                    <?php else: ?>
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                    <?php endif; ?>
                </span>
                <span>
                    <?php
                    if ($isFinished)        echo 'Матч завершен. Спасибо, что были с нами!';
                    elseif ($isLive)        echo 'Трансляция идёт прямо сейчас!';
                    elseif (!$broadcastReady) echo 'Трансляция откроется ' . (!empty($match['broadcast_start_time']) ? format_match_time($match['broadcast_start_time']) : 'за несколько минут до начала матча') . '.';
                    else echo 'Матч начнётся ' . format_match_time($match['start_time']) . '. Трансляция уже открыта.';
                    ?>
                </span>
                <?php if ($isLive && $streamEvent): ?>
                <span class="match-viewers-inline" id="matchViewersInline">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span id="matchViewerCount">—</span>
                </span>
                <?php endif; ?>
            </div>

            <!-- COUNTDOWN / LIVE TIMER -->
            <?php if (!$isFinished): ?>
            <div class="match-timer-bar" id="timerBar"
                 data-start-ts="<?php echo $startTs; ?>"
                 data-close-ts="<?php echo $closeTs; ?>"
                 data-broadcast-ts="<?php echo $broadcastStartTs; ?>">
                <?php if ($isLive): ?>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="live-badge" style="font-size:11px;padding:4px 12px;">LIVE</span>
                        <span class="timer-label">Матч идет</span>
                    </div>
                    <div class="timer-digits" id="matchTimer">
                        <div class="timer-block"><span class="timer-num" id="liveH">00</span><span class="timer-unit">ч</span></div>
                        <span class="timer-sep">:</span>
                        <div class="timer-block"><span class="timer-num" id="liveM">00</span><span class="timer-unit">мин</span></div>
                        <span class="timer-sep">:</span>
                        <div class="timer-block"><span class="timer-num" id="liveS">00</span><span class="timer-unit">сек</span></div>
                    </div>
                <?php else: ?>
                    <span class="timer-label">До начала матча</span>
                    <div class="timer-digits" id="countdown">
                        <div class="timer-block"><span class="timer-num" id="cdD">00</span><span class="timer-unit">дней</span></div>
                        <span class="timer-sep">:</span>
                        <div class="timer-block"><span class="timer-num" id="cdH">00</span><span class="timer-unit">ч</span></div>
                        <span class="timer-sep">:</span>
                        <div class="timer-block"><span class="timer-num" id="cdM">00</span><span class="timer-unit">мин</span></div>
                        <span class="timer-sep">:</span>
                        <div class="timer-block"><span class="timer-num" id="cdS">00</span><span class="timer-unit">сек</span></div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- PLAYER + SIDEBAR -->
            <section class="player-section">
                <div class="player-wrapper">

                    <div class="player-box">
                        <?php if ($isFinished): ?>
                            <div class="player-container">
                                <div class="status-message">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1.3" style="margin-bottom:14px;"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                                    <h2>Матч завершен</h2>
                                    <p>Следите за новыми трансляциями.</p>
                                </div>
                            </div>

                        <?php elseif (!$broadcastReady): ?>
                            <div class="player-container player-container--pending" style="position:relative;overflow:hidden;">
                                <?php if (!empty($match['cover_image'])): ?>
                                    <img src="/<?php echo e($match['cover_image']); ?>" alt="<?php echo e($match['title']); ?>"
                                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;filter:blur(6px) brightness(.38);">
                                <?php endif; ?>
                                <div class="status-message" style="position:relative;z-index:2;">
                                    <div class="pending-icon">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                                    </div>
                                    <h2>Трансляция скоро откроется</h2>
                                    <p id="broadcastCountdown">Загрузка...</p>
                                </div>
                            </div>

                        <?php elseif ($hlsTokenUrl): ?>
                            <div class="player-container" id="playerWrapper">
                                <video 
                                    id="player-hls" 
                                    playsinline 
                                    preload="auto"
                                    <?php if (!empty($match['cover_image'])): ?>
                                    poster="/<?php echo htmlspecialchars($match['cover_image']); ?>"
                                    <?php endif; ?>
                                    style="width:100%;height:100%;background:#000;">
                                </video>
                                <div class="player-loader" id="playerLoader" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(0,0,0,0.85);z-index:10;">
                                    <div style="width:44px;height:44px;border:3px solid rgba(255,255,255,0.15);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                                    <p id="playerStatusText" style="color:rgba(255,255,255,0.7);font-size:13px;margin-top:14px;">Подключение...</p>
                                </div>
                                <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
                                <script>
                                (function(){
                                    var video = document.getElementById('player-hls');
                                    var loader = document.getElementById('playerLoader');
                                    var statusText = document.getElementById('playerStatusText');
                                    var hlsUrl = <?php echo json_encode($hlsTokenUrl); ?>;
                                    var hls = null;
                                    var retryCount = 0;
                                    var maxRetries = 5;

                                    function showStatus(msg) { if (statusText) statusText.textContent = msg; }
                                    function hideLoader() { if (loader) loader.style.display = 'none'; video.controls = true; }
                                    function showLoader() { if (loader) loader.style.display = 'flex'; }

                                    function initPlayer() {
                                        if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                            video.src = hlsUrl;
                                            video.addEventListener('loadeddata', function() { hideLoader(); video.play().catch(function(){}); }, {once:true});
                                            video.addEventListener('error', handleError);
                                            return;
                                        }

                                        if (!window.Hls || !Hls.isSupported()) {
                                            showStatus('Браузер не поддерживает HLS');
                                            return;
                                        }

                                        hls = new Hls({
                                            enableWorker: true,
                                            lowLatencyMode: false,
                                            maxBufferLength: 90,
                                            maxMaxBufferLength: 180,
                                            maxBufferSize: 180 * 1000 * 1000,
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

                                        hls.on(Hls.Events.MANIFEST_PARSED, function(e, data) {
                                            showStatus('Загрузка...');
                                            retryCount = 0;
                                        });

                                        hls.on(Hls.Events.FRAG_BUFFERED, function() {
                                            hideLoader();
                                            video.play().catch(function(){});
                                        });

                                        hls.on(Hls.Events.ERROR, function(e, data) {
                                            if (!data.fatal) return;
                                            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                                                if (retryCount < maxRetries) {
                                                    retryCount++;
                                                    showStatus('Переподключение (' + retryCount + '/' + maxRetries + ')...');
                                                    showLoader();
                                                    setTimeout(function() { hls.startLoad(); }, 1500);
                                                } else {
                                                    showStatus('Ошибка сети. Обновите страницу.');
                                                }
                                            } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                                                showStatus('Восстановление...');
                                                hls.recoverMediaError();
                                            } else {
                                                showStatus('Ошибка воспроизведения');
                                            }
                                        });

                                        video.addEventListener('waiting', function() { showLoader(); showStatus('Буферизация...'); });
                                        video.addEventListener('playing', hideLoader);
                                        video.addEventListener('canplay', hideLoader);
                                    }

                                    function handleError() {
                                        if (retryCount < maxRetries) {
                                            retryCount++;
                                            showStatus('Повтор (' + retryCount + ')...');
                                            setTimeout(initPlayer, 2000);
                                        } else {
                                            showStatus('Не удалось загрузить');
                                        }
                                    }

                                    initPlayer();
                                })();
                                </script>
                            </div>

                        <?php else: ?>
                            <div class="player-container">
                                <div class="status-message">
                                    <div style="text-align:center;color:rgba(255,255,255,0.6);">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1.3" style="margin-bottom:14px;display:inline-block;"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" stroke="rgba(255,255,255,0.35)"/></svg>
                                        <h2>Sportify Stream Platform</h2>
                                        <p style="font-size:14px;margin-top:10px;">Powered by TESA</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="sidebar-col">

                        

                        <?php if ($isLive && $streamEvent): ?>
                        <div class="sidebar-panel" style="margin-bottom:16px;">
                            <div class="sidebar-panel-header">Зрители онлайн</div>
                            <div class="sidebar-panel-body" style="padding:14px 18px;">
                                <div class="viewers-live-block" id="viewersLiveBlock">
                                    <div class="viewers-live-num" id="viewersLiveNum">—</div>
                                    <div class="viewers-live-label">смотрят сейчас</div>
                                </div>
                                <div class="viewers-live-stats" id="viewersLiveStats"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="sidebar-panel">
                            <div class="sidebar-panel-header">Поддержка</div>
                            <div class="sidebar-panel-body">
                                <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:14px;line-height:1.6;">По вопросам и техническим проблемам пишите нам.</p>
                                <div class="contact-info-block">
                                    <h4>Написать нам</h4>
                                    <a href="mailto:<?php echo e(CONTACT_EMAIL); ?>"><?php echo e(CONTACT_EMAIL); ?></a>
                                </div>
                                <a href="https://t.me/+G7ItfFblqe85MmEy" target="_blank" rel="noopener noreferrer" class="tg-link-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/></svg>
                                    Наш Telegram-канал
                                </a>
                            </div>
                        </div>

                    </div>
                </div>



            </section>



            <!-- OTHER MATCHES -->
            <?php if (!empty($otherMatches)): ?>
            <section style="margin-bottom:48px;">
                <h2 class="section-title">Другие трансляции</h2>
                <div class="matches-grid">
                    <?php foreach ($otherMatches as $om):
                        $os = get_match_status($om);
                        $oLive = $os['type'] === 'live';
                    ?>
                    <a href="<?php echo url('match.php?id=' . $om['id']); ?>" class="match-card">
                        <?php if (!empty($om['cover_image'])): ?>
                            <img src="/<?php echo e($om['cover_image']); ?>" alt="<?php echo e($om['title']); ?>" class="match-card-image" loading="lazy">
                        <?php else: ?>
                            <div class="match-card-image-placeholder"></div>
                        <?php endif; ?>
                        <div class="match-header">
                            <span class="match-title"><?php echo e($om['title']); ?></span>
                            <?php if ($oLive): ?><span class="live-badge">LIVE</span><?php endif; ?>
                        </div>
                        <div class="match-content">
                            <div class="match-info">
                                <div class="info-row">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                    <?php echo format_match_time($om['start_time']); ?>
                                </div>
                                <div class="info-row">
                                    <span class="status-dot" style="background:<?php echo $oLive ? 'var(--color-live)' : 'var(--color-upcoming)'; ?>;"></span>
                                    <span class="status-label" style="color:<?php echo $oLive ? 'var(--color-live)' : 'var(--color-upcoming)'; ?>;"><?php echo e($os['label']); ?></span>
                                </div>
                            </div>
                            <button class="match-btn <?php echo $oLive ? '' : 'match-btn--secondary'; ?>">Перейти</button>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="<?php echo url(); ?>" class="footer-link">Все трансляции</a>
                <a href="<?php echo url('help.php'); ?>" class="footer-link">Помощь</a>
                <a href="https://t.me/+G7ItfFblqe85MmEy" target="_blank" rel="noopener noreferrer" class="footer-link">Telegram</a>
            </div>
            <p>&copy; <?php echo date('Y'); ?> SPORTIFY. Все права защищены.</p>
        </div>
    </footer>

    <script>
    (function() {
       
        var matchId = <?php echo $matchId; ?>;
        var lastStatusType = '<?php echo $status['type']; ?>';
        var broadcastWasReady = <?php echo $broadcastReady ? 'true' : 'false'; ?>;
        
        function updateMatchStatus() {
            fetch('/api/match-status.php?id=' + matchId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    
                    if (data.status_type !== lastStatusType || 
                        (!broadcastWasReady && data.broadcast_ready)) {
                        updateUI(data);
                        lastStatusType = data.status_type;
                        if (data.broadcast_ready) broadcastWasReady = true;
                    }
                })
                .catch(function(err) { 
                    console.error('Status update failed:', err); 
                });
        }

        
        
        function updateUI(data) {
           
            var notif = document.querySelector('.match-notification');
            if (notif) {
                notif.innerHTML = '';
                if (data.is_finished) {
                    notif.className = 'match-notification finished';
                    notif.innerHTML = '<span class="notif-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg></span><span>Матч завершен. Спасибо, что были с нами!</span>';
                } else if (data.is_live) {
                    notif.className = 'match-notification live';
                    notif.innerHTML = '<span class="notif-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/></svg></span><span>Матч идет прямо сейчас — трансляция в эфире!</span>';
                } else if (!data.broadcast_ready) {
                    notif.className = 'match-notification info';
                    notif.innerHTML = '<span class="notif-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg></span><span>Трансляция откроется за несколько минут до начала матча.</span>';
                } else {
                    notif.className = 'match-notification info';
                    notif.innerHTML = '<span class="notif-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg></span><span>Трансляция открыта. Матч начнется вскоре.</span>';
                }
            }
            
            
            if ((data.is_live || data.broadcast_ready) && data.has_iframe) {
                var playerBox = document.querySelector('.player-box');
                var playerContainer = document.querySelector('.player-container');
                if (playerContainer && playerContainer.classList.contains('player-container--pending')) {
                    playerContainer.classList.remove('player-container--pending');
                    if (data.iframe_code) {
                        playerContainer.innerHTML = data.iframe_code;
                    }
                }
            }
            
          
            if (data.match_started) {
                var predBtns = document.getElementById('predictionBtns');
                if (predBtns) predBtns.style.display = 'none';
            }
        }
        
       
        var statusInterval = setInterval(updateMatchStatus, 15000);
        
        // ---- Timer ----
        var timerBar = document.getElementById('timerBar');
        if (timerBar) {
            var startTs     = parseInt(timerBar.dataset.startTs, 10) * 1000;
            var closeTs     = parseInt(timerBar.dataset.closeTs, 10) * 1000;
            var broadcastTs = parseInt(timerBar.dataset.broadcastTs, 10) * 1000;
            var isLive      = <?php echo $isLive ? 'true' : 'false'; ?>;

            function pad(n) { return n < 10 ? '0' + n : '' + n; }

            function updateTimer() {
                var now = Date.now();
                if (isLive) {
                    var elapsed = Math.max(0, Math.floor((now - startTs) / 1000));
                    var h = Math.floor(elapsed / 3600), m = Math.floor((elapsed % 3600) / 60), s = elapsed % 60;
                    var hE = document.getElementById('liveH'), mE = document.getElementById('liveM'), sE = document.getElementById('liveS');
                    if (hE) hE.textContent = pad(h);
                    if (mE) mE.textContent = pad(m);
                    if (sE) sE.textContent = pad(s);
                    if (now >= closeTs) { 
                        clearInterval(timerInterval); 
                        updateMatchStatus();
                    }
                } else {
                    var diff = Math.max(0, Math.floor((startTs - now) / 1000));
                    var d = Math.floor(diff / 86400), h = Math.floor((diff % 86400) / 3600), m = Math.floor((diff % 3600) / 60), s = diff % 60;
                    var dE = document.getElementById('cdD'), hE = document.getElementById('cdH'), mE = document.getElementById('cdM'), sE = document.getElementById('cdS');
                    if (dE) dE.textContent = pad(d);
                    if (hE) hE.textContent = pad(h);
                    if (mE) mE.textContent = pad(m);
                    if (sE) sE.textContent = pad(s);
                    if (diff <= 0) { 
                        clearInterval(timerInterval); 
                       
                        isLive = true;
                        updateMatchStatus();
                    }
                }
            }
            updateTimer();
            var timerInterval = setInterval(updateTimer, 1000);
        }

       
        var bcEl = document.getElementById('broadcastCountdown');
        if (bcEl) {
            var broadcastTs2 = parseInt(document.getElementById('timerBar') ? document.getElementById('timerBar').dataset.broadcastTs : '0', 10) * 1000;
            function updateBC() {
                var diff = Math.max(0, Math.floor((broadcastTs2 - Date.now()) / 1000));
                if (diff <= 0) { 
                    clearInterval(bcInterval); 
                    updateMatchStatus();
                    return; 
                }
                var h = Math.floor(diff / 3600), m = Math.floor((diff % 3600) / 60), s = diff % 60;
                var parts = [];
                if (h > 0) parts.push(h + ' ч');
                parts.push((m < 10 ? '0' : '') + m + ' мин ' + (s < 10 ? '0' : '') + s + ' сек');
                bcEl.textContent = 'Откроется через ' + parts.join(' ');
            }
            updateBC();
            var bcInterval = setInterval(updateBC, 1000);
        }

       
        var shareBtn = document.getElementById('shareBtn');
        if (shareBtn) {
            shareBtn.addEventListener('click', function() {
                var url   = this.dataset.url;
                var title = this.dataset.title;
                if (navigator.share) {
                    navigator.share({ title: title, url: url }).catch(function(){});
                } else {
                    navigator.clipboard.writeText(url).then(function() {
                        var span = shareBtn.querySelector('span');
                        var orig = span.textContent;
                        span.textContent = 'Скопировано!';
                        shareBtn.classList.add('share-btn--copied');
                        setTimeout(function() {
                            span.textContent = orig;
                            shareBtn.classList.remove('share-btn--copied');
                        }, 2000);
                    }).catch(function() {
                        prompt('Скопируйте ссылку:', url);
                    });
                }
            });
        }

        
        var _chatScrollFixed = false;
        
        (function() {
            <?php if ($streamEvent): ?>
            var streamKey = '<?php echo htmlspecialchars($streamEvent['stream_key'], ENT_QUOTES); ?>';
            var viewerSession = {
                uuid: getOrCreateViewerUUID(),
                heartbeatInterval: null,
                lastHeartbeat: null
            };

           
            function getOrCreateViewerUUID() {
                const KEY = 'viewer_uuid_' + streamKey;
                let uuid = sessionStorage.getItem(KEY);
                if (!uuid) {
                    uuid = 'vu_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    sessionStorage.setItem(KEY, uuid);
                }
                return uuid;
            }

           
            function registerViewer() {
                fetch('/api/viewer-stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=register&stream_key=' + encodeURIComponent(streamKey) + 
                          '&viewer_uuid=' + encodeURIComponent(viewerSession.uuid)
                })
                .then(r => r.json())
                .catch(err => console.warn('Viewer registration failed:', err));
            }

           
            function sendHeartbeat() {
                fetch('/api/viewer-stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=heartbeat&stream_key=' + encodeURIComponent(streamKey) + 
                          '&viewer_uuid=' + encodeURIComponent(viewerSession.uuid)
                })
                .catch(err => console.warn('Heartbeat failed:', err));
            }

            
            function markViewerLeft() {
                fetch('/api/viewer-stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=leave&stream_key=' + encodeURIComponent(streamKey) + 
                          '&viewer_uuid=' + encodeURIComponent(viewerSession.uuid),
                    keepalive: true  // Отправить даже если закрывается вкладка
                })
                .catch(err => console.warn('Mark left failed:', err));
            }

           
            function fetchStats() {
                fetch('/api/viewer-stats.php?action=stats&stream_key=' + encodeURIComponent(streamKey))
                    .then(r => r.json())
                    .then(data => {
                        updateStatsDisplay(data);
                    })
                    .catch(err => console.warn('Stats fetch failed:', err));
            }

           
            function updateStatsDisplay(data) {
               
                var badge = document.getElementById('viewersOnlineBadge');
                if (badge) {
                    badge.textContent = data.online_now + (data.online_now === 1 ? ' зритель' : ' зрителей');
                }

               
                var statsPanel = document.getElementById('streamStatsPanel');
                if (statsPanel) {
                    statsPanel.innerHTML = `
                        <div class="stream-stats">
                            <div class="stat-item">
                                <span class="stat-label">Смотрят сейчас</span>
                                <span class="stat-value">${data.online_now}</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Всего зрителей</span>
                                <span class="stat-value">${data.total_unique}</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Максимум одновременно</span>
                                <span class="stat-value">${data.peak_online}</span>
                            </div>
                        </div>
                    `;
                }
            }

           
            registerViewer();
            fetchStats();  

           
            viewerSession.heartbeatInterval = setInterval(sendHeartbeat, 60000);

            
            var statsInterval = setInterval(fetchStats, 120000);

           
            window.addEventListener('beforeunload', markViewerLeft);
            window.addEventListener('unload', markViewerLeft);

           
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    
                    sendHeartbeat();
                }
            });

            <?php endif; ?>
        })();

    })();
    </script>



</body>
</html>
