<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html"); 
    exit();
}
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
        <div class="dashboard-header">
            <div>
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p>Here's an overview of your financial health</p>
            </div>
            <button class="btn btn-primary" id="addPaymentBtn">Добави плащане</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Total Balance</div>
                <div class="stat-value">
                    <form action="">
                        <input type="text" placeholder="Balance">
                    </form>
                </div>
                <div class="stat-description">+$2,100 from last month</div>
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

    <!-- Payment Modal Overlay -->
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
                <form id="paymentForm">
                    <div class="form-group">
                        <label for="date">Дата: <span class="required">*</span></label>
                        <input type="text" id="date" class="form-input" value="20 март 2025" readonly>
                        <span class="check-mark">✓</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Сума: <span class="required">*</span></label>
                        <input type="text" id="amount" class="form-input" placeholder="Пример: 100.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Коментар:</label>
                        <textarea id="comment" class="form-input" placeholder="Пример: Сметка за ток"></textarea>
                    </div>
                    
                    <button type="button" class="btn category-btn">Избери категория</button>
                </form>
            </div>
        </div>
    </div>

    <footer class="container">
        <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
    </footer>

    <script>
        // Modal functionality
        const modal = document.getElementById('paymentModal');
        const addPaymentBtn = document.getElementById('addPaymentBtn');
        const closeModal = document.querySelector('.close-modal');
        const tabBtns = document.querySelectorAll('.tab-btn');
        
        // Open modal when button is clicked
        addPaymentBtn.addEventListener('click', function() {
            modal.style.display = 'flex';
        });
        
        // Close modal when X is clicked
        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Tab functionality
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all tabs
                tabBtns.forEach(tab => tab.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');
                
                // You can add logic here to show different form fields based on tab
                const tabType = this.getAttribute('data-tab');
                console.log('Selected tab:', tabType);
            });
        });
    </script>
</body>
</html>