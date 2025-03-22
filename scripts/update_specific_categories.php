<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Updating Specific Budget Categories</h1>";

$categoryChanges = [
    'Salary' => 'Заплата',
    'Previous month balance' => 'Баланс от предходен месец'
];

$categoriesToRemove = ['Kids', 'Transport'];

echo "<h2>Updating Categories:</h2>";
foreach ($categoryChanges as $oldCategory => $newCategory) {
    $checkQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $oldCategory);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        echo "<p>Updating category: <strong>$oldCategory</strong> to <strong>$newCategory</strong></p>";
        
        $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ss", $newCategory, $oldCategory);
        $stmt->execute();
        
        echo "<p style='color:green'>Updated " . $stmt->affected_rows . " records</p>";
    } else {
        echo "<p>Category <strong>$oldCategory</strong> not found in database</p>";
    }
}

echo "<h2>Handling Duplicate Categories:</h2>";
foreach ($categoriesToRemove as $category) {
    $englishCategory = $category;
    $bulgarianCategory = ($category == 'Kids') ? 'Деца' : 'Транспорт';
    
    $checkQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $englishCategory);
    $stmt->execute();
    $result = $stmt->get_result();
    $englishCount = $result->fetch_assoc()['count'];
    
    $stmt->bind_param("s", $bulgarianCategory);
    $stmt->execute();
    $result = $stmt->get_result();
    $bulgarianCount = $result->fetch_assoc()['count'];
    
    if ($englishCount > 0 && $bulgarianCount > 0) {
        echo "<p>Both <strong>$englishCategory</strong> and <strong>$bulgarianCategory</strong> exist. Merging values...</p>";
        
        $conn->begin_transaction();
        
        try {
            $findDuplicatesQuery = "
                SELECT user_id, month, year, type 
                FROM budget_plan_items 
                WHERE category = ? OR category = ? 
                GROUP BY user_id, month, year, type
                HAVING COUNT(*) > 1
            ";
            $stmt = $conn->prepare($findDuplicatesQuery);
            $stmt->bind_param("ss", $englishCategory, $bulgarianCategory);
            $stmt->execute();
            $duplicatesResult = $stmt->get_result();
            
            while ($row = $duplicatesResult->fetch_assoc()) {
                $userId = $row['user_id'];
                $month = $row['month'];
                $year = $row['year'];
                $type = $row['type'];
                
                $sumQuery = "
                    SELECT SUM(amount) as total 
                    FROM budget_plan_items 
                    WHERE user_id = ? AND month = ? AND year = ? AND type = ? 
                    AND (category = ? OR category = ?)
                ";
                $stmt = $conn->prepare($sumQuery);
                $stmt->bind_param("iiisss", $userId, $month, $year, $type, $englishCategory, $bulgarianCategory);
                $stmt->execute();
                $sumResult = $stmt->get_result();
                $totalAmount = $sumResult->fetch_assoc()['total'];
                
                $updateQuery = "
                    UPDATE budget_plan_items 
                    SET amount = ? 
                    WHERE user_id = ? AND month = ? AND year = ? AND type = ? AND category = ?
                ";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("diiiss", $totalAmount, $userId, $month, $year, $type, $bulgarianCategory);
                $stmt->execute();
                
                $deleteQuery = "
                    DELETE FROM budget_plan_items 
                    WHERE user_id = ? AND month = ? AND year = ? AND type = ? AND category = ?
                ";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("iiiss", $userId, $month, $year, $type, $englishCategory);
                $stmt->execute();
                
                echo "<p style='color:green'>Merged records for User ID: $userId, Month: $month, Year: $year, Type: $type</p>";
            }
            
            $updateRemainingQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
            $stmt = $conn->prepare($updateRemainingQuery);
            $stmt->bind_param("ss", $bulgarianCategory, $englishCategory);
            $stmt->execute();
            $remainingUpdated = $stmt->affected_rows;
            
            if ($remainingUpdated > 0) {
                echo "<p style='color:green'>Updated $remainingUpdated remaining records from $englishCategory to $bulgarianCategory</p>";
            }
            
            $conn->commit();
            echo "<p style='color:green'>Successfully merged $englishCategory into $bulgarianCategory</p>";
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p style='color:red'>Error merging categories: " . $e->getMessage() . "</p>";
        }
    } elseif ($englishCount > 0) {
        echo "<p>Only <strong>$englishCategory</strong> exists. Renaming to <strong>$bulgarianCategory</strong>...</p>";
        
        $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ss", $bulgarianCategory, $englishCategory);
        $stmt->execute();
        
        echo "<p style='color:green'>Updated " . $stmt->affected_rows . " records</p>";
    } else {
        echo "<p>Category <strong>$englishCategory</strong> not found in database</p>";
    }
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

echo "<h2>Verification:</h2>";

$verifyQuery = "SELECT category, COUNT(*) as count FROM budget_plan_items GROUP BY category ORDER BY count DESC";
$result = $conn->query($verifyQuery);

echo "<h3>Current Categories in Database:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Category</th><th>Count</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['category'] . "</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='plan_budget.php' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>