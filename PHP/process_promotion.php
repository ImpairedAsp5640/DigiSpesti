<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = trim($_POST['product_name'] ?? '');
    $discount = trim($_POST['discount'] ?? '');
    $store = trim($_POST['store'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($productName) || empty($discount) || empty($store)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: product_promotions.php");
        exit();
    }
    
    $insertQuery = "INSERT INTO product_promotions (product_name, discount, store, description, added_by) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ssssi", $productName, $discount, $store, $description, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Product promotion added successfully!";
    } else {
        $_SESSION['error'] = "Error adding promotion: " . $conn->error;
    }
    
    header("Location: product_promotions.php");
    exit();
} else {
    header("Location: product_promotions.php");
    exit();
}
?>