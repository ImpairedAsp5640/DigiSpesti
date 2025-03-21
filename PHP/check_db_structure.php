<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Structure Check</h1>";

$tableCheckQuery = "SHOW TABLES LIKE 'budget_plans'";
$result = $conn->query($tableCheckQuery);
$budgetPlansExists = ($result && $result->num_rows > 0);

echo "<p>budget_plans table: " . ($budgetPlansExists ? "Exists" : "Does not exist") . "</p>";

$tableCheckQuery = "SHOW TABLES LIKE 'budget_plan_items'";
$result = $conn->query($tableCheckQuery);
$budgetPlanItemsExists = ($result && $result->num_rows > 0);

echo "<p>budget_plan_items table: " . ($budgetPlanItemsExists ? "Exists" : "Does not exist") . "</p>";

if (!$budgetPlansExists) {
    $createBudgetPlansTable = "CREATE TABLE budget_plans (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        month INT(2) NOT NULL,
        year INT(4) NOT NULL,
        income DECIMAL(10, 2) NOT NULL DEFAULT 0,
        expenses DECIMAL(10, 2) NOT NULL DEFAULT 0,
        savings DECIMAL(10, 2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE KEY user_month_year (user_id, month, year)
    )";
    
    if ($conn->query($createBudgetPlansTable)) {
        echo "<p style='color:green'>Successfully created budget_plans table</p>";
    } else {
        echo "<p style='color:red'>Error creating budget_plans table: " . $conn->error . "</p>";
    }
}

if (!$budgetPlanItemsExists) {
    $createBudgetPlanItemsTable = "CREATE TABLE budget_plan_items (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        month INT(2) NOT NULL,
        year INT(4) NOT NULL,
        type ENUM('income', 'expense', 'savings') NOT NULL,
        category VARCHAR(50) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE KEY user_month_year_type_category (user_id, month, year, type, category)
    )";
    
    if ($conn->query($createBudgetPlanItemsTable)) {
        echo "<p style='color:green'>Successfully created budget_plan_items table</p>";
    } else {
        echo "<p style='color:red'>Error creating budget_plan_items table: " . $conn->error . "</p>";
        echo "<p>Error details: " . $conn->error . "</p>";
    }
}

if ($budgetPlansExists) {
    $columnsQuery = "SHOW COLUMNS FROM budget_plans";
    $result = $conn->query($columnsQuery);
    
    echo "<h2>Columns in budget_plans table:</h2>";
    echo "<ul>";
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
        $columns[] = $row['Field'];
    }
    echo "</ul>";
    
    $requiredColumns = ['id', 'user_id', 'month', 'year', 'income', 'expenses', 'savings'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (!empty($missingColumns)) {
        echo "<p style='color:red'>Missing columns in budget_plans table: " . implode(", ", $missingColumns) . "</p>";
        
        foreach ($missingColumns as $column) {
            $alterQuery = "";
            switch ($column) {
                case 'income':
                case 'expenses':
                case 'savings':
                    $alterQuery = "ALTER TABLE budget_plans ADD COLUMN $column DECIMAL(10, 2) NOT NULL DEFAULT 0";
                    break;
                case 'month':
                case 'year':
                    $alterQuery = "ALTER TABLE budget_plans ADD COLUMN $column INT NOT NULL";
                    break;
                case 'user_id':
                    $alterQuery = "ALTER TABLE budget_plans ADD COLUMN user_id INT(11) NOT NULL, ADD FOREIGN KEY (user_id) REFERENCES users(id)";
                    break;
            }
            
            if (!empty($alterQuery)) {
                if ($conn->query($alterQuery)) {
                    echo "<p style='color:green'>Added column $column to budget_plans table</p>";
                } else {
                    echo "<p style='color:red'>Error adding column $column: " . $conn->error . "</p>";
                }
            }
        }
    }
}

if ($budgetPlanItemsExists) {
    $columnsQuery = "SHOW COLUMNS FROM budget_plan_items";
    $result = $conn->query($columnsQuery);
    
    echo "<h2>Columns in budget_plan_items table:</h2>";
    echo "<ul>";
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
        $columns[] = $row['Field'];
    }
    echo "</ul>";
    
    $requiredColumns = ['id', 'user_id', 'month', 'year', 'type', 'category', 'amount'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (!empty($missingColumns)) {
        echo "<p style='color:red'>Missing columns in budget_plan_items table: " . implode(", ", $missingColumns) . "</p>";
        
        foreach ($missingColumns as $column) {
            $alterQuery = "";
            switch ($column) {
                case 'amount':
                    $alterQuery = "ALTER TABLE budget_plan_items ADD COLUMN $column DECIMAL(10, 2) NOT NULL DEFAULT 0";
                    break;
                case 'month':
                case 'year':
                    $alterQuery = "ALTER TABLE budget_plan_items ADD COLUMN $column INT NOT NULL";
                    break;
                case 'type':
                    $alterQuery = "ALTER TABLE budget_plan_items ADD COLUMN $column ENUM('income', 'expense', 'savings') NOT NULL";
                    break;
                case 'category':
                    $alterQuery = "ALTER TABLE budget_plan_items ADD COLUMN $column VARCHAR(50) NOT NULL";
                    break;
                case 'user_id':
                    $alterQuery = "ALTER TABLE budget_plan_items ADD COLUMN user_id INT(11) NOT NULL, ADD FOREIGN KEY (user_id) REFERENCES users(id)";
                    break;
            }
            
            if (!empty($alterQuery)) {
                if ($conn->query($alterQuery)) {
                    echo "<p style='color:green'>Added column $column to budget_plan_items table</p>";
                } else {
                    echo "<p style='color:red'>Error adding column $column: " . $conn->error . "</p>";
                }
            }
        }
    }
}

$tableCheckQuery = "SHOW TABLES LIKE 'users'";
$result = $conn->query($tableCheckQuery);
$usersExists = ($result && $result->num_rows > 0);

if ($usersExists) {
    echo "<p>users table: Exists</p>";
    
    $columnsQuery = "SHOW COLUMNS FROM users";
    $result = $conn->query($columnsQuery);
    
    echo "<h2>Columns in users table:</h2>";
    echo "<ul>";
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
        $columns[] = $row['Field'];
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>users table does not exist!</p>";
}

echo "<h2>Foreign Key Constraints:</h2>";

$fkQuery = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = '$database'
            AND TABLE_NAME IN ('budget_plans', 'budget_plan_items')";

$result = $conn->query($fkQuery);

if ($result && $result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['TABLE_NAME'] . "." . $row['COLUMN_NAME'] . 
             " references " . $row['REFERENCED_TABLE_NAME'] . "." . $row['REFERENCED_COLUMN_NAME'] . 
             " (Constraint: " . $row['CONSTRAINT_NAME'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No foreign key constraints found for budget tables.</p>";
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
    
    $deleteItemsQuery = "DELETE FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? AND category = 'Test Category'";
    $stmt = $conn->prepare($deleteItemsQuery);
    $stmt->bind_param("iii", $userId, $testMonth, $testYear);
    $stmt->execute();
    
    $insertItemQuery = "INSERT INTO budget_plan_items (user_id, month, year, type, category, amount) VALUES (?, ?, ?, 'income', 'Test Category', 500)";
    $stmt = $conn->prepare($insertItemQuery);
    $stmt->bind_param("iii", $userId, $testMonth, $testYear);
    $stmt->execute();
    
    echo "<p style='color:green'>Successfully inserted test record into budget_plan_items</p>";
    
    $conn->commit();
    
    echo "<p style='color:green'>Transaction committed successfully</p>";
} catch (Exception $e) {
    $conn->rollback();
    
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='plan_budget.php' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>

