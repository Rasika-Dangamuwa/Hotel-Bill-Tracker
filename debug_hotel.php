<?php
/**
 * Debug Hotel Registration Issue
 * Save as: debug_hotel.php (temporary file)
 */

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die('Please login first');
}

echo "<h2>Hotel Registration Debug</h2>";

try {
    $db = getDB();
    
    // Test 1: Check if hotels table exists and structure
    echo "<h3>1. Hotels Table Structure:</h3>";
    $tableInfo = $db->fetchAll("DESCRIBE hotels");
    echo "<pre>";
    print_r($tableInfo);
    echo "</pre>";
    
    // Test 2: Check if hotel_rates table exists and structure
    echo "<h3>2. Hotel Rates Table Structure:</h3>";
    $ratesInfo = $db->fetchAll("DESCRIBE hotel_rates");
    echo "<pre>";
    print_r($ratesInfo);
    echo "</pre>";
    
    // Test 3: Try a simple insert
    echo "<h3>3. Test Simple Insert:</h3>";
    
    // Get current user
    $currentUser = getCurrentUser();
    echo "Current User ID: " . $currentUser['id'] . "<br>";
    
    // Test data
    $testHotelName = "Test Hotel " . date('Y-m-d H:i:s');
    $testLocation = "Test Location";
    $testRate = 5000.00;
    
    try {
        $db->beginTransaction();
        
        // Insert hotel with explicit column names
        echo "Attempting to insert hotel...<br>";
        $hotelId = $db->insert(
            "INSERT INTO hotels (hotel_name, location, address, phone, email, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$testHotelName, $testLocation, 'Test Address', '0111234567', 'test@test.com', $currentUser['id']]
        );
        echo "Hotel inserted successfully! Hotel ID: $hotelId<br>";
        
        // Insert rate
        echo "Attempting to insert hotel rate...<br>";
        $rateId = $db->insert(
            "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by, created_at) VALUES (?, ?, CURDATE(), 1, ?, NOW())",
            [$hotelId, $testRate, $currentUser['id']]
        );
        echo "Rate inserted successfully! Rate ID: $rateId<br>";
        
        $db->commit();
        echo "<strong style='color: green;'>Test Insert Successful!</strong><br>";
        
        // Clean up test data
        $db->query("DELETE FROM hotel_rates WHERE id = ?", [$rateId]);
        $db->query("DELETE FROM hotels WHERE id = ?", [$hotelId]);
        echo "Test data cleaned up.<br>";
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        echo "<strong style='color: red;'>Insert Error: " . $e->getMessage() . "</strong><br>";
        echo "SQL Error Code: " . $e->getCode() . "<br>";
    }
    
    // Test 4: Check foreign key constraints
    echo "<h3>4. Foreign Key Constraints:</h3>";
    try {
        $constraints = $db->fetchAll("
            SELECT 
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE REFERENCED_TABLE_SCHEMA = 'hotel_tracking_system' 
            AND TABLE_NAME IN ('hotels', 'hotel_rates')
        ");
        echo "<pre>";
        print_r($constraints);
        echo "</pre>";
    } catch (Exception $e) {
        echo "Error checking constraints: " . $e->getMessage() . "<br>";
    }
    
    // Test 5: Check if audit_log table exists
    echo "<h3>5. Audit Log Table Check:</h3>";
    try {
        $auditExists = $db->fetchValue("SHOW TABLES LIKE 'audit_log'");
        if ($auditExists) {
            echo "✅ audit_log table exists<br>";
            $auditStructure = $db->fetchAll("DESCRIBE audit_log");
            echo "<pre>";
            print_r($auditStructure);
            echo "</pre>";
        } else {
            echo "❌ audit_log table does NOT exist<br>";
        }
    } catch (Exception $e) {
        echo "Error checking audit_log: " . $e->getMessage() . "<br>";
    }
    
    // Test 6: Check users table
    echo "<h3>6. Current User Check:</h3>";
    try {
        $user = $db->fetchRow("SELECT * FROM users WHERE id = ?", [$currentUser['id']]);
        echo "Current user data:<br>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
    } catch (Exception $e) {
        echo "Error checking user: " . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "<strong style='color: red;'>Database Error: " . $e->getMessage() . "</strong><br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<br><a href='hotels/register.php'>Back to Hotel Registration</a>";
?>