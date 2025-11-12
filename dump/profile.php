<?php
session_start();

$cartCount = 0;
try {
    $pdo = new PDO("mysql:host=localhost;dbname=dump;charset=utf8", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cartCount = $stmt->fetchColumn() ?: 0; 
    }
} catch (PDOException $e) {
    $cartCount = 0; 
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dump";
$conn = mysqli_connect($servername, $username, $password, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

$is_admin = isset($user['is_admin']) && $user['is_admin'] == 1;

$orders_query = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY order_date DESC";
$orders_result = mysqli_query($conn, $orders_query);

$update_success = false;
$update_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_login = mysqli_real_escape_string($conn, $_POST['login']);
        $new_email = mysqli_real_escape_string($conn, $_POST['email']);
        $check_query = "SELECT id FROM users WHERE (login='$new_login' OR email='$new_email') AND id!=$user_id";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) > 0) {
            $update_error = "Логин или email уже заняты";
        } else {
            $upd = "UPDATE users SET login='$new_login', email='$new_email' WHERE id=$user_id";
            if (mysqli_query($conn, $upd)) {
                $update_success = "Данные обновлены!";
                $_SESSION['user_login'] = $new_login;
                $user = mysqli_fetch_assoc(mysqli_query($conn, $user_query));
            } else {
                $update_error = "Ошибка: " . mysqli_error($conn);
            }
        }
    }
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        if (password_verify($current, $user['password'])) {
            if ($new === $confirm && strlen($new)>=6) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $upd2 = "UPDATE users SET password='$hash' WHERE id=$user_id";
                if (mysqli_query($conn, $upd2)) {
                    $update_success = "Пароль изменен!";
                } else {
                    $update_error = "Ошибка: " . mysqli_error($conn);
                }
            } else {
                $update_error = "Пароли не совпадают или слишком короткие";
            }
        } else {
            $update_error = "Неверный текущий пароль";
        }
    }
    if (isset($_POST['cancel_order'])) {
        $oid = (int)$_POST['order_id'];
        $upd3 = "UPDATE orders SET status='cancel_request' WHERE id=$oid AND user_id=$user_id";
        if (mysqli_query($conn, $upd3)) {
            $update_success = "Запрос на отмену заказа #$oid отправлен";
        } else {
            $update_error = "Ошибка запроса: " . mysqli_error($conn);
        }
    }
}
if (isset($_GET['logout'])) {
    session_destroy();header('Location:index.php');exit;
}
if ($is_admin) {
    $users_result = mysqli_query($conn, "SELECT id,login,email,is_admin FROM users ORDER BY id");
}
function translateStatus($s) {
    $m=['pending'=>'Ожидает обработки','processing'=>'В обработке','shipped'=>'В пути','delivered'=>'Доставлен','cancelled'=>'Отменен','cancel_request'=>'Ожидает отмены'];
    return $m[$s] ?? $s;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="css/profile.css">
<title>Профиль <?=htmlspecialchars($user['login'])?></title>
</head>
<body>
<header class="main-header">
  <div class="header-container">
    <div class="logo">
      <a href="index.php">DUMP</a>
    </div>
    
    <div class="header-icons">
      <a href="<?= isset($_SESSION['user_id']) ? 'profile.php' : 'login.php'; ?>" class="icon-link">
        <i class="fas fa-user"></i>
      </a>
      <a href="basket.php" class="icon-link">
        <i class="fas fa-shopping-bag"></i>
        <span class="icon-badge"><?= $cartCount ?></span> 
      </a>
    </div>
  </div>
</header>
<div class="container">
  <div class="profile-header">
    <h1><?= htmlspecialchars($user['login']) ?></h1>
    <button onclick="location='?logout=1'" class="logout-btn">Выйти</button>
  </div>
  <?php if ($update_success): ?><div class="alert success"><i class="fas fa-check-circle"></i> <?= $update_success ?></div><?php endif; ?>
  <?php if ($update_error): ?><div class="alert error"><i class="fas fa-exclamation-circle"></i> <?= $update_error ?></div><?php endif; ?>
  <div class="profile-content">
    <div class="profile-sidebar">
      <div class="profile-avatar"><?= strtoupper(substr($user['login'],0,1)) ?></div>
      <?php if ($is_admin): ?><a href="admin.php" class="admin-panel-btn"><i class="fas fa-tools"></i> Админ-панель</a><?php endif; ?>
      <div class="info-section">
        <h3>Основная информация</h3>
        <div class="static-value">
          <span><?= htmlspecialchars($user['login']) ?></span>
          <button class="edit-btn" onclick="toggleEdit('login')">Изменить</button>
        </div>
        <div class="static-value">
          <span><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <form method="POST" class="edit-form" id="login-form">
          <input type="text" name="login" value="<?= htmlspecialchars($user['login']) ?>" required placeholder="Логин">
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required placeholder="Email">
          <div class="form-actions">
            <button type="button" class="btn btn-danger" onclick="toggleEdit('login')">Отмена</button>
            <button type="submit" name="update_profile" class="btn btn-primary">Сохранить</button>
          </div>
        </form>
      </div>
      <div class="info-section">
        <h3>Безопасность</h3>
        <div class="static-value">
          <span>••••••••</span>
          <button class="edit-btn" onclick="toggleEdit('password')">Изменить</button>
        </div>
        <form method="POST" class="edit-form" id="password-form">
          <input type="password" name="current_password" placeholder="Текущий пароль" required>
          <input type="password" name="new_password" placeholder="Новый пароль" required>
          <input type="password" name="confirm_password" placeholder="Подтвердите пароль" required>
          <div class="form-actions">
            <button type="button" class="btn btn-danger" onclick="toggleEdit('password')">Отмена</button>
            <button type="submit" name="change_password" class="btn btn-primary">Сохранить</button>
          </div>
        </form>
      </div>
    </div>
    <div class="main-content">
      <h2 class="section-title">История заказов</h2>
      <?php if (mysqli_num_rows($orders_result) > 0): ?>
        <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
          <div class="order-card">
            <div class="order-header">
              <p><strong>Заказ #<?= $order['id'] ?></strong></p>
              <p>Дата: <?= date('d.m.Y H:i',strtotime($order['order_date'])) ?></p>
              <p>Статус: <span><?= translateStatus($order['status']) ?></span></p>
            </div>
            <?php $items_q="SELECT * FROM orders_details WHERE order_id={$order['id']}"; $items_r=mysqli_query($conn,$items_q); while($item=mysqli_fetch_assoc($items_r)): ?>
              <div class="order-item">
                <?php if($item['image_path']): ?><img src="<?= htmlspecialchars($item['image_path']) ?>" alt=""><?php endif; ?>
                <div>
                  <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                  <p>Размер: <?= htmlspecialchars($item['size']) ?></p>
                  <p>Цена: $<?= number_format($item['price'],2) ?></p>
                  <p>Кол-во: <?= $item['quantity'] ?></p>
                </div>
              </div>
            <?php endwhile; ?>
            <div class="order-actions">
              <?php if (!in_array($order['status'], ['cancelled','delivered','cancel_request'])): ?>
                <form method="POST"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><button type="submit" name="cancel_order" class="btn btn-danger">Отменить заказ</button></form>
              <?php endif; ?>
              <a href="order_tracking.php?id=<?= $order['id'] ?>" class="btn btn-primary">Отследить</a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?><p>У вас пока нет заказов.</p><?php endif; ?>
      <?php if ($is_admin): ?>
        <div class="admin-section">
          <h2 class="section-title">Запросы на отмену заказов</h2>
          <?php $req_q="SELECT * FROM orders WHERE status='cancel_request'"; $req_r=mysqli_query($conn,$req_q); if(mysqli_num_rows($req_r)>0): while($req=mysqli_fetch_assoc($req_r)): ?>
            <div class="order-card">
              <div class="order-header">
                <p><strong>Заказ #<?= $req['id'] ?></strong></p>
                <p>Дата: <?= date('d.m.Y H:i',strtotime($req['order_date'])) ?></p>
              </div>
              <div class="order-actions">
                <form method="POST" action="process_cancel.php"><input type="hidden" name="order_id" value="<?= $req['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-danger">Подтвердить</button></form>
                <form method="POST" action="process_cancel.php"><input type="hidden" name="order_id" value="<?= $req['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-primary">Отклонить</button></form>
              </div>
            </div>
          <?php endwhile; else: ?><p>Нет запросов.</p><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
  function toggleEdit(type) {
    const form = document.getElementById(type+'-form');
    const val = form.previousElementSibling;
    if (form.style.display==='flex') { 
        form.style.display='none'; 
        val.style.display='flex'; 
    } else { 
        form.style.display='flex'; 
        val.style.display='none'; 
    }
  }
  
  document.querySelectorAll('.btn, .edit-btn, .admin-panel-btn').forEach(btn => {
    btn.addEventListener('mouseenter', function() {
      this.style.transform = 'scale(1.03)';
    });
    btn.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
    });
  });
</script>
</body>
</html>
<?php mysqli_close($conn); ?>