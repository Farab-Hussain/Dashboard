<?php
// Database connection should already be established by the calling file
// require_once __DIR__ . '/../db.php'; // Removed to prevent conflicts
require_once __DIR__ . '/../auth.php';

function get_bidder_stats($user_id = null) {
    global $pdo;
    
    try {
        error_log("Getting bidder stats...");
        
        // Check if bidders table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'bidders'");
        if ($stmt->rowCount() == 0) {
            error_log("Bidders table does not exist");
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0
            ];
        }
        
        error_log("Bidders table exists, counting records...");
        
        // Build WHERE clause for operator filtering
        $where_clause = "";
        $params = [];
        if ($user_id) {
            $where_clause = "WHERE created_by = ?";
            $params[] = $user_id;
        }
        
        // Get total count
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bidders " . $where_clause);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("Total bidders: " . $total);
        
        // Get active bidders (using the correct 'status' column)
        $active_sql = "SELECT COUNT(*) as active FROM bidders WHERE status = 'active'";
        if ($user_id) {
            $active_sql .= " AND created_by = ?";
        }
        $stmt = $pdo->prepare($active_sql);
        $stmt->execute($user_id ? [$user_id] : []);
        $active = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
        error_log("Active bidders: " . $active);
        
        // Get inactive bidders
        $inactive_sql = "SELECT COUNT(*) as inactive FROM bidders WHERE status = 'inactive'";
        if ($user_id) {
            $inactive_sql .= " AND created_by = ?";
        }
        $stmt = $pdo->prepare($inactive_sql);
        $stmt->execute($user_id ? [$user_id] : []);
        $inactive = $stmt->fetch(PDO::FETCH_ASSOC)['inactive'];
        error_log("Inactive bidders: " . $inactive);
        
        $stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive
        ];
        
        error_log("Final bidder stats: " . print_r($stats, true));
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Database error in get_bidder_stats: " . $e->getMessage());
        return [
            'total' => 0,
            'active' => 0,
            'inactive' => 0
        ];
    }
}

function get_recent_activity() {
    global $pdo;
    
    try {
        // Check if activity_log table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_log'");
        if ($stmt->rowCount() == 0) {
            return [];
        }
        
        // Get recent activity (last 10 entries)
        $stmt = $pdo->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_recent_activity: " . $e->getMessage());
        return [];
    }
}

function get_system_health_status() {
    global $pdo;
    
    try {
        // Test database connection
        $stmt = $pdo->query("SELECT 1");
        if ($stmt) {
            return "Healthy";
        } else {
            return "Warning";
        }
    } catch (PDOException $e) {
        error_log("System health check failed: " . $e->getMessage());
        return "Error";
    }
}

function update_system_settings($settings) {
    global $pdo;
    
    try {
        // Check if settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            // Create settings table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Update each setting
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                  VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        return ['success' => true];
    } catch (PDOException $e) {
        error_log("Settings update error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function log_activity($user_id, $action, $details = '') {
    global $pdo;
    
    try {
        // Check if activity_log table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_log'");
        if ($stmt->rowCount() == 0) {
            // Create activity_log table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $details]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Activity logging error: " . $e->getMessage());
        return false;
    }
}

function get_setting($key, $default = '') {
    global $pdo;
    
    try {
        // Check if settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() == 0) {
            return $default;
        }
        
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Settings retrieval error: " . $e->getMessage());
        return $default;
    }
}
?> 