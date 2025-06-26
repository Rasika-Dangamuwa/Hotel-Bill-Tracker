<?php
/**
 * Dashboard Page
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Main dashboard for account assistants
 */

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if user is logged in
requireLogin();

// Get current user information
$currentUser = getCurrentUser();
$userName = $currentUser['name'];
$userRole = getUserRoleDisplay();

// Get dashboard statistics
try {
    $db = getDB();
    
    // Get total bills
    $totalBills = $db->fetchValue("SELECT COUNT(*) FROM bills") ?: 0;
    
    // Get total employees
    $totalEmployees = $db->fetchValue("SELECT COUNT(*) FROM employees WHERE is_active = 1") ?: 0;
    
    // Get total hotels
    $totalHotels = $db->fetchValue("SELECT COUNT(*) FROM hotels WHERE is_active = 1") ?: 0;
    
    // Get current month amount
    $currentMonth = date('Y-m');
    $monthlyAmount = $db->fetchValue(
        "SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE DATE_FORMAT(created_at, '%Y-%m') = ?",
        [$currentMonth]
    ) ?: 0;
    
    // Get recent activities (last 10)
    $recentActivities = $db->fetchAll(
        "SELECT table_name, action, new_values, created_at, u.name as user_name 
         FROM audit_log a 
         LEFT JOIN users u ON a.user_id = u.id 
         ORDER BY a.created_at DESC 
         LIMIT 10"
    );
    
} catch (Exception $e) {
    // Set default values if database queries fail
    $totalBills = 0;
    $totalEmployees = 0;
    $totalHotels = 0;
    $monthlyAmount = 0;
    $recentActivities = [];
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hotel Bill Tracking System</title>
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

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Main Content */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: #718096;
            font-size: 1rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .card-hotel { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .card-employee { background: linear-gradient(135deg, #48bb78, #38a169); color: white; }
        .card-bill { background: linear-gradient(135deg, #ed8936, #dd6b20); color: white; }
        .card-view { background: linear-gradient(135deg, #4299e1, #3182ce); color: white; }
        .card-report { background: linear-gradient(135deg, #9f7aea, #805ad5); color: white; }
        .card-settings { background: linear-gradient(135deg, #718096, #4a5568); color: white; }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
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

        /* Recent Activity */
        .recent-activity {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-text {
            font-size: 0.9rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .main-container {
                padding: 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .user-section {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">HT</div>
                <div>
                    <div class="header-title">Hotel Bill Tracking System</div>
                    <div style="font-size: 0.8rem; opacity: 0.9;">Nestle Lanka Limited</div>
                </div>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
                </div>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>!</h1>
            <p class="welcome-subtitle">Manage hotel bills and track promotional crew expenses efficiently.</p>
        </section>

        <!-- Stats Overview -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalBills); ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalEmployees); ?></div>
                <div class="stat-label">Active Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($totalHotels); ?></div>
                <div class="stat-label">Registered Hotels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">LKR <?php echo number_format($monthlyAmount, 2); ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </section>

        <!-- Dashboard Actions -->
        <section class="dashboard-grid">
            <div class="dashboard-card" onclick="navigateTo('hotels/register.php')">
                <div class="card-icon card-hotel">🏨</div>
                <h3 class="card-title">Register Hotel</h3>
                <p class="card-description">Add new hotels to the system and manage hotel information including rates and contact details.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('employees/register.php')">
                <div class="card-icon card-employee">👥</div>
                <h3 class="card-title">Register Employee</h3>
                <p class="card-description">Add new crew members and promotional staff to the employee database.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('bills/add.php')">
                <div class="card-icon card-bill">📋</div>
                <h3 class="card-title">Add New Bill</h3>
                <p class="card-description">Enter hotel bills submitted by propagandists with room assignments and additional charges.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('bills/view.php')">
                <div class="card-icon card-view">👁️</div>
                <h3 class="card-title">View Bills</h3>
                <p class="card-description">Review, search, and manage all hotel bills in the system with detailed filtering options.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('reports/index.php')">
                <div class="card-icon card-report">📊</div>
                <h3 class="card-title">Reports</h3>
                <p class="card-description">Generate detailed reports on expenses, employee stays, and hotel usage patterns.</p>
            </div>

            <div class="dashboard-card" onclick="navigateTo('settings/index.php')">
                <div class="card-icon card-settings">⚙️</div>
                <h3 class="card-title">Settings</h3>
                <p class="card-description">Manage system settings, user accounts, and configure hotel rates.</p>
            </div>
        </section>

        <!-- Recent Activity -->
        <section class="recent-activity">
            <h2 class="section-title">
                <span>📋</span>
                Recent Activity
            </h2>
            <ul class="activity-list">
                <?php if (!empty($recentActivities)): ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <li class="activity-item">
                            <span class="activity-text">
                                <?php
                                $actionText = '';
                                switch ($activity['action']) {
                                    case 'LOGIN':
                                        $actionText = $activity['user_name'] . ' logged in';
                                        break;
                                    case 'INSERT':
                                        $actionText = 'New ' . strtolower($activity['table_name']) . ' record created';
                                        break;
                                    case 'UPDATE':
                                        $actionText = ucfirst($activity['table_name']) . ' record updated';
                                        break;
                                    case 'DELETE':
                                        $actionText = ucfirst($activity['table_name']) . ' record deleted';
                                        break;
                                    default:
                                        $actionText = ucfirst($activity['action']) . ' on ' . $activity['table_name'];
                                }
                                echo htmlspecialchars($actionText);
                                ?>
                            </span>
                            <span class="activity-time">
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
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="activity-item">
                        <span class="activity-text">No recent activities found</span>
                        <span class="activity-time">-</span>
                    </li>
                <?php endif; ?>
            </ul>
        </section>
    </main>

    <script>
        // Navigation function
        function navigateTo(page) {
            window.location.href = page;
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>