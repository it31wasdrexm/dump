<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dump";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    
    $paymentMethod = mysqli_real_escape_string($conn, $_POST['paymentMethod']);
    $cardNumber = mysqli_real_escape_string($conn, $_POST['cardNumber']);
    $cardholderName = mysqli_real_escape_string($conn, $_POST['cardholderName']);
    $cardDate = mysqli_real_escape_string($conn, $_POST['cardDate']);
    $cvv = mysqli_real_escape_string($conn, $_POST['cvv']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    
    $sqlCart = "SELECT * FROM cart WHERE user_id = $userId";
    $resultCart = $conn->query($sqlCart);
    $cartItems = array();

    if ($resultCart->num_rows > 0) {
        while ($rowCart = $resultCart->fetch_assoc()) {
            $cartItems[] = $rowCart;
        }
    } else {
        die("Корзина пуста");
    }

   
    mysqli_begin_transaction($conn);

    try {
        
        $sqlOrder = "INSERT INTO orders (user_id, payment_method, card_number, cardholder_name, card_date, cvv, 
                    country, city, address, email, phone, status) 
                    VALUES ('$userId', '$paymentMethod', '$cardNumber', '$cardholderName', '$cardDate', '$cvv',
                            '$country', '$city', '$address', '$email', '$phone', 'processing')";

        if (!mysqli_query($conn, $sqlOrder)) {
            throw new Exception("Ошибка создания заказа: " . mysqli_error($conn));
        }
        $orderId = mysqli_insert_id($conn);
        
 $initialStatus = 'processing';
    $historySql = "INSERT INTO order_status_history 
                  (order_id, old_status, new_status) 
                  VALUES ('$orderId', '', '$initialStatus')";
    mysqli_query($conn, $historySql);

       
        foreach ($cartItems as $item) {
            $product_id = $item['product_id'];
            $size = $item['size'];
            $quantity = $item['quantity'];
            
           
            $checkStmt = $conn->prepare("SELECT quantity FROM product_sizes 
                                        WHERE product_id = ? AND size = ?");
            $checkStmt->bind_param("is", $product_id, $size);
            $checkStmt->execute();
            $stock = $checkStmt->get_result()->fetch_assoc();
            
            if (!$stock || $stock['quantity'] < $quantity) {
                throw new Exception("Недостаточно товара: {$item['product_name']} (размер $size)");
            }
            
           
            $updateStmt = $conn->prepare("UPDATE product_sizes 
                                          SET quantity = quantity - ? 
                                          WHERE product_id = ? AND size = ?");
            $updateStmt->bind_param("iis", $quantity, $product_id, $size);
            if (!$updateStmt->execute()) {
                throw new Exception("Ошибка обновления склада: " . $conn->error);
            }
            
           
            $productName = $item['product_name'];
            $price = $item['price'];
            $imagePath = $item['image_path'];

            $sqlOrderDetails = "INSERT INTO orders_details (order_id, product_id, product_name, size, price, image_path, quantity) 
                              VALUES ('$orderId', '$product_id', '$productName', '$size', '$price', '$imagePath', '$quantity')";
            if (!mysqli_query($conn, $sqlOrderDetails)) {
                throw new Exception("Ошибка добавления деталей заказа: " . mysqli_error($conn));
            }
        }

       
        $sqlClearCart = "DELETE FROM cart WHERE user_id = $userId";
        if (!mysqli_query($conn, $sqlClearCart)) {
            throw new Exception("Ошибка очистки корзины: " . mysqli_error($conn));
        }

        
        mysqli_commit($conn);

        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        
        mysqli_rollback($conn);
        die($e->getMessage());
    }
}

mysqli_close($conn);
?>