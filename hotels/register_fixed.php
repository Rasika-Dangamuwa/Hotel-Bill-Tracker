<?php
/**
 * Fixed Hotel Registration with Detailed Error Handling
 * Save as: hotels/register_fixed.php
 */

// Enable error logging
ini_set('log_errors', 1);
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
        $hotelName = trim($_POST['hotel_name']);
        $location = trim($_POST['location']);
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $initialRate = floatval($_POST['initial_rate']);
        
        // Validate required fields
        if (empty($hotelName) || empty($location) || $initialRate <= 0) {
            throw new Exception('Hotel name, location, and rate are required.');
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        $db = getDB();
        $currentUser = getCurrentUser();
        
        $debugInfo[] = "Starting hotel registration for user ID: " . $currentUser['id'];
        
        // Step 1: Insert hotel
        $debugInfo[] = "Step 1: Inserting hotel";
        $hotelId = $db->insert(
            "INSERT INTO hotels (hotel_name, location, address, phone, email, is_active, created_by) VALUES (?, ?, ?, ?, ?, 1, ?)",
            [$hotelName, $location, $address, $phone, $email, $currentUser['id']]
        );
        
        if (!$hotelId) {
            throw new Exception('Failed to create hotel record');
        }
        
        $debugInfo[] = "Hotel created successfully with ID: " . $hotelId;
        
        // Step 2: Insert rate with detailed error handling
        $debugInfo[] = "Step 2: Inserting hotel rate";
        
        try {
            // First, try the standard insert
            $rateId = $db->insert(
                "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by) VALUES (?, ?, ?, 1, ?)",
                [$hotelId, $initialRate, date('Y-m-d'), $currentUser['id']]
            );
            
            if ($rateId) {
                $debugInfo[] = "Rate created successfully with ID: " . $rateId;
                $message = "Hotel registered successfully! Hotel ID: $hotelId, Rate ID: $rateId";
                $messageType = 'success';
                $_POST = array(); // Clear form
            } else {
                throw new Exception('Rate insert returned no ID');
            }
            
        } catch (Exception $rateError) {
            $debugInfo[] = "Rate insert failed: " . $rateError->getMessage();
            
            // Try alternative rate insert without is_current
            try {
                $debugInfo[] = "Trying alternative rate insert...";
                $rateId = $db->insert(
                    "INSERT INTO hotel_rates (hotel_id, rate, effective_date, created_by) VALUES (?, ?, ?, ?)",
                    [$hotelId, $initialRate, date('Y-m-d'), $currentUser['id']]
                );
                
                if ($rateId) {
                    // Update to set as current
                    $db->query("UPDATE hotel_rates SET is_current = 1 WHERE id = ?", [$rateId]);
                    $debugInfo[] = "Alternative rate insert successful with ID: " . $rateId;
                    $message = "Hotel registered successfully! Hotel ID: $hotelId, Rate ID: $rateId (alternative method)";
                    $messageType = 'success';
                    $_POST = array();
                } else {
                    throw new Exception('Alternative rate insert also failed');
                }
                
            } catch (Exception $altError) {
                $debugInfo[] = "Alternative rate insert also failed: " . $altError->getMessage();
                
                // Rate failed but hotel was created - still partial success
                $message = "Hotel created (ID: $hotelId) but rate creation failed. Please add rate manually. Error: " . $rateError->getMessage();
                $messageType = 'error';
            }
        }
        
    } catch (Exception $e) {
        $debugInfo[] = "Main error: " . $e->getMessage();
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get existing hotels
$recentHotels = [];
try {
    $db = getDB();
    $recentHotels = $db->fetchAll(
        "SELECT h.id, h.hotel_name, h.location, h.created_at,
                COALESCE(hr.rate, 0) as current_rate
         FROM hotels h 
         LEFT JOIN hotel_rates hr ON h.id = hr.hotel_id AND hr.is_current = 1 
         WHERE h.is_active = 1 
         ORDER BY h.id DESC 
         LIMIT 5"
    );
} catch (Exception $e) {
    $debugInfo[] = "Failed to load recent hotels: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Hotel - Fixed Version</title>
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
            max-width: 1000px;
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
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        .form-container, .info-container {
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
            height: 80px;
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
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
        }

        .hotels-list h3 {
            margin-bottom: 1rem;
            color: #2d3748;
        }

        .hotel-item {
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
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

        .rate-badge {
            background: #ebf4ff;
            color: #2b6cb0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .no-rate {
            background: #fed7d7;
            color: #c53030;
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">Register Hotel - Fixed Version</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">Hotel Registration</h2>
                <p class="form-subtitle">Add a new hotel with better error handling</p>
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
                           required placeholder="Enter hotel name">
                </div>

                <div class="form-group">
                    <label for="location">Location <span class="required">*</span></label>
                    <input type="text" id="location" name="location" 
                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                           required placeholder="e.g., Colombo, Kandy">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" placeholder="Full address (optional)"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                           placeholder="e.g., +94 11 1234567">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           placeholder="hotel@example.com">
                </div>

                <div class="form-group">
                    <label for="initial_rate">Room Rate (LKR) <span class="required">*</span></label>
                    <input type="number" id="initial_rate" name="initial_rate" 
                           value="<?php echo htmlspecialchars($_POST['initial_rate'] ?? ''); ?>" 
                           required min="1" step="0.01" placeholder="e.g., 5000">
                </div>

                <button type="submit" class="btn">Register Hotel</button>
            </form>

            <?php if (!empty($debugInfo)): ?>
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    <?php foreach ($debugInfo as $info): ?>
                        <?php echo htmlspecialchars($info); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="info-container">
            <h3>📋 Recent Hotels</h3>
            <?php if (!empty($recentHotels)): ?>
                <?php foreach ($recentHotels as $hotel): ?>
                    <div class="hotel-item">
                        <div class="hotel-name"><?php echo htmlspecialchars($hotel['hotel_name']); ?></div>
                        <div class="hotel-details">
                            📍 <?php echo htmlspecialchars($hotel['location']); ?><br>
                            <?php if ($hotel['current_rate'] > 0): ?>
                                <span class="rate-badge">LKR <?php echo number_format($hotel['current_rate'], 2); ?>/night</span>
                            <?php else: ?>
                                <span class="rate-badge no-rate">No Rate Set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #718096; font-style: italic;">No hotels registered yet</p>
            <?php endif; ?>

            <h3 style="margin-top: 2rem;">🔧 Debug Tools</h3>
            <p><a href="../debug_rates.php" target="_blank" style="color: #667eea;">Debug Rate Insert Issues</a></p>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const hotelName = document.getElementById('hotel_name').value.trim();
            const location = document.getElementById('location').value.trim();
            const rate = parseFloat(document.getElementById('initial_rate').value);

            if (!hotelName || !location || !rate || rate <= 0) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }
        });

        document.getElementById('hotel_name').focus();
    </script>
</body>
</html>