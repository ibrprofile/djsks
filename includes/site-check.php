<?php
/**
 * Проверка статуса сайта - подключается на всех страницах кроме админки
 */

// Не проверяем статус для админов
if (defined('IS_ADMIN_PAGE') && IS_ADMIN_PAGE === true) {
    return;
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SELECT * FROM site_settings WHERE setting_key = 'site_closed'");
    $site_closed = $stmt->fetch();
    
    if ($site_closed && $site_closed['setting_value'] == '1') {
        $stmt = $conn->query("SELECT * FROM site_settings WHERE setting_key = 'site_closed_message'");
        $message_row = $stmt->fetch();
        $message = $message_row ? $message_row['setting_value'] : 'Сайт временно недоступен';
        
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Сайт недоступен</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    padding: 1rem;
                }
                .container { text-align: center; max-width: 600px; }
                .icon { font-size: 6rem; margin-bottom: 2rem; }
                h1 { font-size: 2.5rem; margin-bottom: 1rem; font-weight: 800; }
                p { font-size: 1.25rem; opacity: 0.9; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">🔒</div>
                <h1>Сайт временно недоступен</h1>
                <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (PDOException $e) {
    // Таблица не существует или другая ошибка - пропускаем проверку
    error_log("Site check error: " . $e->getMessage());
}


