<?php
/**
 * Fixed Hotel Registration Page - Final Version
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
        $hotelName = trim($_POST['hotel_name']);
        $location = trim($_POST['location']);
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $initialRate = floatval($_POST['initial_rate']);
        
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
        
        $db = getDB();
        $currentUser = getCurrentUser();
        
        // Start transaction
        $db->beginTransaction();
        
        // Insert hotel
        $hotelId = $db->insert(
            "INSERT INTO hotels (hotel_name, location, address, phone, email, is_active, created_by) VALUES (?, ?, ?, ?, ?, 1, ?)",
            [$hotelName, $location, $address, $phone, $email, $currentUser['id']]
        );
        
        // Insert initial rate
        $rateId = $db->insert(
            "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by) VALUES (?, ?, CURDATE(), 1, ?)",
            [$hotelId, $initialRate, $currentUser['id']]
        );
        
        // Log activity (now should work with fixed enum)
        $db->query(
            "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [
                'hotels',
                $hotelId,
                'INSERT',
                json_encode(['hotel_name' => $hotelName, 'location' => $location, 'rate' => $initialRate]),
                $currentUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        
        $db->commit();
        
        $message = 'Hotel registered successfully! Hotel ID: ' . $hotelId . ', Initial Rate: LKR ' . number_format($initialRate, 2);
        $messageType = 'success';
        
        // Clear form data
        $_POST = array();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollback();
        }
        
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get existing hotels for reference
try {
    $db = getDB();
    $recentHotels = $db->fetchAll(
        "SELECT h.id, h.hotel_name, h.location, hr.rate, h.created_at 
         FROM hotels h 
         LEFT JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1 
         WHERE h.is_active = 1 
         ORDER BY h.created_at DESC 
         LIMIT 5"
    );
} catch (Exception $e) {
    $recentHotels = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Hotel - Hotel Bill Tracking System</title>
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

        input, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: #f8fafc;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            height: 100px;
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

        .rate-info {
            background: #ebf4ff;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .rate-info p {
            margin: 0;
            font-size: 0.9rem;
            color: #2b6cb0;
        }

        /* Recent Hotels Section */
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

        .hotel-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .hotel-item {
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .hotel-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }

        .hotel-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .hotel-details {
            font-size: 0.9rem;
            color: #718096;
        }

        .hotel-rate {
            font-weight: 600;
            color: #667eea;
        }

        .no-hotels {
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
            <h1 class="header-title">Register New Hotel</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <!-- Registration Form -->
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">Hotel Registration</h2>
                <p class="form-subtitle">Add a new hotel to the system with initial room rate</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="hotel_name">Hotel Name <span class="required">*</span></label>
                    <input type="text" id="hotel_name" name="hotel_name" 
                           value="<?php echo htmlspecialchars($_POST['hotel_name'] ?? ''); ?>" 
                           required maxlength="255" placeholder="Enter hotel name">
                </div>

                <div class="form-group">
                    <label for="location">Location <span class="required">*</span></label>
                    <input type="text" id="location" name="location" 
                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                           required maxlength="255" placeholder="e.g., Colombo, Kandy, Galle">
                </div>

                <div class="form-group">
                    <label for="address">Full Address</label>
                    <textarea id="address" name="address" placeholder="Enter complete hotel address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                           maxlength="20" placeholder="e.g., +94 11 1234567">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           maxlength="100" placeholder="hotel@example.com">
                </div>

                <div class="form-group">
                    <label for="initial_rate">Initial Room Rate (LKR) <span class="required">*</span></label>
                    <input type="number" id="initial_rate" name="initial_rate" 
                           value="<?php echo htmlspecialchars($_POST['initial_rate'] ?? ''); ?>" 
                           required min="0" step="0.01" placeholder="e.g., 5000.00">
                    <div class="rate-info">
                        <p>💡 This will be set as the current room rate. You can add new rates later when prices change.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../dashboard.php'">Cancel</button>
                    <button type="submit" class="btn">Register Hotel</button>
                </div>
            </form>
        </div>

        <!-- Recent Hotels -->
        <div class="recent-container">
            <h3 class="recent-title">
                <span>🏨</span>
                Recent Hotels
            </h3>
            
            <div class="hotel-list">
                <?php if (!empty($recentHotels)): ?>
                    <?php foreach ($recentHotels as $hotel): ?>
                        <div class="hotel-item">
                            <div class="hotel-name"><?php echo htmlspecialchars($hotel['hotel_name']); ?></div>
                            <div class="hotel-details">
                                📍 <?php echo htmlspecialchars($hotel['location']); ?><br>
                                💰 <span class="hotel-rate">LKR <?php echo number_format($hotel['rate'] ?? 0, 2); ?></span> per night<br>
                                <small>Added: <?php echo date('M j, Y', strtotime($hotel['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-hotels">
                        No hotels registered yet
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const hotelName = document.getElementById('hotel_name').value.trim();
            const location = document.getElementById('location').value.trim();
            const initialRate = parseFloat(document.getElementById('initial_rate').value);

            if (!hotelName || !location || !initialRate || initialRate <= 0) {
                e.preventDefault();
                alert('Please fill in all required fields with valid values.');
                return;
            }

            // Confirm submission
            if (!confirm('Are you sure you want to register this hotel?')) {
                e.preventDefault();
            }
        });

        // Auto-focus first field
        document.getElementById('hotel_name').focus();
    </script>
</body>
</html>