<?php
/**
 * Add New Bill Page - Final Fixed Version
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Core module for entering hotel bills with fraud prevention
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
        $db->beginTransaction();
        
        // Get form data
        $invoiceNumber = trim($_POST['invoice_number']);
        $hotelId = intval($_POST['hotel_id']);
        $checkIn = $_POST['check_in'];
        $checkOut = $_POST['check_out'];
        $roomCount = intval($_POST['room_count']);
        $waterCharge = floatval($_POST['water_charge'] ?? 0);
        $washingCharge = floatval($_POST['washing_charge'] ?? 0);
        $serviceCharge = floatval($_POST['service_charge'] ?? 0);
        $miscCharge = floatval($_POST['misc_charge'] ?? 0);
        $miscDescription = trim($_POST['misc_description'] ?? '');
        $employees = $_POST['employees'] ?? [];
        
        // Validate required fields
        if (empty($invoiceNumber) || empty($hotelId) || empty($checkIn) || empty($checkOut) || empty($roomCount)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate dates
        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);
        if ($checkOutDate <= $checkInDate) {
            throw new Exception('Check-out date must be after check-in date.');
        }
        
        // Check if invoice number already exists
        $existingInvoice = $db->fetchRow("SELECT id FROM bills WHERE invoice_number = ?", [$invoiceNumber]);
        if ($existingInvoice) {
            throw new Exception('Invoice number already exists. Please use a unique invoice number.');
        }
        
        // Get hotel rate
        $currentRate = $db->fetchRow(
            "SELECT id, rate FROM hotel_rates WHERE hotel_id = ? AND is_current = 1",
            [$hotelId]
        );
        if (!$currentRate) {
            throw new Exception('No current rate found for selected hotel.');
        }
        
        // Validate room count vs employee count
        if (!empty($employees) && count($employees) > ($roomCount * 2)) {
            throw new Exception('Too many employees for the number of rooms. Maximum 2 employees per room.');
        }
        
        // Calculate totals
        $totalNights = $checkInDate->diff($checkOutDate)->days;
        $baseAmount = $totalNights * $roomCount * $currentRate['rate'];
        $totalAmount = $baseAmount + $waterCharge + $washingCharge + $serviceCharge + $miscCharge;
        
        // Insert bill
        $billId = $db->insert(
            "INSERT INTO bills (invoice_number, hotel_id, rate_id, check_in, check_out, total_nights, room_count, base_amount, water_charge, washing_charge, service_charge, misc_charge, misc_description, total_amount, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$invoiceNumber, $hotelId, $currentRate['id'], $checkIn, $checkOut, $totalNights, $roomCount, $baseAmount, $waterCharge, $washingCharge, $serviceCharge, $miscCharge, $miscDescription, $totalAmount, $currentUser['id']]
        );
        
        // Insert employee assignments
        if (!empty($employees)) {
            foreach ($employees as $employeeId => $employeeData) {
                if (!isset($employeeData['check_in']) || !isset($employeeData['check_out'])) {
                    continue; // Skip if no dates provided
                }
                
                $empCheckIn = $employeeData['check_in'];
                $empCheckOut = $employeeData['check_out'];
                
                // Validate employee dates are within bill range
                $empCheckInDate = new DateTime($empCheckIn);
                $empCheckOutDate = new DateTime($empCheckOut);
                
                if ($empCheckInDate < $checkInDate || $empCheckOutDate > $checkOutDate) {
                    throw new Exception('Employee stay dates must be within bill date range.');
                }
                
                if ($empCheckOutDate <= $empCheckInDate) {
                    throw new Exception('Employee check-out date must be after check-in date.');
                }
                
                // Generate all dates between employee check-in and check-out
                $currentDate = clone $empCheckInDate;
                while ($currentDate < $empCheckOutDate) {
                    $stayDate = $currentDate->format('Y-m-d');
                    
                    // Check if employee is already assigned to another bill on this date
                    $conflictCheck = $db->fetchRow(
                        "SELECT b.invoice_number, h.hotel_name FROM bill_employees be 
                         JOIN bills b ON be.bill_id = b.id 
                         JOIN hotels h ON b.hotel_id = h.id 
                         WHERE be.employee_id = ? AND be.stay_date = ?",
                        [$employeeId, $stayDate]
                    );
                    
                    if ($conflictCheck) {
                        $employeeName = $db->fetchValue("SELECT name FROM employees WHERE id = ?", [$employeeId]);
                        throw new Exception("Employee {$employeeName} is already assigned to {$conflictCheck['hotel_name']} (Invoice: {$conflictCheck['invoice_number']}) on {$stayDate}");
                    }
                    
                    // Insert employee assignment for this date
                    $db->query(
                        "INSERT INTO bill_employees (bill_id, employee_id, stay_date) VALUES (?, ?, ?)",
                        [$billId, $employeeId, $stayDate]
                    );
                    
                    $currentDate->add(new DateInterval('P1D'));
                }
            }
        }
        
        // Log activity
        $db->query(
            "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [
                'bills',
                $billId,
                'INSERT',
                json_encode(['invoice_number' => $invoiceNumber, 'hotel_id' => $hotelId, 'total_amount' => $totalAmount]),
                $currentUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        
        $db->commit();
        
        $message = "Bill created successfully! Bill ID: {$billId}, Total Amount: LKR " . number_format($totalAmount, 2);
        $messageType = 'success';
        
        // Clear form data
        $_POST = array();
        
    } catch (Exception $e) {
        if ($db && $db->inTransaction()) {
            $db->rollback();
        }
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get hotels for dropdown
try {
    $db = getDB();
    $hotels = $db->fetchAll(
        "SELECT h.id, h.hotel_name, h.location, hr.rate 
         FROM hotels h 
         JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1 
         WHERE h.is_active = 1 
         ORDER BY h.hotel_name"
    );
    
    // Get active employees
    $employees = $db->fetchAll(
        "SELECT id, name, nic, designation FROM employees WHERE is_active = 1 ORDER BY name"
    );
} catch (Exception $e) {
    $hotels = [];
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Bill - Hotel Bill Tracking System</title>
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
            max-width: 1200px;
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: #718096;
        }

        .form-sections {
            display: grid;
            gap: 2rem;
        }

        .section {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .required {
            color: #e53e3e;
        }

        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: #f8fafc;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .rate-display {
            background: #ebf4ff;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .rate-display h4 {
            color: #2b6cb0;
            margin-bottom: 0.5rem;
        }

        .rate-amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2b6cb0;
        }

        /* Employee Assignment Styles */
        .employee-assignment-container {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .add-employee-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-add-employee {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-employee:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(72, 187, 120, 0.3);
        }

        .employee-count-info {
            color: #718096;
            font-size: 0.9rem;
        }

        .selected-employees {
            min-height: 200px;
        }

        .selected-employee-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .selected-employee-card:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }

        .selected-employee-card.has-conflicts {
            border-left: 4px solid #dc2626;
            background: #fef7f7;
        }

        .employee-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .employee-card-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-card-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .conflict-avatar {
            background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
        }

        .employee-card-details h4 {
            margin: 0;
            color: #2d3748;
            font-size: 1rem;
        }

        .employee-card-meta {
            color: #718096;
            font-size: 0.85rem;
        }

        .remove-employee-btn {
            background: #fed7d7;
            color: #c53030;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .remove-employee-btn:hover {
            background: #feb2b2;
        }

        .employee-conflict-warning {
            background: #fef5e7;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-size: 0.85rem;
        }

        .conflict-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #fed7aa;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 4px;
        }

        .conflict-item:hover {
            background: #fed7aa;
        }

        .conflict-item:last-child {
            border-bottom: none;
        }

        .conflict-details {
            flex: 1;
        }

        .conflict-hotel {
            font-weight: 600;
            color: #c05621;
        }

        .conflict-dates {
            font-size: 0.8rem;
            color: #9c4221;
        }

        .conflict-badge {
            background: #fed7aa;
            color: #c05621;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stay-period-controls {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .date-input-group {
            display: flex;
            flex-direction: column;
        }

        .date-input-group label {
            font-size: 0.85rem;
            color: #4a5568;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .date-input-group input {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .nights-display {
            background: #ebf4ff;
            color: #2b6cb0;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
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
            max-width: 600px;
            max-height: 80vh;
            overflow: hidden;
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
            max-height: 60vh;
            overflow-y: auto;
        }

        .employee-search {
            margin-bottom: 1rem;
        }

        .employee-search input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .employee-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .employee-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .employee-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .employee-option:hover {
            border-color: #667eea;
            background: #f8fafc;
        }

        .employee-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f7fafc;
        }

        .employee-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .employee-details {
            flex: 1;
        }

        .employee-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 2px;
        }

        .employee-meta {
            color: #718096;
            font-size: 0.85rem;
        }

        .employee-status {
            text-align: right;
        }

        .available-badge {
            background: #c6f6d5;
            color: #2f855a;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .assigned-badge {
            background: #fed7d7;
            color: #c53030;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .conflict-status-badge {
            background: #fef5e7;
            color: #c05621;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .checking-badge {
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Hotel Search Styles */
        .hotel-search-container {
            position: relative;
        }

        .hotel-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .hotel-dropdown.active {
            display: block;
        }

        .hotel-option {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .hotel-option:hover {
            background: #f8fafc;
        }

        .hotel-option.selected {
            background: #ebf4ff;
            border-left: 4px solid #667eea;
        }

        .hotel-info .hotel-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 2px;
        }

        .hotel-info .hotel-location {
            font-size: 0.85rem;
            color: #718096;
        }

        .hotel-rate {
            font-weight: 600;
            color: #667eea;
            font-size: 0.9rem;
        }

        /* Total Calculation */
        .calculation-section {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .calculation-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }

        .calculation-row.total {
            border-top: 2px solid #2d3748;
            margin-top: 1rem;
            padding-top: 1rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: #2d3748;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
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

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
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

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }

            .stay-period-controls {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .add-employee-section {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">Add New Bill</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">Create Hotel Bill</h2>
                <p class="form-subtitle">Enter hotel bill details with employee assignments and additional charges</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="billForm">
                <div class="form-sections">
                    <!-- Basic Information -->
                    <div class="section">
                        <h3 class="section-title">🏨 Basic Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="invoice_number">Invoice Number <span class="required">*</span></label>
                                <input type="text" id="invoice_number" name="invoice_number" 
                                       value="<?php echo htmlspecialchars($_POST['invoice_number'] ?? ''); ?>" 
                                       required maxlength="50" placeholder="Enter unique invoice number">
                            </div>

                            <div class="form-group">
                                <label for="hotel_id">Select Hotel <span class="required">*</span></label>
                                <div class="hotel-search-container">
                                    <input type="text" id="hotelSearch" placeholder="Search hotels by name or location..." 
                                           onkeyup="searchHotels()" onfocus="showHotelDropdown()" 
                                           autocomplete="off">
                                    <input type="hidden" id="hotel_id" name="hotel_id" required>
                                    <div class="hotel-dropdown" id="hotelDropdown">
                                        <?php foreach ($hotels as $hotel): ?>
                                            <div class="hotel-option" 
                                                 data-hotel-id="<?php echo $hotel['id']; ?>" 
                                                 data-rate="<?php echo $hotel['rate']; ?>"
                                                 onclick="selectHotel(<?php echo $hotel['id']; ?>, '<?php echo htmlspecialchars($hotel['hotel_name'] . ' - ' . $hotel['location']); ?>', <?php echo $hotel['rate']; ?>)">
                                                <div class="hotel-info">
                                                    <div class="hotel-name"><?php echo htmlspecialchars($hotel['hotel_name']); ?></div>
                                                    <div class="hotel-location"><?php echo htmlspecialchars($hotel['location']); ?></div>
                                                </div>
                                                <div class="hotel-rate">LKR <?php echo number_format($hotel['rate'], 2); ?>/night</div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div id="rateDisplay" class="rate-display" style="display: none;">
                                    <h4>Current Room Rate</h4>
                                    <div class="rate-amount">LKR <span id="currentRate">0</span> per night</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date and Room Information -->
                    <div class="section">
                        <h3 class="section-title">📅 Stay Details</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="check_in">Check-in Date <span class="required">*</span></label>
                                <input type="date" id="check_in" name="check_in" 
                                       value="<?php echo htmlspecialchars($_POST['check_in'] ?? ''); ?>" 
                                       required onchange="updateDateRange()">
                            </div>

                            <div class="form-group">
                                <label for="check_out">Check-out Date <span class="required">*</span></label>
                                <input type="date" id="check_out" name="check_out" 
                                       value="<?php echo htmlspecialchars($_POST['check_out'] ?? ''); ?>" 
                                       required onchange="updateDateRange()">
                            </div>

                            <div class="form-group">
                                <label for="room_count">Number of Rooms <span class="required">*</span></label>
                                <input type="number" id="room_count" name="room_count" 
                                       value="<?php echo htmlspecialchars($_POST['room_count'] ?? ''); ?>" 
                                       required min="1" max="50" onchange="calculateTotal()">
                            </div>
                        </div>
                    </div>

                    <!-- Employee Assignment -->
                    <div class="section">
                        <h3 class="section-title">👥 Employee Assignment</h3>
                        <p style="color: #718096; margin-bottom: 1rem;">
                            Select employees and customize their stay periods. Each employee can have different check-in/check-out dates within the bill period.
                        </p>
                        
                        <div class="employee-assignment-container">
                            <!-- Add Employee Button -->
                            <div class="add-employee-section">
                                <button type="button" class="btn-add-employee" onclick="showEmployeeSelector()">
                                    + Add Employee to Bill
                                </button>
                                <span class="employee-count-info">
                                    <span id="selectedEmployeeCount">0</span> employees selected
                                </span>
                            </div>

                            <!-- Selected Employees List -->
                            <div class="selected-employees" id="selectedEmployeesList">
                                <div class="no-employees-message" id="noEmployeesMessage">
                                    <div style="text-align: center; padding: 2rem; color: #718096;">
                                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                                        <h4>No Employees Assigned</h4>
                                        <p>Click "Add Employee to Bill" to assign crew members to this hotel bill.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Charges -->
                    <div class="section">
                        <h3 class="section-title">💰 Additional Charges</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="water_charge">Water Charges (LKR)</label>
                                <input type="number" id="water_charge" name="water_charge" 
                                       value="<?php echo htmlspecialchars($_POST['water_charge'] ?? '0'); ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="washing_charge">Vehicle Washing (LKR)</label>
                                <input type="number" id="washing_charge" name="washing_charge" 
                                       value="<?php echo htmlspecialchars($_POST['washing_charge'] ?? '0'); ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="service_charge">Service Charge (LKR)</label>
                                <input type="number" id="service_charge" name="service_charge" 
                                       value="<?php echo htmlspecialchars($_POST['service_charge'] ?? '0'); ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="misc_charge">Miscellaneous (LKR)</label>
                                <input type="number" id="misc_charge" name="misc_charge" 
                                       value="<?php echo htmlspecialchars($_POST['misc_charge'] ?? '0'); ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group full-width">
                                <label for="misc_description">Miscellaneous Description</label>
                                <textarea id="misc_description" name="misc_description" rows="2" 
                                          placeholder="Describe miscellaneous charges if any"><?php echo htmlspecialchars($_POST['misc_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Total Calculation -->
                    <div class="section">
                        <h3 class="section-title">🧮 Bill Summary</h3>
                        <div class="calculation-section">
                            <div class="calculation-row">
                                <span>Room Rate × Nights × Rooms:</span>
                                <span>LKR <span id="baseAmount">0.00</span></span>
                            </div>
                            <div class="calculation-row">
                                <span>Water Charges:</span>
                                <span>LKR <span id="waterAmount">0.00</span></span>
                            </div>
                            <div class="calculation-row">
                                <span>Vehicle Washing:</span>
                                <span>LKR <span id="washingAmount">0.00</span></span>
                            </div>
                            <div class="calculation-row">
                                <span>Service Charge:</span>
                                <span>LKR <span id="serviceAmount">0.00</span></span>
                            </div>
                            <div class="calculation-row">
                                <span>Miscellaneous:</span>
                                <span>LKR <span id="miscAmount">0.00</span></span>
                            </div>
                            <div class="calculation-row total">
                                <span>Total Amount:</span>
                                <span>LKR <span id="totalAmount">0.00</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../dashboard.php'">Cancel</button>
                    <button type="submit" class="btn">Create Bill</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Employee Selector Modal -->
    <div class="modal-overlay" id="employeeSelectorModal" onclick="hideEmployeeSelector(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>Select Employee</h3>
                <button type="button" class="modal-close" onclick="hideEmployeeSelector()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="employee-search">
                    <input type="text" id="employeeSearch" placeholder="Search by name or NIC..." oninput="filterEmployees()">
                </div>
                <div class="employee-list">
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $employee): ?>
                            <div class="employee-option" data-employee-id="<?php echo $employee['id']; ?>" onclick="selectEmployee(<?php echo $employee['id']; ?>)">
                                <div class="employee-avatar">
                                    <?php echo strtoupper(substr($employee['name'], 0, 2)); ?>
                                </div>
                                <div class="employee-details">
                                    <div class="employee-name"><?php echo htmlspecialchars($employee['name']); ?></div>
                                    <div class="employee-meta">
                                        NIC: <?php echo htmlspecialchars($employee['nic']); ?>
                                        <?php if ($employee['designation']): ?>
                                            • <?php echo htmlspecialchars($employee['designation']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="employee-status" id="status_<?php echo $employee['id']; ?>">
                                    <span class="available-badge">Available</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: #718096;">
                            <p>No employees found.</p>
                            <a href="../employees/register.php" class="btn" style="margin-top: 1rem;">Register Employees</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedEmployees = new Map();
        let billStartDate = null;
        let billEndDate = null;
        let currentRate = 0;

        // Hotel search and selection functions
        function showHotelDropdown() {
            document.getElementById('hotelDropdown').classList.add('active');
        }

        function hideHotelDropdown() {
            setTimeout(() => {
                document.getElementById('hotelDropdown').classList.remove('active');
            }, 200);
        }

        function searchHotels() {
            const searchTerm = document.getElementById('hotelSearch').value.toLowerCase();
            const hotelOptions = document.querySelectorAll('.hotel-option');
            
            hotelOptions.forEach(option => {
                const hotelName = option.querySelector('.hotel-name').textContent.toLowerCase();
                const location = option.querySelector('.hotel-location').textContent.toLowerCase();
                
                if (hotelName.includes(searchTerm) || location.includes(searchTerm)) {
                    option.style.display = 'flex';
                } else {
                    option.style.display = 'none';
                }
            });
            
            showHotelDropdown();
        }

        function selectHotel(hotelId, hotelText, rate) {
            document.getElementById('hotelSearch').value = hotelText;
            document.getElementById('hotel_id').value = hotelId;
            currentRate = parseFloat(rate) || 0;
            
            // Update rate display
            const rateDisplay = document.getElementById('rateDisplay');
            const rateSpan = document.getElementById('currentRate');
            rateSpan.textContent = new Intl.NumberFormat().format(currentRate);
            rateDisplay.style.display = 'block';
            
            hideHotelDropdown();
            calculateTotal();
        }

        // Update date range and refresh employee availability
        function updateDateRange() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            
            if (checkIn && checkOut) {
                billStartDate = new Date(checkIn);
                billEndDate = new Date(checkOut);
                
                // Update all selected employees with new date constraints
                selectedEmployees.forEach((employee, employeeId) => {
                    // Reset dates to bill range if they're outside
                    if (new Date(employee.checkIn) < billStartDate) {
                        employee.checkIn = checkIn;
                    }
                    if (new Date(employee.checkOut) > billEndDate) {
                        employee.checkOut = checkOut;
                    }
                    
                    // Re-check conflicts for this employee
                    checkSingleEmployeeConflicts(employeeId);
                });
                
                refreshSelectedEmployeesList();
                calculateTotal();
                
                // Refresh employee availability in modal
                setTimeout(() => {
                    checkEmployeeAvailability();
                }, 500);
            }
        }

        // Show employee selector modal
        function showEmployeeSelector() {
            if (!billStartDate || !billEndDate) {
                alert('Please select check-in and check-out dates first.');
                return;
            }
            
            const modal = document.getElementById('employeeSelectorModal');
            modal.classList.add('active');
            document.getElementById('employeeSearch').focus();
            checkEmployeeAvailability();
        }

        // Hide employee selector modal
        function hideEmployeeSelector(event) {
            if (!event || event.target.classList.contains('modal-overlay') || event.target.classList.contains('modal-close')) {
                const modal = document.getElementById('employeeSelectorModal');
                modal.classList.remove('active');
                document.getElementById('employeeSearch').value = '';
                filterEmployees();
            }
        }

        // Filter employees in modal
        function filterEmployees() {
            const searchTerm = document.getElementById('employeeSearch').value.toLowerCase();
            const employeeOptions = document.querySelectorAll('.employee-option');
            
            employeeOptions.forEach(option => {
                const name = option.querySelector('.employee-name').textContent.toLowerCase();
                const nic = option.querySelector('.employee-meta').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || nic.includes(searchTerm)) {
                    option.style.display = 'flex';
                } else {
                    option.style.display = 'none';
                }
            });
        }

        // Select employee from modal (FIXED)
        async function selectEmployee(employeeId) {
            // Prevent duplicate selection
            if (selectedEmployees.has(employeeId)) {
                alert('This employee is already assigned to this bill.');
                return;
            }
            
            // Show checking status immediately
            const statusElement = document.getElementById(`status_${employeeId}`);
            if (statusElement) {
                statusElement.innerHTML = '<span class="checking-badge">Checking...</span>';
            }
            
            // Get employee details
            const employeeOption = document.querySelector(`[data-employee-id="${employeeId}"]`);
            const employeeName = employeeOption.querySelector('.employee-name').textContent;
            const employeeMeta = employeeOption.querySelector('.employee-meta').textContent;
            
            try {
                // Check for conflicts
                const conflicts = await checkEmployeeConflicts(employeeId, 
                    document.getElementById('check_in').value, 
                    document.getElementById('check_out').value);
                
                // Add to selected employees with conflict data
                selectedEmployees.set(employeeId, {
                    id: employeeId,
                    name: employeeName,
                    meta: employeeMeta,
                    checkIn: document.getElementById('check_in').value,
                    checkOut: document.getElementById('check_out').value,
                    conflicts: conflicts,
                    hasConflicts: conflicts.length > 0
                });
                
                // Update status to assigned
                if (statusElement) {
                    statusElement.innerHTML = '<span class="assigned-badge">Assigned</span>';
                }
                
                refreshSelectedEmployeesList();
                hideEmployeeSelector();
                calculateTotal();
                
            } catch (error) {
                console.error('Error checking conflicts:', error);
                alert('Error checking employee conflicts. Please try again.');
                
                // Reset status
                if (statusElement) {
                    statusElement.innerHTML = '<span class="available-badge">Available</span>';
                }
            }
        }

        // Check for employee conflicts (REAL AJAX CALL)
        async function checkEmployeeConflicts(employeeId, checkIn, checkOut) {
            try {
                const response = await fetch('../api/check_employee_conflicts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        check_in: checkIn,
                        check_out: checkOut
                    })
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.data) {
                        return data.data.conflicts || [];
                    } else {
                        console.log('API response:', data.message);
                        return [];
                    }
                } else {
                    console.error('API request failed:', response.status);
                    return [];
                }
            } catch (error) {
                console.error('Error checking conflicts:', error);
                return [];
            }
        }

        // Check single employee conflicts (for date updates)
        async function checkSingleEmployeeConflicts(employeeId) {
            const employee = selectedEmployees.get(employeeId);
            if (!employee) return;
            
            try {
                const conflicts = await checkEmployeeConflicts(employeeId, employee.checkIn, employee.checkOut);
                employee.conflicts = conflicts;
                employee.hasConflicts = conflicts.length > 0;
                selectedEmployees.set(employeeId, employee);
                refreshSelectedEmployeesList();
            } catch (error) {
                console.error('Error rechecking conflicts:', error);
            }
        }

        // Remove employee from bill (FIXED)
        function removeEmployee(employeeId) {
            if (confirm('Remove this employee from the bill?')) {
                selectedEmployees.delete(employeeId);
                refreshSelectedEmployeesList();
                calculateTotal();
                
                // Update status in modal if open
                const statusElement = document.getElementById(`status_${employeeId}`);
                if (statusElement) {
                    statusElement.innerHTML = '<span class="available-badge">Available</span>';
                }
            }
        }

        // Update employee stay dates
        function updateEmployeeDates(employeeId) {
            const checkInInput = document.getElementById(`emp_checkin_${employeeId}`);
            const checkOutInput = document.getElementById(`emp_checkout_${employeeId}`);
            const nightsDisplay = document.getElementById(`emp_nights_${employeeId}`);
            
            const newCheckIn = checkInInput.value;
            const newCheckOut = checkOutInput.value;
            
            // Validate dates are within bill range
            if (new Date(newCheckIn) < billStartDate || new Date(newCheckOut) > billEndDate) {
                alert('Employee dates must be within the bill period.');
                // Reset to previous values
                const employee = selectedEmployees.get(employeeId);
                checkInInput.value = employee.checkIn;
                checkOutInput.value = employee.checkOut;
                return;
            }
            
            if (new Date(newCheckOut) <= new Date(newCheckIn)) {
                alert('Check-out date must be after check-in date.');
                return;
            }
            
            // Update employee data
            const employee = selectedEmployees.get(employeeId);
            employee.checkIn = newCheckIn;
            employee.checkOut = newCheckOut;
            
            // Update nights display
            const nights = Math.ceil((new Date(newCheckOut) - new Date(newCheckIn)) / (1000 * 60 * 60 * 24));
            nightsDisplay.textContent = `${nights} nights`;
            
            selectedEmployees.set(employeeId, employee);
            calculateTotal();
            
            // Re-check conflicts with new dates
            checkSingleEmployeeConflicts(employeeId);
        }

        // Refresh the selected employees list display (ENHANCED)
        function refreshSelectedEmployeesList() {
            const container = document.getElementById('selectedEmployeesList');
            const noMessage = document.getElementById('noEmployeesMessage');
            const countSpan = document.getElementById('selectedEmployeeCount');
            
            countSpan.textContent = selectedEmployees.size;
            
            if (selectedEmployees.size === 0) {
                noMessage.style.display = 'block';
                container.innerHTML = '';
                container.appendChild(noMessage);
                return;
            }
            
            noMessage.style.display = 'none';
            
            let html = '';
            selectedEmployees.forEach((employee, employeeId) => {
                const checkInDate = new Date(employee.checkIn);
                const checkOutDate = new Date(employee.checkOut);
                const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                
                // Generate conflicts warning if any
                let conflictsHtml = '';
                if (employee.conflicts && employee.conflicts.length > 0) {
                    conflictsHtml = `
                        <div class="employee-conflict-warning">
                            <div style="font-weight: 600; color: #c05621; margin-bottom: 0.5rem;">
                                ⚠️ DUPLICATE ASSIGNMENTS DETECTED (${employee.conflicts.length} conflicts)
                            </div>
                            ${employee.conflicts.map(conflict => {
                                return `
                                    <div class="conflict-item" 
                                         onmouseover="showConflictDetails(${conflict.bill_id}, '${conflict.invoice}', '${conflict.hotel}', '${conflict.bill_details.check_in}', '${conflict.bill_details.check_out}', '${conflict.bill_details.total_amount}', '${conflict.bill_details.submitted_by}')" 
                                         onmouseout="hideConflictDetails()"
                                         style="cursor: pointer;">
                                        <div class="conflict-details">
                                            <div class="conflict-hotel">${conflict.hotel}</div>
                                            <div class="conflict-dates">
                                                Date: ${new Date(conflict.date).toLocaleDateString()} | 
                                                Invoice: ${conflict.invoice} | 
                                                Bill ID: ${conflict.bill_id}
                                            </div>
                                        </div>
                                        <div class="conflict-badge">DUPLICATE</div>
                                    </div>
                                `;
                            }).join('')}
                            <div style="margin-top: 0.75rem; font-size: 0.8rem; color: #9c4221; padding: 0.5rem; background: #fed7aa; border-radius: 4px;">
                                <strong>⚠️ WARNING:</strong> This employee is already assigned to other bills on overlapping dates. 
                                This will create duplicate bookings. Hover over conflicts above for detailed bill information.
                            </div>
                        </div>
                    `;
                }
                
                html += `
                    <div class="selected-employee-card ${employee.hasConflicts ? 'has-conflicts' : ''}">
                        <div class="employee-card-header">
                            <div class="employee-card-info">
                                <div class="employee-card-avatar ${employee.hasConflicts ? 'conflict-avatar' : ''}">
                                    ${employee.name.substring(0, 2).toUpperCase()}
                                </div>
                                <div class="employee-card-details">
                                    <h4>${employee.name} ${employee.hasConflicts ? '<span style="color: #dc2626; font-size: 0.8rem;">(CONFLICTS)</span>' : ''}</h4>
                                    <div class="employee-card-meta">${employee.meta}</div>
                                </div>
                            </div>
                            <button type="button" class="remove-employee-btn" onclick="removeEmployee(${employeeId})" title="Remove employee from bill">
                                Remove
                            </button>
                        </div>
                        ${conflictsHtml}
                        <div class="stay-period-controls">
                            <div class="date-input-group">
                                <label>Check-in Date</label>
                                <input type="date" 
                                       id="emp_checkin_${employeeId}" 
                                       name="employees[${employeeId}][check_in]"
                                       value="${employee.checkIn}" 
                                       min="2020-01-01"
                                       max="2030-12-31"
                                       onchange="updateEmployeeDates(${employeeId})">
                            </div>
                            <div class="date-input-group">
                                <label>Check-out Date</label>
                                <input type="date" 
                                       id="emp_checkout_${employeeId}" 
                                       name="employees[${employeeId}][check_out]"
                                       value="${employee.checkOut}" 
                                       min="2020-01-01"
                                       max="2030-12-31"
                                       onchange="updateEmployeeDates(${employeeId})">
                            </div>
                            <div class="nights-display" id="emp_nights_${employeeId}">
                                ${nights} nights
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Show conflict details on hover (ENHANCED)
        function showConflictDetails(billId, invoice, hotel, checkIn, checkOut, totalAmount, submittedBy) {
            // Remove existing tooltip
            hideConflictDetails();
            
            // Create tooltip with bill details
            const tooltip = document.createElement('div');
            tooltip.id = 'conflict-tooltip';
            tooltip.style.cssText = `
                position: fixed;
                background: #2d3748;
                color: white;
                padding: 1rem;
                border-radius: 8px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                z-index: 1000;
                max-width: 350px;
                font-size: 0.85rem;
                pointer-events: none;
                border: 2px solid #fed7aa;
            `;
            
            const billDetails = `
                <div style="font-weight: 600; margin-bottom: 0.5rem; color: #fed7aa;">⚠️ Conflicting Bill Details</div>
                <div style="margin-bottom: 0.5rem;">
                    <strong>Invoice:</strong> ${invoice}<br>
                    <strong>Hotel:</strong> ${hotel}<br>
                    <strong>Bill ID:</strong> ${billId}
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <strong>Stay Period:</strong><br>
                    Check-in: ${new Date(checkIn).toLocaleDateString()}<br>
                    Check-out: ${new Date(checkOut).toLocaleDateString()}
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <strong>Bill Details:</strong><br>
                    Total: LKR ${parseFloat(totalAmount).toLocaleString()}<br>
                    Submitted by: ${submittedBy}
                </div>
                <div style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px solid #4a5568; opacity: 0.8; font-size: 0.8rem;">
                    This employee is already assigned to this bill on overlapping dates
                </div>
            `;
            
            tooltip.innerHTML = billDetails;
            document.body.appendChild(tooltip);
            
            // Position tooltip near mouse
            document.addEventListener('mousemove', updateTooltipPosition);
        }

        function updateTooltipPosition(e) {
            const tooltip = document.getElementById('conflict-tooltip');
            if (tooltip) {
                tooltip.style.left = (e.pageX + 10) + 'px';
                tooltip.style.top = (e.pageY - 50) + 'px';
            }
        }

        function hideConflictDetails() {
            const tooltip = document.getElementById('conflict-tooltip');
            if (tooltip) {
                tooltip.remove();
                document.removeEventListener('mousemove', updateTooltipPosition);
            }
        }

        // Check employee availability for the bill dates (REAL-TIME)
        async function checkEmployeeAvailability() {
            if (!billStartDate || !billEndDate) return;
            
            const statusElements = document.querySelectorAll('[id^="status_"]');
            
            // Update status for each employee
            for (const element of statusElements) {
                const employeeId = parseInt(element.id.split('_')[1]);
                
                if (selectedEmployees.has(employeeId)) {
                    element.innerHTML = '<span class="assigned-badge">Assigned</span>';
                } else {
                    // Check for conflicts
                    element.innerHTML = '<span class="checking-badge">Checking...</span>';
                    
                    try {
                        const conflicts = await checkEmployeeConflicts(
                            employeeId,
                            billStartDate.toISOString().split('T')[0],
                            billEndDate.toISOString().split('T')[0]
                        );
                        
                        if (conflicts.length > 0) {
                            element.innerHTML = `<span class="conflict-status-badge">Conflicts (${conflicts.length})</span>`;
                        } else {
                            element.innerHTML = '<span class="available-badge">Available</span>';
                        }
                    } catch (error) {
                        element.innerHTML = '<span class="available-badge">Available</span>';
                    }
                }
            }
        }

        // Calculate total amount
        function calculateTotal() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            const roomCount = parseInt(document.getElementById('room_count').value) || 0;
            const waterCharge = parseFloat(document.getElementById('water_charge').value) || 0;
            const washingCharge = parseFloat(document.getElementById('washing_charge').value) || 0;
            const serviceCharge = parseFloat(document.getElementById('service_charge').value) || 0;
            const miscCharge = parseFloat(document.getElementById('misc_charge').value) || 0;

            let totalNights = 0;
            if (checkIn && checkOut) {
                const start = new Date(checkIn);
                const end = new Date(checkOut);
                totalNights = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            }

            const baseAmount = currentRate * totalNights * roomCount;
            const totalAmount = baseAmount + waterCharge + washingCharge + serviceCharge + miscCharge;

            // Update display
            document.getElementById('baseAmount').textContent = new Intl.NumberFormat('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(baseAmount);

            document.getElementById('waterAmount').textContent = new Intl.NumberFormat('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(waterCharge);

            document.getElementById('washingAmount').textContent = new Intl.NumberFormat('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(washingCharge);

            document.getElementById('serviceAmount').textContent = new Intl.NumberFormat('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(serviceCharge);

            document.getElementById('miscAmount').textContent = new Intl.NumberFormat('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(miscCharge);

            document.getElementById('totalAmount').textContent = new Intl.NumberFormat('en-LK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(totalAmount);
        }

        // Form validation before submission (STRICT CONFLICT PREVENTION)
        document.getElementById('billForm').addEventListener('submit', function(e) {
            // Basic validation
            const invoiceNumber = document.getElementById('invoice_number').value.trim();
            const hotelId = document.getElementById('hotel_id').value;
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            const roomCount = parseInt(document.getElementById('room_count').value);

            if (!invoiceNumber || !hotelId || !checkIn || !checkOut || !roomCount) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            // Date validation
            const startDate = new Date(checkIn);
            const endDate = new Date(checkOut);
            
            if (endDate <= startDate) {
                e.preventDefault();
                alert('Check-out date must be after check-in date.');
                return;
            }

            // Check if any employees are selected
            if (selectedEmployees.size === 0) {
                if (!confirm('No employees have been assigned to this bill. Continue anyway?')) {
                    e.preventDefault();
                    return;
                }
            }

            // STRICT CONFLICT CHECKING - PREVENT SUBMISSION WITH CONFLICTS
            let hasConflicts = false;
            let conflictedEmployees = [];
            let totalConflicts = 0;
            
            selectedEmployees.forEach((employee) => {
                if (employee.conflicts && employee.conflicts.length > 0) {
                    hasConflicts = true;
                    conflictedEmployees.push(employee.name);
                    totalConflicts += employee.conflicts.length;
                }
            });

            // BLOCK SUBMISSION IF CONFLICTS EXIST
            if (hasConflicts) {
                e.preventDefault();
                
                const conflictMessage = `
🚫 CANNOT SUBMIT BILL - DUPLICATE ASSIGNMENTS DETECTED!

The following employees have conflicting assignments:
${conflictedEmployees.map(name => `• ${name}`).join('\n')}

Total conflicts found: ${totalConflicts}

SOLUTION:
1. Remove conflicted employees from this bill, OR
2. Verify the existing bills are correct and remove duplicates, OR  
3. Adjust the dates to avoid overlaps

This bill cannot be submitted until all conflicts are resolved.
                `;
                
                alert(conflictMessage);
                
                // Scroll to first conflicted employee
                const firstConflictCard = document.querySelector('.selected-employee-card.has-conflicts');
                if (firstConflictCard) {
                    firstConflictCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Highlight the conflicted employee
                    firstConflictCard.style.border = '3px solid #dc2626';
                    firstConflictCard.style.animation = 'pulse 2s ease-in-out 3';
                }
                
                return;
            }

            // Validate employee room capacity
            const maxEmployees = roomCount * 2; // 2 employees per room max
            if (selectedEmployees.size > maxEmployees) {
                e.preventDefault();
                alert(`Too many employees assigned. Maximum ${maxEmployees} employees allowed for ${roomCount} rooms (2 per room).`);
                return;
            }

            // Final confirmation for clean bill
            const totalAmount = document.getElementById('totalAmount').textContent;
            const employeeCount = selectedEmployees.size;
            
            if (!confirm(`✅ BILL VALIDATION PASSED\n\nConfirm creating bill for LKR ${totalAmount} with ${employeeCount} employees?\n\n• No conflicts detected\n• All validations passed\n\nThis action cannot be undone.`)) {
                e.preventDefault();
                return;
            }
        });

        // Auto-focus invoice number
        document.getElementById('invoice_number').focus();

        // Initialize calculations on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });

        // Invoice number formatting
        document.getElementById('invoice_number').addEventListener('input', function(e) {
            // Convert to uppercase and remove special characters
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
            e.target.value = value;
        });

        // Set date ranges (allow past dates for bills)
        const oneYearAgo = new Date();
        oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
        const minDate = oneYearAgo.toISOString().split('T')[0];
        
        const oneYearFromNow = new Date();
        oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
        const maxDate = oneYearFromNow.toISOString().split('T')[0];
        
        document.getElementById('check_in').setAttribute('min', minDate);
        document.getElementById('check_in').setAttribute('max', maxDate);
        document.getElementById('check_out').setAttribute('min', minDate);
        document.getElementById('check_out').setAttribute('max', maxDate);

        // Update check-out minimum when check-in changes
        document.getElementById('check_in').addEventListener('change', function() {
            const checkInDate = this.value;
            const checkOutInput = document.getElementById('check_out');
            const nextDay = new Date(checkInDate);
            nextDay.setDate(nextDay.getDate() + 1);
            checkOutInput.setAttribute('min', nextDay.toISOString().split('T')[0]);
            
            // Clear check-out if it's before new minimum
            if (checkOutInput.value && checkOutInput.value <= checkInDate) {
                checkOutInput.value = '';
            }
            
            updateDateRange();
        });

        // Close hotel dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.hotel-search-container')) {
                hideHotelDropdown();
            }
        });

        // Hide hotel dropdown on blur
        document.getElementById('hotelSearch').addEventListener('blur', hideHotelDropdown);

        // Update date range when check-out changes
        document.getElementById('check_out').addEventListener('change', function() {
            updateDateRange();
        });

        // Room count validation
        document.getElementById('room_count').addEventListener('input', function(e) {
            const value = parseInt(e.target.value);
            if (value > 50) {
                alert('Maximum 50 rooms allowed per bill. For larger bookings, please create multiple bills.');
                e.target.value = 50;
            }
            calculateTotal();
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideEmployeeSelector();
            }
        });

        // Add CSS animation for conflicts
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