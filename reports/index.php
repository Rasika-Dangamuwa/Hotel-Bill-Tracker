<?php
/**
 * Complete Reports Dashboard
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Comprehensive analytics and reporting for hotel bills
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

// Date range filters (default to current month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$hotelFilter = $_GET['hotel_filter'] ?? '';
$employeeFilter = $_GET['employee_filter'] ?? '';

try {
    $db = getDB();
    
    // Build WHERE clause for filters
    $whereConditions = ["DATE(b.created_at) BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if (!empty($hotelFilter)) {
        $whereConditions[] = "b.hotel_id = ?";
        $params[] = $hotelFilter;
    }
    
    if (!empty($employeeFilter)) {
        $whereConditions[] = "EXISTS (SELECT 1 FROM bill_employees be WHERE be.bill_id = b.id AND be.employee_id = ?)";
        $params[] = $employeeFilter;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 1. Summary Statistics
    $summaryStats = $db->fetchRow(
        "SELECT 
            COUNT(*) as total_bills,
            SUM(total_amount) as total_amount,
            AVG(total_amount) as avg_amount,
            SUM(total_nights * room_count) as total_room_nights,
            COUNT(DISTINCT hotel_id) as unique_hotels
         FROM bills b 
         WHERE $whereClause",
        $params
    );
    
    // Count unique employees in the period
    $totalEmployees = $db->fetchValue(
        "SELECT COUNT(DISTINCT be.employee_id) 
         FROM bill_employees be 
         JOIN bills b ON be.bill_id = b.id 
         WHERE $whereClause",
        $params
    );
    
    $summaryStats['total_employees'] = $totalEmployees ?? 0;
    
    // 2. Monthly Breakdown
    $monthlyBreakdown = $db->fetchAll(
        "SELECT 
            DATE_FORMAT(b.created_at, '%Y-%m') as month,
            COUNT(*) as bill_count,
            SUM(b.total_amount) as total_amount,
            SUM(b.total_nights * b.room_count) as total_room_nights,
            AVG(b.total_amount) as avg_amount
         FROM bills b 
         WHERE $whereClause
         GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
         ORDER BY month DESC
         LIMIT 12",
        $params
    );
    
    // 3. Hotel Performance
    $hotelPerformance = $db->fetchAll(
        "SELECT 
            h.id,
            h.hotel_name,
            h.location,
            COUNT(b.id) as bill_count,
            SUM(b.total_amount) as total_amount,
            AVG(b.total_amount) as avg_amount,
            SUM(b.total_nights * b.room_count) as total_room_nights,
            hr.rate as current_rate
         FROM bills b
         JOIN hotels h ON b.hotel_id = h.id
         LEFT JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1
         WHERE $whereClause
         GROUP BY h.id, h.hotel_name, h.location, hr.rate
         ORDER BY total_amount DESC
         LIMIT 10",
        $params
    );
    
    // 4. Employee Utilization
    $employeeUtilization = $db->fetchAll(
        "SELECT 
            e.id,
            e.name,
            e.nic,
            e.designation,
            e.department,
            COUNT(DISTINCT be.bill_id) as bill_count,
            COUNT(be.stay_date) as total_nights,
            MIN(be.stay_date) as first_assignment,
            MAX(be.stay_date) as last_assignment,
            COUNT(DISTINCT h.id) as unique_hotels
         FROM bill_employees be
         JOIN employees e ON be.employee_id = e.id
         JOIN bills b ON be.bill_id = b.id
         JOIN hotels h ON b.hotel_id = h.id
         WHERE $whereClause
         GROUP BY e.id, e.name, e.nic, e.designation, e.department
         ORDER BY total_nights DESC
         LIMIT 15",
        $params
    );
    
    // 5. Expense Breakdown
    $expenseBreakdown = $db->fetchRow(
        "SELECT 
            SUM(base_amount) as accommodation_total,
            SUM(water_charge) as water_total,
            SUM(washing_charge) as washing_total,
            SUM(service_charge) as service_total,
            SUM(misc_charge) as misc_total,
            SUM(total_amount) as grand_total
         FROM bills b 
         WHERE $whereClause",
        $params
    );
    
    // 6. Recent Bills
    $recentBills = $db->fetchAll(
        "SELECT 
            b.id,
            b.invoice_number,
            b.total_amount,
            b.total_nights,
            b.room_count,
            b.status,
            b.created_at,
            h.hotel_name,
            h.location,
            u.name as submitted_by,
            (SELECT COUNT(DISTINCT be.employee_id) FROM bill_employees be WHERE be.bill_id = b.id) as employee_count
         FROM bills b
         JOIN hotels h ON b.hotel_id = h.id
         JOIN users u ON b.submitted_by = u.id
         WHERE $whereClause
         ORDER BY b.created_at DESC
         LIMIT 10",
        $params
    );
    
    // 7. Status Distribution
    $statusDistribution = $db->fetchAll(
        "SELECT 
            status,
            COUNT(*) as count,
            SUM(total_amount) as total_amount
         FROM bills b 
         WHERE $whereClause
         GROUP BY status
         ORDER BY count DESC",
        $params
    );
    
    // Get filter options
    $hotels = $db->fetchAll("SELECT id, hotel_name, location FROM hotels WHERE is_active = 1 ORDER BY hotel_name");
    $employees = $db->fetchAll("SELECT id, name, designation FROM employees WHERE is_active = 1 ORDER BY name");
    
} catch (Exception $e) {
    // Set default values on error
    $summaryStats = [
        'total_bills' => 0, 
        'total_amount' => 0, 
        'avg_amount' => 0, 
        'total_room_nights' => 0, 
        'unique_hotels' => 0,
        'total_employees' => 0
    ];
    $monthlyBreakdown = [];
    $hotelPerformance = [];
    $employeeUtilization = [];
    $expenseBreakdown = [
        'accommodation_total' => 0, 
        'water_total' => 0, 
        'washing_total' => 0, 
        'service_total' => 0, 
        'misc_total' => 0, 
        'grand_total' => 0
    ];
    $recentBills = [];
    $statusDistribution = [];
    $hotels = [];
    $employees = [];
    error_log("Reports error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Hotel Bill Tracking System</title>
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
        }

        .btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            align-items: center;
        }

        .quick-range {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-right: auto;
        }

        .quick-range span {
            font-size: 0.9rem;
            color: #4a5568;
            font-weight: 500;
        }

        .quick-btn {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-btn:hover {
            background: #ebf4ff;
            border-color: #667eea;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
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
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        /* Summary Stats */
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

        /* Expense Breakdown */
        .expense-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .expense-chart {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .expense-item {
            text-align: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .expense-item:hover {
            border-color: #667eea;
            background: #ebf4ff;
        }

        .expense-amount {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .expense-label {
            font-size: 0.8rem;
            color: #718096;
            font-weight: 500;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }

        /* Charts and Tables */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .report-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .report-section.full-width {
            grid-column: 1 / -1;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
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

        .amount-cell {
            font-weight: 600;
            color: #2d3748;
        }

        .metric-cell {
            color: #667eea;
            font-weight: 500;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
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

        .no-data {
            text-align: center;
            color: #718096;
            font-style: italic;
            padding: 3rem;
        }

        .export-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-export {
            background: #48bb78;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background: #38a169;
        }

        .action-links {
            display: flex;
            gap: 0.25rem;
        }

        .action-link {
            background: #ebf4ff;
            color: #2b6cb0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .action-link:hover {
            background: #bee3f8;
        }

        .action-link.edit {
            background: #f0fff4;
            color: #2f855a;
        }

        .action-link.edit:hover {
            background: #c6f6d5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .reports-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
                flex-direction: column;
            }

            .quick-range {
                margin-right: 0;
                justify-content: center;
                margin-bottom: 1rem;
            }

            .btn-primary,
            .btn-secondary {
                flex: 1;
                text-align: center;
            }

            .expense-chart {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .expense-chart {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .header-actions, .filter-actions, .export-actions {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .report-section, .filters-section, .expense-section {
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">📊 Reports & Analytics</h1>
            <div class="header-actions">
                <a href="../dashboard.php" class="btn">← Back to Dashboard</a>
                <button onclick="window.print()" class="btn">🖨️ Print Report</button>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="hotel_filter">Filter by Hotel</label>
                        <select id="hotel_filter" name="hotel_filter">
                            <option value="">All Hotels</option>
                            <?php foreach ($hotels as $hotel): ?>
                                <option value="<?php echo $hotel['id']; ?>" 
                                        <?php echo $hotelFilter == $hotel['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hotel['hotel_name'] . ' - ' . $hotel['location']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="employee_filter">Filter by Employee</label>
                        <select id="employee_filter" name="employee_filter">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" 
                                        <?php echo $employeeFilter == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['name'] . ($employee['designation'] ? ' - ' . $employee['designation'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <div class="quick-range">
                        <span>Quick:</span>
                        <button type="button" class="quick-btn" onclick="setDateRange(7)">7 days</button>
                        <button type="button" class="quick-btn" onclick="setDateRange(30)">30 days</button>
                        <button type="button" class="quick-btn" onclick="setDateRange(90)">90 days</button>
                    </div>
                    <a href="index.php" class="btn-secondary">Clear Filters</a>
                    <button type="submit" class="btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($summaryStats['total_bills'] ?? 0); ?></div>
                <div class="stat-label">Total Bills</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">LKR <?php echo number_format($summaryStats['total_amount'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">LKR <?php echo number_format($summaryStats['avg_amount'] ?? 0, 2); ?></div>
                <div class="stat-label">Average Bill Amount</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($summaryStats['total_room_nights'] ?? 0); ?></div>
                <div class="stat-label">Total Room Nights</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($summaryStats['unique_hotels'] ?? 0); ?></div>
                <div class="stat-label">Hotels Used</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($summaryStats['total_employees'] ?? 0); ?></div>
                <div class="stat-label">Employees Assigned</div>
            </div>
        </div>

        <!-- Expense Breakdown -->
        <div class="expense-section">
            <h2 class="section-title">💰 Expense Breakdown</h2>
            <div class="expense-chart">
                <div class="expense-item">
                    <div class="expense-amount">LKR <?php echo number_format($expenseBreakdown['accommodation_total'] ?? 0, 0); ?></div>
                    <div class="expense-label">Accommodation</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $expenseBreakdown['grand_total'] > 0 ? ($expenseBreakdown['accommodation_total'] / $expenseBreakdown['grand_total'] * 100) : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="expense-item">
                    <div class="expense-amount">LKR <?php echo number_format($expenseBreakdown['water_total'] ?? 0, 0); ?></div>
                    <div class="expense-label">Water Charges</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $expenseBreakdown['grand_total'] > 0 ? ($expenseBreakdown['water_total'] / $expenseBreakdown['grand_total'] * 100) : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="expense-item">
                    <div class="expense-amount">LKR <?php echo number_format($expenseBreakdown['washing_total'] ?? 0, 0); ?></div>
                    <div class="expense-label">Vehicle Washing</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $expenseBreakdown['grand_total'] > 0 ? ($expenseBreakdown['washing_total'] / $expenseBreakdown['grand_total'] * 100) : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="expense-item">
                    <div class="expense-amount">LKR <?php echo number_format($expenseBreakdown['service_total'] ?? 0, 0); ?></div>
                    <div class="expense-label">Service Charges</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $expenseBreakdown['grand_total'] > 0 ? ($expenseBreakdown['service_total'] / $expenseBreakdown['grand_total'] * 100) : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="expense-item">
                    <div class="expense-amount">LKR <?php echo number_format($expenseBreakdown['misc_total'] ?? 0, 0); ?></div>
                    <div class="expense-label">Miscellaneous</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $expenseBreakdown['grand_total'] > 0 ? ($expenseBreakdown['misc_total'] / $expenseBreakdown['grand_total'] * 100) : 0; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Grid -->
        <div class="reports-grid">
            <!-- Hotel Performance -->
            <div class="report-section">
                <h2 class="section-title">🏨 Hotel Performance</h2>
                <?php if (!empty($hotelPerformance)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hotel</th>
                                <th>Bills</th>
                                <th>Total Amount</th>
                                <th>Avg Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hotelPerformance as $hotel): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($hotel['hotel_name']); ?></strong><br>
                                        <small style="color: #718096;"><?php echo htmlspecialchars($hotel['location']); ?></small>
                                    </td>
                                    <td class="metric-cell"><?php echo $hotel['bill_count']; ?></td>
                                    <td class="amount-cell">LKR <?php echo number_format($hotel['total_amount'], 2); ?></td>
                                    <td class="amount-cell">LKR <?php echo number_format($hotel['avg_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="export-actions">
                        <button onclick="exportTable('hotel-performance')" class="btn-export">📊 Export CSV</button>
                    </div>
                <?php else: ?>
                    <div class="no-data">No hotel performance data found for the selected period.</div>
                <?php endif; ?>
            </div>

            <!-- Employee Utilization -->
            <div class="report-section">
                <h2 class="section-title">👥 Employee Utilization</h2>
                <?php if (!empty($employeeUtilization)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Bills</th>
                                <th>Total Nights</th>
                                <th>Hotels</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employeeUtilization as $employee): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($employee['name']); ?></strong><br>
                                        <small style="color: #718096;">
                                            <?php echo htmlspecialchars($employee['designation'] ?? 'No designation'); ?>
                                            <?php if ($employee['department']): ?>
                                                - <?php echo htmlspecialchars($employee['department']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="metric-cell"><?php echo $employee['bill_count']; ?></td>
                                    <td class="metric-cell"><?php echo $employee['total_nights']; ?></td>
                                    <td class="metric-cell"><?php echo $employee['unique_hotels']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="export-actions">
                        <button onclick="exportTable('employee-utilization')" class="btn-export">📊 Export CSV</button>
                    </div>
                <?php else: ?>
                    <div class="no-data">No employee utilization data found for the selected period.</div>
                <?php endif; ?>
            </div>

            <!-- Monthly Breakdown -->
            <div class="report-section">
                <h2 class="section-title">📅 Monthly Breakdown</h2>
                <?php if (!empty($monthlyBreakdown)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Bills</th>
                                <th>Total Amount</th>
                                <th>Room Nights</th>
                                <th>Avg Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyBreakdown as $month): ?>
                                <tr>
                                    <td><strong><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></strong></td>
                                    <td class="metric-cell"><?php echo $month['bill_count']; ?></td>
                                    <td class="amount-cell">LKR <?php echo number_format($month['total_amount'], 2); ?></td>
                                    <td class="metric-cell"><?php echo $month['total_room_nights']; ?></td>
                                    <td class="amount-cell">LKR <?php echo number_format($month['avg_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="export-actions">
                        <button onclick="exportTable('monthly-breakdown')" class="btn-export">📊 Export CSV</button>
                    </div>
                <?php else: ?>
                    <div class="no-data">No monthly breakdown data found for the selected period.</div>
                <?php endif; ?>
            </div>

            <!-- Status Distribution -->
            <div class="report-section">
                <h2 class="section-title">📋 Bill Status Distribution</h2>
                <?php if (!empty($statusDistribution)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Total Amount</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalStatusBills = array_sum(array_column($statusDistribution, 'count'));
                            foreach ($statusDistribution as $status): 
                            ?>
                                <tr>
                                    <td>
                                        <span class="status-badge status-<?php echo $status['status']; ?>">
                                            <?php echo ucfirst($status['status']); ?>
                                        </span>
                                    </td>
                                    <td class="metric-cell"><?php echo $status['count']; ?></td>
                                    <td class="amount-cell">LKR <?php echo number_format($status['total_amount'], 2); ?></td>
                                    <td class="metric-cell">
                                        <?php echo $totalStatusBills > 0 ? number_format(($status['count'] / $totalStatusBills) * 100, 1) : 0; ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="export-actions">
                        <button onclick="exportTable('status-distribution')" class="btn-export">📊 Export CSV</button>
                    </div>
                <?php else: ?>
                    <div class="no-data">No status distribution data found for the selected period.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Bills -->
        <div class="report-section full-width">
            <h2 class="section-title">🕐 Recent Bills</h2>
            <?php if (!empty($recentBills)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Hotel</th>
                            <th>Amount</th>
                            <th>Nights</th>
                            <th>Rooms</th>
                            <th>Employees</th>
                            <th>Status</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBills as $bill): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($bill['invoice_number']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($bill['hotel_name']); ?></strong><br>
                                    <small style="color: #718096;"><?php echo htmlspecialchars($bill['location']); ?></small>
                                </td>
                                <td class="amount-cell">LKR <?php echo number_format($bill['total_amount'], 2); ?></td>
                                <td class="metric-cell"><?php echo $bill['total_nights']; ?></td>
                                <td class="metric-cell"><?php echo $bill['room_count']; ?></td>
                                <td class="metric-cell"><?php echo $bill['employee_count']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $bill['status']; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($bill['submitted_by']); ?></strong><br>
                                    <small style="color: #718096;"><?php echo date('M j, Y', strtotime($bill['created_at'])); ?></small>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($bill['created_at'])); ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="../bills/details.php?id=<?php echo $bill['id']; ?>" class="action-link">👁️ View</a>
                                        <?php if ($bill['status'] === 'pending'): ?>
                                            <a href="../bills/edit.php?id=<?php echo $bill['id']; ?>" class="action-link edit">✏️ Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="export-actions">
                    <button onclick="exportTable('recent-bills')" class="btn-export">📊 Export CSV</button>
                    <a href="../bills/view.php" class="btn-secondary">View All Bills</a>
                </div>
            <?php else: ?>
                <div class="no-data">No recent bills found for the selected period.</div>
            <?php endif; ?>
        </div>

        <!-- Print Summary -->
        <div class="report-section full-width" style="margin-top: 2rem; border: 2px solid #e2e8f0; background: #f8fafc;">
            <h2 class="section-title">📄 Report Summary</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div>
                    <strong>Report Period:</strong><br>
                    <?php echo date('F j, Y', strtotime($startDate)); ?> to <?php echo date('F j, Y', strtotime($endDate)); ?>
                </div>
                <div>
                    <strong>Generated By:</strong><br>
                    <?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo getUserRoleDisplay(); ?>)
                </div>
                <div>
                    <strong>Generated On:</strong><br>
                    <?php echo date('F j, Y g:i A'); ?>
                </div>
                <div>
                    <strong>Total Records:</strong><br>
                    <?php echo number_format($summaryStats['total_bills']); ?> bills, <?php echo number_format($summaryStats['total_employees']); ?> employees
                </div>
            </div>
            
            <?php if (!empty($hotelFilter) || !empty($employeeFilter)): ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #ebf4ff; border-radius: 8px; border-left: 4px solid #667eea;">
                    <strong>Applied Filters:</strong><br>
                    <?php if (!empty($hotelFilter)): ?>
                        <?php 
                        $filteredHotel = array_filter($hotels, function($h) use ($hotelFilter) { 
                            return $h['id'] == $hotelFilter; 
                        });
                        if (!empty($filteredHotel)):
                            $hotel = reset($filteredHotel);
                        ?>
                            Hotel: <?php echo htmlspecialchars($hotel['hotel_name'] . ' - ' . $hotel['location']); ?><br>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($employeeFilter)): ?>
                        <?php 
                        $filteredEmployee = array_filter($employees, function($e) use ($employeeFilter) { 
                            return $e['id'] == $employeeFilter; 
                        });
                        if (!empty($filteredEmployee)):
                            $employee = reset($filteredEmployee);
                        ?>
                            Employee: <?php echo htmlspecialchars($employee['name']); ?><br>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Quick date range functions
        function setDateRange(days) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - days);
            
            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        }

        // Export table to CSV
        function exportTable(tableName) {
            // Find the table within the report section
            const reportSections = document.querySelectorAll('.report-section');
            let targetTable = null;
            
            reportSections.forEach(section => {
                const table = section.querySelector('.data-table');
                if (table) {
                    // Use section title to identify the table
                    const title = section.querySelector('.section-title').textContent.toLowerCase();
                    if (title.includes('hotel') && tableName === 'hotel-performance') {
                        targetTable = table;
                    } else if (title.includes('employee') && tableName === 'employee-utilization') {
                        targetTable = table;
                    } else if (title.includes('monthly') && tableName === 'monthly-breakdown') {
                        targetTable = table;
                    } else if (title.includes('status') && tableName === 'status-distribution') {
                        targetTable = table;
                    } else if (title.includes('recent') && tableName === 'recent-bills') {
                        targetTable = table;
                    }
                }
            });
            
            if (!targetTable) {
                alert('Table not found for export');
                return;
            }
            
            let csv = [];
            const rows = targetTable.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowData = [];
                
                cells.forEach(cell => {
                    // Clean the cell text
                    let text = cell.textContent.trim();
                    // Remove extra whitespace and newlines
                    text = text.replace(/\s+/g, ' ');
                    // Escape quotes and wrap in quotes if contains comma
                    if (text.includes(',') || text.includes('"')) {
                        text = '"' + text.replace(/"/g, '""') + '"';
                    }
                    rowData.push(text);
                });
                
                csv.push(rowData.join(','));
            });
            
            // Create and download CSV file
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `${tableName}-${new Date().toISOString().split('T')[0]}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                alert('CSV export not supported in this browser');
            }
        }

        // Auto-update end date when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            const endDateInput = document.getElementById('end_date');
            const endDate = new Date(endDateInput.value);
            
            // If end date is before start date, update it
            if (endDate <= startDate) {
                const newEndDate = new Date(startDate);
                newEndDate.setDate(startDate.getDate() + 30); // Add 30 days
                endDateInput.value = newEndDate.toISOString().split('T')[0];
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+P for print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            // Ctrl+E for export (show export options)
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                alert('Use the Export CSV buttons in each section to download data');
            }
        });

        // Highlight current filters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Highlight active filters
            const filterInputs = document.querySelectorAll('.filter-group input, .filter-group select');
            filterInputs.forEach(input => {
                if (input.value && input.value !== '') {
                    input.style.borderColor = '#667eea';
                    input.style.backgroundColor = '#ebf4ff';
                }
            });
        });

        // Animate statistics on load
        window.addEventListener('load', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = stat.textContent;
                stat.textContent = '0';
                
                // Simple counter animation
                const isLKR = finalValue.includes('LKR');
                const numericValue = parseFloat(finalValue.replace(/[^\d.]/g, ''));
                
                if (!isNaN(numericValue)) {
                    let current = 0;
                    const increment = numericValue / 50; // 50 steps
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= numericValue) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            if (isLKR) {
                                stat.textContent = 'LKR ' + current.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            } else {
                                stat.textContent = Math.floor(current).toLocaleString();
                            }
                        }
                    }, 20);
                }
            });
        });
    </script>
</body>
</html>