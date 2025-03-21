<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$currentMonth = date('Y-m');
$currentMonthName = date('F Y');
$previousMonth = date('Y-m', strtotime('-1 month'));
$previousMonthName = date('F Y', strtotime('-1 month'));

$balanceQuery = "SELECT balance FROM users WHERE id = ?";
$stmt = $conn->prepare($balanceQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$currentBalance = $row['balance'] ?? 0;

$incomeQuery = "SELECT SUM(amount) as total_income FROM transactions 
                WHERE user_id = ? AND type = 'income' 
                AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
$stmt = $conn->prepare($incomeQuery);
$stmt->bind_param("is", $userId, $currentMonth);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$currentMonthIncome = $row['total_income'] ?? 0;

$savingsQuery = "SELECT SUM(amount) as total_savings FROM transactions 
                 WHERE user_id = ? AND type = 'savings' 
                 AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
$stmt = $conn->prepare($savingsQuery);
$stmt->bind_param("is", $userId, $currentMonth);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$currentMonthSavings = $row['total_savings'] ?? 0;

$savingsRate = 0;
if ($currentMonthIncome > 0) {
    $savingsRate = ($currentMonthSavings / $currentMonthIncome) * 100;
}

$prevIncomeQuery = "SELECT SUM(amount) as total_income FROM transactions 
                    WHERE user_id = ? AND type = 'income' 
                    AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
$stmt = $conn->prepare($prevIncomeQuery);
$stmt->bind_param("is", $userId, $previousMonth);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$previousMonthIncome = $row['total_income'] ?? 0;

$prevSavingsQuery = "SELECT SUM(amount) as total_savings FROM transactions 
                     WHERE user_id = ? AND type = 'savings' 
                     AND DATE_FORMAT(transaction_date, '%Y-%m') = ?";
$stmt = $conn->prepare($prevSavingsQuery);
$stmt->bind_param("is", $userId, $previousMonth);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$previousMonthSavings = $row['total_savings'] ?? 0;

$previousSavingsRate = 0;
if ($previousMonthIncome > 0) {
    $previousSavingsRate = ($previousMonthSavings / $previousMonthIncome) * 100;
}

$savingsRateChange = $savingsRate - $previousSavingsRate;

$prevBalanceQuery = "SELECT 
                        (SELECT balance FROM users WHERE id = ?) - 
                        (SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) 
                         FROM transactions 
                         WHERE user_id = ? AND DATE_FORMAT(transaction_date, '%Y-%m') = ?) 
                     AS prev_balance";
$stmt = $conn->prepare($prevBalanceQuery);
$stmt->bind_param("iis", $userId, $userId, $currentMonth);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$previousBalance = max(0, $row['prev_balance'] ?? 0);

$balanceChange = $currentBalance - $previousBalance;

$currentMonthNum = date('m');
$currentYearNum = date('Y');
$daysInMonth = date('t');

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
$stmt->bind_param("iii", $userId, $currentMonthNum, $currentYearNum);
$stmt->execute();
$dailyTotals = $stmt->get_result();

$graphData = [];
$runningIncome = 0;
$runningExpense = 0;
$runningSavings = 0;

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
}

$graphDataJson = json_encode($graphData);
$currentDay = date('d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiSpesti</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="modal.css">
    <style>
        
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
                <h1>Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
                <p>Here's an overview of your financial health for <?php echo $currentMonthName; ?></p>
            </div>
            <button class="btn btn-primary" id="addPaymentBtn">Добави плащане</button>
        </div>

        <div class="stats-grid stats-grid-two">
            <div class="stat-card">
                <div class="stat-title">Total Balance</div>
                <div class="stat-value" id="totalBalance">
                    <?php echo number_format($currentBalance, 2); ?> лв.
                </div>
                <div class="stat-description">
                    <?php if ($balanceChange > 0): ?>
                        <span class="positive-change">+<?php echo number_format($balanceChange, 2); ?> лв.</span> from <?php echo $previousMonthName; ?>
                    <?php elseif ($balanceChange < 0): ?>
                        <span class="negative-change"><?php echo number_format($balanceChange, 2); ?> лв.</span> from <?php echo $previousMonthName; ?>
                    <?php else: ?>
                        No change from <?php echo $previousMonthName; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Savings Rate</div>
                <div class="stat-value"><?php echo number_format($savingsRate, 1); ?>%</div>
                <div class="stat-description">
                    <?php if ($savingsRateChange > 0): ?>
                        <span class="positive-change">+<?php echo number_format($savingsRateChange, 1); ?>%</span> from <?php echo $previousMonthName; ?>
                    <?php elseif ($savingsRateChange < 0): ?>
                        <span class="negative-change"><?php echo number_format($savingsRateChange, 1); ?>%</span> from <?php echo $previousMonthName; ?>
                    <?php else: ?>
                        No change from <?php echo $previousMonthName; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 24px;">
            <div class="card card-premium" style="flex: 2; min-width: 300px;">
                <h2>Spending Overview</h2>
                <p>Your spending patterns for <?php echo $currentMonthName; ?></p>
                <div class="graph-container" style="height: 300px; border-radius: 8px; overflow: hidden; margin-bottom: 0;">
                    <div class="graph-area" id="graphArea">
                    </div>
                    <div class="graph-labels">
                        <div>day 1</div>
                        <div id="currentDayLabel"><?php echo date('d'); ?></div>
                        <div><?php echo date('t'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="card card-premium" style="flex: 1; min-width: 300px;">
                <h2>Financial Tips</h2>
                <p>Smart advice to improve your financial health</p>
                
                <div id="tips-container">
                </div>
            </div>
        </div>
    </main>

    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>ДОБАВИ</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-tabs">
                <button class="tab-btn active" data-tab="income">ДОХОД</button>
                <button class="tab-btn" data-tab="expense">РАЗХОД</button>
                <button class="tab-btn" data-tab="savings">СПЕСТЯВАНЕ</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" action="process_transaction.php" method="POST">
                    <input type="hidden" id="transactionType" name="transactionType" value="income">
                    <input type="hidden" id="selectedCategory" name="selectedCategory" value="">
                    
                    <div class="form-group">
                        <label for="date">Дата: <span class="required">*</span></label>
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
                        <label for="amount">Сума: <span class="required">*</span></label>
                        <input type="text" id="amount" name="amount" class="form-input" placeholder="Пример: 100.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Коментар:</label>
                        <textarea id="comment" name="comment" class="form-input" placeholder="Пример: Сметка за ток"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Категория: <span class="required">*</span></label>
                        <div class="dropup">
                            <button type="button" onclick="dropup()" class="dropbtn">Изберете категория</button>
                            <div id="Dropup" class="dropup-content">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Запази</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="container">
        <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
    </footer>

    <script>
        const modal = document.getElementById('paymentModal');
        const addPaymentBtn = document.getElementById('addPaymentBtn');
        const closeModal = document.querySelector('.close-modal');
        const tabBtns = document.querySelectorAll('.tab-btn');
        const dropupContent = document.getElementById('Dropup');
        const transactionTypeInput = document.getElementById('transactionType');
        const selectedCategoryInput = document.getElementById('selectedCategory');
        const paymentForm = document.getElementById('paymentForm');
        
        // Category options for each tab
        const categories = {
            income: ['Salary', 'Previous month balance'],
            expense: ['Utilities', 'Housing', 'Groceries', 'Transport', 'Car', 'Kids', 'Clothing', 
                     'Personal', 'Cigarettes & alcohol', 'Fun', 'Eating out', 'Education', 
                     'Gifts', 'Sport/Hobby', 'Travel/Leisure', 'Medical', 'Pets', 'Miscellaneous'],
            savings: ['Emergency Fund', 'Retirement', 'Vacation', 'Education', 'Home Purchase']
        };
        
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
        
        addPaymentBtn.addEventListener('click', function() {
            modal.style.display = 'flex';
            updateCategoryDropdown('income'); // Default to income tab
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        function dropup() {
            document.getElementById("Dropup").classList.toggle("show");
        }

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
        }
        
        function updateCategoryDropdown(tabType) {
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
            document.querySelector('.dropbtn').textContent = category;
            selectedCategoryInput.value = category;
            dropupContent.classList.remove('show');
        }
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                tabBtns.forEach(tab => tab.classList.remove('active'));
                
                this.classList.add('active');
                
                const tabType = this.getAttribute('data-tab');
                
                transactionTypeInput.value = tabType;
                
                updateCategoryDropdown(tabType);
                
                document.querySelector('.dropbtn').textContent = 'Изберете категория';
                selectedCategoryInput.value = '';
            });
        });
        
        paymentForm.addEventListener('submit', function(e) {
            if (!selectedCategoryInput.value) {
                e.preventDefault();
                alert('Моля, изберете категория!');
            }
        });
        
        updateCategoryDropdown('income');
        
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date');
            if (dateInput) {
                const datePicker = new DatePicker(dateInput);
            }
        });
        
        const financialTips = [
            {
                title: "50/30/20 Budget Rule",
                description: "Allocate 50% of your income to needs, 30% to wants, and 20% to savings and debt repayment."
            },
            {
                title: "Emergency Fund First",
                description: "Build an emergency fund covering 3-6 months of expenses before focusing on other financial goals."
            },
            {
                title: "Pay Yourself First",
                description: "Set up automatic transfers to your savings account on payday before spending on other expenses."
            },
            {
                title: "Eliminate High-Interest Debt",
                description: "Focus on paying off high-interest debt first, such as credit cards, to save money in the long run."
            },
            {
                title: "Track Your Spending",
                description: "Monitor where your money goes for at least one month to identify areas where you can cut back."
            },
            {
                title: "Use the 24-Hour Rule",
                description: "Wait 24 hours before making non-essential purchases to avoid impulse buying."
            },
            {
                title: "Automate Your Finances",
                description: "Set up automatic bill payments and savings transfers to ensure consistency and avoid late fees."
            },
            {
                title: "Negotiate Bills Annually",
                description: "Call service providers yearly to negotiate better rates on recurring bills like internet and insurance."
            },
            {
                title: "Invest Early and Often",
                description: "Take advantage of compound interest by starting to invest as early as possible, even with small amounts."
            },
            {
                title: "Cook at Home",
                description: "Prepare meals at home instead of eating out to save significantly on food expenses."
            },
            {
                title: "Use Cash for Discretionary Spending",
                description: "Pay with cash for non-essential purchases to become more mindful of your spending habits."
            },
            {
                title: "Review Subscriptions Quarterly",
                description: "Audit your subscriptions every three months and cancel those you don't regularly use."
            },
            {
                title: "Buy Used for Big Purchases",
                description: "Consider buying quality used items for big purchases like cars and furniture to avoid depreciation."
            },
            {
                title: "Increase Retirement Contributions",
                description: "Boost your retirement contributions by 1% each year to build your nest egg without feeling the pinch."
            },
            {
                title: "Use Tax-Advantaged Accounts",
                description: "Maximize contributions to tax-advantaged accounts like 401(k)s and IRAs to reduce your tax burden."
            }
        ];
        
        function getRandomTips(tipsArray, count) {
            const tipsCopy = [...tipsArray];
            const selectedTips = [];
            
            for (let i = 0; i < count && tipsCopy.length > 0; i++) {
                const randomIndex = Math.floor(Math.random() * tipsCopy.length);
                selectedTips.push(tipsCopy[randomIndex]);
                tipsCopy.splice(randomIndex, 1); // Remove the selected tip to avoid duplicates
            }
            
            return selectedTips;
        }
        
        function displayTips() {
            const tipsContainer = document.getElementById('tips-container');
            if (!tipsContainer) return;
            
            tipsContainer.innerHTML = '';
            
            const randomTips = getRandomTips(financialTips, 3);
            
            randomTips.forEach(tip => {
                const tipElement = document.createElement('div');
                tipElement.className = 'insight-card tip-card';
                
                tipElement.innerHTML = `
                    <p class="insight-title">${tip.title}</p>
                    <p class="insight-description">${tip.description}</p>
                `;
                
                tipsContainer.appendChild(tipElement);
            });
        }
        
        document.addEventListener('DOMContentLoaded', displayTips);

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

        document.addEventListener('DOMContentLoaded', function() {
            renderGraph();
        });
    </script>
</body>
</html>

