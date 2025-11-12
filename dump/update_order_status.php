<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || !($_SESSION['is_admin'] ?? false)) {
    die('Доступ запрещен');
}

$order_id = $_POST['order_id'] ?? 0;
$new_status = $_POST['new_status'] ?? '';

if (!$order_id || !$new_status) {
    die('Неверные параметры');
}

try {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $current_status = $stmt->fetchColumn();
    
    $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $updateStmt->execute([$new_status, $order_id]);

    $historyStmt = $pdo->prepare("
        INSERT INTO order_status_history 
        (order_id, old_status, new_status, changed_by) 
        VALUES (?, ?, ?, ?)
    ");
    $historyStmt->execute([
        $order_id,
        $current_status,
        $new_status,
        $_SESSION['user_id']
    ]);
    
    $_SESSION['status_update_success'] = "Статус заказа #$order_id обновлен на '$new_status'";
} catch (PDOException $e) {
    $_SESSION['status_update_error'] = "Ошибка: " . $e->getMessage();
}

header("Location: admin.php");
exit;