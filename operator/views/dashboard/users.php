<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_auth();

// Only admin can access this page
if (!is_admin()) {
    header('Location: dashboard.php');
    exit();
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
    $stmt->execute([$user_id, $_SESSION['user_id']]);
    header('Location: users.php');
    exit();
}

// Handle role change
if (isset($_POST['change_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$new_role, $user_id]);
    header('Location: users.php');
    exit();
}

// Handle new user creation
if (isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    
    // Validate username (minimum 3 letters)
    if (!preg_match('/^[A-Za-z]{3,}$/', $username)) {
        $error = "Username must be at least 3 alphabetic characters";
    } else {
        $hashed_password = hash_password($password);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role]);
            header('Location: users.php');
            exit();
        } catch (PDOException $e) {
            $error = "Username already exists";
        }
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY role, username");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count users by role
$admin_count = 0;
$operator_count = 0;

foreach ($users as $user) {
    if ($user['role'] === 'admin') $admin_count++;
    else $operator_count++;
}

include 'includes/header.php';
?>

<h2>User Management</h2>

<?php if (isset($error)): ?>
    <div class="error"><?= $error ?></div>
<?php endif; ?>

<div class="user-stats">
    <p>Total Users: <?= count($users) ?></p>
    <p>Admins: <?= $admin_count ?></p>
    <p>Operators: <?= $operator_count ?></p>
</div>

<!-- Create User Form -->
<div class="create-user-form">
    <h3>Create New User</h3>
    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" pattern="[A-Za-z]+" title="Only alphabetic characters allowed" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role" required>
                <option value="operator">Operator</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" name="create_user" class="btn-primary">Create User</button>
    </form>
</div>

<!-- Users Table -->
<table class="users-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?= $user['id'] ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td>
                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                    <?= ucfirst($user['role']) ?> (You)
                <?php else: ?>
                    <form method="POST" class="role-form">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <select name="new_role" onchange="this.form.submit()">
                            <option value="operator" <?= $user['role'] === 'operator' ? 'selected' : '' ?>>Operator</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <input type="hidden" name="change_role">
                    </form>
                <?php endif; ?>
            </td>
            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
            <td>
                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <a href="users.php?delete=<?= $user['id'] ?>" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>

<style>
.users-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.users-table th, .users-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.users-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.users-table tr:hover {
    background-color: #f5f5f5;
}

.role-form {
    display: inline;
}

.btn-delete {
    color: #e74c3c;
    text-decoration: none;
    padding: 5px 10px;
    border-radius: 4px;
    border: 1px solid #e74c3c;
}

.btn-delete:hover {
    background-color: #fadbd8;
}

.user-stats {
    display: flex;
    gap: 20px;
    margin: 15px 0;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.create-user-form {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 20px 0;
}
</style>