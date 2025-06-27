<?php
/**
 * User Management System
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Admin interface for managing account assistant users
 */

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
requireLogin();
requireAdmin();

// Get current user
$currentUser = getCurrentUser();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_user') {
            // Create new user
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            $role = $_POST['role'];
            
            // Validate required fields
            if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
                throw new Exception('All fields are required.');
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check password match
            if ($password !== $confirmPassword) {
                throw new Exception('Passwords do not match.');
            }
            
            // Check password strength
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }
            
            // Check if email already exists
            $existingUser = $db->fetchRow("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existingUser) {
                throw new Exception('Email address already exists.');
            }
            
            // Hash password and create user
            $hashedPassword = hashPassword($password);
            $userId = $db->insert(
                "INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)",
                [$name, $email, $hashedPassword, $role]
            );
            
            // Log activity
            $db->query(
                "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    'users',
                    $userId,
                    'INSERT',
                    json_encode(['name' => $name, 'email' => $email, 'role' => $role]),
                    $currentUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            
            $message = "User created successfully! User ID: $userId";
            $messageType = 'success';
            
        } elseif ($action === 'edit_user') {
            // Edit existing user
            $userId = intval($_POST['user_id']);
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate required fields
            if (empty($name) || empty($email)) {
                throw new Exception('Name and email are required.');
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if email exists for other users
            $existingUser = $db->fetchRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($existingUser) {
                throw new Exception('Email address already exists for another user.');
            }
            
            // Prevent admin from deactivating themselves
            if ($userId == $currentUser['id'] && !$isActive) {
                throw new Exception('You cannot deactivate your own account.');
            }
            
            // Get old values for audit log
            $oldUser = $db->fetchRow("SELECT name, email, role, is_active FROM users WHERE id = ?", [$userId]);
            
            // Update user
            $db->query(
                "UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
                [$name, $email, $role, $isActive, $userId]
            );
            
            // Log activity
            $db->query(
                "INSERT INTO audit_log (table_name, record_id, action, old_values, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    'users',
                    $userId,
                    'UPDATE',
                    json_encode($oldUser),
                    json_encode(['name' => $name, 'email' => $email, 'role' => $role, 'is_active' => $isActive]),
                    $currentUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            
            $message = "User updated successfully!";
            $messageType = 'success';
            
        } elseif ($action === 'reset_password') {
            // Reset user password
            $userId = intval($_POST['user_id']);
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Validate required fields
            if (empty($newPassword) || empty($confirmPassword)) {
                throw new Exception('Both password fields are required.');
            }
            
            // Check password match
            if ($newPassword !== $confirmPassword) {
                throw new Exception('Passwords do not match.');
            }
            
            // Check password strength
            if (strlen($newPassword) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }
            
            // Hash password and update
            $hashedPassword = hashPassword($newPassword);
            $db->query("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hashedPassword, $userId]);
            
            // Log activity
            $db->query(
                "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    'users',
                    $userId,
                    'UPDATE',
                    json_encode(['action' => 'password_reset']),
                    $currentUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            
            $message = "Password reset successfully!";
            $messageType = 'success';
            
        } elseif ($action === 'delete_user') {
            // Delete user (soft delete by deactivating)
            $userId = intval($_POST['user_id']);
            
            // Prevent admin from deleting themselves
            if ($userId == $currentUser['id']) {
                throw new Exception('You cannot delete your own account.');
            }
            
            // Get user info for audit log
            $userToDelete = $db->fetchRow("SELECT name, email FROM users WHERE id = ?", [$userId]);
            
            // Deactivate user instead of deleting
            $db->query("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?", [$userId]);
            
            // Log activity
            $db->query(
                "INSERT INTO audit_log (table_name, record_id, action, old_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    'users',
                    $userId,
                    'DELETE',
                    json_encode($userToDelete),
                    $currentUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            
            $message = "User deactivated successfully!";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get all users
try {
    $db = getDB();
    $users = $db->fetchAll(
        "SELECT id, name, email, role, is_active, created_at, updated_at 
         FROM users 
         ORDER BY created_at DESC"
    );
    
    // Get user statistics
    $userStats = $db->fetchRow(
        "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
            SUM(CASE WHEN role = 'account_assistant' THEN 1 ELSE 0 END) as assistant_users
         FROM users"
    );
    
    // Get recent user activities
    $recentActivities = $db->fetchAll(
        "SELECT a.*, u.name as user_name 
         FROM audit_log a 
         JOIN users u ON a.user_id = u.id 
         WHERE a.table_name = 'users' 
         ORDER BY a.created_at DESC 
         LIMIT 10"
    );
    
} catch (Exception $e) {
    $users = [];
    $userStats = ['total_users' => 0, 'active_users' => 0, 'admin_users' => 0, 'assistant_users' => 0];
    $recentActivities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Hotel Bill Tracking System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .btn-add {
            background: #48bb78;
        }

        .btn-add:hover {
            background: #38a169;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Main Content */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .users-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .users-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .users-table tbody tr:hover {
            background: #f8fafc;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .user-details h4 {
            margin: 0;
            color: #2d3748;
            font-size: 1rem;
        }

        .user-email {
            color: #718096;
            font-size: 0.85rem;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: #fed7d7;
            color: #c53030;
        }

        .role-account_assistant {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-inactive {
            background: #fed7d7;
            color: #c53030;
        }

        .actions {
            display: flex;
            gap: 0.25rem;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #ebf4ff;
            color: #2b6cb0;
        }

        .btn-edit:hover {
            background: #bee3f8;
        }

        .btn-password {
            background: #fef5e7;
            color: #d69e2e;
        }

        .btn-password:hover {
            background: #fed7aa;
        }

        .btn-delete {
            background: #fed7d7;
            color: #c53030;
        }

        .btn-delete:hover {
            background: #feb2b2;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .activities-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-text {
            font-size: 0.9rem;
            color: #4a5568;
        }

        .activity-user {
            font-weight: 600;
            color: #2d3748;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #718096;
            white-space: nowrap;
            margin-left: 1rem;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #2d3748;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #718096;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: #f7fafc;
            color: #2d3748;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .required {
            color: #e53e3e;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .no-users {
            text-align: center;
            color: #718096;
            padding: 3rem;
            font-style: italic;
        }

        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .users-table th,
            .users-table td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .actions {
                flex-direction: column;
                gap: 0.25rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">👥 User Management</h1>
            <div class="header-actions">
                <a href="../dashboard.php" class="btn">← Back to Dashboard</a>
                <button onclick="showCreateUserModal()" class="btn btn-add">+ Add User</button>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $userStats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $userStats['active_users']; ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $userStats['admin_users']; ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $userStats['assistant_users']; ?></div>
                <div class="stat-label">Account Assistants</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-grid">
            <!-- Users List -->
            <div class="users-section">
                <div class="section-header">
                    <h2 class="section-title">👤 System Users</h2>
                    <span style="color: #718096; font-size: 0.9rem;">
                        <?php echo count($users); ?> users total
                    </span>
                </div>

                <?php if (!empty($users)): ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                            </div>
                                            <div class="user-details">
                                                <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'Administrator' : 'Account Assistant'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button onclick="showEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                    class="action-btn btn-edit" title="Edit User">
                                                ✏️ Edit
                                            </button>
                                            <button onclick="showPasswordResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" 
                                                    class="action-btn btn-password" title="Reset Password">
                                                🔑 Reset
                                            </button>
                                            <?php if ($user['id'] != $currentUser['id']): ?>
                                                <button onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" 
                                                        class="action-btn btn-delete" title="Deactivate User">
                                                    🗑️ Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-users">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                        <h3>No Users Found</h3>
                        <p>Click "Add User" to create the first user account.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Recent Activities -->
                <div class="sidebar-section">
                    <h3 class="section-title">📋 Recent Activities</h3>
                    <div class="activities-list">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-text">
                                        <span class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                        <?php
                                        $actionText = '';
                                        switch ($activity['action']) {
                                            case 'INSERT':
                                                $actionText = 'created a new user';
                                                break;
                                            case 'UPDATE':
                                                $values = json_decode($activity['new_values'], true);
                                                if (isset($values['action']) && $values['action'] === 'password_reset') {
                                                    $actionText = 'reset a user password';
                                                } else {
                                                    $actionText = 'updated user details';
                                                }
                                                break;
                                            case 'DELETE':
                                                $actionText = 'deactivated a user';
                                                break;
                                            default:
                                                $actionText = strtolower($activity['action']) . ' user';
                                        }
                                        echo $actionText;
                                        ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php
                                        $time = new DateTime($activity['created_at']);
                                        $now = new DateTime();
                                        $diff = $now->diff($time);
                                        
                                        if ($diff->days > 0) {
                                            echo $diff->days . 'd ago';
                                        } elseif ($diff->h > 0) {
                                            echo $diff->h . 'h ago';
                                        } elseif ($diff->i > 0) {
                                            echo $diff->i . 'm ago';
                                        } else {
                                            echo 'Just now';
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: #718096; padding: 2rem;">
                                No recent activities
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Info -->
                <div class="sidebar-section">
                    <h3 class="section-title">ℹ️ System Information</h3>
                    <div style="font-size: 0.9rem; color: #4a5568; line-height: 1.6;">
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Current Admin:</strong><br>
                            <?php echo htmlspecialchars($currentUser['name']); ?>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Login Time:</strong><br>
                            <?php echo date('M j, Y g:i A', $currentUser['login_time']); ?>
                        </div>
                        <div>
                            <strong>System Version:</strong><br>
                            Hotel Bill Tracking v1.0
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal-overlay" id="createUserModal" onclick="hideModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>👤 Create New User</h3>
                <button type="button" class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="form-group">
                        <label for="create_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="create_name" name="name" required maxlength="100" 
                               placeholder="Enter full name">
                    </div>

                    <div class="form-group">
                        <label for="create_email">Email Address <span class="required">*</span></label>
                        <input type="email" id="create_email" name="email" required maxlength="100" 
                               placeholder="user@nestle.lk">
                    </div>

                    <div class="form-group">
                        <label for="create_password">Password <span class="required">*</span></label>
                        <input type="password" id="create_password" name="password" required minlength="6" 
                               placeholder="Minimum 6 characters">
                    </div>

                    <div class="form-group">
                        <label for="create_confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="create_confirm_password" name="confirm_password" required minlength="6" 
                               placeholder="Re-enter password">
                    </div>

                    <div class="form-group">
                        <label for="create_role">Role <span class="required">*</span></label>
                        <select id="create_role" name="role" required>
                            <option value="account_assistant">Account Assistant</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="hideModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editUserModal" onclick="hideModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>✏️ Edit User</h3>
                <button type="button" class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    
                    <div class="form-group">
                        <label for="edit_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="edit_name" name="name" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="edit_email">Email Address <span class="required">*</span></label>
                        <input type="email" id="edit_email" name="email" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="edit_role">Role <span class="required">*</span></label>
                        <select id="edit_role" name="role" required>
                            <option value="account_assistant">Account Assistant</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="edit_is_active" name="is_active">
                            <label for="edit_is_active">Active User</label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="hideModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div class="modal-overlay" id="passwordResetModal" onclick="hideModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>🔑 Reset Password</h3>
                <button type="button" class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" id="reset_user_id" name="user_id">
                    
                    <p style="margin-bottom: 1rem; color: #4a5568;">
                        Reset password for: <strong id="reset_user_name"></strong>
                    </p>

                    <div class="form-group">
                        <label for="reset_new_password">New Password <span class="required">*</span></label>
                        <input type="password" id="reset_new_password" name="new_password" required minlength="6" 
                               placeholder="Minimum 6 characters">
                    </div>

                    <div class="form-group">
                        <label for="reset_confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="reset_confirm_password" name="confirm_password" required minlength="6" 
                               placeholder="Re-enter new password">
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="hideModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteUserForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" id="delete_user_id" name="user_id">
    </form>

    <script>
        // Show create user modal
        function showCreateUserModal() {
            document.getElementById('createUserModal').classList.add('active');
            document.getElementById('create_name').focus();
        }

        // Show edit user modal
        function showEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            
            document.getElementById('editUserModal').classList.add('active');
            document.getElementById('edit_name').focus();
        }

        // Show password reset modal
        function showPasswordResetModal(userId, userName) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name').textContent = userName;
            document.getElementById('reset_new_password').value = '';
            document.getElementById('reset_confirm_password').value = '';
            
            document.getElementById('passwordResetModal').classList.add('active');
            document.getElementById('reset_new_password').focus();
        }

        // Hide modals
        function hideModal(event) {
            if (!event || event.target.classList.contains('modal-overlay') || event.target.classList.contains('modal-close')) {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.classList.remove('active');
                });
                
                // Clear form data
                document.querySelectorAll('form input').forEach(input => {
                    if (input.type !== 'hidden' && input.type !== 'submit') {
                        input.value = '';
                    }
                });
                document.querySelectorAll('form select').forEach(select => {
                    select.selectedIndex = 0;
                });
                document.querySelectorAll('form input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
        }

        // Confirm delete user
        function confirmDeleteUser(userId, userName) {
            if (confirm(`Are you sure you want to deactivate the user "${userName}"?\n\nThis action will prevent them from logging in, but their data will be preserved.`)) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteUserForm').submit();
            }
        }

        // Password confirmation validation
        document.getElementById('create_confirm_password').addEventListener('input', function() {
            const password = document.getElementById('create_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('reset_confirm_password').addEventListener('input', function() {
            const password = document.getElementById('reset_new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideModal();
            }
        });

        // Auto-generate strong password suggestion
        function generatePassword() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let password = "";
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            return password;
        }

        // Add password generator buttons to forms
        document.addEventListener('DOMContentLoaded', function() {
            // Add generate password button to create form
            const createPasswordGroup = document.getElementById('create_password').parentElement;
            const generateBtn1 = document.createElement('button');
            generateBtn1.type = 'button';
            generateBtn1.textContent = '🎲 Generate Strong Password';
            generateBtn1.style.cssText = 'margin-top: 0.5rem; padding: 0.5rem 1rem; background: #ebf4ff; color: #2b6cb0; border: 1px solid #bee3f8; border-radius: 6px; cursor: pointer; font-size: 0.9rem;';
            generateBtn1.onclick = function() {
                const password = generatePassword();
                document.getElementById('create_password').value = password;
                document.getElementById('create_confirm_password').value = password;
                alert('Strong password generated and filled in both fields!');
            };
            createPasswordGroup.appendChild(generateBtn1);

            // Add generate password button to reset form
            const resetPasswordGroup = document.getElementById('reset_new_password').parentElement;
            const generateBtn2 = document.createElement('button');
            generateBtn2.type = 'button';
            generateBtn2.textContent = '🎲 Generate Strong Password';
            generateBtn2.style.cssText = 'margin-top: 0.5rem; padding: 0.5rem 1rem; background: #ebf4ff; color: #2b6cb0; border: 1px solid #bee3f8; border-radius: 6px; cursor: pointer; font-size: 0.9rem;';
            generateBtn2.onclick = function() {
                const password = generatePassword();
                document.getElementById('reset_new_password').value = password;
                document.getElementById('reset_confirm_password').value = password;
                alert('Strong password generated and filled in both fields!');
            };
            resetPasswordGroup.appendChild(generateBtn2);
        });

        // Form validation before submission
        document.addEventListener('submit', function(e) {
            const form = e.target;
            
            if (form.querySelector('input[name="action"][value="create_user"]')) {
                // Validate create user form
                const password = form.querySelector('input[name="password"]').value;
                const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return;
                }
            }
            
            if (form.querySelector('input[name="action"][value="reset_password"]')) {
                // Validate password reset form
                const password = form.querySelector('input[name="new_password"]').value;
                const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return;
                }
                
                if (!confirm('Are you sure you want to reset this user\'s password?')) {
                    e.preventDefault();
                    return;
                }
            }
        });

        // Real-time email validation
        function validateEmail(input) {
            const email = input.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                input.setCustomValidity('Please enter a valid email address');
            } else {
                input.setCustomValidity('');
            }
        }

        document.getElementById('create_email').addEventListener('input', function() {
            validateEmail(this);
        });

        document.getElementById('edit_email').addEventListener('input', function() {
            validateEmail(this);
        });

        // Animate statistics on load
        window.addEventListener('load', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent);
                stat.textContent = '0';
                
                if (!isNaN(finalValue)) {
                    let current = 0;
                    const increment = Math.max(1, Math.floor(finalValue / 20));
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= finalValue) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            stat.textContent = current;
                        }
                    }, 50);
                }
            });
        });

        // Auto-refresh activities every 30 seconds
        setInterval(function() {
            // You could implement AJAX refresh here if needed
            // For now, just show a subtle indicator that data might be stale
            const activities = document.querySelector('.activities-list');
            if (activities) {
                const lastUpdate = new Date().toLocaleTimeString();
                // Could add a "Last updated: " indicator
            }
        }, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N for new user
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                showCreateUserModal();
            }
        });

        // Search functionality (if you want to add it later)
        function searchUsers(query) {
            const rows = document.querySelectorAll('.users-table tbody tr');
            query = query.toLowerCase();
            
            rows.forEach(row => {
                const name = row.querySelector('.user-details h4').textContent.toLowerCase();
                const email = row.querySelector('.user-email').textContent.toLowerCase();
                
                if (name.includes(query) || email.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Highlight current user row
        document.addEventListener('DOMContentLoaded', function() {
            const currentUserId = <?php echo $currentUser['id']; ?>;
            const userRows = document.querySelectorAll('.users-table tbody tr');
            
            userRows.forEach(row => {
                const editButton = row.querySelector('.btn-edit');
                if (editButton && editButton.onclick) {
                    // This is a simple way to identify the current user's row
                    // You might want to add data attributes for better identification
                    const userEmail = row.querySelector('.user-email').textContent;
                    if (userEmail === '<?php echo htmlspecialchars($currentUser['email']); ?>') {
                        row.style.background = 'linear-gradient(135deg, #ebf4ff, #f8fafc)';
                        row.style.border = '1px solid #bee3f8';
                    }
                }
            });
        });
    </script>
</body>
</html>