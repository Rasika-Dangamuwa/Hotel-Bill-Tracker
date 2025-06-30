<?php
/**
 * Add Bill Page - Enhanced with Propagandist and Bill File tracking
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

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $invoiceNumber = trim($_POST['invoice_number']);
        $hotelId = intval($_POST['hotel_id']);
        $propagandistId = !empty($_POST['propagandist_id']) ? intval($_POST['propagandist_id']) : null;
        $billFileId = !empty($_POST['bill_file_id']) ? intval($_POST['bill_file_id']) : null;
        $checkIn = $_POST['check_in'];
        $checkOut = $_POST['check_out'];
        $roomCount = intval($_POST['room_count']);
        $waterCharge = floatval($_POST['water_charge'] ?? 0);
        $washingCharge = floatval($_POST['washing_charge'] ?? 0);
        $serviceCharge = floatval($_POST['service_charge'] ?? 0);
        $miscCharge = floatval($_POST['misc_charge'] ?? 0);
        $miscDescription = trim($_POST['misc_description'] ?? '');
        $selectedEmployees = json_decode($_POST['selected_employees'], true) ?? [];
        
        // Validate required fields
        if (empty($invoiceNumber) || !$hotelId || empty($checkIn) || empty($checkOut) || !$roomCount) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate dates
        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);
        if ($checkOutDate <= $checkInDate) {
            throw new Exception('Check-out date must be after check-in date.');
        }
        
        // Validate employees assigned
        if (empty($selectedEmployees)) {
            throw new Exception('Please assign at least one employee to this bill.');
        }
        
        $db = getDB();
        $currentUser = getCurrentUser();
        
        // Check if invoice number exists
        $existingBill = $db->fetchRow("SELECT id FROM bills WHERE invoice_number = ?", [$invoiceNumber]);
        if ($existingBill) {
            throw new Exception('Invoice number already exists. Please use a unique invoice number.');
        }
        
        // Get hotel rate
        $hotelRate = $db->fetchRow(
            "SELECT hr.id, hr.rate FROM hotel_rates hr 
             WHERE hr.hotel_id = ? AND hr.is_current = 1",
            [$hotelId]
        );
        
        if (!$hotelRate) {
            throw new Exception('No current rate found for selected hotel.');
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert bill
            $billId = $db->insert(
                "INSERT INTO bills (invoice_number, hotel_id, rate_id, check_in, check_out, 
                 room_count, water_charge, washing_charge, service_charge, misc_charge, 
                 misc_description, submitted_by, propagandist_id, bill_file_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $invoiceNumber, $hotelId, $hotelRate['id'], $checkIn, $checkOut,
                    $roomCount, $waterCharge, $washingCharge, $serviceCharge, $miscCharge,
                    $miscDescription, $currentUser['id'], $propagandistId, $billFileId
                ]
            );
            
            // Insert employee assignments
            foreach ($selectedEmployees as $employee) {
                $employeeId = intval($employee['id']);
                $roomAssignments = $employee['rooms'] ?? [];
                
                // Get all dates between check-in and check-out
                $current = clone $checkInDate;
                while ($current < $checkOutDate) {
                    $stayDate = $current->format('Y-m-d');
                    $roomNumber = $roomAssignments[$stayDate] ?? null;
                    
                    $db->insert(
                        "INSERT INTO bill_employees (bill_id, employee_id, stay_date, room_number) VALUES (?, ?, ?, ?)",
                        [$billId, $employeeId, $stayDate, $roomNumber]
                    );
                    
                    $current->add(new DateInterval('P1D'));
                }
            }
            
            // Log activity
            $db->insert(
                "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    'bills',
                    $billId,
                    'INSERT',
                    json_encode(['invoice_number' => $invoiceNumber, 'hotel_id' => $hotelId, 'propagandist_id' => $propagandistId, 'bill_file_id' => $billFileId]),
                    $currentUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            
            $db->commit();
            
            $message = 'Bill added successfully! Bill ID: ' . $billId;
            $messageType = 'success';
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

try {
    $db = getDB();
    
    // Get hotels with current rates
    $hotels = $db->fetchAll(
        "SELECT h.id, h.hotel_name, h.location, hr.rate 
         FROM hotels h 
         JOIN hotel_rates hr ON h.id = hr.hotel_id 
         WHERE h.is_active = 1 AND hr.is_current = 1 
         ORDER BY h.hotel_name"
    );
    
    // Get active employees
    $employees = $db->fetchAll(
        "SELECT id, name, nic, designation, department 
         FROM employees 
         WHERE is_active = 1 
         ORDER BY name"
    );
    
    // Get active propagandists
    $propagandists = $db->fetchAll(
        "SELECT id, name, nic, phone, department, propagandist_since, notes 
         FROM active_propagandists 
         ORDER BY name"
    );
    
    // Get pending bill files
    $billFiles = $db->fetchAll(
        "SELECT id, file_number, description, submitted_date, total_bills, total_amount, created_by_name 
         FROM pending_bill_files 
         ORDER BY submitted_date DESC, file_number"
    );
    
} catch (Exception $e) {
    error_log("Add bill page error: " . $e->getMessage());
    $message = 'Database error occurred. Please try again.';
    $messageType = 'error';
    $hotels = [];
    $employees = [];
    $propagandists = [];
    $billFiles = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Bill - Hotel Tracking System</title>
    <link rel="stylesheet" href="main.css">
    <style>
        /* Enhanced form styles for new dropdowns */
        .form-grid-enhanced {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .dropdown-container {
            position: relative;
        }
        
        .dropdown-info {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 0.5rem;
        }
        
        .propagandist-badge {
            display: inline-block;
            background: #e6fffa;
            color: #234e52;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        
        .file-info {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .file-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #718096;
        }
        
        .required-note {
            background: #fef5e7;
            border: 1px solid #f6e05e;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .form-grid-enhanced {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="page-title">📋 Add New Hotel Bill</h1>
                <p class="page-subtitle">Enter hotel bill details with propagandist and file tracking</p>
            </div>
            <div class="header-actions">
                <a href="view.php" class="btn btn-secondary">View All Bills</a>
                <a href="../dashboard.php" class="btn btn-outline">Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="required-note">
            <h4 style="margin-bottom: 0.5rem;">📌 Important Information</h4>
            <p><strong>Account Assistant:</strong> You are adding bills to the system.<br>
            <strong>Propagandist:</strong> Select who originally submitted these bills.<br>
            <strong>Bill File:</strong> Select which file batch these bills belong to.</p>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">Hotel Bill Information</h2>
                <p class="form-subtitle">Complete all sections below to add the hotel bill</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="billForm">
                <div class="form-sections">
                    <!-- Tracking Information -->
                    <div class="section">
                        <h3 class="section-title">📋 Tracking Information</h3>
                        <div class="form-grid-enhanced">
                            <div class="form-group">
                                <label for="propagandist_id">Submitted by Propagandist</label>
                                <select id="propagandist_id" name="propagandist_id">
                                    <option value="">Select propagandist (optional)</option>
                                    <?php foreach ($propagandists as $propagandist): ?>
                                        <option value="<?php echo $propagandist['id']; ?>" 
                                                <?php echo ($_POST['propagandist_id'] ?? '') == $propagandist['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($propagandist['name']); ?>
                                            <?php if ($propagandist['department']): ?>
                                                - <?php echo htmlspecialchars($propagandist['department']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="dropdown-info">
                                    💡 Select the propagandist who originally submitted these hotel bills
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="bill_file_id">Bill File</label>
                                <select id="bill_file_id" name="bill_file_id" onchange="showFileInfo()">
                                    <option value="">Select bill file (optional)</option>
                                    <?php foreach ($billFiles as $file): ?>
                                        <option value="<?php echo $file['id']; ?>"
                                                data-file-number="<?php echo htmlspecialchars($file['file_number']); ?>"
                                                data-description="<?php echo htmlspecialchars($file['description'] ?? ''); ?>"
                                                data-date="<?php echo $file['submitted_date']; ?>"
                                                data-bills="<?php echo $file['total_bills']; ?>"
                                                data-amount="<?php echo number_format($file['total_amount'], 2); ?>"
                                                <?php echo ($_POST['bill_file_id'] ?? '') == $file['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($file['file_number']); ?>
                                            <?php if ($file['description']): ?>
                                                - <?php echo htmlspecialchars($file['description']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="dropdown-info">
                                    📁 Select which file batch this bill belongs to
                                </div>
                                <div id="fileInfo" class="file-info" style="display: none;">
                                    <div id="fileDetails"></div>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                                <div class="hotel-rate">LKR <?php echo number_format($hotel['rate'], 2); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div id="rateDisplay" class="rate-display" style="display: none;">
                                    <h4>Current Rate</h4>
                                    <div class="rate-amount">LKR <span id="currentRate">0</span> per night per room</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="check_in">Check-in Date <span class="required">*</span></label>
                                <input type="date" id="check_in" name="check_in" 
                                       value="<?php echo $_POST['check_in'] ?? ''; ?>" 
                                       required onchange="updateDateRange()">
                            </div>

                            <div class="form-group">
                                <label for="check_out">Check-out Date <span class="required">*</span></label>
                                <input type="date" id="check_out" name="check_out" 
                                       value="<?php echo $_POST['check_out'] ?? ''; ?>" 
                                       required onchange="updateDateRange()">
                            </div>

                            <div class="form-group">
                                <label for="room_count">Number of Rooms <span class="required">*</span></label>
                                <input type="number" id="room_count" name="room_count" 
                                       value="<?php echo $_POST['room_count'] ?? ''; ?>" 
                                       required min="1" max="50" onchange="calculateTotal()">
                            </div>
                        </div>
                    </div>

                    <!-- Employee Assignment - Keep existing structure -->
                    <div class="section">
                        <h3 class="section-title">👥 Employee Assignment</h3>
                        <div class="employee-assignment-container">
                            <div class="add-employee-button" onclick="showEmployeeSelector()">
                                <span class="add-icon">+</span>
                                <span>Assign Employees to Bill</span>
                            </div>
                            
                            <div id="selectedEmployeesContainer" class="selected-employees-container" style="display: none;">
                                <h4>Assigned Employees</h4>
                                <div id="selectedEmployeesList" class="selected-employees-list"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Charges - Keep existing structure -->
                    <div class="section">
                        <h3 class="section-title">💰 Additional Charges</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="water_charge">Water Charge</label>
                                <input type="number" id="water_charge" name="water_charge" 
                                       value="<?php echo $_POST['water_charge'] ?? '0'; ?>" 
                                       step="0.01" min="0" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="washing_charge">Washing Charge</label>
                                <input type="number" id="washing_charge" name="washing_charge" 
                                       value="<?php echo $_POST['washing_charge'] ?? '0'; ?>" 
                                       step="0.01" min="0" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="service_charge">Service Charge</label>
                                <input type="number" id="service_charge" name="service_charge" 
                                       value="<?php echo $_POST['service_charge'] ?? '0'; ?>" 
                                       step="0.01" min="0" onchange="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label for="misc_charge">Miscellaneous Charge</label>
                                <input type="number" id="misc_charge" name="misc_charge" 
                                       value="<?php echo $_POST['misc_charge'] ?? '0'; ?>" 
                                       step="0.01" min="0" onchange="calculateTotal()">
                            </div>

                            <div class="form-group form-group-full">
                                <label for="misc_description">Miscellaneous Description</label>
                                <input type="text" id="misc_description" name="misc_description" 
                                       value="<?php echo htmlspecialchars($_POST['misc_description'] ?? ''); ?>" 
                                       placeholder="Describe miscellaneous charges">
                            </div>
                        </div>
                    </div>

                    <!-- Bill Summary - Keep existing structure -->
                    <div class="section">
                        <h3 class="section-title">📊 Bill Summary</h3>
                        <div class="bill-summary">
                            <div class="summary-row">
                                <span>Base Amount:</span>
                                <span id="baseAmount">LKR 0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Additional Charges:</span>
                                <span id="additionalCharges">LKR 0.00</span>
                            </div>
                            <div class="summary-row total">
                                <span>Total Amount:</span>
                                <span id="totalAmount">LKR 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="selected_employees" name="selected_employees">
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='view.php'">Cancel</button>
                    <button type="submit" class="btn">Add Bill</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Employee Selector Modal - Keep existing structure -->
    <div class="modal-overlay" id="employeeSelectorModal" onclick="hideEmployeeSelector(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>Select Employees</h3>
                <button class="modal-close" onclick="hideEmployeeSelector()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="employee-search">
                    <input type="text" id="employeeSearch" placeholder="Search employees by name or NIC..." 
                           onkeyup="filterEmployees()">
                </div>
                
                <div class="employee-list">
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $employee): ?>
                            <div class="employee-option" data-employee-id="<?php echo $employee['id']; ?>" 
                                 onclick="selectEmployee(<?php echo $employee['id']; ?>)">
                                <div class="employee-info">
                                    <div class="employee-name"><?php echo htmlspecialchars($employee['name']); ?></div>
                                    <div class="employee-meta">
                                        <?php echo htmlspecialchars($employee['nic']); ?>
                                        <?php if (!empty($employee['designation'])): ?>
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

        // Show bill file information
        function showFileInfo() {
            const select = document.getElementById('bill_file_id');
            const fileInfo = document.getElementById('fileInfo');
            const fileDetails = document.getElementById('fileDetails');
            
            if (select.value) {
                const option = select.selectedOptions[0];
                const fileNumber = option.dataset.fileNumber;
                const description = option.dataset.description;
                const date = option.dataset.date;
                const bills = option.dataset.bills;
                const amount = option.dataset.amount;
                
                fileDetails.innerHTML = `
                    <div style="margin-bottom: 0.5rem;"><strong>File:</strong> ${fileNumber}</div>
                    ${description ? `<div style="margin-bottom: 0.5rem;"><strong>Description:</strong> ${description}</div>` : ''}
                    <div class="file-meta">
                        <span>📅 ${date}</span>
                        <span>📋 ${bills} bills</span>
                        <span>💰 LKR ${amount}</span>
                    </div>
                `;
                fileInfo.style.display = 'block';
            } else {
                fileInfo.style.display = 'none';
            }
        }

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

        function updateDateRange() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            
            if (checkIn && checkOut) {
                billStartDate = new Date(checkIn);
                billEndDate = new Date(checkOut);
                
                if (billEndDate <= billStartDate) {
                    alert('Check-out date must be after check-in date.');
                    document.getElementById('check_out').value = '';
                    billEndDate = null;
                    return;
                }
                
                calculateTotal();
                
                // Re-check employee availability if any selected
                if (selectedEmployees.size > 0) {
                    checkEmployeeAvailability();
                }
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
                // Check for conflicts
                const conflicts = await checkEmployeeConflicts(employeeId);
                
                // Add to selected employees
                selectedEmployees.set(employeeId, {
                    id: employeeId,
                    name: employeeName,
                    meta: employeeMeta,
                    checkIn: document.getElementById('check_in').value,
                    checkOut: document.getElementById('check_out').value,
                    conflicts: conflicts,
                    hasConflicts: conflicts.length > 0,
                    rooms: {} // Room assignments for each date
                });
                
                // Update status based on conflicts
                if (statusElement) {
                    if (conflicts.length > 0) {
                        statusElement.innerHTML = '<span class="conflict-badge" onmouseover="showConflictTooltip(event, ' + employeeId + ')" onmouseout="hideConflictTooltip()">Conflicts</span>';
                    } else {
                        statusElement.innerHTML = '<span class="assigned-badge">Assigned</span>';
                    }
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
                
                // Update status in modal
                const statusElement = document.getElementById(`status_${employeeId}`);
                if (statusElement) {
                    statusElement.innerHTML = '<span class="available-badge">Available</span>';
                }
                
                refreshSelectedEmployeesList();
                calculateTotal();
            }
        }

        // Refresh selected employees list
        function refreshSelectedEmployeesList() {
            const container = document.getElementById('selectedEmployeesContainer');
            const list = document.getElementById('selectedEmployeesList');
            
            if (selectedEmployees.size === 0) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'block';
            
            let html = '';
            selectedEmployees.forEach((employee, employeeId) => {
                const conflictClass = employee.hasConflicts ? 'has-conflicts' : '';
                const conflictIcon = employee.hasConflicts ? '⚠️' : '✅';
                
                html += `
                    <div class="selected-employee ${conflictClass}">
                        <div class="employee-main">
                            <div class="employee-info">
                                <div class="employee-name">${conflictIcon} ${employee.name}</div>
                                <div class="employee-meta">${employee.meta}</div>
                                ${employee.hasConflicts ? '<div class="conflict-warning">⚠️ Has booking conflicts</div>' : ''}
                            </div>
                            <button type="button" class="remove-employee" onclick="removeEmployee(${employeeId})">Remove</button>
                        </div>
                        <div class="room-assignment">
                            <label>Room assignments (optional):</label>
                            <div class="room-inputs" id="roomInputs_${employeeId}"></div>
                        </div>
                    </div>
                `;
            });
            
            list.innerHTML = html;
            
            // Generate room input fields for each employee
            selectedEmployees.forEach((employee, employeeId) => {
                generateRoomInputs(employeeId);
            });
            
            // Update hidden input
            updateSelectedEmployeesInput();
        }

        // Generate room input fields for date range
        function generateRoomInputs(employeeId) {
            const container = document.getElementById(`roomInputs_${employeeId}`);
            if (!container || !billStartDate || !billEndDate) return;
            
            let html = '';
            const current = new Date(billStartDate);
            
            while (current < billEndDate) {
                const dateStr = current.toISOString().split('T')[0];
                const displayDate = current.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric' 
                });
                
                html += `
                    <div class="room-input-group">
                        <label>${displayDate}:</label>
                        <input type="text" placeholder="Room #" 
                               onchange="updateRoomAssignment(${employeeId}, '${dateStr}', this.value)"
                               maxlength="10">
                    </div>
                `;
                
                current.setDate(current.getDate() + 1);
            }
            
            container.innerHTML = html;
        }

        // Update room assignment
        function updateRoomAssignment(employeeId, date, roomNumber) {
            if (selectedEmployees.has(employeeId)) {
                const employee = selectedEmployees.get(employeeId);
                employee.rooms[date] = roomNumber.trim() || null;
                updateSelectedEmployeesInput();
            }
        }

        // Update hidden input with selected employees data
        function updateSelectedEmployeesInput() {
            const employeesArray = Array.from(selectedEmployees.values());
            document.getElementById('selected_employees').value = JSON.stringify(employeesArray);
        }

        // Check employee conflicts
        async function checkEmployeeConflicts(employeeId) {
            if (!billStartDate || !billEndDate) return [];
            
            const formData = new FormData();
            formData.append('action', 'check_conflicts');
            formData.append('employee_id', employeeId);
            formData.append('check_in', document.getElementById('check_in').value);
            formData.append('check_out', document.getElementById('check_out').value);
            
            try {
                const response = await fetch('../api/employee_conflicts.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                return data.conflicts || [];
            } catch (error) {
                console.error('Error checking conflicts:', error);
                return [];
            }
        }

        // Check employee availability for all employees
        async function checkEmployeeAvailability() {
            if (!billStartDate || !billEndDate) return;
            
            const statusElements = document.querySelectorAll('[id^="status_"]');
            
            // Update status for each employee
            for (const element of statusElements) {
                const employeeId = parseInt(element.id.split('_')[1]);
                
                if (selectedEmployees.has(employeeId)) {
                    const employee = selectedEmployees.get(employeeId);
                    if (employee.hasConflicts) {
                        element.innerHTML = '<span class="conflict-badge" onmouseover="showConflictTooltip(event, ' + employeeId + ')" onmouseout="hideConflictTooltip()">Conflicts</span>';
                    } else {
                        element.innerHTML = '<span class="assigned-badge">Assigned</span>';
                    }
                } else {
                    // Check for conflicts
                    element.innerHTML = '<span class="checking-badge">Checking...</span>';
                    
                    try {
                        const conflicts = await checkEmployeeConflicts(employeeId);
                        
                        if (conflicts.length > 0) {
                            element.innerHTML = '<span class="conflict-badge" onmouseover="showConflictTooltip(event, ' + employeeId + ')" onmouseout="hideConflictTooltip()">Conflicts</span>';
                        } else {
                            element.innerHTML = '<span class="available-badge">Available</span>';
                        }
                        
                        // Store conflicts data for tooltip
                        element.dataset.conflicts = JSON.stringify(conflicts);
                        
                    } catch (error) {
                        console.error('Error checking availability:', error);
                        element.innerHTML = '<span class="available-badge">Available</span>';
                    }
                }
            }
        }

        // Show conflict tooltip
        function showConflictTooltip(event, employeeId) {
            const element = event.target;
            let conflicts = [];
            
            // Get conflicts from selected employee or status element
            if (selectedEmployees.has(employeeId)) {
                conflicts = selectedEmployees.get(employeeId).conflicts;
            } else {
                const statusElement = document.getElementById(`status_${employeeId}`);
                if (statusElement && statusElement.dataset.conflicts) {
                    conflicts = JSON.parse(statusElement.dataset.conflicts);
                }
            }
            
            if (conflicts.length === 0) return;
            
            // Remove existing tooltip
            hideConflictTooltip();
            
            // Create tooltip
            const tooltip = document.createElement('div');
            tooltip.id = 'conflict-tooltip';
            tooltip.className = 'conflict-tooltip';
            
            let tooltipContent = '<div class="tooltip-header">⚠️ Booking Conflicts</div>';
            conflicts.forEach(conflict => {
                const propagandistInfo = conflict.propagandist_name ? 
                    `<br><small>Submitted by: ${conflict.propagandist_name}</small>` : '';
                const fileInfo = conflict.file_number ? 
                    `<br><small>File: ${conflict.file_number}</small>` : '';
                
                tooltipContent += `
                    <div class="conflict-item">
                        <strong>${conflict.hotel_name}</strong><br>
                        ${conflict.check_in} to ${conflict.check_out}<br>
                        <small>Invoice: ${conflict.invoice_number}</small>
                        ${propagandistInfo}
                        ${fileInfo}
                    </div>
                `;
            });
            
            tooltip.innerHTML = tooltipContent;
            document.body.appendChild(tooltip);
            
            // Position tooltip
            const rect = element.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            
            tooltip.style.position = 'fixed';
            tooltip.style.left = Math.min(rect.left, window.innerWidth - tooltipRect.width - 20) + 'px';
            tooltip.style.top = (rect.bottom + 10) + 'px';
            tooltip.style.zIndex = '10000';
            
            // Adjust if tooltip goes off screen
            if (tooltipRect.right > window.innerWidth - 20) {
                tooltip.style.left = (rect.right - tooltipRect.width) + 'px';
            }
            
            if (tooltipRect.bottom > window.innerHeight - 20) {
                tooltip.style.top = (rect.top - tooltipRect.height - 10) + 'px';
            }
            
            if (tooltipRect.top < 20) {
                tooltip.style.top = (rect.bottom + 10) + 'px';
            }
        }

        // Hide conflict tooltip
        function hideConflictTooltip() {
            const tooltip = document.getElementById('conflict-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        }

        // Calculate total amount
        function calculateTotal() {
            if (!billStartDate || !billEndDate || !currentRate) {
                document.getElementById('baseAmount').textContent = 'LKR 0.00';
                document.getElementById('additionalCharges').textContent = 'LKR 0.00';
                document.getElementById('totalAmount').textContent = 'LKR 0.00';
                return;
            }
            
            const nights = Math.ceil((billEndDate - billStartDate) / (1000 * 60 * 60 * 24));
            const rooms = parseInt(document.getElementById('room_count').value) || 0;
            const baseAmount = nights * rooms * currentRate;
            
            const waterCharge = parseFloat(document.getElementById('water_charge').value) || 0;
            const washingCharge = parseFloat(document.getElementById('washing_charge').value) || 0;
            const serviceCharge = parseFloat(document.getElementById('service_charge').value) || 0;
            const miscCharge = parseFloat(document.getElementById('misc_charge').value) || 0;
            
            const additionalCharges = waterCharge + washingCharge + serviceCharge + miscCharge;
            const totalAmount = baseAmount + additionalCharges;
            
            document.getElementById('baseAmount').textContent = 'LKR ' + baseAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('additionalCharges').textContent = 'LKR ' + additionalCharges.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('totalAmount').textContent = 'LKR ' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        // Form validation before submit
        document.getElementById('billForm').addEventListener('submit', function(e) {
            if (selectedEmployees.size === 0) {
                e.preventDefault();
                alert('Please assign at least one employee to this bill.');
                return false;
            }
            
            // Update hidden input before submit
            updateSelectedEmployeesInput();
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show file info if file is pre-selected
            showFileInfo();
        });
    </script>
</body>
</html>