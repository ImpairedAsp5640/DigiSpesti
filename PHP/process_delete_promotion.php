<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $promotionId = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
    
    if ($promotionId <= 0) {
        $_SESSION['error'] = "Invalid promotion ID.";
        header("Location: product_promotions.php");
        exit();
    }
    
    $deleteQuery = "DELETE FROM product_promotions WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $promotionId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Promotion deleted successfully!";
        } else {
            $_SESSION['error'] = "Promotion not found or already deleted.";
        }
    } else {
        $_SESSION['error'] = "Error deleting promotion: " . $conn->error;
    }
    
    header("Location: product_promotions.php");
    exit();
} else {
    header("Location: product_promotions.php");
    exit();
}
?>