<?php
/**
 * Simple Hotel Registration - Minimal Version
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
        $initialRate = floatval($_POST['initial_rate']);
        
        // Basic validation
        if (empty($hotelName) || empty($location) || $initialRate <= 0) {
            throw new Exception('Hotel name, location, and rate are required.');
        }
        
        $db = getDB();
        $currentUser = getCurrentUser();
        
        // Simple insert without transaction - just hotel first
        $hotelId = $db->insert(
            "INSERT INTO hotels (hotel_name, location, is_active, created_by) VALUES (?, ?, 1, ?)",
            [$hotelName, $location, $currentUser['id']]
        );
        
        if ($hotelId) {
            // Then insert rate
            $rateId = $db->insert(
                "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by) VALUES (?, ?, CURDATE(), 1, ?)",
                [$hotelId, $initialRate, $currentUser['id']]
            );
            
            if ($rateId) {
                $message = "Hotel registered successfully! Hotel ID: $hotelId";
                $messageType = 'success';
                $_POST = array(); // Clear form
            } else {
                throw new Exception('Failed to create hotel rate');
            }
        } else {
            throw new Exception('Failed to create hotel');
        }
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get existing hotels
$recentHotels = [];
try {
    $db = getDB();
    $recentHotels = $db->fetchAll("SELECT * FROM hotels WHERE is_active = 1 ORDER BY id DESC LIMIT 5");
} catch (Exception $e) {
    // Ignore errors here
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Hotel - Simple Version</title>
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
            max-width: 800px;
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
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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

        input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: #f8fafc;
        }

        input:focus {
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

        .hotels-list {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        }

        .hotel-location {
            color: #718096;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">Register New Hotel (Simple)</h1>
            <a href="../dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </header>

    <div class="container">
        <div class="form-container">
            <div class="form-header">
                <h2 class="form-title">Hotel Registration</h2>
                <p class="form-subtitle">Simple version - just basic details</p>
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
                           required placeholder="Enter location (e.g., Colombo)">
                </div>

                <div class="form-group">
                    <label for="initial_rate">Room Rate (LKR) <span class="required">*</span></label>
                    <input type="number" id="initial_rate" name="initial_rate" 
                           value="<?php echo htmlspecialchars($_POST['initial_rate'] ?? ''); ?>" 
                           required min="1" step="0.01" placeholder="Enter room rate per night">
                </div>

                <button type="submit" class="btn">Register Hotel</button>
            </form>
        </div>

        <?php if (!empty($recentHotels)): ?>
        <div class="hotels-list">
            <h3>📋 Recent Hotels</h3>
            <?php foreach ($recentHotels as $hotel): ?>
                <div class="hotel-item">
                    <div class="hotel-name"><?php echo htmlspecialchars($hotel['hotel_name']); ?></div>
                    <div class="hotel-location">📍 <?php echo htmlspecialchars($hotel['location']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Simple form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const hotelName = document.getElementById('hotel_name').value.trim();
            const location = document.getElementById('location').value.trim();
            const rate = parseFloat(document.getElementById('initial_rate').value);

            if (!hotelName || !location || !rate || rate <= 0) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            // Simple confirmation
            if (!confirm('Register this hotel?')) {
                e.preventDefault();
            }
        });

        // Auto-focus
        document.getElementById('hotel_name').focus();
    </script>
</body>
</html>