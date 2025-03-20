<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT id, password FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            session_start();
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }

    $stmt->close();
}

$conn->close();

// If there was an error, display it
if (isset($error)) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Error - WealthWise</title>
        <link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <header class="header">
            <div class="container">
                <div class="logo">
                    <div class="logo-icon">$</div>
                    <span>WealthWise</span>
                </div>
                <nav class="nav">
                    <a href="register.html" class="nav-link">Register</a>
                </nav>
            </div>
        </header>

        <main class="container">
            <div class="form-container">
                <h1 class="text-center">Login Error</h1>
                <p class="text-center" style="color: #e53e3e;">' . $error . '</p>
                <div class="text-center" style="margin-top: 24px;">
                    <a href="login.html" class="btn btn-primary">Try Again</a>
                </div>
            </div>
        </main>

        <footer class="container">
            <p>&copy; 2025 WealthWise. All rights reserved.</p>
        </footer>
    </body>
    </html>';
}
?>

