<?php
session_start();

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: history.php");
    exit();
}

if (!isset($_POST['transaction_id']) || empty($_POST['transaction_id'])) {
    $_SESSION['error'] = "No transaction ID provided.";
    header("Location: history.php");
    exit();
}

$transactionId = $_POST['transaction_id'];
$userId = $_SESSION['user_id'];
$transactionType = $_POST['transactionType'] ?? '';
$category = $_POST['selectedCategory'] ?? '';
$amount = $_POST['amount'] ?? 0;
$comment = $_POST['comment'] ?? '';
$date = $_POST['date'] ?? date('d F Y');

if (empty($transactionType) || empty($category) || empty($amount)) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header("Location: edit_transaction.php?id=$transactionId");
    exit();
}

$amount = str_replace(',', '.', $amount);
$amount = floatval($amount);

$formattedDate = date('Y-m-d', strtotime($date));

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

$updateQuery = "UPDATE transactions 
               SET type = ?, category = ?, amount = ?, comment = ?, transaction_date = ? 
               WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("ssdssis", $transactionType, $category, $amount, $comment, $formattedDate, $transactionId, $userId);

if ($stmt->execute()) {
    $_SESSION['success'] = "Транзакцията е редактирана успешно.";
} else {
    $_SESSION['error'] = "Error updating transaction: " . $conn->error;
}

$month = date('m', strtotime($formattedDate));
$year = date('Y', strtotime($formattedDate));

header("Location: history.php?month=$month&year=$year");
exit();
?>

