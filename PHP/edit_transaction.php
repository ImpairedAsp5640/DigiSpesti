<?php
session_start();

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No transaction ID provided.";
    header("Location: history.php");
    exit();
}

$transactionId = $_GET['id'];
$userId = $_SESSION['user_id'];

$checkQuery = "SELECT * FROM transactions WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("ii", $transactionId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Transaction not found or you don't have permission to edit it.";
    header("Location: history.php");
    exit();
}

$transaction = $result->fetch_assoc();
$transactionType = $transaction['type'];
$category = $transaction['category'];
$amount = $transaction['amount'];
$comment = $transaction['comment'];
$date = date('d F Y', strtotime($transaction['transaction_date']));

$categories = [
                'income' => ['Заплата', 'Баланс от предходен месец'],
                'expense' => ['Битови сметки', 'Жилище', 'Храна и консумативи', 'Транспорт', 'Автомобил', 'Деца', 'Дрехи и обувки', 
                     'Лични', 'Цигари и алкохол', 'Развлечения', 'Хранене навън', 'Образование', 
                     'Подаръци', 'Спорт/Хоби', 'Пътуване/Отдих', 'Медицински', 'Домашни любимци', 'Разни'],
            'savings' => ['Непредвидени разходи', 'Пенсиониране', 'Ваканция', 'Образование', 'Купуване на къща']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поправи транзакция - DigiSpesti</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="modal.css">
    <style>
        .edit-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }
        
        .form-header {
            margin-bottom: 24px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
        }
        
        .tab-container {
            display: flex;
            border-bottom: 1px solid #e5e5e5;
            margin-bottom: 24px;
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            font-weight: 500;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            border-bottom: 2px solid #ffc300;
            color: #0a2463;
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
        
        .required {
            color: #ef4444;
        }
        
        .dropup {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .dropbtn {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            background-color: white;
            text-align: left;
            cursor: pointer;
            font-size: 16px;
        }
        
        .dropup-content {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 0;
            width: 100%;
            background-color: white;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            box-shadow: 0 -4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .dropup-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        
        .dropup-content a:hover {
            background-color: #f5f5f5;
        }
        
        .show {
            display: block;
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
                <a href="index.php" class="nav-link">Начална страница</a>
                <a href="history.php" class="nav-link">Плащания</a>
                <a href="savings.php" class="nav-link">Спестявания</a>
                <a href="plan_budget.php" class="nav-link">Бюджет</a>
                <a href="product_promotions.php" class="nav-link">Промоции</a>
                <form action="logout.php" method="POST" style="display: inline; margin-left: 20px;">
                    <button type="submit" class="btn btn-outline">Излезте</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="edit-form">
            <div class="form-header">
                <h1>Редактирайте транзакцията</h1>
                <p>Актуализирайте Вашата транзакция</p>
            </div>
            
            <div class="tab-container">
                <div class="tab <?php echo ($transactionType == 'income') ? 'active' : ''; ?>" data-tab="income">ДОХОД</div>
                <div class="tab <?php echo ($transactionType == 'expense') ? 'active' : ''; ?>" data-tab="expense">РАЗХОД</div>
                <div class="tab <?php echo ($transactionType == 'savings') ? 'active' : ''; ?>" data-tab="savings">СПЕСТЯВАНЕ</div>
            </div>
            
            <form id="editForm" action="update_transaction.php" method="POST">
                <input type="hidden" name="transaction_id" value="<?php echo $transactionId; ?>">
                <input type="hidden" id="transactionType" name="transactionType" value="<?php echo $transactionType; ?>">
                <input type="hidden" id="selectedCategory" name="selectedCategory" value="<?php echo $category; ?>">
                
                <div class="form-group">
                    <label for="date">Дата: <span class="required">*</span></label>
                    <div class="date-picker">
                        <input type="text" id="date" name="date" class="form-input" value="<?php echo $date; ?>" readonly>
                        <div id="datePickerCalendar" class="date-picker-calendar">
                            <div class="date-picker-header">
                                <div class="date-picker-nav" id="prevMonth">&lt;</div>
                                <div id="currentMonthYear"></div>
                                <div class="date-picker-nav" id="nextMonth">&gt;</div>
                            </div>
                            <div class="date-picker-grid" id="calendarGrid">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="amount">Сума: <span class="required">*</span></label>
                    <input type="text" id="amount" name="amount" class="form-input" value="<?php echo $amount; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="comment">Коментар:</label>
                    <textarea id="comment" name="comment" class="form-input"><?php echo $comment; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="category">Категория: <span class="required">*</span></label>
                    <div class="dropup">
                        <button type="button" onclick="dropup()" class="dropbtn"><?php echo $category; ?></button>
                        <div id="Dropup" class="dropup-content">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="history.php" class="btn btn-outline">Откажете</a>
                    <button type="submit" class="btn btn-primary">Запазете промените</button>
                </div>
            </form>
        </div>
    </main>

    <footer class="container">
        <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
    </footer>

    <script>
        const categories = {
            income: <?php echo json_encode($categories['income']); ?>,
            expense: <?php echo json_encode($categories['expense']); ?>,
            savings: <?php echo json_encode($categories['savings']); ?>
        };
        
        class DatePicker {
            constructor(inputElement) {
                this.input = inputElement;
                this.calendar = document.getElementById('datePickerCalendar');
                this.currentDate = new Date();
                this.selectedDate = new Date(this.input.value);
                
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
            const dateInput = document.getElementById('date');
            if (dateInput) {
                const datePicker = new DatePicker(dateInput);
            }
            
            const tabs = document.querySelectorAll('.tab');
            const transactionTypeInput = document.getElementById('transactionType');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    this.classList.add('active');
                    
                    const tabType = this.getAttribute('data-tab');
                    
                    if (transactionTypeInput) {
                        transactionTypeInput.value = tabType;
                    }
                    
                    updateCategoryDropdown(tabType);
                });
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
                const dropupContent = document.getElementById('Dropup');
                const selectedCategoryInput = document.getElementById('selectedCategory');
                
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
                const selectedCategoryInput = document.getElementById('selectedCategory');
                
                if (dropBtn) {
                    dropBtn.textContent = category;
                }
                if (selectedCategoryInput) {
                    selectedCategoryInput.value = category;
                }
                
                document.getElementById("Dropup").classList.remove('show');
            }
            
            const editForm = document.getElementById('editForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const selectedCategoryInput = document.getElementById('selectedCategory');
                    
                    if (selectedCategoryInput && !selectedCategoryInput.value) {
                        e.preventDefault();
                        alert('Please select a category!');
                    }
                });
            }
            
            updateCategoryDropdown(transactionTypeInput.value);
        });
    </script>
</body>
</html>

