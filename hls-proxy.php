<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/functions.php';
Security::setSecurityHeaders();

$u = isset($_GET['u']) ? trim($_GET['u']) : '';
if (!$u) {
    http_response_code(400);
    exit;
}

$p = parse_url($u);
if (!$p || empty($p['scheme']) || empty($p['host'])) {
    http_response_code(400);
    exit;
}

$origin = url();

function fetch_remote($url, $headers, $verifySsl = true) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_WHATEVER);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$data, $code, $ct, $err];
}

$ext = strtolower(pathinfo($p['path'] ?? '', PATHINFO_EXTENSION));
$is_playlist = $ext === 'm3u8';

// Профили запросов (попытки с разными заголовками)
$scheme = $p['scheme'];
$host = $p['host'];
$originHeaders = [
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
    'Accept-Language: ru,en;q=0.9',
];

$profiles = [];
if ($is_playlist) {
    $profiles[] = array_merge($originHeaders, ['Accept: application/vnd.apple.mpegurl']);
} else {
    $profiles[] = array_merge($originHeaders, ['Accept: */*']);
}
// Доп. попытка с реферальным заголовком на исходный хост
$profiles[] = array_merge($originHeaders, [
    'Referer: ' . $scheme . '://' . $host . '/',
    'Origin: ' . $scheme . '://' . $host,
    $is_playlist ? 'Accept: application/vnd.apple.mpegurl' : 'Accept: */*',
]);
// Последняя попытка с ослабленной SSL проверкой (на случай некорректного сертификата)
$profiles[] = array_merge($originHeaders, [
    $is_playlist ? 'Accept: application/vnd.apple.mpegurl' : 'Accept: */*',
]);

$body = null; $code = 0; $ct = null; $err = '';
foreach ($profiles as $i => $hdrs) {
    $verify = $i < count($profiles) - 1; // последняя попытка с verify=false
    list($body, $code, $ct, $err) = fetch_remote($u, $hdrs, $verify);
    if ($code >= 200 && $code < 400 && $body) {
        break;
    }
}

if ($code >= 400 || $body === false || $body === null) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad Gateway (' . $code . ')' . ($err ? ' - ' . $err : '');
    exit;
}

if ($is_playlist) {
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    $base = preg_replace('#[^/]+$#', '', $u);
    $lines = preg_split('/\r\n|\r|\n/', $body);
    $out = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) {
            $out[] = $line;
            continue;
        }
        if (preg_match('#^https?://#i', $trim)) {
            $abs = $trim;
        } elseif (strpos($trim, '/') === 0) {
            $abs = $scheme . '://' . $host . $trim;
        } else {
            $abs = $base . $trim;
        }
        $prox = url('hls-proxy.php') . '?u=' . urlencode($abs);
        $out[] = $prox;
    }
    echo implode("\n", $out);
    exit;
}

if ($ext === 'ts' || $ext === 'm4s' || $ext === 'mp4') {
    header('Content-Type: application/octet-stream');
} else {
    header('Content-Type: ' . ($ct ?: 'application/octet-stream'));
}
echo $body;
exit;
