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

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: history.php");
    exit();
}

// Check if transaction ID is provided
if (!isset($_POST['transaction_id']) || empty($_POST['transaction_id'])) {
    $_SESSION['error'] = "No transaction ID provided.";
    header("Location: history.php");
    exit();
}

// Get form data
$transactionId = $_POST['transaction_id'];
$userId = $_SESSION['user_id'];
$transactionType = $_POST['transactionType'] ?? '';
$category = $_POST['selectedCategory'] ?? '';
$amount = $_POST['amount'] ?? 0;
$comment = $_POST['comment'] ?? '';
$date = $_POST['date'] ?? date('d F Y');

// Validate data
if (empty($transactionType) || empty($category) || empty($amount)) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header("Location: edit_transaction.php?id=$transactionId");
    exit();
}

// Format amount (replace comma with dot)
$amount = str_replace(',', '.', $amount);
$amount = floatval($amount);

// Format date for database
$formattedDate = date('Y-m-d', strtotime($date));

// Verify that the transaction belongs to the current user
$checkQuery = "SELECT * FROM transactions WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("ii", $transactionId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to edit it.";
    header("Location: history.php");
    exit();
}

// Update the transaction
$updateQuery = "UPDATE transactions 
               SET type = ?, category = ?, amount = ?, comment = ?, transaction_date = ? 
               WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("ssdssis", $transactionType, $category, $amount, $comment, $formattedDate, $transactionId, $userId);

if ($stmt->execute()) {
    $_SESSION['success'] = "Transaction updated successfully.";
} else {
    $_SESSION['error'] = "Error updating transaction: " . $conn->error;
}

// Get month and year for redirect
$month = date('m', strtotime($formattedDate));
$year = date('Y', strtotime($formattedDate));

// Redirect back to history page for the same month/year
header("Location: history.php?month=$month&year=$year");
exit();
?>

