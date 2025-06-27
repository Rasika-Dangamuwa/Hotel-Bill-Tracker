<?php
/**
 * Bill Edit Page - Complete Duplicate of Add.php with Edit Functionality
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Edit existing bills with full employee assignment functionality
 */

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in
requireLogin();

// Get bill ID from URL
$billId = intval($_GET['id'] ?? 0);

if (!$billId) {
    header('Location: view.php?error=invalid_bill_id');
    exit;
}

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
        
        // Check if invoice number already exists for other bills
        $existingInvoice = $db->fetchRow("SELECT id FROM bills WHERE invoice_number = ? AND id != ?", [$invoiceNumber, $billId]);
        if ($existingInvoice) {
            throw new Exception('Invoice number already exists in another bill. Please use a unique invoice number.');
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
        
        // Get original bill data for audit log
        $originalBill = $db->fetchRow("SELECT * FROM bills WHERE id = ?", [$billId]);
        if (!$originalBill) {
            throw new Exception('Bill not found.');
        }
        
        // Update bill
        $db->query(
            "UPDATE bills SET 
                invoice_number = ?, hotel_id = ?, rate_id = ?, check_in = ?, check_out = ?, 
                total_nights = ?, room_count = ?, base_amount = ?, water_charge = ?, 
                washing_charge = ?, service_charge = ?, misc_charge = ?, misc_description = ?, 
                total_amount = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $invoiceNumber, $hotelId, $currentRate['id'], $checkIn, $checkOut,
                $totalNights, $roomCount, $baseAmount, $waterCharge,
                $washingCharge, $serviceCharge, $miscCharge, $miscDescription,
                $totalAmount, $billId
            ]
        );
        
        // Delete existing employee assignments
        $db->query("DELETE FROM bill_employees WHERE bill_id = ?", [$billId]);
        
        // Insert new employee assignments
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
                    
                    // Check if employee is already assigned to another bill on this date (EXCLUDE CURRENT BILL)
                    $conflictCheck = $db->fetchRow(
                        "SELECT b.invoice_number, h.hotel_name FROM bill_employees be 
                         JOIN bills b ON be.bill_id = b.id 
                         JOIN hotels h ON b.hotel_id = h.id 
                         WHERE be.employee_id = ? AND be.stay_date = ? AND b.id != ?",
                        [$employeeId, $stayDate, $billId]
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
        $changes = [];
        if ($originalBill['invoice_number'] != $invoiceNumber) $changes['invoice_number'] = $invoiceNumber;
        if ($originalBill['hotel_id'] != $hotelId) $changes['hotel_id'] = $hotelId;
        if ($originalBill['total_amount'] != $totalAmount) $changes['total_amount'] = $totalAmount;
        
        $db->query(
            "INSERT INTO audit_log (table_name, record_id, action, old_values, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                'bills',
                $billId,
                'UPDATE',
                json_encode(['original_total' => $originalBill['total_amount']]),
                json_encode($changes),
                $currentUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        
        $db->commit();
        
        $message = "Bill updated successfully! Total Amount: LKR " . number_format($totalAmount, 2);
        $messageType = 'success';
        
        // Clear form data but keep current data for display
        // $_POST = array();
        
    } catch (Exception $e) {
        if ($db && $db->inTransaction()) {
            $db->rollback();
        }
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get bill details
try {
    $db = getDB();
    
    // Get bill with hotel and user information
    $bill = $db->fetchRow(
        "SELECT b.*, h.hotel_name, h.location, hr.rate as room_rate, u.name as submitted_by_name
         FROM bills b 
         JOIN hotels h ON b.hotel_id = h.id 
         JOIN hotel_rates hr ON b.rate_id = hr.id
         JOIN users u ON b.submitted_by = u.id
         WHERE b.id = ?",
        [$billId]
    );
    
    if (!$bill) {
        header('Location: view.php?error=bill_not_found');
        exit;
    }
    
    // Get hotels for dropdown
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
    
    // Get current employee assignments
    $currentEmployees = $db->fetchAll(
        "SELECT e.id, e.name, e.nic, e.designation, e.department,
                MIN(be.stay_date) as check_in, MAX(be.stay_date) as check_out
         FROM bill_employees be 
         JOIN employees e ON be.employee_id = e.id 
         WHERE be.bill_id = ? 
         GROUP BY e.id, e.name, e.nic, e.designation, e.department
         ORDER BY e.name",
        [$billId]
    );
    
    // Adjust check_out dates (add 1 day since we store stay dates, not check-out dates)
    foreach ($currentEmployees as &$emp) {
        $checkOutDate = new DateTime($emp['check_out']);
        $checkOutDate->add(new DateInterval('P1D'));
        $emp['check_out'] = $checkOutDate->format('Y-m-d');
    }
    
} catch (Exception $e) {
    header('Location: view.php?error=database_error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bill - <?php echo htmlspecialchars($bill['invoice_number']); ?></title>
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

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .edit-warning {
            background: linear-gradient(135deg, #fef5e7, #fed7aa);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .warning-icon {
            font-size: 2rem;
            color: #d69e2e;
        }

        .warning-text {
            flex: 1;
        }

        .warning-title {
            font-weight: 700;
            color: #c05621;
            margin-bottom: 0.5rem;
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

        /* Employee Assignment Styles - Exact copy from add.php */
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

        /* Modal Styles - Exact copy from add.php */
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

        .checking-badge {
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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

        .btn-primary {
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
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

            .btn-primary, .btn-secondary {
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

            .header-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">Edit Bill - <?php echo htmlspecialchars($bill['invoice_number']); ?></h1>
            <div class="header-actions">
                <a href="view.php" class="btn">← Back to Bills</a>
                <a href="details.php?id=<?php echo $bill['id']; ?>" class="btn" target="_blank">View Details</a>
                <button type="button" class="btn btn-danger" onclick="deleteBill()">🗑️ Delete Bill</button>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Edit Warning -->
        <div class="edit-warning">
            <div class="warning-icon">⚠️</div>
            <div class="warning-text">
                <div class="warning-title">Editing Existing Bill</div>
                <div>You are modifying Bill ID <?php echo $bill['id']; ?> (<?php echo htmlspecialchars($bill['invoice_number']); ?>). All changes will be tracked and logged for audit purposes.</div>
            </div>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">Edit Hotel Bill</h2>
                <p class="form-subtitle">Modify hotel bill details, employee assignments, and additional charges</p>
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
                                       value="<?php echo htmlspecialchars($_POST['invoice_number'] ?? $bill['invoice_number']); ?>" 
                                       required maxlength="50" placeholder="Enter unique invoice number">
                            </div>

                            <div class="form-group">
                                <label for="hotel_id">Select Hotel <span class="required">*</span></label>
                                <div class="hotel-search-container">
                                    <input type="text" id="hotelSearch" placeholder="Search hotels by name or location..." 
                                           onkeyup="searchHotels()" onfocus="showHotelDropdown()" 
                                           autocomplete="off"
                                           value="<?php echo htmlspecialchars($bill['hotel_name'] . ' - ' . $bill['location']); ?>">
                                    <input type="hidden" id="hotel_id" name="hotel_id" 
                                           value="<?php echo $_POST['hotel_id'] ?? $bill['hotel_id']; ?>" required>
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
                                <div id="rateDisplay" class="rate-display" style="display: block;">
                                    <h4>Current Room Rate</h4>
                                    <div class="rate-amount">LKR <span id="currentRate"><?php echo number_format($bill['room_rate'], 2); ?></span> per night</div>
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
                                       value="<?php echo $_POST['check_in'] ?? $bill['check_in']; ?>" 
                                       required readonly disabled
                                       style="background: #f1f5f9; color: #64748b; cursor: not-allowed;">
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
                                    📌 Date range cannot be changed in edit mode. Delete and recreate bill to change dates.
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="check_out">Check-out Date <span class="required">*</span></label>
                                <input type="date" id="check_out" name="check_out" 
                                       value="<?php echo $_POST['check_out'] ?? $bill['check_out']; ?>" 
                                       required readonly disabled
                                       style="background: #f1f5f9; color: #64748b; cursor: not-allowed;">
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
                                    📌 Date range cannot be changed in edit mode. Delete and recreate bill to change dates.
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="room_count">Number of Rooms <span class="required">*</span></label>
                                <input type="number" id="room_count" name="room_count" 
                                       value="<?php echo $_POST['room_count'] ?? $bill['room_count']; ?>" 
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
                                       value="<?php echo $_POST['water_charge'] ?? $bill['water_charge']; ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="washing_charge">Vehicle Washing (LKR)</label>
                                <input type="number" id="washing_charge" name="washing_charge" 
                                       value="<?php echo $_POST['washing_charge'] ?? $bill['washing_charge']; ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="service_charge">Service Charge (LKR)</label>
                                <input type="number" id="service_charge" name="service_charge" 
                                       value="<?php echo $_POST['service_charge'] ?? $bill['service_charge']; ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="misc_charge">Miscellaneous (LKR)</label>
                                <input type="number" id="misc_charge" name="misc_charge" 
                                       value="<?php echo $_POST['misc_charge'] ?? $bill['misc_charge']; ?>" 
                                       min="0" step="0.01" onchange="calculateTotal()">
                            </div>

                            <div class="form-group full-width">
                                <label for="misc_description">Miscellaneous Description</label>
                                <textarea id="misc_description" name="misc_description" rows="2" 
                                          placeholder="Describe miscellaneous charges if any"><?php echo htmlspecialchars($_POST['misc_description'] ?? $bill['misc_description']); ?></textarea>
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
                    <button type="button" class="btn-secondary" onclick="window.location.href='view.php'">Cancel</button>
                    <button type="submit" class="btn-primary">Update Bill</button>
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
        const currentBillId = <?php echo $billId; ?>; // Current bill ID to exclude from conflicts

        // Delete bill function
        function deleteBill() {
            const billId = <?php echo $billId; ?>;
            const invoiceNumber = '<?php echo addslashes($bill['invoice_number']); ?>';
            
            if (!confirm(`⚠️ DELETE BILL CONFIRMATION\n\nAre you sure you want to delete this bill?\n\n• Invoice: ${invoiceNumber}\n• Bill ID: ${billId}\n\nThis action CANNOT be undone!`)) {
                return;
            }
            
            // Second confirmation with typing
            const confirmation = prompt(`🚨 FINAL CONFIRMATION\n\nTo permanently delete this bill, type "DELETE" (in capital letters):`);
            
            if (confirmation !== 'DELETE') {
                alert('Deletion cancelled. You must type "DELETE" exactly to confirm.');
                return;
            }
            
            // Show loading state
            const deleteBtn = document.querySelector('.btn-danger');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '🔄 Deleting...';
            deleteBtn.disabled = true;
            
            // Send delete request
            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    bill_id: billId,
                    confirm_delete: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Bill deleted successfully!');
                    window.location.href = 'view.php?message=bill_deleted';
                } else {
                    alert('❌ Error deleting bill: ' + data.message);
                    deleteBtn.innerHTML = originalText;
                    deleteBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('❌ Network error occurred while deleting bill.');
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            });
        }

        // Initialize with existing data
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial values (dates are now fixed)
            billStartDate = new Date('<?php echo $bill['check_in']; ?>');
            billEndDate = new Date('<?php echo $bill['check_out']; ?>');
            currentRate = parseFloat('<?php echo $bill['room_rate']; ?>') || 0;
            
            // Load existing employees
            <?php if (!empty($currentEmployees)): ?>
                <?php foreach ($currentEmployees as $emp): ?>
                    selectedEmployees.set(<?php echo $emp['id']; ?>, {
                        id: <?php echo $emp['id']; ?>,
                        name: '<?php echo addslashes($emp['name']); ?>',
                        meta: 'NIC: <?php echo addslashes($emp['nic']); ?><?php echo $emp['designation'] ? ' • ' . addslashes($emp['designation']) : ''; ?>',
                        checkIn: '<?php echo $emp['check_in']; ?>',
                        checkOut: '<?php echo $emp['check_out']; ?>',
                        conflicts: [],
                        hasConflicts: false
                    });
                <?php endforeach; ?>
            <?php endif; ?>
            
            refreshSelectedEmployeesList();
            calculateTotal();
        });

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

        // Remove updateDateRange function since dates are now fixed
        // Date range is locked in edit mode to prevent employee date conflicts

        // Show employee selector modal
        function showEmployeeSelector() {
            if (!billStartDate || !billEndDate) {
                alert('Please select check-in and check-out dates first.');
                return;
            }
            
            const modal = document.getElementById('employeeSelectorModal');
            modal.classList.add('active');
            document.getElementById('employeeSearch').focus();
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

        // Select employee from modal
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
                // Add to selected employees without conflict checking in edit mode
                selectedEmployees.set(employeeId, {
                    id: employeeId,
                    name: employeeName,
                    meta: employeeMeta,
                    checkIn: document.getElementById('check_in').value,
                    checkOut: document.getElementById('check_out').value,
                    conflicts: [],
                    hasConflicts: false
                });
                
                // Update status to assigned
                if (statusElement) {
                    statusElement.innerHTML = '<span class="assigned-badge">Assigned</span>';
                }
                
                refreshSelectedEmployeesList();
                hideEmployeeSelector();
                calculateTotal();
                
            } catch (error) {
                console.error('Error selecting employee:', error);
                alert('Error selecting employee. Please try again.');
                
                // Reset status
                if (statusElement) {
                    statusElement.innerHTML = '<span class="available-badge">Available</span>';
                }
            }
        }

        // Remove employee from bill
        function removeEmployee(employeeId) {
            if (confirm('Remove this employee from the bill?')) {
                selectedEmployees.delete(employeeId);
                
                // Update count immediately
                document.getElementById('selectedEmployeeCount').textContent = selectedEmployees.size;
                
                // If no employees left, show the "no employees" message
                if (selectedEmployees.size === 0) {
                    const container = document.getElementById('selectedEmployeesList');
                    const noMessage = document.getElementById('noEmployeesMessage');
                    container.innerHTML = '';
                    container.appendChild(noMessage);
                    noMessage.style.display = 'block';
                } else {
                    // Refresh the list to remove the employee card
                    refreshSelectedEmployeesList();
                }
                
                calculateTotal();
                
                // Update status in modal if open
                const statusElement = document.getElementById(`status_${employeeId}`);
                if (statusElement) {
                    statusElement.innerHTML = '<span class="available-badge">Available</span>';
                }
            }
        }

        // Update employee stay dates (with date range validation)
        function updateEmployeeDates(employeeId) {
            const checkInInput = document.getElementById(`emp_checkin_${employeeId}`);
            const checkOutInput = document.getElementById(`emp_checkout_${employeeId}`);
            const nightsDisplay = document.getElementById(`emp_nights_${employeeId}`);
            
            const newCheckIn = checkInInput.value;
            const newCheckOut = checkOutInput.value;
            
            // Validate dates are within FIXED bill range
            if (new Date(newCheckIn) < billStartDate || new Date(newCheckOut) > billEndDate) {
                alert('Employee dates must be within the bill period.\n\nBill Period: ' + billStartDate.toDateString() + ' to ' + billEndDate.toDateString() + '\n\nIf you need to change the bill dates, you must delete this bill and create a new one.');
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
        }

        // Refresh the selected employees list display
        function refreshSelectedEmployeesList() {
            const container = document.getElementById('selectedEmployeesList');
            const countSpan = document.getElementById('selectedEmployeeCount');
            
            // Update count
            countSpan.textContent = selectedEmployees.size;
            
            // If no employees selected, show the "no employees" message
            if (selectedEmployees.size === 0) {
                container.innerHTML = `
                    <div class="no-employees-message" id="noEmployeesMessage">
                        <div style="text-align: center; padding: 2rem; color: #718096;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                            <h4>No Employees Assigned</h4>
                            <p>Click "Add Employee to Bill" to assign crew members to this hotel bill.</p>
                        </div>
                    </div>
                `;
                return;
            }
            
            // Generate HTML for all selected employees
            let html = '';
            selectedEmployees.forEach((employee, employeeId) => {
                const checkInDate = new Date(employee.checkIn);
                const checkOutDate = new Date(employee.checkOut);
                const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                
                html += `
                    <div class="selected-employee-card">
                        <div class="employee-card-header">
                            <div class="employee-card-info">
                                <div class="employee-card-avatar">
                                    ${employee.name.substring(0, 2).toUpperCase()}
                                </div>
                                <div class="employee-card-details">
                                    <h4>${employee.name}</h4>
                                    <div class="employee-card-meta">${employee.meta}</div>
                                </div>
                            </div>
                            <button type="button" class="remove-employee-btn" onclick="removeEmployee(${employeeId})" title="Remove employee from bill">
                                Remove
                            </button>
                        </div>
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

        // Form validation before submission (simplified for edit mode)
        document.getElementById('billForm').addEventListener('submit', function(e) {
            // Basic validation
            const invoiceNumber = document.getElementById('invoice_number').value.trim();
            const hotelId = document.getElementById('hotel_id').value;
            const roomCount = parseInt(document.getElementById('room_count').value);

            if (!invoiceNumber || !hotelId || !roomCount) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            // Validate employee room capacity
            const maxEmployees = roomCount * 2; // 2 employees per room max
            if (selectedEmployees.size > maxEmployees) {
                e.preventDefault();
                alert(`Too many employees assigned. Maximum ${maxEmployees} employees allowed for ${roomCount} rooms (2 per room).`);
                return;
            }

            // Final confirmation
            const totalAmount = document.getElementById('totalAmount').textContent;
            const employeeCount = selectedEmployees.size;
            
            if (!confirm(`Confirm updating this bill?\n\n• Total Amount: LKR ${totalAmount}\n• Employees: ${employeeCount}\n\nThis action will modify the existing bill.`)) {
                e.preventDefault();
                return;
            }
        });

        // Remove date-related event listeners since dates are now locked
        // Auto-focus invoice number
        document.getElementById('invoice_number').focus();

        // Invoice number formatting
        document.getElementById('invoice_number').addEventListener('input', function(e) {
            // Convert to uppercase and remove special characters
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
            e.target.value = value;
        });

        // Close hotel dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.hotel-search-container')) {
                hideHotelDropdown();
            }
        });

        // Hide hotel dropdown on blur
        document.getElementById('hotelSearch').addEventListener('blur', hideHotelDropdown);

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
    </script>
</body>
</html>