<?php
session_start();

if (!isset($_SESSION['user_id']) || !($_SESSION['is_admin'] ?? false)) {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'dump';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

$errors = [];
$products = [];
$success = '';


if (isset($_SESSION['cancel_success'])) {
    $success = $_SESSION['cancel_success'];
    unset($_SESSION['cancel_success']);
}

if (isset($_SESSION['cancel_error'])) {
    $errors[] = $_SESSION['cancel_error'];
    unset($_SESSION['cancel_error']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = htmlspecialchars($_POST['category_name']);
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        $success = 'Категория успешно добавлена!';
    } catch (PDOException $e) {
        $errors[] = 'Ошибка добавления категории: ' . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        $name = htmlspecialchars($_POST['name']);
        $price = (float)$_POST['price'];
        $category = (int)$_POST['category'];
        $image_path = '';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('product_') . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = '/uploads/' . $file_name;
            } else {
                throw new Exception('Ошибка загрузки изображения');
            }
        }

        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO products (name, price, image_path, category) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $price, $image_path, $category]);
        $product_id = $pdo->lastInsertId();

        
        foreach ($_POST['sizes'] as $size => $quantity) {
            if ($quantity > 0) {
                $stmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$product_id, $size, $quantity]);
            }
        }

        $pdo->commit();
        $success = 'Товар успешно добавлен!';
        header("Refresh:2");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}


if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Товар успешно удалён!';
        header("Refresh:2");
    } catch (PDOException $e) {
        $errors[] = 'Ошибка удаления товара: ' . $e->getMessage();
    }
}


try {
    $stmt = $pdo->query("
        SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category = c.id 
        ORDER BY p.id DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
   
    $categories = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Ошибка загрузки данных: ' . $e->getMessage();
}


$cancel_requests = [];
$status_stats = [];
$test_result = null;

try {
  
    $stmt = $pdo->query("
        SELECT 
            o.id AS order_id,
            o.order_date,
            o.status,
            u.login AS user_login,
            COUNT(od.id) AS items_count,
            SUM(od.price * od.quantity) AS total_amount
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN orders_details od ON o.id = od.order_id
        WHERE o.status = 'cancel_request'
        GROUP BY o.id
        ORDER BY o.order_date DESC
    ");
    $cancel_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
   
    $stats_stmt = $pdo->query("
        SELECT status, COUNT(*) AS count 
        FROM orders 
        GROUP BY status
    ");
    $status_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
  
    $test_stmt = $pdo->query("
        SELECT id, status 
        FROM orders 
        WHERE status = 'cancel_request'
        LIMIT 1
    ");
    $test_result = $test_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errors[] = 'Ошибка загрузки запросов на отмену: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | DUMP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Админ-панель</h1>
            <a href="index.php" class="btn">Вернуться на сайт</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <?= implode('<br>', $errors) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h2 class="form-title">Запросы на отмену заказов</h2>
            
            <?php if (!empty($cancel_requests)): ?>
                <div class="cancel-requests">
                    <?php foreach ($cancel_requests as $request): ?>
                        <div class="request-card">
                            <div class="request-info">
                                <p><strong>Заказ #<?= $request['order_id'] ?></strong></p>
                                <p>Пользователь: <?= htmlspecialchars($request['user_login']) ?></p>
                                <p>Дата: <?= date('d.m.Y H:i', strtotime($request['order_date'])) ?></p>
                                <p>Товаров: <?= $request['items_count'] ?></p>
                                <p>Сумма: $<?= number_format($request['total_amount'], 2) ?></p>
                            </div>
                            <div class="request-actions">
                                <form method="POST" action="process_cancel.php">
                                    <input type="hidden" name="order_id" value="<?= $request['order_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve">Подтвердить отмену</button>
                                </form>
                                <form method="POST" action="process_cancel.php">
                                    <input type="hidden" name="order_id" value="<?= $request['order_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject">Отклонить запрос</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Нет запросов на отмену заказов</p>
            <?php endif; ?>
        </div>
<div class="form-section">
    <h2 class="form-title">Управление статусами заказов</h2>
    
    <?php
    try {
        $ordersStmt = $pdo->query("
            SELECT o.id, o.order_date, o.status, u.login AS user_login
            FROM orders o
            JOIN users u ON o.user_id = u.id
            ORDER BY o.order_date DESC
            LIMIT 10
        ");
        $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = 'Ошибка загрузки заказов: ' . $e->getMessage();
    }
    ?>
    
    <table class="products-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Пользователь</th>
                <th>Текущий статус</th>
                <th>Новый статус</th>
                <th>Действие</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= $order['id'] ?></td>
                <td><?= date('d.m.Y H:i', strtotime($order['order_date'])) ?></td>
                <td><?= htmlspecialchars($order['user_login']) ?></td>
                <td><?= $order['status'] ?></td>
                <td>
                    <form method="POST" action="update_order_status.php">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <select name="new_status">
                            <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Обработка</option>
                            <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>В пути</option>
                            <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Доставлен</option>
                        </select>
                </td>
                <td>
                    <button type="submit" class="btn">Обновить</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
        <div class="form-section">
            <h2 class="form-title">Добавить новую категорию</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Название категории</label>
                    <input type="text" name="category_name" required>
                </div>
                <button type="submit" class="btn" name="add_category" style="margin-top: 16px;">
                    Добавить категорию
                </button>
            </form>
        </div>

        <div class="form-section">
            <h2 class="form-title">Добавить новый товар</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="grid-form">
                    <div class="form-group">
                        <label>Название товара</label>
                        <input type="text" name="name" required>
                    </div>

                    <div class="form-group">
                        <label>Цена (₸)</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Категория</label>
                        <select name="category" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Размеры и количество</label>
                        <div class="sizes-grid">
                            <div class="size-input">
                                <label>S <input type="number" name="sizes[S]" value="0" min="0"></label>
                            </div>
                            <div class="size-input">
                                <label>M <input type="number" name="sizes[M]" value="0" min="0"></label>
                            </div>
                            <div class="size-input">
                                <label>L <input type="number" name="sizes[L]" value="0" min="0"></label>
                            </div>
                            <div class="size-input">
                                <label>XL <input type="number" name="sizes[XL]" value="0" min="0"></label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Изображение товара</label>
                        <div class="file-upload">
                            <input type="file" name="image" required onchange="previewImage(this)">
                            <div class="file-preview">
                                <span>Выберите изображение</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn" name="add_product" style="margin-top: 24px;">
                    Добавить товар
                </button>
            </form>
        </div>

        <div class="form-section">
            <h2 class="form-title">Список товаров</h2>
            <table class="products-table">
                <thead>
                    <tr>
                        <th>Изображение</th>
                        <th>Название</th>
                        <th>Цена</th>
                        <th>Категория</th>
                        <th>Наличие</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): 
                          
                            $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM product_sizes WHERE product_id = ?");
                            $stmt->execute([$product['id']]);
                            $total = $stmt->fetch()['total'] ?? 0;
                        ?>
                            <tr>
                                <td>
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_path']) ?>" 
                                             class="product-image <?= $total <= 0 ? 'sold-out' : '' ?>">
                                    <?php else: ?>
                                        <div class="no-image">Нет фото</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td>₸<?= number_format($product['price'], 2) ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'Без категории') ?></td>
                                <td>
                                    <?php if ($total > 0): ?>
                                        <span class="in-stock">✅ В наличии (<?= $total ?>)</span>
                                    <?php else: ?>
                                        <span class="out-of-stock">❌ Нет в наличии</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn">Изменить</a>
                                    <a href="?delete=<?= $product['id'] ?>" 
                                       class="btn btn-delete"
                                       onclick="return confirm('Вы уверены?')">Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-products">
                                <?php if (!empty($errors)): ?>
                                    <?= implode('<br>', $errors) ?>
                                <?php else: ?>
                                    Нет товаров для отображения
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = input.closest('.file-upload').querySelector('.file-preview');
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Предпросмотр">`;
            }

            if (file) {
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>