<?php
require_once __DIR__ . '/../shared/includes/config.php';
require_once __DIR__ . '/../shared/includes/auth.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION[$portal_settings['session_prefix'] . 'user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Login</title>
    <link rel="stylesheet" href="../shared/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <h1>Admin Portal Login</h1>
        <?php if ($error): ?>
            <div class="error-message">
                <?php
                switch($error) {
                    case 'unauthorized':
                        echo 'You are not authorized to access this portal.';
                        break;
                    case 'invalid':
                        echo 'Invalid username or password.';
                        break;
                    default:
                        echo 'An error occurred. Please try again.';
                }
                ?>
            </div>
        <?php endif; ?>
        
        <form action="process_login.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html> 