<?php
/**
 * Room Rates Management Page - FIXED VERSION
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

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_rate') {
            $hotelId = intval($_POST['hotel_id']);
            $newRate = floatval($_POST['new_rate']);
            $effectiveDate = $_POST['effective_date'];
            $notes = trim($_POST['notes'] ?? '');
            
            // Validate required fields
            if (empty($hotelId) || empty($newRate) || empty($effectiveDate)) {
                throw new Exception('Hotel, rate, and effective date are required.');
            }
            
            if ($newRate <= 0) {
                throw new Exception('Rate must be greater than 0.');
            }
            
            // Get hotel name for logging
            $hotel = $db->fetchRow("SELECT hotel_name FROM hotels WHERE id = ?", [$hotelId]);
            if (!$hotel) {
                throw new Exception('Hotel not found.');
            }
            
            $db->beginTransaction();
            
            // Get current rate for comparison
            $currentRate = $db->fetchRow(
                "SELECT rate FROM hotel_rates WHERE hotel_id = ? AND is_current = 1",
                [$hotelId]
            );
            
            // Only create new rate if it's different from current rate
            if (!$currentRate || abs($currentRate['rate'] - $newRate) > 0.01) {
                // Set all previous rates for this hotel as not current
                $db->query(
                    "UPDATE hotel_rates SET is_current = 0, end_date = DATE_SUB(?, INTERVAL 1 DAY) WHERE hotel_id = ? AND is_current = 1",
                    [$effectiveDate, $hotelId]
                );
                
                // Insert new rate
                $rateId = $db->insert(
                    "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by) VALUES (?, ?, ?, 1, ?)",
                    [$hotelId, $newRate, $effectiveDate, $currentUser['id']]
                );
                
                $db->commit();
                
                $message = "Room rate updated successfully for " . $hotel['hotel_name'] . " to LKR " . number_format($newRate, 2);
                $messageType = 'success';
            } else {
                $message = "No changes made - rate is the same as current rate.";
                $messageType = 'info';
            }
        }
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollback();
        }
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get all hotels with current rates
try {
    $db = getDB();
    
    $hotelsWithRates = $db->fetchAll(
        "SELECT h.id, h.hotel_name, h.location, h.address, h.phone, h.email,
                hr.id as rate_id, hr.rate, hr.effective_date, hr.created_at as rate_created,
                u.name as rate_created_by,
                DATEDIFF(CURDATE(), hr.effective_date) as days_since_update
         FROM hotels h
         LEFT JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1
         LEFT JOIN users u ON hr.created_by = u.id
         WHERE h.is_active = 1
         ORDER BY days_since_update DESC, h.hotel_name"
    );
    
    // Get rate history
    $rateHistory = [];
    foreach ($hotelsWithRates as $hotel) {
        if ($hotel['id']) {
            $history = $db->fetchAll(
                "SELECT hr.rate, hr.effective_date, hr.end_date, hr.created_at,
                        u.name as created_by
                 FROM hotel_rates hr
                 JOIN users u ON hr.created_by = u.id
                 WHERE hr.hotel_id = ?
                 ORDER BY hr.effective_date DESC
                 LIMIT 10",
                [$hotel['id']]
            );
            $rateHistory[$hotel['id']] = $history;
        }
    }
    
    // Get statistics
    $rateStats = $db->fetchRow(
        "SELECT 
            COUNT(DISTINCT h.id) as total_hotels,
            COUNT(DISTINCT CASE WHEN hr.effective_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN h.id END) as updated_recently,
            COUNT(DISTINCT CASE WHEN hr.effective_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN h.id END) as needs_update,
            AVG(hr.rate) as avg_rate,
            MIN(hr.rate) as min_rate,
            MAX(hr.rate) as max_rate
         FROM hotels h
         LEFT JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1
         WHERE h.is_active = 1"
    );
    
} catch (Exception $e) {
    $hotelsWithRates = [];
    $rateHistory = [];
    $rateStats = [
        'total_hotels' => 0, 'updated_recently' => 0, 'needs_update' => 0,
        'avg_rate' => 0, 'min_rate' => 0, 'max_rate' => 0
    ];
    error_log("Rates page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Room Rates - Hotel Bill Tracking System</title>
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

        .alert-info {
            background: #bee3f8;
            color: #2b6cb0;
            border: 1px solid #90cdf4;
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

        .stat-card.warning {
            border-left-color: #f59e0b;
        }

        .stat-card.warning .stat-number {
            color: #f59e0b;
        }

        .stat-card.success {
            border-left-color: #10b981;
        }

        .stat-card.success .stat-number {
            color: #10b981;
        }

        /* Main Content */
        .rates-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Search Container */
        .search-container {
            position: relative;
            width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .rates-table {
            width: 100%;
            border-collapse: collapse;
        }

        .rates-table th,
        .rates-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .rates-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rates-table tbody tr:hover {
            background: #f8fafc;
        }

        .hotel-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hotel-icon {
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

        .hotel-details h4 {
            margin: 0;
            color: #2d3748;
            font-size: 1rem;
        }

        .hotel-location {
            color: #718096;
            font-size: 0.85rem;
        }

        .rate-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .rate-info {
            font-size: 0.8rem;
            color: #718096;
            margin-top: 0.25rem;
        }

        .update-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-recent {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-moderate {
            background: #fef5e7;
            color: #d69e2e;
        }

        .status-outdated {
            background: #fed7d7;
            color: #c53030;
        }

        .status-none {
            background: #e2e8f0;
            color: #4a5568;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-update {
            background: #ebf4ff;
            color: #2b6cb0;
        }

        .btn-update:hover {
            background: #bee3f8;
        }

        .btn-history {
            background: #f0fff4;
            color: #2f855a;
        }

        .btn-history:hover {
            background: #c6f6d5;
        }

        /* Modal Styles */
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
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

        .history-item {
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-rate {
            font-weight: 600;
            color: #2d3748;
        }

        .history-meta {
            font-size: 0.85rem;
            color: #718096;
        }

        .current-indicator {
            background: #48bb78;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .no-results {
            text-align: center;
            padding: 3rem 2rem;
            color: #718096;
        }

        .no-results h3 {
            margin-bottom: 0.5rem;
            color: #4a5568;
        }

        .highlight {
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
            padding: 1px 2px;
            border-radius: 2px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .search-container {
                width: 100%;
                max-width: none;
            }

            .rates-table {
                font-size: 0.9rem;
            }

            .rates-table th,
            .rates-table td {
                padding: 0.75rem 0.5rem;
            }

            .hotel-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .actions {
                flex-direction: column;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">Manage Room Rates</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($rateStats['total_hotels']); ?></div>
                <div class="stat-label">Total Hotels</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php echo number_format($rateStats['updated_recently']); ?></div>
                <div class="stat-label">Updated Recently</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo number_format($rateStats['needs_update']); ?></div>
                <div class="stat-label">Needs Update</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">LKR <?php echo number_format($rateStats['avg_rate'], 0); ?></div>
                <div class="stat-label">Average Rate</div>
            </div>
        </div>

        <!-- Rates Management Table -->
        <div class="rates-section">
            <div class="section-header">
                <h2 class="section-title">💰 Hotel Room Rates</h2>
                <div class="search-container">
                    <input type="text" id="hotelSearch" placeholder="🔍 Search hotels by name or location..." 
                           class="search-input" autocomplete="off">
                </div>
            </div>

            <?php if (!empty($hotelsWithRates)): ?>
                <table class="rates-table" id="ratesTable">
                    <thead>
                        <tr>
                            <th>Hotel</th>
                            <th>Current Rate</th>
                            <th>Last Updated</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hotelsWithRates as $hotel): ?>
                            <tr>
                                <td>
                                    <div class="hotel-info">
                                        <div class="hotel-icon">
                                            <?php echo strtoupper(substr($hotel['hotel_name'], 0, 2)); ?>
                                        </div>
                                        <div class="hotel-details">
                                            <h4><?php echo htmlspecialchars($hotel['hotel_name']); ?></h4>
                                            <div class="hotel-location"><?php echo htmlspecialchars($hotel['location']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($hotel['rate']): ?>
                                        <div class="rate-display">LKR <?php echo number_format($hotel['rate'], 2); ?></div>
                                        <div class="rate-info">per night</div>
                                    <?php else: ?>
                                        <div class="rate-display" style="color: #c53030;">No Rate Set</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hotel['effective_date']): ?>
                                        <div><?php echo date('M j, Y', strtotime($hotel['effective_date'])); ?></div>
                                        <div class="rate-info">by <?php echo htmlspecialchars($hotel['rate_created_by']); ?></div>
                                    <?php else: ?>
                                        <div style="color: #718096;">Never updated</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $days = $hotel['days_since_update'] ?? 999;
                                    if (!$hotel['rate']) {
                                        echo '<span class="update-status status-none">No Rate</span>';
                                    } elseif ($days <= 90) {
                                        echo '<span class="update-status status-recent">Recent</span>';
                                    } elseif ($days <= 180) {
                                        echo '<span class="update-status status-moderate">Moderate</span>';
                                    } else {
                                        echo '<span class="update-status status-outdated">Outdated</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="action-btn btn-update" 
                                                onclick="showUpdateRateModal(<?php echo $hotel['id']; ?>, '<?php echo addslashes($hotel['hotel_name']); ?>', <?php echo $hotel['rate'] ?: 0; ?>)">
                                            Update Rate
                                        </button>
                                        <?php if ($hotel['rate']): ?>
                                            <button class="action-btn btn-history" 
                                                    onclick="showHistoryModal(<?php echo $hotel['id']; ?>, '<?php echo addslashes($hotel['hotel_name']); ?>')">
                                                History
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: #718096;">
                    <h3>No Hotels Found</h3>
                    <p>Please register hotels first before managing rates.</p>
                    <a href="../hotels/register.php" class="btn-primary" style="margin-top: 1rem; display: inline-block; text-decoration: none;">Register Hotel</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Update Rate Modal -->
    <div class="modal-overlay" id="updateRateModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Room Rate</h3>
                <button type="button" class="modal-close" onclick="hideModals()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_rate">
                    <input type="hidden" name="hotel_id" id="updateHotelId">
                    
                    <div class="form-group">
                        <label>Hotel</label>
                        <input type="text" id="updateHotelName" readonly style="background: #f7fafc;">
                    </div>
                    
                    <div class="form-group">
                        <label for="updateCurrentRate">Current Rate (LKR)</label>
                        <input type="text" id="updateCurrentRate" readonly style="background: #f7fafc;">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_rate">New Rate (LKR) <span class="required">*</span></label>
                        <input type="number" id="new_rate" name="new_rate" step="0.01" min="0" required placeholder="Enter new room rate">
                    </div>
                    
                    <div class="form-group">
                        <label for="effective_date">Effective Date <span class="required">*</span></label>
                        <input type="date" id="effective_date" name="effective_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Reason for rate change, market conditions, etc."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="hideModals()">Cancel</button>
                        <button type="submit" class="btn-primary">Update Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rate History Modal -->
    <div class="modal-overlay" id="historyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Rate History</h3>
                <button type="button" class="modal-close" onclick="hideModals()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="historyContent">
                    <!-- History will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        // Store rate history data safely
        window.RATE_HISTORY_DATA = <?php echo json_encode($rateHistory, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing page...');
            
            // Initialize effective date to today
            initializeEffectiveDate();
            
            // Initialize search functionality
            initializeSearch();
            
            // Initialize modal click handlers
            initializeModals();
            
            console.log('Page initialization complete!');
        });

        // Initialize effective date field
        function initializeEffectiveDate() {
            const effectiveDateField = document.getElementById('effective_date');
            if (effectiveDateField) {
                const today = new Date();
                effectiveDateField.value = today.toISOString().split('T')[0];
                
                // Set min/max dates
                const oneYearAgo = new Date();
                oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
                const oneYearFromNow = new Date();
                oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
                
                effectiveDateField.setAttribute('min', oneYearAgo.toISOString().split('T')[0]);
                effectiveDateField.setAttribute('max', oneYearFromNow.toISOString().split('T')[0]);
                
                console.log('Effective date initialized to:', effectiveDateField.value);
            }
        }

        // Initialize search functionality
        function initializeSearch() {
            const searchInput = document.getElementById('hotelSearch');
            if (searchInput) {
                console.log('Search input found, adding event listeners...');
                
                // Add multiple event listeners for better compatibility
                searchInput.addEventListener('input', performSearch);
                searchInput.addEventListener('keyup', performSearch);
                searchInput.addEventListener('search', performSearch);
                
                console.log('Search event listeners added successfully!');
                
                // Test search functionality
                setTimeout(() => {
                    console.log('Testing search function...');
                    performSearch();
                }, 500);
            } else {
                console.error('Search input not found!');
            }
        }

        // Initialize modal functionality
        function initializeModals() {
            // Add click handlers to modal overlays
            const updateModal = document.getElementById('updateRateModal');
            const historyModal = document.getElementById('historyModal');
            
            if (updateModal) {
                updateModal.addEventListener('click', function(e) {
                    if (e.target === updateModal) {
                        hideModals();
                    }
                });
            }
            
            if (historyModal) {
                historyModal.addEventListener('click', function(e) {
                    if (e.target === historyModal) {
                        hideModals();
                    }
                });
            }

            // Add escape key handler
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideModals();
                }
            });

            console.log('Modal event handlers initialized');
        }

        // SEARCH FUNCTIONALITY
        function performSearch() {
            console.log('Search function called!');
            
            const searchInput = document.getElementById('hotelSearch');
            if (!searchInput) {
                console.error('Search input not found!');
                return;
            }
            
            const searchTerm = searchInput.value.toLowerCase().trim();
            console.log('Searching for:', searchTerm);
            
            const tableBody = document.querySelector('#ratesTable tbody');
            if (!tableBody) {
                console.error('Table body not found!');
                return;
            }
            
            const rows = tableBody.querySelectorAll('tr:not(.no-results-row)');
            console.log('Found rows:', rows.length);
            
            let visibleCount = 0;
            
            // Remove existing no-results row
            const existingNoResults = tableBody.querySelector('.no-results-row');
            if (existingNoResults) {
                existingNoResults.remove();
            }
            
            // Filter rows
            rows.forEach((row, index) => {
                const hotelNameElement = row.querySelector('.hotel-details h4');
                const locationElement = row.querySelector('.hotel-location');
                
                if (hotelNameElement && locationElement) {
                    const hotelName = hotelNameElement.textContent.toLowerCase();
                    const location = locationElement.textContent.toLowerCase();
                    
                    console.log(`Row ${index}: "${hotelName}" - "${location}"`);
                    
                    if (searchTerm === '' || hotelName.includes(searchTerm) || location.includes(searchTerm)) {
                        row.style.display = '';
                        visibleCount++;
                        console.log(`✓ Showing row ${index}`);
                        
                        // Highlight search terms
                        if (searchTerm.length > 0) {
                            highlightText(hotelNameElement, searchTerm);
                            highlightText(locationElement, searchTerm);
                        } else {
                            removeHighlight(hotelNameElement);
                            removeHighlight(locationElement);
                        }
                    } else {
                        row.style.display = 'none';
                        console.log(`✗ Hiding row ${index}`);
                    }
                } else {
                    console.warn(`Row ${index} missing required elements`);
                }
            });
            
            console.log(`Search complete: ${visibleCount} of ${rows.length} rows visible`);
            
            // Show no results message if needed
            if (visibleCount === 0 && searchTerm.length > 0) {
                showNoResultsMessage(searchTerm, tableBody);
            }
        }

        // Highlight search terms
        function highlightText(element, searchTerm) {
            if (!element || !searchTerm) return;
            
            const originalText = element.getAttribute('data-original-text') || element.textContent;
            element.setAttribute('data-original-text', originalText);
            
            const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
            const highlightedText = originalText.replace(regex, '<span class="highlight">$1</span>');
            element.innerHTML = highlightedText;
        }

        // Remove highlighting
        function removeHighlight(element) {
            if (!element) return;
            
            const originalText = element.getAttribute('data-original-text');
            if (originalText) {
                element.textContent = originalText;
                element.removeAttribute('data-original-text');
            }
        }

        // Escape special regex characters
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\                // Set min/max dates');
        }

        // Show no results message
        function showNoResultsMessage(searchTerm, tableBody) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="5" class="no-results">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">🔍</div>
                    <h3>No Hotels Found</h3>
                    <p>No hotels match your search for "<strong>${escapeHtml(searchTerm)}</strong>"</p>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #9ca3af;">
                        Try searching by hotel name or location
                    </p>
                </td>
            `;
            tableBody.appendChild(noResultsRow);
        }

        // Escape HTML for safe display
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // MODAL FUNCTIONALITY
        function showUpdateRateModal(hotelId, hotelName, currentRate) {
            console.log('Opening update rate modal for hotel:', hotelName);
            
            try {
                // Set form values
                document.getElementById('updateHotelId').value = hotelId;
                document.getElementById('updateHotelName').value = hotelName;
                document.getElementById('updateCurrentRate').value = currentRate > 0 ? 
                    'LKR ' + new Intl.NumberFormat().format(currentRate) : 'No rate set';
                
                // Clear form fields
                document.getElementById('new_rate').value = '';
                document.getElementById('notes').value = '';
                
                // Show modal
                document.getElementById('updateRateModal').classList.add('active');
                
                // Focus on rate input
                setTimeout(() => {
                    document.getElementById('new_rate').focus();
                }, 100);
                
                console.log('Update rate modal opened successfully');
            } catch (error) {
                console.error('Error opening update rate modal:', error);
                alert('Error opening update form. Please try again.');
            }
        }

        function showHistoryModal(hotelId, hotelName) {
            console.log('Opening history modal for hotel:', hotelName);
            
            try {
                const historyContent = document.getElementById('historyContent');
                historyContent.innerHTML = '<div style="text-align: center; padding: 2rem;">Loading...</div>';
                
                // Get rate history from global data
                const rateHistory = window.RATE_HISTORY_DATA || {};
                const history = rateHistory[hotelId] || [];
                
                console.log('Rate history for hotel', hotelId, ':', history);
                
                let historyHtml = `<h4 style="margin-bottom: 1rem;">${escapeHtml(hotelName)} - Rate History</h4>`;
                
                if (history.length > 0) {
                    historyHtml += '<div>';
                    history.forEach((rate, index) => {
                        const isCurrent = rate.end_date === null || rate.end_date === '';
                        const endDate = rate.end_date ? 
                            new Date(rate.end_date).toLocaleDateString() : 'Current';
                        
                        historyHtml += `
                            <div class="history-item">
                                <div>
                                    <div class="history-rate">LKR ${new Intl.NumberFormat().format(parseFloat(rate.rate))}</div>
                                    <div class="history-meta">
                                        ${new Date(rate.effective_date).toLocaleDateString()} - ${endDate}<br>
                                        By: ${escapeHtml(rate.created_by)} | ${new Date(rate.created_at).toLocaleDateString()}
                                    </div>
                                </div>
                                ${isCurrent ? '<span class="current-indicator">CURRENT</span>' : ''}
                            </div>
                        `;
                    });
                    historyHtml += '</div>';
                } else {
                    historyHtml += '<div style="text-align: center; padding: 2rem; color: #718096;">No rate history found</div>';
                }
                
                historyContent.innerHTML = historyHtml;
                document.getElementById('historyModal').classList.add('active');
                
                console.log('History modal opened successfully');
            } catch (error) {
                console.error('Error opening history modal:', error);
                alert('Error loading rate history. Please try again.');
            }
        }

        function hideModals() {
            console.log('Hiding all modals');
            
            const updateModal = document.getElementById('updateRateModal');
            const historyModal = document.getElementById('historyModal');
            
            if (updateModal) {
                updateModal.classList.remove('active');
            }
            
            if (historyModal) {
                historyModal.classList.remove('active');
            }
        }

        // FORM VALIDATION
        document.addEventListener('DOMContentLoaded', function() {
            // Rate input validation
            const newRateInput = document.getElementById('new_rate');
            if (newRateInput) {
                newRateInput.addEventListener('input', function(e) {
                    const value = parseFloat(e.target.value);
                    if (value < 0) {
                        e.target.value = 0;
                    }
                    if (value > 1000000) {
                        e.target.value = 1000000;
                        alert('Maximum rate is LKR 1,000,000');
                    }
                });
            }

            // Form submission validation
            const updateForm = document.querySelector('form[method="POST"]');
            if (updateForm) {
                updateForm.addEventListener('submit', function(e) {
                    const newRate = parseFloat(document.getElementById('new_rate').value);
                    const effectiveDate = document.getElementById('effective_date').value;
                    
                    if (!newRate || newRate <= 0) {
                        e.preventDefault();
                        alert('Please enter a valid rate greater than 0.');
                        document.getElementById('new_rate').focus();
                        return;
                    }
                    
                    if (!effectiveDate) {
                        e.preventDefault();
                        alert('Please select an effective date.');
                        document.getElementById('effective_date').focus();
                        return;
                    }
                    
                    // Confirm rate change
                    const hotelName = document.getElementById('updateHotelName').value;
                    const currentRateText = document.getElementById('updateCurrentRate').value;
                    
                    const confirmMessage = `Confirm rate update for ${hotelName}?\n\n` +
                        `Current: ${currentRateText}\n` +
                        `New: LKR ${new Intl.NumberFormat().format(newRate)}\n` +
                        `Effective: ${new Date(effectiveDate).toLocaleDateString()}`;
                    
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                    }
                });
            }
        });

        // GLOBAL TEST FUNCTIONS (for debugging)
        window.testSearch = function() {
            console.log('Manual search test triggered');
            performSearch();
        };

        window.testModal = function() {
            console.log('Manual modal test triggered');
            showUpdateRateModal(1, 'Test Hotel', 5000);
        };

        // KEYBOARD SHORTCUTS
        document.addEventListener('keydown', function(e) {
            // Ctrl+F or Cmd+F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.getElementById('hotelSearch');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                const searchInput = document.getElementById('hotelSearch');
                if (searchInput === document.activeElement) {
                    searchInput.value = '';
                    performSearch();
                    searchInput.blur();
                }
            }
        });

        console.log('All JavaScript loaded successfully!');
    </script>
</body>
</html>