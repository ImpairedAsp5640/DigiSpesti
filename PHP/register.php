<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $check_query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Username already taken!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Registration failed: " . $stmt->error;
        }
    }

    $stmt->close();
}

$conn->close();

if (isset($success)) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Success - DigiSpesti</title>
        <link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <header class="header">
            <div class="container">
                <div class="logo-container">
                  <img src="image.png" alt="DigiSpesti Logo">
                </div>
                <nav class="nav">
                    <a href="login.html" class="nav-link">Влезте</a>
                </nav>
            </div>
        </header>

        <main class="container">
            <div class="form-container">
                <h1 class="text-center">Успешна регистрация!</h1>
                <p class="text-center">Вашият акаунт е направен успешно.</p>
                <div class="text-center" style="margin-top: 24px;">
                    <a href="login.html" class="btn btn-primary">Влезте сега</a>
                </div>
            </div>
        </main>

        <footer class="container">
            <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
        </footer>
    </body>
    </html>';
} elseif (isset($error)) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Error - WealthWise</title>
        <link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <header class="header">
            <div class="container">
                <div class="logo-container">
                  <img src="image.png" alt="DigiSpesti Logo">
                </div>
                    <span>DigiSpesti</span>
                </div>
                <nav class="nav">
                    <a href="login.html" class="nav-link">Login</a>
                </nav>
            </div>
        </header>

        <main class="container">
            <div class="form-container">
                <h1 class="text-center">Registration Error</h1>
                <p class="text-center" style="color: #e53e3e;">' . $error . '</p>
                <div class="text-center" style="margin-top: 24px;">
                    <a href="register.html" class="btn btn-primary">Try Again</a>
                </div>
            </div>
        </main>

        <footer class="container">
            <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
        </footer>
    </body>
    </html>';
}
?>

