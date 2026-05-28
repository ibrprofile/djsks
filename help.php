<?php
require_once 'config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Помощь - SPORTIFY</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="/" class="logo">SPORTIFY</a>
        </div>
    </header>

    <main class="container" style="max-width: 800px;">
        <h1 class="section-title">Помощь</h1>
        
        <div style="background: white; border-radius: 0.75rem; padding: 2rem; margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">Что делать если не работает трансляция?</h2>
            <ul style="list-style: disc; margin-left: 1.5rem; color: #666; line-height: 1.8;">
                <li>Попробуйте обновить страницу (Ctrl/Cmd + R)</li>
                <li>Проверьте скорость вашего интернет-соединения</li>
                <li>Попробуйте открыть страницу в другом браузере</li>
                <li>Очистите кэш браузера</li>
                <li>Выключите блокировщики рекламы и VPN</li>
            </ul>
        </div>
        
        <div style="background: white; border-radius: 0.75rem; padding: 2rem; margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">Как связаться с администрацией сайта?</h2>
            <p style="color: #666; line-height: 1.8;">
                Если у вас есть вопросы или проблемы, напишите нам на почту:<br>
                <a href="mailto:sportify-tv@proton.me" style="color: #2563eb; font-weight: 600;">sportify-tv@proton.me</a>
            </p>
        </div>
        
        <div style="background: white; border-radius: 0.75rem; padding: 2rem;">
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">На каких устройствах работает?</h2>
            <p style="color: #666; line-height: 1.8;">
                SPORTIFY работает на всех современных устройствах: компьютерах, планшетах и смартфонах. 
                Поддерживаются все популярные браузеры: Chrome, Firefox, Safari, Edge.
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 3rem;">
            <a href="/" class="btn btn-primary">Вернуться на главную</a>
        </div>
    </main>
</body>
</html>
