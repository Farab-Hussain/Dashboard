<?php
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function login_user($username, $password) {
    global $pdo;
    
    try {
        // Get user from database
        $stmt = $pdo->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }
        
        // Verify password
        if (!verify_password($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }
        
        // Check domain restriction for operator.alamexusa.com
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        if ($current_domain === 'operator.alamexusa.com') {
            // Only allow admin users to login from this domain
            if ($user['role'] !== 'admin' && $user['role'] !== 'super_admin') {
                return ['success' => false, 'error' => 'Access denied. Only administrators can login from this domain.'];
            }
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred.'];
    }
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_auth() {
    // Make sure session is started with secure settings
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration']) || 
        time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

function is_admin() {
    // Make sure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Debug information
    error_log("Checking admin status:");
    error_log("Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
    error_log("Session data: " . print_r($_SESSION, true));
    
    // Check if role is set and equals 'admin'
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    error_log("Is admin result: " . ($is_admin ? 'true' : 'false'));
    
    return $is_admin;
}

function require_admin_auth() {
    // Make sure session is started with secure settings
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration']) || 
        time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
    
    // Check if user has admin role
    if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
        // Log the unauthorized access attempt
        error_log("Unauthorized access attempt by user ID: " . $_SESSION['user_id'] . " with role: " . ($_SESSION['role'] ?? 'none'));
        
        // Clear session and redirect to login
        session_destroy();
        header('Location: index.php?error=unauthorized');
        exit();
    }
}

?>