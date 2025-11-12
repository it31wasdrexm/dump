<?php
session_start();
require 'db_connect.php'; 

$stmt = $pdo->query("
    SELECT p.*, c.name AS category_name, 
    (SELECT SUM(quantity) FROM product_sizes WHERE product_id = p.id) AS total_quantity
    FROM products p
    LEFT JOIN categories c ON p.category = c.id
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .sold-out {
      position: relative;
      opacity: 0.7;
    }
    
    .sold-out::after {
      content: "SOLD OUT";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(0,0,0,0.7);
      color: white;
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 12px;
    }
    
    .card {
      position: relative;
    }
    
    .card-category {
      position: absolute;
      top: 10px;
      left: 10px;
      background: rgba(0,0,0,0.7);
      color: white;
      padding: 3px 6px;
      border-radius: 4px;
      font-size: 12px;
    }
    
    .main-header {
   
      color: black;
      padding: 15px 0;
      position: sticky;
      top: 0;
      z-index: 100;
   ;
    }
    
    .header-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 80px;
      margin: 0 auto;
      padding: 0 20px;
    }
    
    .logo {
      font-size: 28px;
      font-weight: bold;
      letter-spacing: 1px;
    }
    
    .logo a {
      color: black;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    
    .logo a:hover {
      color: black;
    }
    
    .nav-links {
      display: flex;
      gap: 30px;
    }
    
    .nav-links a {
      color: white;
      text-decoration: none;
      font-size: 16px;
      transition: all 0.3s ease;
    }
    
    .nav-links a:hover {
      color: black;
    }
    
    .header-icons {
      display: flex;
      gap: 20px;
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
        <?php
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cartCount = $stmt->fetchColumn() ?: 0;
}
?>

<span class="icon-badge"><?= $cartCount ?></span>
      </a>
    </div>
  </div>
</header>

<nav>
  <h1>SS1</h1>
</nav>

<div class="card-container">
  <?php foreach ($products as $product): ?>
    <div class="card" id="product<?= $product['id'] ?>">
      <a href="product.php?id=<?= $product['id'] ?>">
        <div class="card-category"><?= htmlspecialchars($product['category_name'] ?? '') ?></div>
        <img src="<?= htmlspecialchars($product['image_path']) ?>" 
             alt="<?= htmlspecialchars($product['name']) ?>"
             class="<?= ($product['total_quantity'] ?? 0) <= 0 ? 'sold-out' : '' ?>">
      </a>
      <nav><?= htmlspecialchars($product['name']) ?></nav>
      <p>₸<?= number_format($product['price'], 2) ?></p>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>