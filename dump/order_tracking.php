<?php
session_start();
require 'db_connect.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$order_id = $_GET['id'] ?? 0;

try {
  
  $stmt = $pdo->prepare("
    SELECT o.*, u.login AS user_login,
      (SELECT SUM(od.price * od.quantity) FROM orders_details od WHERE od.order_id = o.id) total
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = :order_id
  ");
  $stmt->execute(['order_id'=>$order_id]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: die("Заказ не найден");

  if ($_SESSION['user_id']!=$order['user_id'] && !($_SESSION['is_admin']??false))
    die("Доступ запрещен");

  $historyStmt = $pdo->prepare("
    SELECT h.*, u.login AS by_name
    FROM order_status_history h
    LEFT JOIN users u ON u.id=h.changed_by
    WHERE h.order_id=:order_id
    ORDER BY h.changed_at DESC
  ");
  $historyStmt->execute(['order_id'=>$order_id]);
  $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

  $itemsStmt = $pdo->prepare("
    SELECT od.quantity, od.price, p.name, p.image_path
    FROM orders_details od
    JOIN products p ON p.id=od.product_id
    WHERE od.order_id=:order_id
  ");
  $itemsStmt->execute(['order_id'=>$order_id]);
  $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

 
  $stmtCartCount = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
  $stmtCartCount->execute([$_SESSION['user_id']]);
  $cartCount = $stmtCartCount->fetchColumn() ?: 0;

} catch(PDOException $e) { die("Ошибка: ".$e->getMessage()); }

function translateStatus($s){
  $m=['pending'=>'Ожидает','processing'=>'В обработке',
       'shipped'=>'В пути','delivered'=>'Доставлен',
       'cancelled'=>'Отменён','cancel_request'=>'Запрос на отмену'];
  return $m[$s] ?? $s;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Заказ #<?= $order_id ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  body { margin:0; font-family: 'Comic Sans MS', cursive, sans-serif; background:#fff; color:#000; }
  
  
  .main-header {
    background: white;
    color: black;
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  
  .header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }
  
  .logo {
    font-size: 28px;
    font-weight: bold;
    letter-spacing: 1px;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
  }
  
  .logo a {
    color: black;
    text-decoration: none;
    transition: all 0.3s ease;
  }
  
  .logo a:hover {
    color: black;
  }
  
  .header-icons {
    display: flex;
    gap: 20px;
    margin-left: auto;
  }
  
  .icon-link {
    color: black;
    font-size: 20px;
    position: relative;
  }
  
  .icon-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #ff5252;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  
  .container { max-width:800px; margin:30px auto; padding:0 20px; }
  .status-bar { display:flex; justify-content:space-between; align-items:center;
    margin:30px 0; position:relative;
  }
  .status-bar::before {
    content:''; position:absolute; top:50%; left:0; right:0; height:2px; background:#eee; z-index:0;
  }
  .status-step { position:relative; z-index:1; text-align:center; flex:1; }
  .status-bubble {
    width:30px; height:30px; border-radius:50%; background:#eee; color:#888;
    margin:0 auto 8px; display:flex; align-items:center; justify-content:center;
    font-weight:500;
  }
  .status-completed .status-bubble,
  .status-active .status-bubble {
    background:#000; color:#fff;
  }
  .status-label { font-size:13px; }
  .history-item { margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid #eee; }
  .history-date { font-size:12px; color:#555; margin-top:5px; }
  table { width:100%; border-collapse:collapse; margin-top:20px; }
  th, td { text-align:left; padding:10px; border-bottom:1px solid #f0f0f0; }
  th { font-weight:600; }
  img.product-img { width:50px; height:auto; border-radius:4px; }
  h1, h2, h3 { margin-top:30px; font-weight:600; }
</style>
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
  <h1>Отслеживание заказа #<?= $order_id ?></h1>

  <div class="status-bar">
    <?php
    $steps=['processing','shipped','delivered'];
    $cur=$order['status'];
    foreach($steps as $i=>$s):
      $active = $s==$cur;
      $completed = array_search($cur,$steps)>$i;
      $cls = $completed?'status-completed':($active?'status-active':'');
    ?>
      <div class="status-step <?= $cls ?>">
        <div class="status-bubble"><?= $i+1 ?></div>
        <div class="status-label"><?= translateStatus($s) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>Текущий статус: <?= translateStatus($order['status']) ?></h2>

  <h3>История изменений</h3>
  <?php foreach($history as $h): ?>
    <div class="history-item">
      <div>
        <?= $h['old_status'] ? "Сменён с <b>".translateStatus($h['old_status'])."</b> на <b>".translateStatus($h['new_status'])."</b>" :
           "Установлен статус <b>".translateStatus($h['new_status'])."</b>"; ?>
      </div>
      <div class="history-date">
        <?= date('d.m.Y H:i', strtotime($h['changed_at'])) ?>
        <?= $h['by_name'] ? "| Изменил: ".htmlspecialchars($h['by_name']) : "" ?>
      </div>
    </div>
  <?php endforeach; ?>

  <h3>Содержимое заказа</h3>
  <table>
    <thead>
      <tr><th>Товар</th><th></th><th>Цена</th><th>Кол-во</th><th>Итого</th></tr>
    </thead>
    <tbody>
      <?php foreach($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['name']) ?></td>
          <td><img src="<?= htmlspecialchars($it['image_path']) ?>" class="product-img"></td>
          <td>₸<?= number_format($it['price'],2) ?></td>
          <td><?= $it['quantity'] ?></td>
          <td>₸<?= number_format($it['price']*$it['quantity'],2) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="4" style="text-align:right;font-weight:600;">Итого:</td>
        <td style="font-weight:600;">₸<?= number_format($order['total'],2) ?></td>
      </tr>
    </tbody>
  </table>
</div>

</body>
</html>