<?php
/**
 * Add New Bill Page
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
            foreach ($employees as $employeeData) {
                $employeeId = intval($employeeData['id']);
                $stayDates = $employeeData['dates'] ?? [];
                
                if (empty($stayDates)) {
                    continue; // Skip if no dates selected
                }
                
                foreach ($stayDates as $stayDate) {
                    // Validate stay date is within bill range
                    $stayDateTime = new DateTime($stayDate);
                    if ($stayDateTime < $checkInDate || $stayDateTime >= $checkOutDate) {
                        throw new Exception('Employee stay date must be within bill date range.');
                    }
                    
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
                    
                    // Insert employee assignment
                    $db->query(
                        "INSERT INTO bill_employees (bill_id, employee_id, stay_date) VALUES (?, ?, ?)",
                        [$billId, $employeeId, $stayDate]
                    );
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

        /* Employee Selection */
        .employee-selection {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
        }

        .employee-item {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .employee-item:last-child {
            border-bottom: none;
        }

        .employee-checkbox {
            transform: scale(1.2);
        }

        .employee-info {
            flex: 1;
        }

        .employee-name {
            font-weight: 600;
            color: #2d3748;
        }

        .employee-details {
            font-size: 0.9rem;
            color: #718096;
        }

        .date-selection {
            margin-top: 0.5rem;
            display: none;
        }

        .date-selection.active {
            display: block;
        }

        .date-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .date-checkbox {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: #f7fafc;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
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
                                <select id="hotel_id" name="hotel_id" required onchange="updateHotelRate()">
                                    <option value="">Choose hotel...</option>
                                    <?php foreach ($hotels as $hotel): ?>
                                        <option value="<?php echo $hotel['id']; ?>" 
                                                data-rate="<?php echo $hotel['rate']; ?>"
                                                <?php echo ($_POST['hotel_id'] ?? '') == $hotel['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hotel['hotel_name'] . ' - ' . $hotel['location']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                        <p style="color: #718096; margin-bottom: 1rem;">Select employees and their stay dates. Each employee can only be assigned to one room per night.</p>
                        
                        <div class="employee-selection">
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $employee): ?>
                                    <div class="employee-item">
                                        <input type="checkbox" class="employee-checkbox" 
                                               id="emp_<?php echo $employee['id']; ?>" 
                                               value="<?php echo $employee['id']; ?>"
                                               onchange="toggleEmployeeDates(<?php echo $employee['id']; ?>)">
                                        <div class="employee-info">
                                            <div class="employee-name"><?php echo htmlspecialchars($employee['name']); ?></div>
                                            <div class="employee-details">
                                                NIC: <?php echo htmlspecialchars($employee['nic']); ?>
                                                <?php if ($employee['designation']): ?>
                                                    | <?php echo htmlspecialchars($employee['designation']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="date-selection" id="dates_<?php echo $employee['id']; ?>">
                                                <label style="font-size: 0.9rem; margin-bottom: 0.25rem;">Stay dates:</label>
                                                <div class="date-checkboxes" id="dateBoxes_<?php echo $employee['id']; ?>">
                                                    <!-- Dates will be populated by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 2rem; text-align: center; color: #718096;">
                                    No employees found. Please register employees first.
                                </div>
                            <?php endif; ?>
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

    <script>
        let currentRate = 0;

        // Update hotel rate display
        function updateHotelRate() {
            const select = document.getElementById('hotel_id');
            const rateDisplay = document.getElementById('rateDisplay');
            const rateSpan = document.getElementById('currentRate');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                currentRate = parseFloat(selectedOption.dataset.rate) || 0;
                rateSpan.textContent = new Intl.NumberFormat().format(currentRate);
                rateDisplay.style.display = 'block';
                calculateTotal();
            } else {
                rateDisplay.style.display = 'none';
                currentRate = 0;
                calculateTotal();
            }
        }

        // Update date range for employee selection
        function updateDateRange() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            
            if (checkIn && checkOut) {
                const startDate = new Date(checkIn);
                const endDate = new Date(checkOut);
                
                // Generate date options for each employee
                document.querySelectorAll('[id^="dateBoxes_"]').forEach(container => {
                    container.innerHTML = '';
                    
                    const currentDate = new Date(startDate);
                    while (currentDate < endDate) {
                        const dateStr = currentDate.toISOString().split('T')[0];
                        const dateLabel = currentDate.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        });
                        
                        const employeeId = container.id.split('_')[1];
                        
                        const checkbox = document.createElement('div');
                        checkbox.className = 'date-checkbox';
                        checkbox.innerHTML = `
                            <input type="checkbox" name="employees[${employeeId}][dates][]" value="${dateStr}" id="date_${employeeId}_${dateStr}">
                            <label for="date_${employeeId}_${dateStr}">${dateLabel}</label>
                        `;
                        
                        container.appendChild(checkbox);
                        currentDate.setDate(currentDate.getDate() + 1);
                    }
                });
                
                calculateTotal();
            }
        }

        // Toggle employee date selection
        function toggleEmployeeDates(employeeId) {
            const checkbox = document.getElementById(`emp_${employeeId}`);
            const dateSection = document.getElementById(`dates_${employeeId}`);
            const employeeInput = document.querySelector(`input[name="employees[${employeeId}][id]"]`);
            
            if (checkbox.checked) {
                dateSection.classList.add('active');
                
                // Add hidden input for employee ID if not exists
                if (!employeeInput) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `employees[${employeeId}][id]`;
                    hiddenInput.value = employeeId;
                    dateSection.appendChild(hiddenInput);
                }
                
                // Auto-select all dates by default
                const dateCheckboxes = dateSection.querySelectorAll('input[type="checkbox"]');
                dateCheckboxes.forEach(cb => cb.checked = true);
            } else {
                dateSection.classList.remove('active');
                
                // Remove hidden input
                if (employeeInput) {
                    employeeInput.remove();
                }
                
                // Uncheck all date checkboxes
                const dateCheckboxes = dateSection.querySelectorAll('input[type="checkbox"]');
                dateCheckboxes.forEach(cb => cb.checked = false);
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

        // Form validation before submission
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
            const selectedEmployees = document.querySelectorAll('.employee-checkbox:checked');
            if (selectedEmployees.length === 0) {
                if (!confirm('No employees have been assigned to this bill. Continue anyway?')) {
                    e.preventDefault();
                    return;
                }
            }

            // Validate employee date selections
            let hasInvalidEmployees = false;
            selectedEmployees.forEach(empCheckbox => {
                const employeeId = empCheckbox.value;
                const employeeName = empCheckbox.closest('.employee-item').querySelector('.employee-name').textContent;
                const dateCheckboxes = document.querySelectorAll(`input[name="employees[${employeeId}][dates][]"]:checked`);
                
                if (dateCheckboxes.length === 0) {
                    alert(`Please select stay dates for ${employeeName} or uncheck them from the bill.`);
                    hasInvalidEmployees = true;
                }
            });

            if (hasInvalidEmployees) {
                e.preventDefault();
                return;
            }

            // Final confirmation
            const totalAmount = document.getElementById('totalAmount').textContent;
            if (!confirm(`Confirm creating bill for LKR ${totalAmount}?\n\nThis action cannot be undone.`)) {
                e.preventDefault();
                return;
            }
        });

        // Auto-focus invoice number
        document.getElementById('invoice_number').focus();

        // Initialize calculations on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateHotelRate();
            updateDateRange();
            calculateTotal();
        });

        // Invoice number formatting
        document.getElementById('invoice_number').addEventListener('input', function(e) {
            // Convert to uppercase and remove special characters
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
            e.target.value = value;
        });

        // Set minimum dates (today)
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('check_in').setAttribute('min', today);
        document.getElementById('check_out').setAttribute('min', today);

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
    </script>
</body>
</html>