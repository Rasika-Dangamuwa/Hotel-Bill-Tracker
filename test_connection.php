<?php
/**
 * Database Connection Test
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Use this file to test your database connection
 * Access: http://yoursite/test_connection.php
 */

// Set the access flag BEFORE including db.php
if (!defined('ALLOW_ACCESS')) {
    define('ALLOW_ACCESS', true);
}

// Include database connection
require_once 'includes/db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: #27ae60;
            background: #d5f4e6;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #e74c3c;
            background: #fdeaea;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            color: #3498db;
            background: #ebf3fd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .test-result {
            margin: 15px 0;
            padding: 10px;
            border-left: 4px solid #ddd;
            background: #f9f9f9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🔌 Database Connection Test</h1>
        <p><strong>Hotel Bill Tracking System - Nestle Lanka Limited</strong></p>
        
        <?php
        echo "<div class='info'>";
        echo "<strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "<br>";
        echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
        echo "<strong>Server:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown');
        echo "</div>";

        // Test 1: Basic Connection
        echo "<h2>📋 Test Results</h2>";
        
        try {
            echo "<div class='test-result'>";
            echo "<h3>1. Database Connection Test</h3>";
            
            if (DatabaseConfig::testConnection()) {
                echo "<div class='success'>✅ <strong>SUCCESS:</strong> Database connection established!</div>";
            } else {
                echo "<div class='error'>❌ <strong>FAILED:</strong> Cannot connect to database</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ <strong>CONNECTION ERROR:</strong> " . $e->getMessage() . "</div>";
        }
        echo "</div>";

        // Test 2: Database Structure
        try {
            echo "<div class='test-result'>";
            echo "<h3>2. Database Structure Test</h3>";
            
            $db = getDB();
            
            // Check if tables exist
            $tables = ['users', 'hotels', 'hotel_rates', 'employees', 'bills', 'bill_employees', 'audit_log'];
            $existingTables = [];
            
            foreach ($tables as $table) {
                try {
                    $result = $db->fetchValue("SHOW TABLES LIKE ?", [$table]);
                    if ($result) {
                        $existingTables[] = $table;
                    }
                } catch (Exception $e) {
                    // Table check failed, continue
                }
            }
            
            if (count($existingTables) === count($tables)) {
                echo "<div class='success'>✅ <strong>SUCCESS:</strong> All required tables exist</div>";
            } else {
                echo "<div class='error'>❌ <strong>WARNING:</strong> Some tables are missing</div>";
                $missingTables = array_diff($tables, $existingTables);
                echo "<p><strong>Missing tables:</strong> " . implode(', ', $missingTables) . "</p>";
                echo "<div class='info'><strong>Solution:</strong> Run the database schema SQL to create missing tables</div>";
            }
            
            echo "<p><strong>Existing tables:</strong> " . implode(', ', $existingTables) . "</p>";
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='test-result'>";
            echo "<div class='error'>❌ <strong>TABLE CHECK ERROR:</strong> " . $e->getMessage() . "</div>";
            echo "</div>";
        }

        // Test 3: Sample Data Check
        try {
            echo "<div class='test-result'>";
            echo "<h3>3. Sample Data Test</h3>";
            
            $db = getDB();
            
            // Check users table
            $userCount = $db->fetchValue("SELECT COUNT(*) FROM users");
            if ($userCount > 0) {
                echo "<div class='success'>✅ <strong>Users found:</strong> $userCount users in database</div>";
                
                // Show sample users
                $users = $db->fetchAll("SELECT id, name, email, role FROM users LIMIT 5");
                if ($users) {
                    echo "<table>";
                    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
                    foreach ($users as $user) {
                        echo "<tr>";
                        echo "<td>" . $user['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($user['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<div class='error'>❌ <strong>No users found:</strong> Please run the database schema to create sample users</div>";
            }
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='test-result'>";
            echo "<div class='error'>❌ <strong>DATA CHECK ERROR:</strong> " . $e->getMessage() . "</div>";
            echo "</div>";
        }

        // Test 4: Authentication Test
        try {
            echo "<div class='test-result'>";
            echo "<h3>4. Authentication Test</h3>";
            
            $db = getDB();
            
            // Check if default users exist with correct passwords
            $adminUser = $db->fetchRow("SELECT * FROM users WHERE email = ?", ['admin@nestle.lk']);
            $accountUser = $db->fetchRow("SELECT * FROM users WHERE email = ?", ['accounts@nestle.lk']);
            
            if ($adminUser && $accountUser) {
                echo "<div class='success'>✅ <strong>Default users exist</strong></div>";
                echo "<div class='info'>";
                echo "<strong>Test Login Credentials:</strong><br>";
                echo "Admin: admin@nestle.lk / password<br>";
                echo "Account Assistant: accounts@nestle.lk / password";
                echo "</div>";
                
                // Test password verification
                if (password_verify('password', $adminUser['password'])) {
                    echo "<div class='success'>✅ <strong>Password verification working</strong></div>";
                } else {
                    echo "<div class='error'>❌ <strong>Password verification failed</strong></div>";
                }
            } else {
                echo "<div class='error'>❌ <strong>Default users not found</strong></div>";
            }
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='test-result'>";
            echo "<div class='error'>❌ <strong>AUTH TEST ERROR:</strong> " . $e->getMessage() . "</div>";
            echo "</div>";
        }

        // Test 5: Login Process Test
        try {
            echo "<div class='test-result'>";
            echo "<h3>5. Login Process Test</h3>";
            
            // Check if login_process.php exists
            if (file_exists('login_process.php')) {
                echo "<div class='success'>✅ <strong>login_process.php exists</strong></div>";
            } else {
                echo "<div class='error'>❌ <strong>login_process.php is missing</strong></div>";
            }
            
            // Check if includes/auth.php exists
            if (file_exists('includes/auth.php')) {
                echo "<div class='success'>✅ <strong>includes/auth.php exists</strong></div>";
            } else {
                echo "<div class='error'>❌ <strong>includes/auth.php is missing</strong></div>";
            }
            
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='test-result'>";
            echo "<div class='error'>❌ <strong>FILE CHECK ERROR:</strong> " . $e->getMessage() . "</div>";
            echo "</div>";
        }

        // Configuration Info
        echo "<div class='test-result'>";
        echo "<h3>6. Configuration Information</h3>";
        echo "<pre>";
        echo "Database Host: localhost\n";
        echo "Database Name: hotel_tracking_system\n";
        echo "Character Set: utf8mb4\n";
        echo "Timezone: +05:30 (Sri Lanka)\n";
        echo "PDO Driver: " . (extension_loaded('pdo_mysql') ? 'Available' : 'NOT AVAILABLE') . "\n";
        echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
        echo "Current Directory: " . __DIR__ . "\n";
        echo "File Path: " . __FILE__ . "\n";
        echo "</pre>";
        echo "</div>";

        // Instructions
        echo "<div class='info'>";
        echo "<h3>📝 Next Steps:</h3>";
        echo "<ol>";
        echo "<li>If connection failed: Check your database credentials in <code>includes/db.php</code></li>";
        echo "<li>If tables are missing: Run the SQL schema file to create tables</li>";
        echo "<li>If users are missing: Import the sample data from schema.sql</li>";
        echo "<li>If login files are missing: Create login_process.php and includes/auth.php</li>";
        echo "<li>If everything is green: Your system is ready! Delete this test file for security.</li>";
        echo "</ol>";
        echo "</div>";
        ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Login Page</a>
        </div>
    </div>
</body>
</html>