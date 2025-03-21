<?php
require_once 'config.php';

// Create budget_plans table if it doesn't exist
$createBudgetPlansTable = "CREATE TABLE IF NOT EXISTS budget_plans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    month INT(2) NOT NULL,
    year INT(4) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY user_month_year (user_id, month, year)
)";

if ($conn->query($createBudgetPlansTable)) {
    echo "Successfully created budget_plans table.<br>";
} else {
    echo "Error creating budget_plans table: " . $conn->error . "<br>";
}

echo "Database update complete.";
$conn->close();
?>