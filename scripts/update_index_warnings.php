<?php
define('INCLUDED_FROM_SCRIPT', true);


$warningsQuery = "SELECT e.category, bpi.amount as planned_amount, 
             SUM(e.amount) as spent_amount,
             (bpi.amount - SUM(e.amount)) as remaining,
             (SUM(e.amount) / bpi.amount * 100) as percentage_used
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
             HAVING SUM(e.amount) > (bpi.amount * 0.8)
             ORDER BY percentage_used DESC";

$stmt = $conn->prepare($warningsQuery);
$currentMonth = date('m');
$currentYear = date('Y');
$stmt->bind_param("iii", $userId, $currentMonth, $currentYear);
$stmt->execute();
$warningsResult = $stmt->get_result();

$warnings = [];
while ($row = $warningsResult->fetch_assoc()) {
    $warnings[] = $row;
}

if (!empty($warnings)) {
    echo '<div class="warning-container">';
    echo '<h3>Budget Warnings</h3>';

    foreach ($warnings as $warning) {
        $percentUsed = round($warning['percentage_used']);
        $warningClass = $percentUsed >= 100 ? 'critical-warning' : 'warning-item';
        
        echo '<div class="' . $warningClass . '">';
        echo '<div>';
        echo '<strong>' . $warning['category'] . ':</strong> You\'ve spent ' . number_format($warning['spent_amount'], 2) . ' ';
        echo '(' . $percentUsed . '% of your budget). ';
        
        if ($warning['remaining'] < 0) {
            echo '<span class="exceeded">Exceeded by: ' . number_format(abs($warning['remaining']), 2) . '</span>';
        } else {
            echo 'Remaining: ' . number_format($warning['remaining'], 2);
        }
        
        echo '</div>';
        echo '<a href="plan_budget.php?highlight=' . urlencode($warning['category']) . '" class="warning-action">Adjust Budget</a>';
        echo '</div>';
    }

    echo '</div>';
    
    echo '<style>
        .warning-container {
            margin-bottom: 20px;
            border: 1px solid #ffeeba;
            border-radius: 4px;
            padding: 15px;
            background-color: #fff8e6;
        }
        
        .warning-container h3 {
            margin-top: 0;
            color: #856404;
            margin-bottom: 15px;
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
        
        .critical-warning {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .warning-action {
            background-color: #856404;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            margin-left: 10px;
        }
        
        .critical-warning .warning-action {
            background-color: #721c24;
        }
        
        .exceeded {
            color: #dc3545;
            font-weight: bold;
        }
    </style>';
}

include_once 'check_budget_warnings.php';
?>