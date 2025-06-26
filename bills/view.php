<?php
/**
 * View Bills Page
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Search and manage all hotel bills
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

// Handle search and filtering
$searchQuery = '';
$statusFilter = '';
$hotelFilter = '';
$startDate = '';
$endDate = '';
$page = 1;
$limit = 10;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $searchQuery = trim($_GET['search'] ?? '');
    $statusFilter = $_GET['status'] ?? '';
    $hotelFilter = $_GET['hotel'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
}

try {
    $db = getDB();
    
    // Build WHERE clause for filtering
    $whereConditions = [];
    $params = [];
    
    if (!empty($searchQuery)) {
        $whereConditions[] = "(b.invoice_number LIKE ? OR h.hotel_name LIKE ? OR h.location LIKE ?)";
        $params[] = '%' . $searchQuery . '%';
        $params[] = '%' . $searchQuery . '%';
        $params[] = '%' . $searchQuery . '%';
    }
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "b.status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($hotelFilter)) {
        $whereConditions[] = "b.hotel_id = ?";
        $params[] = $hotelFilter;
    }
    
    if (!empty($startDate)) {
        $whereConditions[] = "b.check_in >= ?";
        $params[] = $startDate;
    }
    
    if (!empty($endDate)) {
        $whereConditions[] = "b.check_out <= ?";
        $params[] = $endDate;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM bills b 
                 JOIN hotels h ON b.hotel_id = h.id 
                 JOIN users u ON b.submitted_by = u.id 
                 $whereClause";
    $totalBills = $db->fetchValue($countSql, $params);
    $totalPages = ceil($totalBills / $limit);
    
    // Get bills with pagination
    $offset = ($page - 1) * $limit;
    $billsSql = "SELECT b.*, h.hotel_name, h.location, u.name as submitted_by_name,
                        hr.rate as bill_rate,
                        (SELECT COUNT(DISTINCT be.employee_id) FROM bill_employees be WHERE be.bill_id = b.id) as employee_count
                 FROM bills b 
                 JOIN hotels h ON b.hotel_id = h.id 
                 JOIN users u ON b.submitted_by = u.id 
                 JOIN hotel_rates hr ON b.rate_id = hr.id
                 $whereClause
                 ORDER BY b.created_at DESC 
                 LIMIT $limit OFFSET $offset";
    
    $bills = $db->fetchAll($billsSql, $params);
    
    // Get hotels for filter dropdown
    $hotels = $db->fetchAll("SELECT id, hotel_name, location FROM hotels WHERE is_active = 1 ORDER BY hotel_name");
    
} catch (Exception $e) {
    $bills = [];
    $hotels = [];
    $totalBills = 0;
    $totalPages = 0;
    error_log("View bills error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bills - Hotel Bill Tracking System</title>
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

        .search-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        input, select {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .bills-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .bills-header {
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bills-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .bills-count {
            color: #718096;
            font-size: 0.9rem;
        }

        .bills-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bills-table th,
        .bills-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .bills-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bills-table tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
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

        .bill-amount {
            font-weight: 600;
            color: #2d3748;
        }

        .bill-details {
            font-size: 0.9rem;
            color: #718096;
        }

        .pagination {
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination-info {
            color: #718096;
            font-size: 0.9rem;
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            background: white;
            color: #4a5568;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .page-btn:hover {
            background: #f8fafc;
            border-color: #667eea;
        }

        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .no-bills {
            padding: 4rem 2rem;
            text-align: center;
            color: #718096;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .view-btn {
            background: #ebf4ff;
            color: #2b6cb0;
        }

        .view-btn:hover {
            background: #bee3f8;
        }

        .edit-btn {
            background: #f0fff4;
            color: #2f855a;
        }

        .edit-btn:hover {
            background: #c6f6d5;
        }

        .export-btn {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .export-btn:hover {
            background: #edf2f7;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .search-grid {
                grid-template-columns: 1fr;
            }

            .search-actions {
                justify-content: stretch;
            }

            .btn {
                flex: 1;
                text-align: center;
            }

            .bills-table {
                font-size: 0.9rem;
            }

            .bills-table th,
            .bills-table td {
                padding: 0.5rem;
            }

            .pagination {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">View Bills</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <!-- Search and Filter Section -->
        <div class="search-container">
            <form method="GET" action="">
                <div class="search-grid">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo htmlspecialchars($searchQuery); ?>" 
                               placeholder="Invoice number, hotel name, location...">
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="hotel">Hotel</label>
                        <select id="hotel" name="hotel">
                            <option value="">All Hotels</option>
                            <?php foreach ($hotels as $hotel): ?>
                                <option value="<?php echo $hotel['id']; ?>" 
                                        <?php echo $hotelFilter == $hotel['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hotel['hotel_name'] . ' - ' . $hotel['location']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">From Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">To Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                </div>

                <div class="search-actions">
                    <a href="view.php" class="btn btn-secondary">Clear Filters</a>
                    <button type="submit" class="btn">Search Bills</button>
                    <a href="add.php" class="btn">+ Add New Bill</a>
                </div>
            </form>
        </div>

        <!-- Bills List -->
        <div class="bills-container">
            <div class="bills-header">
                <h2 class="bills-title">Hotel Bills</h2>
                <div class="bills-count">
                    Showing <?php echo count($bills); ?> of <?php echo $totalBills; ?> bills
                </div>
            </div>

            <?php if (!empty($bills)): ?>
                <table class="bills-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Hotel</th>
                            <th>Stay Period</th>
                            <th>Rooms</th>
                            <th>Employees</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($bill['invoice_number']); ?></strong>
                                    <div class="bill-details">
                                        Created: <?php echo date('M j, Y', strtotime($bill['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($bill['hotel_name']); ?></strong>
                                    <div class="bill-details"><?php echo htmlspecialchars($bill['location']); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo date('M j', strtotime($bill['check_in'])) . ' - ' . date('M j, Y', strtotime($bill['check_out'])); ?></strong>
                                    <div class="bill-details"><?php echo $bill['total_nights']; ?> nights</div>
                                </td>
                                <td><?php echo $bill['room_count']; ?></td>
                                <td><?php echo $bill['employee_count']; ?></td>
                                <td>
                                    <div class="bill-amount">LKR <?php echo number_format($bill['total_amount'], 2); ?></div>
                                    <div class="bill-details">@ LKR <?php echo number_format($bill['bill_rate'], 2); ?>/night</div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $bill['status']; ?>">
                                        <?php echo ucfirst($bill['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($bill['submitted_by_name']); ?></div>
                                    <div class="bill-details"><?php echo date('M j, Y', strtotime($bill['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="details.php?id=<?php echo $bill['id']; ?>" class="action-btn view-btn">View</a>
                                        <?php if ($bill['status'] === 'pending'): ?>
                                            <a href="edit.php?id=<?php echo $bill['id']; ?>" class="action-btn edit-btn">Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn">First</a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">Previous</a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">Next</a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="page-btn">Last</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-bills">
                    <h3>No Bills Found</h3>
                    <p>No bills match your search criteria.</p>
                    <a href="add.php" class="btn" style="margin-top: 1rem;">Add Your First Bill</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit form when filters change (optional)
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                // Uncomment next line to auto-submit on filter change
                // this.form.submit();
            });
        });

        // Clear search on escape key
        document.getElementById('search').addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
            }
        });
    </script>
</body>
</html>