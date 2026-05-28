<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/functions.php';

Security::setSecurityHeaders();

$db = Database::getInstance();
$conn = $db->getConnection();

$banners = [];
try {
    $bannersQuery = $conn->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC LIMIT 3");
    $banners = $bannersQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Banners query error: " . $e->getMessage());
}

$matches = [];
try {
    $currentTime = date('Y-m-d H:i:s');
    $matchesQuery = $conn->prepare("SELECT * FROM matches WHERE auto_close_time > ? ORDER BY start_time ASC");
    $matchesQuery->execute([$currentTime]);
    $matches = $matchesQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Matches query error: " . $e->getMessage());
}

$liveMatches     = [];
$upcomingMatches = [];
foreach ($matches as $m) {
    $s = get_match_status($m);
    if ($s['type'] === 'live') $liveMatches[] = $m;
    else $upcomingMatches[] = $m;
}
$sortedMatches = array_merge($liveMatches, $upcomingMatches);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <?php echo generate_meta_tags(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('style.css'); ?>">
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

    <main>

        <!-- ===== UCL FINAL BANNER ===== -->
        <div class="container">
            <a href="/ucl-final/" class="ucl-promo-banner">
                <img src="/uploads/final-b.png" alt="Финал Лиги чемпионов 2026 ПСЖ - Арсенал" class="ucl-promo-img">
                <div class="ucl-promo-overlay"></div>
                <div class="ucl-promo-content">
                    <span class="ucl-promo-badge">Финал Лиги чемпионов</span>
                    <span class="ucl-promo-title">ПСЖ — Арсенал</span>
                    <span class="ucl-promo-meta">30 мая, 19:00 МСК</span>
                </div>
            </a>
        </div>
        <style>
        .ucl-promo-banner{display:block;position:relative;border-radius:16px;overflow:hidden;margin:24px auto;max-width:1200px;aspect-ratio:21/9;text-decoration:none}
        .ucl-promo-img{width:100%;height:100%;object-fit:cover;transition:transform 0.4s}
        .ucl-promo-banner:hover .ucl-promo-img{transform:scale(1.03)}
        .ucl-promo-overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(10,15,30,0.85) 0%,rgba(10,15,30,0.4) 50%,transparent 100%)}
        .ucl-promo-content{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;padding:clamp(20px,5vw,48px);gap:8px}
        .ucl-promo-badge{display:inline-flex;width:fit-content;background:linear-gradient(90deg,#c9a227,#e8c547);color:#0a0f1e;padding:6px 14px;border-radius:6px;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase}
        .ucl-promo-title{font-family:'Oswald',system-ui,sans-serif;font-size:clamp(1.5rem,4vw,2.8rem);font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:1px}
        .ucl-promo-meta{font-size:clamp(12px,2vw,15px);color:rgba(255,255,255,0.7)}
        @media(max-width:600px){.ucl-promo-banner{aspect-ratio:16/9;margin:16px}}
        </style>

        <!-- ===== BANNERS ===== -->
        <?php if (!empty($banners)): ?>
        <div class="container">
            <section class="banner-section">
                <?php foreach ($banners as $banner): ?>
                <?php
                    $hasImg  = !empty($banner['image_path']);
                    $imgSrc  = $hasImg ? '/' . e($banner['image_path']) : '';
                    $hasLink = !empty($banner['button_link']);
                    $wrapTag = $hasLink ? 'a' : 'div';
                    $wrapAttr = $hasLink ? 'href="' . e($banner['button_link']) . '"' : '';
                ?>
                <<?php echo $wrapTag; ?> class="banner-card" <?php echo $wrapAttr; ?>>
                    <?php if ($hasImg): ?>
                        <img src="<?php echo $imgSrc; ?>" alt="<?php echo e($banner['title']); ?>" class="banner-bg-img">
                    <?php endif; ?>
                    <div class="banner-overlay"></div>
                    <div class="banner-body">
                        <div class="banner-text">
                            <h2 class="banner-title"><?php echo e($banner['title']); ?></h2>
                            <?php if (!empty($banner['description'])): ?>
                                <p class="banner-desc"><?php echo e($banner['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($banner['button_text'])): ?>
                            <span class="banner-btn"><?php echo e($banner['button_text']); ?></span>
                        <?php endif; ?>
                    </div>
                </<?php echo $wrapTag; ?>>
                <?php endforeach; ?>
            </section>
        </div>
        <?php endif; ?>

        <!-- ===== MATCHES ===== -->
        <div class="container">

            <?php if (!empty($liveMatches)): ?>
            <div class="live-now-row">
                <span class="live-badge">LIVE</span>
                <span class="live-now-label">Сейчас в эфире</span>
            </div>
            <?php endif; ?>

            <h2 class="section-title" style="margin-top:<?php echo empty($liveMatches) ? '36px' : '10px'; ?>;">Трансляции</h2>

            <?php if (!empty($sortedMatches)): ?>
            <div class="matches-grid">
                <?php foreach ($sortedMatches as $match):
                    $status   = get_match_status($match);
                    $isLive   = $status['type'] === 'live';
                    $isUp     = $status['type'] === 'upcoming';
                    $startTs  = strtotime($match['start_time']);
                    $diffSec  = $startTs - time();
                ?>
                <a href="<?php echo url('match.php?id=' . $match['id']); ?>" class="match-card">

                    <?php if (!empty($match['cover_image'])): ?>
                        <img src="/<?php echo e($match['cover_image']); ?>"
                             alt="<?php echo e($match['title']); ?>"
                             class="match-card-image" loading="lazy">
                    <?php else: ?>
                        <div class="match-card-image-placeholder"></div>
                    <?php endif; ?>

                   
                    <div class="match-header">
                        <span class="match-title"><?php echo e($match['title']); ?></span>
                        <?php if ($isLive): ?>
                            <span class="live-badge">LIVE</span>
                        <?php elseif ($isUp && $diffSec > 0 && $diffSec < 86400): ?>
                            <?php $hL = floor($diffSec/3600); $mL = floor(($diffSec%3600)/60); ?>
                            <span class="badge-soon">
                                <?php if ($hL > 0): ?><?php echo $hL; ?>ч <?php endif; ?><?php echo $mL; ?>м
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="match-content">
                        <div class="match-info">
                            <div class="info-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                <?php echo format_match_time($match['start_time']); ?>
                            </div>
                            <div class="info-row">
                                <span class="status-dot" style="background:<?php echo $isLive ? 'var(--color-live)' : ($isUp ? 'var(--color-upcoming)' : 'var(--color-finished)'); ?>;"></span>
                                <span class="status-label" style="color:<?php echo $isLive ? 'var(--color-live)' : ($isUp ? 'var(--color-upcoming)' : 'var(--color-finished)'); ?>;">
                                    <?php echo e($status['label']); ?>
                                </span>
                            </div>
                        </div>
                        <button class="match-btn <?php echo $isLive ? '' : 'match-btn--secondary'; ?>">
                            <?php echo $isLive ? 'Смотреть' : 'Перейти'; ?>
                        </button>
                    </div>

                    

                </a>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;margin-bottom:16px;"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l2.5 2.5"/></svg>
                <p>Нет активных трансляций</p>
                <p style="font-size:13px;color:var(--color-text-muted);margin-top:6px;">Следите за анонсами в Telegram-канале</p>
            </div>
            <?php endif; ?>

           
            <div class="contact-section">
                <h3>Остались вопросы?</h3>
                <p>Напишите нам — поможем разобраться</p>
                <a href="mailto:<?php echo CONTACT_EMAIL; ?>" class="btn btn-primary"><?php echo CONTACT_EMAIL; ?></a>
            </div>

        </div>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="<?php echo url('help.php'); ?>" class="footer-link">Помощь</a>
                <a href="https://t.me/+G7ItfFblqe85MmEy" target="_blank" rel="noopener noreferrer" class="footer-link">Telegram</a>
                <a href="mailto:<?php echo CONTACT_EMAIL; ?>" class="footer-link"><?php echo CONTACT_EMAIL; ?></a>
            </div>
            <p>&copy; <?php echo date('Y'); ?> SPORTIFY. Все права защищены.</p>
        </div>
    </footer>

</body>
</html>
