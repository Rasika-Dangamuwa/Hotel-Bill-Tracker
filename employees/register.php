<?php
/**
 * Employee Registration Page
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
        $name = trim($_POST['name']);
        $nic = trim($_POST['nic']);
        $phone = trim($_POST['phone']);
        $designation = trim($_POST['designation']);
        $department = trim($_POST['department']);
        
        // Validate required fields
        if (empty($name) || empty($nic)) {
            throw new Exception('Employee name and NIC are required.');
        }
        
        // Validate NIC format (Sri Lankan NIC)
        if (!preg_match('/^([0-9]{9}[vVxX]|[0-9]{12})$/', $nic)) {
            throw new Exception('Please enter a valid Sri Lankan NIC number (9 digits + V/X or 12 digits).');
        }
        
        // Validate phone if provided
        if (!empty($phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{7,15}$/', $phone)) {
            throw new Exception('Please enter a valid phone number.');
        }
        
        $db = getDB();
        $currentUser = getCurrentUser();
        
        // Check if NIC already exists
        $existingEmployee = $db->fetchRow("SELECT id, name FROM employees WHERE nic = ?", [$nic]);
        if ($existingEmployee) {
            throw new Exception('An employee with this NIC already exists: ' . $existingEmployee['name']);
        }
        
        // Insert employee
        $employeeId = $db->insert(
            "INSERT INTO employees (name, nic, phone, designation, department, added_by) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, strtoupper($nic), $phone, $designation, $department, $currentUser['id']]
        );
        
        // Log activity
        $db->query(
            "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [
                'employees',
                $employeeId,
                'INSERT',
                json_encode(['name' => $name, 'nic' => $nic, 'designation' => $designation]),
                $currentUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        
        $message = 'Employee registered successfully! Employee ID: ' . $employeeId;
        $messageType = 'success';
        
        // Clear form data
        $_POST = array();
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get recent employees for reference
try {
    $db = getDB();
    $recentEmployees = $db->fetchAll(
        "SELECT id, name, nic, designation, department, created_at 
         FROM employees 
         WHERE is_active = 1 
         ORDER BY created_at DESC 
         LIMIT 10"
    );
} catch (Exception $e) {
    $recentEmployees = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Employee - Hotel Bill Tracking System</title>
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
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .form-container, .recent-container {
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

        .form-group {
            margin-bottom: 1.5rem;
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

        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: #f8fafc;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

        .nic-info {
            background: #ebf4ff;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .nic-info p {
            margin: 0;
            font-size: 0.9rem;
            color: #2b6cb0;
        }

        /* Recent Employees Section */
        .recent-container {
            height: fit-content;
        }

        .recent-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .employee-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .employee-item {
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .employee-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }

        .employee-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .employee-details {
            font-size: 0.9rem;
            color: #718096;
        }

        .employee-nic {
            font-family: monospace;
            background: #f7fafc;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }

        .no-employees {
            text-align: center;
            color: #718096;
            font-style: italic;
            padding: 2rem;
        }

        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
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
            <h1 class="header-title">Register New Employee</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <!-- Registration Form -->
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">Employee Registration</h2>
                <p class="form-subtitle">Add a new crew member or promotional staff to the system</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                           required maxlength="255" placeholder="Enter employee's full name">
                </div>

                <div class="form-group">
                    <label for="nic">National Identity Card (NIC) <span class="required">*</span></label>
                    <input type="text" id="nic" name="nic" 
                           value="<?php echo htmlspecialchars($_POST['nic'] ?? ''); ?>" 
                           required maxlength="12" placeholder="e.g., 123456789V or 123456789012"
                           pattern="[0-9]{9}[vVxX]|[0-9]{12}" 
                           title="Enter valid Sri Lankan NIC (9 digits + V/X or 12 digits)">
                    <div class="nic-info">
                        <p>💡 Enter Sri Lankan NIC: Old format (9 digits + V/X) or New format (12 digits)</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                           maxlength="20" placeholder="e.g., +94 71 1234567">
                </div>

                <div class="form-group">
                    <label for="designation">Designation/Position</label>
                    <select id="designation" name="designation">
                        <option value="">Select designation</option>
                        <option value="Propagandist" <?php echo ($_POST['designation'] ?? '') === 'Propagandist' ? 'selected' : ''; ?>>Propagandist</option>
                        <option value="Crew Member" <?php echo ($_POST['designation'] ?? '') === 'Crew Member' ? 'selected' : ''; ?>>Crew Member</option>
                        <option value="Team Leader" <?php echo ($_POST['designation'] ?? '') === 'Team Leader' ? 'selected' : ''; ?>>Team Leader</option>
                        <option value="Supervisor" <?php echo ($_POST['designation'] ?? '') === 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="Coordinator" <?php echo ($_POST['designation'] ?? '') === 'Coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                        <option value="Other" <?php echo ($_POST['designation'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="department">Department/Division</label>
                    <select id="department" name="department">
                        <option value="">Select department</option>
                        <option value="Marketing" <?php echo ($_POST['department'] ?? '') === 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                        <option value="Sales" <?php echo ($_POST['department'] ?? '') === 'Sales' ? 'selected' : ''; ?>>Sales</option>
                        <option value="Promotions" <?php echo ($_POST['department'] ?? '') === 'Promotions' ? 'selected' : ''; ?>>Promotions</option>
                        <option value="Events" <?php echo ($_POST['department'] ?? '') === 'Events' ? 'selected' : ''; ?>>Events</option>
                        <option value="Field Operations" <?php echo ($_POST['department'] ?? '') === 'Field Operations' ? 'selected' : ''; ?>>Field Operations</option>
                        <option value="Other" <?php echo ($_POST['department'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../dashboard.php'">Cancel</button>
                    <button type="submit" class="btn">Register Employee</button>
                </div>
            </form>
        </div>

        <!-- Recent Employees -->
        <div class="recent-container">
            <h3 class="recent-title">
                <span>👥</span>
                Recent Employees
            </h3>
            
            <div class="employee-list">
                <?php if (!empty($recentEmployees)): ?>
                    <?php foreach ($recentEmployees as $employee): ?>
                        <div class="employee-item">
                            <div class="employee-name"><?php echo htmlspecialchars($employee['name']); ?></div>
                            <div class="employee-details">
                                <span class="employee-nic"><?php echo htmlspecialchars($employee['nic']); ?></span><br>
                                <?php if ($employee['designation']): ?>
                                    <strong><?php echo htmlspecialchars($employee['designation']); ?></strong>
                                    <?php if ($employee['department']): ?>
                                        - <?php echo htmlspecialchars($employee['department']); ?>
                                    <?php endif; ?>
                                    <br>
                                <?php endif; ?>
                                <small>Added: <?php echo date('M j, Y', strtotime($employee['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-employees">
                        No employees registered yet
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const nic = document.getElementById('nic').value.trim();

            if (!name || !nic) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            // Validate NIC format
            const nicPattern = /^([0-9]{9}[vVxX]|[0-9]{12})$/;
            if (!nicPattern.test(nic)) {
                e.preventDefault();
                alert('Please enter a valid Sri Lankan NIC number.\n\nFormat:\n• Old: 9 digits + V or X (e.g., 123456789V)\n• New: 12 digits (e.g., 123456789012)');
                return;
            }

            // Confirm submission
            if (!confirm('Are you sure you want to register this employee?')) {
                e.preventDefault();
            }
        });

        // Auto-focus first field
        document.getElementById('name').focus();

        // Format NIC input
        document.getElementById('nic').addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^0-9VX]/g, '');
            
            // Limit length based on format
            if (value.includes('V') || value.includes('X')) {
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
            } else {
                if (value.length > 12) {
                    value = value.substring(0, 12);
                }
            }
            
            e.target.value = value;
        });

        // Format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d\+\-\(\)\s]/g, '');
            e.target.value = value;
        });

        // Name formatting
        document.getElementById('name').addEventListener('input', function(e) {
            // Capitalize first letter of each word
            let value = e.target.value.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
            e.target.value = value;
        });
    </script>
</body>
</html>