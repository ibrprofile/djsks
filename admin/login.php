<?php
/**
 * Страница входа в админ-панель
 */

define('APP_ACCESS', true);
define('IS_ADMIN_PAGE', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Security.php';

session_start();

// Админ пароль из переменных окружения (или переменная сессии)
$admin_password = getenv('ADMIN_PASSWORD') ?: "5512";

// Если уже авторизован, перенаправляем в админку
if (!empty($_SESSION['admin_logged_in'])) {
    redirect(url('admin/dashboard.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Проверка CSRF токена
    if (!Security::validateCSRFToken($csrf_token)) {
        $error = 'Недействительный запрос. Попробуйте снова.';
    } elseif (empty($code)) {
        $error = 'Введите код-пароль';
    } elseif (empty($admin_password)) {
        $error = 'Администратор не настроен. Обратитесь к владельцу сайта.';
    } elseif ($code === $admin_password) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        redirect(url('admin/dashboard.php'));
    } else {
        $error = 'Неверный код-пароль';
        sleep(1); // Защита от перебора
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <?php echo generate_meta_tags('Вход в админ-панель'); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            font-size: 1.75rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #6b7280;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .back-link a:hover {
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>Вход в админ-панель</h1>
            <p>Введите ваши учетные данные</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
            <div class="form-group">
                <label for="code" class="form-label">Код-пароль</label>
                <input 
                    type="password" 
                    id="code" 
                    name="code" 
                    class="form-input" 
                    required
                    autocomplete="off"
                    placeholder="Введите код"
                    autofocus
                >
            </div>
            
            <button type="submit" class="btn-login">Войти</button>
        </form>
        
        <div class="back-link">
            <a href="<?php echo url(); ?>">← Вернуться на главную</a>
        </div>
    </div>
</body>
</html>
