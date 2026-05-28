<?php
session_start();
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';
require_once '../includes/StreamManager.php';

require_admin();

$db = Database::getInstance()->getConnection();

try {
    $db->exec(file_get_contents(__DIR__ . '/../scripts/ucl-final-tables.sql'));
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_match':
            $stmt = $db->prepare("UPDATE ucl_final_2026 SET is_live=?, is_finished=?, psg_score=?, arsenal_score=?, psg_coach=?, arsenal_coach=?, stream_id=? WHERE id=1");
            $stmt->execute([
                isset($_POST['is_live']) ? 1 : 0,
                isset($_POST['is_finished']) ? 1 : 0,
                (int)$_POST['psg_score'],
                (int)$_POST['arsenal_score'],
                $_POST['psg_coach'],
                $_POST['arsenal_coach'],
                !empty($_POST['stream_id']) ? (int)$_POST['stream_id'] : null
            ]);
            $_SESSION['success'] = 'Данные матча обновлены';
            break;

        case 'add_player':
            $stmt = $db->prepare("INSERT INTO ucl_final_players (team, name, number, position, position_order, is_starter) VALUES (?, ?, ?, ?, ?, ?)");
            $posOrder = ['gk' => 1, 'def' => 2, 'mid' => 3, 'fwd' => 4][$_POST['position']] ?? 3;
            $stmt->execute([
                $_POST['team'],
                $_POST['player_name'],
                !empty($_POST['number']) ? (int)$_POST['number'] : null,
                $_POST['position'],
                $posOrder,
                isset($_POST['is_starter']) ? 1 : 0
            ]);
            $_SESSION['success'] = 'Игрок добавлен';
            break;

        case 'delete_player':
            $stmt = $db->prepare("DELETE FROM ucl_final_players WHERE id = ?");
            $stmt->execute([(int)$_POST['player_id']]);
            $_SESSION['success'] = 'Игрок удалён';
            break;

        case 'update_player':
            $stmt = $db->prepare("UPDATE ucl_final_players SET name=?, number=?, position=?, is_starter=? WHERE id=?");
            $stmt->execute([
                $_POST['player_name'],
                !empty($_POST['number']) ? (int)$_POST['number'] : null,
                $_POST['position'],
                isset($_POST['is_starter']) ? 1 : 0,
                (int)$_POST['player_id']
            ]);
            $_SESSION['success'] = 'Игрок обновлён';
            break;

        case 'add_event':
            $stmt = $db->prepare("INSERT INTO ucl_final_timeline (minute, event_type, title, description, team) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                (int)$_POST['minute'],
                $_POST['event_type'],
                $_POST['event_title'],
                $_POST['description'] ?? null,
                $_POST['team'] ?? 'neutral'
            ]);
            $_SESSION['success'] = 'Событие добавлено';
            break;

        case 'delete_event':
            $stmt = $db->prepare("DELETE FROM ucl_final_timeline WHERE id = ?");
            $stmt->execute([(int)$_POST['event_id']]);
            $_SESSION['success'] = 'Событие удалено';
            break;
    }
    header('Location: ucl-final.php');
    exit;
}

$match = $db->query("SELECT * FROM ucl_final_2026 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$match) {
    $db->exec("INSERT INTO ucl_final_2026 (id) VALUES (1)");
    $match = $db->query("SELECT * FROM ucl_final_2026 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

$psgPlayers = $db->query("SELECT * FROM ucl_final_players WHERE team='psg' ORDER BY position_order, name")->fetchAll(PDO::FETCH_ASSOC);
$arsenalPlayers = $db->query("SELECT * FROM ucl_final_players WHERE team='arsenal' ORDER BY position_order, name")->fetchAll(PDO::FETCH_ASSOC);
$timeline = $db->query("SELECT * FROM ucl_final_timeline ORDER BY minute ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$streams = $db->query("SELECT id, name, status FROM streaming_events ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$positions = ['gk' => 'Вратарь', 'def' => 'Защитник', 'mid' => 'Полузащитник', 'fwd' => 'Нападающий'];
$eventTypes = [
    'goal' => 'Гол',
    'yellow' => 'Жёлтая карточка',
    'red' => 'Красная карточка', 
    'sub' => 'Замена',
    'var' => 'VAR',
    'penalty' => 'Пенальти',
    'miss' => 'Промах',
    'other' => 'Другое'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Финал ЛЧ 2026 — Админ</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .ucl-header { background: linear-gradient(135deg, #0d1b3e 0%, #1e3a8a 100%); color: #fff; padding: 24px; border-radius: var(--radius-card); margin-bottom: 24px; }
        .ucl-header h1 { font-family: var(--font-sport); font-size: 1.6rem; margin-bottom: 8px; }
        .ucl-header p { opacity: 0.8; font-size: 14px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .squad-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .squad-table th, .squad-table td { padding: 10px 12px; border-bottom: 1px solid var(--color-border); text-align: left; }
        .squad-table th { background: var(--color-surface-2); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-muted); }
        .badge-starter { background: rgba(16,185,129,0.15); color: #10b981; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .badge-sub { background: rgba(107,114,128,0.1); color: #6b7280; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .event-icon { font-size: 16px; margin-right: 6px; }
        .score-input { width: 60px; text-align: center; font-size: 24px; font-weight: 700; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="/admin/dashboard.php" class="logo">SPORTIFY Admin</a>
            <a href="/admin/logout.php" class="btn btn-danger" style="padding:8px 18px;font-size:13px;">Выйти</a>
        </div>
    </header>

    <div class="admin-container">

        <div class="ucl-header">
            <h1>Финал Лиги Чемпионов 2026</h1>
            <p>ПСЖ — Арсенал · 30 мая 2026 · Пушкаш Арена, Будапешт</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="admin-card">
            <h3>Управление матчем</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_match">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Статус матча</label>
                        <div style="display:flex;gap:20px;margin-top:8px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;">
                                <input type="checkbox" name="is_live" value="1" <?php echo $match['is_live'] ? 'checked' : ''; ?> style="width:18px;height:18px;">
                                LIVE (идёт трансляция)
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;">
                                <input type="checkbox" name="is_finished" value="1" <?php echo $match['is_finished'] ? 'checked' : ''; ?> style="width:18px;height:18px;">
                                Завершён
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Трансляция</label>
                        <select name="stream_id" class="form-control">
                            <option value="">— не выбрана —</option>
                            <?php foreach ($streams as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $match['stream_id'] == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?> (<?php echo $s['status']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Счёт</label>
                    <div style="display:flex;align-items:center;gap:16px;margin-top:8px;">
                        <div style="text-align:center;">
                            <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:4px;">ПСЖ</div>
                            <input type="number" name="psg_score" value="<?php echo $match['psg_score']; ?>" class="form-control score-input" min="0">
                        </div>
                        <span style="font-size:24px;color:var(--color-text-muted);">:</span>
                        <div style="text-align:center;">
                            <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:4px;">Арсенал</div>
                            <input type="number" name="arsenal_score" value="<?php echo $match['arsenal_score']; ?>" class="form-control score-input" min="0">
                        </div>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Тренер ПСЖ</label>
                        <input type="text" name="psg_coach" value="<?php echo htmlspecialchars($match['psg_coach']); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Тренер Арсенала</label>
                        <input type="text" name="arsenal_coach" value="<?php echo htmlspecialchars($match['arsenal_coach']); ?>" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="/ucl-final/" target="_blank" class="btn" style="background:var(--color-surface-2);color:var(--color-text-muted);border:1px solid var(--color-border);margin-left:8px;">Открыть страницу</a>
            </form>
        </div>

        <div class="grid-2">
            <div class="admin-card">
                <h3>Состав ПСЖ</h3>
                <form method="POST" style="margin-bottom:16px;">
                    <input type="hidden" name="action" value="add_player">
                    <input type="hidden" name="team" value="psg">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input type="text" name="player_name" placeholder="Фамилия" class="form-control" style="flex:1;min-width:120px;" required>
                        <input type="number" name="number" placeholder="№" class="form-control" style="width:50px;">
                        <select name="position" class="form-control" style="width:130px;">
                            <?php foreach ($positions as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label style="display:flex;align-items:center;gap:4px;font-size:12px;">
                            <input type="checkbox" name="is_starter" value="1"> Старт
                        </label>
                        <button type="submit" class="btn btn-primary" style="padding:8px 14px;">+</button>
                    </div>
                </form>
                <table class="squad-table">
                    <thead><tr><th>№</th><th>Игрок</th><th>Поз.</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($psgPlayers as $p): ?>
                    <tr>
                        <td><?php echo $p['number'] ?? '-'; ?></td>
                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><?php echo $positions[$p['position']] ?? ''; ?></td>
                        <td><?php echo $p['is_starter'] ? '<span class="badge-starter">Старт</span>' : '<span class="badge-sub">Запас</span>'; ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_player">
                                <input type="hidden" name="player_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;">×</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-card">
                <h3>Состав Арсенала</h3>
                <form method="POST" style="margin-bottom:16px;">
                    <input type="hidden" name="action" value="add_player">
                    <input type="hidden" name="team" value="arsenal">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input type="text" name="player_name" placeholder="Фамилия" class="form-control" style="flex:1;min-width:120px;" required>
                        <input type="number" name="number" placeholder="№" class="form-control" style="width:50px;">
                        <select name="position" class="form-control" style="width:130px;">
                            <?php foreach ($positions as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label style="display:flex;align-items:center;gap:4px;font-size:12px;">
                            <input type="checkbox" name="is_starter" value="1"> Старт
                        </label>
                        <button type="submit" class="btn btn-primary" style="padding:8px 14px;">+</button>
                    </div>
                </form>
                <table class="squad-table">
                    <thead><tr><th>№</th><th>Игрок</th><th>Поз.</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($arsenalPlayers as $p): ?>
                    <tr>
                        <td><?php echo $p['number'] ?? '-'; ?></td>
                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><?php echo $positions[$p['position']] ?? ''; ?></td>
                        <td><?php echo $p['is_starter'] ? '<span class="badge-starter">Старт</span>' : '<span class="badge-sub">Запас</span>'; ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_player">
                                <input type="hidden" name="player_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;">×</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card">
            <h3>Хронология матча</h3>
            <form method="POST" style="margin-bottom:20px;">
                <input type="hidden" name="action" value="add_event">
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Минута</label>
                        <input type="number" name="minute" class="form-control" style="width:70px;" min="0" max="120" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Тип</label>
                        <select name="event_type" class="form-control" style="width:140px;">
                            <?php foreach ($eventTypes as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Команда</label>
                        <select name="team" class="form-control" style="width:120px;">
                            <option value="neutral">—</option>
                            <option value="psg">ПСЖ</option>
                            <option value="arsenal">Арсенал</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;flex:1;min-width:200px;">
                        <label>Заголовок</label>
                        <input type="text" name="event_title" class="form-control" placeholder="напр. Гол! Мбаппе" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;flex:1;min-width:200px;">
                        <label>Описание</label>
                        <input type="text" name="description" class="form-control" placeholder="Удар с линии штрафной">
                    </div>
                    <button type="submit" class="btn btn-primary" style="height:42px;">Добавить</button>
                </div>
            </form>

            <?php if (empty($timeline)): ?>
            <p style="color:var(--color-text-muted);text-align:center;padding:20px;">События появятся здесь</p>
            <?php else: ?>
            <table class="squad-table">
                <thead><tr><th>Мин</th><th>Тип</th><th>Команда</th><th>Событие</th><th>Описание</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($timeline as $e): 
                    $icons = ['goal'=>'⚽','yellow'=>'🟨','red'=>'🟥','sub'=>'🔄','var'=>'📺','penalty'=>'⚽','miss'=>'❌','other'=>'📌'];
                ?>
                <tr>
                    <td><strong><?php echo $e['minute']; ?>'</strong></td>
                    <td><span class="event-icon"><?php echo $icons[$e['event_type']] ?? ''; ?></span><?php echo $eventTypes[$e['event_type']] ?? ''; ?></td>
                    <td><?php echo $e['team'] === 'psg' ? 'ПСЖ' : ($e['team'] === 'arsenal' ? 'Арсенал' : '—'); ?></td>
                    <td><?php echo htmlspecialchars($e['title']); ?></td>
                    <td style="color:var(--color-text-muted);font-size:12px;"><?php echo htmlspecialchars($e['description'] ?? ''); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete_event">
                            <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
                            <button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;">×</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>
