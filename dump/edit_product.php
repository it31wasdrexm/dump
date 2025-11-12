<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$product_id = $_GET['id'] ?? 0;
$product = ['name' => '', 'price' => 0, 'image_path' => '', 'category' => null];
$sizes = [];
$all_sizes = ['S', 'M', 'L', 'XL']; 

if ($product_id) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category = c.id WHERE p.id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
   
    $stmt = $pdo->prepare("SELECT * FROM product_sizes WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $existing_sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    

    foreach ($all_sizes as $size) {
        $found = false;
        foreach ($existing_sizes as $existing) {
            if ($existing['size'] == $size) {
                $sizes[$size] = $existing;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $sizes[$size] = ['size' => $size, 'quantity' => 0, 'id' => null];
        }
    }
}


$categories = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    
    try {
        $pdo->beginTransaction();
        
      
        $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, category = ? WHERE id = ?");
        $stmt->execute([$name, $price, $category, $product_id]);
        
       
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('product_') . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                
                if (!empty($product['image_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $product['image_path'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $product['image_path']);
                }
                
               
                $image_path = '/uploads/' . $file_name;
                $stmt = $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?");
                $stmt->execute([$image_path, $product_id]);
            }
        }
        
       
        foreach ($_POST['sizes'] as $size => $quantity) {
            $quantity = (int)$quantity;
            
           
            $stmt = $pdo->prepare("SELECT id FROM product_sizes WHERE product_id = ? AND size = ?");
            $stmt->execute([$product_id, $size]);
            $size_exists = $stmt->fetch();
            
            if ($size_exists) {
                
                $stmt = $pdo->prepare("UPDATE product_sizes SET quantity = ? WHERE id = ?");
                $stmt->execute([$quantity, $size_exists['id']]);
            } else {
                
                if ($quantity > 0) {
                    $stmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$product_id, $size, $quantity]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'Товар успешно обновлен!';
        header('Location: admin.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Редактирование товара</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {
      font-family: 'Comic Sans MS', cursive, sans-serif;
    }
    .admin-container {
      max-width: 800px;
      margin: 40px auto;
      padding: 0 20px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
    }
    
    input, select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    
    .btn {
      padding: 10px 20px;
      background: #1a1a1a;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-right: 10px;
    }
    
    .size-input {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }
    
    .size-input input {
      width: 80px;
    }
    
    .image-preview {
      max-width: 200px;
      max-height: 200px;
      margin: 10px 0;
    }
    
    .file-upload {
      margin: 15px 0;
    }
  </style>
</head>
<body>

<div class="admin-container">
  <h1>Редактирование товара</h1>
  
  <?php if (!empty($errors)): ?>
    <div style="color: red; margin-bottom: 20px;">
      <?= implode('<br>', $errors) ?>
    </div>
  <?php endif; ?>
  
  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label>Название</label>
      <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
    </div>
    
    <div class="form-group">
      <label>Цена</label>
      <input type="number" name="price" step="0.01" value="<?= htmlspecialchars($product['price']) ?>" required>
    </div>
    
    <div class="form-group">
      <label>Категория</label>
      <select name="category" required>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $product['category'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="form-group">
      <label>Текущее изображение</label>
      <?php if (!empty($product['image_path'])): ?>
        <img src="<?= htmlspecialchars($product['image_path']) ?>" class="image-preview">
      <?php else: ?>
        <p>Изображение не загружено</p>
      <?php endif; ?>
    </div>
    
    <div class="form-group">
      <label>Новое изображение (оставьте пустым, чтобы не изменять)</label>
      <div class="file-upload">
        <input type="file" name="image" id="image-upload" onchange="previewImage(this)">
      </div>
      <img id="image-preview" src="#" alt="Предпросмотр" style="display: none; max-width: 200px; max-height: 200px; margin-top: 10px;">
    </div>
    
    <div class="form-group">
      <label>Размеры и количество</label>
      <?php foreach ($sizes as $size): ?>
        <div class="size-input">
          <span><?= htmlspecialchars($size['size']) ?></span>
          <input type="number" name="sizes[<?= htmlspecialchars($size['size']) ?>]" 
                 value="<?= htmlspecialchars($size['quantity']) ?>" min="0">
        </div>
      <?php endforeach; ?>
    </div>
    
    <button type="submit" class="btn">Сохранить</button>
    <a href="admin.php" class="btn">Отмена</a>
  </form>
</div>

<script>
function previewImage(input) {
  const preview = document.getElementById('image-preview');
  const file = input.files[0];
  
  if (file) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
    }
    
    reader.readAsDataURL(file);
  } else {
    preview.style.display = 'none';
  }
}
</script>
</body>
</html>