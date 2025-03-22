<?php
require_once 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Update Script</h1>";
echo "<p>Creating savings_goals table...</p>";

$createSavingsGoalsTable = "CREATE TABLE IF NOT EXISTS savings_goals (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    name VARCHAR(255) NOT NULL,
    target_amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

try {
    if ($conn->query($createSavingsGoalsTable)) {
        echo "<p style='color: green;'>Successfully created savings_goals table.</p>";
    } else {
        echo "<p style='color: red;'>Error creating savings_goals table: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

$checkTableQuery = "SHOW TABLES LIKE 'savings_goals'";
$result = $conn->query($checkTableQuery);
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>Verified: savings_goals table exists.</p>";
} else {
    echo "<p style='color: red;'>Verification failed: savings_goals table does not exist.</p>";
    
    echo "<p>Attempting alternative approach...</p>";
    
    $createTableDirectly = "
    CREATE TABLE savings_goals (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        name VARCHAR(255) NOT NULL,
        target_amount DECIMAL(10, 2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    try {
        if ($conn->query($createTableDirectly)) {
            echo "<p style='color: green;'>Successfully created savings_goals table using alternative approach.</p>";
            
            // Add foreign key constraint separately
            $addForeignKey = "ALTER TABLE savings_goals 
                             ADD CONSTRAINT fk_savings_user 
                             FOREIGN KEY (user_id) REFERENCES users(id)";
            
            if ($conn->query($addForeignKey)) {
                echo "<p style='color: green;'>Successfully added foreign key constraint.</p>";
            } else {
                echo "<p style='color: orange;'>Warning: Could not add foreign key constraint: " . $conn->error . "</p>";
                echo "<p>This is not critical - the table will still work without the constraint.</p>";
            }
        } else {
            echo "<p style='color: red;'>Error creating savings_goals table using alternative approach: " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception in alternative approach: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>Updating savings.php</h2>";
echo "<p>Modifying the savings.php file to ensure table creation happens before queries...</p>";

$savingsPhpPath = __DIR__ . '/savings.php';
if (file_exists($savingsPhpPath)) {
    $content = file_get_contents($savingsPhpPath);
    
    if (is_writable($savingsPhpPath)) {
        // Move the table creation code to the top, before any queries
        $pattern = '/\/\/ Check if savings_goals table exists, if not create it(.*?)}/s';
        if (preg_match($pattern, $content, $matches)) {
            $tableCreationCode = $matches[0];
            $content = str_replace($tableCreationCode, '', $content);
            
            $insertPosition = strpos($content, 'require_once \'config.php\';') + strlen('require_once \'config.php\';');
            $newContent = substr($content, 0, $insertPosition) . "\n\n// Check if savings_goals table exists, if not create it\n" . $tableCreationCode . "\n" . substr($content, $insertPosition);
            
            if (file_put_contents($savingsPhpPath, $newContent)) {
                echo "<p style='color: green;'>Successfully updated savings.php file.</p>";
            } else {
                echo "<p style='color: red;'>Failed to write to savings.php file.</p>";
            }
        } else {
            echo "<p style='color: orange;'>Could not find table creation code in savings.php.</p>";
        }
    } else {
        echo "<p style='color: orange;'>savings.php file is not writable. Please manually move the table creation code to the top of the file, right after the database connection.</p>";
    }
} else {
    echo "<p style='color: orange;'>savings.php file not found in the expected location.</p>";
}

echo "<h2>Database Update Complete</h2>";
echo "<p>You can now <a href='savings.php'>go to the Savings page</a>.</p>";

$conn->close();
?>

