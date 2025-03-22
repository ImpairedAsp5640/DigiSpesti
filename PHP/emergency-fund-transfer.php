<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $goalId = $_POST['goal_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $comment = $_POST['comment'] ?? '';
    $userId = $_SESSION['user_id'];
    
    if ($goalId > 0 && $amount > 0) {
        $goalQuery = "SELECT name FROM savings_goals WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($goalQuery);
        $stmt->bind_param("ii", $goalId, $userId);
        $stmt->execute();
        $goalResult = $stmt->get_result();
        
        if ($goalResult->num_rows > 0) {
            $goalData = $goalResult->fetch_assoc();
            $goalCategory = $goalData['name'];
            
            $emergencyFundQuery = "SELECT SUM(amount) as total FROM transactions 
                                  WHERE user_id = ? AND type = 'savings' AND category = 'Непредвидени разходи'";
            $stmt = $conn->prepare($emergencyFundQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $emergencyResult = $stmt->get_result();
            $emergencyData = $emergencyResult->fetch_assoc();
            $emergencyTotal = $emergencyData['total'] ?? 0;
            
            if ($emergencyTotal >= $amount) {
                $conn->begin_transaction();
                
                try {
                    $withdrawalComment = empty($comment) ? "Transfer to " . $goalCategory : $comment;
                    $insertWithdrawalQuery = "INSERT INTO transactions (user_id, type, category, amount, comment, transaction_date) 
                                            VALUES (?, 'savings', 'Непредвидени разходи', ?, ?, NOW())";
                    $negativeAmount = -1 * $amount; 
                    $stmt = $conn->prepare($insertWithdrawalQuery);
                    $stmt->bind_param("ids", $userId, $negativeAmount, $withdrawalComment);
                    $stmt->execute();
                    
                    $depositComment = empty($comment) ? "Transfer from Emergency Fund" : $comment;
                    $insertDepositQuery = "INSERT INTO transactions (user_id, type, category, amount, comment, transaction_date) 
                                          VALUES (?, 'savings', ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($insertDepositQuery);
                    $stmt->bind_param("isds", $userId, $goalCategory, $amount, $depositComment);
                    $stmt->execute();
                    
                    $conn->commit();
                    
                    $_SESSION['success'] = "Успешно прехвърлени " . number_format($amount, 2) . " лв. от Непредвидени разходи към " . $goalCategory;
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "Недостатъчна наличност в Непредвидени разходи. Оставащи: " . number_format($emergencyTotal, 2) . " лв.";
            }
        } else {
            $_SESSION['error'] = "Невалидна цел за спестяване.";
        }
    } else {
        $_SESSION['error'] = "Моля, въведете валидна сума.";
    }
    
    header("Location: savings.php");
    exit();
} else {
    header("Location: savings.php");
    exit();
}
?>

