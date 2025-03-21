<?php
session_start();

// Force UTF-8 encoding for the entire page
ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

// Include database connection
require_once 'config.php';

// Check if transaction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No transaction ID provided.";
    header("Location: history.php");
    exit();
}

$transactionId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Verify that the transaction belongs to the current user
$checkQuery = "SELECT * FROM transactions WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("ii", $transactionId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to delete it.";
    header("Location: history.php");
    exit();
}

// Get transaction details for redirect
$transaction = $result->fetch_assoc();
$month = date('m', strtotime($transaction['transaction_date']));
$year = date('Y', strtotime($transaction['transaction_date']));

// Delete the transaction
$deleteQuery = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($deleteQuery);
$stmt->bind_param("ii", $transactionId, $userId);

if ($stmt->execute()) {
    $_SESSION['success'] = "Transaction deleted successfully.";
} else {
    $_SESSION['error'] = "Error deleting transaction: " . $conn->error;
}

// Redirect back to history page for the same month/year
header("Location: history.php?month=$month&year=$year");
exit();
?>

