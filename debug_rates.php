<?php
/**
 * Debug Rate Insert Issue
 * Save as: debug_rates.php
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

echo "<h2>🔧 Debug Rate Insert Issue</h2>";

try {
    $db = getDB();
    $currentUser = getCurrentUser();
    
    echo "<h3>1. Current User Info:</h3>";
    echo "User ID: " . $currentUser['id'] . "<br>";
    echo "User Name: " . $currentUser['name'] . "<br>";
    echo "User Role: " . $currentUser['role'] . "<br><br>";
    
    echo "<h3>2. Hotel Rates Table Structure:</h3>";
    $ratesStructure = $db->fetchAll("DESCRIBE hotel_rates");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($ratesStructure as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "<h3>3. Test Data for Rate Insert:</h3>";
    // Get a hotel ID to test with
    $testHotel = $db->fetchRow("SELECT id, hotel_name FROM hotels ORDER BY id DESC LIMIT 1");
    if ($testHotel) {
        echo "Test Hotel ID: " . $testHotel['id'] . " (" . $testHotel['hotel_name'] . ")<br>";
        echo "Test Rate: 5000.00<br>";
        echo "Test User ID: " . $currentUser['id'] . "<br>";
        echo "Test Date: " . date('Y-m-d') . "<br><br>";
        
        echo "<h3>4. Attempt Rate Insert:</h3>";
        
        // Test 1: Try the exact SQL from the failing code
        echo "<strong>Test 1: Original SQL</strong><br>";
        $sql1 = "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by) VALUES (?, ?, CURDATE(), 1, ?)";
        $params1 = [$testHotel['id'], 5000.00, $currentUser['id']];
        
        echo "SQL: " . $sql1 . "<br>";
        echo "Params: " . json_encode($params1) . "<br>";
        
        try {
            $rateId1 = $db->insert($sql1, $params1);
            echo "<span style='color: green;'>✓ SUCCESS: Rate ID " . $rateId1 . "</span><br><br>";
            
            // Clean up
            $db->query("DELETE FROM hotel_rates WHERE id = ?", [$rateId1]);
            echo "Test record cleaned up.<br><br>";
            
        } catch (Exception $e) {
            echo "<span style='color: red;'>✗ FAILED: " . $e->getMessage() . "</span><br>";
            echo "Error Code: " . $e->getCode() . "<br><br>";
        }
        
        // Test 2: Try with explicit date
        echo "<strong>Test 2: With Explicit Date</strong><br>";
        $sql2 = "INSERT INTO hotel_rates (hotel_id, rate, effective_date, is_current, created_by) VALUES (?, ?, ?, 1, ?)";
        $params2 = [$testHotel['id'], 5000.00, date('Y-m-d'), $currentUser['id']];
        
        echo "SQL: " . $sql2 . "<br>";
        echo "Params: " . json_encode($params2) . "<br>";
        
        try {
            $rateId2 = $db->insert($sql2, $params2);
            echo "<span style='color: green;'>✓ SUCCESS: Rate ID " . $rateId2 . "</span><br><br>";
            
            // Clean up
            $db->query("DELETE FROM hotel_rates WHERE id = ?", [$rateId2]);
            echo "Test record cleaned up.<br><br>";
            
        } catch (Exception $e) {
            echo "<span style='color: red;'>✗ FAILED: " . $e->getMessage() . "</span><br>";
            echo "Error Code: " . $e->getCode() . "<br><br>";
        }
        
        // Test 3: Try minimal insert
        echo "<strong>Test 3: Minimal Required Fields</strong><br>";
        $sql3 = "INSERT INTO hotel_rates (hotel_id, rate, effective_date, created_by) VALUES (?, ?, ?, ?)";
        $params3 = [$testHotel['id'], 5000.00, date('Y-m-d'), $currentUser['id']];
        
        echo "SQL: " . $sql3 . "<br>";
        echo "Params: " . json_encode($params3) . "<br>";
        
        try {
            $rateId3 = $db->insert($sql3, $params3);
            echo "<span style='color: green;'>✓ SUCCESS: Rate ID " . $rateId3 . "</span><br><br>";
            
            // Clean up
            $db->query("DELETE FROM hotel_rates WHERE id = ?", [$rateId3]);
            echo "Test record cleaned up.<br><br>";
            
        } catch (Exception $e) {
            echo "<span style='color: red;'>✗ FAILED: " . $e->getMessage() . "</span><br>";
            echo "Error Code: " . $e->getCode() . "<br><br>";
        }
        
        // Test 4: Check foreign key constraints
        echo "<h3>5. Foreign Key Constraint Check:</h3>";
        
        // Check if hotel_id exists
        $hotelExists = $db->fetchValue("SELECT COUNT(*) FROM hotels WHERE id = ?", [$testHotel['id']]);
        echo "Hotel ID " . $testHotel['id'] . " exists: " . ($hotelExists ? "YES" : "NO") . "<br>";
        
        // Check if user_id exists
        $userExists = $db->fetchValue("SELECT COUNT(*) FROM users WHERE id = ?", [$currentUser['id']]);
        echo "User ID " . $currentUser['id'] . " exists: " . ($userExists ? "YES" : "NO") . "<br><br>";
        
        // Test 5: Check existing rates for this hotel
        echo "<h3>6. Existing Rates for This Hotel:</h3>";
        $existingRates = $db->fetchAll("SELECT * FROM hotel_rates WHERE hotel_id = ?", [$testHotel['id']]);
        if ($existingRates) {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Rate</th><th>Effective Date</th><th>Is Current</th><th>Created By</th></tr>";
            foreach ($existingRates as $rate) {
                echo "<tr>";
                echo "<td>" . $rate['id'] . "</td>";
                echo "<td>" . $rate['rate'] . "</td>";
                echo "<td>" . $rate['effective_date'] . "</td>";
                echo "<td>" . $rate['is_current'] . "</td>";
                echo "<td>" . $rate['created_by'] . "</td>";
                echo "</tr>";
            }
            echo "</table><br>";
        } else {
            echo "No existing rates for this hotel.<br><br>";
        }
        
    } else {
        echo "<span style='color: red;'>No hotels found to test with!</span><br>";
    }
    
    echo "<h3>7. Recent Error Logs:</h3>";
    echo "Check your PHP error log for detailed error messages.<br>";
    echo "Error log location: " . ini_get('error_log') . "<br>";
    
} catch (Exception $e) {
    echo "<span style='color: red;'>Database Error: " . $e->getMessage() . "</span><br>";
}

echo "<br><a href='hotels/register.php'>Back to Hotel Registration</a>";
?>
