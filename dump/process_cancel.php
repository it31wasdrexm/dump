<?php
session_start();
require 'db_connect.php';


if (!isset($_SESSION['user_id']) || !($_SESSION['is_admin'] ?? false)) {
    die('Доступ запрещен');
}

$order_id = $_POST['order_id'] ?? 0;
$action = $_POST['action'] ?? '';

try {
    $pdo->beginTransaction();

    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception("Заказ не найден");
    }

    if ($action === 'approve') {
       
        $stmt = $pdo->prepare("SELECT * FROM orders_details WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();

        foreach ($order_items as $item) {
            $update_stmt = $pdo->prepare("
                UPDATE product_sizes 
                SET quantity = quantity + ? 
                WHERE product_id = ? AND size = ?
            ");
            $update_stmt->execute([$item['quantity'], $item['product_id'], $item['size']]);
        }

        
        $update_order = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $update_order->execute([$order_id]);
        
        $_SESSION['cancel_success'] = "Заказ #$order_id отменен. Товары возвращены на склад";

    } elseif ($action === 'reject') {
       
        $update_order = $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
        $update_order->execute([$order_id]);
        $_SESSION['cancel_success'] = "Запрос на отмену заказа #$order_id отклонен";
        
    } elseif ($action === 'update_status') {
       
        $new_status = $_POST['new_status'] ?? '';
        $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (!in_array($new_status, $valid_statuses)) {
            throw new Exception("Недопустимый статус");
        }
        
        $update_order = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $update_order->execute([$new_status, $order_id]);
        $_SESSION['cancel_success'] = "Статус заказа #$order_id обновлен на '$new_status'";
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['cancel_error'] = "Ошибка: " . $e->getMessage();
}

header("Location: admin.php");
exit();
?>