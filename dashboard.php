<?php
/**
 * Updated Main Dashboard
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Enhanced dashboard for account assistants with room rate management
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
    
    // Get pending bills count
    $pendingBills = $db->fetchValue("SELECT COUNT(*) FROM bills WHERE status = 'pending'") ?: 0;
    
    // Get today's bills
    $todayBills = $db->fetchValue("SELECT COUNT(*) FROM bills WHERE DATE(created_at) = CURDATE()") ?: 0;
    
    // Get hotels needing rate updates (rates older than 6 months)
    $outdatedRates = $db->fetchValue(
        "SELECT COUNT(DISTINCT h.id) FROM hotels h 
         JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1 
         WHERE h.is_active = 1 AND hr.effective_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)"
    ) ?: 0;
    
    // Get recent activities (last 10)
    $recentActivities = $db->fetchAll(
        "SELECT table_name, action, new_values, created_at, u.name as user_name 
         FROM audit_log a 
         LEFT JOIN users u ON a.user_id = u.id 
         ORDER BY a.created_at DESC 
         LIMIT 10"
    );
    
    // Get recent bills for quick overview
    $recentBills = $db->fetchAll(
        "SELECT b.id, b.invoice_number, b.total_amount, b.status, b.created_at,
                h.hotel_name, h.location, u.name as submitted_by
         FROM bills b
         JOIN hotels h ON b.hotel_id = h.id
         JOIN users u ON b.submitted_by = u.id
         ORDER BY b.created_at DESC
         LIMIT 5"
    );
    
    // Get hotels with current rates for quick rate overview
    $currentRates = $db->fetchAll(
        "SELECT h.id, h.hotel_name, h.location, hr.rate, hr.effective_date
         FROM hotels h
         JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1
         WHERE h.is_active = 1
         ORDER BY hr.effective_date ASC
         LIMIT 8"
    );
    
} catch (Exception $e) {
    // Set default values if database queries fail
    $totalBills = 0;
    $totalEmployees = 0;
    $totalHotels = 0;
    $monthlyAmount = 0;
    $pendingBills = 0;
    $todayBills = 0;
    $outdatedRates = 0;
    $recentActivities = [];
    $recentBills = [];
    $currentRates = [];
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

        .header-actions {
            display: flex;
            gap: 1rem;
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

        .admin-btn {
            background: #48bb78;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .admin-btn:hover {
            background: #38a169;
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
            border-left: 4px solid #667eea;
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

        /* Stats Overview */
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
            align-items: center;
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
        .stat-icon.employees { background: linear-gradient(135deg, #48bb78, #38a169); }
        .stat-icon.hotels { background: linear-gradient(135deg, #ed8936, #dd6b20); }
        .stat-icon.amount { background: linear-gradient(135deg, #4299e1, #3182ce); }
        .stat-icon.pending { background: linear-gradient(135deg, #f56565, #e53e3e); }
        .stat-icon.today { background: linear-gradient(135deg, #9f7aea, #805ad5); }

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
        }

        /* Dashboard Actions */
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
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
            text-decoration: none;
            color: inherit;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 1rem;
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .card-icon.hotel { background: linear-gradient(135deg, #667eea, #764ba2); }
        .card-icon.employee { background: linear-gradient(135deg, #48bb78, #38a169); }
        .card-icon.bill { background: linear-gradient(135deg, #ed8936, #dd6b20); }
        .card-icon.view { background: linear-gradient(135deg, #4299e1, #3182ce); }
        .card-icon.report { background: linear-gradient(135deg, #9f7aea, #805ad5); }
        .card-icon.rates { background: linear-gradient(135deg, #f56565, #e53e3e); }
        .card-icon.settings { background: linear-gradient(135deg, #718096, #4a5568); }

        .card-content {
            flex: 1;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }

        .card-description {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .card-badge {
            background: #fed7aa;
            color: #c05621;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
            display: inline-block;
        }

        .card-badge.urgent {
            background: #fed7d7;
            color: #c53030;
        }

        .card-badge.success {
            background: #c6f6d5;
            color: #2f855a;
        }

        /* Content Sections */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-section {
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

        /* Recent Bills */
        .bills-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .bill-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .bill-item:hover {
            background: #f8fafc;
            margin: 0 -1.5rem;
            padding: 1rem 1.5rem;
        }

        .bill-item:last-child {
            border-bottom: none;
        }

        .bill-info h4 {
            margin: 0;
            color: #2d3748;
            font-size: 1rem;
        }

        .bill-meta {
            color: #718096;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .bill-amount {
            font-weight: 600;
            color: #2d3748;
            text-align: right;
        }

        .bill-status {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 0.25rem;
        }

        .status-pending {
            background: #fef5e7;
            color: #d69e2e;
        }

        .status-approved {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-rejected {
            background: #fed7d7;
            color: #c53030;
        }

        /* Rate Overview */
        .rates-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .rate-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .rate-item:hover {
            background: #f8fafc;
            margin: 0 -1.5rem;
            padding: 1rem 1.5rem;
        }

        .rate-item:last-child {
            border-bottom: none;
        }

        .rate-info h4 {
            margin: 0;
            color: #2d3748;
            font-size: 1rem;
        }

        .rate-location {
            color: #718096;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .rate-details {
            text-align: right;
        }

        .rate-amount {
            font-weight: 600;
            color: #2d3748;
            font-size: 1rem;
        }

        .rate-date {
            color: #718096;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .rate-outdated {
            background: #fed7d7;
            color: #c53030;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }

        /* Recent Activity */
        .activities-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
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

        .no-data {
            text-align: center;
            color: #718096;
            font-style: italic;
            padding: 2rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

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

            .user-section {
                flex-direction: column;
                gap: 10px;
            }

            .header-actions {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .bill-item,
            .rate-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
                <div class="header-actions">
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="admin-btn">🔧 Admin Panel</a>
                    <?php endif; ?>
                    <button class="logout-btn" onclick="logout()">Logout</button>
                </div>
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
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalBills); ?></div>
                        <div class="stat-label">Total Bills</div>
                    </div>
                    <div class="stat-icon bills">📋</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalEmployees); ?></div>
                        <div class="stat-label">Active Employees</div>
                    </div>
                    <div class="stat-icon employees">👥</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalHotels); ?></div>
                        <div class="stat-label">Registered Hotels</div>
                    </div>
                    <div class="stat-icon hotels">🏨</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number">LKR <?php echo number_format($monthlyAmount, 0); ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-icon amount">💰</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($pendingBills); ?></div>
                        <div class="stat-label">Pending Bills</div>
                    </div>
                    <div class="stat-icon pending">⏳</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($todayBills); ?></div>
                        <div class="stat-label">Today's Bills</div>
                    </div>
                    <div class="stat-icon today">📅</div>
                </div>
            </div>
        </section>

        <!-- Dashboard Actions -->
        <section class="dashboard-grid">
            <a href="hotels/register.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon hotel">🏨</div>
                    <div class="card-content">
                        <h3 class="card-title">Register Hotel</h3>
                        <p class="card-description">Add new hotels to the system and manage hotel information including rates and contact details.</p>
                    </div>
                </div>
            </a>

            <a href="employees/register.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon employee">👥</div>
                    <div class="card-content">
                        <h3 class="card-title">Register Employee</h3>
                        <p class="card-description">Add new crew members and promotional staff to the employee database.</p>
                    </div>
                </div>
            </a>

            <a href="bills/add.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon bill">📋</div>
                    <div class="card-content">
                        <h3 class="card-title">Add New Bill</h3>
                        <p class="card-description">Enter hotel bills submitted by propagandists with room assignments and additional charges.</p>
                    </div>
                </div>
            </a>

            <a href="bills/view.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon view">👁️</div>
                    <div class="card-content">
                        <h3 class="card-title">View Bills</h3>
                        <p class="card-description">Review, search, and manage all hotel bills in the system with detailed filtering options.</p>
                    </div>
                </div>
            </a>

            <a href="hotels/rates.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon rates">💱</div>
                    <div class="card-content">
                        <h3 class="card-title">Manage Room Rates</h3>
                        <p class="card-description">Update current room rates for hotels and manage rate history and changes.</p>
                        <?php if ($outdatedRates > 0): ?>
                            <span class="card-badge urgent"><?php echo $outdatedRates; ?> rates need updating</span>
                        <?php else: ?>
                            <span class="card-badge success">All rates up to date</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>

            <a href="reports/index.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon report">📊</div>
                    <div class="card-content">
                        <h3 class="card-title">Reports & Analytics</h3>
                        <p class="card-description">Generate detailed reports on expenses, employee stays, and hotel usage patterns.</p>
                    </div>
                </div>
            </a>

            <a href="settings/index.php" class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon settings">⚙️</div>
                    <div class="card-content">
                        <h3 class="card-title">Settings</h3>
                        <p class="card-description">Manage your profile, system preferences, and configure application settings.</p>
                    </div>
                </div>
            </a>
        </section>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Bills -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span>📋</span>
                        Recent Bills
                    </h2>
                    <a href="bills/view.php" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">View All →</a>
                </div>
                <div class="section-content">
                    <div class="bills-list">
                        <?php if (!empty($recentBills)): ?>
                            <?php foreach ($recentBills as $bill): ?>
                                <div class="bill-item">
                                    <div class="bill-info">
                                        <h4><?php echo htmlspecialchars($bill['invoice_number']); ?></h4>
                                        <div class="bill-meta">
                                            <?php echo htmlspecialchars($bill['hotel_name']); ?> • 
                                            <?php echo htmlspecialchars($bill['location']); ?> • 
                                            <?php echo date('M j, Y', strtotime($bill['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="bill-amount">
                                        <div>LKR <?php echo number_format($bill['total_amount'], 2); ?></div>
                                        <div class="bill-status status-<?php echo $bill['status']; ?>">
                                            <?php echo ucfirst($bill['status']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <div style="font-size: 2rem; margin-bottom: 1rem;">📋</div>
                                <p>No bills found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Current Room Rates -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span>💱</span>
                        Current Room Rates
                    </h2>
                    <a href="hotels/rates.php" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">Manage →</a>
                </div>
                <div class="section-content">
                    <div class="rates-list">
                        <?php if (!empty($currentRates)): ?>
                            <?php foreach ($currentRates as $rate): ?>
                                <div class="rate-item">
                                    <div class="rate-info">
                                        <h4><?php echo htmlspecialchars($rate['hotel_name']); ?></h4>
                                        <div class="rate-location"><?php echo htmlspecialchars($rate['location']); ?></div>
                                    </div>
                                    <div class="rate-details">
                                        <div class="rate-amount">LKR <?php echo number_format($rate['rate'], 2); ?></div>
                                        <div class="rate-date">
                                            Since <?php echo date('M j, Y', strtotime($rate['effective_date'])); ?>
                                        </div>
                                        <?php 
                                        $monthsSinceUpdate = (time() - strtotime($rate['effective_date'])) / (30 * 24 * 60 * 60);
                                        if ($monthsSinceUpdate > 6): 
                                        ?>
                                            <div class="rate-outdated">Needs Update</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <div style="font-size: 2rem; margin-bottom: 1rem;">💱</div>
                                <p>No rates found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <span>📋</span>
                    Recent Activity
                </h2>
            </div>
            <div class="section-content">
                <div class="activities-list">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
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
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <div style="font-size: 2rem; margin-bottom: 1rem;">📋</div>
                            <p>No recent activities found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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

        // Animate statistics on load
        window.addEventListener('load', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalText = stat.textContent;
                const isLKR = finalText.includes('LKR');
                const numericValue = parseFloat(finalText.replace(/[^\d.]/g, ''));
                
                if (!isNaN(numericValue) && numericValue > 0) {
                    stat.textContent = isLKR ? 'LKR 0' : '0';
                    let current = 0;
                    const increment = Math.max(1, Math.floor(numericValue / 30));
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
                    }, 50);
                }
            });
        });

        // Add hover effects to clickable items
        document.querySelectorAll('.bill-item, .rate-item').forEach(item => {
            item.addEventListener('click', function() {
                // Add click functionality if needed
                const billId = this.dataset.billId;
                if (billId) {
                    window.location.href = `bills/details.php?id=${billId}`;
                }
            });
        });

        // Real-time updates (placeholder for future implementation)
        function updateDashboard() {
            // This could fetch updated statistics via AJAX
            // For now, just add a subtle visual indicator
            const statsCards = document.querySelectorAll('.stat-card');
            statsCards.forEach(card => {
                card.style.transition = 'transform 0.3s ease';
                card.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    card.style.transform = 'scale(1)';
                }, 300);
            });
        }

        // Update dashboard every 5 minutes
        setInterval(updateDashboard, 300000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case 'h':
                        e.preventDefault();
                        window.location.href = 'hotels/register.php';
                        break;
                    case 'e':
                        e.preventDefault();
                        window.location.href = 'employees/register.php';
                        break;
                    case 'b':
                        e.preventDefault();
                        window.location.href = 'bills/add.php';
                        break;
                    case 'v':
                        e.preventDefault();
                        window.location.href = 'bills/view.php';
                        break;
                    case 'r':
                        e.preventDefault();
                        window.location.href = 'hotels/rates.php';
                        break;
                    case 's':
                        e.preventDefault();
                        window.location.href = 'settings/index.php';
                        break;
                }
            }
        });

        // Show keyboard shortcuts help
        function showKeyboardHelp() {
            alert(`Keyboard Shortcuts:
            
Alt + H - Register Hotel
Alt + E - Register Employee  
Alt + B - Add New Bill
Alt + V - View Bills
Alt + R - Manage Room Rates
Alt + S - Settings

Tip: Use these shortcuts for quick navigation!`);
        }

        // Add help button functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add a small help icon to the header
            const helpBtn = document.createElement('button');
            helpBtn.innerHTML = '❓';
            helpBtn.title = 'Keyboard shortcuts';
            helpBtn.style.cssText = `
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 0.8rem;
                transition: all 0.3s ease;
                margin-left: 0.5rem;
            `;
            helpBtn.onclick = showKeyboardHelp;
            helpBtn.onmouseover = function() {
                this.style.background = 'rgba(255,255,255,0.3)';
            };
            helpBtn.onmouseout = function() {
                this.style.background = 'rgba(255,255,255,0.2)';
            };
            
            const headerActions = document.querySelector('.header-actions');
            if (headerActions) {
                headerActions.appendChild(helpBtn);
            }
        });

        // Progressive enhancement for rate alerts
        document.addEventListener('DOMContentLoaded', function() {
            const urgentBadges = document.querySelectorAll('.card-badge.urgent');
            urgentBadges.forEach(badge => {
                // Add subtle pulse animation to urgent items
                badge.style.animation = 'pulse 2s infinite';
            });
            
            // Add CSS for pulse animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.7; }
                    100% { opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        });

        // Check for outdated rates notification
        <?php if ($outdatedRates > 0): ?>
        // Show notification about outdated rates
        setTimeout(function() {
            if (confirm('⚠️ <?php echo $outdatedRates; ?> hotel rates haven\'t been updated in over 6 months.\n\nWould you like to review and update them now?')) {
                window.location.href = 'hotels/rates.php';
            }
        }, 3000);
        <?php endif; ?>

        // Add loading states for better UX
        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Add loading state
                const icon = this.querySelector('.card-icon');
                if (icon) {
                    icon.style.animation = 'rotate 1s infinite linear';
                }
                
                // Add CSS for rotate animation
                if (!document.getElementById('rotate-animation')) {
                    const rotateStyle = document.createElement('style');
                    rotateStyle.id = 'rotate-animation';
                    rotateStyle.textContent = `
                        @keyframes rotate {
                            from { transform: rotate(0deg); }
                            to { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(rotateStyle);
                }
            });
        });
    </script>
</body>
</html>