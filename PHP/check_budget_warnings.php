<?php
if (!defined('INCLUDED_FROM_SCRIPT')) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.html");
        exit();
    }
    require_once 'config.php';
}

$userId = isset($userId) ? $userId : $_SESSION['user_id'];
$month = isset($month) ? $month : date('m');
$year = isset($year) ? $year : date('Y');

$userQuery = "SELECT email FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$userResult = $stmt->get_result();
$userEmail = '';

if ($userResult->num_rows > 0) {
    $userRow = $userResult->fetch_assoc();
    $userEmail = $userRow['email'];
}

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
                 HAVING SUM(e.amount) > (bpi.amount * 0.8)";

$stmt = $conn->prepare($warningsQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$warningsResult = $stmt->get_result();
$warnings = [];
$criticalWarnings = [];

while ($row = $warningsResult->fetch_assoc()) {
    $warnings[] = $row;
    
    if ($row['percentage_used'] >= 100) {
        $criticalWarnings[] = $row;
    }
}

$checkLogQuery = "SELECT * FROM budget_warning_logs WHERE user_id = ? AND month = ? AND year = ? ORDER BY sent_at DESC LIMIT 1";
$stmt = $conn->prepare($checkLogQuery);
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$logResult = $stmt->get_result();
$lastWarningLog = null;

if ($logResult->num_rows > 0) {
    $lastWarningLog = $logResult->fetch_assoc();
}

$shouldSendEmail = false;

if (!empty($warnings)) {
    if ($lastWarningLog === null) {
        $shouldSendEmail = true;
    } else {
        $previousWarningCount = $lastWarningLog['warning_count'];
        $lastSentTime = strtotime($lastWarningLog['sent_at']);
        $currentTime = time();
        $hoursSinceLastWarning = ($currentTime - $lastSentTime) / 3600;
        
        if (count($warnings) > $previousWarningCount || $hoursSinceLastWarning >= 24) {
            $shouldSendEmail = true;
        }
    }
}

if ($shouldSendEmail && !empty($userEmail)) {
    $subject = "Budget Warning - " . date('F Y', strtotime("$year-$month-01"));
    
    $message = "<html><body>";
    $message .= "<h2>Budget Warning</h2>";
    $message .= "<p>You have " . count($warnings) . " budget categories that are approaching or exceeding their limits for " . date('F Y', strtotime("$year-$month-01")) . ".</p>";
    
    $message .= "<table border='1' cellpadding='5' cellspacing='0'>";
    $message .= "<tr><th>Category</th><th>Budget</th><th>Spent</th><th>Remaining</th><th>% Used</th></tr>";
    
    foreach ($warnings as $warning) {
        $rowClass = $warning['percentage_used'] >= 100 ? "style='background-color: #ffcccc;'" : "";
        $message .= "<tr $rowClass>";
        $message .= "<td>" . $warning['category'] . "</td>";
        $message .= "<td>" . number_format($warning['planned_amount'], 2) . "</td>";
        $message .= "<td>" . number_format($warning['spent_amount'], 2) . "</td>";
        $message .= "<td>" . number_format($warning['remaining'], 2) . "</td>";
        $message .= "<td>" . round($warning['percentage_used']) . "%</td>";
        $message .= "</tr>";
    }
    
    $message .= "</table>";
    $message .= "<p>Please review your spending and adjust your budget if necessary.</p>";
    $message .= "<p><a href='https://yourwebsite.com/plan_budget.php?month=$month&year=$year'>Click here to adjust your budget</a></p>";
    $message .= "</body></html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: DigiSpesti <noreply@digispesti.com>" . "\r\n";
    
    mail($userEmail, $subject, $message, $headers);
    
    $insertLogQuery = "INSERT INTO budget_warning_logs (user_id, month, year, warning_count, sent_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($insertLogQuery);
    $stmt->bind_param("iiii", $userId, $month, $year, count($warnings));
    $stmt->execute();
}

if (!defined('INCLUDED_FROM_SCRIPT')) {
    echo json_encode($warnings);
}
?>