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
    <title>Home</title>
    <style>
        body {
            text-align: center;
            font-family: Arial, sans-serif;
            margin-top: 50px;
        }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px; 
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <form action="logout.php" method="POST">
        <input type="submit" value="Log out">
    </form>
</div>

</body>
</html>
