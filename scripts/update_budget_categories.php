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

echo "<h2>Found Categories:</h2>";
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
    $checkQuery = "SELECT COUNT(*) as count FROM budget_plan_items WHERE category = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $englishCategory);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        echo "<p>Updating category: <strong>$englishCategory</strong> to <strong>$bulgarianCategory</strong></p>";
        
        $updateQuery = "UPDATE budget_plan_items SET category = ? WHERE category = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ss", $bulgarianCategory, $englishCategory);
        $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        $totalUpdated += $affectedRows;
        
        echo "<p style='color:green'>Updated $affectedRows records</p>";
    } else {
        echo "<p>Category <strong>$englishCategory</strong> not found in database</p>";
    }
}

echo "<h2>Summary:</h2>";
echo "<p>Total records updated: <strong>$totalUpdated</strong></p>";

$remainingEnglishQuery = "SELECT DISTINCT category FROM budget_plan_items WHERE " . 
    implode(" OR ", array_map(function($cat) use ($conn) { 
        return "category = '" . $conn->real_escape_string($cat) . "'"; 
    }, array_keys($categoryMapping)));

$result = $conn->query($remainingEnglishQuery);

if ($result->num_rows > 0) {
    echo "<h3 style='color:red'>Warning: The following English categories still exist in the database:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['category'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:green'>All English categories have been successfully replaced with Bulgarian equivalents.</p>";
}

echo "<p><a href='plan_budget.php' class='btn btn-primary'>Return to Budget Planning</a></p>";

$conn->close();
?>