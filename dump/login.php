<?php
session_start();


$host = 'localhost';
$dbname = 'dump';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

$errors = [];
$success = '';
$activeForm = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $login = trim($_POST['login'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

       
        if (empty($login) || empty($email) || empty($password) || empty($confirm)) {
            $errors[] = 'Все поля обязательны для заполнения';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        } elseif ($password !== $confirm) {
            $errors[] = 'Пароли не совпадают';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать минимум 6 символов';
        }

        if (empty($errors)) {
            try {
               
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR login = ?");
                $stmt->execute([$email, $login]);
                
                if ($stmt->rowCount() > 0) {
                    $errors[] = 'Пользователь с таким email или логином уже существует';
                } else {
                    
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (login, email, password) VALUES (?, ?, ?)");
                    $stmt->execute([$login, $email, $hashed]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = 'Регистрация прошла успешно. Теперь вы можете войти.';
                        $activeForm = 'login';
                    } else {
                        $errors[] = 'Ошибка при регистрации. Попробуйте ещё раз.';
                    }
                }
            } catch (PDOException $e) {
                error_log("Ошибка регистрации: " . $e->getMessage());
                $errors[] = 'Произошла ошибка при регистрации';
            }
        } else {
            $activeForm = 'register';
        }
    }

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = 'Введите email и пароль';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_login'] = $user['login'];
                    $_SESSION['is_admin'] = ($user['is_admin'] == 1);

                    header("Location: index.php");
                    exit;
                } else {
                    $errors[] = 'Неверный email или пароль';
                }
            } catch (PDOException $e) {
                error_log("Ошибка входа: " . $e->getMessage());
                $errors[] = 'Произошла ошибка при входе';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход / Регистрация</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
            --dark-gray: #e0e0e0;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--white);
            color: var(--black);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            line-height: 1.5;
        }
        
        .container {
            width: 100%;
            max-width: 440px;
        }
        
        .form-box {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--dark-gray);
        }
        
        .toggle-buttons {
            display: flex;
            border-bottom: 1px solid var(--dark-gray);
        }
        
        .toggle-buttons button {
            flex: 1;
            padding: 16px;
            background: var(--white);
            border: none;
            color: var(--black);
            font-weight: 500;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .toggle-buttons button.active {
            color: var(--black);
            font-weight: 600;
        }
        
        .toggle-buttons button.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--black);
        }
        
        .toggle-buttons button:not(.active):hover {
            background: var(--gray);
        }
        
        .form-content {
            padding: 32px;
        }
        
        .form {
            display: none;
            flex-direction: column;
            gap: 20px;
        }
        
        .form.active {
            display: flex;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--black);
        }
        
        .form input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--dark-gray);
            border-radius: 8px;
            font-size: 15px;
            transition: var(--transition);
            background: var(--white);
            color: var(--black);
        }
        
        .form input:focus {
            outline: none;
            border-color: var(--black);
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
        }
        
        .form button {
            width: 100%;
            padding: 14px;
            background: var(--black);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }
        
        .form button:hover {
            opacity: 0.9;
        }
        
        .message {
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .message.error {
            background: #fff0f0;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        
        .message.success {
            background: #f0fff4;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        @media (max-width: 480px) {
            .form-content {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-box">
            <div class="toggle-buttons">
                <button class="<?= $activeForm === 'login' ? 'active' : '' ?>" onclick="switchForm('login')">Вход</button>
                <button class="<?= $activeForm === 'register' ? 'active' : '' ?>" onclick="switchForm('register')">Регистрация</button>
            </div>
            
            <div class="form-content">
                <?php if (!empty($errors)): ?>
                    <div class="message error"><?= implode('<br>', $errors) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="message success"><?= $success ?></div>
                <?php endif; ?>
                
                <form method="POST" id="login" class="form <?= $activeForm === 'login' ? 'active' : '' ?>">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Пароль" required>
                    </div>
                    <button type="submit">Войти</button>
                </form>
                
                <form method="POST" id="register" class="form <?= $activeForm === 'register' ? 'active' : '' ?>">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <input type="text" name="login" placeholder="Имя пользователя" required value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Пароль (минимум 6 символов)" required minlength="6">
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm" placeholder="Повторите пароль" required minlength="6">
                    </div>
                    <button type="submit">Зарегистрироваться</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchForm(form) {
            document.getElementById('login').classList.remove('active');
            document.getElementById('register').classList.remove('active');
            
            const buttons = document.querySelectorAll('.toggle-buttons button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.querySelector(`.toggle-buttons button[onclick*="${form}"]`).classList.add('active');
            document.getElementById(form).classList.add('active');
        }
    </script>
</body>
</html>