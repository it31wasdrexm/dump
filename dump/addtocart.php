<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Необходимо авторизоваться');
}

$product_id = $_POST['product_id'] ?? 0;
$size = $_POST['size'] ?? '';


$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    die('Товар не найден');
}


$stmt = $pdo->prepare("SELECT * FROM product_sizes WHERE product_id = ? AND size = ? AND quantity > 0");
$stmt->execute([$product_id, $size]);
$size_info = $stmt->fetch();

if (!$size_info) {
    die('Выбранный размер недоступен');
}


$stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND size = ?");
$stmt->execute([$_SESSION['user_id'], $product_id, $size]);
$existing_item = $stmt->fetch();

if ($existing_item) {
 
    $new_quantity = $existing_item['quantity'] + 1;
    if ($new_quantity > $size_info['quantity']) {
        die('Недостаточно товара на складе');
    }
    
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->execute([$new_quantity, $existing_item['id']]);
} else {
  
    $stmt = $pdo->prepare("INSERT INTO cart 
        (user_id, product_id, product_name, product_code, size, price, quantity, image_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $product_id,
        $product['name'],
        '', 
        $size,
        $product['price'],
        1,
        $product['image_path']
    ]);
}

echo 'Товар добавлен в корзину';
?>