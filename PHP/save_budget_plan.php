<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.html");
  exit();
}

require_once 'config.php';

$debug = true;
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    if (!file_exists('budget_debug.log')) {
        file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Debug log created\n");
    }
    
    file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
}

$userId = $_SESSION['user_id'];
$month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

$incomeData = isset($_POST['income']) ? $_POST['income'] : [];
$expenseData = isset($_POST['expense']) ? $_POST['expense'] : [];

if ($debug) {
  file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Parsed data: Income: " . print_r($incomeData, true) . 
                   " Expense: " . print_r($expenseData, true) . "\n", FILE_APPEND);
}

if (isset($incomeData["0"])) {
    unset($incomeData["0"]);
    if ($debug) {
        file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Removed '0' category from income data\n", FILE_APPEND);
    }
}

if (isset($expenseData["0"])) {
    unset($expenseData["0"]);
    if ($debug) {
        file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Removed '0' category from expense data\n", FILE_APPEND);
    }
}

$totalIncome = 0;
foreach ($incomeData as $category => $amount) {
  $totalIncome += floatval(str_replace(',', '.', $amount));
}

$totalExpenses = 0;
foreach ($expenseData as $category => $amount) {
  $totalExpenses += floatval(str_replace(',', '.', $amount));
}

$totalSavings = 0;

if ($debug) {
  file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Totals: Income: $totalIncome, Expenses: $totalExpenses, Savings: $totalSavings\n", FILE_APPEND);
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
  
  if ($debug) {
    file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Ensured tables exist\n", FILE_APPEND);
  }
}

ensureBudgetTablesExist($conn);

$conn->begin_transaction();

try {
  $checkQuery = "SELECT id FROM budget_plans WHERE user_id = ? AND month = ? AND year = ?";
  $stmt = $conn->prepare($checkQuery);
  $stmt->bind_param("iii", $userId, $month, $year);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      $budgetPlanId = $row['id'];
      
      $updateQuery = "UPDATE budget_plans SET income = ?, expenses = ?, savings = ? WHERE id = ?";
      $stmt = $conn->prepare($updateQuery);
      $stmt->bind_param("dddi", $totalIncome, $totalExpenses, $totalSavings, $budgetPlanId);
      $stmt->execute();
      
      if ($debug) {
          file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Updated budget plan ID: $budgetPlanId\n", FILE_APPEND);
      }
  } else {
      $insertQuery = "INSERT INTO budget_plans (user_id, month, year, income, expenses, savings) VALUES (?, ?, ?, ?, ?, ?)";
      $stmt = $conn->prepare($insertQuery);
      $stmt->bind_param("iiiddd", $userId, $month, $year, $totalIncome, $totalExpenses, $totalSavings);
      $stmt->execute();
      $budgetPlanId = $conn->insert_id;
      
      if ($debug) {
          file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Inserted new budget plan ID: $budgetPlanId\n", FILE_APPEND);
      }
  }
  
  $deleteQuery = "DELETE FROM budget_plan_items WHERE user_id = ? AND month = ? AND year = ?";
  $stmt = $conn->prepare($deleteQuery);
  $stmt->bind_param("iii", $userId, $month, $year);
  $stmt->execute();
  
  if ($debug) {
      file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Deleted existing budget plan items\n", FILE_APPEND);
  }
  
  foreach ($incomeData as $category => $amount) {
    if (empty($category) || $category === "0") {
        if ($debug) {
            file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Skipping empty or '0' income category\n", FILE_APPEND);
        }
        continue;
    }
    
    $amount = floatval(str_replace(',', '.', $amount));
    
    if ($amount <= 0) {
        if ($debug) {
            file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Skipping zero amount income item: $category = $amount\n", FILE_APPEND);
        }
        continue;
    }
    
    if ($debug) {
        file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Processing income item: $category = $amount\n", FILE_APPEND);
    }
    
    try {
        $insertItemQuery = "INSERT INTO budget_plan_items (user_id, month, year, type, category, amount) VALUES (?, ?, ?, 'income', ?, ?)";
        $stmt = $conn->prepare($insertItemQuery);
        $stmt->bind_param("iiisd", $userId, $month, $year, $category, $amount);
        $result = $stmt->execute();
        
        if ($debug) {
            if ($result) {
                file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Successfully inserted income item: $category = $amount\n", FILE_APPEND);
            } else {
                file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Failed to insert income item: $category = $amount. Error: " . $stmt->error . "\n", FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        if ($debug) {
            file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Error inserting income item: $category = $amount. Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        continue;
    }
  }
  
  if ($debug) {
      file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Starting to insert expense items. Count: " . count($expenseData) . "\n", FILE_APPEND);
  }
  
  foreach ($expenseData as $category => $amount) {
    if (empty($category) || $category === "0") {
        if ($debug) {
            file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Skipping empty or '0' expense category\n", FILE_APPEND);
        }
        continue;
    }
    
    $amount = floatval(str_replace(',', '.', $amount));
    
    if ($amount <= 0) {
        if ($debug) {
            file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Skipping zero amount expense item: $category = $amount\n", FILE_APPEND);
        }
        continue;
    }
    
    if ($debug) {
        file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Processing expense item: $category = $amount\n", FILE_APPEND);
    }
    
    try {
        $insertItemQuery = "INSERT INTO budget_plan_items (user_id, month, year, type, category, amount) VALUES (?, ?, ?, 'expense', ?, ?)";
        $stmt = $conn->prepare($insertItemQuery);
        $stmt->bind_param("iiisd", $userId, $month, $year, $category, $amount);
        $result = $stmt->execute();
        
        if ($debug) {
            if ($result) {
                file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Successfully inserted expense item: $category = $amount\n", FILE_APPEND);
            } else {
                file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Failed to insert expense item: $category = $amount. Error: " . $stmt->error . "\n", FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        if ($debug) {
            file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Error inserting expense item: $category = $amount. Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        continue;
    }
  }
  
  $conn->commit();
  
  if ($debug) {
      file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - Transaction committed successfully\n", FILE_APPEND);
  }
  
  $_SESSION['success'] = "Budget plan saved successfully!";
  
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
      echo "success";
  } else {
      header("Location: plan_budget.php?month=$month&year=$year");
  }
  
} catch (Exception $e) {
  $conn->rollback();
  
  if ($debug) {
      file_put_contents('budget_debug.log', date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
  }
  
  $_SESSION['error'] = "Error saving budget plan: " . $e->getMessage();
  
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
      echo "error: " . $e->getMessage();
  } else {
      header("Location: plan_budget.php?month=$month&year=$year");
  }
}

$conn->close();
?>