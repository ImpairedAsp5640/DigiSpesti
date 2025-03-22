<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

require_once 'config.php';

$checkTableQuery = "SHOW TABLES LIKE 'savings_goals'";
$result = $conn->query($checkTableQuery);
if ($result->num_rows == 0) {
    $createTableQuery = "CREATE TABLE savings_goals (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        name VARCHAR(255) NOT NULL,
        target_amount DECIMAL(10, 2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    $conn->query($createTableQuery);
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_goal') {
    $goalId = $_POST['goal_id'] ?? 0;
    
    if ($goalId > 0) {
        $checkGoalQuery = "SELECT id FROM savings_goals WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($checkGoalQuery);
        $stmt->bind_param("ii", $goalId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $deleteGoalQuery = "DELETE FROM savings_goals WHERE id = ?";
            $stmt = $conn->prepare($deleteGoalQuery);
            $stmt->bind_param("i", $goalId);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Цел за спестяване е изтрита успешно!";
            } else {
                $_SESSION['error'] = "Възникна грешка при изтриването на цел: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Невалидна цел за спестяване.";
        }

        header("Location: savings.php");
        exit();
    }
}

$savingsQuery = "SELECT id, category, amount, comment, transaction_date, 
                (SELECT SUM(amount) FROM transactions 
                 WHERE user_id = ? AND type = 'savings' AND category = t.category) as total_saved
                FROM transactions t
                WHERE user_id = ? AND type = 'savings'
                ORDER BY category, transaction_date DESC";
$stmt = $conn->prepare($savingsQuery);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$savingsTransactions = $stmt->get_result();

$goalsQuery = "SELECT * FROM savings_goals WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($goalsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$savingsGoals = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_goal') {
    $goalName = $_POST['goal_name'] ?? '';
    $goalAmount = $_POST['goal_amount'] ?? 0;
    $goalDescription = $_POST['goal_description'] ?? '';
    
    if (!empty($goalName) && $goalAmount > 0) {
        $insertGoalQuery = "INSERT INTO savings_goals (user_id, name, target_amount, description, created_at) 
                           VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insertGoalQuery);
        $stmt->bind_param("isds", $userId, $goalName, $goalAmount, $goalDescription);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Цел за спестяване е добавена успешно!";
        } else {
            $_SESSION['error'] = "Възникна грешка при въвеждане на цел за спестяване: " . $conn->error;
        }
        
        header("Location: savings.php");
        exit();
    } else {
        $_SESSION['error'] = "Моля направете име и валидна сума за Вашето спестяване.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contribute') {
    $goalId = $_POST['goal_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $comment = $_POST['comment'] ?? '';
    
    if ($goalId > 0 && $amount > 0) {
        $goalQuery = "SELECT name FROM savings_goals WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($goalQuery);
        $stmt->bind_param("ii", $goalId, $userId);
        $stmt->execute();
        $goalResult = $stmt->get_result();
        
        if ($goalResult->num_rows > 0) {
            $goalData = $goalResult->fetch_assoc();
            $category = $goalData['name'];

            $conn->begin_transaction();
            
            try {
                $balanceQuery = "SELECT balance FROM users WHERE id = ?";
                $stmt = $conn->prepare($balanceQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $currentBalance = $row['balance'] ?? 0;
                
                $newBalance = $currentBalance - $amount;
                
                $updateBalanceQuery = "UPDATE users SET balance = ? WHERE id = ?";
                $stmt = $conn->prepare($updateBalanceQuery);
                $stmt->bind_param("di", $newBalance, $userId);
                $stmt->execute();
                
                $insertQuery = "INSERT INTO transactions (user_id, type, category, amount, comment, transaction_date) 
                                VALUES (?, 'savings', ?, ?, ?, NOW())";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("isds", $userId, $category, $amount, $comment);
                $stmt->execute();
                
                $conn->commit();
                
                $_SESSION['success'] = "Вноската е добавена успешно!";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Възникна грешка: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Невалидна цел за спестяване.";
        }
        
        header("Location: savings.php");
        exit();
    } else {
        $_SESSION['error'] = "Моля въведете валидна сума.";
    }
}

$savingsByCategory = [];
if ($savingsTransactions->num_rows > 0) {
    mysqli_data_seek($savingsTransactions, 0);
    while ($transaction = $savingsTransactions->fetch_assoc()) {
        $category = $transaction['category'];
        if (!isset($savingsByCategory[$category])) {
            $savingsByCategory[$category] = [
                'transactions' => [],
                'total' => 0
            ];
        }
        $savingsByCategory[$category]['transactions'][] = $transaction;
        $savingsByCategory[$category]['total'] = $transaction['total_saved'];
    }
}

$goalsWithProgress = [];
if ($savingsGoals->num_rows > 0) {
    mysqli_data_seek($savingsGoals, 0);
    while ($goal = $savingsGoals->fetch_assoc()) {
        $goalName = $goal['name'];
        $targetAmount = $goal['target_amount'];
        $savedAmount = 0;
        
        if (isset($savingsByCategory[$goalName])) {
            $savedAmount = $savingsByCategory[$goalName]['total'];
        }
        
        $progress = ($targetAmount > 0) ? min(100, ($savedAmount / $targetAmount) * 100) : 0;
        $isComplete = $progress >= 100;
        
        $goalsWithProgress[] = [
            'id' => $goal['id'],
            'name' => $goalName,
            'target_amount' => $targetAmount,
            'saved_amount' => $savedAmount,
            'description' => $goal['description'],
            'progress' => $progress,
            'is_complete' => $isComplete,
            'created_at' => $goal['created_at']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Спестяване - DigiSpesti</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="modal.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .savings-container {
            margin-top: 24px;
        }
        
        .savings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        .savings-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.3s ease;
            animation: fadeIn 1s ease-in-out;
            position: relative;
        }
        
        .savings-card:hover {
            transform: translateY(-5px);
        }
        
        .savings-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .savings-card-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .savings-card-amount {
            font-size: 24px;
            font-weight: bold;
            margin: 8px 0;
            color: #d4af37;
        }
        
        .savings-card-description {
            color: #666;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .progress-container {
            background-color: #f0f0f0;
            border-radius: 4px;
            height: 20px;
            margin-bottom: 8px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #ffc300;
            width: 0;
            transition: width 1s ease-in-out;
            position: relative;
        }
        
        .progress-bar.complete {
            background-color: #4CAF50;
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
        }
        
        .savings-card-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }
        
        .add-goal-card {
            background-color: #f5f5f5;
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            animation: fadeIn 1s ease-in-out;
        }
        
        .add-goal-card:hover {
            background-color: #e5e5e5;
            border-color: #aaa;
        }
        
        .add-goal-icon {
            width: 48px;
            height: 48px;
            background-color: #ffc300;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: white;
            font-size: 24px;
        }
        
        .add-goal-text {
            font-size: 16px;
            color: #666;
        }
        
        .savings-history {
            margin-top: 48px;
        }
        
        .savings-history h2 {
            margin-bottom: 16px;
        }
        
        .transaction-list {
            margin-top: 24px;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e5e5;
            animation: fadeIn 1s ease-in-out;
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
        
        .contribute-btn {
            background-color: #ffc300;
            color: #1a1a1a;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .contribute-btn:hover {
            background-color: #cc9c00;
        }
        
        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        
        .celebration {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
            display: none;
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #ffc300;
            opacity: 0;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes confettiFall {
            0% {
                opacity: 1;
                transform: translateY(0) rotate(0deg);
            }
            100% {
                opacity: 0;
                transform: translateY(100vh) rotate(720deg);
            }
        }
        
        .almost-complete {
            animation: pulse 1.5s infinite;
        }
        
        .complete {
            border: 2px solid #4CAF50;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 195, 0, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 195, 0, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 195, 0, 0);
            }
        }
        
        .goal-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 20;
            display: none;
        }
        
        .goal-message h3 {
            margin-top: 0;
        }
        
        .goal-message.almost h3 {
            color: #d4af37;
        }
        
        .goal-message.complete h3 {
            color: #4CAF50;
        }
        
        .goal-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 100;
            justify-content: center;
            align-items: center;
        }
        
        .goal-modal-content {
            background-color: white;
            border-radius: 8px;
            padding: 24px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }
        
        .goal-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .goal-modal-header h2 {
            margin: 0;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
        }
        
        .goal-form-group {
            margin-bottom: 16px;
        }
        
        .goal-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .goal-form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .goal-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
        }
        
        .completion-badge {
            background-color: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
        .emergency-fund-btn {
            background-color: #6b6b7b;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .emergency-fund-btn:hover {
            background-color: #555566;
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
                <a href="product_promotions.php" class="nav-link">Промоции</a>
                <form action="logout.php" method="POST" style="display: inline; margin-left: 20px;">
                    <button type="submit" class="btn btn-outline">Излезте</button>
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
                <h1>Спестявания</h1>
                <p>Контролирайте своите цели за спестявания и прогреса си</p>
            </div>
            <button class="btn btn-primary" id="addGoalBtn">Добави цел за спестяване</button>
        </div>

        <div class="savings-container">
            <div class="savings-grid">
                <?php if (count($goalsWithProgress) > 0): ?>
                    <?php foreach ($goalsWithProgress as $goal): ?>
                        <div class="savings-card <?php 
                            if ($goal['is_complete']) {
                                echo 'complete';
                            } elseif ($goal['progress'] >= 90) {
                                echo 'almost-complete';
                            }
                        ?>">
                            <div class="savings-card-header">
                                <h3 class="savings-card-title">
                                    <?php echo htmlspecialchars($goal['name']); ?>
                                    <?php if ($goal['is_complete']): ?>
                                        <span class="completion-badge">ИЗПЪЛНЕНО</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="savings-card-amount">
                                <?php echo number_format($goal['saved_amount'], 2); ?> / <?php echo number_format($goal['target_amount'], 2); ?> лв.
                            </div>
                            <?php if (!empty($goal['description'])): ?>
                                <div class="savings-card-description">
                                    <?php echo htmlspecialchars($goal['description']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="progress-container">
                                <div class="progress-bar <?php echo $goal['is_complete'] ? 'complete' : ''; ?>" 
                                     style="width: <?php echo $goal['progress']; ?>%;" 
                                     data-progress="<?php echo $goal['progress']; ?>" 
                                     data-goal-id="<?php echo $goal['id']; ?>"
                                     data-complete="<?php echo $goal['is_complete'] ? 'true' : 'false'; ?>">
                                </div>
                            </div>
                            <div class="progress-text">
                                <span><?php echo number_format($goal['progress'], 1); ?>%</span>
                                <?php if (!$goal['is_complete']): ?>
                                    <span><?php echo number_format($goal['target_amount'] - $goal['saved_amount'], 2); ?> лв. оставащи</span>
                                <?php else: ?>
                                    <span>Целта е постигната!</span>
                                <?php endif; ?>
                            </div>
                            <div class="savings-card-actions">
                                <?php if (!$goal['is_complete']): ?>
                                    <button class="contribute-btn" data-goal-id="<?php echo $goal['id']; ?>" data-goal-name="<?php echo htmlspecialchars($goal['name']); ?>">
                                        Преведи
                                    </button>
                                    <button class="emergency-fund-btn" data-goal-id="<?php echo $goal['id']; ?>" data-goal-name="<?php echo htmlspecialchars($goal['name']); ?>">
                                        Спешни спестяване
                                    </button>
                                <?php else: ?>
                                    <button class="delete-btn" data-goal-id="<?php echo $goal['id']; ?>" data-goal-name="<?php echo htmlspecialchars($goal['name']); ?>">
                                        Изтрийте целта
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($goal['progress'] >= 90 && !$goal['is_complete']): ?>
                                <div class="celebration" id="celebration-<?php echo $goal['id']; ?>">
                                </div>
                                <div class="goal-message almost" id="message-<?php echo $goal['id']; ?>">
                                    <h3>Добра работа, продължавайте!</h3>
                                    <p>Почти постигна целта си!</p>
                                </div>
                            <?php elseif ($goal['is_complete']): ?>
                                <div class="celebration" id="celebration-complete-<?php echo $goal['id']; ?>">
                                </div>
                                <div class="goal-message complete" id="complete-message-<?php echo $goal['id']; ?>">
                                    <h3>Браво!</h3>
                                    <p>Вие постигнахте целта си!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="add-goal-card" id="addGoalCard">
                    <div class="add-goal-icon">+</div>
                    <div class="add-goal-text">Add a new savings goal</div>
                </div>
            </div>
        </div>

        <div class="savings-history">
            <h2>Последни спестяващи транзакции</h2>
            
            <div class="transaction-list">
                <?php if ($savingsTransactions->num_rows > 0): ?>
                    <?php 
                    mysqli_data_seek($savingsTransactions, 0);
                    $count = 0;
                    while ($transaction = $savingsTransactions->fetch_assoc()): 
                        if ($count++ >= 5) break;
                    ?>
                        <div class="transaction-item">
                            <div class="transaction-date">
                                <?php echo date('d', strtotime($transaction['transaction_date'])); ?>
                            </div>
                            <div class="transaction-details">
                                <div><?php echo htmlspecialchars($transaction['category']); ?></div>
                                <?php if (!empty($transaction['comment'])): ?>
                                    <div style="color: #737373; font-size: 14px;"><?php echo htmlspecialchars($transaction['comment']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="transaction-amount">
                                <?php echo number_format($transaction['amount'], 2); ?> лв.
                            </div>
                            <div class="transaction-actions">
                                <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="transaction-action">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                    </svg>
                                </a>
                                <a href="delete_transaction.php?id=<?php echo $transaction['id']; ?>" class="transaction-action" onclick="return confirm('Сигурни ли сте, че искаш да изтриете своята транзакция?');">
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
                        <p>Няма намерени спестяващи транзакции</p>
                        <button class="btn btn-primary" id="addContributionBtn">Добави първата си вноска</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="goalModal" class="goal-modal">
        <div class="goal-modal-content">
            <div class="goal-modal-header">
                <h2>Добави цел за спестяване</h2>
                <span class="close-modal" id="closeGoalModal">&times;</span>
            </div>
            <div class="goal-modal-body">
                <form id="goalForm" action="savings.php" method="POST">
                    <input type="hidden" name="action" value="add_goal">
                    
                    <div class="goal-form-group">
                        <label for="goal_name">Име на целта: <span class="required">*</span></label>
                        <input type="text" id="goal_name" name="goal_name" class="goal-form-input" required>
                    </div>
                    
                    <div class="goal-form-group">
                        <label for="goal_amount">Желана сума: <span class="required">*</span></label>
                        <input type="number" id="goal_amount" name="goal_amount" class="goal-form-input" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="goal-form-group">
                        <label for="goal_description">Описание:</label>
                        <textarea id="goal_description" name="goal_description" class="goal-form-input" rows="3"></textarea>
                    </div>
                    
                    <div class="goal-form-actions">
                        <button type="button" class="btn btn-outline" id="cancelGoalBtn">Откажете</button>
                        <button type="submit" class="btn btn-primary">Запазете целта</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="contributeModal" class="goal-modal">
        <div class="goal-modal-content">
            <div class="goal-modal-header">
                <h2>Направи вноска на <span id="contributeGoalName"></span></h2>
                <span class="close-modal" id="closeContributeModal">&times;</span>
            </div>
            <div class="goal-modal-body">
                <form id="contributeForm" action="savings.php" method="POST">
                    <input type="hidden" name="action" value="contribute">
                    <input type="hidden" id="goal_id" name="goal_id" value="">
                    
                    <div class="goal-form-group">
                        <label for="amount">Сума: <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" class="goal-form-input" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="goal-form-group">
                        <label for="comment">Коментар:</label>
                        <textarea id="comment" name="comment" class="goal-form-input" rows="2"></textarea>
                    </div>
                    
                    <div class="goal-form-actions">
                        <button type="button" class="btn btn-outline" id="cancelContributeBtn">Откажете</button>
                        <button type="submit" class="btn btn-primary">Запази вноска</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="goal-modal">
        <div class="goal-modal-content">
            <div class="goal-modal-header">
                <h2>Изтрийте цел за спестявания</h2>
                <span class="close-modal" id="closeDeleteModal">&times;</span>
            </div>
            <div class="goal-modal-body">
                <p>Сигурни ли сте, че искате изтриете своята цел за спестяване "<span id="deleteGoalName"></span>"?</p>
                <p>Това действие не може да се възстанови.</p>
                
                <form id="deleteForm" action="savings.php" method="POST">
                    <input type="hidden" name="action" value="delete_goal">
                    <input type="hidden" id="delete_goal_id" name="goal_id" value="">
                    
                    <div class="goal-form-actions">
                        <button type="button" class="btn btn-outline" id="cancelDeleteBtn">Откажете</button>
                        <button type="submit" class="btn btn-primary">Изтрийте цел</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="emergencyFundModal" class="goal-modal">
        <div class="goal-modal-content">
            <div class="goal-modal-header">
                <h2>Използвай спешните спестявания за <span id="emergencyFundGoalName"></span></h2>
                <span class="close-modal" id="closeEmergencyFundModal">&times;</span>
            </div>
            <div class="goal-modal-body">
                <form id="emergencyFundForm" action="emergency-fund-transfer.php" method="POST">
                    <input type="hidden" id="ef_goal_id" name="goal_id" value="">
                
                    <div class="goal-form-group">
                        <label for="ef_amount">Сума: <span class="required">*</span></label>
                        <input type="number" id="ef_amount" name="amount" class="goal-form-input" step="0.01" min="0.01" required>
                    </div>
                
                    <div class="goal-form-group">
                        <label for="ef_comment">Коментар:</label>
                        <textarea id="ef_comment" name="comment" class="goal-form-input" rows="2"></textarea>
                    </div>
                
                    <div class="goal-form-actions">
                        <button type="button" class="btn btn-outline" id="cancelEmergencyFundBtn">Откажете</button>
                        <button type="submit" class="btn btn-primary">Фонд за превеждане</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="container">
        <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const progress = parseFloat(bar.getAttribute('data-progress'));
                bar.style.width = progress + '%';
                
                const isComplete = bar.getAttribute('data-complete') === 'true';
                const goalId = bar.getAttribute('data-goal-id');
                
                if (isComplete) {

                    const celebrationDiv = document.getElementById('celebration-complete-' + goalId);
                    const messageDiv = document.getElementById('complete-message-' + goalId);
                    
                    if (celebrationDiv) {
                        celebrationDiv.style.display = 'block';
                        createConfetti(celebrationDiv, true);
                    }
                    
                    if (messageDiv) {
                        setTimeout(() => {
                            messageDiv.style.display = 'block';
                            

                            setTimeout(() => {
                                messageDiv.style.display = 'none';
                            }, 5000);
                        }, 1000);
                    }
                }

                else if (progress >= 90) {
                    const celebrationDiv = document.getElementById('celebration-' + goalId);
                    const messageDiv = document.getElementById('message-' + goalId);
                    
                    if (celebrationDiv) {
                        celebrationDiv.style.display = 'block';
                        createConfetti(celebrationDiv, false);
                    }
                    

                    if (messageDiv) {
                        setTimeout(() => {
                            messageDiv.style.display = 'block';
                            

                            setTimeout(() => {
                                messageDiv.style.display = 'none';
                            }, 5000);
                        }, 1000);
                    }
                }
            });
            
            const goalModal = document.getElementById('goalModal');
            const addGoalBtn = document.getElementById('addGoalBtn');
            const addGoalCard = document.getElementById('addGoalCard');
            const closeGoalModalBtn = document.getElementById('closeGoalModal');
            const cancelGoalBtn = document.getElementById('cancelGoalBtn');
            
            function openGoalModal() {
                goalModal.style.display = 'flex';
            }
            
            function closeGoalModal() {
                goalModal.style.display = 'none';
            }
            
            if (addGoalBtn) {
                addGoalBtn.addEventListener('click', openGoalModal);
            }
            
            if (addGoalCard) {
                addGoalCard.addEventListener('click', openGoalModal);
            }
            
            if (closeGoalModalBtn) {
                closeGoalModalBtn.addEventListener('click', closeGoalModal);
            }
            
            if (cancelGoalBtn) {
                cancelGoalBtn.addEventListener('click', closeGoalModal);
            }
  
            const contributeModal = document.getElementById('contributeModal');
            const contributeButtons = document.querySelectorAll('.contribute-btn');
            const closeContributeModalBtn = document.getElementById('closeContributeModal');
            const cancelContributeBtn = document.getElementById('cancelContributeBtn');
            const contributeGoalName = document.getElementById('contributeGoalName');
            const goalIdInput = document.getElementById('goal_id');
            
            function openContributeModal(goalId, goalName) {
                if (contributeGoalName) {
                    contributeGoalName.textContent = goalName;
                }
                
                if (goalIdInput) {
                    goalIdInput.value = goalId;
                }
                
                contributeModal.style.display = 'flex';
            }
            
            function closeContributeModal() {
                contributeModal.style.display = 'none';
            }
            
            contributeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const goalId = this.getAttribute('data-goal-id');
                    const goalName = this.getAttribute('data-goal-name');
                    openContributeModal(goalId, goalName);
                });
            });
            
            if (closeContributeModalBtn) {
                closeContributeModalBtn.addEventListener('click', closeContributeModal);
            }
            
            if (cancelContributeBtn) {
                cancelContributeBtn.addEventListener('click', closeContributeModal);
            }
            

            const deleteModal = document.getElementById('deleteModal');
            const deleteButtons = document.querySelectorAll('.savings-card-actions .delete-btn');
            const closeDeleteModalBtn = document.getElementById('closeDeleteModal');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const deleteGoalName = document.getElementById('deleteGoalName');
            const deleteGoalIdInput = document.getElementById('delete_goal_id');
            
            function openDeleteModal(goalId, goalName) {
                if (deleteGoalName) {
                    deleteGoalName.textContent = goalName;
                }
                
                if (deleteGoalIdInput) {
                    deleteGoalIdInput.value = goalId;
                }
                
                deleteModal.style.display = 'flex';
            }
            
            function closeDeleteModal() {
                deleteModal.style.display = 'none';
            }
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const goalId = this.getAttribute('data-goal-id');
                    const goalName = this.getAttribute('data-goal-name');
                    openDeleteModal(goalId, goalName);
                });
            });
            
            if (closeDeleteModalBtn) {
                closeDeleteModalBtn.addEventListener('click', closeDeleteModal);
            }
            
            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', closeDeleteModal);
            }
            
            window.addEventListener('click', function(event) {
                if (event.target === goalModal) {
                    closeGoalModal();
                }
                
                if (event.target === contributeModal) {
                    closeContributeModal();
                }
                
                if (event.target === deleteModal) {
                    closeDeleteModal();
                }
                if (event.target === emergencyFundModal) {
                    closeEmergencyFundModal();
                }
            });
            
            const addContributionBtn = document.getElementById('addContributionBtn');
            if (addContributionBtn) {
                addContributionBtn.addEventListener('click', openGoalModal);
            }
            
            const goalForm = document.getElementById('goalForm');
            if (goalForm) {
                goalForm.addEventListener('submit', function(e) {
                    const goalName = document.getElementById('goal_name').value;
                    const goalAmount = document.getElementById('goal_amount').value;
                    
                    if (!goalName || !goalAmount || parseFloat(goalAmount) <= 0) {
                        e.preventDefault();
                        alert('Моля, посочете име и валидна сума за Вашата цел за спестяване.');
                    }
                });
            }
            
            const contributeForm = document.getElementById('contributeForm');
            if (contributeForm) {
                contributeForm.addEventListener('submit', function(e) {
                    const amount = document.getElementById('amount').value;
                    
                    if (!amount || parseFloat(amount) <= 0) {
                        e.preventDefault();
                        alert('c');
                    }
                });
            }
        });

        function createConfetti(container, isComplete) {
            const colors = isComplete ? 
                ['#4CAF50', '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107'] : 
                ['#ffc300', '#d4af37', '#ffeb3b', '#ff9800', '#ff5722'];
            const confettiCount = isComplete ? 100 : 50;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.top = '-10px';
                confetti.style.width = Math.random() * 10 + 5 + 'px';
                confetti.style.height = Math.random() * 10 + 5 + 'px';
                confetti.style.transform = 'rotate(' + Math.random() * 360 + 'deg)';
                confetti.style.animation = 'confettiFall ' + (Math.random() * 3 + 2) + 's linear forwards';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                
                container.appendChild(confetti);
            }
        }

        const emergencyFundModal = document.getElementById('emergencyFundModal');
        const emergencyFundButtons = document.querySelectorAll('.emergency-fund-btn');
        const closeEmergencyFundModalBtn = document.getElementById('closeEmergencyFundModal');
        const cancelEmergencyFundBtn = document.getElementById('cancelEmergencyFundBtn');
        const emergencyFundGoalName = document.getElementById('emergencyFundGoalName');
        const efGoalIdInput = document.getElementById('ef_goal_id');

        function openEmergencyFundModal(goalId, goalName) {
            if (emergencyFundGoalName) {
                emergencyFundGoalName.textContent = goalName;
            }
            
            if (efGoalIdInput) {
                efGoalIdInput.value = goalId;
            }
            
            emergencyFundModal.style.display = 'flex';
        }

        function closeEmergencyFundModal() {
            emergencyFundModal.style.display = 'none';
        }

        emergencyFundButtons.forEach(button => {
            button.addEventListener('click', function() {
                const goalId = this.getAttribute('data-goal-id');
                const goalName = this.getAttribute('data-goal-name');
                openEmergencyFundModal(goalId, goalName);
            });
        });

        if (closeEmergencyFundModalBtn) {
            closeEmergencyFundModalBtn.addEventListener('click', closeEmergencyFundModal);
        }

        if (cancelEmergencyFundBtn) {
            cancelEmergencyFundBtn.addEventListener('click', closeEmergencyFundModal);
        }

        window.addEventListener('click', function(event) {
            if (event.target === emergencyFundModal) {
                closeEmergencyFundModal();
            }
        });

        const emergencyFundForm = document.getElementById('emergencyFundForm');
        if (emergencyFundForm) {
            emergencyFundForm.addEventListener('submit', function(e) {
                const amount = document.getElementById('ef_amount').value;
                
                if (!amount || parseFloat(amount) <= 0) {
                    e.preventDefault();
                    alert('Моля, предоставете валидна сума за вашето превеждане.');
                }
            });
        }
    </script>
</body>

</html>

