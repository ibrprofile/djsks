<?php
/**
 * Страница регистрации нового администратора
 * Защищенная форма с проверкой надежности пароля
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/functions.php';

Security::setSecurityHeaders();

// Если уже авторизован, перенаправляем в админку
if (is_admin_logged_in()) {
    redirect(url('admin/index.php'));
}

$error = '';
$success = '';
$validation_errors = [];

if (is_post()) {
    $username = Security::sanitize($_POST['username'] ?? '');
    $email = Security::sanitize($_POST['email'] ?? '', 'email');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Проверка CSRF токена
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!Security::validateCSRFToken($csrf_token)) {
        $error = 'Неверный токен безопасности';
    } else {
        // Валидация данных
        if (empty($username)) {
            $validation_errors['username'] = 'Логин обязателен для заполнения';
        } elseif (strlen($username) < 3) {
            $validation_errors['username'] = 'Логин должен содержать минимум 3 символа';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $validation_errors['username'] = 'Логин может содержать только латинские буквы, цифры и подчеркивание';
        }
        
        if (empty($email)) {
            $validation_errors['email'] = 'Email обязателен для заполнения';
        } elseif (!Security::validateEmail($email)) {
            $validation_errors['email'] = 'Введите корректный email адрес';
        }
        
        if (empty($password)) {
            $validation_errors['password'] = 'Пароль обязателен для заполнения';
        } elseif (strlen($password) < 8) {
            $validation_errors['password'] = 'Пароль должен содержать минимум 8 символов';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $validation_errors['password'] = 'Пароль должен содержать хотя бы одну заглавную букву';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $validation_errors['password'] = 'Пароль должен содержать хотя бы одну строчную букву';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $validation_errors['password'] = 'Пароль должен содержать хотя бы одну цифру';
        }
        
        if ($password !== $password_confirm) {
            $validation_errors['password_confirm'] = 'Пароли не совпадают';
        }
        
        // Если нет ошибок валидации, проверяем существование пользователя
        if (empty($validation_errors)) {
            $db = Database::getInstance();
            
            // Проверка существования username
            $existing_user = $db->fetchOne("SELECT id FROM admin_users WHERE username = ?", [$username]);
            if ($existing_user) {
                $validation_errors['username'] = 'Пользователь с таким логином уже существует';
            }
            
            // Проверка существования email
            $existing_email = $db->fetchOne("SELECT id FROM admin_users WHERE email = ?", [$email]);
            if ($existing_email) {
                $validation_errors['email'] = 'Пользователь с таким email уже существует';
            }
            
            // Если все проверки пройдены, создаем пользователя
            if (empty($validation_errors)) {
                $hashed_password = Security::hashPassword($password);
                
                $sql = "INSERT INTO admin_users (username, password, email, created_at) VALUES (?, ?, ?, NOW())";
                $result = $db->query($sql, [$username, $hashed_password, $email]);
                
                if ($result) {
                    $success = 'Аккаунт успешно создан! Вы можете войти используя свои учетные данные.';
                    
                    // Логирование
                    $ip = Security::getClientIP();
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    error_log("[REGISTRATION] New admin registered: {$username} from IP: {$ip}");
                    
                    // Очищаем форму
                    $_POST = [];
                } else {
                    $error = 'Произошла ошибка при создании аккаунта. Попробуйте позже.';
                }
            }
        }
        
        // Если есть ошибки валидации, показываем первую
        if (!empty($validation_errors)) {
            $error = reset($validation_errors);
        }
    }
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <?php echo generate_meta_tags('Регистрация администратора'); ?>
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
        
        .register-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 480px;
            padding: 2.5rem;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            font-size: 1.75rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-input.error {
            border-color: #ef4444;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            border-left: 4px solid #dc2626;
        }
        
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            border-left: 4px solid #059669;
        }
        
        .password-requirements {
            background: #f3f4f6;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
        
        .password-requirements ul {
            margin: 0.5rem 0 0 1.25rem;
            color: #6b7280;
        }
        
        .password-requirements li {
            margin-bottom: 0.25rem;
        }
        
        .password-requirements .title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        
        .btn-register {
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
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .btn-register:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .links-section {
            margin-top: 1.5rem;
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .links-section a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .links-section a:hover {
            text-decoration: underline;
        }
        
        .links-section .separator {
            margin: 0 0.5rem;
            color: #d1d5db;
        }
        
        .field-error {
            color: #dc2626;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 640px) {
            .register-card {
                padding: 2rem 1.5rem;
            }
            
            .register-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-header">
            <h1>Регистрация администратора</h1>
            <p>Создайте новый аккаунт администратора</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo e($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="username" class="form-label">Логин</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input <?php echo isset($validation_errors['username']) ? 'error' : ''; ?>" 
                    required
                    autocomplete="username"
                    value="<?php echo e($_POST['username'] ?? ''); ?>"
                    pattern="[a-zA-Z0-9_]{3,}"
                    title="Минимум 3 символа, только буквы, цифры и подчеркивание"
                >
                <?php if (isset($validation_errors['username'])): ?>
                    <div class="field-error"><?php echo e($validation_errors['username']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input <?php echo isset($validation_errors['email']) ? 'error' : ''; ?>" 
                    required
                    autocomplete="email"
                    value="<?php echo e($_POST['email'] ?? ''); ?>"
                >
                <?php if (isset($validation_errors['email'])): ?>
                    <div class="field-error"><?php echo e($validation_errors['email']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Пароль</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input <?php echo isset($validation_errors['password']) ? 'error' : ''; ?>" 
                    required
                    autocomplete="new-password"
                    minlength="8"
                >
                <?php if (isset($validation_errors['password'])): ?>
                    <div class="field-error"><?php echo e($validation_errors['password']); ?></div>
                <?php endif; ?>
                <div class="password-requirements">
                    <div class="title">Требования к паролю:</div>
                    <ul>
                        <li>Минимум 8 символов</li>
                        <li>Хотя бы одна заглавная буква (A-Z)</li>
                        <li>Хотя бы одна строчная буква (a-z)</li>
                        <li>Хотя бы одна цифра (0-9)</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password_confirm" class="form-label">Подтверждение пароля</label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    class="form-input <?php echo isset($validation_errors['password_confirm']) ? 'error' : ''; ?>" 
                    required
                    autocomplete="new-password"
                    minlength="8"
                >
                <?php if (isset($validation_errors['password_confirm'])): ?>
                    <div class="field-error"><?php echo e($validation_errors['password_confirm']); ?></div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-register">Создать аккаунт</button>
        </form>
        
        <div class="links-section">
            <a href="<?php echo url('admin/login.php'); ?>">Уже есть аккаунт? Войти</a>
            <span class="separator">|</span>
            <a href="<?php echo url(); ?>">Вернуться на главную</a>
        </div>
    </div>
    
    <script>
        // Валидация формы на клиентской стороне
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Пароли не совпадают');
                return false;
            }
            
            // Проверка сложности пароля
            if (password.length < 8) {
                e.preventDefault();
                alert('Пароль должен содержать минимум 8 символов');
                return false;
            }
            
            if (!/[A-Z]/.test(password)) {
                e.preventDefault();
                alert('Пароль должен содержать хотя бы одну заглавную букву');
                return false;
            }
            
            if (!/[a-z]/.test(password)) {
                e.preventDefault();
                alert('Пароль должен содержать хотя бы одну строчную букву');
                return false;
            }
            
            if (!/[0-9]/.test(password)) {
                e.preventDefault();
                alert('Пароль должен содержать хотя бы одну цифру');
                return false;
            }
        });
        
        // Визуальная индикация совпадения паролей
        const passwordConfirm = document.getElementById('password_confirm');
        const password = document.getElementById('password');
        
        passwordConfirm.addEventListener('input', function() {
            if (this.value && password.value) {
                if (this.value === password.value) {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#ef4444';
                }
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });
    </script>
</body>
</html>
