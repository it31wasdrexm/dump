<?php
session_start();
require 'db_connect.php';

$product_id = $_GET['id'] ?? 1;
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name 
    FROM products p
    LEFT JOIN categories c ON p.category = c.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

$image_data = null;
$image_mime = 'image/png';

if (!empty($product['image_path'])) {
    $image_path = $_SERVER['DOCUMENT_ROOT'] . $product['image_path'];
    if (file_exists($image_path)) {
        $image_data = file_get_contents($image_path);
        $image_mime = mime_content_type($image_path);
    }
}

if (!$image_data) {
    $default_path = $_SERVER['DOCUMENT_ROOT'] . '/images/default.jpg';
    if (file_exists($default_path)) {
        $image_data = file_get_contents($default_path);
        $image_mime = mime_content_type($default_path);
    } else {
        $image_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $image_mime = 'image/png';
    }
}

$base64_image = 'data:' . $image_mime . ';base64,' . base64_encode($image_data);

$sizes = $pdo->prepare("SELECT * FROM product_sizes WHERE product_id = ? AND quantity > 0");
$sizes->execute([$product_id]);
$available_sizes = $sizes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&display=swap" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="css/product.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: -300px;
            background: black;
            color: white;
            padding: 20px;
            border: 2px solid white;
            border-radius: 5px;
            transition: right 0.5s ease-in-out;
            z-index: 1000;
        }

        .notification.show {
            right: 20px;
        }
        
        .category-badge {
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
            background-color: #fff;
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
        
        .container {
            display: flex;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            gap: 40px;
        }
        
        .left-column {
            flex: 1;
        }
        
        .right-column {
            flex: 1;
        }
        
        .product-description h1 {
            font-size: 32px;
            margin-bottom: 20px;
        }
        
        .product-price {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        
        .cable-config {
            margin-bottom: 30px;
        }
        
        .cable-choose {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .cable-choose button {
            padding: 10px 15px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .cable-choose button:hover {
            background: #e0e0e0;
        }
        
        .cart-btn {
            width: 50%;
            padding: 15px;
            background: black;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .cart-btn:hover {
            opacity: 0.9;
        }
        
        .left-column img {
            width: 100%;
            max-height: 600px;
            object-fit: contain;
            border: 1px solid white;
            border-radius: 8px;
            display: block; 
            background-color: white; 
            padding: 5px; 
        }
        
       
        .image-container {
            position: relative;
            background-color: #f9f9f9; 
            border-radius: 8px;
            overflow: hidden;
            min-height: 300px; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
.left-column img {
    max-width: 100% !important;
    height: auto !important;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: static !important;
    z-index: 100 !important;
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

    <main class="container">
        <div class="left-column">
            <div class="image-container">
                <div class="category-badge"><?= htmlspecialchars($product['category_name'] ?? '') ?></div>
                <img src="<?= $base64_image ?>" 
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     style="max-width: 100%; height: auto;"> 
            </div>
        </div>

        <div class="right-column">
            <div class="product-description">
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                <div class="product-price">
                    <span>₸<?= number_format($product['price'], 2) ?></span>
                </div>
            </div>

            <div class="product-configuration">
                <div class="cable-config">
                    <span>Размер: <span id="selectedSize">Не выбрано</span></span>
                    <div class="cable-choose">
                        <?php foreach ($available_sizes as $size): ?>
                            <button type="button" onclick="selectSize('<?= $size['size'] ?>')">
                                <?= $size['size'] ?> 
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form id="addToCartForm" method="POST" action="addtocart.php">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="hidden" name="size" id="selectedSizeInput" value="">
                    <button type="submit" class="cart-btn">Добавить в корзину</button>
                </form>
            </div>
        </div>
    </main>

    <div id="notification" class="notification">
        Товар добавлен в корзину!
    </div>

    <script>
        function selectSize(size) {
            document.getElementById("selectedSize").innerText = size;
            document.getElementById("selectedSizeInput").value = size;
        }
        
        document.getElementById('addToCartForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!document.getElementById('selectedSizeInput').value) {
                alert('Пожалуйста, выберите размер');
                return;
            }

            const formData = new FormData(e.target);
            
            try {
                const response = await fetch(e.target.action, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.text();
                
                if (response.ok) {
                    showNotification();
                } else {
                    alert('Ошибка: ' + result);
                }
            } catch (error) {
                console.error('Ошибка:', error);
                alert('Произошла ошибка соединения');
            }
        });

        function showNotification() {
            const notification = document.getElementById('notification');
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        window.addEventListener('load', function() {
            const img = document.querySelector('.left-column img');
            if (img) {
                console.log('Image dimensions:', img.naturalWidth + 'x' + img.naturalHeight);
                
                
                if (img.naturalWidth === 0) {
                    console.error('Изображение не загружено');
                    
                    const message = document.createElement('div');
                    message.style.position = 'absolute';
                    message.style.top = '50%';
                    message.style.left = '50%';
                    message.style.transform = 'translate(-50%, -50%)';
                    message.style.color = 'red';
                    message.style.fontWeight = 'bold';
                    message.textContent = 'Ошибка загрузки изображения';
                    
                    document.querySelector('.image-container').appendChild(message);
                }
            }
        });
    </script>
</body>
</html>