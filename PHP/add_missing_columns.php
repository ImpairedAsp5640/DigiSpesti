<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Adding Missing Columns to budget_plans Table</h1>";

$tableCheckQuery = "SHOW TABLES LIKE 'budget_plans'";
$result = $conn->query($tableCheckQuery);
$budgetPlansExists = ($result && $result->num_rows > 0);

if (!$budgetPlansExists) {
    echo "<p style='color:red'>Error: budget_plans table does not exist!</p>";
    echo "<p>Please run check_db_structure.php first to create the table.</p>";
    exit();
}

$columnsQuery = "SHOW COLUMNS FROM budget_plans";
$result = $conn->query($columnsQuery);

echo "<h2>Current columns in budget_plans table:</h2>";
echo "<ul>";
$columns = [];
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
    $columns[] = $row['Field'];
}
echo "</ul>";

$requiredColumns = ['income', 'expenses', 'savings'];
$missingColumns = array_diff($requiredColumns, $columns);

if (empty($missingColumns)) {
    echo "<p style='color:green'>All required columns already exist in the budget_plans table.</p>";
} else {
    echo "<p>Missing columns: " . implode(", ", $missingColumns) . "</p>";
    
    foreach ($missingColumns as $column) {
        $alterQuery = "ALTER TABLE budget_plans ADD COLUMN $column DECIMAL(10, 2) NOT NULL DEFAULT 0";
        
        if ($conn->query($alterQuery)) {
            echo "<p style='color:green'>Successfully added column '$column' to budget_plans table</p>";
        } else {
            echo "<p style='color:red'>Error adding column '$column': " . $conn->error . "</p>";
        }
    }
    
    $columnsQuery = "SHOW COLUMNS FROM budget_plans";
    $result = $conn->query($columnsQuery);
    
    echo "<h2>Updated columns in budget_plans table:</h2>";
    echo "<ul>";
    $updatedColumns = [];
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
        $updatedColumns[] = $row['Field'];
    }
    echo "</ul>";
    
    $stillMissingColumns = array_diff($requiredColumns, $updatedColumns);
    if (empty($stillMissingColumns)) {
        echo "<p style='color:green'>All required columns have been successfully added!</p>";
    } else {
        echo "<p style='color:red'>Some columns could not be added: " . implode(", ", $stillMissingColumns) . "</p>";
    }
}

echo "<h2>Testing Database Operations:</h2>";

$userId = $_SESSION['user_id'];
$testMonth = date('m');
$testYear = date('Y');

try {
    $conn->begin_transaction();
    
    $checkQuery = "SELECT id FROM budget_plans WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("iii", $userId, $testMonth, $testYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $budgetPlanId = $row['id'];
        
        $updateQuery = "UPDATE budget_plans SET income = 1000, expenses = 500, savings = 200 WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $budgetPlanId);
        $stmt->execute();
        
        echo "<p style='color:green'>Successfully updated test record in budget_plans (ID: $budgetPlanId)</p>";
    } else {
        $insertQuery = "INSERT INTO budget_plans (user_id, month, year, income, expenses, savings) VALUES (?, ?, ?, 1000, 500, 200)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iii", $userId, $testMonth, $testYear);
        $stmt->execute();
        $budgetPlanId = $conn->insert_id;
        
        echo "<p style='color:green'>Successfully inserted test record into budget_plans (ID: $budgetPlanId)</p>";
    }
    
    $conn->commit();
    
    echo "<p style='color:green'>Transaction committed successfully</p>";
    
    $verifyQuery = "SELECT * FROM budget_plans WHERE id = ?";
    $stmt = $conn->prepare($verifyQuery);
    $stmt->bind_param("i", $budgetPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    $savedData = $result->fetch_assoc();
    
    echo "<h3>Saved Data:</h3>";
    echo "<ul>";
    echo "<li>ID: " . $savedData['id'] . "</li>";
    echo "<li>User ID: " . $savedData['user_id'] . "</li>";
    echo "<li>Month: " . $savedData['month'] . "</li>";
    echo "<li>Year: " . $savedData['year'] . "</li>";
    echo "<li>Income: " . $savedData['income'] . "</li>";
    echo "<li>Expenses: " . $savedData['expenses'] . "</li>";
    echo "<li>Savings: " . $savedData['savings'] . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    $conn->rollback();
    
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='plan_budget.php' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>

