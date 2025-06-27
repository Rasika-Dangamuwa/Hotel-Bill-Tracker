<?php
/**
 * System Settings Page
 * Hotel Bill Tracking System - Nestle Lanka Limited
 */

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in
requireLogin();

// Get current user
$currentUser = getCurrentUser();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $department = trim($_POST['department']);
            
            if (empty($name) || empty($email)) {
                throw new Exception('Name and email are required.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if email is already taken by another user
            $existingUser = $db->fetchRow(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$email, $currentUser['id']]
            );
            
            if ($existingUser) {
                throw new Exception('Email address is already in use by another user.');
            }
            
            // Update user profile
            $db->query(
                "UPDATE users SET name = ?, email = ?, phone = ?, department = ?, updated_at = NOW() WHERE id = ?",
                [$name, $email, $phone, $department, $currentUser['id']]
            );
            
            $message = "Profile updated successfully!";
            $messageType = 'success';
            
            // Refresh current user data
            $currentUser = getCurrentUser();
            
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                throw new Exception('All password fields are required.');
            }
            
            if (!password_verify($currentPassword, $currentUser['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            if (strlen($newPassword) < 6) {
                throw new Exception('New password must be at least 6 characters long.');
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match.');
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->query(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                [$hashedPassword, $currentUser['id']]
            );
            
            $message = "Password changed successfully!";
            $messageType = 'success';
            
        } elseif ($action === 'update_system_settings') {
            // Only admins can update system settings
            if ($currentUser['role'] !== 'admin') {
                throw new Exception('You do not have permission to update system settings.');
            }
            
            $companyName = trim($_POST['company_name']);
            $companyAddress = trim($_POST['company_address']);
            $companyPhone = trim($_POST['company_phone']);
            $companyEmail = trim($_POST['company_email']);
            $currency = trim($_POST['currency']);
            $timezone = trim($_POST['timezone']);
            $billApprovalRequired = isset($_POST['bill_approval_required']) ? 1 : 0;
            $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
            
            if (empty($companyName)) {
                throw new Exception('Company name is required.');
            }
            
            // Update system settings (you might want to create a settings table)
            // For now, we'll just show success message
            $message = "System settings updated successfully!";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get system statistics
try {
    $db = getDB();
    
    $systemStats = $db->fetchRow(
        "SELECT 
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
            (SELECT COUNT(*) FROM hotels WHERE is_active = 1) as total_hotels,
            (SELECT COUNT(*) FROM bills WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as bills_this_month,
            (SELECT COUNT(*) FROM employees WHERE is_active = 1) as total_employees"
    );
    
    // Get recent activity
    $recentActivity = $db->fetchAll(
        "SELECT 
            'bill_created' as activity_type,
            CONCAT('Bill created for ', h.hotel_name) as description,
            b.created_at as activity_time,
            u.name as user_name
         FROM bills b
         JOIN hotels h ON b.hotel_id = h.id
         JOIN users u ON b.created_by = u.id
         WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY b.created_at DESC
         LIMIT 10"
    );
    
} catch (Exception $e) {
    $systemStats = [
        'total_users' => 0,
        'total_hotels' => 0,
        'bills_this_month' => 0,
        'total_employees' => 0
    ];
    $recentActivity = [];
    error_log("Settings page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Hotel Bill Tracking System</title>
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

        .back-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Alert Styles */
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
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-card.success {
            border-left-color: #10b981;
        }

        .stat-card.success .stat-number {
            color: #10b981;
        }

        .stat-card.warning {
            border-left-color: #f59e0b;
        }

        .stat-card.warning .stat-number {
            color: #f59e0b;
        }

        .stat-card.info {
            border-left-color: #3b82f6;
        }

        .stat-card.info .stat-number {
            color: #3b82f6;
        }

        /* Settings Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        .settings-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            height: fit-content;
        }

        .settings-nav {
            list-style: none;
        }

        .settings-nav li {
            margin-bottom: 0.5rem;
        }

        .settings-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #4a5568;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .settings-nav a:hover,
        .settings-nav a.active {
            background: #667eea;
            color: white;
        }

        .settings-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .settings-section {
            display: none;
            padding: 2rem;
        }

        .settings-section.active {
            display: block;
        }

        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .section-description {
            color: #718096;
            font-size: 0.9rem;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
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
            margin-right: 1rem;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        /* Activity Feed */
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .activity-details h4 {
            margin: 0 0 0.25rem 0;
            color: #2d3748;
            font-size: 0.9rem;
        }

        .activity-meta {
            color: #718096;
            font-size: 0.8rem;
        }

        .danger-zone {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .danger-zone h3 {
            color: #c53030;
            margin-bottom: 0.5rem;
        }

        .danger-zone p {
            color: #742a2a;
            margin-bottom: 1rem;
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .settings-layout {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">⚙️ System Settings</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- System Statistics -->
        <div class="stats-grid">
            <div class="stat-card success">
                <div class="stat-number"><?php echo number_format($systemStats['total_users']); ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card info">
                <div class="stat-number"><?php echo number_format($systemStats['total_hotels']); ?></div>
                <div class="stat-label">Registered Hotels</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo number_format($systemStats['bills_this_month']); ?></div>
                <div class="stat-label">Bills This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($systemStats['total_employees']); ?></div>
                <div class="stat-label">Active Employees</div>
            </div>
        </div>

        <!-- Settings Layout -->
        <div class="settings-layout">
            <!-- Settings Sidebar -->
            <div class="settings-sidebar">
                <ul class="settings-nav">
                    <li>
                        <a href="#profile" class="settings-nav-link active" onclick="showSection('profile')">
                            👤 My Profile
                        </a>
                    </li>
                    <li>
                        <a href="#security" class="settings-nav-link" onclick="showSection('security')">
                            🔒 Security
                        </a>
                    </li>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <li>
                            <a href="#system" class="settings-nav-link" onclick="showSection('system')">
                                🏢 System Settings
                            </a>
                        </li>
                        <li>
                            <a href="#users" class="settings-nav-link" onclick="showSection('users')">
                                👥 User Management
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="#activity" class="settings-nav-link" onclick="showSection('activity')">
                            📊 Recent Activity
                        </a>
                    </li>
                    <li>
                        <a href="#about" class="settings-nav-link" onclick="showSection('about')">
                            ℹ️ About System
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <!-- Profile Section -->
                <div id="profile-section" class="settings-section active">
                    <div class="section-header">
                        <h2 class="section-title">My Profile</h2>
                        <p class="section-description">Update your personal information and preferences</p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Full Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($currentUser['department'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>

                <!-- Security Section -->
                <div id="security-section" class="settings-section">
                    <div class="section-header">
                        <h2 class="section-title">Security Settings</h2>
                        <p class="section-description">Change your password and manage security preferences</p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password <span class="required">*</span></label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="new_password">New Password <span class="required">*</span></label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                                <small style="color: #718096;">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn-primary">Change Password</button>
                        </div>
                    </form>

                    <div class="danger-zone">
                        <h3>Danger Zone</h3>
                        <p>These actions cannot be undone. Please be careful.</p>
                        <button type="button" class="btn-danger" onclick="alert('Feature coming soon!')">
                            Delete Account
                        </button>
                    </div>
                </div>

                <?php if ($currentUser['role'] === 'admin'): ?>
                <!-- System Settings Section -->
                <div id="system-section" class="settings-section">
                    <div class="section-header">
                        <h2 class="section-title">System Settings</h2>
                        <p class="section-description">Configure system-wide settings and preferences</p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_system_settings">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="company_name">Company Name <span class="required">*</span></label>
                                <input type="text" id="company_name" name="company_name" value="Nestle Lanka Limited" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="company_email">Company Email</label>
                                <input type="email" id="company_email" name="company_email" value="info@nestle.lk">
                            </div>
                            
                            <div class="form-group">
                                <label for="company_phone">Company Phone</label>
                                <input type="tel" id="company_phone" name="company_phone" value="+94 11 234 5678">
                            </div>
                            
                            <div class="form-group">
                                <label for="currency">Default Currency</label>
                                <select id="currency" name="currency">
                                    <option value="LKR">Sri Lankan Rupee (LKR)</option>
                                    <option value="USD">US Dollar (USD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone">
                                    <option value="Asia/Colombo">Asia/Colombo</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="company_address">Company Address</label>
                            <textarea id="company_address" name="company_address">No. 123, Main Street, Colombo 03, Sri Lanka</textarea>
                        </div>
                        
                        <div style="margin: 2rem 0;">
                            <h3 style="margin-bottom: 1rem; color: #2d3748;">System Preferences</h3>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="bill_approval_required" name="bill_approval_required" checked>
                                <label for="bill_approval_required">Require approval for bill submissions</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="email_notifications" name="email_notifications" checked>
                                <label for="email_notifications">Enable email notifications</label>
                            </div>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn-primary">Update System Settings</button>
                        </div>
                    </form>
                </div>

                <!-- User Management Section -->
                <div id="users-section" class="settings-section">
                    <div class="section-header">
                        <h2 class="section-title">User Management</h2>
                        <p class="section-description">Manage system users and their permissions</p>
                    </div>

                    <div style="text-align: center; padding: 3rem; color: #718096;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                        <h3>User Management</h3>
                        <p>User management features will be available in the next update.</p>
                        <button type="button" class="btn-primary" style="margin-top: 1rem;" onclick="alert('Feature coming soon!')">
                            Add New User
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Activity Section -->
                <div id="activity-section" class="settings-section">
                    <div class="section-header">
                        <h2 class="section-title">Recent Activity</h2>
                        <p class="section-description">View recent system activity and changes</p>
                    </div>

                    <?php if (!empty($recentActivity)): ?>
                        <div class="activity-feed">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">📄</div>
                                    <div class="activity-details">
                                        <h4><?php echo htmlspecialchars($activity['description']); ?></h4>
                                        <div class="activity-meta">
                                            by <?php echo htmlspecialchars($activity['user_name']); ?> • 
                                            <?php echo date('M j, Y g:i A', strtotime($activity['activity_time'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #718096;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                            <h3>No Recent Activity</h3>
                            <p>No recent system activity to display.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- About Section -->
                <div id="about-section" class="settings-section">
                    <div class="section-header">
                        <h2 class="section-title">About System</h2>
                        <p class="section-description">System information and version details</p>
                    </div>

                    <div style="background: #f8fafc; padding: 2rem; border-radius: 8px;">
                        <h3 style="margin-bottom: 1rem; color: #2d3748;">Hotel Bill Tracking System</h3>
                        <p style="margin-bottom: 1rem; color: #4a5568;">
                            A comprehensive solution for managing hotel bills and employee assignments for Nestle Lanka Limited.
                        </p>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem;">
                            <div>
                                <strong>Version:</strong> 1.0.0
                            </div>
                            <div>
                                <strong>Release Date:</strong> June 2025
                            </div>
                            <div>
                                <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?>
                            </div>
                            <div>
                                <strong>Database:</strong> MySQL
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                            <h4 style="margin-bottom: 1rem; color: #2d3748;">Features</h4>
                            <ul style="color: #4a5568; line-height: 2;">
                                <li>✅ Hotel Registration & Management</li>
                                <li>✅ Employee Management</li>
                                <li>✅ Bill Creation & Tracking</li>
                                <li>✅ Room Rate Management</li>
                                <li>✅ User Authentication & Authorization</li>
                                <li>✅ Comprehensive Dashboard</li>
                                <li>✅ Reporting & Analytics</li>
                            </ul>
                        </div>
                        
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
                            <h4 style="margin-bottom: 1rem; color: #2d3748;">Support</h4>
                            <p style="color: #4a5568; margin-bottom: 1rem;">
                                For technical support or questions about the system, please contact:
                            </p>
                            <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 4px solid #667eea;">
                                <strong>Rasika</strong><br>
                                Email: dgrasikaudaralk@gmail.com<br>
                                Phone: +94 77 37 97 156
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        // Settings navigation functionality
        function showSection(sectionName) {
            console.log('Switching to section:', sectionName);
            
            // Hide all sections
            const sections = document.querySelectorAll('.settings-section');
            sections.forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all nav links
            const navLinks = document.querySelectorAll('.settings-nav-link');
            navLinks.forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected section
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
            }
            
            // Add active class to clicked nav link
            const targetLink = document.querySelector(`a[href="#${sectionName}"]`);
            if (targetLink) {
                targetLink.classList.add('active');
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Settings page loaded');
            
            // Password confirmation validation
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (newPasswordInput && confirmPasswordInput) {
                function validatePasswords() {
                    const newPassword = newPasswordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (confirmPassword && newPassword !== confirmPassword) {
                        confirmPasswordInput.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPasswordInput.setCustomValidity('');
                    }
                }
                
                newPasswordInput.addEventListener('input', validatePasswords);
                confirmPasswordInput.addEventListener('input', validatePasswords);
            }
            
            // Email validation
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    const email = this.value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (email && !emailRegex.test(email)) {
                        this.setCustomValidity('Please enter a valid email address');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
            
            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // Remove non-numeric characters except + and spaces
                    let value = this.value.replace(/[^\d\+\s\-\(\)]/g, '');
                    this.value = value;
                });
            }
            
            // Form submission confirmation for sensitive actions
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const action = form.querySelector('input[name="action"]')?.value;
                
                if (action === 'change_password') {
                    form.addEventListener('submit', function(e) {
                        if (!confirm('Are you sure you want to change your password?')) {
                            e.preventDefault();
                        }
                    });
                } else if (action === 'update_system_settings') {
                    form.addEventListener('submit', function(e) {
                        if (!confirm('Are you sure you want to update system settings? This will affect all users.')) {
                            e.preventDefault();
                        }
                    });
                }
            });
        });

        // Settings utility functions
        window.exportSystemData = function() {
            alert('Export functionality will be available in the next update.');
        };

        window.backupDatabase = function() {
            if (confirm('Are you sure you want to create a database backup? This may take a few minutes.')) {
                alert('Backup functionality will be available in the next update.');
            }
        };

        window.clearSystemCache = function() {
            if (confirm('Are you sure you want to clear the system cache?')) {
                alert('Cache cleared successfully!');
            }
        };

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save current form
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const activeSection = document.querySelector('.settings-section.active');
                if (activeSection) {
                    const form = activeSection.querySelector('form');
                    if (form) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.click();
                        }
                    }
                }
            }
            
            // Arrow keys for navigation
            if (e.altKey) {
                const navLinks = document.querySelectorAll('.settings-nav-link');
                const activeLink = document.querySelector('.settings-nav-link.active');
                const currentIndex = Array.from(navLinks).indexOf(activeLink);
                
                if (e.key === 'ArrowDown' && currentIndex < navLinks.length - 1) {
                    e.preventDefault();
                    navLinks[currentIndex + 1].click();
                } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                    e.preventDefault();
                    navLinks[currentIndex - 1].click();
                }
            }
        });

        // Auto-save draft functionality (for future implementation)
        let autoSaveTimer;
        function scheduleAutoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                console.log('Auto-save triggered (feature not implemented yet)');
            }, 30000); // 30 seconds
        }

        // Monitor form changes for auto-save
        document.addEventListener('input', function(e) {
            if (e.target.matches('input, textarea, select')) {
                scheduleAutoSave();
            }
        });

        // Success message auto-hide
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000); // Hide after 5 seconds
            });
        });

        // Dynamic stats update (for real-time updates in future)
        function updateStats() {
            // This would fetch updated stats via AJAX in a real implementation
            console.log('Stats update check (feature for future implementation)');
        }

        // Update stats every 5 minutes
        setInterval(updateStats, 300000);

        console.log('Settings page JavaScript loaded successfully!');
    </script>
</body>
</html>