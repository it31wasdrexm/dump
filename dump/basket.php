<?php
session_start();
require 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


$stmtCartCount = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
$stmtCartCount->execute([$_SESSION['user_id']]);
$cartCount = $stmtCartCount->fetchColumn() ?: 0;


$stmt = $pdo->prepare("
    SELECT
        c.id AS cart_id,
        c.product_id,
        c.product_name,
        c.size,
        c.price,
        c.quantity,
        c.image_path,
        ps.quantity AS stock_quantity
    FROM cart c
    LEFT JOIN product_sizes ps
        ON c.product_id = ps.product_id
        AND c.size = ps.size
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


$totalPrice = 0;
foreach ($items as $row) {
    $totalPrice += $row['price'] * $row['quantity'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Корзина | DUMP</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: 'Comic Sans MS', cursive, sans-serif;
      background: #fff;
      color: #000;
    }
    
   
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

 
    .cart-container {
      max-width: 1200px;
      margin: 4rem auto;
      padding: 0 5%;
    }
    .cart-header {
      text-align: center;
      margin-bottom: 3rem;
    }
    .cart-header h1 {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      position: relative;
      display: inline-block;
    }
    .cart-header h1::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 3px;
      background: #000;
    }
    .cart-items {
      display: grid;
      gap: 2rem;
    }
    .cart-item {
      display: grid;
      grid-template-columns: 120px 1fr auto;
      gap: 2rem;
      padding: 2rem;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.05);
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .cart-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .item-image {
      width: 100%;
      height: 140px;
      object-fit: cover;
      border-radius: 8px;
      border: 2px solid #f5f5f5;
    }
    .item-details {
      display: flex;
      flex-direction: column;
      gap: 0.8rem;
    }
    .item-title {
      font-size: 1.2rem;
      font-weight: 700;
    }
    .item-meta {
      color: #666;
      font-size: 0.9rem;
    }
    .item-actions {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 1rem;
    }
    .remove-btn {
      background: none;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #ff4444;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      transition: background 0.3s;
    }
    .remove-btn:hover {
      background: rgba(255, 68, 68, 0.1);
    }
    .cart-summary {
      margin-top: 3rem;
      padding-top: 2rem;
      border-top: 2px solid #f5f5f5;
      text-align: right;
    }
    .total-price {
      font-size: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .checkout-btn {
      background: #000;
      color: #fff;
      padding: 1rem 3rem;
      border: none;
      border-radius: 30px;
      cursor: pointer;
      font-size: 1rem;
      transition: transform 0.3s, opacity 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 0.8rem;
      text-decoration: none;
    }
    .checkout-btn:disabled {
      background: #999;
      cursor: not-allowed;
    }
    .checkout-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      opacity: 0.9;
    }
    .empty-cart {
      text-align: center;
      padding: 4rem 0;
    }
    @media (max-width: 768px) {
      .cart-item {
        grid-template-columns: 1fr;
        text-align: center;
      }
      .item-actions {
        align-items: center;
      }
      .item-image {
        height: 200px;
      }
    }
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

  <main class="cart-container">
    <div class="cart-header">
      <h1>Ваша корзина</h1>
    </div>

    <?php if (!empty($items)): ?>
      <div class="cart-items">
        <?php foreach ($items as $item): ?>
          <div class="cart-item">
            <img src="<?= htmlspecialchars($item['image_path']) ?>" class="item-image" alt="<?= htmlspecialchars($item['product_name']) ?>">
            <div class="item-details">
              <h3 class="item-title"><?= htmlspecialchars($item['product_name']) ?></h3>
              <p class="item-meta">Размер: <?= htmlspecialchars($item['size']) ?></p>
              <p class="item-meta">Цена: ₸<?= number_format($item['price'], 2) ?></p>
              <p class="item-meta">Кол-во: <?= $item['quantity'] ?></p>
              <p class="item-meta">В наличии: <?= $item['stock_quantity'] ?></p>
            </div>
            <div class="item-actions">
              <form method="POST" action="removefromcart.php">
                <input type="hidden" name="remove_id" value="<?= $item['cart_id'] ?>">
                <button type="submit" class="remove-btn">
                  <i class="fas fa-trash-alt"></i> Удалить
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="cart-summary">
        <h2 class="total-price">Итог: ₸<?= number_format($totalPrice, 2) ?></h2>
        <a href="checkout.php" class="checkout-btn">
          <i class="fas fa-credit-card"></i> Оформить заказ
        </a>
      </div>
    <?php else: ?>
      <div class="empty-cart">
        <h2>Корзина пуста</h2>
        <a href="index.php" style="
            display: inline-block;
            margin-top: 1rem;
            color: #000;
            text-decoration: none;
            border: 2px solid #000;
            padding: 0.5rem 1rem;
          ">
          <i class="fas fa-arrow-left"></i> Вернуться к покупкам
        </a>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>