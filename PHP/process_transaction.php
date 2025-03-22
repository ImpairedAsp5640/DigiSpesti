<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionType = $_POST['transactionType'] ?? '';
    $category = $_POST['selectedCategory'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $date = $_POST['date'] ?? date('Y-m-d');
    $comment = $_POST['comment'] ?? '';
    $userId = $_SESSION['user_id'];
    $redirect = $_POST['redirect'] ?? 'index.php';
    
    if ($transactionType === 'savings' && $category === 'Непредвидени разходи' && empty($amount)) {
        $amount = 0;
        
        if (empty($comment)) {
            $comment = 'Emergency Fund contribution';
        }
    }
    
    $formattedDate = date('Y-m-d', strtotime($date));
    
    $amount = str_replace(',', '.', $amount); 
    $amount = floatval($amount);

    if (!($transactionType === 'savings' && $category === 'Непредвидени разходи' && $amount === 0) && $amount <= 0) {
        $_SESSION['error'] = "Моля въведете число, по-голямо от 0.";
        header("Location: $redirect");
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        $balanceQuery = "SELECT balance FROM users WHERE id = ?";
        $stmt = $conn->prepare($balanceQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $currentBalance = $row['balance'] ?? 0;
        
        $newBalance = $currentBalance;
        if ($transactionType === 'income') {
            $newBalance += $amount;
        } else if ($transactionType === 'expense') {
            $newBalance -= $amount;
        } else if ($transactionType === 'savings') {
            $newBalance -= $amount;
        }
        

        $updateBalanceQuery = "UPDATE users SET balance = ? WHERE id = ?";
        $stmt = $conn->prepare($updateBalanceQuery);
        $stmt->bind_param("di", $newBalance, $userId);
        $stmt->execute();
        
        $insertQuery = "INSERT INTO transactions (user_id, type, category, amount, comment, transaction_date) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("issdss", $userId, $transactionType, $category, $amount, $comment, $formattedDate);
        $stmt->execute();
        
        $conn->commit();
        
        $_SESSION['success'] = "Транзакцията е запазена успешно!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: $redirect");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>

