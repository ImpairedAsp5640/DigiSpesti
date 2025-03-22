<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$userId = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$action = isset($_GET['action']) ? $_GET['action'] : 'overview';

echo '
<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1200px; margin: 0 auto; }
    h1, h2, h3 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .success { color: green; }
    .warning { color: orange; }
    .error { color: red; }
    .action-btn { display: inline-block; margin: 5px; padding: 8px 15px; background-color: #4CAF50; color: white; 
                 text-decoration: none; border-radius: 4px; }
    .action-btn.danger { background-color: #f44336; }
    .action-btn.warning { background-color: #ff9800; }
    .action-btn.info { background-color: #2196F3; }
    .nav-tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #ddd; }
    .nav-tab { padding: 10px 15px; text-decoration: none; color: #333; }
    .nav-tab.active { border: 1px solid #ddd; border-bottom: none; background-color: #f9f9f9; }
    .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
    pre { background-color: #f5f5f5; padding: 10px; overflow: auto; }
</style>
';

echo "<h1>Budget Database Debug Tools</h1>";

echo '<div class="nav-tabs">';
echo '<a href="?action=overview" class="nav-tab ' . ($action == 'overview' ? 'active' : '') . '">Overview</a>';
echo '<a href="?action=table_structure" class="nav-tab ' . ($action == 'table_structure' ? 'active' : '') . '">Table Structure</a>';
echo '<a href="?action=categories" class="nav-tab ' . ($action == 'categories' ? 'active' : '') . '">Categories</a>';
echo '<a href="?action=duplicates" class="nav-tab ' . ($action == 'duplicates' ? 'active' : '') . '">Duplicates</a>';
echo '<a href="?action=fix_tools" class="nav-tab ' . ($action == 'fix_tools' ? 'active' : '') . '">Fix Tools</a>';
echo '</div>';

if ($action == 'overview') {
    echo "<div class='section'>";
    echo "<h2>Database Overview</h2>";
    
    $tables = ['users', 'budget_plans', 'budget_plan_items', 'transactions'];
    echo "<h3>Tables Status:</h3>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Status</th><th>Row Count</th></tr>";
    
    foreach ($tables as $table) {
        $tableCheckQuery = "SHOW TABLES LIKE '$table'";
        $result = $conn->query($tableCheckQuery);
        $tableExists = ($result && $result->num_rows > 0);
        
        $rowCount = 0;
        if ($tableExists) {
            $countQuery = "SELECT COUNT(*) as count FROM $table";
            $countResult = $conn->query($countQuery);
            if ($countResult) {
                $rowCount = $countResult->fetch_assoc()['count'];
            }
        }
        
        echo "<tr>";
        echo "<td>$table</td>";
        echo "<td>" . ($tableExists ? "<span class='success'>Exists</span>" : "<span class='error'>Missing</span>") . "</td>";
        echo "<td>" . ($tableExists ? $rowCount : "N/A") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Current User Data:</h3>";
    $userQuery = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows > 0) {
        $userData = $userResult->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>ID</td><td>{$userData['id']}</td></tr>";
        echo "<tr><td>Username</td><td>{$userData['username']}</td></tr>";
        echo "<tr><td>Balance</td><td>{$userData['balance']}</td></tr>";
        echo "<tr><td>Created At</td><td>{$userData['created_at']}</td></tr>";
        echo "</table>";
    } else {
        echo "<p class='error'>User not found!</p>";
    }
    
    echo "<h3>Budget Data for $month/$year:</h3>";
    $budgetQuery = "SELECT * FROM budget_plans WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($budgetQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $budgetResult = $stmt->get_result();
    
    if ($budgetResult->num_rows > 0) {
        $budgetData = $budgetResult->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($budgetData as $key => $value) {
            echo "<tr><td>$key</td><td>$value</td></tr>";
        }
        echo "</table>";
        
        $itemsQuery = "SELECT * FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? ORDER BY type, category";
        $stmt = $conn->prepare($itemsQuery);
        $stmt->bind_param("iii", $userId, $month, $year);
        $stmt->execute();
        $itemsResult = $stmt->get_result();
        
        if ($itemsResult->num_rows > 0) {
            echo "<h4>Budget Items:</h4>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Type</th><th>Category</th><th>Amount</th></tr>";
            
            while ($item = $itemsResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$item['id']}</td>";
                echo "<td>{$item['type']}</td>";
                echo "<td>{$item['category']}</td>";
                echo "<td>{$item['amount']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No budget items found for this month/year.</p>";
        }
    } else {
        echo "<p>No budget plan found for this month/year.</p>";
    }
    
    echo "</div>";
}

if ($action == 'table_structure') {
    echo "<div class='section'>";
    echo "<h2>Table Structure</h2>";
    
    $tables = ['users', 'budget_plans', 'budget_plan_items', 'transactions'];
    
    foreach ($tables as $table) {
        $tableCheckQuery = "SHOW TABLES LIKE '$table'";
        $result = $conn->query($tableCheckQuery);
        $tableExists = ($result && $result->num_rows > 0);
        
        if ($tableExists) {
            echo "<h3>$table Table:</h3>";
            
            $columnsQuery = "SHOW COLUMNS FROM $table";
            $columnsResult = $conn->query($columnsQuery);
            
            if ($columnsResult->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                
                while ($column = $columnsResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$column['Field']}</td>";
                    echo "<td>{$column['Type']}</td>";
                    echo "<td>{$column['Null']}</td>";
                    echo "<td>{$column['Key']}</td>";
                    echo "<td>{$column['Default']}</td>";
                    echo "<td>{$column['Extra']}</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
            
            $indexesQuery = "SHOW INDEXES FROM $table";
            $indexesResult = $conn->query($indexesQuery);
            
            if ($indexesResult->num_rows > 0) {
                echo "<h4>Indexes:</h4>";
                echo "<table>";
                echo "<tr><th>Key Name</th><th>Column</th><th>Non Unique</th><th>Seq in Index</th></tr>";
                
                while ($index = $indexesResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$index['Key_name']}</td>";
                    echo "<td>{$index['Column_name']}</td>";
                    echo "<td>{$index['Non_unique']}</td>";
                    echo "<td>{$index['Seq_in_index']}</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
        } else {
            echo "<h3>$table Table: <span class='error'>Does not exist</span></h3>";
        }
    }
    
    echo "</div>";
}

if ($action == 'categories') {
    echo "<div class='section'>";
    echo "<h2>Categories Analysis</h2>";
    
    $categoriesQuery = "SELECT DISTINCT category FROM budget_plan_items ORDER BY category";
    $categoriesResult = $conn->query($categoriesQuery);
    
    if ($categoriesResult->num_rows > 0) {
        echo "<h3>All Categories:</h3>";
        echo "<table>";
        echo "<tr><th>Category</th><th>Count</th><th>Total Amount</th><th>Actions</th></tr>";
        
        while ($category = $categoriesResult->fetch_assoc()) {
            $categoryName = $category['category'];
            
            $statsQuery = "SELECT COUNT(*) as count, SUM(amount) as total FROM budget_plan_items WHERE category = ?";
            $stmt = $conn->prepare($statsQuery);
            $stmt->bind_param("s", $categoryName);
            $stmt->execute();
            $statsResult = $stmt->get_result();
            $stats = $statsResult->fetch_assoc();
            
            echo "<tr>";
            echo "<td>$categoryName</td>";
            echo "<td>{$stats['count']}</td>";
            echo "<td>{$stats['total']}</td>";
            echo "<td><a href='?action=category_details&category=" . urlencode($categoryName) . "' class='action-btn info'>Details</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No categories found.</p>";
    }
    
    echo "<h3>Potential English/Bulgarian Category Pairs:</h3>";
    
    $potentialPairs = [
        ['Salary', 'Заплата'],
        ['Previous month balance', 'Баланс от предходен месец'],
        ['Kids', 'Деца'],
        ['Transport', 'Транспорт'],
        ['Housing', 'Жилище'],
        ['Utilities', 'Битови сметки'],
        ['Food and groceries', 'Храна и консулмативи'],
        ['Car', 'Автомобил'],
        ['Clothing and footwear', 'Дрехи и обувки'],
        ['Personal', 'Лични'],
        ['Cigarettes and alcohol', 'Цигари и алкохол'],
        ['Entertainment', 'Развлечения'],
        ['Eating out', 'Хранене навън'],
        ['Education', 'Образование'],
        ['Gifts', 'Подаръци'],
        ['Sports/Hobby', 'Спорт/Хоби'],
        ['Travel/Leisure', 'Пътуване/Отдих'],
        ['Medical', 'Медицински'],
        ['Pets', 'Домашни любимци'],
        ['Miscellaneous', 'Разни']
    ];
    
    echo "<table>";
    echo "<tr><th>English</th><th>Bulgarian</th><th>English Count</th><th>Bulgarian Count</th><th>Actions</th></tr>";
    
    foreach ($potentialPairs as $pair) {
        $english = $pair[0];
        $bulgarian = $pair[1];
        
        $englishQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
        $stmt = $conn->prepare($englishQuery);
        $stmt->bind_param("s", $english);
        $stmt->execute();
        $englishResult = $stmt->get_result();
        $englishCount = $englishResult->fetch_assoc()['count'];
        
        $bulgarianQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
        $stmt = $conn->prepare($bulgarianQuery);
        $stmt->bind_param("s", $bulgarian);
        $stmt->execute();
        $bulgarianResult = $stmt->get_result();
        $bulgarianCount = $bulgarianResult->fetch_assoc()['count'];
        
        $rowClass = '';
        if ($englishCount > 0 && $bulgarianCount > 0) {
            $rowClass = 'class="warning"';
        }
        
        echo "<tr $rowClass>";
        echo "<td>$english</td>";
        echo "<td>$bulgarian</td>";
        echo "<td>$englishCount</td>";
        echo "<td>$bulgarianCount</td>";
        echo "<td>";
        
        if ($englishCount > 0 && $bulgarianCount > 0) {
            echo "<a href='?action=merge_categories&from=" . urlencode($english) . "&to=" . urlencode($bulgarian) . "' class='action-btn warning'>Merge</a>";
        } elseif ($englishCount > 0) {
            echo "<a href='?action=rename_category&from=" . urlencode($english) . "&to=" . urlencode($bulgarian) . "' class='action-btn info'>Rename</a>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "</div>";
}

if ($action == 'category_details') {
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    
    if (!empty($category)) {
        echo "<div class='section'>";
        echo "<h2>Category Details: $category</h2>";
        
        $recordsQuery = "SELECT * FROM budget_plan_items WHERE category = ? ORDER BY year DESC, month DESC, user_id";
        $stmt = $conn->prepare($recordsQuery);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $recordsResult = $stmt->get_result();
        
        if ($recordsResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>User ID</th><th>Month</th><th>Year</th><th>Type</th><th>Amount</th><th>Created</th><th>Updated</th></tr>";
            
            while ($record = $recordsResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$record['id']}</td>";
                echo "<td>{$record['user_id']}</td>";
                echo "<td>{$record['month']}</td>";
                echo "<td>{$record['year']}</td>";
                echo "<td>{$record['type']}</td>";
                echo "<td>{$record['amount']}</td>";
                echo "<td>{$record['created_at']}</td>";
                echo "<td>{$record['updated_at']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            echo "<h3>Rename Category:</h3>";
            echo "<form method='GET' action=''>";
            echo "<input type='hidden' name='action' value='rename_category'>";
            echo "<input type='hidden' name='from' value='$category'>";
            echo "<label>New Name: <input type='text' name='to' required></label>";
            echo "<button type='submit' class='action-btn warning'>Rename</button>";
            echo "</form>";
        } else {
            echo "<p>No records found for this category.</p>";
        }
        
        echo "</div>";
    }
}

if ($action == 'duplicates') {
    echo "<div class='section'>";
    echo "<h2>Duplicate Analysis</h2>";
    
    $duplicatesQuery = "SELECT user_id, month, year, type, COUNT(DISTINCT category) as category_count
                       FROM budget_plan_items
                       GROUP BY user_id, month, year, type
                       HAVING COUNT(DISTINCT category) > 1";
    $duplicatesResult = $conn->query($duplicatesQuery);
    
    if ($duplicatesResult->num_rows > 0) {
        echo "<h3>Users with Multiple Categories for Same Type:</h3>";
        echo "<table>";
        echo "<tr><th>User ID</th><th>Month</th><th>Year</th><th>Type</th><th>Category Count</th><th>Actions</th></tr>";
        
        while ($duplicate = $duplicatesResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$duplicate['user_id']}</td>";
            echo "<td>{$duplicate['month']}</td>";
            echo "<td>{$duplicate['year']}</td>";
            echo "<td>{$duplicate['type']}</td>";
            echo "<td>{$duplicate['category_count']}</td>";
            echo "<td><a href='?action=user_categories&user_id={$duplicate['user_id']}&month={$duplicate['month']}&year={$duplicate['year']}&type={$duplicate['type']}' class='action-btn info'>View</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='success'>No users with multiple categories of the same type found.</p>";
    }
    
    $duplicateEntriesQuery = "SELECT user_id, month, year, type, category, COUNT(*) as entry_count
                             FROM budget_plan_items
                             GROUP BY user_id, month, year, type, category
                             HAVING COUNT(*) > 1";
    $duplicateEntriesResult = $conn->query($duplicateEntriesQuery);
    
    if ($duplicateEntriesResult->num_rows > 0) {
        echo "<h3>Duplicate Entries (Same User, Month, Year, Type, Category):</h3>";
        echo "<table>";
        echo "<tr><th>User ID</th><th>Month</th><th>Year</th><th>Type</th><th>Category</th><th>Count</th><th>Actions</th></tr>";
        
        while ($duplicate = $duplicateEntriesResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$duplicate['user_id']}</td>";
            echo "<td>{$duplicate['month']}</td>";
            echo "<td>{$duplicate['year']}</td>";
            echo "<td>{$duplicate['type']}</td>";
            echo "<td>{$duplicate['category']}</td>";
            echo "<td>{$duplicate['entry_count']}</td>";
            echo "<td><a href='?action=fix_duplicate_entries&user_id={$duplicate['user_id']}&month={$duplicate['month']}&year={$duplicate['year']}&type={$duplicate['type']}&category=" . urlencode($duplicate['category']) . "' class='action-btn warning'>Fix</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p class='success'>No duplicate entries found.</p>";
    }
    
    $zeroQuery = "SELECT * FROM budget_plan_items WHERE category = '0'";
    $zeroResult = $conn->query($zeroQuery);
    
    if ($zeroResult->num_rows > 0) {
        echo "<h3>Entries with Category '0':</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>User ID</th><th>Month</th><th>Year</th><th>Type</th><th>Amount</th><th>Actions</th></tr>";
        
        while ($zero = $zeroResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$zero['id']}</td>";
            echo "<td>{$zero['user_id']}</td>";
            echo "<td>{$zero['month']}</td>";
            echo "<td>{$zero['year']}</td>";
            echo "<td>{$zero['type']}</td>";
            echo "<td>{$zero['amount']}</td>";
            echo "<td><a href='?action=delete_entry&id={$zero['id']}' class='action-btn danger'>Delete</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<p><a href='?action=delete_all_zero' class='action-btn danger'>Delete All Category '0' Entries</a></p>";
    } else {
        echo "<p class='success'>No entries with category '0' found.</p>";
    }
    
    echo "</div>";
}

if ($action == 'user_categories') {
    $userIdParam = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $monthParam = isset($_GET['month']) ? intval($_GET['month']) : 0;
    $yearParam = isset($_GET['year']) ? intval($_GET['year']) : 0;
    $typeParam = isset($_GET['type']) ? $_GET['type'] : '';
    
    if ($userIdParam > 0 && $monthParam > 0 && $yearParam > 0 && !empty($typeParam)) {
        echo "<div class='section'>";
        echo "<h2>Categories for User $userIdParam, $monthParam/$yearParam, Type: $typeParam</h2>";
        
        $categoriesQuery = "SELECT * FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? AND type = ? ORDER BY category";
        $stmt = $conn->prepare($categoriesQuery);
        $stmt->bind_param("iiis", $userIdParam, $monthParam, $yearParam, $typeParam);
        $stmt->execute();
        $categoriesResult = $stmt->get_result();
        
        if ($categoriesResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Category</th><th>Amount</th><th>Created</th><th>Updated</th><th>Actions</th></tr>";
            
            while ($category = $categoriesResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$category['id']}</td>";
                echo "<td>{$category['category']}</td>";
                echo "<td>{$category['amount']}</td>";
                echo "<td>{$category['created_at']}</td>";
                echo "<td>{$category['updated_at']}</td>";
                echo "<td>";
                echo "<a href='?action=delete_entry&id={$category['id']}' class='action-btn danger'>Delete</a> ";
                echo "<a href='?action=edit_entry&id={$category['id']}' class='action-btn info'>Edit</a>";
                echo "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            echo "<h3>Merge All Categories:</h3>";
            echo "<form method='GET' action=''>";
            echo "<input type='hidden' name='action' value='merge_all_categories'>";
            echo "<input type='hidden' name='user_id' value='$userIdParam'>";
            echo "<input type='hidden' name='month' value='$monthParam'>";
            echo "<input type='hidden' name='year' value='$yearParam'>";
            echo "<input type='hidden' name='type' value='$typeParam'>";
            echo "<label>Target Category: <select name='target_category'>";
            
            $categoriesResult->data_seek(0);
            while ($category = $categoriesResult->fetch_assoc()) {
                echo "<option value='" . htmlspecialchars($category['category']) . "'>" . htmlspecialchars($category['category']) . "</option>";
            }
            
            echo "</select></label>";
            echo "<button type='submit' class='action-btn warning'>Merge All to Selected Category</button>";
            echo "</form>";
        } else {
            echo "<p>No categories found.</p>";
        }
        
        echo "</div>";
    }
}

if ($action == 'fix_tools') {
    echo "<div class='section'>";
    echo "<h2>Fix Tools</h2>";
    
    echo "<div style='display: flex; flex-wrap: wrap; gap: 20px;'>";
    
    echo "<div style='flex: 1; min-width: 300px; border: 1px solid #ddd; padding: 15px; border-radius: 4px;'>";
    echo "<h3>Fix English to Bulgarian Categories</h3>";
    echo "<p>This tool will replace English category names with their Bulgarian equivalents.</p>";
    echo "<a href='?action=fix_english_categories' class='action-btn warning'>Run Fix</a>";
    echo "</div>";
    
    echo "<div style='flex: 1; min-width: 300px; border: 1px solid #ddd; padding: 15px; border-radius: 4px;'>";
    echo "<h3>Fix Duplicate Entries</h3>";
    echo "<p>This tool will merge duplicate entries (same user, month, year, type, category).</p>";
    echo "<a href='?action=fix_all_duplicates' class='action-btn warning'>Run Fix</a>";
    echo "</div>";
    
    echo "<div style='flex: 1; min-width: 300px; border: 1px solid #ddd; padding: 15px; border-radius: 4px;'>";
    echo "<h3>Delete Category '0' Entries</h3>";
    echo "<p>This tool will delete all entries with category '0'.</p>";
    echo "<a href='?action=delete_all_zero' class='action-btn danger'>Run Fix</a>";
    echo "</div>";
    
    echo "<div style='flex: 1; min-width: 300px; border: 1px solid #ddd; padding: 15px; border-radius: 4px;'>";
    echo "<h3>Update Budget Plans Totals</h3>";
    echo "<p>This tool will recalculate and update the totals in the budget_plans table.</p>";
    echo "<a href='?action=update_budget_totals' class='action-btn info'>Run Fix</a>";
    echo "</div>";
    
    echo "</div>";
    
    echo "<h3>Run Custom SQL Query</h3>";
    echo "<p class='warning'>Warning: Be careful with custom SQL queries as they can modify or delete data!</p>";
    echo "<form method='POST' action='?action=run_sql'>";
    echo "<textarea name='sql_query' rows='5' style='width: 100%; margin-bottom: 10px;' placeholder='Enter your SQL query here...'></textarea><br>";
    echo "<button type='submit' class='action-btn danger'>Run Query</button>";
    echo "</form>";
    
    echo "</div>";
}

if ($action == 'fix_english_categories') {
    echo "<div class='section'>";
    echo "<h2>Fixing English Categories</h2>";
    
    $categoryMapping = [
        'Salary' => 'Заплата',
        'Previous month balance' => 'Баланс от предходен месец',
        'Kids' => 'Деца',
        'Transport' => 'Транспорт',
        'Housing' => 'Жилище',
        'Utilities' => 'Битови сметки',
        'Food and groceries' => 'Храна и консулмативи',
        'Car' => 'Автомобил',
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
    
    $totalUpdated = 0;
    
    foreach ($categoryMapping as $englishCategory => $bulgarianCategory) {
        echo "<h3>Processing: $englishCategory > $bulgarianCategory</h3>";
        
        $checkQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $englishCategory);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            echo "<p>Found $count items with category '$englishCategory'</p>";
            
            $checkBulgarianQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
            $stmt = $conn->prepare($checkBulgarianQuery);
            $stmt->bind_param("s", $bulgarianCategory);
            $stmt->execute();
            $result = $stmt->get_result();
            $bulgarianCount = $result->fetch_assoc()['count'];
            
            if ($bulgarianCount > 0) {
                echo "<p>Both categories exist. Merging data...</p>";
                
                $conn->begin_transaction();
                
                try {
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
                        echo "<p class='success'>Merged $mergeCount records</p>";
                    }
                    
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
                    if ($remainingUpdated > 0) {
                        echo "<p class='success'>Renamed $remainingUpdated remaining records from $englishCategory to $bulgarianCategory</p>";
                        $totalUpdated += $remainingUpdated;
                    }
                    
                    $conn->commit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    echo "<p class='error'>Error merging categories: " . $e->getMessage() . "</p>";
                }
            } else {
                echo "<p>Only English category exists. Renaming all instances...</p>";
                
                $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ss", $bulgarianCategory, $englishCategory);
                $stmt->execute();
                
                $affectedRows = $stmt->affected_rows;
                echo "<p class='success'>Renamed $affectedRows records from $englishCategory to $bulgarianCategory</p>";
                $totalUpdated += $affectedRows;
            }
        } else {
            echo "<p>No records found with category '$englishCategory'</p>";
        }
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p>Total records updated: <strong>$totalUpdated</strong></p>";
    
    echo "<h3>Updating budget_plans totals...</h3>";
    
    $updatePlansQuery = "SELECT DISTINCT user_id, month, year FROM budget_plan_items";
    $result = $conn->query($updatePlansQuery);
    
    $updatedPlans = 0;
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
            $updatedPlans++;
        }
    }
    
    echo "<p class='success'>Updated totals for $updatedPlans budget plans</p>";
    
    echo "<p><a href='?action=categories' class='action-btn'>Back to Categories</a></p>";
    echo "</div>";
}

if ($action == 'fix_all_duplicates') {
    echo "<div class='section'>";
    echo "<h2>Fixing All Duplicate Entries</h2>";
    
    $duplicateEntriesQuery = "SELECT user_id, month, year, type, category, COUNT(*) as entry_count
                             FROM budget_plan_items
                             GROUP BY user_id, month, year, type, category
                             HAVING COUNT(*) > 1";
    $duplicateEntriesResult = $conn->query($duplicateEntriesQuery);
    
    if ($duplicateEntriesResult->num_rows > 0) {
        echo "<p>Found " . $duplicateEntriesResult->num_rows . " groups of duplicate entries.</p>";
        
        $fixedGroups = 0;
        
        while ($duplicate = $duplicateEntriesResult->fetch_assoc()) {
            $userId = $duplicate['user_id'];
            $month = $duplicate['month'];
            $year = $duplicate['year'];
            $type = $duplicate['type'];
            $category = $duplicate['category'];
            
            echo "<h3>Fixing duplicates for User $userId, $month/$year, Type: $type, Category: $category</h3>";
            
            $entriesQuery = "SELECT id, amount FROM budget_plan_items 
                            WHERE user_id = ? AND month = ? AND year = ? AND type = ? AND category = ?
                            ORDER BY id";
            $stmt = $conn->prepare($entriesQuery);
            $stmt->bind_param("iiiss", $userId, $month, $year, $type, $category);
            $stmt->execute();
            $entriesResult = $stmt->get_result();
            
            $entries = [];
            $totalAmount = 0;
            
            while ($entry = $entriesResult->fetch_assoc()) {
                $entries[] = $entry;
                $totalAmount += $entry['amount'];
            }
            
            if (count($entries) > 0) {
                $keepId = $entries[0]['id'];
                
                $updateQuery = "UPDATE budget_plan_items SET amount = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("di", $totalAmount, $keepId);
                $stmt->execute();
                
                echo "<p>Updated entry ID $keepId with total amount $totalAmount</p>";
                
                $deleteIds = [];
                for ($i = 1; $i < count($entries); $i++) {
                    $deleteIds[] = $entries[$i]['id'];
                }
                
                if (!empty($deleteIds)) {
                    $deleteQuery = "DELETE FROM budget_plan_items WHERE id IN (" . implode(',', $deleteIds) . ")";
                    $conn->query($deleteQuery);
                    
                    echo "<p class='success'>Deleted " . count($deleteIds) . " duplicate entries</p>";
                }
                
                $fixedGroups++;
            }
        }
        
        echo "<h3>Summary:</h3>";
        echo "<p class='success'>Fixed $fixedGroups groups of duplicate entries</p>";
        
        echo "<h3>Updating budget_plans totals...</h3>";
        
        $updatePlansQuery = "SELECT DISTINCT user_id, month, year FROM budget_plan_items";
        $result = $conn->query($updatePlansQuery);
        
        $updatedPlans = 0;
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
                $updatedPlans++;
            }
        }
        
        echo "<p class='success'>Updated totals for $updatedPlans budget plans</p>";
    } else {
        echo "<p class='success'>No duplicate entries found.</p>";
    }
    
    echo "<p><a href='?action=duplicates' class='action-btn'>Back to Duplicates</a></p>";
    echo "</div>";
}

if ($action == 'delete_all_zero') {
    echo "<div class='section'>";
    echo "<h2>Deleting All Category '0' Entries</h2>";
    
    $deleteQuery = "DELETE FROM budget_plan_items WHERE category = '0'";
    $conn->query($deleteQuery);
    
    $affectedRows = $conn->affected_rows;
    
    if ($affectedRows > 0) {
        echo "<p class='success'>Deleted $affectedRows entries with category '0'</p>";
        
        echo "<h3>Updating budget_plans totals...</h3>";
        
        $updatePlansQuery = "SELECT DISTINCT user_id, month, year FROM budget_plan_items";
        $result = $conn->query($updatePlansQuery);
        
        $updatedPlans = 0;
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
                $updatedPlans++;
            }
        }
        
        echo "<p class='success'>Updated totals for $updatedPlans budget plans</p>";
    } else {
        echo "<p>No entries with category '0' found.</p>";
    }
    
    echo "<p><a href='?action=duplicates' class='action-btn'>Back to Duplicates</a></p>";
    echo "</div>";
}

if ($action == 'update_budget_totals') {
    echo "<div class='section'>";
    echo "<h2>Updating Budget Plans Totals</h2>";
    
    $updatePlansQuery = "SELECT DISTINCT user_id, month, year FROM budget_plan_items";
    $result = $conn->query($updatePlansQuery);
    
    $updatedPlans = 0;
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
        
        $checkQuery = "SELECT id FROM budget_plans WHERE user_id = ? AND month = ? AND year = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("iii", $userId, $month, $year);
        $stmt->execute();
        $checkResult = $stmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $updateQuery = "UPDATE budget_plans SET income = ?, expenses = ?, savings = ? WHERE user_id = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("dddiii", $totalIncome, $totalExpenses, $totalSavings, $userId, $month, $year);
            $stmt->execute();
        } else {
            $insertQuery = "INSERT INTO budget_plans (user_id, month, year, income, expenses, savings) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iiiddd", $userId, $month, $year, $totalIncome, $totalExpenses, $totalSavings);
            $stmt->execute();
        }
        
        $updatedPlans++;
        echo "<p>Updated budget plan for User: $userId, Month: $month, Year: $year with Income: $totalIncome, Expenses: $totalExpenses, Savings: $totalSavings</p>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p class='success'>Updated totals for $updatedPlans budget plans</p>";
    
    echo "<p><a href='?action=overview' class='action-btn'>Back to Overview</a></p>";
    echo "</div>";
}

if ($action == 'run_sql') {
    echo "<div class='section'>";
    echo "<h2>Run SQL Query</h2>";
    
    if (isset($_POST['sql_query']) && !empty($_POST['sql_query'])) {
        $sqlQuery = $_POST['sql_query'];
        
        echo "<h3>Query:</h3>";
        echo "<pre>" . htmlspecialchars($sqlQuery) . "</pre>";
        
        try {
            $result = $conn->query($sqlQuery);
            
            if ($result === TRUE) {
                echo "<p class='success'>Query executed successfully. Affected rows: " . $conn->affected_rows . "</p>";
            } elseif ($result === FALSE) {
                echo "<p class='error'>Error executing query: " . $conn->error . "</p>";
            } else {
                echo "<h3>Results:</h3>";
                
                if ($result->num_rows > 0) {
                    echo "<table>";
                    
                    $firstRow = $result->fetch_assoc();
                    echo "<tr>";
                    foreach ($firstRow as $key => $value) {
                        echo "<th>" . htmlspecialchars($key) . "</th>";
                    }
                    echo "</tr>";
                    
                    echo "<tr>";
                    foreach ($firstRow as $value) {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                    echo "</tr>";
                    
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        foreach ($row as $value) {
                            echo "<td>" . htmlspecialchars($value) . "</td>";
                        }
                        echo "</tr>";
                    }
                    
                    echo "</table>";
                } else {
                    echo "<p>No results found.</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>No SQL query provided.</p>";
    }
    
    echo "<p><a href='?action=fix_tools' class='action-btn'>Back to Fix Tools</a></p>";
    echo "</div>";
}

if ($action == 'rename_category') {
    $fromCategory = isset($_GET['from']) ? $_GET['from'] : '';
    $toCategory = isset($_GET['to']) ? $_GET['to'] : '';
    
    if (!empty($fromCategory) && !empty($toCategory)) {
        echo "<div class='section'>";
        echo "<h2>Renaming Category</h2>";
        
        echo "<p>Renaming category from <strong>$fromCategory</strong> to <strong>$toCategory</strong></p>";
        
        $checkQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $toCategory);
        $stmt->execute();
        $result = $stmt->get_result();
        $targetExists = $result->fetch_assoc()['count'] > 0;
        
        if ($targetExists) {
            echo "<p class='warning'>Warning: Target category '$toCategory' already exists. This will merge the categories.</p>";
            
            $conn->begin_transaction();
            
            try {
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
                $stmt->bind_param("ss", $fromCategory, $toCategory);
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
                    $stmt->bind_param("iiisss", $userId, $month, $year, $type, $fromCategory, $toCategory);
                    $stmt->execute();
                    $sumResult = $stmt->get_result();
                    $totalAmount = $sumResult->fetch_assoc()['total'];
                    
                    $updateQuery = "
                        UPDATE budget_plan_items 
                        SET amount = ? 
                        WHERE user_id = ? AND month = ? AND year = ? AND type = ? AND category = ?
                    ";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("diiiss", $totalAmount, $userId, $month, $year, $type, $toCategory);
                    $stmt->execute();
                    
                    $deleteQuery = "
                        DELETE FROM budget_plan_items 
                        WHERE user_id = ? AND month = ? AND year = ? AND type = ? AND category = ?
                    ";
                    $stmt = $conn->prepare($deleteQuery);
                    $stmt->bind_param("iiiss", $userId, $month, $year, $type, $fromCategory);
                    $stmt->execute();
                    
                    $mergeCount++;
                }
                
                if ($mergeCount > 0) {
                    echo "<p class='success'>Merged $mergeCount records</p>";
                }
                
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
                $stmt->bind_param("sss", $toCategory, $fromCategory, $toCategory);
                $stmt->execute();
                
                $remainingUpdated = $stmt->affected_rows;
                if ($remainingUpdated > 0) {
                    echo "<p class='success'>Renamed $remainingUpdated remaining records</p>";
                }
                
                $conn->commit();
                echo "<p class='success'>Category renamed/merged successfully</p>";
                
            } catch (Exception $e) {
                $conn->rollback();
                echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
            }
        } else {

            $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ss", $toCategory, $fromCategory);
            $stmt->execute();
            
            $affectedRows = $stmt->affected_rows;
            echo "<p class='success'>Renamed $affectedRows records</p>";
        }
        
        echo "<h3>Updating budget_plans totals...</h3>";
        
        $updatePlansQuery = "SELECT DISTINCT user_id, month, year FROM budget_plan_items";
        $result = $conn->query($updatePlansQuery);
        
        $updatedPlans = 0;
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
                $updatedPlans++;
            }
        }
        
        echo "<p class='success'>Updated totals for $updatedPlans budget plans</p>";
        
        echo "<p><a href='?action=categories' class='action-btn'>Back to Categories</a></p>";
        echo "</div>";
    }
}

$conn->close();
?>