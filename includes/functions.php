<?php
function e($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function array_get($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error($message, $code = 400) {
    json_response(['error' => $message], $code);
}

function json_success($data = [], $message = 'Success') {
    $response = ['success' => true];
    if (!empty($message)) {
        $response['message'] = $message;
    }
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    json_response($response);
}

function redirect($url, $code = 302) {
    header("Location: $url", true, $code);
    exit;
}

function format_date($date, $format = 'd F Y, H:i') {
    $months = [
        'January' => 'января', 'February' => 'февраля', 'March' => 'марта',
        'April' => 'апреля', 'May' => 'мая', 'June' => 'июня',
        'July' => 'июля', 'August' => 'августа', 'September' => 'сентября',
        'October' => 'октября', 'November' => 'ноября', 'December' => 'декабря'
    ];
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $formatted = date($format, $timestamp);
    return str_replace(array_keys($months), array_values($months), $formatted);
}

function url($path = '') {
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    return $base . ($path ? '/' . $path : '');
}

function asset($path) {
    return url('assets/' . ltrim($path, '/'));
}

function is_admin_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin() {
    if (!is_admin_logged_in()) {
        if (is_ajax()) {
            json_error('Необходима авторизация', 401);
        } else {
            redirect(url('admin/login.php'));
        }
    }
}

function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

function number_format_locale($number) {
    return number_format($number, 0, ',', ' ');
}

function view($template, $data = []) {
    extract($data);
    $templatePath = __DIR__ . '/../views/' . $template . '.php';
    if (!file_exists($templatePath)) {
        throw new Exception("View not found: $template");
    }
    include $templatePath;
}

function is_post() { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function is_get() { return $_SERVER['REQUEST_METHOD'] === 'GET'; }
function is_delete() { return $_SERVER['REQUEST_METHOD'] === 'DELETE'; }
function is_put() { return $_SERVER['REQUEST_METHOD'] === 'PUT'; }

function get_input_data() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    return $_POST;
}

function generate_meta_tags($title = '', $description = '', $keywords = '') {
    $title = !empty($title) ? e($title) . ' - ' . SITE_NAME : SITE_NAME;
    $description = !empty($description) ? e($description) : SITE_DESCRIPTION;
    $meta = [
        '<meta charset="UTF-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
        '<title>' . $title . '</title>',
        '<meta name="description" content="' . $description . '">',
    ];
    if (!empty($keywords)) {
        $meta[] = '<meta name="keywords" content="' . e($keywords) . '">';
    }
    $meta[] = '<meta property="og:title" content="' . $title . '">';
    $meta[] = '<meta property="og:description" content="' . $description . '">';
    $meta[] = '<meta property="og:type" content="website">';
    $meta[] = '<meta name="theme-color" content="#3b82f6">';
    return implode("\n    ", $meta);
}

function upload_file($file, $target_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Некорректные параметры файла');
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('Файл слишком большой');
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('Файл не был загружен');
        default:
            throw new Exception('Неизвестная ошибка загрузки');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Файл превышает 10 МБ');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];
    if (!array_key_exists($mime_type, $allowed_mimes)) {
        throw new Exception('Недопустимый тип файла');
    }
    $ext = $allowed_mimes[$mime_type];
    if (!in_array($ext, $allowed_types)) {
        throw new Exception('Недопустимое расширение файла');
    }
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    $filename = sprintf('%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(8)), $ext);
    $target_path = rtrim($target_dir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception('Не удалось сохранить файл');
    }
    return $target_path;
}

function delete_file($path) {
    if (file_exists($path) && is_file($path)) {
        return unlink($path);
    }
    return false;
}

function get_match_status($match) {
    $now = time();
    $start_time = strtotime($match['start_time']);
    $auto_close_time = strtotime($match['auto_close_time']);
    if ($now >= $auto_close_time) {
        return ['type' => 'finished', 'label' => 'Завершен'];
    }
    if ($now >= $start_time) {
        return ['type' => 'live', 'label' => 'В эфире'];
    }
    return ['type' => 'upcoming', 'label' => 'Скоро начнется'];
}

function format_match_time($datetime) {
    $timestamp = strtotime($datetime);
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $time = date('H:i', $timestamp);
    return sprintf('%d %s в %s по МСК', $day, $month, $time);
}
?>
