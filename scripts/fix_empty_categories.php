<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fixing Empty Categories</h1>";

$emptyQuery = "SELECT * FROM budget_plan_items WHERE category = '' OR category IS NULL";
$emptyResult = $conn->query($emptyQuery);

if ($emptyResult->num_rows > 0) {
    echo "<h2>Found " . $emptyResult->num_rows . " items with empty categories:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Month</th><th>Year</th><th>Type</th><th>Amount</th></tr>";
    
    $emptyItems = [];
    while ($item = $emptyResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . $item['user_id'] . "</td>";
        echo "<td>" . $item['month'] . "</td>";
        echo "<td>" . $item['year'] . "</td>";
        echo "<td>" . $item['type'] . "</td>";
        echo "<td>" . $item['amount'] . "</td>";
        echo "</tr>";
        
        $emptyItems[] = $item;
    }
    echo "</table>";
    
    $defaultCategories = [
        'income' => 'Заплата', 
        'expense' => 'Разни',  
        'savings' => 'Спестявания' 
    ];
    
    echo "<h2>Fixing empty categories...</h2>";
    
    $conn->begin_transaction();
    
    try {
        $fixedCount = 0;
        
        foreach ($emptyItems as $item) {
            $id = $item['id'];
            $type = $item['type'];
            $defaultCategory = $defaultCategories[$type] ?? 'Разни';
            
            if ($type == 'income' && abs($item['amount'] - 2000) < 0.01) {
                $defaultCategory = 'Заплата';
            }
            
            if ($type == 'income' && $item['amount'] < 500) {
                $defaultCategory = 'Баланс от предходен месец';
            }
            
            echo "<p>Setting item ID $id ($type, amount: {$item['amount']}) to category: <strong>$defaultCategory</strong></p>";
            
            $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("si", $defaultCategory, $id);
            
            if ($stmt->execute()) {
                $fixedCount++;
                echo "<p style='color:green'>? Successfully updated</p>";
            } else {
                echo "<p style='color:red'>? Failed to update: " . $stmt->error . "</p>";
            }
        }
        
        $conn->commit();
        echo "<h2>Fixed $fixedCount items with empty categories</h2>";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>Updating budget_plans totals...</h2>";
    
    $updatePlansQuery = "SELECT DISTINCT user_id, month, year FROM budget_plan_items";
    $result = $conn->query($updatePlansQuery);
    
    $updatedPlans = 0;
    while ($row = $result->fetch_assoc()) {
        $userId = $row['user_id'];
        $month = $row['month'];
        $year = $row['year'];
        
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
            $updatedPlans++;
            echo "<p style='color:green'>Updated budget plan for User: $userId, Month: $month, Year: $year</p>";
        } else {
            echo "<p style='color:red'>Error updating budget plan: " . $stmt->error . "</p>";
        }
    }
    
    echo "<h3>Updated totals for $updatedPlans budget plans</h3>";
    
} else {
    echo "<p>No items with empty categories found.</p>";
}

echo "<h2>Verification:</h2>";

$verifyQuery = "SELECT id, user_id, month, year, type, category, amount FROM budget_plan_items ORDER BY id";
$verifyResult = $conn->query($verifyQuery);

if ($verifyResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User</th><th>Month/Year</th><th>Type</th><th>Category</th><th>Amount</th></tr>";
    
    while ($item = $verifyResult->fetch_assoc()) {
        $categoryStyle = ($item['category'] == '' || $item['category'] === NULL) ? "style='color:red;font-weight:bold'" : "";
        
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . $item['user_id'] . "</td>";
        echo "<td>" . $item['month'] . "/" . $item['year'] . "</td>";
        echo "<td>" . $item['type'] . "</td>";
        echo "<td $categoryStyle>" . $item['category'] . "</td>";
        echo "<td>" . $item['amount'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<p><a href='budget_debug_tools.php' style='display:inline-block; padding:10px 15px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:4px;'>Return to Debug Tools</a></p>";

$conn->close();
?>