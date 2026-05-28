<?php
/**
 * Тестовая страница для проверки работоспособности
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test</title></head><body>";
echo "<h1>Тест подключения</h1>";

// Тест 1: PHP работает
echo "<p>✓ PHP работает</p>";

// Тест 2: Конфиг загружается
define('APP_ACCESS', true);
try {
    require_once __DIR__ . '/config.php';
    echo "<p>✓ config.php загружен</p>";
} catch (Exception $e) {
    echo "<p>✗ Ошибка config.php: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Тест 3: Database класс
try {
    require_once __DIR__ . '/includes/Database.php';
    echo "<p>✓ Database.php загружен</p>";
} catch (Exception $e) {
    echo "<p>✗ Ошибка Database.php: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Тест 4: Подключение к БД
try {
    $db = Database::getInstance();
    echo "<p>✓ Подключение к БД успешно</p>";
    
    $conn = $db->getConnection();
    echo "<p>✓ PDO соединение получено</p>";
} catch (Exception $e) {
    echo "<p>✗ Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Тест 5: Проверка таблиц
try {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>✓ Таблицы в БД:</p><ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>✗ Ошибка получения списка таблиц: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Тест 6: Security класс
try {
    require_once __DIR__ . '/includes/Security.php';
    echo "<p>✓ Security.php загружен</p>";
} catch (Exception $e) {
    echo "<p>✗ Ошибка Security.php: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Тест 7: Functions
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "<p>✓ functions.php загружен</p>";
} catch (Exception $e) {
    echo "<p>✗ Ошибка functions.php: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>Все основные компоненты проверены!</strong></p>";
echo "<p><a href='/'>Перейти на главную</a> | <a href='/admin/login.php'>Админ-панель</a></p>";
echo "</body></html>";
?>
