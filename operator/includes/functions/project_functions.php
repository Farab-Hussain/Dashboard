<?php
// Database connection should already be established by the calling file
// require_once __DIR__ . '/../db.php'; // Removed to prevent conflicts
require_once __DIR__ . '/../auth.php';

function ensure_completed_column_exists() {
    global $pdo;
    
    try {
        // Check if completed column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'completed'");
        if ($stmt->rowCount() == 0) {
            // Add completed column
            $pdo->exec("ALTER TABLE projects ADD COLUMN completed TINYINT(1) DEFAULT 0 NOT NULL");
            // Update existing projects
            $pdo->exec("UPDATE projects SET completed = 0 WHERE completed IS NULL");
        }
    } catch (PDOException $e) {
        error_log("Error ensuring completed column exists: " . $e->getMessage());
    }
}

function create_project($data, $files) {
    global $pdo;
    
    // Ensure completed column exists
    ensure_completed_column_exists();
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert project
        $sql = "INSERT INTO projects (
            due_date, due_time, time_zone, assign_date,
            title, state, code,
            nature_fbo, nature_state,
            type_online, type_email, type_sealed,
            status_submitted, status_not_submitted, status_no_result,
            reason_rfq, reason_rfi, reason_rejection, reason_other,
            completed, operator_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['due_date'],
            $data['due_time'],
            $data['time_zone'],
            $data['assign_date'],
            $data['title'],
            $data['state'],
            $data['code'],
            $data['nature_fbo'],
            $data['nature_state'],
            $data['type_online'],
            $data['type_email'],
            $data['type_sealed'],
            $data['status_submitted'],
            $data['status_not_submitted'],
            $data['status_no_result'],
            $data['reason_rfq'],
            $data['reason_rfi'],
            $data['reason_rejection'],
            $data['reason_other'],
            $data['completed'],
            $_SESSION['user_id']
        ]);

        $project_id = $pdo->lastInsertId();

        // Handle file uploads
        if (!empty($files['project_files']['name'][0])) {
            $upload_dir = 'upload/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($files['project_files']['tmp_name'] as $key => $tmp_name) {
                if ($files['project_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $files['project_files']['name'][$key];
                    $file_type = isset($data['file_types'][$key]) ? $data['file_types'][$key] : 'Unknown';
                    $file_path = $upload_dir . time() . '_' . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $pdo->prepare("INSERT INTO project_files (project_id, file_path, file_type) VALUES (?, ?, ?)");
                        $stmt->execute([$project_id, $file_path, $file_type]);
                    } else {
                        error_log("Failed to move uploaded file: " . $tmp_name . " to " . $file_path);
                    }
                } else {
                    error_log("File upload error for key $key: " . $files['project_files']['error'][$key]);
                }
            }
        }

        // Commit transaction
        $pdo->commit();
        return true;

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Project creation error: " . $e->getMessage());
        return false;
    }
}

function get_projects($filters = [], $user_id = null) {
    global $pdo;
    
    $sql = "SELECT * FROM projects WHERE 1=1";
    $params = [];

    if ($user_id) {
        $sql .= " AND operator_id = ?";
        $params[] = $user_id;
    }

    // Search filter
    if (!empty($filters['search'])) {
        $sql .= " AND (title LIKE ? OR state LIKE ? OR code LIKE ?)";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }

    // Individual field filters
    if (!empty($filters['title'])) {
        $sql .= " AND title LIKE ?";
        $params[] = "%{$filters['title']}%";
    }

    if (!empty($filters['code'])) {
        $sql .= " AND code LIKE ?";
        $params[] = "%{$filters['code']}%";
    }

    if (!empty($filters['state'])) {
        $sql .= " AND state = ?";
        $params[] = $filters['state'];
    }

    if (!empty($filters['nature'])) {
        // Since we don't have a single nature column, we'll search in nature_fbo and nature_state
        if ($filters['nature'] === 'fbo') {
            $sql .= " AND nature_fbo = 1";
        } elseif ($filters['nature'] === 'state') {
            $sql .= " AND nature_state = 1";
        }
    }

    if (!empty($filters['fbo'])) {
        if ($filters['fbo'] === 'yes') {
            $sql .= " AND nature_fbo = 1";
        } elseif ($filters['fbo'] === 'no') {
            $sql .= " AND nature_fbo = 0";
        }
    }

    // Date filters
    if (!empty($filters['month'])) {
        $sql .= " AND MONTH(due_date) = ?";
        $params[] = intval($filters['month']);
    }

    if (!empty($filters['date'])) {
        $sql .= " AND DATE(due_date) = ?";
        $params[] = $filters['date'];
    }

    if (!empty($filters['day'])) {
        $sql .= " AND DAY(due_date) = ?";
        $params[] = intval($filters['day']);
    }

    if (!empty($filters['time'])) {
        $sql .= " AND TIME(due_time) = ?";
        $params[] = $filters['time'];
    }

    // Type filters
    if (!empty($filters['type_online'])) {
        $sql .= " AND type_online = 1";
    }

    if (!empty($filters['type_email'])) {
        $sql .= " AND type_email = 1";
    }

    if (!empty($filters['type_sealed'])) {
        $sql .= " AND type_sealed = 1";
    }

    // Status filters
    if (!empty($filters['status_submitted']) && empty($filters['status_not_submitted'])) {
        $sql .= " AND status_submitted = 1";
    } elseif (empty($filters['status_submitted']) && !empty($filters['status_not_submitted'])) {
        $sql .= " AND status_not_submitted = 1";
    } elseif (!empty($filters['status_submitted']) && !empty($filters['status_not_submitted'])) {
        $sql .= " AND (status_submitted = 1 OR status_not_submitted = 1)";
    }

    // Order by due date
    $sql .= " ORDER BY due_date ASC, due_time ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in get_projects: " . $e->getMessage());
        error_log("SQL Query: " . $sql);
        error_log("Parameters: " . print_r($params, true));
        throw new Exception("Database error while fetching projects: " . $e->getMessage());
    }
}

function get_project_files($project_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ?");
    $stmt->execute([$project_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_project($project_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($project) {
            // Get project files
            $project['files'] = get_project_files($project_id);
            return ['success' => true, 'project' => $project];
        } else {
            return ['success' => false, 'error' => 'Project not found'];
        }
    } catch (PDOException $e) {
        error_log("Database error in get_project: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function update_project($project_id, $data, $files = null) {
    global $pdo;
    
    // Ensure completed column exists
    ensure_completed_column_exists();
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update project
        $sql = "UPDATE projects SET 
            due_date = ?, due_time = ?, time_zone = ?, assign_date = ?,
            title = ?, state = ?, code = ?,
            nature_fbo = ?, nature_state = ?,
            type_online = ?, type_email = ?, type_sealed = ?,
            status_submitted = ?, status_not_submitted = ?, status_no_result = ?,
            reason_rfq = ?, reason_rfi = ?, reason_rejection = ?, reason_other = ?,
            completed = ?
            WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['due_date'],
            $data['due_time'],
            $data['time_zone'],
            $data['assign_date'],
            $data['title'],
            $data['state'],
            $data['code'],
            $data['nature_fbo'],
            $data['nature_state'],
            $data['type_online'],
            $data['type_email'],
            $data['type_sealed'],
            $data['status_submitted'],
            $data['status_not_submitted'],
            $data['status_no_result'],
            $data['reason_rfq'],
            $data['reason_rfi'],
            $data['reason_rejection'],
            $data['reason_other'],
            $data['completed'],
            $project_id
        ]);

        // Handle new file uploads if provided
        if ($files && !empty($files['project_files']['name'][0])) {
            $upload_dir = 'upload/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($files['project_files']['tmp_name'] as $key => $tmp_name) {
                if ($files['project_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = $files['project_files']['name'][$key];
                    $file_type = isset($data['file_types'][$key]) ? $data['file_types'][$key] : 'Unknown';
                    $file_path = $upload_dir . time() . '_' . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $pdo->prepare("INSERT INTO project_files (project_id, file_path, file_type) VALUES (?, ?, ?)");
                        $stmt->execute([$project_id, $file_path, $file_type]);
                    } else {
                        error_log("Failed to move uploaded file: " . $tmp_name . " to " . $file_path);
                    }
                } else {
                    error_log("File upload error for key $key: " . $files['project_files']['error'][$key]);
                }
            }
        }

        // Commit transaction
        $pdo->commit();
        return ['success' => true];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Project update error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred'];
    }
}

function delete_project($project_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Delete project files
        $stmt = $pdo->prepare("DELETE FROM project_files WHERE project_id = ?");
        $stmt->execute([$project_id]);
        
        // Delete project
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Project deletion error: " . $e->getMessage());
        return false;
    }
}

function get_project_stats($user_id = null) {
    global $pdo;
    
    // Ensure completed column exists
    ensure_completed_column_exists();
    
    try {
        // Check if projects table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'projects'");
        if ($stmt->rowCount() == 0) {
            return [
                'total' => 0,
                'active' => 0,
                'completed' => 0
            ];
        }
        
        // Build WHERE clause for operator filtering
        $where_clause = "";
        $params = [];
        if ($user_id) {
            $where_clause = "WHERE operator_id = ?";
            $params[] = $user_id;
        }
        
        // Get total count
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects " . $where_clause);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get active projects (due date in the future)
        $active_sql = "SELECT COUNT(*) as active FROM projects WHERE due_date >= CURDATE()";
        if ($user_id) {
            $active_sql .= " AND operator_id = ?";
        }
        $stmt = $pdo->prepare($active_sql);
        $stmt->execute($user_id ? [$user_id] : []);
        $active = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
        
        // Get completed projects (based on completed field)
        $completed_sql = "SELECT COUNT(*) as completed FROM projects WHERE completed = 1";
        if ($user_id) {
            $completed_sql .= " AND operator_id = ?";
        }
        $stmt = $pdo->prepare($completed_sql);
        $stmt->execute($user_id ? [$user_id] : []);
        $completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
        
        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed
        ];
    } catch (PDOException $e) {
        error_log("Database error in get_project_stats: " . $e->getMessage());
        return [
            'total' => 0,
            'active' => 0,
            'completed' => 0
        ];
    }
}

// API Handler for AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'delete_project') {
    header('Content-Type: application/json');
    
    if (isset($_POST['project_id'])) {
        $result = delete_project($_POST['project_id']);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting project']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Project ID required']);
    }
    exit();
} 