<?php
session_start();
// mb_internal_encoding('UTF-8');
// mb_http_output('UTF-8');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

require_once 'config.php';

if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8mb4");
} else {
    mysqli_set_charset($conn, "utf8mb4");
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$currentMonthName = date('F Y', strtotime("$currentYear-$currentMonth-01"));

$plannedBudgetQuery = "SELECT amount FROM budget_plans 
                      WHERE user_id = ? AND month = ? AND year = ?";
$stmt = $conn->prepare($plannedBudgetQuery);
$stmt->bind_param("iii", $userId, $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
$plannedBudget = 0;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $plannedBudget = $row['amount'];
}

$transactionsQuery = "SELECT id, type, category, amount, comment, 
                     DATE_FORMAT(transaction_date, '%d') as day,
                     transaction_date
                     FROM transactions 
                     WHERE user_id = ? AND 
                     MONTH(transaction_date) = ? AND 
                     YEAR(transaction_date) = ?
                     ORDER BY transaction_date DESC";
$stmt = $conn->prepare($transactionsQuery);
$stmt->bind_param("iii", $userId, $currentMonth, $currentYear);
$stmt->execute();
$transactions = $stmt->get_result();

$totalIncome = 0;
$totalExpense = 0;
$totalSavings = 0;

$dailyTotalsQuery = "SELECT 
                    DATE_FORMAT(transaction_date, '%d') as day,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
                    SUM(CASE WHEN type = 'savings' THEN amount ELSE 0 END) as savings
                    FROM transactions 
                    WHERE user_id = ? AND 
                    MONTH(transaction_date) = ? AND 
                    YEAR(transaction_date) = ?
                    GROUP BY DATE_FORMAT(transaction_date, '%d')
                    ORDER BY day ASC";
$stmt = $conn->prepare($dailyTotalsQuery);
$stmt->bind_param("iii", $userId, $currentMonth, $currentYear);
$stmt->execute();
$dailyTotals = $stmt->get_result();

$graphData = [];
$runningIncome = 0;
$runningExpense = 0;
$runningSavings = 0;

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
for ($i = 1; $i <= $daysInMonth; $i++) {
    $graphData[$i] = [
        'income' => 0,
        'expense' => 0,
        'savings' => 0,
        'runningIncome' => 0,
        'runningExpense' => 0,
        'runningSavings' => 0,
        'balance' => 0
    ];
}

while ($row = $dailyTotals->fetch_assoc()) {
    $day = (int)$row['day'];
    $dayIncome = $row['income'];
    $dayExpense = $row['expense'];
    $daySavings = $row['savings'];
    
    $runningIncome += $dayIncome;
    $runningExpense += $dayExpense;
    $runningSavings += $daySavings;
    
    $graphData[$day]['income'] = $dayIncome;
    $graphData[$day]['expense'] = $dayExpense;
    $graphData[$day]['savings'] = $daySavings;
    
    for ($i = $day; $i <= $daysInMonth; $i++) {
        $graphData[$i]['runningIncome'] = $runningIncome;
        $graphData[$i]['runningExpense'] = $runningExpense;
        $graphData[$i]['runningSavings'] = $runningSavings;
        $graphData[$i]['balance'] = $runningIncome - $runningExpense - $runningSavings;
    }
    
    $totalIncome += $dayIncome;
    $totalExpense += $dayExpense;
    $totalSavings += $daySavings;
}

$actualBalance = $totalIncome - $totalExpense - $totalSavings;

$graphDataJson = json_encode($graphData);

$currentDay = date('d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - DigiSpesti</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="modal.css">
    <style>
        .history-container {
            margin-top: 24px;
        }
        
        .graph-container {
            height: 400px;
            background-color: #f5f5f5;
            border-radius: 8px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        
        .graph-area {
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        .graph-income {
            background-color: #90c695;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1;
        }
        
        .graph-expense {
            background-color: #c67b7b;
            position: absolute;
            bottom: 0;
            right: 0;
            z-index: 2;
        }
        
        .graph-line {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: rgba(255, 255, 255, 0.7);
            border-left: 2px dashed #999;
            z-index: 3;
        }
        
        .graph-marker {
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #fff;
            border: 3px solid #333;
            transform: translate(-50%, -50%);
            z-index: 4;
        }
        
        .graph-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
        }
        
        .graph-value {
            position: absolute;
            color: white;
            font-weight: bold;
            padding: 8px;
            z-index: 5;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .transaction-list {
            margin-top: 24px;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .transaction-date {
            width: 60px;
            height: 60px;
            background-color: #6b6b7b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            margin-right: 16px;
            flex-shrink: 0;
        }
        
        .transaction-details {
            flex-grow: 1;
        }
        
        .transaction-amount {
            font-weight: bold;
            font-size: 18px;
            margin-left: 16px;
        }
        
        .transaction-amount.positive {
            color: #22c55e;
        }
        
        .transaction-amount.negative {
            color: #ef4444;
        }
        
        .transaction-actions {
            display: flex;
            gap: 8px;
            margin-left: 16px;
        }
        
        .transaction-action {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            background-color: #f5f5f5;
            cursor: pointer;
        }
        
        .transaction-action:hover {
            background-color: #e5e5e5;
        }
        
        .month-selector {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        
        .month-selector h2 {
            margin: 0;
        }
        
        .month-nav {
            display: flex;
            gap: 8px;
        }
        
        .month-nav a {
            padding: 8px 16px;
            background-color: #f5f5f5;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .month-nav a:hover {
            background-color: #e5e5e5;
        }
        
        .filter-tabs {
            display: flex;
            border-bottom: 1px solid #e5e5e5;
            margin-bottom: 16px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .filter-tab.active {
            border-bottom: 2px solid #ffc300;
            color: #0a2463;
        }
        
        .budget-summary {
            display: flex;
            margin-top: 24px;
            border-top: 1px solid #e5e5e5;
            padding-top: 24px;
        }
        
        .budget-column {
            flex: 1;
            text-align: center;
            padding: 16px;
        }
        
        .budget-column:first-child {
            border-right: 1px solid #e5e5e5;
        }
        
        .budget-label {
            font-size: 16px;
            color: #737373;
            margin-bottom: 8px;
        }
        
        .budget-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .budget-value.positive {
            color: #90c695;
        }
        
        .budget-help {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #e5e5e5;
            color: #737373;
            text-align: center;
            line-height: 20px;
            font-size: 14px;
            margin-left: 8px;
            cursor: help;
        }
        
        .budget-form {
            margin-top: 24px;
            padding: 16px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }
        
        .budget-form h3 {
            margin-top: 0;
            margin-bottom: 16px;
        }
        
        .search-box {
            display: flex;
            margin-bottom: 16px;
        }
        
        .search-box input {
            flex-grow: 1;
            padding: 8px 16px;
            border: 1px solid #e5e5e5;
            border-radius: 4px 0 0 4px;
            font-size: 16px;
        }
        
        .search-box button {
            padding: 8px 16px;
            background-color: #ffc300;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        .date-picker {
            position: relative;
        }
        
        .date-picker input {
            cursor: pointer;
        }
        
        .date-picker-calendar {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 10;
            background-color: white;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 8px;
            display: none;
        }
        
        .date-picker-calendar.show {
            display: block;
        }
        
        .date-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .date-picker-nav {
            cursor: pointer;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        
        .date-picker-nav:hover {
            background-color: #f5f5f5;
        }
        
        .date-picker-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        
        .date-picker-day {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .date-picker-day:hover {
            background-color: #f5f5f5;
        }
        
        .date-picker-day.selected {
            background-color: #ffc300;
            color: white;
        }
        
        .date-picker-day.disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .date-picker-weekday {
            width: 32px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            color: #737373;
        }
        
        .graph-text {
            position: absolute;
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
            z-index: 6;
        }
        
        .graph-text-small {
            font-size: 14px;
            display: block;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo-container">
                <img src="image.png" alt="DigiSpesti Logo">
            </div>
            <nav class="nav">
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="history.php" class="nav-link">History</a>
                <a href="#" class="nav-link">Savings</a>
                <a href="plan_budget.php" class="nav-link">Plan your budget</a>
                <form action="logout.php" method="POST" style="display: inline; margin-left: 20px;">
                    <button type="submit" class="btn btn-outline">Log out</button>
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
                <h1>My Payments</h1>
                <p>Track and manage your financial transactions</p>
            </div>
            <button class="btn btn-primary" id="addPaymentBtn">Add Payment</button>
        </div>

        <div class="month-selector">
            <h2><?php echo $currentMonthName; ?></h2>
            <div class="month-nav">
                <?php
                    $prevMonth = $currentMonth - 1;
                    $prevYear = $currentYear;
                    if ($prevMonth < 1) {
                        $prevMonth = 12;
                        $prevYear--;
                    }
                    
                    $nextMonth = $currentMonth + 1;
                    $nextYear = $currentYear;
                    if ($nextMonth > 12) {
                        $nextMonth = 1;
                        $nextYear++;
                    }
                ?>
                <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>">&lt; Previous</a>
                <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>">Current</a>
                <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>">Next &gt;</a>
            </div>
        </div>

        <div class="graph-container">
            <div class="graph-area" id="graphArea">
                <!-- Graph will be rendered by JavaScript -->
            </div>
            <div class="graph-labels">
                <div>day 1</div>
                <div id="currentDayLabel"><?php echo $currentDay; ?></div>
                <div><?php echo $daysInMonth; ?></div>
            </div>
        </div>

        <div class="filter-tabs">
            <div class="filter-tab active" data-filter="all">All</div>
            <div class="filter-tab" data-filter="income">Income</div>
            <div class="filter-tab" data-filter="expense">Expenses</div>
            <div class="filter-tab" data-filter="savings">Savings</div>
            <div style="flex-grow: 1;"></div>
            <div class="search-box">
                <input type="text" id="searchTransactions" placeholder="Search...">
                <button type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </div>
        </div>

        <div class="transaction-list" id="transactionList">
            <?php if ($transactions->num_rows > 0): ?>
                <?php while ($transaction = $transactions->fetch_assoc()): ?>
                    <div class="transaction-item" data-type="<?php echo $transaction['type']; ?>">
                        <div class="transaction-date">
                            <?php echo $transaction['day']; ?>
                        </div>
                        <div class="transaction-details">
                            <div><?php echo htmlspecialchars($transaction['category']); ?></div>
                            <?php if (!empty($transaction['comment'])): ?>
                                <div style="color: #737373; font-size: 14px;"><?php echo htmlspecialchars($transaction['comment']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="transaction-amount <?php echo ($transaction['type'] == 'income') ? 'positive' : 'negative'; ?>">
                            <?php 
                                $prefix = ($transaction['type'] == 'income') ? '+ ' : '- ';
                                if ($transaction['type'] == 'savings') {
                                    echo number_format($transaction['amount'], 2);
                                } else {
                                    echo $prefix . number_format($transaction['amount'], 2);
                                }
                            ?>
                        </div>
                        <div class="transaction-actions">
                            <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="transaction-action">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                </svg>
                            </a>
                            <a href="delete_transaction.php?id=<?php echo $transaction['id']; ?>" class="transaction-action" onclick="return confirm('Are you sure you want to delete this transaction?');">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 32px;">
                    <p>No transactions found for this month.</p>
                    <button class="btn btn-primary" id="addFirstTransactionBtn">Add Your First Transaction</button>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>ADD</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-tabs">
                <button class="tab-btn active" data-tab="income">INCOME</button>
                <button class="tab-btn" data-tab="expense">EXPENSE</button>
                <button class="tab-btn" data-tab="savings">SAVING</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" action="process_transaction.php" method="POST">
                    <input type="hidden" id="transactionType" name="transactionType" value="income">
                    <input type="hidden" id="selectedCategory" name="selectedCategory" value="">
                    <input type="hidden" name="redirect" value="history.php?month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>">
                    
                    <div class="form-group">
                        <label for="date">Date: <span class="required">*</span></label>
                        <div class="date-picker">
                            <input type="text" id="date" name="date" class="form-input" value="<?php echo date('d F Y'); ?>" readonly>
                            <div id="datePickerCalendar" class="date-picker-calendar">
                                <div class="date-picker-header">
                                    <div class="date-picker-nav" id="prevMonth">&lt;</div>
                                    <div id="currentMonthYear"></div>
                                    <div class="date-picker-nav" id="nextMonth">&gt;</div>
                                </div>
                                <div class="date-picker-grid" id="calendarGrid">
                                    <!-- Calendar will be rendered by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount: <span class="required">*</span></label>
                        <input type="text" id="amount" name="amount" class="form-input" placeholder="Example: 100.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Comment:</label>
                        <textarea id="comment" name="comment" class="form-input" placeholder="Example: Electricity bill"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category: <span class="required">*</span></label>
                        <div class="dropup">
                            <button type="button" onclick="dropup()" class="dropbtn">Select category</button>
                            <div id="Dropup" class="dropup-content">
                                <!-- Categories will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="container">
        <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
    </footer>

    <script>
        const graphData = <?php echo $graphDataJson; ?>;
        const daysInMonth = <?php echo $daysInMonth; ?>;
        const currentDay = <?php echo min((int)$currentDay, $daysInMonth); ?>;
        
        function renderGraph() {
    const graphArea = document.getElementById('graphArea');
    if (!graphArea) return;
    
    graphArea.innerHTML = '';
    
    let maxIncome = 0;
    let maxExpense = 0;
    
    for (let day = 1; day <= daysInMonth; day++) {
        if (graphData[day]) {
            maxIncome = Math.max(maxIncome, graphData[day].runningIncome);
            maxExpense = Math.max(maxExpense, graphData[day].runningExpense + graphData[day].runningSavings);
        }
    }
    
    const maxValue = Math.max(maxIncome, maxExpense, 100);
    
    const headerArea = document.createElement('div');
    headerArea.style.position = 'absolute';
    headerArea.style.top = '0';
    headerArea.style.left = '0';
    headerArea.style.width = '100%';
    headerArea.style.height = '30%';
    headerArea.style.backgroundColor = '#6b6b7b';
    headerArea.style.zIndex = '1';
    graphArea.appendChild(headerArea);
    
    const incomeArea = document.createElement('div');
    incomeArea.className = 'graph-income';
    incomeArea.style.height = `${(maxIncome / maxValue) * 70}%`;
    incomeArea.style.top = '30%';
    incomeArea.style.width = '100%';
    graphArea.appendChild(incomeArea);
    
    if (graphData[currentDay]) {
        const expenseTotal = graphData[currentDay].runningExpense + graphData[currentDay].runningSavings;
        const expenseArea = document.createElement('div');
        expenseArea.className = 'graph-expense';
        expenseArea.style.height = `${(expenseTotal / maxValue) * 70}%`;
        expenseArea.style.top = '30%';
        expenseArea.style.width = `${((daysInMonth - currentDay) / daysInMonth) * 100}%`;
        expenseArea.style.left = `${(currentDay / daysInMonth) * 100}%`;
        graphArea.appendChild(expenseArea);
    }
    
    const dayLine = document.createElement('div');
    dayLine.className = 'graph-line';
    dayLine.style.left = `${(currentDay / daysInMonth) * 100}%`;
    graphArea.appendChild(dayLine);
    
    if (maxIncome > 0) {
        const incomeText = document.createElement('div');
        incomeText.className = 'graph-text';
        incomeText.style.top = '10%';
        incomeText.style.left = '20px';
        incomeText.innerHTML = `${Math.round(maxIncome)}<span class="graph-text-small">real income</span>`;
        graphArea.appendChild(incomeText);
        
        const incomeMarker = document.createElement('div');
        incomeMarker.className = 'graph-marker';
        incomeMarker.style.left = '20px';
        incomeMarker.style.top = '15%';
        graphArea.appendChild(incomeMarker);
    }
    
    if (graphData[currentDay] && (graphData[currentDay].runningExpense > 0 || graphData[currentDay].runningSavings > 0)) {
        const expenseTotal = graphData[currentDay].runningExpense + graphData[currentDay].runningSavings;
        
        const expenseText = document.createElement('div');
        expenseText.className = 'graph-text';
        expenseText.style.top = '50%';
        expenseText.style.left = `${(currentDay / daysInMonth) * 100 + 5}%`;
        expenseText.innerHTML = `${Math.round(expenseTotal)}<span class="graph-text-small">real expenses</span>`;
        graphArea.appendChild(expenseText);
        
        const expenseMarker = document.createElement('div');
        expenseMarker.className = 'graph-marker';
        expenseMarker.style.left = `${(currentDay / daysInMonth) * 100}%`;
        expenseMarker.style.top = '55%';
        graphArea.appendChild(expenseMarker);
    }
}
        
        class DatePicker {
            constructor(inputElement) {
                this.input = inputElement;
                this.calendar = document.getElementById('datePickerCalendar');
                this.currentDate = new Date();
                this.selectedDate = new Date();
                
                this.renderCalendar();
                
                this.input.addEventListener('click', () => this.toggleCalendar());
                document.getElementById('prevMonth').addEventListener('click', () => this.prevMonth());
                document.getElementById('nextMonth').addEventListener('click', () => this.nextMonth());
                
                document.addEventListener('click', (e) => {
                    if (!this.input.contains(e.target) && !this.calendar.contains(e.target)) {
                        this.calendar.classList.remove('show');
                    }
                });
            }
            
            toggleCalendar() {
                this.calendar.classList.toggle('show');
            }
            
            renderCalendar() {
                const year = this.currentDate.getFullYear();
                const month = this.currentDate.getMonth();
                
                document.getElementById('currentMonthYear').textContent = new Date(year, month, 1).toLocaleDateString('default', { month: 'long', year: 'numeric' });
                
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                
                const firstDay = new Date(year, month, 1).getDay();
                
                const grid = document.getElementById('calendarGrid');
                grid.innerHTML = '';
                
                const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
                weekdays.forEach(day => {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'date-picker-weekday';
                    dayElement.textContent = day;
                    grid.appendChild(dayElement);
                });
                
                for (let i = 0; i < firstDay; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.className = 'date-picker-day disabled';
                    grid.appendChild(emptyDay);
                }
                
                for (let i = 1; i <= daysInMonth; i++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'date-picker-day';
                    dayElement.textContent = i;
                    
                    if (this.selectedDate.getDate() === i && 
                        this.selectedDate.getMonth() === month && 
                        this.selectedDate.getFullYear() === year) {
                        dayElement.classList.add('selected');
                    }
                    
                    dayElement.addEventListener('click', () => this.selectDate(i));
                    
                    grid.appendChild(dayElement);
                }
            }
            
            selectDate(day) {
                this.selectedDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), day);
                this.input.value = this.formatDate(this.selectedDate);
                this.renderCalendar();
                this.calendar.classList.remove('show');
            }
            
            formatDate(date) {
                const day = date.getDate();
                const month = date.toLocaleDateString('default', { month: 'long' });
                const year = date.getFullYear();
                return `${day} ${month} ${year}`;
            }
            
            prevMonth() {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.renderCalendar();
            }
            
            nextMonth() {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.renderCalendar();
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            renderGraph();
            
            const dateInput = document.getElementById('date');
            if (dateInput) {
                const datePicker = new DatePicker(dateInput);
            }
            
            const filterTabs = document.querySelectorAll('.filter-tab');
            const transactionItems = document.querySelectorAll('.transaction-item');
            
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    filterTabs.forEach(t => t.classList.remove('active'));
                    
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    transactionItems.forEach(item => {
                        if (filter === 'all' || item.getAttribute('data-type') === filter) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            const searchInput = document.getElementById('searchTransactions');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    transactionItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            const modal = document.getElementById('paymentModal');
            const addPaymentBtn = document.getElementById('addPaymentBtn');
            const addFirstTransactionBtn = document.getElementById('addFirstTransactionBtn');
            const closeModal = document.querySelector('.close-modal');
            const tabBtns = document.querySelectorAll('.tab-btn');
            const dropupContent = document.getElementById('Dropup');
            const transactionTypeInput = document.getElementById('transactionType');
            const selectedCategoryInput = document.getElementById('selectedCategory');
            const paymentForm = document.getElementById('paymentForm');
            
            const categories = {
                income: ['Salary', 'Previous month balance'],
                expense: ['Utilities', 'Housing', 'Groceries', 'Transport', 'Car', 'Kids', 'Clothing', 
                         'Personal', 'Cigarettes & alcohol', 'Fun', 'Eating out', 'Education', 
                         'Gifts', 'Sport/Hobby', 'Travel/Leisure', 'Medical', 'Pets', 'Miscellaneous'],
                savings: ['Emergency Fund', 'Retirement', 'Vacation', 'Education', 'Home Purchase']
            };
            
            if (addPaymentBtn) {
                addPaymentBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                    updateCategoryDropdown('income'); // Default to income tab
                });
            }
            
            if (addFirstTransactionBtn) {
                addFirstTransactionBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                    updateCategoryDropdown('income');
                });
            }

            if (closeModal) {
                closeModal.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            }
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            window.dropup = function() {
                document.getElementById("Dropup").classList.toggle("show");
            };

            window.onclick = function(event) {
                if (!event.target.matches('.dropbtn')) {
                    var dropdowns = document.getElementsByClassName("dropup-content");
                    for (var i = 0; i < dropdowns.length; i++) {
                        var openDropdown = dropdowns[i];
                        if (openDropdown.classList.contains('show')) {
                            openDropdown.classList.remove('show');
                        }
                    }
                }
            };
            
            function updateCategoryDropdown(tabType) {
                if (!dropupContent) return;
                
                dropupContent.innerHTML = '';
                
                if (categories[tabType]) {
                    categories[tabType].forEach(category => {
                        const link = document.createElement('a');
                        link.href = '#';
                        link.textContent = category;
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            selectCategory(category);
                        });
                        dropupContent.appendChild(link);
                    });
                }
            }
            
            function selectCategory(category) {
                const dropBtn = document.querySelector('.dropbtn');
                if (dropBtn) {
                    dropBtn.textContent = category;
                }
                if (selectedCategoryInput) {
                    selectedCategoryInput.value = category;
                }
                if (dropupContent) {
                    dropupContent.classList.remove('show');
                }
            }
            
            if (tabBtns) {
                tabBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        tabBtns.forEach(tab => tab.classList.remove('active'));
                        
                        this.classList.add('active');
                        
                        const tabType = this.getAttribute('data-tab');
                        
                        if (transactionTypeInput) {
                            transactionTypeInput.value = tabType;
                        }
                        
                        updateCategoryDropdown(tabType);
                        
                        const dropBtn = document.querySelector('.dropbtn');
                        if (dropBtn) {
                            dropBtn.textContent = 'Select category';
                        }
                        if (selectedCategoryInput) {
                            selectedCategoryInput.value = '';
                        }
                    });
                });
            }
            
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    if (selectedCategoryInput && !selectedCategoryInput.value) {
                        e.preventDefault();
                        alert('Please select a category!');
                    }
                });
            }
            
            updateCategoryDropdown('income');
            
            const budgetForm = document.getElementById('budgetForm');
            if (budgetForm) {
                budgetForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('save_budget.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        const plannedBudgetValue = document.querySelector('.budget-column:first-child .budget-value');
                        if (plannedBudgetValue) {
                            plannedBudgetValue.textContent = parseFloat(formData.get('plannedBudget')).toFixed(2);
                        }
                        
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success';
                        alertDiv.textContent = 'Budget plan updated successfully!';
                        
                        const dashboard = document.querySelector('.dashboard');
                        if (dashboard) {
                            dashboard.insertBefore(alertDiv, dashboard.firstChild);
                            
                            setTimeout(() => {
                                alertDiv.remove();
                            }, 3000);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                });
            }
        });
    </script>
</body>
</html>

