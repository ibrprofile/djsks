<?php
/**
 * API: голосование за исход матча
 * POST /api/vote.php  { match_id, vote: "1"|"draw"|"2" }
 * GET  /api/vote.php?match_id=N  — получить результаты
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Security.php';

Security::setSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance()->getConnection();

// Получить IP пользователя
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $ip)[0]);
$ipHash = hash('sha256', $ip); // не храним чистый IP

function getMatchVotes($db, $matchId) {
    $stmt = $db->prepare("
        SELECT vote, COUNT(*) as cnt
        FROM match_votes
        WHERE match_id = ?
        GROUP BY vote
    ");
    $stmt->execute([$matchId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = ['1' => 0, 'draw' => 0, '2' => 0];
    foreach ($rows as $r) {
        if (isset($counts[$r['vote']])) {
            $counts[$r['vote']] = (int)$r['cnt'];
        }
    }
    $total = array_sum($counts);
    $pct = [];
    foreach ($counts as $k => $v) {
        $pct[$k] = $total > 0 ? round($v / $total * 100) : 0;
    }
    return ['counts' => $counts, 'percentages' => $pct, 'total' => $total];
}

// ---- GET: получить результаты ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $matchId = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
    if (!$matchId) { echo json_encode(['error' => 'bad_request']); exit; }

    // Получить матч для проверки флага no_draw
    $stmt = $db->prepare("SELECT no_draw_vote, start_time FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$match) { echo json_encode(['error' => 'not_found']); exit; }

    // Проверить свой голос
    $stmtMy = $db->prepare("SELECT vote FROM match_votes WHERE match_id = ? AND ip_hash = ?");
    $stmtMy->execute([$matchId, $ipHash]);
    $myVote = $stmtMy->fetchColumn();

    $data = getMatchVotes($db, $matchId);
    $data['my_vote'] = $myVote ?: null;
    $data['no_draw'] = !empty($match['no_draw_vote']);
    $data['match_started'] = time() >= strtotime($match['start_time']);

    echo json_encode($data);
    exit;
}

// ---- POST: проголосовать ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $matchId = isset($input['match_id']) ? (int)$input['match_id'] : 0;
    $vote = $input['vote'] ?? '';

    if (!$matchId || !in_array($vote, ['1', 'draw', '2'])) {
        http_response_code(400);
        echo json_encode(['error' => 'bad_request']);
        exit;
    }

    // Получить матч
    $stmt = $db->prepare("SELECT no_draw_vote, start_time FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$match) { http_response_code(404); echo json_encode(['error' => 'not_found']); exit; }

    // Нельзя голосовать после начала матча
    if (time() >= strtotime($match['start_time'])) {
        echo json_encode(['error' => 'match_started']);
        exit;
    }

    // Нельзя голосовать за ничью если флаг no_draw
    if ($vote === 'draw' && !empty($match['no_draw_vote'])) {
        echo json_encode(['error' => 'no_draw']);
        exit;
    }

    // Проверить существующий голос
    $stmtCheck = $db->prepare("SELECT id FROM match_votes WHERE match_id = ? AND ip_hash = ?");
    $stmtCheck->execute([$matchId, $ipHash]);
    $existing = $stmtCheck->fetchColumn();

    if ($existing) {
        // Обновить голос
        $stmtUpd = $db->prepare("UPDATE match_votes SET vote = ?, updated_at = NOW() WHERE match_id = ? AND ip_hash = ?");
        $stmtUpd->execute([$vote, $matchId, $ipHash]);
    } else {
        // Новый голос
        $stmtIns = $db->prepare("INSERT INTO match_votes (match_id, ip_hash, vote) VALUES (?, ?, ?)");
        $stmtIns->execute([$matchId, $ipHash, $vote]);
    }

    $data = getMatchVotes($db, $matchId);
    $data['my_vote'] = $vote;
    $data['no_draw'] = !empty($match['no_draw_vote']);
    $data['match_started'] = false;

    echo json_encode($data);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
