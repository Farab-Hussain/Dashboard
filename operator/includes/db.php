<?php
// Error handling
function handle_db_error($e) {
    error_log("Database Error: " . $e->getMessage());
    error_log("Connection Details: " . print_r([
        'host' => $GLOBALS['host'],
        'dbname' => $GLOBALS['dbname'],
        'username' => $GLOBALS['username']
    ], true));
    
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        throw $e;
    } else {
        die("A database error occurred. Please try again later.");
    }
}

// Include configuration
require_once __DIR__ . '/config.php';

// Get current environment
$environment = $config['environment'] ?? 'production';
$db_config = $config[$environment];

// Database configuration
$host = $db_config['host'];
$dbname = $db_config['dbname'];
$username = $db_config['username'];
$password = $db_config['password'];
$port = $db_config['port'];

// After config is loaded and before DB connection
if (!isset($host, $dbname, $username, $password, $port)) {
    error_log("[DB DEBUG] Missing DB config: " . print_r($db_config, true));
}

// Initialize PDO as null first
$pdo = null;

try {
    // Test connection without database first
    $pdo = new PDO(
        "mysql:host=$host;port=$port",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    error_log("[DB DEBUG] PDO after initial connection: " . print_r($pdo, true));

    // Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    if ($stmt->rowCount() == 0) {
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    // Connect to the specific database
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    error_log("[DB DEBUG] PDO after DB selection: " . print_r($pdo, true));

    // Test the connection
    $stmt = $pdo->query("SELECT 1");
    if (!$stmt) {
        throw new PDOException("Failed to execute test query");
    }

    // Log successful connection
    error_log("Successfully connected to Hostinger database: $dbname");

    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Create users table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            full_name VARCHAR(255),
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        // Check if email column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER username");
        }

        // Check if full_name column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'full_name'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(255) AFTER email");
        }

        // Check if is_active column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER role");
        }

        // Check if last_login column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER is_active");
        }
    }

    // Check if projects table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'projects'");
    if ($stmt->rowCount() == 0) {
        // Create projects table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            due_date DATE NOT NULL,
            due_time TIME NOT NULL,
            time_zone VARCHAR(10) NOT NULL,
            assign_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            state VARCHAR(100) NOT NULL,
            code VARCHAR(100) NOT NULL,
            nature_fbo TINYINT(1) DEFAULT 0,
            nature_state TINYINT(1) DEFAULT 0,
            type_online TINYINT(1) DEFAULT 0,
            type_email TINYINT(1) DEFAULT 0,
            type_sealed TINYINT(1) DEFAULT 0,
            status_submitted TINYINT(1) DEFAULT 0,
            status_not_submitted TINYINT(1) DEFAULT 0,
            status_no_result TINYINT(1) DEFAULT 0,
            reason_rfq TINYINT(1) DEFAULT 0,
            reason_rfi TINYINT(1) DEFAULT 0,
            reason_rejection TINYINT(1) DEFAULT 0,
            reason_other TINYINT(1) DEFAULT 0,
            operator_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Check if project_files table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_files'");
    if ($stmt->rowCount() == 0) {
        // Create project_files table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS project_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    // Ensure PDO is available globally
    $GLOBALS['pdo'] = $pdo;
    
} catch (PDOException $e) {
    error_log("PDO Connection Error: " . $e->getMessage());
    $pdo = null;
    $GLOBALS['pdo'] = null;
    handle_db_error($e);
}

// Helper function for safe queries
function safe_query($sql, $params = []) {
    global $pdo;
    
    if (!isset($pdo) || $pdo === null) {
        error_log("PDO connection not available in safe_query");
        return false;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        handle_db_error($e);
    }
}
?>