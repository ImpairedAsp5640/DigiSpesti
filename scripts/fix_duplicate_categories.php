<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fixing Duplicate Categories</h1>";

$categoriesToFix = [
    'Salary' => 'Заплата',
    'Previous month balance' => 'Баланс от предходен месец'
];

echo "<h2>Fixing Categories:</h2>";

foreach ($categoriesToFix as $englishCategory => $bulgarianCategory) {
    echo "<h3>Processing: $englishCategory → $bulgarianCategory</h3>";
    
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
    
    echo "<p>Found $englishCount items with category '$englishCategory'</p>";
    echo "<p>Found $bulgarianCount items with category '$bulgarianCategory'</p>";
    
    if ($englishCount > 0) {
        $conn->begin_transaction();
        
        try {
            if ($bulgarianCount > 0) {
                echo "<p>Both categories exist. Merging data...</p>";
                
                $findDuplicatesQuery = "
                    SELECT a.user_id, a.month, a.year, a.type 
                    FROM budget_plan_items a
                    JOIN budget_plan_items b ON 
                        a.user_id = b.user_id AND 
                        a.month = b.month AND 
                        a.year = b.year AND 
                        a.type = b.type
                    WHERE a.category = ? AND b.category = ?
                ";
                $stmt = $conn->prepare($findDuplicatesQuery);
                $stmt->bind_param("ss", $englishCategory, $bulgarianCategory);
                $stmt->execute();
                $duplicatesResult = $stmt->get_result();
                
                $mergeCount = 0;
                while ($row = $duplicatesResult->fetch_assoc()) {
                    $userId = $row['user_id'];
                    $month = $row['month'];
                    $year = $row['year'];
                    $type = $row['type'];
                    
                    $getAmountsQuery = "
                        SELECT category, amount 
                        FROM budget_plan_items 
                        WHERE user_id = ? AND month = ? AND year = ? AND type = ? 
                        AND (category = ? OR category = ?)
                    ";
                    $stmt = $conn->prepare($getAmountsQuery);
                    $stmt->bind_param("iiisss", $userId, $month, $year, $type, $englishCategory, $bulgarianCategory);
                    $stmt->execute();
                    $amountsResult = $stmt->get_result();
                    
                    $englishAmount = 0;
                    $bulgarianAmount = 0;
                    
                    while ($amountRow = $amountsResult->fetch_assoc()) {
                        if ($amountRow['category'] == $englishCategory) {
                            $englishAmount = $amountRow['amount'];
                        } else {
                            $bulgarianAmount = $amountRow['amount'];
                        }
                    }
                    
                    $totalAmount = $englishAmount + $bulgarianAmount;
                    
                    echo "<p>User $userId, Month $month, Year $year: Merging $englishCategory ($englishAmount) and $bulgarianCategory ($bulgarianAmount) = $totalAmount</p>";
                    
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
                    
                    $mergeCount++;
                }
                
                echo "<p style='color:green'>Merged data for $mergeCount records</p>";
                
                $updateRemainingQuery = "
                    UPDATE budget_plan_items 
                    SET category = ? 
                    WHERE category = ? AND NOT EXISTS (
                        SELECT 1 FROM (
                            SELECT user_id, month, year, type 
                            FROM budget_plan_items 
                            WHERE category = ?
                        ) as b 
                        WHERE 
                            budget_plan_items.user_id = b.user_id AND 
                            budget_plan_items.month = b.month AND 
                            budget_plan_items.year = b.year AND 
                            budget_plan_items.type = b.type
                    )
                ";
                $stmt = $conn->prepare($updateRemainingQuery);
                $stmt->bind_param("sss", $bulgarianCategory, $englishCategory, $bulgarianCategory);
                $stmt->execute();
                
                $remainingUpdated = $stmt->affected_rows;
                echo "<p style='color:green'>Renamed $remainingUpdated remaining records from $englishCategory to $bulgarianCategory</p>";
            } else {
                echo "<p>Only English category exists. Renaming all instances...</p>";
                
                $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ss", $bulgarianCategory, $englishCategory);
                $stmt->execute();
                
                $affectedRows = $stmt->affected_rows;
                echo "<p style='color:green'>Renamed $affectedRows records from $englishCategory to $bulgarianCategory</p>";
            }
            
            $conn->commit();
            echo "<p style='color:green'>Changes committed successfully</p>";
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>No records found with category '$englishCategory'</p>";
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
    $stmt->bind_param("dddiii", $totalIncome, $totalExpenses, $totalSavings, $userId, $month, $year);
    
    if ($stmt->execute()) {
        echo "<p style='color:green'>Updated budget plan for User: $userId, Month: $month, Year: $year</p>";
    } else {
        echo "<p style='color:red'>Error updating budget plan: " . $stmt->error . "</p>";
    }
}

echo "<h2>Verification:</h2>";

$verifyQuery = "SELECT category, COUNT(*) as count FROM budget_plan_items WHERE category IN ('Salary', 'Previous month balance') GROUP BY category";
$result = $conn->query($verifyQuery);

if ($result->num_rows > 0) {
    echo "<p style='color:red'>Warning: The following English categories still exist:</p>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['category'] . ": " . $row['count'] . " records</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:green'>All specified English categories have been successfully replaced with Bulgarian equivalents.</p>";
}

$allCategoriesQuery = "SELECT category, COUNT(*) as count FROM budget_plan_items GROUP BY category ORDER BY count DESC";
$result = $conn->query($allCategoriesQuery);

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