<?php
/**
 * Detailed Debug Hotel Registration
 * Save as: hotels/register_debug.php
 */

// Enable ALL error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$debugInfo = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $debugInfo[] = "=== STARTING HOTEL REGISTRATION ===";
        
        $hotelName = trim($_POST['hotel_name']);
        $location = trim($_POST['location']);
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $initialRate = floatval($_POST['initial_rate']);
        
        $debugInfo[] = "Form data received: " . json_encode([
            'hotel_name' => $hotelName,
            'location' => $location,
            'rate' => $initialRate
        ]);
        
        // Validate required fields
        if (empty($hotelName) || empty($location) || empty($initialRate)) {
            throw new Exception('Hotel name, location, and initial rate are required.');
        }
        
        if ($initialRate <= 0) {
            throw new Exception('Initial rate must be greater than 0.');
        }
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        $debugInfo[] = "Validation passed";
        
        $db = getDB();
        $currentUser = getCurrentUser();
        
        $debugInfo[] = "Database connection established, User ID: " . $currentUser['id'];
        
        // Start transaction
        $db->beginTransaction();
        $debugInfo[] = "Transaction started";
        
        // Step 1: Insert hotel
        $debugInfo[] = "=== STEP 1: INSERTING HOTEL ===";
        $hotelSql = "INSERT INTO hotels (hotel_name, location, address, phone, email, is_active, created_by) VALUES (?, ?, ?, ?, ?, 1, ?)";
        $hotelParams = [$hotelName, $location, $address, $phone, $email, $currentUser['id']];
        
        $debugInfo[] = "Hotel SQL: " . $hotelSql;
        $debugInfo[] = "Hotel Params: " . json_encode($hotelParams);
        
        try {
            $hotelId = $db->insert($hotelSql, $hotelParams);
            $debugInfo[] = "✓ Hotel inserted successfully! ID: " . $hotelId;
        } catch (Exception $hotelError) {
            $debugInfo[] = "✗ Hotel insert failed: " . $hotelError->getMessage();
            throw new Exception("Hotel insert failed: " . $hotelError->getMessage());
        }
        
        // Step 2: Insert rate
        $debugInfo[] = "=== STEP 2: INSERTING RATE ===";
        $rateSql = "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by) VALUES (?, ?, CURDATE(), 1, ?)";
        $rateParams = [$hotelId, $initialRate, $currentUser['id']];
        
        $debugInfo[] = "Rate SQL: " . $rateSql;
        $debugInfo[] = "Rate Params: " . json_encode($rateParams);
        
        try {
            $rateId = $db->insert($rateSql, $rateParams);
            $debugInfo[] = "✓ Rate inserted successfully! ID: " . $rateId;
        } catch (Exception $rateError) {
            $debugInfo[] = "✗ Rate insert failed: " . $rateError->getMessage();
            throw new Exception("Rate insert failed: " . $rateError->getMessage());
        }
        
        // Step 3: Log activity
        $debugInfo[] = "=== STEP 3: INSERTING AUDIT LOG ===";
        $auditSql = "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
        $auditParams = [
            'hotels',
            $hotelId,
            'INSERT',
            json_encode(['hotel_name' => $hotelName, 'location' => $location, 'rate' => $initialRate]),
            $currentUser['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $debugInfo[] = "Audit SQL: " . $auditSql;
        $debugInfo[] = "Audit Params: " . json_encode($auditParams);
        
        try {
            $auditResult = $db->query($auditSql, $auditParams);
            $debugInfo[] = "✓ Audit log inserted successfully!";
        } catch (Exception $auditError) {
            $debugInfo[] = "✗ Audit log failed: " . $auditError->getMessage();
            // Don't fail the whole transaction for audit log
            $debugInfo[] = "Continuing without audit log...";
        }
        
        // Step 4: Commit
        $debugInfo[] = "=== STEP 4: COMMITTING TRANSACTION ===";
        $db->commit();
        $debugInfo[] = "✓ Transaction committed successfully!";
        
        $message = 'Hotel registered successfully! Hotel ID: ' . $hotelId . ', Rate ID: ' . $rateId;
        $messageType = 'success';
        
        // Clear form data
        $_POST = array();
        
    } catch (Exception $e) {
        $debugInfo[] = "=== ERROR OCCURRED ===";
        $debugInfo[] = "Error: " . $e->getMessage();
        $debugInfo[] = "File: " . $e->getFile();
        $debugInfo[] = "Line: " . $e->getLine();
        $debugInfo[] = "Stack trace: " . $e->getTraceAsString();
        
        if (isset($db) && $db->inTransaction()) {
            $db->rollback();
            $debugInfo[] = "Transaction rolled back";
        }
        
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Test current database state
$debugInfo[] = "=== CURRENT DATABASE STATE ===";
try {
    $db = getDB();
    
    // Check audit_log structure
    $auditStructure = $db->fetchRow("SHOW CREATE TABLE audit_log");
    $debugInfo[] = "Audit Log Structure: " . $auditStructure['Create Table'];
    
    // Check hotel_rates structure  
    $ratesStructure = $db->fetchRow("SHOW CREATE TABLE hotel_rates");
    $debugInfo[] = "Hotel Rates Structure: " . $ratesStructure['Create Table'];
    
    // Test simple queries
    $hotelCount = $db->fetchValue("SELECT COUNT(*) FROM hotels");
    $rateCount = $db->fetchValue("SELECT COUNT(*) FROM hotel_rates");
    $debugInfo[] = "Current hotels: " . $hotelCount . ", Current rates: " . $rateCount;
    
} catch (Exception $e) {
    $debugInfo[] = "Database state check failed: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Hotel Registration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .form-container, .debug-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        input, textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            white-space: pre-line;
            max-height: 600px;
            overflow-y: auto;
        }

        h1, h2 {
            color: #2d3748;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Hotel Registration</h1>
        
        <div class="form-container">
            <h2>Test Form</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="hotel_name">Hotel Name *</label>
                    <input type="text" id="hotel_name" name="hotel_name" 
                           value="<?php echo htmlspecialchars($_POST['hotel_name'] ?? 'Test Hotel ' . date('H:i:s')); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" 
                           value="<?php echo htmlspecialchars($_POST['location'] ?? 'Colombo'); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($_POST['address'] ?? 'Test Address'); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? '0112345678'); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? 'test@hotel.com'); ?>">
                </div>

                <div class="form-group">
                    <label for="initial_rate">Initial Rate *</label>
                    <input type="number" id="initial_rate" name="initial_rate" 
                           value="<?php echo htmlspecialchars($_POST['initial_rate'] ?? '5000'); ?>" 
                           step="0.01" required>
                </div>

                <button type="submit" class="btn">Register Hotel</button>
            </form>
        </div>

        <?php if (!empty($debugInfo)): ?>
        <div class="debug-container">
            <h2>🐛 Debug Information</h2>
            <div class="debug-info"><?php echo implode("\n", $debugInfo); ?></div>
        </div>
        <?php endif; ?>

        <p><a href="../dashboard.php">← Back to Dashboard</a></p>
    </div>
</body>
</html>