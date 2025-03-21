<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Budget Data Cleanup Tool</h1>";

$userId = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

echo "<h2>Cleaning data for User ID: $userId, Month: $month, Year: $year</h2>";

$zeroQuery = "SELECT * FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? AND category = '0'";
$stmt = $conn->prepare($zeroQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Checking for Category '0' Entries:</h3>";
if ($result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " entries with category '0'. Deleting these entries...</p>";
    
    $deleteZeroQuery = "DELETE FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? AND category = '0'";
    $stmt = $conn->prepare($deleteZeroQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    
    echo "<p style='color:green'>Deleted " . $stmt->affected_rows . " entries with category '0'.</p>";
} else {
    echo "<p style='color:green'>No category '0' entries found.</p>";
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
    echo "<p>Found duplicate entries. Fixing...</p>";
    
    $duplicatesQuery = "SELECT t1.* 
                       FROM budget_plan_items t1
                       INNER JOIN (
                           SELECT user_id, month, year, type, category
                           FROM budget_plan_items
                           WHERE user_id = ? AND month = ? AND year = ?
                           GROUP BY user_id, month, year, type, category
                           HAVING COUNT(*) > 1
                       ) t2 
                       ON t1.user_id = t2.user_id 
                       AND t1.month = t2.month 
                       AND t1.year = t2.year 
                       AND t1.type = t2.type 
                       AND t1.category = t2.category
                       ORDER BY t1.user_id, t1.month, t1.year, t1.type, t1.category, t1.id";
    
    $stmt = $conn->prepare($duplicatesQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $duplicates = [];
    while ($row = $result->fetch_assoc()) {
        $key = $row['user_id'] . '-' . $row['month'] . '-' . $row['year'] . '-' . $row['type'] . '-' . $row['category'];
        if (!isset($duplicates[$key])) {
            $duplicates[$key] = [];
        }
        $duplicates[$key][] = $row;
    }
    
    foreach ($duplicates as $key => $items) {
        echo "<p>Processing duplicate group: $key</p>";
        
        $keepItem = $items[0];
        $totalAmount = 0;
        $idsToDelete = [];
        
        foreach ($items as $item) {
            $totalAmount += floatval($item['amount']);
            if ($item['id'] != $keepItem['id']) {
                $idsToDelete[] = $item['id'];
            }
        }
        
        $updateQuery = "UPDATE budget_plan_items SET amount = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("di", $totalAmount, $keepItem['id']);
        
        if ($stmt->execute()) {
            echo "<p style='color:green'>Updated item ID " . $keepItem['id'] . " with total amount " . $totalAmount . "</p>";
        } else {
            echo "<p style='color:red'>Error updating item: " . $stmt->error . "</p>";
        }
        
        if (!empty($idsToDelete)) {
            $deleteIds = implode(',', $idsToDelete);
            $deleteQuery = "DELETE FROM budget_plan_items WHERE id IN ($deleteIds)";
            
            if ($conn->query($deleteQuery)) {
                echo "<p style='color:green'>Deleted " . count($idsToDelete) . " duplicate items</p>";
            } else {
                echo "<p style='color:red'>Error deleting duplicates: " . $conn->error . "</p>";
            }
        }
    }
    
    $verifyQuery = "SELECT user_id, month, year, type, category, COUNT(*) as count 
                   FROM budget_plan_items 
                   WHERE user_id = ? AND month = ? AND year = ?
                   GROUP BY user_id, month, year, type, category 
                   HAVING COUNT(*) > 1";
    $stmt = $conn->prepare($verifyQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color:red'>There are still " . $result->num_rows . " duplicate groups. Additional fixes may be needed.</p>";
    } else {
        echo "<p style='color:green'>All duplicates have been fixed!</p>";
    }
} else {
    echo "<p style='color:green'>No duplicate entries found.</p>";
}

echo "<h3>Updating budget_plans totals...</h3>";

$totalsQuery = "SELECT 
              SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
              SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
              SUM(CASE WHEN type = 'savings' THEN amount ELSE 0 END) as total_savings
              FROM budget_plan_items 
              WHERE user_id = ? AND month = ? AND year = ?";

$stmt = $conn->prepare($totalsQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$totalsResult = $stmt->get_result();
$totals = $totalsResult->fetch_assoc();

$totalIncome = $totals['total_income'] ?? 0;
$totalExpenses = $totals['total_expenses'] ?? 0;
$totalSavings = $totals['total_savings'] ?? 0;

$updateQuery = "UPDATE budget_plans SET income = ?, expenses = ?, savings = ? WHERE user_id = ? AND month = ? AND year = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("dddiii", $totalIncome, $totalExpenses, $totalSavings, $userId, $month, $year);

if ($stmt->execute()) {
    echo "<p style='color:green'>Updated budget plan with Income: $totalIncome, Expenses: $totalExpenses, Savings: $totalSavings</p>";
} else {
    echo "<p style='color:red'>Error updating budget plan: " . $stmt->error . "</p>";
}

$itemsQuery = "SELECT * FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? ORDER BY type, category";
$stmt = $conn->prepare($itemsQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Current Budget Items:</h3>";
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Type</th><th>Category</th><th>Amount</th></tr>";
    
    while ($item = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . $item['type'] . "</td>";
        echo "<td>" . $item['category'] . "</td>";
        echo "<td>" . $item['amount'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No budget items found for this month/year.</p>";
}

echo "<p><a href='plan_budget.php?month=$month&year=$year' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>