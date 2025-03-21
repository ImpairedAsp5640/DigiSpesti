<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Budget Data Debugging Tool</h1>";

$userId = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

echo "<h2>Checking data for User ID: $userId, Month: $month, Year: $year</h2>";

$budgetQuery = "SELECT * FROM budget_plans WHERE user_id = ? AND month = ? AND year = ?";
$stmt = $conn->prepare($budgetQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Budget Plan Data:</h3>";
if ($result->num_rows > 0) {
    $budgetPlan = $result->fetch_assoc();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Month</th><th>Year</th><th>Income</th><th>Expenses</th><th>Savings</th><th>Created</th><th>Updated</th></tr>";
    echo "<tr>";
    echo "<td>" . $budgetPlan['id'] . "</td>";
    echo "<td>" . $budgetPlan['user_id'] . "</td>";
    echo "<td>" . $budgetPlan['month'] . "</td>";
    echo "<td>" . $budgetPlan['year'] . "</td>";
    echo "<td>" . $budgetPlan['income'] . "</td>";
    echo "<td>" . $budgetPlan['expenses'] . "</td>";
    echo "<td>" . $budgetPlan['savings'] . "</td>";
    echo "<td>" . $budgetPlan['created_at'] . "</td>";
    echo "<td>" . $budgetPlan['updated_at'] . "</td>";
    echo "</tr>";
    echo "</table>";
} else {
    echo "<p style='color:red'>No budget plan found for this month/year.</p>";
}

$itemsQuery = "SELECT * FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? ORDER BY type, category";
$stmt = $conn->prepare($itemsQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Budget Plan Items:</h3>";
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Month</th><th>Year</th><th>Type</th><th>Category</th><th>Amount</th><th>Created</th><th>Updated</th></tr>";
    
    while ($item = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . $item['user_id'] . "</td>";
        echo "<td>" . $item['month'] . "</td>";
        echo "<td>" . $item['year'] . "</td>";
        echo "<td>" . $item['type'] . "</td>";
        echo "<td>" . $item['category'] . "</td>";
        echo "<td>" . $item['amount'] . "</td>";
        echo "<td>" . $item['created_at'] . "</td>";
        echo "<td>" . $item['updated_at'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red'>No budget plan items found for this month/year.</p>";
}

$duplicateQuery = "SELECT user_id, month, year, type, category, COUNT(*) as count 
                  FROM budget_plan_items 
                  WHERE user_id = ? AND month = ? AND year = ?
                  GROUP BY user_id, month, year, type, category 
                  HAVING COUNT(*) > 1";
$stmt = $conn->prepare($duplicateQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Checking for Duplicate Entries:</h3>";
if ($result->num_rows > 0) {
    echo "<p style='color:red'>Found duplicate entries:</p>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>Type: " . $row['type'] . ", Category: " . $row['category'] . " - Count: " . $row['count'] . "</li>";
    }
    echo "</ul>";
    
    echo "<p>To fix duplicates, run the <a href='fix_duplicate_entries.php'>fix_duplicate_entries.php</a> script.</p>";
} else {
    echo "<p style='color:green'>No duplicate entries found.</p>";
}

$zeroQuery = "SELECT * FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? AND category = '0'";
$stmt = $conn->prepare($zeroQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Checking for Category '0' Entries:</h3>";
if ($result->num_rows > 0) {
    echo "<p style='color:red'>Found " . $result->num_rows . " entries with category '0':</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Type</th><th>Amount</th></tr>";
    
    while ($item = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . $item['type'] . "</td>";
        echo "<td>" . $item['amount'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<p>To remove category '0' entries, run the <a href='fix_duplicate_entries.php'>fix_duplicate_entries.php</a> script.</p>";
} else {
    echo "<p style='color:green'>No category '0' entries found.</p>";
}

echo "<h3>Recent Debug Log Entries:</h3>";
if (file_exists('budget_debug.log')) {
    $logContent = file_get_contents('budget_debug.log');
    $logLines = array_slice(explode("\n", $logContent), -50); // Get last 50 lines
    
    echo "<pre style='background-color: #f5f5f5; padding: 10px; max-height: 400px; overflow-y: auto;'>";
    foreach ($logLines as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p>No debug log file found.</p>";
}

echo "<p><a href='plan_budget.php?month=$month&year=$year' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>

