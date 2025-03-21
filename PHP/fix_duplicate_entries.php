<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fixing Duplicate Entries in budget_plan_items Table</h1>";

$tableCheckQuery = "SHOW TABLES LIKE 'budget_plan_items'";
$result = $conn->query($tableCheckQuery);
$budgetPlanItemsExists = ($result && $result->num_rows > 0);

if (!$budgetPlanItemsExists) {
    echo "<p style='color:red'>Error: budget_plan_items table does not exist!</p>";
    exit();
}

$duplicateQuery = "SELECT * FROM budget_plan_items WHERE category = '0'";
$result = $conn->query($duplicateQuery);

if ($result->num_rows > 0) {
    echo "<h2>Found " . $result->num_rows . " entries with category '0'</h2>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . $row['id'] . ", User: " . $row['user_id'] . ", Month: " . $row['month'] . ", Year: " . $row['year'] . ", Type: " . $row['type'] . ", Amount: " . $row['amount'] . "</li>";
    }
    echo "</ul>";
    
    $deleteQuery = "DELETE FROM budget_plan_items WHERE category = '0'";
    if ($conn->query($deleteQuery)) {
        echo "<p style='color:green'>Successfully deleted " . $conn->affected_rows . " entries with category '0'</p>";
    } else {
        echo "<p style='color:red'>Error deleting entries: " . $conn->error . "</p>";
    }
} else {
    echo "<p>No entries found with category '0'</p>";
}

$duplicateCheckQuery = "SELECT user_id, month, year, type, category, COUNT(*) as count 
                       FROM budget_plan_items 
                       GROUP BY user_id, month, year, type, category 
                       HAVING COUNT(*) > 1";
$result = $conn->query($duplicateCheckQuery);

if ($result->num_rows > 0) {
    echo "<h2>Found duplicate entries:</h2>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>User: " . $row['user_id'] . ", Month: " . $row['month'] . ", Year: " . $row['year'] . ", Type: " . $row['type'] . ", Category: " . $row['category'] . " - Count: " . $row['count'] . "</li>";
    }
    echo "</ul>";
    
    echo "<h3>Fixing duplicates...</h3>";
    
    $duplicatesQuery = "SELECT t1.* 
                       FROM budget_plan_items t1
                       INNER JOIN (
                           SELECT user_id, month, year, type, category
                           FROM budget_plan_items
                           GROUP BY user_id, month, year, type, category
                           HAVING COUNT(*) > 1
                       ) t2 
                       ON t1.user_id = t2.user_id 
                       AND t1.month = t2.month 
                       AND t1.year = t2.year 
                       AND t1.type = t2.type 
                       AND t1.category = t2.category
                       ORDER BY t1.user_id, t1.month, t1.year, t1.type, t1.category, t1.id";
    
    $result = $conn->query($duplicatesQuery);
    
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
                   GROUP BY user_id, month, year, type, category 
                   HAVING COUNT(*) > 1";
    $result = $conn->query($verifyQuery);
    
    if ($result->num_rows > 0) {
        echo "<p style='color:red'>There are still " . $result->num_rows . " duplicate groups. Additional fixes may be needed.</p>";
    } else {
        echo "<p style='color:green'>All duplicates have been fixed!</p>";
    }
    
} else {
    echo "<p>No duplicate entries found.</p>";
}

echo "<h2>Updating budget_plans totals...</h2>";

$updatePlansQuery = "SELECT DISTINCT user_id, month, year FROM budget_plan_items";
$result = $conn->query($updatePlansQuery);

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
    $stmt->bind_param("dddiiii", $totalIncome, $totalExpenses, $totalSavings, $userId, $month, $year);
    
    if ($stmt->execute()) {
        echo "<p style='color:green'>Updated budget plan for User: $userId, Month: $month, Year: $year with Income: $totalIncome, Expenses: $totalExpenses, Savings: $totalSavings</p>";
    } else {
        echo "<p style='color:red'>Error updating budget plan: " . $stmt->error . "</p>";
    }
}

echo "<p><a href='plan_budget.php' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>

