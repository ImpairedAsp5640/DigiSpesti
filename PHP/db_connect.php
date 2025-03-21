<?php
// Database connection details
$host = "database-1.cluster-cgnbwogrvdhe.eu-central-1.rds.amazonaws.com";
$username = "impairedasp5640";
$password = ">)0qTEMVW(19#)ez|2WF8RhupP21";
$database = "database1";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
function createTables($conn) {
    // Users table
    $usersTable = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        balance DECIMAL(10, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    // Transactions table
    $transactionsTable = "CREATE TABLE IF NOT EXISTS transactions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        type ENUM('income', 'expense', 'savings') NOT NULL,
        category VARCHAR(50) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        comment TEXT,
        transaction_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    // Execute queries
    if (!$conn->query($usersTable)) {
        echo "Error creating users table: " . $conn->error;
    }
    
    if (!$conn->query($transactionsTable)) {
        echo "Error creating transactions table: " . $conn->error;
    }
}

// Call the function to create tables
createTables($conn);
?>