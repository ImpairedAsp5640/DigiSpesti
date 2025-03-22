<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Updating Budget Categories to Bulgarian</h1>";

$categoryMapping = [
    'Salary' => 'Заплата',
    'Previous month balance' => 'Баланс от предходен месец',
    
    'Utilities' => 'Битови сметки',
    'Housing' => 'Жилище',
    'Food and groceries' => 'Храна и консулмативи',
    'Transportation' => 'Транспорт',
    'Car' => 'Автомобил',
    'Children' => 'Деца',
    'Kids' => 'Деца',
    'Transport' => 'Транспорт',
    'Clothing and footwear' => 'Дрехи и обувки',
    'Personal' => 'Лични',
    'Cigarettes and alcohol' => 'Цигари и алкохол',
    'Entertainment' => 'Развлечения',
    'Eating out' => 'Хранене навън',
    'Education' => 'Образование',
    'Gifts' => 'Подаръци',
    'Sports/Hobby' => 'Спорт/Хоби',
    'Travel/Leisure' => 'Пътуване/Отдих',
    'Medical' => 'Медицински',
    'Pets' => 'Домашни любимци',
    'Miscellaneous' => 'Разни'
];

$uniqueCategoriesQuery = "SELECT DISTINCT category FROM budget_plan_items";
$result = $conn->query($uniqueCategoriesQuery);

echo "<h2>Found Categories in Database:</h2>";
echo "<ul>";
$categories = [];
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['category'] . "</li>";
    $categories[] = $row['category'];
}
echo "</ul>";

echo "<h2>Updating Categories:</h2>";

$totalUpdated = 0;

foreach ($categoryMapping as $englishCategory => $bulgarianCategory) {
    if ($englishCategory == $bulgarianCategory) {
        continue;
    }
    
    $checkQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $englishCategory);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        echo "<p>Updating category: <strong>$englishCategory</strong> to <strong>$bulgarianCategory</strong></p>";
        
        $checkBulgarianQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
        $stmt = $conn->prepare($checkBulgarianQuery);
        $stmt->bind_param("s", $bulgarianCategory);
        $stmt->execute();
        $result = $stmt->get_result();
        $bulgarianCount = $result->fetch_assoc()['count'];
        
        if ($bulgarianCount > 0) {
            echo "<p>Both <strong>$englishCategory</strong> and <strong>$bulgarianCategory</strong> exist. Merging values...</p>";
            
            $conn->begin_transaction();
            
            try {
                $findDuplicatesQuery = "
                    SELECT user_id, month, year, type 
                    FROM budget_plan_items 
                    WHERE (category = ? OR category = ?) 
                    GROUP BY user_id, month, year, type
                    HAVING COUNT(*) > 1
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
                    
                    $mergeCount++;
                }
                
                if ($mergeCount > 0) {
                    echo "<p style='color:green'>Merged $mergeCount records</p>";
                }
                
                $updateRemainingQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
                $stmt = $conn->prepare($updateRemainingQuery);
                $stmt->bind_param("ss", $bulgarianCategory, $englishCategory);
                $stmt->execute();
                $remainingUpdated = $stmt->affected_rows;
                
                if ($remainingUpdated > 0) {
                    echo "<p style='color:green'>Updated $remainingUpdated remaining records from $englishCategory to $bulgarianCategory</p>";
                    $totalUpdated += $remainingUpdated;
                }
                
                $conn->commit();
                
            } catch (Exception $e) {
                $conn->rollback();
                echo "<p style='color:red'>Error merging categories: " . $e->getMessage() . "</p>";
            }
        } else {
            $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ss", $bulgarianCategory, $englishCategory);
            $stmt->execute();
            
            $affectedRows = $stmt->affected_rows;
            echo "<p style='color:green'>Updated $affectedRows records</p>";
            $totalUpdated += $affectedRows;
        }
    }
}

echo "<h2>Summary:</h2>";
echo "<p>Total records updated: <strong>$totalUpdated</strong></p>";

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

$remainingEnglishCategories = [];
foreach ($categoryMapping as $englishCategory => $bulgarianCategory) {
    if ($englishCategory != $bulgarianCategory) {
        $checkQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $englishCategory);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            $remainingEnglishCategories[] = $englishCategory;
        }
    }
}

if (!empty($remainingEnglishCategories)) {
    echo "<h3 style='color:red'>Warning: The following English categories still exist in the database:</h3>";
    echo "<ul>";
    foreach ($remainingEnglishCategories as $category) {
        echo "<li>" . $category . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:green'>All English categories have been successfully replaced with Bulgarian equivalents.</p>";
}

echo "<p><a href='plan_budget.php' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>