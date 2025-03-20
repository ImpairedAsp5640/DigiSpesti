<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}


require_once 'config.php';


$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];


$balanceQuery = "SELECT balance FROM users WHERE id = ?";
$stmt = $conn->prepare($balanceQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$balance = $row['balance'] ?? 0; 
require_once 'db_connect.php';


$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];



$transactionsQuery = "SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC LIMIT 10";
$stmt = $conn->prepare($transactionsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentTransactions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiSpesti</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="modal.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <div class="logo-icon">$</div>
                <span>DigiSpesti</span>
            </div>
            <nav class="nav">
                <a href="#" class="nav-link">Dashboard</a>
                <a href="#" class="nav-link">Savings</a>
                <a href="#" class="nav-link">Investments</a>
                <a href="#" class="nav-link">Budget AI</a>
                <form action="logout.php" method="POST" style="display: inline;">
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
                <p>Here's an overview of your financial health</p>
            </div>
            <button class="btn btn-primary" id="addPaymentBtn">Добави плащане</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Total Balance</div>
                <div class="stat-value" id="totalBalance">
                    <?php echo number_format($balance, 2); ?> лв.
                </div>
                <div class="stat-description">Track your financial growth</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Savings Rate</div>
                <div class="stat-value">24%</div>
                <div class="stat-description">+3% from last month</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">Investment Growth</div>
                <div class="stat-value">+8.2%</div>
                <div class="stat-description">YTD performance</div>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 24px;">
            <div class="card card-premium" style="flex: 2; min-width: 300px;">
                <h2>Spending Overview</h2>
                <p>Your spending patterns for the last 30 days</p>
                <div style="height: 300px; background-color: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <span style="color: #737373;">Spending chart visualization</span>
                </div>
            </div>
            
            <div class="card card-premium" style="flex: 1; min-width: 300px;">
                <h2>AI Insights</h2>
                <p>Personalized financial recommendations</p>
                
                <div class="insight-card">
                    <p class="insight-title">Reduce subscription costs</p>
                    <p class="insight-description">You could save $42/month by optimizing your streaming subscriptions.</p>
                    <a href="#" style="font-size: 14px; display: inline-block; margin-top: 8px;">View details →</a>
                </div>
                
                <div class="insight-card">
                    <p class="insight-title">Savings opportunity</p>
                    <p class="insight-description">Increasing your 401(k) contribution by 2% could save $840 in taxes this year.</p>
                    <a href="#" style="font-size: 14px; display: inline-block; margin-top: 8px;">View details →</a>
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
                        <input type="text" id="date" name="date" class="form-input" value="<?php echo date('d F Y'); ?>" readonly>
                        <span class="check-mark">✓</span>
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
                                <!-- Categories will be populated by JavaScript -->
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
        
        // Open modal
        addPaymentBtn.addEventListener('click', function() {
            modal.style.display = 'flex';
            updateCategoryDropdown('income'); // Default to income tab
        });

        // Close modal
        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Toggle dropdown
        function dropup() {
            document.getElementById("Dropup").classList.toggle("show");
        }

        // Close dropdown when clicking outside
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
        
        // Update category dropdown based on selected tab
        function updateCategoryDropdown(tabType) {
            // Clear existing options
            dropupContent.innerHTML = '';
            
            // Add new options based on tab
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
    </script>
</body>
</html>