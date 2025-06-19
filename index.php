<?php
// Error reporting at the VERY TOP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Include necessary files
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        $result = login_user($username, $password);
        if ($result['success']) {
            // Redirect based on role - only admins allowed
            if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') {
                header('Location: dashboard.php');
                exit();
            } else {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $error_message = "Access denied. Only administrators can login here.";
            }
        } else {
            $error_message = $result['error'] ?? "Invalid username or password.";
        }
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to admin dashboard if already logged in as admin
    if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin')) {
        header('Location: dashboard.php');
        exit();
    } else {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $error_message = "Access denied. Only administrators can login here.";
    }
}

// Handle error messages from URL parameters
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
        case 'unauthorized_role':
            $error_message = "Access denied. Only administrators can login here.";
            break;
        case 'timeout':
            $error_message = "Session expired. Please login again.";
            break;
        default:
            $error_message = "An error occurred. Please try again.";
            break;
    }
}

// Set content type and security headers
header('Content-Type: text/html; charset=utf-8');
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com;");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Alamex Portal - Login</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Preload Font Awesome -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    
    <!-- Load Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- Load custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    
    <style>
        :root {
            --primary-color: #2196F3;
            --primary-hover: #1976D2;
            --error-color: #f44336;
            --success-color: #4CAF50;
            --border-color: #e0e0e0;
            --text-color: #333;
            --text-muted: #666;
            --background-color: #f5f5f5;
            --card-background: #ffffff;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            background: var(--background-color);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .login-container {
            background: var(--card-background);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px var(--shadow-color);
            width: 100%;
            max-width: 420px;
            margin: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px var(--shadow-color);
        }

        h2 {
            color: var(--text-color);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--card-background);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .form-group i {
            position: absolute;
            right: 1rem;
            top: 2.5rem;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }

        .form-group input:focus + i {
            color: var(--primary-color);
        }

        button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        button[type="submit"]:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .register-link {
            margin-top: 1.5rem;
            text-align: center;
            color: var(--text-muted);
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .error {
            color: var(--error-color);
            background-color: #ffebee;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #ffcdd2;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error i {
            font-size: 1.2rem;
        }

        @media (max-width: 600px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }

        .portal-selection {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
        }
        .portal-cards {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 40px;
        }
        .portal-card {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 300px;
            transition: transform 0.3s ease;
        }
        .portal-card:hover {
            transform: translateY(-5px);
        }
        .portal-card h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .portal-card p {
            color: #666;
            margin-bottom: 25px;
        }
        .portal-card .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        .portal-card .btn:hover {
            background: #2980b9;
        }
        .admin-portal .btn {
            background: #e74c3c;
        }
        .admin-portal .btn:hover {
            background: #c0392b;
        }

        .error-message {
            background-color: #ffebee;
            color: var(--error-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .error-message i {
            font-size: 1.2rem;
        }
        
        .notice-message {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-left: 4px solid #2196F3;
        }
        
        .notice-message i {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Alamex Portal</h2>
        
        <?php 
        // Check if accessing from operator.alamexusa.com
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        if ($current_domain === 'operator.alamexusa.com'): ?>
            <div class="notice-message">
                <i class="fas fa-info-circle"></i>
                <strong>Administrator Access Only:</strong> This portal is restricted to administrators only.
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" autocomplete="off">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       autocomplete="username">
                <i class="fas fa-user"></i>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       autocomplete="current-password">
                <i class="fas fa-lock"></i>
            </div>
            
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </button>
        </form>
    </div>
</body>
</html>