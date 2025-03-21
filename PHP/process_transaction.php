<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Include database connection
require_once 'config.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionType = $_POST['transactionType'] ?? '';
    $category = $_POST['selectedCategory'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $date = $_POST['date'] ?? date('Y-m-d');
    $comment = $_POST['comment'] ?? '';
    $userId = $_SESSION['user_id'];
    $redirect = $_POST['redirect'] ?? 'index.php';
    
    // Convert date to MySQL format (if needed)
    $formattedDate = date('Y-m-d', strtotime($date));
    
    // Validate amount (convert to float and ensure it's a valid number)
    $amount = str_replace(',', '.', $amount); // Replace comma with dot for decimal
    $amount = floatval($amount);
    
    if ($amount <= 0) {
        $_SESSION['error'] = "Please enter a valid amount greater than zero.";
        header("Location: $redirect");
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get current balance
        $balanceQuery = "SELECT balance FROM users WHERE id = ?";
        $stmt = $conn->prepare($balanceQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $currentBalance = $row['balance'] ?? 0;
        
        // Calculate new balance based on transaction type
        $newBalance = $currentBalance;
        if ($transactionType === 'income') {
            $newBalance += $amount;
        } else if ($transactionType === 'expense') {
            $newBalance -= $amount;
        } else if ($transactionType === 'savings') {
            // For savings, we subtract from balance (it's money set aside)
            $newBalance -= $amount;
        }
        
        // Update user balance
        $updateBalanceQuery = "UPDATE users SET balance = ? WHERE id = ?";
        $stmt = $conn->prepare($updateBalanceQuery);
        $stmt->bind_param("di", $newBalance, $userId);
        $stmt->execute();
        
        // Insert transaction record
        $insertQuery = "INSERT INTO transactions (user_id, type, category, amount, comment, transaction_date) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("issdss", $userId, $transactionType, $category, $amount, $comment, $formattedDate);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['success'] = "Transaction saved successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect back to the page that submitted the form
    header("Location: $redirect");
    exit();
} else {
    // If not POST request, redirect to index
    header("Location: index.php");
    exit();
}
?>