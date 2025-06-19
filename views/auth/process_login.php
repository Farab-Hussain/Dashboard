<?php
require_once __DIR__ . '/../shared/includes/config.php';
require_once __DIR__ . '/../shared/includes/auth.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: login.php?error=invalid');
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;port=$port",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Query user with role check for admin roles only
    $stmt = $pdo->prepare("
        SELECT id, username, email, role, password_hash 
        FROM users 
        WHERE username = ? AND role IN ('admin', 'super_admin')
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Set portal-specific session variables
        $session_prefix = $portal_settings['session_prefix'];
        $_SESSION[$session_prefix . 'user_id'] = $user['id'];
        $_SESSION[$session_prefix . 'username'] = $user['username'];
        $_SESSION[$session_prefix . 'email'] = $user['email'];
        $_SESSION[$session_prefix . 'role'] = $user['role'];
        
        header('Location: dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
}

// If we get here, login failed
header('Location: login.php?error=invalid');
exit();
?> 