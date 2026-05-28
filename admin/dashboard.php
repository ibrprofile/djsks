<?php
/**
 * Админ-панель: управление матчами, баннерами и трансляциями
 */

session_start();
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';
require_once '../includes/StreamManager.php';
require_once '../includes/ViewerTracker.php';

require_admin();

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {

            case 'add_banner':
                try {
                    $image_path = null;
                    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                        $image_path = upload_file($_FILES['banner_image'], '../uploads/banners/');
                        // strip leading '../' so path is relative to project root
                        $image_path = ltrim($image_path, './');
                        $image_path = preg_replace('#^\.\./#', '', $image_path);
                    }
                    $stmt = $db->prepare("INSERT INTO banners (title, description, button_text, button_link, image_path, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['button_text'],
                        $_POST['button_link'],
                        $image_path,
                        (int)$_POST['sort_order']
                    ]);
                    $_SESSION['success'] = 'Баннер успешно добавлен';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
                }
                header('Location: dashboard.php?tab=banners');
                exit;

            case 'delete_banner':
                $stmt = $db->prepare("SELECT image_path FROM banners WHERE id = ?");
                $stmt->execute([$_POST['banner_id']]);
                $banner = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($banner && $banner['image_path']) delete_file($banner['image_path']);
                $stmt = $db->prepare("DELETE FROM banners WHERE id = ?");
                $stmt->execute([$_POST['banner_id']]);
                $_SESSION['success'] = 'Баннер удален';
                header('Location: dashboard.php?tab=banners');
                exit;

            case 'add_match':
                try {
                    $cover_image = null;
                    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                        $raw = upload_file($_FILES['cover_image'], '../uploads/match_photo/');
                        $cover_image = preg_replace('#^\.\./#', '', $raw);
                    }

                    // broadcast_start_time is optional — defaults to start_time if left blank
                    $broadcastStart = !empty($_POST['broadcast_start_time'])
                        ? $_POST['broadcast_start_time']
                        : $_POST['start_time'];

                    $noDrawVote = isset($_POST['no_draw_vote']) ? 1 : 0;
                    $stmt = $db->prepare("
                        INSERT INTO matches
                            (title, start_time, broadcast_start_time, auto_close_time, cover_image, no_draw_vote)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['start_time'],
                        $broadcastStart,
                        $_POST['auto_close_time'],
                        $cover_image,
                        $noDrawVote
                    ]);
                    $_SESSION['success'] = 'Матч успешно добавлен';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
                }
                header('Location: dashboard.php?tab=matches');
                exit;

            case 'edit_match':
                try {
                    $cover_image = null;
                    // Only update cover if a new file is uploaded
                    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                        $raw = upload_file($_FILES['cover_image'], '../uploads/match_photo/');
                        $cover_image = preg_replace('#^\.\./#', '', $raw);
                    }

                    $broadcastStart = !empty($_POST['broadcast_start_time'])
                        ? $_POST['broadcast_start_time']
                        : $_POST['start_time'];

                    $noDrawVote = isset($_POST['no_draw_vote']) ? 1 : 0;
                    if ($cover_image) {
                        $stmt = $db->prepare("
                            UPDATE matches
                            SET title=?, start_time=?, broadcast_start_time=?, auto_close_time=?, cover_image=?, no_draw_vote=?
                            WHERE id=?
                        ");
                        $stmt->execute([
                            $_POST['title'],
                            $_POST['start_time'],
                            $broadcastStart,
                            $_POST['auto_close_time'],
                            $cover_image,
                            $noDrawVote,
                            (int)$_POST['match_id']
                        ]);
                    } else {
                        $stmt = $db->prepare("
                            UPDATE matches
                            SET title=?, start_time=?, broadcast_start_time=?, auto_close_time=?, no_draw_vote=?
                            WHERE id=?
                        ");
                        $stmt->execute([
                            $_POST['title'],
                            $_POST['start_time'],
                            $broadcastStart,
                            $_POST['auto_close_time'],
                            $noDrawVote,
                            (int)$_POST['match_id']
                        ]);
                    }
                    $_SESSION['success'] = 'Матч обновлен';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
                }
                header('Location: dashboard.php?tab=matches');
                exit;

            case 'delete_match':
                $stmt = $db->prepare("SELECT cover_image FROM matches WHERE id = ?");
                $stmt->execute([$_POST['match_id']]);
                $match = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($match && $match['cover_image']) delete_file($match['cover_image']);
                $stmt = $db->prepare("DELETE FROM matches WHERE id = ?");
                $stmt->execute([$_POST['match_id']]);
                $_SESSION['success'] = 'Матч удален';
                header('Location: dashboard.php?tab=matches');
                exit;

            case 'create_stream':
                try {
                    $stream_manager = new StreamManager();
                    $event = $stream_manager->createEvent(
                        $_POST['stream_name'],
                        !empty($_POST['match_id']) ? (int)$_POST['match_id'] : null,
                        $_SESSION['admin_user'] ?? 'admin'
                    );
                    if ($event) {
                        $_SESSION['success'] = 'Трансляция создана. RTMP URL и ключ скопированы выше.';
                        $_SESSION['new_stream'] = $event;
                    } else {
                        $_SESSION['error'] = 'Ошибка при создании трансляции';
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
                }
                header('Location: dashboard.php?tab=streaming');
                exit;

            case 'edit_stream':
                try {
                    $stmt = $db->prepare("UPDATE streaming_events SET name=?, match_id=?, rtmp_url=? WHERE id=?");
                    $stmt->execute([
                        $_POST['stream_name'],
                        !empty($_POST['match_id']) ? (int)$_POST['match_id'] : null,
                        !empty($_POST['rtmp_url']) ? $_POST['rtmp_url'] : 'potok.sportifymn.com/live',
                        (int)$_POST['stream_id']
                    ]);
                    $_SESSION['success'] = 'Трансляция обновлена';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
                }
                header('Location: dashboard.php?tab=streaming');
                exit;

            case 'update_stream_status':
                try {
                    $stream_manager = new StreamManager();
                    $newStatus = in_array($_POST['status'], ['pending','live','finished']) ? $_POST['status'] : null;
                    if ($newStatus) {
                        $stream_manager->updateEventStatus((int)$_POST['stream_id'], $newStatus);
                        $_SESSION['success'] = 'Статус трансляции обновлён';
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
                }
                header('Location: dashboard.php?tab=streaming');
                exit;

            case 'delete_stream':
                try {
                    $stream_manager = new StreamManager();
                    if ($stream_manager->deleteEvent((int)$_POST['stream_id'])) {
                        $_SESSION['success'] = 'Трансляция удалена';
                    } else {
                        $_SESSION['error'] = 'Ошибка при удалении';
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
                }
                header('Location: dashboard.php?tab=streaming');
                exit;
        }
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'matches';
$editMatchId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editStreamId = isset($_GET['edit_stream']) ? (int)$_GET['edit_stream'] : 0;

$banners = [];
$matches = [];
$streaming_events = [];

try {
    $banners = $db->query("SELECT * FROM banners ORDER BY sort_order ASC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Banners query error: " . $e->getMessage()); }

try {
    $matches = $db->query("SELECT * FROM matches ORDER BY start_time DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Matches query error: " . $e->getMessage()); }

try {
    $streaming_events = $db->query("SELECT * FROM streaming_events ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Streaming events query error: " . $e->getMessage()); }

// ОПТИМИЗАЦИЯ: Создаём индекс-массивы для O(1) поиска вместо O(n)
$matchesById = [];
$eventsById = [];
foreach ($matches as $m) {
    $matchesById[$m['id']] = $m;
}
foreach ($streaming_events as $s) {
    $eventsById[$s['id']] = $s;
}

// Find match for edit mode
$editMatch = isset($matchesById[$editMatchId]) ? $matchesById[$editMatchId] : null;

// Find stream for edit mode
$editStream = isset($eventsById[$editStreamId]) ? $eventsById[$editStreamId] : null;

// Helper: format datetime for <input type="datetime-local">
function dtLocal($val) {
    if (empty($val)) return '';
    return date('Y-m-d\TH:i', strtotime($val));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — SPORTIFY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

    <header>
        <div class="header-content">
            <a href="/" class="logo">SPORTIFY Admin</a>
            <a href="/admin/logout.php" class="btn btn-danger" style="padding:8px 18px;font-size:13px;">Выйти</a>
        </div>
    </header>

    <div class="admin-container">

        <!-- Alerts -->
        <?php if (empty($banners) && empty($matches)): ?>
        <div class="alert alert-warning">
            <strong>Внимание!</strong> Таблицы базы данных не найдены. Выполните SQL-скрипт <code>/scripts/setup-database.sql</code> для создания таблиц.
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="admin-tabs">
            <button class="tab-btn <?php echo $active_tab === 'matches' ? 'active' : ''; ?>"
                    onclick="location.href='?tab=matches'">Матчи</button>
            <button class="tab-btn <?php echo $active_tab === 'streaming' ? 'active' : ''; ?>"
                    onclick="location.href='?tab=streaming'">Трансляции</button>
            <button class="tab-btn <?php echo $active_tab === 'stats' ? 'active' : ''; ?>"
                    onclick="location.href='?tab=stats'">Статистика</button>
            <button class="tab-btn <?php echo $active_tab === 'banners' ? 'active' : ''; ?>"
                    onclick="location.href='?tab=banners'">Баннеры</button>
            <button class="tab-btn <?php echo $active_tab === 'chat' ? 'active' : ''; ?>"
                    onclick="location.href='?tab=chat'">Чат</button>
        </div>

        <!-- ===================== MATCHES TAB ===================== -->
        <?php if ($active_tab === 'matches'): ?>
        <section>
            <h2 class="section-title">Управление матчами</h2>

            <!-- Add / Edit form -->
            <div class="admin-card">
                <h3><?php echo $editMatch ? 'Редактировать матч #' . $editMatch['id'] : 'Добавить новый матч'; ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $editMatch ? 'edit_match' : 'add_match'; ?>">
                    <?php if ($editMatch): ?>
                        <input type="hidden" name="match_id" value="<?php echo $editMatch['id']; ?>">
                    <?php endif; ?>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Название матча *</label>
                            <input type="text" name="title" class="form-control"
                                   placeholder="Реал Мадрид - Манчестер Сити"
                                   value="<?php echo $editMatch ? htmlspecialchars($editMatch['title']) : ''; ?>"
                                   required>
                            <small>Формат: Команда 1 - Команда 2 (через дефис с пробелами)</small>
                        </div>

                        <div class="form-group">
                            <label>Дата и время начала матча *
                                <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(реальное начало игры)</span>
                            </label>
                            <input type="datetime-local" name="start_time" class="form-control"
                                   value="<?php echo $editMatch ? dtLocal($editMatch['start_time']) : ''; ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Дата и время открытия трансляции
                                <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(когда плеер станет ��оступен)</span>
                            </label>
                            <input type="datetime-local" name="broadcast_start_time" class="form-control"
                                   value="<?php echo $editMatch && !empty($editMatch['broadcast_start_time']) ? dtLocal($editMatch['broadcast_start_time']) : ''; ?>">
                            <small>Оставьте пустым — трансляция откроется одновременно с началом матча</small>
                        </div>

                        <div class="form-group">
                            <label>Автозакрытие страницы матча *
                                <span style="font-size:11px;font-weight:400;color:var(--color-text-muted);">(когда страница исчезнет)</span>
                            </label>
                            <input type="datetime-local" name="auto_close_time" class="form-control"
                                   value="<?php echo $editMatch ? dtLocal($editMatch['auto_close_time']) : ''; ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Обложка матча<?php echo $editMatch && !empty($editMatch['cover_image']) ? ' (новая заменит старую)' : ''; ?></label>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                            <?php if ($editMatch && !empty($editMatch['cover_image'])): ?>
                                <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                                    <img src="/<?php echo htmlspecialchars($editMatch['cover_image']); ?>"
                                         style="height:48px;width:auto;border-radius:4px;border:1px solid var(--color-border);"
                                         alt="">
                                    <span style="font-size:12px;color:var(--color-text-muted);">Текущая обложка</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group" style="grid-column:1/-1;">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;">
                                <input type="checkbox" name="no_draw_vote" value="1"
                                       <?php echo ($editMatch && !empty($editMatch['no_draw_vote'])) ? 'checked' : ''; ?>
                                       style="width:16px;height:16px;cursor:pointer;accent-color:var(--color-primary);">
                                Убрать кнопку «Ничья» в голосовании
                            </label>
                            <small>Включите для матчей на вылет, где ничья невозможна</small>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:8px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editMatch ? 'Сохранить изменения' : 'Добавить матч'; ?>
                        </button>
                        <?php if ($editMatch): ?>
                            <a href="?tab=matches" class="btn" style="background:var(--color-surface-2);color:var(--color-text-muted);border:1px solid var(--color-border);">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Matches table -->
            <?php if (!empty($matches)): ?>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Трансляция с</th>
                            <th>Начало матча</th>
                            <th>Закрытие</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matches as $match): ?>
                        <?php $st = get_match_status($match); ?>
                        <tr>
                            <td style="color:var(--color-text-muted);font-size:13px;">#<?php echo $match['id']; ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($match['title']); ?></td>
                            <td style="font-size:13px;color:var(--color-text-muted);">
                                <?php echo !empty($match['broadcast_start_time']) ? format_match_time($match['broadcast_start_time']) : '—'; ?>
                            </td>
                            <td style="font-size:13px;"><?php echo format_match_time($match['start_time']); ?></td>
                            <td style="font-size:13px;color:var(--color-text-muted);"><?php echo format_match_time($match['auto_close_time']); ?></td>
                            <td>
                                <span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
                                    background:<?php echo $st['type']==='live' ? 'rgba(239,68,68,.12)' : ($st['type']==='upcoming' ? 'rgba(245,158,11,.12)' : 'rgba(107,114,128,.1)'); ?>;
                                    color:<?php echo $st['type']==='live' ? '#dc2626' : ($st['type']==='upcoming' ? '#b45309' : '#6b7280'); ?>;">
                                    <?php echo htmlspecialchars($st['label']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <a href="/match.php?id=<?php echo $match['id']; ?>" target="_blank" class="btn btn-primary"
                                       style="padding:6px 12px;font-size:12px;">Открыть</a>
                                    <a href="?tab=matches&edit=<?php echo $match['id']; ?>" class="btn"
                                       style="padding:6px 12px;font-size:12px;background:var(--color-surface-2);color:var(--color-text-muted);border:1px solid var(--color-border);">
                                        Изменить
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить матч?');">
                                        <input type="hidden" name="action" value="delete_match">
                                        <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding:6px 12px;font-size:12px;">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="margin-top:16px;">
                <p>Матчей ещё нет. Добавьте первый матч выше.</p>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- ===================== STREAMING TAB ===================== -->
        <?php if ($active_tab === 'streaming'): ?>
        <section>
            <h2 class="section-title">Управление трансляциями</h2>

            <!-- Создание / Редактирование -->
            <div class="admin-card">
                <h3><?php echo $editStream ? 'Редактировать трансляцию #' . $editStream['id'] : 'Создать новое событие трансляции'; ?></h3>

                <?php if (isset($_SESSION['new_stream']) && $_SESSION['new_stream']): ?>
                <?php $nsData = $_SESSION['new_stream']; unset($_SESSION['new_stream']); ?>
                <div class="stream-info-box" style="margin-bottom:24px;">
                    <div style="font-weight:700;margin-bottom:14px;font-size:15px;">Данные для подключения OBS</div>
                    <div class="copy-field">
                        <label>RTMP-сервер (вставьте в поле "Server" в OBS)</label>
                        <div class="copy-input-group">
                            <input type="text" value="rtmp://potok.sportifymn.com/live" readonly class="form-control" id="rtmpServer">
                            <button type="button" class="btn btn-primary" onclick="copyField('rtmpServer',this)" style="padding:8px 16px;">Копировать</button>
                        </div>
                    </div>
                    <div class="copy-field">
                        <label>Ключ потока (вставьте в поле "Stream Key" в OBS)</label>
                        <div class="copy-input-group">
                            <input type="text" value="<?php echo htmlspecialchars($nsData['stream_key']); ?>" readonly class="form-control" id="newStreamKey">
                            <button type="button" class="btn btn-primary" onclick="copyField('newStreamKey',this)" style="padding:8px 16px;">Копировать</button>
                        </div>
                    </div>
                    <div class="copy-field">
                        <label>HLS URL (ссылка на поток для плеера)</label>
                        <div class="copy-input-group">
                            <input type="text" value="<?php echo htmlspecialchars($nsData['hls_url']); ?>" readonly class="form-control" id="newHlsUrl">
                            <button type="button" class="btn btn-primary" onclick="copyField('newHlsUrl',this)" style="padding:8px 16px;">Копировать</button>
                        </div>
                    </div>
                    <div style="background:var(--color-surface-2);border-radius:8px;padding:14px;margin-top:14px;font-size:12px;color:var(--color-text-muted);line-height:1.7;">
                        <strong style="color:var(--color-text);">Инструкция OBS Studio:</strong><br>
                        1. Откройте OBS Studio → Settings → Stream<br>
                        2. Service: <strong>Custom...</strong><br>
                        3. Server: <strong>rtmp://potok.sportifymn.com/live</strong><br>
                        4. Stream Key: вставьте ключ выше<br>
                        5. Нажмите <strong>Start Streaming</strong>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $editStream ? 'edit_stream' : 'create_stream'; ?>">
                    <?php if ($editStream): ?>
                        <input type="hidden" name="stream_id" value="<?php echo $editStream['id']; ?>">
                    <?php endif; ?>
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;">
                        <div class="form-group">
                            <label>Название события *</label>
                            <input type="text" name="stream_name" class="form-control"
                                   value="<?php echo $editStream ? htmlspecialchars($editStream['name']) : ''; ?>"
                                   placeholder="напр. Финал Champions League" required>
                        </div>
                        <div class="form-group">
                            <label>Связать с матчем</label>
                            <select name="match_id" class="form-control">
                                <option value="">— не связывать —</option>
                                <?php foreach ($matches as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo ($editStream && $editStream['match_id'] == $m['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($editStream): ?>
                        <div class="form-group">
                            <label>RTMP URL сервера</label>
                            <input type="text" name="rtmp_url" class="form-control"
                                   value="<?php echo htmlspecialchars($editStream['rtmp_url'] ?? 'rtmp://potok.sportifymn.com/live'); ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:12px;margin-top:8px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editStream ? 'Сохранить изменения' : 'Создать трансляцию'; ?>
                        </button>
                        <?php if ($editStream): ?>
                        <a href="?tab=streaming" class="btn" style="background:var(--color-surface-2);color:var(--color-text-muted);border:1px solid var(--color-border);">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Таблица трансляций -->
            <?php if (!empty($streaming_events)): ?>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Матч</th>
                            <th>Статус</th>
                            <th>Stream Key / HLS URL</th>
                            <th>Создана</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($streaming_events as $event): ?>
                        <?php
                            // ОПТИМИЗАЦИЯ: используем индекс-массив вместо loop поиска
                            $evMatch = isset($matchesById[$event['match_id']]) ? $matchesById[$event['match_id']] : null;
                            $stColor = ['live'=>'#dc2626','pending'=>'#b45309','finished'=>'#6b7280'][$event['status']] ?? '#6b7280';
                            $stBg    = ['live'=>'rgba(239,68,68,.12)','pending'=>'rgba(245,158,11,.12)','finished'=>'rgba(107,114,128,.1)'][$event['status']] ?? 'rgba(107,114,128,.1)';
                        ?>
                        <tr>
                            <td style="color:var(--color-text-muted);font-size:13px;">#<?php echo $event['id']; ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($event['name']); ?></td>
                            <td style="font-size:13px;">
                                <?php echo $evMatch ? '<a href="/match.php?id=' . $evMatch['id'] . '" target="_blank" style="color:var(--color-primary);text-decoration:none;">' . htmlspecialchars($evMatch['title']) . '</a>' : '<span style="color:var(--color-text-muted);">—</span>'; ?>
                            </td>
                            <td>
                                <span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;
                                    letter-spacing:.5px;text-transform:uppercase;background:<?php echo $stBg; ?>;color:<?php echo $stColor; ?>;">
                                    <?php echo $event['status'] === 'live' ? 'LIVE' : ($event['status'] === 'pending' ? 'Ожидание' : 'Завершена'); ?>
                                </span>
                            </td>
                            <td style="font-size:11px;font-family:monospace;">
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                    <span style="color:var(--color-text-muted);">Key:</span>
                                    <span style="cursor:pointer;color:var(--color-primary);" onclick="showStreamKey('<?php echo htmlspecialchars($event['stream_key'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($event['hls_url'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($event['rtmp_url'] ?? 'rtmp://potok.sportifymn.com/live', ENT_QUOTES); ?>', <?php echo $event['id']; ?>)" title="Нажмите для просмотра ключа и HLS URL">
                                        <?php echo substr($event['stream_key'], 0, 16); ?>... (нажмите)
                                    </span>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--color-text-muted);">
                                <?php echo date('d.m.Y H:i', strtotime($event['created_at'])); ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                    <!-- Изменить статус -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_stream_status">
                                        <input type="hidden" name="stream_id" value="<?php echo $event['id']; ?>">
                                        <select name="status" class="form-control" style="padding:5px 8px;font-size:12px;width:auto;display:inline;" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $event['status']==='pending'?'selected':''; ?>>Ожидание</option>
                                            <option value="live"    <?php echo $event['status']==='live'   ?'selected':''; ?>>LIVE</option>
                                            <option value="finished"<?php echo $event['status']==='finished'?'selected':''; ?>>Завершена</option>
                                        </select>
                                    </form>
                                    <a href="?tab=streaming&edit_stream=<?php echo $event['id']; ?>" class="btn"
                                       style="padding:5px 10px;font-size:12px;background:var(--color-surface-2);color:var(--color-text-muted);border:1px solid var(--color-border);">
                                        Изменить
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить трансляцию?');">
                                        <input type="hidden" name="action" value="delete_stream">
                                        <input type="hidden" name="stream_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding:5px 10px;font-size:12px;">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="margin-top:16px;">
                <p>Трансляций ещё нет. Создайте первую выше.</p>
            </div>
            <?php endif; ?>
        </section>

        <!-- Модалка: ключ потока -->
        <div id="streamKeyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;align-items:center;justify-content:center;">
            <div style="background:var(--color-surface-1,#fff);border-radius:12px;padding:28px;max-width:560px;width:92%;box-shadow:0 8px 40px rgba(0,0,0,.2);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="margin:0;font-family:var(--font-sport);font-size:1.1rem;letter-spacing:.3px;text-transform:uppercase;" id="skModalTitle">Данные трансляции</h3>
                    <button onclick="document.getElementById('streamKeyModal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--color-text-muted);">×</button>
                </div>
                <div id="skModalBody"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===================== STATS TAB ===================== -->
        <?php if ($active_tab === 'stats'): ?>
        <section>
            <h2 class="section-title">Статистика трансляций</h2>

            <div id="statsTableContainer" style="margin-top:20px;">
                <div style="text-align:center;padding:40px;color:var(--color-text-muted);">Загрузка статистики...</div>
            </div>

            <!-- Детальная панель -->
            <div id="statsDetailPanel" style="display:none;margin-top:28px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <h3 id="statsDetailTitle" style="font-family:var(--font-sport);font-size:1.1rem;margin:0;text-transform:uppercase;letter-spacing:.3px;"></h3>
                    <button onclick="document.getElementById('statsDetailPanel').style.display='none'" class="btn" style="padding:6px 14px;font-size:13px;background:var(--color-surface-2);color:var(--color-text-muted);border:1px solid var(--color-border);">Закрыть</button>
                </div>
                <div id="statsKpiGrid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;"></div>
                <div class="admin-card" style="padding:20px;">
                    <div style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--color-text-muted);margin-bottom:14px;">График онлайна (последние 2 часа)</div>
                    <div style="position:relative;height:180px;" id="onlineChartWrap"></div>
                </div>
                <div class="admin-card" style="padding:20px;margin-top:16px;">
                    <div style="font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--color-text-muted);margin-bottom:14px;">Распределение по странам</div>
                    <div id="regionStats" style="color:var(--color-text-muted);font-size:13px;">Загрузка...</div>
                </div>
            </div>

            <script>
            function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

            (function loadStatsTable() {
                fetch('/api/viewer-stats.php?action=all_stats')
                    .then(r => r.json())
                    .then(data => {
                        const c = document.getElementById('statsTableContainer');
                        if (!data || !data.length) {
                            c.innerHTML = '<div class="empty-state"><p>Нет данных по трансляциям.</p></div>';
                            return;
                        }
                        let h = '<div class="data-table"><table><thead><tr>';
                        h += '<th>Трансляция</th><th>Статус</th><th>Сейчас</th><th>Всего</th><th>Пик</th><th>Ср. время</th><th></th>';
                        h += '</tr></thead><tbody>';
                        data.forEach(s => {
                            const stBg    = s.status==='live' ? 'rgba(239,68,68,.12)' : 'rgba(107,114,128,.1)';
                            const stColor = s.status==='live' ? '#dc2626' : '#6b7280';
                            h += '<tr>';
                            h += '<td style="font-weight:600;">' + escH(s.name) + '</td>';
                            h += '<td><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:'+stBg+';color:'+stColor+';">' + (s.status==='live'?'LIVE':s.status) + '</span></td>';
                            h += '<td><strong style="font-size:20px;color:var(--color-primary);">' + (s.online_now||0) + '</strong></td>';
                            h += '<td>' + (s.total_unique||0) + '</td>';
                            h += '<td><strong>' + (s.peak_online||0) + '</strong></td>';
                            h += '<td style="color:var(--color-text-muted);">' + (s.avg_duration_formatted||'—') + '</td>';
                            h += '<td><button class="btn btn-primary" style="padding:5px 12px;font-size:12px;" onclick="loadDetail(\'' + escH(s.stream_key) + '\',\'' + escH(s.name) + '\')">Подробнее</button></td>';
                            h += '</tr>';
                        });
                        h += '</tbody></table></div>';
                        c.innerHTML = h;
                    })
                    .catch(() => {
                        document.getElementById('statsTableContainer').innerHTML = '<div class="empty-state"><p style="color:#ef4444;">Ошибка загрузки статистики</p></div>';
                    });
            })();

            function loadDetail(streamKey, name) {
                document.getElementById('statsDetailTitle').textContent = name;
                document.getElementById('statsDetailPanel').style.display = '';
                document.getElementById('statsKpiGrid').innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--color-text-muted);">Загрузка...</div>';
                document.getElementById('regionStats').innerHTML = 'Загрузка...';
                document.getElementById('onlineChartWrap').innerHTML = '';
                document.getElementById('statsDetailPanel').scrollIntoView({behavior:'smooth',block:'start'});

                fetch('/api/viewer-stats.php?action=stats&stream_key=' + encodeURIComponent(streamKey))
                    .then(r => r.json())
                    .then(d => {
                        const kpis = [
                            {label:'Смотрят сейчас', value:d.online_now,            color:'var(--color-live)'},
                            {label:'Пик онлайна',    value:d.peak_online,           color:'var(--color-primary)'},
                            {label:'Всего зрителей', value:d.total_unique,          color:'var(--color-primary)'},
                            {label:'Ср. время',      value:d.avg_duration_formatted||'—', color:'var(--color-text)'},
                        ];
                        document.getElementById('statsKpiGrid').innerHTML = kpis.map(k =>
                            '<div class="admin-card" style="padding:16px;text-align:center;margin:0;">' +
                            '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--color-text-muted);margin-bottom:8px;">' + k.label + '</div>' +
                            '<div style="font-size:28px;font-weight:700;color:' + k.color + ';">' + k.value + '</div></div>'
                        ).join('');
                    });

                fetch('/api/viewer-stats.php?action=timeline&stream_key=' + encodeURIComponent(streamKey) + '&minutes=120')
                    .then(r => r.json())
                    .then(d => {
                        if (!d.data || !d.data.length) { document.getElementById('onlineChartWrap').innerHTML = '<span style="color:var(--color-text-muted);font-size:13px;">Нет данных графика</span>'; return; }
                        const labels = d.data.map(p => { const t = new Date(p.recorded_at.replace(' ','T')); return t.toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit'}); });
                        const values = d.data.map(p => p.viewers_count || 0);
                        renderSvgChart(document.getElementById('onlineChartWrap'), labels, values);
                    });

                fetch('/api/viewer-stats.php?action=regions&stream_key=' + encodeURIComponent(streamKey))
                    .then(r => r.json())
                    .then(d => {
                        if (!d.regions || !d.regions.length) { document.getElementById('regionStats').innerHTML = '<span>Нет данных о регионах</span>'; return; }
                        const total = d.regions.reduce((a, r) => a + r.count, 0);
                        document.getElementById('regionStats').innerHTML = d.regions.map(r => {
                            const pct = total > 0 ? Math.round(r.count / total * 100) : 0;
                            return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">' +
                                '<span style="min-width:48px;font-weight:600;">' + escH(r.country||'??') + '</span>' +
                                '<div style="flex:1;height:8px;background:var(--color-surface-2);border-radius:4px;overflow:hidden;"><div style="width:'+pct+'%;height:100%;background:var(--color-primary);border-radius:4px;"></div></div>' +
                                '<span style="min-width:80px;text-align:right;">' + r.count + ' (' + pct + '%)</span></div>';
                        }).join('');
                    })
                    .catch(() => { document.getElementById('regionStats').innerHTML = '<span>Регионы недоступны</span>'; });
            }

            function renderSvgChart(wrap, labels, values) {
                const W = wrap.clientWidth || 600, H = 170;
                const pad = {t:16,r:16,b:36,l:48};
                const maxV = Math.max(...values, 1);
                const step = (W - pad.l - pad.r) / Math.max(values.length - 1, 1);
                const tx = i => pad.l + i * step;
                const ty = v => pad.t + (H - pad.t - pad.b) * (1 - v / maxV);
                const pts = values.map((v,i) => tx(i)+','+ty(v)).join(' ');
                const area = tx(0)+','+(H-pad.b)+' '+pts+' '+tx(values.length-1)+','+(H-pad.b);
                let yL='', xL='';
                for (let i=0;i<=4;i++) { const v=Math.round(maxV*i/4),y=ty(v); yL+='<line x1="'+(pad.l-4)+'" y1="'+y+'" x2="'+(W-pad.r)+'" y2="'+y+'" stroke="var(--color-border)" stroke-width="1" stroke-dasharray="3 3"/><text x="'+(pad.l-8)+'" y="'+(y+4)+'" text-anchor="end" font-size="10" fill="var(--color-text-muted)">'+v+'</text>'; }
                labels.forEach((lb,i) => { if (i % Math.max(1,Math.floor(labels.length/8))===0) xL+='<text x="'+tx(i)+'" y="'+(H-pad.b+16)+'" text-anchor="middle" font-size="10" fill="var(--color-text-muted)">'+lb+'</text>'; });
                wrap.innerHTML = '<svg width="100%" height="'+H+'" viewBox="0 0 '+W+' '+H+'" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="cg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--color-primary)" stop-opacity="0.25"/><stop offset="100%" stop-color="var(--color-primary)" stop-opacity="0.02"/></linearGradient></defs>'+yL+xL+'<polygon points="'+area+'" fill="url(#cg)"/><polyline points="'+pts+'" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linejoin="round"/></svg>';
            }
            </script>
        </section>
        <?php endif; ?>

        <!-- ===================== BANNERS TAB ===================== -->
        <?php if ($active_tab === 'banners'): ?>
        <section>
            <h2 class="section-title">Управление баннерами</h2>

            <div class="admin-card">
                <h3>Добавить новый баннер</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_banner">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Заголовок *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Описание *</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Текст кнопки</label>
                            <input type="text" name="button_text" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Ссылка кнопки</label>
                            <input type="url" name="button_link" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Изображение</label>
                            <input type="file" name="banner_image" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Порядок сортировки</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:8px;">Добавить баннер</button>
                </form>
            </div>

            <?php if (!empty($banners)): ?>
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Заголовок</th>
                            <th>Описание</th>
                            <th>Изображение</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $banner): ?>
                        <tr>
                            <td style="color:var(--color-text-muted);font-size:13px;">#<?php echo $banner['id']; ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($banner['title']); ?></td>
                            <td style="font-size:13px;color:var(--color-text-muted);">
                                <?php echo htmlspecialchars(mb_substr($banner['description'], 0, 60)) . (mb_strlen($banner['description']) > 60 ? '…' : ''); ?>
                            </td>
                            <td>
                                <?php if ($banner['image_path']): ?>
                                    <img src="/<?php echo htmlspecialchars($banner['image_path']); ?>"
                                         style="height:44px;width:auto;border-radius:4px;border:1px solid var(--color-border);">
                                <?php else: ?>
                                    <span style="color:var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:12px;font-weight:600;color:<?php echo $banner['is_active'] ? '#065f46' : 'var(--color-text-muted)'; ?>;">
                                    <?php echo $banner['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить баннер?');">
                                    <input type="hidden" name="action" value="delete_banner">
                                    <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:6px 12px;font-size:12px;">Удалить</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="margin-top:16px;">
                <p>Баннеров ещё нет. Добавьте первый баннер выше.</p>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- ===================== CHAT TAB ===================== -->
        <?php if ($active_tab === 'chat'): ?>
        <section>
            <h2 class="section-title">Управление чатом</h2>

            <!-- Выбор матча -->
            <div class="admin-card" style="margin-bottom:20px;">
                <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                        <label style="margin-bottom:6px;">Матч / трансляция</label>
                        <select id="chatMatchSelect" class="form-control" onchange="loadAdminChat(this.value)">
                            <option value="">— выберите матч —</option>
                            <?php foreach ($matches as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                        <label style="margin-bottom:6px;">Написать в чат от администратора</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="adminChatMsg" class="form-control" placeholder="Сообщение в чат..." maxlength="300">
                            <button class="btn btn-primary" onclick="sendAdminMsg()" style="white-space:nowrap;">Отправить</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Список сообщений -->
            <div class="admin-card" style="padding:0;">
                <div style="padding:16px 20px;border-bottom:1px solid var(--color-border);font-weight:600;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:var(--color-text-muted);">
                    Сообщения чата
                </div>
                <div id="adminChatMessages" style="max-height:500px;overflow-y:auto;padding:0;">
                    <div style="padding:40px;text-align:center;color:var(--color-text-muted);">Выберите матч для просмотра чата</div>
                </div>
            </div>

            <!-- Список банов -->
            <div class="admin-card" style="margin-top:20px;">
                <h3>Активные блокировки</h3>
                <div id="adminBansList">
                    <div style="color:var(--color-text-muted);font-size:13px;">Загрузка...</div>
                </div>
            </div>
        </section>

        <script>
        var adminChatMatchId = 0;
        var adminChatInterval = null;

        function loadAdminChat(matchId) {
            adminChatMatchId = parseInt(matchId) || 0;
            if (!adminChatMatchId) {
                document.getElementById('adminChatMessages').innerHTML = '<div style="padding:40px;text-align:center;color:var(--color-text-muted);">Выберите матч для просмотра чата</div>';
                return;
            }
            if (adminChatInterval) clearInterval(adminChatInterval);
            fetchAdminMessages();
            adminChatInterval = setInterval(fetchAdminMessages, 5000);
        }

        function fetchAdminMessages() {
            if (!adminChatMatchId) return;
            fetch('/api/chat-admin.php?action=messages&match_id=' + adminChatMatchId + '&limit=100')
                .then(r => r.json())
                .then(msgs => {
                    const c = document.getElementById('adminChatMessages');
                    if (!msgs.length) { c.innerHTML = '<div style="padding:20px;text-align:center;color:var(--color-text-muted);">Сообщений нет</div>'; return; }
                    c.innerHTML = msgs.map(m => {
                        const cls = m.is_deleted ? 'opacity:0.4;' : '';
                        const del = !m.is_deleted ? '<button onclick="adminDeleteMsg('+m.id+')" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:11px;padding:2px 6px;" title="Удалить">✕</button>' : '<span style="font-size:11px;color:var(--color-text-muted);">удалено</span>';
                        const pin = !m.is_deleted ? '<button onclick="adminPinMsg('+m.id+','+(!m.is_pinned?1:0)+')" style="background:none;border:none;cursor:pointer;font-size:11px;padding:2px 6px;color:'+(m.is_pinned?'var(--color-primary)':'var(--color-text-muted)')+';" title="Закрепить">📌</button>' : '';
                        const ban = '<button onclick="adminBanUser(\''+escH(m.user_id)+'\', \''+escH(m.username)+'\', '+adminChatMatchId+')" style="background:none;border:none;cursor:pointer;color:#b45309;font-size:11px;padding:2px 6px;" title="Заблокировать пользователя">⛔</button>';
                        const adm = m.is_admin ? '<span style="background:rgba(26,86,219,.12);color:var(--color-primary);font-size:10px;padding:1px 6px;border-radius:3px;font-weight:700;margin-right:4px;">ADMIN</span>' : '';
                        const pinBadge = m.is_pinned ? '<span style="color:var(--color-primary);font-size:10px;margin-right:4px;">📌</span>' : '';
                        return '<div style="display:flex;align-items:flex-start;gap:8px;padding:10px 16px;border-bottom:1px solid var(--color-border);'+cls+'">' +
                            '<div style="flex:1;min-width:0;">' +
                            pinBadge + adm +
                            '<strong style="font-size:13px;">' + escH(m.username) + '</strong>' +
                            '<span style="font-size:11px;color:var(--color-text-muted);margin-left:8px;">' + (m.created_at||'') + '</span>' +
                            '<div style="font-size:13px;margin-top:3px;word-break:break-word;">' + escH(m.message) + '</div>' +
                            '</div>' +
                            '<div style="display:flex;gap:2px;flex-shrink:0;">' + pin + del + ban + '</div>' +
                            '</div>';
                    }).join('');
                })
                .catch(() => {});
        }

        function adminDeleteMsg(id) {
            if (!confirm('Удалить сообщение?')) return;
            fetch('/api/chat-admin.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete_msg',msg_id:id})})
                .then(() => fetchAdminMessages()).catch(() => {});
        }

        function adminPinMsg(id, pin) {
            fetch('/api/chat-admin.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'pin_msg',msg_id:id,pinned:pin,match_id:adminChatMatchId})})
                .then(() => fetchAdminMessages()).catch(() => {});
        }

        function adminBanUser(userId, username, matchId) {
            const reason = prompt('Причина блокировки пользователя ' + username + ':');
            if (reason === null) return;
            fetch('/api/chat-admin.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'ban_user',user_id:userId,match_id:matchId,reason:reason||'Нарушение правил'})})
                .then(r => r.json())
                .then(d => { if (d.success) { alert('Пользователь заблокирован'); fetchAdminMessages(); loadAdminBans(); } })
                .catch(() => {});
        }

        function sendAdminMsg() {
            if (!adminChatMatchId) { alert('Выберите матч'); return; }
            const msg = document.getElementById('adminChatMsg').value.trim();
            if (!msg) return;
            fetch('/api/chat-admin.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'send_admin',match_id:adminChatMatchId,message:msg,admin_name:'Администратор'})})
                .then(r => r.json())
                .then(d => { if (d.success) { document.getElementById('adminChatMsg').value = ''; fetchAdminMessages(); } })
                .catch(() => {});
        }

        function loadAdminBans() {
            fetch('/api/chat-admin.php?action=bans')
                .then(r => r.json())
                .then(bans => {
                    const c = document.getElementById('adminBansList');
                    if (!bans.length) { c.innerHTML = '<div style="color:var(--color-text-muted);font-size:13px;">Нет активных блокировок</div>'; return; }
                    c.innerHTML = '<table style="width:100%;font-size:13px;border-collapse:collapse;">' +
                        '<thead><tr style="background:var(--color-surface-2);"><th style="padding:8px;text-align:left;">Ник</th><th style="padding:8px;text-align:left;">Причина</th><th style="padding:8px;text-align:left;">Дата</th><th></th></tr></thead><tbody>' +
                        bans.map(b => '<tr style="border-bottom:1px solid var(--color-border);">' +
                            '<td style="padding:8px;font-weight:600;">' + escH(b.username||b.user_id) + '</td>' +
                            '<td style="padding:8px;color:var(--color-text-muted);">' + escH(b.reason) + '</td>' +
                            '<td style="padding:8px;color:var(--color-text-muted);">' + (b.banned_at||'') + '</td>' +
                            '<td style="padding:8px;"><button onclick="adminUnban(\''+escH(b.user_id)+'\')" class="btn btn-primary" style="padding:4px 10px;font-size:12px;">Разблокировать</button></td>' +
                            '</tr>').join('') + '</tbody></table>';
                })
                .catch(() => {});
        }

        function adminUnban(userId) {
            fetch('/api/chat-admin.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'unban_user',user_id:userId})})
                .then(r => r.json())
                .then(d => { if (d.success) loadAdminBans(); })
                .catch(() => {});
        }

        function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

        loadAdminBans();
        </script>
        <?php endif; ?>

    </div><!-- /.admin-container -->

    <script>
        function copyField(elementId, btn) {
            const el = document.getElementById(elementId);
            if (!el) return;
            navigator.clipboard.writeText(el.value).then(function() {
                const orig = btn.textContent;
                btn.textContent = 'Скопировано';
                setTimeout(function() { btn.textContent = orig; }, 2000);
            }).catch(function() { el.select(); document.execCommand('copy'); });
        }

        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.value;
            navigator.clipboard.writeText(text).then(function() {
                const btn = event.target;
                const orig = btn.textContent;
                btn.textContent = 'Скопировано';
                setTimeout(function() { btn.textContent = orig; }, 2000);
            }).catch(function() {});
        }

        function showStreamKey(key, hls, rtmp, id) {
            const modal = document.getElementById('streamKeyModal');
            const body  = document.getElementById('skModalBody');
            body.innerHTML =
                '<div class="stream-info-box">' +
                mkCopyField('RTMP-сервер (для OBS)', rtmp, 'skRtmp_'+id) +
                mkCopyField('Ключ потока', key, 'skKey_'+id) +
                mkCopyField('HLS URL (ссылка плеера)', hls, 'skHls_'+id) +
                '</div>';
            modal.style.display = 'flex';
        }

        function mkCopyField(label, val, inputId) {
            return '<div class="copy-field"><label>'+label+'</label>' +
                '<div class="copy-input-group"><input type="text" id="'+inputId+'" value="'+val.replace(/"/g,'&quot;')+'" readonly class="form-control" style="font-family:monospace;font-size:12px;">' +
                '<button type="button" class="btn btn-primary" onclick="copyField(\''+inputId+'\',this)" style="padding:8px 14px;white-space:nowrap;">Копировать</button></div></div>';
        }

        document.addEventListener('click', function(e) {
            const modal = document.getElementById('streamKeyModal');
            if (modal && e.target === modal) modal.style.display = 'none';
        });
    </script>

</body>
</html>
