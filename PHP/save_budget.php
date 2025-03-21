<?php
session_start();

// mb_internal_encoding('UTF-8');
// mb_http_output('UTF-8');

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = $_POST['month'] ?? date('m');
    $year = $_POST['year'] ?? date('Y');
    $plannedBudget = $_POST['plannedBudget'] ?? 0;
    $userId = $_SESSION['user_id'];
    
    $plannedBudget = str_replace(',', '.', $plannedBudget);
    $plannedBudget = floatval($plannedBudget);
    
    $checkQuery = "SELECT id FROM budget_plans 
                  WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $planId = $row['id'];
        
        $updateQuery = "UPDATE budget_plans SET amount = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("di", $plannedBudget, $planId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Budget plan updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating budget plan: " . $conn->error;
        }
    } else {
        $insertQuery = "INSERT INTO budget_plans (user_id, month, year, amount) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iiid", $userId, $month, $year, $plannedBudget);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Budget plan saved successfully!";
        } else {
            $_SESSION['error'] = "Error saving budget plan: " . $conn->error;
        }
    }
    
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if ($isAjax) {
        echo "Budget plan updated successfully!";
        exit();
    } else {
        header("Location: history.php?month=$month&year=$year");
        exit();
    }
} else {
    header("Location: history.php");
    exit();
}
?>

