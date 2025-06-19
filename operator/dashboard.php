<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_operator_auth();

// Function to safely include files with fallback paths
function safe_require_once($relative_path) {
    $possible_paths = [
        __DIR__ . '/' . $relative_path,
        dirname(__DIR__) . '/' . $relative_path,
        realpath(__DIR__ . '/' . $relative_path),
        $_SERVER['DOCUMENT_ROOT'] . '/' . $relative_path
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_readable($path)) {
            require_once $path;
            return true;
        }
    }
    
    // If no path works, throw an error with detailed information
    $error_msg = "Could not find file: $relative_path\n";
    $error_msg .= "Tried paths:\n";
    foreach ($possible_paths as $path) {
        $error_msg .= "  - $path (exists: " . (file_exists($path) ? 'YES' : 'NO') . ", readable: " . (is_readable($path) ? 'YES' : 'NO') . ")\n";
    }
    $error_msg .= "Current directory: " . __DIR__ . "\n";
    $error_msg .= "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET') . "\n";
    
    throw new Exception($error_msg);
}

// Handle AJAX requests first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Include necessary files for AJAX handling
    safe_require_once('includes/auth.php');
    safe_require_once('includes/db.php');
    safe_require_once('includes/functions/project_functions.php');
    safe_require_once('includes/functions/user_functions.php');
    safe_require_once('includes/functions/system_functions.php');
    safe_require_once('includes/functions/bidder_functions.php');
    
    // Ensure only operators can access this page
    require_operator_auth();
    
    switch ($_POST['action']) {
        case 'test_ajax':
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'AJAX test successful']);
            exit();
            break;
            
        case 'change_user_role':
            if (isset($_POST['user_id'], $_POST['new_role'])) {
                // Debug logging
                error_log("Role change request received: user_id=" . $_POST['user_id'] . ", new_role=" . $_POST['new_role']);
                error_log("AJAX flag: " . (isset($_POST['ajax']) ? 'yes' : 'no'));
                error_log("X-Requested-With header: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));
                
                $result = change_user_role($_POST['user_id'], $_POST['new_role']);
                
                // Debug logging
                error_log("Role change result: " . json_encode($result));
                
                header('Content-Type: application/json');
                echo json_encode($result);
                exit();
            }
            break;
            
        case 'get_project':
            if (isset($_POST['project_id'])) {
                $result = get_project($_POST['project_id']);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit();
            }
            break;
            
        case 'update_project':
            if (isset($_POST['project_id'])) {
                // Parse the form data similar to create_project
                $project_id = $_POST['project_id'];
                
                // Due date
                $due_date = sprintf('%04d-%02d-%02d', 
                    $_POST['due_year'], 
                    $_POST['due_month'], 
                    $_POST['due_day']
                );
                
                // Due time
                $hour = (int)$_POST['due_hour'];
                $minute = (int)$_POST['due_minute'];
                $ampm = $_POST['due_ampm'];
                
                if ($ampm === 'PM' && $hour < 12) $hour += 12;
                if ($ampm === 'AM' && $hour === 12) $hour = 0;
                
                $due_time = sprintf('%02d:%02d:00', $hour, $minute);
                $time_zone = $_POST['time_zone'];
                
                // Assign date
                $assign_date = sprintf('%04d-%02d-%02d', 
                    $_POST['assign_year'], 
                    $_POST['assign_month'], 
                    $_POST['assign_day']
                );
                
                // Text fields
                $title = $_POST['title'];
                $state = $_POST['state'];
                $code = $_POST['code'];
                
                // Nature checkboxes
                $nature_fbo = isset($_POST['nature_fbo']) ? 1 : 0;
                $nature_state = isset($_POST['nature_state']) ? 1 : 0;
                
                // Type checkboxes
                $type_online = isset($_POST['type_online']) ? 1 : 0;
                $type_email = isset($_POST['type_email']) ? 1 : 0;
                $type_sealed = isset($_POST['type_sealed']) ? 1 : 0;
                
                // Status radio buttons
                $status_submitted = 0;
                $status_not_submitted = 0;
                $status_no_result = 0;
                
                switch ($_POST['status']) {
                    case 'submitted': $status_submitted = 1; break;
                    case 'not_submitted': $status_not_submitted = 1; break;
                    case 'no_result': $status_no_result = 1; break;
                }
                
                // Reason checkboxes
                $reason_rfq = isset($_POST['reason_rfq']) ? 1 : 0;
                $reason_rfi = isset($_POST['reason_rfi']) ? 1 : 0;
                $reason_rejection = isset($_POST['reason_rejection']) ? 1 : 0;
                $reason_other = isset($_POST['reason_other']) ? 1 : 0;
                
                // Completed status
                $completed = isset($_POST['completed']) ? (int)$_POST['completed'] : 0;
                
                $project_data = [
                    'due_date' => $due_date,
                    'due_time' => $due_time,
                    'time_zone' => $time_zone,
                    'assign_date' => $assign_date,
                    'title' => $title,
                    'state' => $state,
                    'code' => $code,
                    'nature_fbo' => $nature_fbo,
                    'nature_state' => $nature_state,
                    'type_online' => $type_online,
                    'type_email' => $type_email,
                    'type_sealed' => $type_sealed,
                    'status_submitted' => $status_submitted,
                    'status_not_submitted' => $status_not_submitted,
                    'status_no_result' => $status_no_result,
                    'reason_rfq' => $reason_rfq,
                    'reason_rfi' => $reason_rfi,
                    'reason_rejection' => $reason_rejection,
                    'reason_other' => $reason_other,
                    'completed' => $completed,
                    'file_types' => $_POST['file_types'] ?? []
                ];
                
                $result = update_project($project_id, $project_data, $_FILES);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit();
            }
            break;
            
        case 'create_bidder':
            if (isset($_POST['company_name'], $_POST['contact_person'], $_POST['email'], $_POST['phone'])) {
                $bidder_data = [
                    'company_name' => $_POST['company_name'],
                    'contact_person' => $_POST['contact_person'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone'],
                    'address' => $_POST['address'] ?? '',
                    'website' => $_POST['website'] ?? '',
                    'status' => $_POST['status'] ?? 'active',
                    'notes' => $_POST['notes'] ?? ''
                ];
                
                $result = create_bidder($bidder_data);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit();
            }
            break;
            
        case 'get_bidder':
            if (isset($_POST['bidder_id'])) {
                $result = get_bidder($_POST['bidder_id']);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit();
            }
            break;
            
        case 'update_bidder':
            if (isset($_POST['bidder_id'])) {
                $bidder_data = [
                    'company_name' => $_POST['company_name'],
                    'contact_person' => $_POST['contact_person'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone'],
                    'address' => $_POST['address'] ?? '',
                    'website' => $_POST['website'] ?? '',
                    'status' => $_POST['status'] ?? 'active',
                    'notes' => $_POST['notes'] ?? ''
                ];
                
                $result = update_bidder($_POST['bidder_id'], $bidder_data);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit();
            }
            break;
            
        case 'delete_bidder':
            if (isset($_POST['bidder_id'])) {
                $result = delete_bidder($_POST['bidder_id']);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit();
            }
            break;
            
        case 'delete_project':
            if (isset($_POST['project_id'])) {
                $result = delete_project($_POST['project_id']);
                if ($result) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error deleting project']);
                }
                exit();
            }
            break;
    }
}

// Use safe_require_once to ensure files are found regardless of server environment
safe_require_once('includes/auth.php');
safe_require_once('includes/db.php');
safe_require_once('includes/functions/project_functions.php');
safe_require_once('includes/functions/user_functions.php');
safe_require_once('includes/functions/system_functions.php');
safe_require_once('includes/functions/bidder_functions.php');

$user_id = $_SESSION['user_id'];

// Ensure database connection is available
if (!isset($pdo) || $pdo === null) {
    error_log("Database connection not available in dashboard.php");
    // Try to re-establish connection
    try {
        require_once __DIR__ . '/includes/db.php';
    } catch (Exception $e) {
        error_log("Failed to re-establish database connection: " . $e->getMessage());
    }
}

// Get system stats
try {
    $system_stats = [
        'users' => get_user_stats(),
        'projects' => get_project_stats($_SESSION['user_id']),
        'bidders' => get_bidder_stats($_SESSION['user_id']),
        'recent_activity' => get_recent_activity()
    ];
} catch (Exception $e) {
    error_log("Error fetching system stats: " . $e->getMessage());
    $system_stats = [
        'users' => ['total' => 0, 'admin' => 0, 'operator' => 0],
        'projects' => ['total' => 0, 'active' => 0, 'completed' => 0],
        'bidders' => ['total' => 0, 'active' => 0, 'inactive' => 0],
        'recent_activity' => []
    ];
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                if (isset($_POST['username'], $_POST['password'], $_POST['role'])) {
                    $result = create_user(
                        trim($_POST['username']),
                        trim($_POST['password']),
                        $_POST['role']
                    );
                    
                    if ($result['success']) {
                        $_SESSION['success_message'] = "User created successfully!";
                    } else {
                        $_SESSION['error_message'] = $result['error'] ?? "Error creating user.";
                    }
                }
                break;

            case 'update_settings':
                if (isset($_POST['settings'])) {
                    $result = update_system_settings($_POST['settings']);
                    if ($result['success']) {
                        $_SESSION['success_message'] = "Settings updated successfully!";
                    } else {
                        $_SESSION['error_message'] = $result['error'] ?? "Error updating settings.";
                    }
                }
                break;

            case 'delete_user':
                if (isset($_POST['user_id'])) {
                    $result = delete_user($_POST['user_id']);
                    if ($result['success']) {
                        $_SESSION['success_message'] = "User deleted successfully!";
                    } else {
                        $_SESSION['error_message'] = $result['error'] ?? "Error deleting user.";
                    }
                }
                break;

            case 'edit_user':
                if (isset($_POST['user_id'], $_POST['username'], $_POST['role'], $_POST['is_active'])) {
                    $result = edit_user(
                        $_POST['user_id'],
                        trim($_POST['username']),
                        $_POST['role'],
                        $_POST['is_active']
                    );
                    
                    if ($result['success']) {
                        $_SESSION['success_message'] = "User updated successfully!";
                    } else {
                        $_SESSION['error_message'] = $result['error'] ?? "Error updating user.";
                    }
                }
                break;

            case 'create_project':
                // Due date
                $due_date = sprintf('%04d-%02d-%02d', 
                    $_POST['due_year'], 
                    $_POST['due_month'], 
                    $_POST['due_day']
                );
                
                // Due time
                $hour = (int)$_POST['due_hour'];
                $minute = (int)$_POST['due_minute'];
                $ampm = $_POST['due_ampm'];
                
                if ($ampm === 'PM' && $hour < 12) $hour += 12;
                if ($ampm === 'AM' && $hour === 12) $hour = 0;
                
                $due_time = sprintf('%02d:%02d:00', $hour, $minute);
                $time_zone = $_POST['time_zone'];
                
                // Assign date
                $assign_date = sprintf('%04d-%02d-%02d', 
                    $_POST['assign_year'], 
                    $_POST['assign_month'], 
                    $_POST['assign_day']
                );
                
                // Text fields
                $title = $_POST['title'];
                $state = $_POST['state'];
                $code = $_POST['code'];
                
                // Nature checkboxes
                $nature_fbo = isset($_POST['nature_fbo']) ? 1 : 0;
                $nature_state = isset($_POST['nature_state']) ? 1 : 0;
                
                // Type checkboxes
                $type_online = isset($_POST['type_online']) ? 1 : 0;
                $type_email = isset($_POST['type_email']) ? 1 : 0;
                $type_sealed = isset($_POST['type_sealed']) ? 1 : 0;
                
                // Status radio buttons
                $status_submitted = 0;
                $status_not_submitted = 0;
                $status_no_result = 0;
                
                switch ($_POST['status']) {
                    case 'submitted': $status_submitted = 1; break;
                    case 'not_submitted': $status_not_submitted = 1; break;
                    case 'no_result': $status_no_result = 1; break;
                }
                
                // Reason checkboxes
                $reason_rfq = isset($_POST['reason_rfq']) ? 1 : 0;
                $reason_rfi = isset($_POST['reason_rfi']) ? 1 : 0;
                $reason_rejection = isset($_POST['reason_rejection']) ? 1 : 0;
                $reason_other = isset($_POST['reason_other']) ? 1 : 0;
                
                // Completed status
                $completed = isset($_POST['completed']) ? (int)$_POST['completed'] : 0;
                
                $project_data = [
                    'due_date' => $due_date,
                    'due_time' => $due_time,
                    'time_zone' => $time_zone,
                    'assign_date' => $assign_date,
                    'title' => $title,
                    'state' => $state,
                    'code' => $code,
                    'nature_fbo' => $nature_fbo,
                    'nature_state' => $nature_state,
                    'type_online' => $type_online,
                    'type_email' => $type_email,
                    'type_sealed' => $type_sealed,
                    'status_submitted' => $status_submitted,
                    'status_not_submitted' => $status_not_submitted,
                    'status_no_result' => $status_no_result,
                    'reason_rfq' => $reason_rfq,
                    'reason_rfi' => $reason_rfi,
                    'reason_rejection' => $reason_rejection,
                    'reason_other' => $reason_other,
                    'completed' => $completed,
                    'file_types' => $_POST['file_types'] ?? []
                ];
                
                $result = create_project($project_data, $_FILES);
                if ($result) {
                    $_SESSION['success_message'] = "Project created successfully!";
                } else {
                    $_SESSION['error_message'] = "Error creating project.";
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . ($_POST['tab'] ?? 'overview'));
        exit();
    }
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'projects';

include __DIR__ . '/includes/templates/header.php';

// Create bidders table if it doesn't exist
create_bidders_table();
?>

<div class="dashboard-container">
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($_SESSION['success_message']); ?>', 'success');
            });
        </script>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($_SESSION['error_message']); ?>', 'error');
            });
        </script>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Admin Dashboard Header -->
    <div class="dashboard-header">
        <div class="tabs">
            <!-- Tab Navigation Buttons -->
            <div class="tab-navigation">
                <a href="?tab=projects" class="tab-btn <?= $active_tab === 'projects' ? 'active' : '' ?>">
                    <i class="fas fa-project-diagram"></i> Projects
                </a>
                <a href="?tab=bidders" class="tab-btn <?= $active_tab === 'bidders' ? 'active' : '' ?>">
                    <i class="fas fa-handshake"></i> Bidders
                </a>
            </div>
            
            <!-- Tab Content Container -->
            <div class="tab-content-container">
                <!-- Projects Management Tab -->
                <div class="tab-content <?= $active_tab === 'projects' ? 'active' : '' ?>" id="projects-tab">
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2><i class="fas fa-project-diagram"></i> Projects Management</h2>
                            <button type="button" class="btn-primary" onclick="showModal('add-project-modal')">
                                <i class="fas fa-plus-circle"></i> Add New Project
                            </button>
                        </div>

                        <!-- Projects Stats Cards -->
                        <div class="stats-cards">
                            <div class="stat-card">
                                <i class="fas fa-project-diagram"></i>
                                <div class="stat-info">
                                    <h3>Total Projects</h3>
                                    <p><?= $system_stats['projects']['total'] ?></p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-spinner"></i>
                                <div class="stat-info">
                                    <h3>Active Projects</h3>
                                    <p><?= $system_stats['projects']['active'] ?></p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-check-circle"></i>
                                <div class="stat-info">
                                    <h3>Completed Projects</h3>
                                    <p><?= $system_stats['projects']['completed'] ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Projects List -->
                        <div class="table-card">

                            <!-- Advanced Filter Form -->
                            <div class="filter-section" id="filterSection">
                                <h3><i class="fas fa-filter"></i> Filter & Search</h3>
                                <form class="filter-form" method="GET" action="">
                                    <input type="hidden" name="tab" value="projects">
                                    
                                    <!-- Search Row -->
                                    <div class="search-row">
                                        <div class="filter-group">
                                            <label for="searchInput">Search</label>
                                            <input type="text" id="searchInput" name="search" placeholder="Search projects... (Press Enter to search)" 
                                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                    </div>
                                </div>

                                    <!-- Main Filter Grid -->
                                    <div class="filter-grid">
                                        <!-- Project Information -->
                                        <div class="filter-group">
                                            <label for="codeFilter">Code</label>
                                            <input type="text" id="codeFilter" name="code" placeholder="Filter by code" 
                                       value="<?= htmlspecialchars($_GET['code'] ?? '') ?>">
                </div>

                            <div class="filter-group">
                                <label for="titleFilter">Title</label>
                                <input type="text" id="titleFilter" name="title" placeholder="Filter by title" 
                                       value="<?= htmlspecialchars($_GET['title'] ?? '') ?>">
                    </div>

                            <div class="filter-group">
                                <label for="stateFilter">State</label>
                                <select id="stateFilter" name="state">
                                    <option value="">All States</option>
                                    <option value="AL" <?= ($_GET['state'] ?? '') === 'AL' ? 'selected' : '' ?>>Alabama</option>
                                    <option value="AK" <?= ($_GET['state'] ?? '') === 'AK' ? 'selected' : '' ?>>Alaska</option>
                                    <option value="AZ" <?= ($_GET['state'] ?? '') === 'AZ' ? 'selected' : '' ?>>Arizona</option>
                                    <option value="AR" <?= ($_GET['state'] ?? '') === 'AR' ? 'selected' : '' ?>>Arkansas</option>
                                    <option value="CA" <?= ($_GET['state'] ?? '') === 'CA' ? 'selected' : '' ?>>California</option>
                                    <option value="CO" <?= ($_GET['state'] ?? '') === 'CO' ? 'selected' : '' ?>>Colorado</option>
                                    <option value="CT" <?= ($_GET['state'] ?? '') === 'CT' ? 'selected' : '' ?>>Connecticut</option>
                                    <option value="DE" <?= ($_GET['state'] ?? '') === 'DE' ? 'selected' : '' ?>>Delaware</option>
                                    <option value="FL" <?= ($_GET['state'] ?? '') === 'FL' ? 'selected' : '' ?>>Florida</option>
                                    <option value="GA" <?= ($_GET['state'] ?? '') === 'GA' ? 'selected' : '' ?>>Georgia</option>
                                    <option value="HI" <?= ($_GET['state'] ?? '') === 'HI' ? 'selected' : '' ?>>Hawaii</option>
                                    <option value="ID" <?= ($_GET['state'] ?? '') === 'ID' ? 'selected' : '' ?>>Idaho</option>
                                    <option value="IL" <?= ($_GET['state'] ?? '') === 'IL' ? 'selected' : '' ?>>Illinois</option>
                                    <option value="IN" <?= ($_GET['state'] ?? '') === 'IN' ? 'selected' : '' ?>>Indiana</option>
                                    <option value="IA" <?= ($_GET['state'] ?? '') === 'IA' ? 'selected' : '' ?>>Iowa</option>
                                    <option value="KS" <?= ($_GET['state'] ?? '') === 'KS' ? 'selected' : '' ?>>Kansas</option>
                                    <option value="KY" <?= ($_GET['state'] ?? '') === 'KY' ? 'selected' : '' ?>>Kentucky</option>
                                    <option value="LA" <?= ($_GET['state'] ?? '') === 'LA' ? 'selected' : '' ?>>Louisiana</option>
                                    <option value="ME" <?= ($_GET['state'] ?? '') === 'ME' ? 'selected' : '' ?>>Maine</option>
                                    <option value="MD" <?= ($_GET['state'] ?? '') === 'MD' ? 'selected' : '' ?>>Maryland</option>
                                    <option value="MA" <?= ($_GET['state'] ?? '') === 'MA' ? 'selected' : '' ?>>Massachusetts</option>
                                    <option value="MI" <?= ($_GET['state'] ?? '') === 'MI' ? 'selected' : '' ?>>Michigan</option>
                                    <option value="MN" <?= ($_GET['state'] ?? '') === 'MN' ? 'selected' : '' ?>>Minnesota</option>
                                    <option value="MS" <?= ($_GET['state'] ?? '') === 'MS' ? 'selected' : '' ?>>Mississippi</option>
                                    <option value="MO" <?= ($_GET['state'] ?? '') === 'MO' ? 'selected' : '' ?>>Missouri</option>
                                    <option value="MT" <?= ($_GET['state'] ?? '') === 'MT' ? 'selected' : '' ?>>Montana</option>
                                    <option value="NE" <?= ($_GET['state'] ?? '') === 'NE' ? 'selected' : '' ?>>Nebraska</option>
                                    <option value="NV" <?= ($_GET['state'] ?? '') === 'NV' ? 'selected' : '' ?>>Nevada</option>
                                    <option value="NH" <?= ($_GET['state'] ?? '') === 'NH' ? 'selected' : '' ?>>New Hampshire</option>
                                    <option value="NJ" <?= ($_GET['state'] ?? '') === 'NJ' ? 'selected' : '' ?>>New Jersey</option>
                                    <option value="NM" <?= ($_GET['state'] ?? '') === 'NM' ? 'selected' : '' ?>>New Mexico</option>
                                    <option value="NY" <?= ($_GET['state'] ?? '') === 'NY' ? 'selected' : '' ?>>New York</option>
                                    <option value="NC" <?= ($_GET['state'] ?? '') === 'NC' ? 'selected' : '' ?>>North Carolina</option>
                                    <option value="ND" <?= ($_GET['state'] ?? '') === 'ND' ? 'selected' : '' ?>>North Dakota</option>
                                    <option value="OH" <?= ($_GET['state'] ?? '') === 'OH' ? 'selected' : '' ?>>Ohio</option>
                                    <option value="OK" <?= ($_GET['state'] ?? '') === 'OK' ? 'selected' : '' ?>>Oklahoma</option>
                                    <option value="OR" <?= ($_GET['state'] ?? '') === 'OR' ? 'selected' : '' ?>>Oregon</option>
                                    <option value="PA" <?= ($_GET['state'] ?? '') === 'PA' ? 'selected' : '' ?>>Pennsylvania</option>
                                    <option value="RI" <?= ($_GET['state'] ?? '') === 'RI' ? 'selected' : '' ?>>Rhode Island</option>
                                    <option value="SC" <?= ($_GET['state'] ?? '') === 'SC' ? 'selected' : '' ?>>South Carolina</option>
                                    <option value="SD" <?= ($_GET['state'] ?? '') === 'SD' ? 'selected' : '' ?>>South Dakota</option>
                                    <option value="TN" <?= ($_GET['state'] ?? '') === 'TN' ? 'selected' : '' ?>>Tennessee</option>
                                    <option value="TX" <?= ($_GET['state'] ?? '') === 'TX' ? 'selected' : '' ?>>Texas</option>
                                    <option value="UT" <?= ($_GET['state'] ?? '') === 'UT' ? 'selected' : '' ?>>Utah</option>
                                    <option value="VT" <?= ($_GET['state'] ?? '') === 'VT' ? 'selected' : '' ?>>Vermont</option>
                                    <option value="VA" <?= ($_GET['state'] ?? '') === 'VA' ? 'selected' : '' ?>>Virginia</option>
                                    <option value="WA" <?= ($_GET['state'] ?? '') === 'WA' ? 'selected' : '' ?>>Washington</option>
                                    <option value="WV" <?= ($_GET['state'] ?? '') === 'WV' ? 'selected' : '' ?>>West Virginia</option>
                                    <option value="WI" <?= ($_GET['state'] ?? '') === 'WI' ? 'selected' : '' ?>>Wisconsin</option>
                                    <option value="WY" <?= ($_GET['state'] ?? '') === 'WY' ? 'selected' : '' ?>>Wyoming</option>
                                </select>
                        </div>

                            <!-- Date Filters -->
                            <div class="filter-group">
                                <label for="monthFilter">Month</label>
                                <select id="monthFilter" name="month">
                                    <option value="">All Months</option>
                                    <option value="01" <?= ($_GET['month'] ?? '') === '01' ? 'selected' : '' ?>>January</option>
                                    <option value="02" <?= ($_GET['month'] ?? '') === '02' ? 'selected' : '' ?>>February</option>
                                    <option value="03" <?= ($_GET['month'] ?? '') === '03' ? 'selected' : '' ?>>March</option>
                                    <option value="04" <?= ($_GET['month'] ?? '') === '04' ? 'selected' : '' ?>>April</option>
                                    <option value="05" <?= ($_GET['month'] ?? '') === '05' ? 'selected' : '' ?>>May</option>
                                    <option value="06" <?= ($_GET['month'] ?? '') === '06' ? 'selected' : '' ?>>June</option>
                                    <option value="07" <?= ($_GET['month'] ?? '') === '07' ? 'selected' : '' ?>>July</option>
                                    <option value="08" <?= ($_GET['month'] ?? '') === '08' ? 'selected' : '' ?>>August</option>
                                    <option value="09" <?= ($_GET['month'] ?? '') === '09' ? 'selected' : '' ?>>September</option>
                                    <option value="10" <?= ($_GET['month'] ?? '') === '10' ? 'selected' : '' ?>>October</option>
                                    <option value="11" <?= ($_GET['month'] ?? '') === '11' ? 'selected' : '' ?>>November</option>
                                    <option value="12" <?= ($_GET['month'] ?? '') === '12' ? 'selected' : '' ?>>December</option>
                                </select>
            </div>

                            <div class="filter-group">
                                <label for="dayFilter">Day</label>
                                <select id="dayFilter" name="day">
                                    <option value="">All Days</option>
                                    <?php for($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($_GET['day'] ?? '') == $i ? 'selected' : '' ?>>
                                            <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                    </div>

                            <div class="filter-group">
                                <label for="dateFilter">Date</label>
                                <input type="date" id="dateFilter" name="date" 
                                       value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                            </div>
                                    </div>

                        <!-- Type and Status Filters -->
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Type</label>
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="type_online" value="1" 
                                               <?= isset($_GET['type_online']) ? 'checked' : '' ?>>
                                        Online
                                    </label>
                                    <label>
                                        <input type="checkbox" name="type_email" value="1" 
                                               <?= isset($_GET['type_email']) ? 'checked' : '' ?>>
                                        Email
                                    </label>
                                    <label>
                                        <input type="checkbox" name="type_sealed" value="1" 
                                               <?= isset($_GET['type_sealed']) ? 'checked' : '' ?>>
                                        Sealed
                                    </label>
                                    </div>
                                </div>

                            <div class="filter-group">
                                <label>Status</label>
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="status_submitted" value="1" 
                                               <?= isset($_GET['status_submitted']) ? 'checked' : '' ?>>
                                        Submitted
                                    </label>
                                    <label>
                                        <input type="checkbox" name="status_not_submitted" value="1" 
                                               <?= isset($_GET['status_not_submitted']) ? 'checked' : '' ?>>
                                        Not Submitted
                                    </label>
                    </div>
                </div>
                    </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <button type="button" class="btn-secondary" onclick="clearAllFilters()">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                <div class="table-header">
                    <h3>Projects List</h3>
                    </div>

                    <table class="projects-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>State</th>
                                <th>Code</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Completed</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                // Prepare filters from GET parameters
                                $filters = [];
                                $filter_fields = ['search', 'title', 'code', 'state', 'month', 'date', 'day', 'time', 'type_online', 'type_email', 'type_sealed', 'status_submitted', 'status_not_submitted'];
                                
                                foreach ($filter_fields as $field) {
                                    if (!empty($_GET[$field])) {
                                        $filters[$field] = $_GET[$field];
                                    }
                                }
                                
                                // Show all projects (not just user's projects)
                                $projects = get_projects($filters);
                                if (!empty($projects)):
                                    foreach ($projects as $project):
                            ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($project['id']) ?></td>
                                    <td><?= htmlspecialchars($project['title']) ?></td>
                                    <td><?= htmlspecialchars($project['state']) ?></td>
                                    <td><?= htmlspecialchars($project['code']) ?></td>
                                    <td>
                                        <div class="timestamp">
                                            <i class="far fa-calendar-alt"></i>
                                            <?= date('M j, Y', strtotime($project['due_date'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($project['status_submitted']): ?>
                                            <span class="status submitted">Submitted</span>
                                        <?php elseif ($project['status_not_submitted']): ?>
                                            <span class="status not-submitted">Not Submitted</span>
                                        <?php else: ?>
                                            <span class="status no-result">No Result</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($project['completed']): ?>
                                            <span class="status completed">Completed</span>
                                        <?php else: ?>
                                            <span class="status not-completed">Not Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="type-tags">
                                            <?php if ($project['type_online']): ?>
                                                <span class="tag online">Online</span>
                                            <?php endif; ?>
                                            <?php if ($project['type_email']): ?>
                                                <span class="tag email">Email</span>
                                            <?php endif; ?>
                                            <?php if ($project['type_sealed']): ?>
                                                <span class="tag sealed">Sealed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                                    endforeach;
                                else:
                            ?>
                                <tr>
                                    <td colspan="8" class="no-data">
                                        <i class="fas fa-project-diagram"></i>
                                        <p>No projects found</p>
                                    </td>
                                </tr>
                            <?php
                                endif;
                            } catch (Exception $e) {
                                error_log("Error loading projects: " . $e->getMessage());
                            ?>
                                <tr>
                                    <td colspan="8" class="error">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <p>Error loading projects. Please try again later.</p>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bidders Management Tab -->
    <div class="tab-content <?= $active_tab === 'bidders' ? 'active' : '' ?>" id="bidders-tab">
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-handshake"></i> Bidder Management</h2>
                <button type="button" class="btn-primary" onclick="showModal('add-bidder-modal')">
                    <i class="fas fa-user-plus"></i> Add New Bidder
                </button>
            </div>

            <!-- Bidders Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <i class="fas fa-handshake"></i>
                    <div class="stat-info">
                        <h3>Total Bidders</h3>
                        <p><?= $system_stats['bidders']['total'] ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-check"></i>
                    <div class="stat-info">
                        <h3>Active Bidders</h3>
                        <p><?= $system_stats['bidders']['active'] ?></p>
                    </div>
                </div>
            </div>

            <!-- Bidders List -->
            <div class="table-card">
                <div class="table-header">
                    <h3>Bidder List</h3>
                    <div class="table-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="bidderSearch" placeholder="Search by company, contact, or email...">
                        </div>
                        <select id="bidderStatusFilter" onchange="filterBidders()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button type="button" class="btn-secondary" onclick="clearBidderFilters()" title="Clear Filters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="bidders-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Company Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $bidders = get_all_bidders([], $_SESSION['user_id']);
                                if (!empty($bidders)):
                                    foreach ($bidders as $bidder):
                            ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($bidder['id']) ?></td>
                                    <td>
                                        <div class="company-name">
                                            <i class="fas fa-building"></i>
                                            <?= htmlspecialchars($bidder['company_name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-person">
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($bidder['contact_person']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="email">
                                            <i class="fas fa-envelope"></i>
                                            <a href="mailto:<?= htmlspecialchars($bidder['email']) ?>">
                                                <?= htmlspecialchars($bidder['email']) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="phone">
                                            <i class="fas fa-phone"></i>
                                            <a href="tel:<?= htmlspecialchars($bidder['phone']) ?>">
                                                <?= htmlspecialchars($bidder['phone']) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $bidder['status'] ?>">
                                            <i class="fas fa-circle"></i>
                                            <?= ucfirst(htmlspecialchars($bidder['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="timestamp">
                                            <i class="far fa-calendar-alt"></i>
                                            <?= date('M j, Y', strtotime($bidder['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="editBidder(<?= $bidder['id'] ?>)" title="Edit Bidder">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon delete" onclick="deleteBidder(<?= $bidder['id'] ?>)" title="Delete Bidder">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                                    endforeach;
                                else:
                            ?>
                                <tr>
                                    <td colspan="8" class="no-data">
                                        <i class="fas fa-handshake"></i>
                                        <p>No bidders found. Add your first bidder!</p>
                                    </td>
                                </tr>
                            <?php
                                endif;
                            } catch (Exception $e) {
                                error_log("Error loading bidders: " . $e->getMessage());
                            ?>
                                <tr>
                                    <td colspan="8" class="error">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <p>Error loading bidders. Please try again later.</p>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="add-user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                <span class="close">&times;</span>
            </div>
            <form id="add-user-form" method="POST">
                <input type="hidden" name="action" value="create_user">
                <input type="hidden" name="tab" value="users">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Role</label>
                    <select name="role">
                        <option value="operator">Operator</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-user-plus"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="edit-user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                <span class="close">&times;</span>
            </div>
            <form id="edit-user-form" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="tab" value="users">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" id="edit_username">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Role</label>
                    <select name="role" id="edit_role">
                        <option value="operator">Operator</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="is_active" id="edit_status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- System Settings Modal -->
    <div id="system-settings-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-cog"></i> System Settings</h2>
                <span class="close">&times;</span>
            </div>
            <form id="settings-form" method="POST">
                <input type="hidden" name="action" value="update_settings">
                <div class="form-section">
                    <h3>General Settings</h3>
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Company Name</label>
                        <input type="text" name="settings[company_name]" value="<?= get_setting('company_name') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> System Email</label>
                        <input type="email" name="settings[system_email]" value="<?= get_setting('system_email') ?>">
                    </div>
                </div>
                <div class="form-section">
                    <h3>Security Settings</h3>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password Policy</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="settings[require_strong_password]" value="1" 
                                    <?= get_setting('require_strong_password') ? 'checked' : '' ?>>
                                Require Strong Passwords
                            </label>
                            <label>
                                <input type="checkbox" name="settings[enable_2fa]" value="1"
                                    <?= get_setting('enable_2fa') ? 'checked' : '' ?>>
                                Enable Two-Factor Authentication
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h3>Notification Settings</h3>
                    <div class="form-group">
                        <label><i class="fas fa-bell"></i> Email Notifications</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="settings[notify_new_user]" value="1"
                                    <?= get_setting('notify_new_user') ? 'checked' : '' ?>>
                                New User Registration
                            </label>
                            <label>
                                <input type="checkbox" name="settings[notify_project_updates]" value="1"
                                    <?= get_setting('notify_project_updates') ? 'checked' : '' ?>>
                                Project Updates
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Project Modal -->
    <div id="add-project-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Project</h2>
                <span class="close">&times;</span>
            </div>
            <form id="add-project-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_project">
                <input type="hidden" name="tab" value="projects">
                
                <!-- Due Date -->
                <div class="form-section">
                    <h3>Due Date</h3>
                    <div class="form-group">
                        <label>Due Date</label>
                        <div class="date-inputs">
                            <select name="due_month">
                                <option value="">MM</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="due_day">
                                <option value="">DD</option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="due_year">
                                <option value="">YYYY</option>
                                <?php for ($i = date('Y'); $i <= date('Y') + 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Time -->
                <div class="form-section">
                    <h3>Time</h3>
                    <div class="form-group">
                        <label>Time</label>
                        <div class="time-inputs">
                            <select name="due_hour">
                                <option value="">HH</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="due_minute">
                                <option value="">MM</option>
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                            </select>
                            <select name="due_ampm">
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                            <select name="time_zone">
                                <option value="EST">EST</option>
                                <option value="CST">CST</option>
                                <option value="PST">PST</option>
                                <option value="MST">MST</option>
                                <option value="AKDT">AKDT</option>
                                <option value="Local">Local</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Assign Date -->
                <div class="form-section">
                    <h3>Assign Date</h3>
                    <div class="form-group">
                        <label>Assign Date</label>
                        <div class="date-inputs">
                            <select name="assign_month">
                                <option value="">MM</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="assign_day">
                                <option value="">DD</option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="assign_year">
                                <option value="">YYYY</option>
                                <?php for ($i = date('Y'); $i <= date('Y') + 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Text Fields -->
                <div class="form-section">
                    <h3>Project Information</h3>
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title">
                    </div>
                    
                    <div class="form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state">
                    </div>

                    <div class="form-group">
                        <label for="code">Code</label>
                        <input type="text" id="code" name="code">
                    </div>
                </div>
                
                <!-- Nature Checkboxes -->
                <div class="form-section">
                    <h3>Nature</h3>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="nature_fbo" value="1"> FBO</label>
                        <label><input type="checkbox" name="nature_state" value="1"> State</label>
                    </div>
                </div>

                <!-- Type Checkboxes -->
                <div class="form-section">
                    <h3>Type</h3>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="type_online" value="1"> Online</label>
                        <label><input type="checkbox" name="type_email" value="1"> Email</label>
                        <label><input type="checkbox" name="type_sealed" value="1"> Sealed</label>
                    </div>
                </div>
                
                <!-- Status Radio Buttons -->
                <div class="form-section">
                    <h3>Status</h3>
                    <div class="radio-group">
                        <label><input type="radio" name="status" value="submitted"> Submitted</label>
                        <label><input type="radio" name="status" value="not_submitted"> Not Submitted</label>
                        <label><input type="radio" name="status" value="no_result"> No Result</label>
                    </div>
                </div>

                <!-- Completed Status Radio Buttons -->
                <div class="form-section">
                    <h3>Project Completion</h3>
                    <div class="radio-group">
                        <label><input type="radio" name="completed" value="0"> Not Completed</label>
                        <label><input type="radio" name="completed" value="1"> Completed</label>
                    </div>
                </div>

                <!-- Reason Checkboxes -->
                <div class="form-section">
                    <h3>Reason for No Bidding</h3>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="reason_rfq" value="1"> RFQ</label>
                        <label><input type="checkbox" name="reason_rfi" value="1"> RFI</label>
                        <label><input type="checkbox" name="reason_rejection" value="1"> Rejection</label>
                        <label><input type="checkbox" name="reason_other" value="1"> Other</label>
                    </div>
                </div>
                
                <!-- File Upload -->
                <div class="form-section">
                    <h3>Project Files</h3>
                    <div id="file-upload-container">
                        <div class="file-upload-row">
                            <select name="file_types[]">
                                <option value="RFQ">RFQ</option>
                                <option value="RFI">RFI</option>
                                <option value="Rejection">Rejection</option>
                            </select>
                            <input type="file" name="project_files[]">
                            <button type="button" class="remove-file">Remove</button>
                        </div>
                    </div>
                    <button type="button" id="add-file-btn" class="btn-secondary">
                        <i class="fas fa-plus"></i> Add Another File
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus-circle"></i> Create Project
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div id="edit-project-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Project</h2>
                <span class="close">&times;</span>
            </div>
            <form id="edit-project-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_project">
                <input type="hidden" name="project_id" id="edit_project_id">
                <input type="hidden" name="tab" value="projects">
                
                <!-- Due Date -->
                <div class="form-section">
                    <h3>Due Date</h3>
                    <div class="form-group">
                        <label>Due Date</label>
                        <div class="date-inputs">
                            <select name="due_month" id="edit_due_month">
                                <option value="">MM</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="due_day" id="edit_due_day">
                                <option value="">DD</option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="due_year" id="edit_due_year">
                                <option value="">YYYY</option>
                                <?php for ($i = date('Y'); $i <= date('Y') + 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Time -->
                <div class="form-section">
                    <h3>Time</h3>
                    <div class="form-group">
                        <label>Time</label>
                        <div class="time-inputs">
                            <select name="due_hour" id="edit_due_hour">
                                <option value="">HH</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="due_minute" id="edit_due_minute">
                                <option value="">MM</option>
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                            </select>
                            <select name="due_ampm" id="edit_due_ampm">
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                            <select name="time_zone" id="edit_time_zone">
                                <option value="EST">EST</option>
                                <option value="CST">CST</option>
                                <option value="PST">PST</option>
                                <option value="MST">MST</option>
                                <option value="AKDT">AKDT</option>
                                <option value="Local">Local</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Assign Date -->
                <div class="form-section">
                    <h3>Assign Date</h3>
                    <div class="form-group">
                        <label>Assign Date</label>
                        <div class="date-inputs">
                            <select name="assign_month" id="edit_assign_month">
                                <option value="">MM</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="assign_day" id="edit_assign_day">
                                <option value="">DD</option>
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="assign_year" id="edit_assign_year">
                                <option value="">YYYY</option>
                                <?php for ($i = date('Y'); $i <= date('Y') + 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Text Fields -->
                <div class="form-section">
                    <h3>Project Information</h3>
                    <div class="form-group">
                        <label for="edit_title">Title</label>
                        <input type="text" id="edit_title" name="title">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_state">State</label>
                        <input type="text" id="edit_state" name="state">
                    </div>

                    <div class="form-group">
                        <label for="edit_code">Code</label>
                        <input type="text" id="edit_code" name="code">
                    </div>
                </div>
                
                <!-- Nature Checkboxes -->
                <div class="form-section">
                    <h3>Nature</h3>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="nature_fbo" id="edit_nature_fbo" value="1"> FBO</label>
                        <label><input type="checkbox" name="nature_state" id="edit_nature_state" value="1"> State</label>
                    </div>
                </div>

                <!-- Type Checkboxes -->
                <div class="form-section">
                    <h3>Type</h3>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="type_online" id="edit_type_online" value="1"> Online</label>
                        <label><input type="checkbox" name="type_email" id="edit_type_email" value="1"> Email</label>
                        <label><input type="checkbox" name="type_sealed" id="edit_type_sealed" value="1"> Sealed</label>
                    </div>
                </div>
                
                <!-- Status Radio Buttons -->
                <div class="form-section">
                    <h3>Status</h3>
                    <div class="radio-group">
                        <label><input type="radio" name="status" id="edit_status_submitted" value="submitted"> Submitted</label>
                        <label><input type="radio" name="status" id="edit_status_not_submitted" value="not_submitted"> Not Submitted</label>
                        <label><input type="radio" name="status" id="edit_status_no_result" value="no_result"> No Result</label>
                    </div>
                </div>

                <!-- Completed Status Radio Buttons -->
                <div class="form-section">
                    <h3>Project Completion</h3>
                    <div class="radio-group">
                        <label><input type="radio" name="completed" id="edit_completed" value="0"> Not Completed</label>
                        <label><input type="radio" name="completed" id="edit_completed" value="1"> Completed</label>
                    </div>
                </div>

                <!-- Reason Checkboxes -->
                <div class="form-section">
                    <h3>Reason for No Bidding</h3>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="reason_rfq" id="edit_reason_rfq" value="1"> RFQ</label>
                        <label><input type="checkbox" name="reason_rfi" id="edit_reason_rfi" value="1"> RFI</label>
                        <label><input type="checkbox" name="reason_rejection" id="edit_reason_rejection" value="1"> Rejection</label>
                        <label><input type="checkbox" name="reason_other" id="edit_reason_other" value="1"> Other</label>
                    </div>
                </div>
                
                <!-- Existing Files Display -->
                <div class="form-section">
                    <h3>Existing Files</h3>
                    <div id="existing-files-container">
                        <!-- Existing files will be loaded here -->
                    </div>
                </div>
                
                <!-- New File Upload -->
                <div class="form-section">
                    <h3>Add New Files</h3>
                    <div id="edit-file-upload-container">
                        <div class="file-upload-row">
                            <select name="file_types[]">
                                <option value="RFQ">RFQ</option>
                                <option value="RFI">RFI</option>
                                <option value="Rejection">Rejection</option>
                            </select>
                            <input type="file" name="project_files[]">
                            <button type="button" class="remove-file">Remove</button>
                        </div>
                    </div>
                    <button type="button" id="edit-add-file-btn" class="btn-secondary">
                        <i class="fas fa-plus"></i> Add Another File
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Project
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Bidder Modal -->
    <div id="add-bidder-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Add New Bidder</h2>
                <span class="close">&times;</span>
            </div>
            <form id="add-bidder-form" method="POST">
                <input type="hidden" name="action" value="create_bidder">
                <input type="hidden" name="tab" value="bidders">
                
                <div class="form-section">
                    <h3>Company Information</h3>
                    <div class="form-group">
                        <label for="company_name"><i class="fas fa-building"></i> Company Name</label>
                        <input type="text" id="company_name" name="company_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="website"><i class="fas fa-globe"></i> Website (Optional)</label>
                        <input type="url" id="website" name="website" placeholder="https://example.com">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Contact Information</h3>
                    <div class="form-group">
                        <label for="contact_person"><i class="fas fa-user"></i> Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Address (Optional)</label>
                        <textarea id="address" name="address" rows="3" placeholder="Enter company address"></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Status & Notes</h3>
                    <div class="form-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Additional notes about this bidder"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-user-plus"></i> Add Bidder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Bidder Modal -->
    <div id="edit-bidder-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit Bidder</h2>
                <span class="close">&times;</span>
            </div>
            <form id="edit-bidder-form" method="POST">
                <input type="hidden" name="action" value="update_bidder">
                <input type="hidden" name="bidder_id" id="edit_bidder_id">
                <input type="hidden" name="tab" value="bidders">
                
                <div class="form-section">
                    <h3>Company Information</h3>
                    <div class="form-group">
                        <label for="edit_company_name"><i class="fas fa-building"></i> Company Name</label>
                        <input type="text" id="edit_company_name" name="company_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_website"><i class="fas fa-globe"></i> Website (Optional)</label>
                        <input type="url" id="edit_website" name="website" placeholder="https://example.com">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Contact Information</h3>
                    <div class="form-group">
                        <label for="edit_contact_person"><i class="fas fa-user"></i> Contact Person</label>
                        <input type="text" id="edit_contact_person" name="contact_person">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="edit_email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_phone"><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_address"><i class="fas fa-map-marker-alt"></i> Address (Optional)</label>
                        <textarea id="edit_address" name="address" rows="3" placeholder="Enter company address"></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Status & Notes</h3>
                    <div class="form-group">
                        <label for="edit_status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                        <textarea id="edit_notes" name="notes" rows="3" placeholder="Additional notes about this bidder"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Bidder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer style="text-align: center; padding: 20px 0; background-color: #f8f9fa; border-top: 1px solid #e9ecef; margin-top: 40px;">
    <div class="container">
        <p style="margin: 0; color: #6c757d; font-size: 14px;">&copy; <?= date('Y') ?> Portal System. All rights reserved.</p>
        <p class="version" style="margin: 5px 0 0 0; color: #6c757d; font-size: 12px;">Version 1.0.0</p>
    </div>
</footer>

<?php include __DIR__ . '/includes/templates/footer.php'; ?> 