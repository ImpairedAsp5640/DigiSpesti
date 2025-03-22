<?php
require_once 'config.php';

$createBudgetPlansTable = "CREATE TABLE IF NOT EXISTS budget_plans (
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
    echo "Successfully created budget_plans table.<br>";
} else {
    echo "Error creating budget_plans table: " . $conn->error . "<br>";
}

$createBudgetPlanItemsTable = "CREATE TABLE IF NOT EXISTS budget_plan_items (
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
    echo "Successfully created budget_plan_items table.<br>";
} else {
    echo "Error creating budget_plan_items table: " . $conn->error . "<br>";
}

echo "Database update complete.";
$conn->close();
?>