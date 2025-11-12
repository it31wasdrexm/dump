<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dump";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $removeId = (int)$_POST['remove_id'];
    
    $sql = "SELECT product_id, size, quantity 
            FROM cart 
            WHERE id = $removeId 
            AND user_id = {$_SESSION['user_id']}";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        
        $conn->query("DELETE FROM cart WHERE id = $removeId");
        
        $conn->query("
            UPDATE product_sizes 
            SET quantity = quantity + {$item['quantity']} 
            WHERE product_id = {$item['product_id']} 
            AND size = '{$conn->real_escape_string($item['size'])}'
        ");
    }
    
    header('Location: basket.php');
    exit;
}

$conn->close();
?>