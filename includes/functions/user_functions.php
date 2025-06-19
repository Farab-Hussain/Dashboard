<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection should already be established by the calling file
// require_once __DIR__ . '/../db.php'; // Removed to prevent conflicts
require_once __DIR__ . '/../auth.php';

// For AJAX requests, ensure database connection is available
if (isset($_GET['action']) && $_GET['action'] === 'get_user') {
    // If PDO is not available, try to establish connection
    if (!isset($pdo) || $pdo === null) {
        try {
            require_once __DIR__ . '/../db.php';
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
            exit();
        }
    }
}

function create_user($username, $password, $role) {
    global $pdo;
    
    try {
        // Validate username (only alphabetic characters)
        if (!preg_match('/^[A-Za-z]+$/', $username)) {
            return ['success' => false, 'error' => 'Username must contain only alphabetic characters'];
        }
        
        $hashed_password = hash_password($password);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hashed_password, $role]);
        
        return ['success' => true];
    } catch (PDOException $e) {
        error_log("User creation error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Username already exists'];
    }
}

function get_all_users() {
    global $pdo;
    
    try {
        // First verify the table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            error_log("Users table does not exist!");
            throw new Exception("Users table does not exist in the database");
        }

        // Get all users with their details - only selecting columns that exist
        $sql = "SELECT 
                    id,
                    username,
                    role,
                    is_active,
                    created_at
                FROM users 
                ORDER BY created_at DESC";
        
        error_log("Executing SQL query: " . $sql);
        $stmt = $pdo->query($sql);
        
        if ($stmt === false) {
            error_log("Query failed to execute");
            throw new Exception("Failed to execute users query");
        }
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log the retrieved data
        error_log("Number of users retrieved: " . count($users));
        if (count($users) > 0) {
            error_log("First user data: " . print_r($users[0], true));
        } else {
            error_log("No users found in the database");
        }
        
        return $users;
    } catch (PDOException $e) {
        error_log("Database error in get_all_users: " . $e->getMessage());
        error_log("SQL State: " . $e->errorInfo[0]);
        error_log("Error Code: " . $e->errorInfo[1]);
        error_log("Error Message: " . $e->errorInfo[2]);
        throw new Exception("Database error while fetching users: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Error in get_all_users: " . $e->getMessage());
        throw $e;
    }
}

function get_user_stats() {
    global $pdo;
    
    // Ensure PDO connection is available
    if (!isset($pdo) || $pdo === null) {
        error_log("PDO connection is not available in get_user_stats");
        return [
            'total' => 0,
            'admin' => 0,
            'operator' => 0
        ];
    }
    
    try {
        // Get total count
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        if ($stmt === false) {
            error_log("Failed to execute COUNT query in get_user_stats");
            return [
                'total' => 0,
                'admin' => 0,
                'operator' => 0
            ];
        }
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get role counts
        $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        if ($stmt === false) {
            error_log("Failed to execute role count query in get_user_stats");
            return [
                'total' => $total,
                'admin' => 0,
                'operator' => 0
            ];
        }
        $role_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stats = [
            'total' => $total,
            'admin' => 0,
            'operator' => 0
        ];
        
        foreach ($role_counts as $count) {
            $stats[$count['role']] = $count['count'];
        }
        
        // Log the stats
        error_log("User statistics: " . print_r($stats, true));
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Database error in get_user_stats: " . $e->getMessage());
        return [
            'total' => 0,
            'admin' => 0,
            'operator' => 0
        ];
    } catch (Exception $e) {
        error_log("General error in get_user_stats: " . $e->getMessage());
        return [
            'total' => 0,
            'admin' => 0,
            'operator' => 0
        ];
    }
}

function change_user_role($user_id, $new_role) {
    global $pdo;
    
    try {
        // Validate role
        if (!in_array($new_role, ['admin', 'operator'])) {
            return ['success' => false, 'error' => 'Invalid role specified'];
        }
        
        // Don't allow changing your own role
        if ($user_id == $_SESSION['user_id']) {
            return ['success' => false, 'error' => 'Cannot change your own role'];
        }
        
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'User not found'];
        }
    } catch (PDOException $e) {
        error_log("Role change error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function delete_user($user_id) {
    global $pdo;
    
    try {
        // Don't allow deleting the current user
        if ($user_id == $_SESSION['user_id']) {
            return ['success' => false, 'error' => 'Cannot delete your own account'];
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'User not found'];
        }
    } catch (PDOException $e) {
        error_log("User deletion error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function edit_user($user_id, $username, $role, $is_active, $new_password = null) {
    global $pdo;
    
    try {
        // Validate username (only alphabetic characters)
        if (!preg_match('/^[A-Za-z]+$/', $username)) {
            return ['success' => false, 'error' => 'Username must contain only alphabetic characters'];
        }
        
        // If new password is provided, update it
        if (!empty($new_password)) {
            $hashed_password = hash_password($new_password);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$username, $hashed_password, $role, $is_active, $user_id]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$username, $role, $is_active, $user_id]);
        }
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'User not found or no changes made'];
        }
    } catch (PDOException $e) {
        error_log("User edit error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function get_user($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, role, is_active, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            return ['success' => true, 'user' => $user];
        } else {
            return ['success' => false, 'error' => 'User not found'];
        }
    } catch (PDOException $e) {
        error_log("Get user error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function get_user_projects($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE operator_id = ? ORDER BY due_date DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// API Handler for AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_user') {
    header('Content-Type: application/json');
    
    try {
        // Ensure PDO is available
        if (!isset($pdo) || $pdo === null) {
            throw new Exception('Database connection not available');
        }
        
        if (isset($_GET['user_id'])) {
            $result = get_user($_GET['user_id']);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
        }
    } catch (Exception $e) {
        error_log("AJAX get_user error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
} 