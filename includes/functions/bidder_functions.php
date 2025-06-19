<?php
// Database connection should already be established by the calling file
// require_once __DIR__ . '/../db.php'; // Removed to prevent conflicts
require_once __DIR__ . '/../auth.php';

function create_bidder($data) {
    global $pdo;
    
    try {
        error_log("Creating bidder with data: " . print_r($data, true));
        
        $stmt = $pdo->prepare("INSERT INTO bidders (company_name, contact_person, email, phone, address, website, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($data['company_name']),
            trim($data['contact_person']),
            trim($data['email']),
            trim($data['phone']),
            trim($data['address'] ?? ''),
            trim($data['website'] ?? ''),
            $data['status'] ?? 'active',
            trim($data['notes'] ?? ''),
            $_SESSION['user_id']
        ]);
        
        $bidder_id = $pdo->lastInsertId();
        error_log("Bidder created successfully with ID: " . $bidder_id);
        
        return ['success' => true, 'id' => $bidder_id];
    } catch (PDOException $e) {
        error_log("Bidder creation error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function get_all_bidders($filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM bidders WHERE 1=1";
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        // Status filter
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        // Order by creation date
        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_all_bidders: " . $e->getMessage());
        throw new Exception("Database error while fetching bidders: " . $e->getMessage());
    }
}

function get_bidder($bidder_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM bidders WHERE id = ?");
        $stmt->execute([$bidder_id]);
        $bidder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bidder) {
            return ['success' => true, 'bidder' => $bidder];
        } else {
            return ['success' => false, 'error' => 'Bidder not found'];
        }
    } catch (PDOException $e) {
        error_log("Database error in get_bidder: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function update_bidder($bidder_id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE bidders SET 
            company_name = ?, contact_person = ?, email = ?, phone = ?, 
            address = ?, website = ?, status = ?, notes = ?, updated_at = NOW()
            WHERE id = ?");
        
        $stmt->execute([
            trim($data['company_name']),
            trim($data['contact_person']),
            trim($data['email']),
            trim($data['phone']),
            trim($data['address'] ?? ''),
            trim($data['website'] ?? ''),
            $data['status'] ?? 'active',
            trim($data['notes'] ?? ''),
            $bidder_id
        ]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Bidder not found or no changes made'];
        }
    } catch (PDOException $e) {
        error_log("Bidder update error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function delete_bidder($bidder_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM bidders WHERE id = ?");
        $stmt->execute([$bidder_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Bidder not found'];
        }
    } catch (PDOException $e) {
        error_log("Bidder deletion error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

// Create bidders table if it doesn't exist
function create_bidders_table() {
    global $pdo;
    
    try {
        error_log("Creating bidders table...");
        
        $sql = "CREATE TABLE IF NOT EXISTS bidders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            address TEXT,
            website VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_company (company_name),
            INDEX idx_email (email)
        )";
        
        $pdo->exec($sql);
        error_log("Bidders table created successfully or already exists");
        return true;
    } catch (PDOException $e) {
        error_log("Error creating bidders table: " . $e->getMessage());
        return false;
    }
} 