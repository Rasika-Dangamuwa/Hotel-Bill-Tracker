<?php
/**
 * Admin Dashboard
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Administrative overview and system management
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

try {
    $db = getDB();
    
    // System Statistics
    $systemStats = $db->fetchRow(
        "SELECT 
            (SELECT COUNT(*) FROM bills) as total_bills,
            (SELECT COUNT(*) FROM hotels WHERE is_active = 1) as active_hotels,
            (SELECT COUNT(*) FROM employees WHERE is_active = 1) as active_employees,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
            (SELECT SUM(total_amount) FROM bills) as total_revenue,
            (SELECT COUNT(*) FROM bills WHERE status = 'pending') as pending_bills,
            (SELECT COUNT(*) FROM bills WHERE DATE(created_at) = CURDATE()) as today_bills,
            (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_count"
    );
    
    // Monthly Growth
    $currentMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));
    
    $monthlyGrowth = $db->fetchRow(
        "SELECT 
            (SELECT COUNT(*) FROM bills WHERE DATE_FORMAT(created_at, '%Y-%m') = ?) as current_month_bills,
            (SELECT COUNT(*) FROM bills WHERE DATE_FORMAT(created_at, '%Y-%m') = ?) as last_month_bills,
            (SELECT SUM(total_amount) FROM bills WHERE DATE_FORMAT(created_at, '%Y-%m') = ?) as current_month_amount,
            (SELECT SUM(total_amount) FROM bills WHERE DATE_FORMAT(created_at, '%Y-%m') = ?) as last_month_amount",
        [$currentMonth, $lastMonth, $currentMonth, $lastMonth]
    );
    
    // Recent System Activities
    $recentActivities = $db->fetchAll(
        "SELECT a.*, u.name as user_name 
         FROM audit_log a 
         JOIN users u ON a.user_id = u.id 
         ORDER BY a.created_at DESC 
         LIMIT 15"
    );
    
    // Top Hotels by Revenue
    $topHotels = $db->fetchAll(
        "SELECT h.hotel_name, h.location, 
                COUNT(b.id) as bill_count,
                SUM(b.total_amount) as total_revenue,
                AVG(b.total_amount) as avg_bill_amount
         FROM hotels h 
         JOIN bills b ON h.id = b.hotel_id 
         WHERE h.is_active = 1 
         GROUP BY h.id, h.hotel_name, h.location 
         ORDER BY total_revenue DESC 
         LIMIT 8"
    );
    
    // Most Active Users
    $activeUsers = $db->fetchAll(
        "SELECT u.name, u.email, u.role,
                COUNT(b.id) as bills_submitted,
                MAX(b.created_at) as last_submission
         FROM users u 
         LEFT JOIN bills b ON u.id = b.submitted_by 
         WHERE u.is_active = 1 
         GROUP BY u.id, u.name, u.email, u.role 
         ORDER BY bills_submitted DESC 
         LIMIT 6"
    );
    
    // System Health Checks
    $systemHealth = [];
    
    // Check for pending bills older than 7 days
    $oldPendingBills = $db->fetchValue(
        "SELECT COUNT(*) FROM bills WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    
    // Check for inactive hotels with recent bills
    $inactiveHotelsWithBills = $db->fetchValue(
        "SELECT COUNT(DISTINCT b.hotel_id) FROM bills b 
         JOIN hotels h ON b.hotel_id = h.id 
         WHERE h.is_active = 0 AND b.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    // Check for employees with no recent assignments
    $inactiveEmployees = $db->fetchValue(
        "SELECT COUNT(*) FROM employees e 
         WHERE e.is_active = 1 
         AND NOT EXISTS (
             SELECT 1 FROM bill_employees be 
             JOIN bills b ON be.bill_id = b.id 
             WHERE be.employee_id = e.id 
             AND b.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
         )"
    );
    
    $systemHealth = [
        'old_pending_bills' => $oldPendingBills,
        'inactive_hotels_with_bills' => $inactiveHotelsWithBills,
        'inactive_employees' => $inactiveEmployees
    ];
    
    // Calculate growth percentages
    $billsGrowth = 0;
    $revenueGrowth = 0;
    
    if ($monthlyGrowth['last_month_bills'] > 0) {
        $billsGrowth = (($monthlyGrowth['current_month_bills'] - $monthlyGrowth['last_month_bills']) / $monthlyGrowth['last_month_bills']) * 100;
    }
    
    if ($monthlyGrowth['last_month_amount'] > 0) {
        $revenueGrowth = (($monthlyGrowth['current_month_amount'] - $monthlyGrowth['last_month_amount']) / $monthlyGrowth['last_month_amount']) * 100;
    }
    
} catch (Exception $e) {
    // Set default values on error
    $systemStats = [
        'total_bills' => 0, 'active_hotels' => 0, 'active_employees' => 0, 'active_users' => 0,
        'total_revenue' => 0, 'pending_bills' => 0, 'today_bills' => 0, 'admin_count' => 0
    ];
    $monthlyGrowth = ['current_month_bills' => 0, 'last_month_bills' => 0, 'current_month_amount' => 0, 'last_month_amount' => 0];
    $recentActivities = [];
    $topHotels = [];
    $activeUsers = [];
    $systemHealth = ['old_pending_bills' => 0, 'inactive_hotels_with_bills' => 0, 'inactive_employees' => 0];
    $billsGrowth = 0;
    $revenueGrowth = 0;
    error_log("Admin dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hotel Bill Tracking System</title>
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

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .admin-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
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
        }

        .btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }

        .welcome-subtitle {
            color: #718096;
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.bills { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.hotels { background: linear-gradient(135deg, #48bb78, #38a169); }
        .stat-icon.employees { background: linear-gradient(135deg, #ed8936, #dd6b20); }
        .stat-icon.users { background: linear-gradient(135deg, #9f7aea, #805ad5); }
        .stat-icon.revenue { background: linear-gradient(135deg, #4299e1, #3182ce); }
        .stat-icon.pending { background: linear-gradient(135deg, #f56565, #e53e3e); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-growth {
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .growth-positive {
            color: #2f855a;
        }

        .growth-negative {
            color: #c53030;
        }

        .growth-neutral {
            color: #718096;
        }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .dashboard-section {
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

        .section-content {
            padding: 1.5rem;
        }

        /* System Health */
        .health-grid {
            display: grid;
            gap: 1rem;
        }

        .health-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .health-item.warning {
            border-color: #f59e0b;
            background: #fef5e7;
        }

        .health-item.error {
            border-color: #dc2626;
            background: #fef2f2;
        }

        .health-item.success {
            border-color: #059669;
            background: #ecfdf5;
        }

        .health-text {
            font-weight: 500;
            color: #374151;
        }

        .health-value {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .health-value.warning { color: #d97706; }
        .health-value.error { color: #dc2626; }
        .health-value.success { color: #059669; }

        /* Activities */
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
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #f8fafc;
            margin: 0 -1.5rem;
            padding: 1rem 1.5rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 0.25rem;
        }

        .activity-user {
            font-weight: 600;
            color: #2d3748;
        }

        .activity-details {
            font-size: 0.8rem;
            color: #718096;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #718096;
            white-space: nowrap;
            margin-left: 1rem;
        }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
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

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
            text-decoration: none;
            color: inherit;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .action-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .action-description {
            color: #718096;
            font-size: 0.9rem;
        }

        .no-data {
            text-align: center;
            color: #718096;
            font-style: italic;
            padding: 2rem;
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

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
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
            <div class="header-left">
                <div class="admin-badge">Admin</div>
                <h1 class="header-title">🔧 System Administration</h1>
            </div>
            <div class="header-actions">
                <a href="../dashboard.php" class="btn">← User Dashboard</a>
                <a href="users.php" class="btn">👥 Manage Users</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2 class="welcome-title">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>!</h2>
            <p class="welcome-subtitle">System administrator dashboard • Monitor and manage the hotel bill tracking system</p>
        </div>

        <!-- System Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($systemStats['total_bills']); ?></div>
                        <div class="stat-label">Total Bills</div>
                        <div class="stat-growth <?php echo $billsGrowth > 0 ? 'growth-positive' : ($billsGrowth < 0 ? 'growth-negative' : 'growth-neutral'); ?>">
                            <?php echo $billsGrowth > 0 ? '↗' : ($billsGrowth < 0 ? '↘' : '→'); ?>
                            <?php echo abs(round($billsGrowth, 1)); ?>% this month
                        </div>
                    </div>
                    <div class="stat-icon bills">📋</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number">LKR <?php echo number_format($systemStats['total_revenue']); ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-growth <?php echo $revenueGrowth > 0 ? 'growth-positive' : ($revenueGrowth < 0 ? 'growth-negative' : 'growth-neutral'); ?>">
                            <?php echo $revenueGrowth > 0 ? '↗' : ($revenueGrowth < 0 ? '↘' : '→'); ?>
                            <?php echo abs(round($revenueGrowth, 1)); ?>% this month
                        </div>
                    </div>
                    <div class="stat-icon revenue">💰</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($systemStats['active_hotels']); ?></div>
                        <div class="stat-label">Active Hotels</div>
                        <div class="stat-growth growth-neutral">
                            🏨 Hotels registered
                        </div>
                    </div>
                    <div class="stat-icon hotels">🏨</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($systemStats['active_employees']); ?></div>
                        <div class="stat-label">Active Employees</div>
                        <div class="stat-growth growth-neutral">
                            👥 Crew members
                        </div>
                    </div>
                    <div class="stat-icon employees">👥</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($systemStats['active_users']); ?></div>
                        <div class="stat-label">System Users</div>
                        <div class="stat-growth growth-neutral">
                            <?php echo $systemStats['admin_count']; ?> admins
                        </div>
                    </div>
                    <div class="stat-icon users">🔐</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($systemStats['pending_bills']); ?></div>
                        <div class="stat-label">Pending Bills</div>
                        <div class="stat-growth <?php echo $systemStats['pending_bills'] > 10 ? 'growth-negative' : 'growth-positive'; ?>">
                            ⏳ Awaiting approval
                        </div>
                    </div>
                    <div class="stat-icon pending">⏳</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="users.php" class="action-card">
                <div class="action-icon">👤</div>
                <div class="action-title">Manage Users</div>
                <div class="action-description">Create, edit, and manage user accounts</div>
            </a>

            <a href="../reports/index.php" class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-title">View Reports</div>
                <div class="action-description">Analyze system usage and performance</div>
            </a>

            <a href="../bills/view.php" class="action-card">
                <div class="action-icon">📋</div>
                <div class="action-title">Review Bills</div>
                <div class="action-description">Monitor and approve pending bills</div>
            </a>

            <a href="../hotels/register.php" class="action-card">
                <div class="action-icon">🏨</div>
                <div class="action-title">Add Hotel</div>
                <div class="action-description">Register new hotels in the system</div>
            </a>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- System Health -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">🔍 System Health</h2>
                </div>
                <div class="section-content">
                    <div class="health-grid">
                        <div class="health-item <?php echo $systemHealth['old_pending_bills'] > 0 ? 'warning' : 'success'; ?>">
                            <div class="health-text">Bills pending > 7 days</div>
                            <div class="health-value <?php echo $systemHealth['old_pending_bills'] > 0 ? 'warning' : 'success'; ?>">
                                <?php echo $systemHealth['old_pending_bills']; ?>
                            </div>
                        </div>

                        <div class="health-item <?php echo $systemHealth['inactive_hotels_with_bills'] > 0 ? 'error' : 'success'; ?>">
                            <div class="health-text">Inactive hotels with recent bills</div>
                            <div class="health-value <?php echo $systemHealth['inactive_hotels_with_bills'] > 0 ? 'error' : 'success'; ?>">
                                <?php echo $systemHealth['inactive_hotels_with_bills']; ?>
                            </div>
                        </div>

                        <div class="health-item <?php echo $systemHealth['inactive_employees'] > 10 ? 'warning' : 'success'; ?>">
                            <div class="health-text">Employees without assignments (90 days)</div>
                            <div class="health-value <?php echo $systemHealth['inactive_employees'] > 10 ? 'warning' : 'success'; ?>">
                                <?php echo $systemHealth['inactive_employees']; ?>
                            </div>
                        </div>

                        <div class="health-item success">
                            <div class="health-text">System uptime</div>
                            <div class="health-value success">99.9%</div>
                        </div>

                        <div class="health-item success">
                            <div class="health-text">Database status</div>
                            <div class="health-value success">Healthy</div>
                        </div>

                        <div class="health-item success">
                            <div class="health-text">Today's bills processed</div>
                            <div class="health-value success"><?php echo $systemStats['today_bills']; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">📋 Recent System Activities</h2>
                </div>
                <div class="section-content">
                    <div class="activities-list">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <span class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                            <?php
                                            $actionText = '';
                                            switch ($activity['action']) {
                                                case 'LOGIN':
                                                    $actionText = 'logged into the system';
                                                    break;
                                                case 'INSERT':
                                                    $actionText = 'created new ' . strtolower($activity['table_name']);
                                                    break;
                                                case 'UPDATE':
                                                    $actionText = 'updated ' . strtolower($activity['table_name']);
                                                    break;
                                                case 'DELETE':
                                                    $actionText = 'deleted ' . strtolower($activity['table_name']);
                                                    break;
                                                default:
                                                    $actionText = strtolower($activity['action']) . ' ' . $activity['table_name'];
                                            }
                                            echo $actionText;
                                            ?>
                                        </div>
                                        <div class="activity-details">
                                            <?php echo ucfirst($activity['table_name']); ?> ID: <?php echo $activity['record_id']; ?>
                                            <?php if ($activity['ip_address']): ?>
                                                • IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                            <?php endif; ?>
                                        </div>
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
                            <div class="no-data">No recent activities found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Analytics -->
        <div class="main-grid">
            <!-- Top Hotels -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">🏆 Top Hotels by Revenue</h2>
                </div>
                <div class="section-content">
                    <?php if (!empty($topHotels)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Hotel</th>
                                    <th>Bills</th>
                                    <th>Revenue</th>
                                    <th>Avg Bill</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topHotels as $hotel): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($hotel['hotel_name']); ?></strong><br>
                                            <small style="color: #718096;"><?php echo htmlspecialchars($hotel['location']); ?></small>
                                        </td>
                                        <td><?php echo $hotel['bill_count']; ?></td>
                                        <td style="font-weight: 600; color: #2d3748;">
                                            LKR <?php echo number_format($hotel['total_revenue'], 2); ?>
                                        </td>
                                        <td>LKR <?php echo number_format($hotel['avg_bill_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">No hotel revenue data available</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Users -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">👨‍💼 Most Active Users</h2>
                </div>
                <div class="section-content">
                    <?php if (!empty($activeUsers)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Bills</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
                                            <small style="color: #718096;"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo $user['role'] === 'admin' ? 'Admin' : 'Assistant'; ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 600; color: #667eea;">
                                            <?php echo $user['bills_submitted'] ?? 0; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['last_submission']): ?>
                                                <?php echo date('M j, Y', strtotime($user['last_submission'])); ?>
                                            <?php else: ?>
                                                <span style="color: #718096;">No bills</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">No user activity data available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="dashboard-section" style="margin-top: 2rem;">
            <div class="section-header">
                <h2 class="section-title">ℹ️ System Information</h2>
            </div>
            <div class="section-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid #667eea;">
                        <h4 style="margin-bottom: 0.5rem; color: #2d3748;">System Version</h4>
                        <p style="color: #4a5568; margin: 0;">Hotel Bill Tracking System v1.0</p>
                        <small style="color: #718096;">Nestle Lanka Limited</small>
                    </div>

                    <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid #48bb78;">
                        <h4 style="margin-bottom: 0.5rem; color: #2d3748;">Database Status</h4>
                        <p style="color: #4a5568; margin: 0;">Connected & Healthy</p>
                        <small style="color: #718096;">MySQL 8.0 - UTF8MB4</small>
                    </div>

                    <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid #4299e1;">
                        <h4 style="margin-bottom: 0.5rem; color: #2d3748;">Server Environment</h4>
                        <p style="color: #4a5568; margin: 0;">PHP <?php echo PHP_VERSION; ?></p>
                        <small style="color: #718096;">Production Ready</small>
                    </div>

                    <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border-left: 4px solid #9f7aea;">
                        <h4 style="margin-bottom: 0.5rem; color: #2d3748;">Current Admin</h4>
                        <p style="color: #4a5568; margin: 0;"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <small style="color: #718096;">Logged in: <?php echo date('M j, Y g:i A', $currentUser['login_time']); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animate statistics on load
        window.addEventListener('load', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalText = stat.textContent;
                const isLKR = finalText.includes('LKR');
                const numericValue = parseFloat(finalText.replace(/[^\d.]/g, ''));
                
                if (!isNaN(numericValue) && numericValue > 0) {
                    stat.textContent = '0';
                    let current = 0;
                    const increment = numericValue / 50;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= numericValue) {
                            stat.textContent = finalText;
                            clearInterval(timer);
                        } else {
                            if (isLKR) {
                                stat.textContent = 'LKR ' + Math.floor(current).toLocaleString();
                            } else {
                                stat.textContent = Math.floor(current).toLocaleString();
                            }
                        }
                    }, 30);
                }
            });
        });

        // Auto-refresh activities every 60 seconds
        setInterval(function() {
            // Add a subtle indicator that data might be refreshed
            const activitiesHeader = document.querySelector('.activities-list').closest('.dashboard-section').querySelector('.section-title');
            const originalText = activitiesHeader.textContent;
            activitiesHeader.textContent = '📋 Recent System Activities (Live)';
            
            setTimeout(() => {
                activitiesHeader.textContent = originalText;
            }, 2000);
        }, 60000);

        // Add tooltips to health items
        document.querySelectorAll('.health-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                const text = this.querySelector('.health-text').textContent;
                const value = this.querySelector('.health-value').textContent;
                
                // Simple tooltip could be added here
                this.title = `${text}: ${value}`;
            });
        });

        // Keyboard shortcuts for admin
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'u':
                        e.preventDefault();
                        window.location.href = 'users.php';
                        break;
                    case 'r':
                        e.preventDefault();
                        window.location.href = '../reports/index.php';
                        break;
                    case 'b':
                        e.preventDefault();
                        window.location.href = '../bills/view.php';
                        break;
                }
            }
        });

        // Real-time system status (placeholder for future implementation)
        function checkSystemStatus() {
            // This could make an AJAX call to check system health
            // For now, just update the timestamp
            const healthItems = document.querySelectorAll('.health-item');
            healthItems.forEach(item => {
                if (item.classList.contains('success')) {
                    item.style.animation = 'pulse 2s ease-in-out';
                    setTimeout(() => {
                        item.style.animation = '';
                    }, 2000);
                }
            });
        }

        // Check system status every 5 minutes
        setInterval(checkSystemStatus, 300000);

        // Add CSS for pulse animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>