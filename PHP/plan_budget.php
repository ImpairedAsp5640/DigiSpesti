<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';

$debug = false;
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin-bottom: 20px;'>";
    echo "<h3>Debug информация</h3>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Месец: " . (isset($_GET['month']) ? $_GET['month'] : date('m')) . "</p>";
    echo "<p>Година: " . (isset($_GET['year']) ? $_GET['year'] : date('Y')) . "</p>";
    
    echo "<p>Database connection: ";
    if ($conn && !$conn->connect_error) {
        echo "<span style='color:green'>Connected</span>";
    } else {
        echo "<span style='color:red'>Failed - " . $conn->connect_error . "</span>";
    }
    echo "</p>";
    
    $tableCheckQuery = "SHOW TABLES LIKE 'budget_plans'";
    $result = $conn->query($tableCheckQuery);
    echo "<p>budget_plans table: ";
    if ($result && $result->num_rows > 0) {
        echo "<span style='color:green'>Exists</span>";
    } else {
        echo "<span style='color:red'>Does not exist</span>";
    }
    echo "</p>";
    
    $tableCheckQuery = "SHOW TABLES LIKE 'budget_plan_items'";
    $result = $conn->query($tableCheckQuery);
    echo "<p>budget_plan_items table: ";
    if ($result && $result->num_rows > 0) {
        echo "<span style='color:green'>Exists</span>";
    } else {
        echo "<span style='color:red'>Does not exist</span>";
    }
    echo "</p>";
    
    $checkItemsQuery = "SELECT COUNT(*) as item_count FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = $conn->prepare($checkItemsQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $itemCount = $result->fetch_assoc()['item_count'];
    
    echo "<p>budget_plan_items count for current month/year: ";
    echo $itemCount > 0 ? "<span style='color:green'>{$itemCount} items found</span>" : "<span style='color:red'>No items found</span>";
    echo "</p>";
    
    $checkZeroCategoryQuery = "SELECT COUNT(*) as zero_count FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ? AND category = '0'";
    $stmt = $conn->prepare($checkZeroCategoryQuery);
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $zeroCount = $result->fetch_assoc()['zero_count'];
    
    if ($zeroCount > 0) {
        echo "<p style='color:red'>Warning: {$zeroCount} items found with category '0'</p>";
    }
    
    echo "</div>";
}

function ensureBudgetTablesExist($conn) {
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
    
    $conn->query($createBudgetPlansTable);
    
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
    
    $conn->query($createBudgetPlanItemsTable);
}

ensureBudgetTablesExist($conn);

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$highlightCategory = isset($_GET['highlight']) ? $_GET['highlight'] : '';

$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentMonthName = date('F Y', strtotime("$year-$month-01"));

$budgetQuery = "SELECT * FROM budget_plans WHERE user_id = ? AND month = ? AND year = ?";
$stmt = $conn->prepare($budgetQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();
$budgetPlan = $result->fetch_assoc();

$plannedIncome = $budgetPlan['income'] ?? 0;
$plannedExpenses = $budgetPlan['expenses'] ?? 0;
$plannedSavings = $budgetPlan['savings'] ?? 0;

$actualQuery = "SELECT 
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as actual_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as actual_expenses,
                SUM(CASE WHEN type = 'savings' THEN amount ELSE 0 END) as actual_savings
                FROM transactions 
                WHERE user_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
$stmt = $conn->prepare($actualQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();
$actualData = $result->fetch_assoc();

$actualIncome = $actualData['actual_income'] ?? 0;
$actualExpenses = $actualData['actual_expenses'] ?? 0;
$actualSavings = $actualData['actual_savings'] ?? 0;

$plannedBalance = $plannedIncome - $plannedExpenses;
$actualBalance = $actualIncome - $actualExpenses;

$expenseCategoriesQuery = "SELECT category, amount FROM budget_plan_items 
                          WHERE user_id = ? AND month = ? AND year = ? AND type = 'expense'";
$stmt = $conn->prepare($expenseCategoriesQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$expenseCategoriesResult = $stmt->get_result();
$expenseCategories = [];

while ($row = $expenseCategoriesResult->fetch_assoc()) {
    $expenseCategories[$row['category']] = $row['amount'];
}

if (isset($expenseCategories["0"])) {
    unset($expenseCategories["0"]);
}

$defaultExpenseCategories = [
    'Битови сметки' => 0,
    'Жилище' => 0,
    'Храна и консулмативи' => 0,
    'Транспорт' => 0,
    'Автомобил' => 0,
    'Деца' => 0,
    'Дрехи и обувки' => 0,
    'Лични' => 0,
    'Цигари и алкохол' => 0,
    'Развлечения' => 0,
    'Хранене навън' => 0,
    'Образование' => 0,
    'Подаръци' => 0,
    'Спорт/Хоби' => 0,
    'Пътуване/Отдих' => 0,
    'Медицински' => 0,
    'Домашни любимци' => 0,
    'Разни' => 0
];

foreach ($defaultExpenseCategories as $category => $amount) {
    if (!isset($expenseCategories[$category])) {
        $expenseCategories[$category] = $amount;
    }
}

$incomeCategoriesQuery = "SELECT category, amount FROM budget_plan_items 
                         WHERE user_id = ? AND month = ? AND year = ? AND type = 'income'";
$stmt = $conn->prepare($incomeCategoriesQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$incomeCategoriesResult = $stmt->get_result();
$incomeCategories = [];

while ($row = $incomeCategoriesResult->fetch_assoc()) {
    $incomeCategories[$row['category']] = $row['amount'];
}

if (isset($incomeCategories["0"])) {
    unset($incomeCategories["0"]);
}

$defaultIncomeCategories = [
    'Заплата' => 0,
    'Баланс от предходен месец' => 0
];

foreach ($defaultIncomeCategories as $category => $amount) {
    if (!isset($incomeCategories[$category])) {
        $incomeCategories[$category] = $amount;
    }
}

$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

$warningsQuery = "SELECT e.category, bpi.amount as planned_amount, 
                 SUM(e.amount) as spent_amount,
                 (bpi.amount - SUM(e.amount)) as remaining
                 FROM transactions e
                 JOIN budget_plan_items bpi ON e.user_id = bpi.user_id 
                     AND MONTH(e.transaction_date) = bpi.month 
                     AND YEAR(e.transaction_date) = bpi.year 
                     AND bpi.category = e.category
                 WHERE e.user_id = ? 
                     AND MONTH(e.transaction_date) = ? 
                     AND YEAR(e.transaction_date) = ?
                     AND e.type = 'expense'
                     AND bpi.type = 'expense'
                 GROUP BY e.category, bpi.amount
                 HAVING SUM(e.amount) > (bpi.amount * 0.8)";
$stmt = $conn->prepare($warningsQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$warningsResult = $stmt->get_result();
$warnings = [];

while ($row = $warningsResult->fetch_assoc()) {
    $warnings[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiSpesti - Plan your budget</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .budget-charts {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            flex: 1;
            min-width: 300px;
            background-color: #6b6b7b;
            border-radius: 8px;
            padding: 20px;
            position: relative;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
            margin: 0 auto;
        }
        
        .chart-title {
            color: white;
            text-align: center;
            margin-bottom: 10px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .chart-total {
            color: white;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .budget-balance {
            display: flex;
            margin-bottom: 20px;
            border-top: 1px solid #e5e5e5;
        }
        
        .balance-card {
            flex: 1;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .balance-card:first-child {
            border-right: 1px solid #e5e5e5;
        }
        
        .balance-title {
            font-size: 16px;
            color: #737373;
            margin-bottom: 10px;
        }
        
        .balance-amount {
            font-size: 36px;
            font-weight: bold;
            color: #0a2463;
            transition: all 0.3s ease;
        }
        
        .balance-help {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            width: 24px;
            height: 24px;
            background-color: #e5e5e5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #737373;
            cursor: help;
        }
        
        .budget-section {
            margin-bottom: 30px;
        }
        
        .budget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        
        .budget-header h3 {
            margin: 0;
            color: #0a2463;
        }
        
        .budget-header .total {
            font-size: 18px;
            font-weight: bold;
            color: #d4af37;
            transition: all 0.3s ease;
        }
        
        .budget-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .budget-item:last-child {
            border-bottom: none;
        }
        
        .budget-item-category {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .budget-item-amount {
            display: flex;
            align-items: center;
        }
        
        .budget-item-amount input {
            width: 100px;
            padding: 5px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            text-align: right;
            transition: all 0.2s ease;
        }
        
        .budget-item-amount .edit-btn {
            background: none;
            border: none;
            color: #0e5e2e;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .month-navigation {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .month-navigation a {
            color: #0a2463;
            text-decoration: none;
            font-size: 18px;
        }
        
        .month-navigation h2 {
            margin: 0;
            color: #d4af37;
        }
        
        .save-budget-btn {
            display: block;
            margin: 20px auto;
            padding: 12px 30px;
            transition: all 0.3s ease;
        }
        
        .budget-edit-mode .budget-item-amount input {
            border-color: #ffc300;
            background-color: #fffbeb;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 0.5s ease-in-out;
        }
        
        .tooltip {
            position: absolute;
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .tooltip.show {
            opacity: 1;
        }
        
        .warning-container {
            margin-bottom: 20px;
        }
        
        .warning-item {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .warning-item .warning-action {
            background-color: #856404;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .category-percentage {
            font-size: 12px;
            color: #666;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .budget-charts {
                flex-direction: column;
            }
            
            .chart-container {
                width: 100%;
            }
            
            .budget-balance {
                flex-direction: column;
            }
            
            .balance-card:first-child {
                border-right: none;
                border-bottom: 1px solid #e5e5e5;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo-container">
                <a href="./index.php"><img src="image.png" alt="DigiSpesti Logo"></a>
            </div>
            <nav class="nav">
                <a href="index.php" class="nav-link">Начална страница</a>
                <a href="history.php" class="nav-link">Плащания</a>
                <a href="savings.php" class="nav-link">Спестявания</a>
                <a href="plan_budget.php" class="nav-link">Бюджет</a>
<<<<<<< HEAD
                <a href="product_promotions.php" class="nav-link">Промоции</a>
=======
>>>>>>> 1dabd120ff1260728edbff6ee42ddc860d54d5df
                <form action="logout.php" method="POST" style="display: inline; margin-left: 20px;">
                    <button type="submit" class="btn btn-outline">Излизане</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="container dashboard">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-header">
            <div>
                <h1>Моят бюджет</h1>
<<<<<<< HEAD
                <p>Планирайте и следете месечния си баланс</p>
=======
                <p>Планирайте и следете месечния Ви бюджет</p>
>>>>>>> 1dabd120ff1260728edbff6ee42ddc860d54d5df
            </div>
            <button class="btn btn-primary" id="editBudgetBtn">Промени бюджета си</button>
        </div>
        
        <div class="month-navigation">
            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>">&lt;</a>
            <h2><?php echo $currentMonthName; ?></h2>
            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>">&gt;</a>
        </div>
        
        <?php if (!empty($warnings)): ?>
        <div class="warning-container">
            <?php foreach ($warnings as $warning): ?>
            <div class="warning-item">
                <div>
<<<<<<< HEAD
                    <strong>Внимание:</strong> Вие сте похарчили <?php echo number_format($warning['spent_amount'], 2); ?> от <?php echo $warning['category']; ?> 
=======
                    <strong>Внимание:</strong> Вие сте похарчили <?php echo number_format($warning['spent_amount'], 2); ?> on <?php echo $warning['category']; ?> 
>>>>>>> 1dabd120ff1260728edbff6ee42ddc860d54d5df
                    (<?php echo round(($warning['spent_amount'] / $warning['planned_amount']) * 100); ?>% от бюджета Ви).
                    Остават: <?php echo number_format($warning['remaining'], 2); ?>
                </div>
                <button class="warning-action" data-category="<?php echo $warning['category']; ?>">Променете бюджета си</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="budget-charts">
            <div class="chart-container">
                <div class="chart-title">Доходи</div>
                <div class="chart-total" id="incomeTotalDisplay"><?php echo number_format($plannedIncome, 2); ?></div>
                <div class="chart-wrapper">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <div class="chart-title">Разходи</div>
                <div class="chart-total" id="expenseTotalDisplay"><?php echo number_format($plannedExpenses, 2); ?></div>
                <div class="chart-wrapper">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="budget-balance">
            <div class="balance-card">
                <div class="balance-title">Планиран баланс:</div>
                <div class="balance-amount" id="plannedBalanceAmount"><?php echo number_format($plannedBalance, 2); ?></div>
                <div class="balance-help" title="Planned balance = Income - Expenses - Savings">?</div>
            </div>
            
            <div class="balance-card">
                <div class="balance-title">Реален баланс:</div>
                <div class="balance-amount" id="actualBalanceAmount"><?php echo number_format($actualBalance, 2); ?></div>
                <div class="balance-help" title="Actual balance = Actual Income - Actual Expenses - Actual Savings">?</div>
            </div>
        </div>
        
        <form id="budgetForm" action="save_budget_plan.php" method="POST">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            
            <div class="budget-section">
                <div class="budget-header">
                    <h3>ДОХОДИ</h3>
                    <div class="total" id="incomeTotalHeader"><?php echo number_format($plannedIncome, 2); ?></div>
                </div>
                
                <div class="budget-items">
                    <?php foreach ($incomeCategories as $category => $amount): ?>
                    <div class="budget-item">
                        <div class="budget-item-category">
                            <span><?php echo $category; ?></span>
                            <span class="category-percentage" data-category="<?php echo $category; ?>" data-type="income">
                                <?php 
                                    $percentage = $plannedIncome > 0 ? round(($amount / $plannedIncome) * 100) : 0;
                                    echo "({$percentage}%)"; 
                                ?>
                            </span>
                        </div>
                        <div class="budget-item-amount">
                            <input type="text" name="income[<?php echo $category; ?>]" value="<?php echo $amount; ?>" readonly data-category="<?php echo $category; ?>" class="income-input">
<<<<<<< HEAD
                            <button type="button" class="edit-btn">редактирай</button>
=======
                            <button type="button" class="edit-btn">поправете</button>
>>>>>>> 1dabd120ff1260728edbff6ee42ddc860d54d5df
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="budget-section">
                <div class="budget-header">
                    <h3>Разходи</h3>
                    <div class="total" id="expenseTotalHeader"><?php echo number_format($plannedExpenses, 2); ?></div>
                </div>
                
                <div class="budget-items">
                    <?php foreach ($expenseCategories as $category => $amount): ?>
                    <div class="budget-item">
                        <div class="budget-item-category">
                            <span><?php echo $category; ?></span>
                            <span class="category-percentage" data-category="<?php echo $category; ?>" data-type="expense">
                                <?php 
                                    $percentage = $plannedExpenses > 0 ? round(($amount / $plannedExpenses) * 100) : 0;
                                    echo "({$percentage}%)"; 
                                ?>
                            </span>
                        </div>
                        <div class="budget-item-amount">
                            <input type="text" name="expense[<?php echo $category; ?>]" value="<?php echo $amount; ?>" readonly data-category="<?php echo $category; ?>" class="expense-input">
<<<<<<< HEAD
                            <button type="button" class="edit-btn">редактирай</button>
=======
                            <button type="button" class="edit-btn">поправете</button>
>>>>>>> 1dabd120ff1260728edbff6ee42ddc860d54d5df
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            
            <button type="submit" class="btn btn-primary save-budget-btn" style="display: none;">Запазете бюджета</button>
        </form>
    </main>

    <footer class="container">
        <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
    </footer>

    <script>
        const highlightCategory = "<?php echo addslashes($highlightCategory); ?>";
        if (highlightCategory) {
            const input = document.querySelector(`input[data-category="${highlightCategory}"]`);
            
            if (input) {
                if (!isEditMode) {
                    isEditMode = true;
                    budgetForm.classList.add('budget-edit-mode');
                    inputs.forEach(input => {
                        input.readOnly = false;
                    });
                    saveButton.style.display = 'block';
                    editBudgetBtn.textContent = 'CANCEL';
                }
                
                setTimeout(() => {
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    input.style.backgroundColor = '#fffacd';
                    setTimeout(() => {
                        input.style.backgroundColor = '';
                    }, 2000);
                    
                    input.focus();
                    input.select();
                }, 500);
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editBudgetBtn = document.getElementById('editBudgetBtn');
            const budgetForm = document.getElementById('budgetForm');
            const saveButton = document.querySelector('.save-budget-btn');
            const inputs = document.querySelectorAll('.budget-item-amount input');
            const editButtons = document.querySelectorAll('.edit-btn');
            const incomeInputs = document.querySelectorAll('.income-input');
            const expenseInputs = document.querySelectorAll('.expense-input');
            const incomeTotalDisplay = document.getElementById('incomeTotalDisplay');
            const expenseTotalDisplay = document.getElementById('expenseTotalDisplay');
            const incomeTotalHeader = document.getElementById('incomeTotalHeader');
            const expenseTotalHeader = document.getElementById('expenseTotalHeader');
            const plannedBalanceAmount = document.getElementById('plannedBalanceAmount');
            const actualBalanceAmount = document.getElementById('actualBalanceAmount');
            const warningActions = document.querySelectorAll('.warning-action');
            
            const incomeChartCtx = document.getElementById('incomeChart').getContext('2d');
            const expenseChartCtx = document.getElementById('expenseChart').getContext('2d');
            
            const incomeColors = [
                '#a8e6cf', '#dcedc1', '#ffd3b6', '#ffaaa5', '#ff8b94',
                '#b8f2e6', '#aed9e0', '#ffa69e', '#faf3dd', '#e8d2ae'
            ];
            
            const expenseColors = [
                '#ffc8b4', '#f3b0c3', '#c6d8ff', '#ffd6a5', '#caffbf',
                '#9bf6ff', '#bdb2ff', '#ffc6ff', '#fdffb6', '#a0c4ff'
            ];
            
            let incomeChart = new Chart(incomeChartCtx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: incomeColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 500
                    },
                    layout: {
                        padding: 20
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: 'white',
                                font: {
                                    size: 12
                                },
                                boxWidth: 15,
                                padding: 10,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map(function(label, i) {
                                            const value = data.datasets[0].data[i];
                                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                            
                                            return {
                                                text: `${label} (${percentage}%)`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                hidden: isNaN(data.datasets[0].data[i]) || data.datasets[0].data[i] === 0,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            let expenseChart = new Chart(expenseChartCtx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: expenseColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 500
                    },
                    layout: {
                        padding: 20
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: 'white',
                                font: {
                                    size: 12
                                },
                                boxWidth: 15,
                                padding: 10,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map(function(label, i) {
                                            const value = data.datasets[0].data[i];
                                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                            
                                            return {
                                                text: `${label} (${percentage}%)`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                hidden: isNaN(data.datasets[0].data[i]) || data.datasets[0].data[i] === 0,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            let isEditMode = false;
            let actualBalance = parseFloat('<?php echo $actualBalance; ?>');
            let updateTimeout = null;
            
            editBudgetBtn.addEventListener('click', function() {
                isEditMode = !isEditMode;
                budgetForm.classList.toggle('budget-edit-mode');
                
                if (isEditMode) {
                    inputs.forEach(input => {
                        input.readOnly = false;
                    });
                    saveButton.style.display = 'block';
                    editBudgetBtn.textContent = 'Откажете';
                } else {
                    inputs.forEach(input => {
                        input.readOnly = true;
                    });
                    saveButton.style.display = 'none';
                    editBudgetBtn.textContent = 'Променете бюджета';
                    
                    budgetForm.reset();
                    updateTotals();
                }
            });
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (!isEditMode) {
                        isEditMode = true;
                        budgetForm.classList.add('budget-edit-mode');
                        inputs.forEach(input => {
                            input.readOnly = false;
                        });
                        saveButton.style.display = 'block';
                        editBudgetBtn.textContent = 'Откажете';
                    }
                    
                    const input = this.previousElementSibling;
                    input.focus();
                    input.select();
                });
            });
            
            warningActions.forEach(button => {
                button.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');
                    
                    const input = document.querySelector(`input[data-category="${category}"]`);
                    
                    if (input) {
                        if (!isEditMode) {
                            isEditMode = true;
                            budgetForm.classList.add('budget-edit-mode');
                            inputs.forEach(input => {
                                input.readOnly = false;
                            });
                            saveButton.style.display = 'block';
                            editBudgetBtn.textContent = 'Откажете';
                        }
                        
                        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        input.style.backgroundColor = '#fffacd';
                        setTimeout(() => {
                            input.style.backgroundColor = '';
                        }, 2000);
                        
                        setTimeout(() => {
                            input.focus();
                            input.select();
                        }, 500);
                    }
                });
            });
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (updateTimeout) {
                        clearTimeout(updateTimeout);
                    }
                    updateTimeout = setTimeout(() => {
                        updateTotals();
                    }, 300);
                });
                
                input.addEventListener('focus', function() {
                    this.select();
                });
                
                input.addEventListener('blur', function() {
                    if (this.value) {
                        const value = parseFloat(this.value.replace(',', '.'));
                        if (!isNaN(value)) {
                            this.value = value.toFixed(2);
                        }
                    }
                    updateTotals();
                });
            });
            
            function updateTotals() {
                let totalIncome = 0;
                let totalExpenses = 0;
                
                incomeInputs.forEach(input => {
                    const value = parseFloat(input.value.replace(',', '.'));
                    if (!isNaN(value)) {
                        totalIncome += value;
                    }
                });
                
                expenseInputs.forEach(input => {
                    const value = parseFloat(input.value.replace(',', '.'));
                    
                    if (!isNaN(value)) {
                        totalExpenses += value;
                    }
                });
                
                incomeTotalDisplay.textContent = totalIncome.toFixed(2);
                expenseTotalDisplay.textContent = totalExpenses.toFixed(2);
                incomeTotalHeader.textContent = totalIncome.toFixed(2);
                expenseTotalHeader.textContent = totalExpenses.toFixed(2);
                
                incomeTotalDisplay.classList.add('pulse');
                expenseTotalDisplay.classList.add('pulse');
                incomeTotalHeader.classList.add('pulse');
                expenseTotalHeader.classList.add('pulse');
                
                setTimeout(() => {
                    incomeTotalDisplay.classList.remove('pulse');
                    expenseTotalDisplay.classList.remove('pulse');
                    incomeTotalHeader.classList.remove('pulse');
                    expenseTotalHeader.classList.remove('pulse');
                }, 500);
                
                const plannedBalance = totalIncome - totalExpenses;
                plannedBalanceAmount.textContent = plannedBalance.toFixed(2);
                
                plannedBalanceAmount.classList.add('pulse');
                setTimeout(() => {
                    plannedBalanceAmount.classList.remove('pulse');
                }, 500);
                
                updateCategoryPercentages('income', totalIncome);
                updateCategoryPercentages('expense', totalExpenses);
                
                updateCharts(totalIncome, totalExpenses);
            }
            
            function updateCategoryPercentages(type, total) {
                const percentageElements = document.querySelectorAll(`.category-percentage[data-type="${type}"]`);
                
                percentageElements.forEach(element => {
                    const category = element.getAttribute('data-category');
                    const input = document.querySelector(`input[data-category="${category}"].${type}-input`);
                    
                    if (input) {
                        const value = parseFloat(input.value.replace(',', '.'));
                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                        element.textContent = `(${percentage}%)`;
                    }
                });
            }
            
            function updateCharts(totalIncome, totalExpenses) {
                const incomeLabels = [];
                const incomeData = [];
                
                incomeInputs.forEach(input => {
                    const category = input.getAttribute('data-category');
                    const value = parseFloat(input.value.replace(',', '.'));
                    
                    if (!isNaN(value) && value > 0) {
                        incomeLabels.push(category);
                        incomeData.push(value);
                    }
                });
                
                if (incomeData.length > 0) {
                    incomeChart.data.labels = incomeLabels;
                    incomeChart.data.datasets[0].data = incomeData;
                    incomeChart.update();
                }
                
                const expenseLabels = [];
                const expenseData = [];
                
                expenseInputs.forEach(input => {
                    const category = input.getAttribute('data-category');
                    const value = parseFloat(input.value.replace(',', '.'));
                    
                    if (!isNaN(value) && value > 0) {
                        expenseLabels.push(category);
                        expenseData.push(value);
                    }
                });
                
                if (expenseData.length > 0) {
                    expenseChart.data.labels = expenseLabels;
                    expenseChart.data.datasets[0].data = expenseData;
                    expenseChart.update();
                }
            }
            
            budgetForm.addEventListener('submit', function(e) {
                if (!isEditMode) {
                    return;
                }
                
                e.preventDefault();
                
                const formData = new FormData(this);
                
                console.log("Submitting form data:");
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'save_budget_plan.php', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        console.log("Response:", xhr.responseText);
                        
                        if (xhr.responseText.includes("success")) {
                            const successAlert = document.createElement('div');
                            successAlert.className = 'alert alert-success';
                            successAlert.textContent = 'Budget plan saved successfully!';
                            
                            const mainContent = document.querySelector('main.container');
                            mainContent.insertBefore(successAlert, mainContent.firstChild);
                            
                            setTimeout(() => {
                                successAlert.remove();
                            }, 3000);
                            
                            isEditMode = false;
                            budgetForm.classList.remove('budget-edit-mode');
                            inputs.forEach(input => {
                                input.readOnly = true;
                            });
                            saveButton.style.display = 'none';
                            editBudgetBtn.textContent = 'Поправете бюджета';
                            
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            const errorAlert = document.createElement('div');
                            errorAlert.className = 'alert alert-error';
                            errorAlert.textContent = 'Възникна грешка при запазването на бюджетния план:: ' + xhr.responseText;
                            
                            const mainContent = document.querySelector('main.container');
                            mainContent.insertBefore(errorAlert, mainContent.firstChild);
                            
                            setTimeout(() => {
                                errorAlert.remove();
                            }, 5000);
                        }
                    } else {
                        console.error('Error:', xhr.statusText);
                        
                        const errorAlert = document.createElement('div');
                        errorAlert.className = 'alert alert-error';
                        errorAlert.textContent = 'Възникна грешка при запазването на бюджетния план. Server returned status: ' + xhr.status;
                        
                        const mainContent = document.querySelector('main.container');
                        mainContent.insertBefore(errorAlert, mainContent.firstChild);
                        
                        setTimeout(() => {
                            errorAlert.remove();
                        }, 5000);
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Request error');
                    
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-error';
                    errorAlert.textContent = 'Network error. Моля проверете вашата връзка.';
                    
                    const mainContent = document.querySelector('main.container');
                    mainContent.insertBefore(errorAlert, mainContent.firstChild);
                    
                    setTimeout(() => {
                        errorAlert.remove();
                    }, 3000);
                };
                
                xhr.send(formData);
            });
            
            inputs.forEach(input => {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        
                        const currentIndex = Array.from(inputs).indexOf(this);
                        const nextInput = inputs[currentIndex + 1] || inputs[0];
                        nextInput.focus();
                    }
                    
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        
                        const currentIndex = Array.from(inputs).indexOf(this);
                        let nextIndex;
                        
                        if (e.key === 'ArrowDown') {
                            nextIndex = (currentIndex + 1) % inputs.length;
                        } else {
                            nextIndex = (currentIndex - 1 + inputs.length) % inputs.length;
                        }
                        
                        inputs[nextIndex].focus();
                    }
                });
            });
            
            inputs.forEach(input => {
                if (input.value) {
                    const value = parseFloat(input.value);
                    if (!isNaN(value)) {
                        input.value = value.toFixed(2);
                    }
                }
            });
            
            updateTotals();
            
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.dispatchEvent(new Event('resize'));
                }, 500);
            });
        });
    </script>
</body>
</html>

