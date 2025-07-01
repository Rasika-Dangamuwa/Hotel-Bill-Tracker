<?php
/**
 * Database Diagnostic Script
 * Check database structure and fix common issues
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic - Hotel Tracking System</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .success { 
            color: #155724; 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            padding: 10px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        .error { 
            color: #721c24; 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            padding: 10px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        .warning { 
            color: #856404; 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            padding: 10px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        .info { 
            color: #004085; 
            background: #d1ecf1; 
            border: 1px solid #bee5eb; 
            padding: 10px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin: 10px 0; 
        }
        table, th, td { 
            border: 1px solid #ddd; 
        }
        th, td { 
            padding: 8px; 
            text-align: left; 
        }
        th { 
            background: #f8f9fa; 
        }
        h1, h2, h3 { 
            color: #333; 
        }
        .fixed { 
            background: #d4edda; 
            color: #155724; 
            font-weight: bold; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Diagnostic Tool</h1>
        <p>This tool checks your database structure and identifies issues with the add bill page.</p>

        <?php
        try {
            $db = getDB();
            
            echo "<h2>📋 Database Connection</h2>";
            echo "<div class='success'>✅ Database connection successful!</div>";
            
            // Test 1: Check table existence
            echo "<h2>📊 Table Structure Check</h2>";
            
            $requiredTables = [
                'hotels' => ['id', 'hotel_name', 'location', 'phone'],
                'employees' => ['id', 'name', 'nic', 'phone', 'department', 'designation'],
                'employee_positions' => ['id', 'employee_id', 'position', 'is_current'],
                'bill_files' => ['id', 'file_number', 'status'],
                'bills' => ['id', 'invoice_number', 'hotel_id', 'propagandist_id', 'bill_file_id'],
                'users' => ['id', 'name']
            ];
            
            foreach ($requiredTables as $tableName => $requiredColumns) {
                echo "<h3>Table: $tableName</h3>";
                
                try {
                    // Check if table exists
                    $tableExists = $db->fetchValue("SHOW TABLES LIKE ?", [$tableName]);
                    
                    if ($tableExists) {
                        echo "<div class='success'>✅ Table '$tableName' exists</div>";
                        
                        // Check columns
                        $columns = $db->fetchAll("DESCRIBE $tableName");
                        $existingColumns = array_column($columns, 'Field');
                        
                        echo "<table>";
                        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
                        foreach ($columns as $column) {
                            echo "<tr>";
                            echo "<td>" . $column['Field'] . "</td>";
                            echo "<td>" . $column['Type'] . "</td>";
                            echo "<td>" . $column['Null'] . "</td>";
                            echo "<td>" . $column['Key'] . "</td>";
                            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                        
                        // Check for missing required columns
                        $missingColumns = array_diff($requiredColumns, $existingColumns);
                        if (!empty($missingColumns)) {
                            echo "<div class='warning'>⚠️ Missing columns: " . implode(', ', $missingColumns) . "</div>";
                        }
                        
                    } else {
                        echo "<div class='error'>❌ Table '$tableName' does NOT exist</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Error checking table '$tableName': " . $e->getMessage() . "</div>";
                }
            }
            
            // Test 2: Check specific queries from add bill page
            echo "<h2>🔍 Query Testing</h2>";
            
            // Test hotels query
            echo "<h3>Hotels Query</h3>";
            try {
                $hotels = $db->fetchAll("SELECT id, hotel_name, location, phone FROM hotels ORDER BY hotel_name");
                echo "<div class='success'>✅ Hotels query successful - " . count($hotels) . " hotels found</div>";
                
                if (count($hotels) > 0) {
                    echo "<div class='info'>Sample hotels:</div>";
                    echo "<table>";
                    echo "<tr><th>ID</th><th>Hotel Name</th><th>Location</th><th>Phone</th></tr>";
                    foreach (array_slice($hotels, 0, 3) as $hotel) {
                        echo "<tr>";
                        echo "<td>" . $hotel['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($hotel['hotel_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($hotel['location']) . "</td>";
                        echo "<td>" . htmlspecialchars($hotel['phone']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div class='warning'>⚠️ No hotels found in database</div>";
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Hotels query failed: " . $e->getMessage() . "</div>";
            }
            
            // Test propagandists query
            echo "<h3>Propagandists Query</h3>";
            try {
                $propagandists = $db->fetchAll(
                    "SELECT e.id, e.name, e.nic, e.phone, e.department 
                     FROM employees e
                     LEFT JOIN employee_positions ep ON e.id = ep.employee_id AND ep.is_current = 1
                     WHERE COALESCE(ep.position, e.designation) = 'Propagandist'
                     ORDER BY e.name"
                );
                echo "<div class='success'>✅ Propagandists query successful - " . count($propagandists) . " propagandists found</div>";
                
                if (count($propagandists) > 0) {
                    echo "<div class='info'>Propagandists:</div>";
                    echo "<table>";
                    echo "<tr><th>ID</th><th>Name</th><th>Department</th><th>Phone</th></tr>";
                    foreach ($propagandists as $prop) {
                        echo "<tr>";
                        echo "<td>" . $prop['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($prop['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($prop['department']) . "</td>";
                        echo "<td>" . htmlspecialchars($prop['phone']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div class='warning'>⚠️ No propagandists found. You need employees with designation or position = 'Propagandist'</div>";
                    
                    // Show existing employees
                    $allEmployees = $db->fetchAll("SELECT id, name, designation FROM employees LIMIT 5");
                    if (!empty($allEmployees)) {
                        echo "<div class='info'>Existing employees (first 5):</div>";
                        echo "<table>";
                        echo "<tr><th>ID</th><th>Name</th><th>Designation</th></tr>";
                        foreach ($allEmployees as $emp) {
                            echo "<tr>";
                            echo "<td>" . $emp['id'] . "</td>";
                            echo "<td>" . htmlspecialchars($emp['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($emp['designation'] ?? 'NULL') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Propagandists query failed: " . $e->getMessage() . "</div>";
            }
            
            // Test bill files query
            echo "<h3>Bill Files Query</h3>";
            try {
                $billFiles = $db->fetchAll(
                    "SELECT id, file_number, 
                            COALESCE(description, '') as description, 
                            created_at,
                            COALESCE(total_bills, 0) as total_bills, 
                            COALESCE(total_amount, 0) as total_amount
                     FROM bill_files 
                     WHERE status = 'pending' 
                     ORDER BY created_at DESC"
                );
                echo "<div class='success'>✅ Bill files query successful - " . count($billFiles) . " pending files found</div>";
                
                if (count($billFiles) > 0) {
                    echo "<div class='info'>Pending bill files:</div>";
                    echo "<table>";
                    echo "<tr><th>ID</th><th>File Number</th><th>Description</th><th>Total Bills</th><th>Total Amount</th></tr>";
                    foreach ($billFiles as $file) {
                        echo "<tr>";
                        echo "<td>" . $file['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($file['file_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($file['description']) . "</td>";
                        echo "<td>" . $file['total_bills'] . "</td>";
                        echo "<td>" . number_format($file['total_amount'], 2) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div class='warning'>⚠️ No pending bill files found. You need to create bill files first.</div>";
                    
                    // Show all bill files
                    $allFiles = $db->fetchAll("SELECT id, file_number, status FROM bill_files LIMIT 5");
                    if (!empty($allFiles)) {
                        echo "<div class='info'>Existing bill files (first 5):</div>";
                        echo "<table>";
                        echo "<tr><th>ID</th><th>File Number</th><th>Status</th></tr>";
                        foreach ($allFiles as $file) {
                            echo "<tr>";
                            echo "<td>" . $file['id'] . "</td>";
                            echo "<td>" . htmlspecialchars($file['file_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($file['status']) . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Bill files query failed: " . $e->getMessage() . "</div>";
            }
            
            // Test 3: Quick fixes
            echo "<h2>🔧 Quick Fixes</h2>";
            
            // Check if we need sample data
            $hotelCount = $db->fetchValue("SELECT COUNT(*) FROM hotels");
            $employeeCount = $db->fetchValue("SELECT COUNT(*) FROM employees");
            $fileCount = $db->fetchValue("SELECT COUNT(*) FROM bill_files WHERE status = 'pending'");
            
            if ($hotelCount == 0) {
                echo "<div class='warning'>⚠️ No hotels found. You need to add hotels first.</div>";
            }
            
            if ($employeeCount == 0) {
                echo "<div class='warning'>⚠️ No employees found. You need to add employees first.</div>";
            }
            
            if ($fileCount == 0) {
                echo "<div class='warning'>⚠️ No pending bill files found. You need to create bill files first.</div>";
            }
            
            echo "<h2>✅ Summary</h2>";
            echo "<div class='info'>";
            echo "<strong>Database Status:</strong><br>";
            echo "• Hotels: $hotelCount<br>";
            echo "• Employees: $employeeCount<br>";
            echo "• Pending Bill Files: $fileCount<br>";
            echo "</div>";
            
            if ($hotelCount > 0 && $employeeCount > 0 && $fileCount > 0) {
                echo "<div class='success'>";
                echo "🎉 <strong>All checks passed!</strong> Your add bill page should work now.<br>";
                echo "<a href='bills/add.php' style='color: #155724; font-weight: bold;'>→ Try Add Bill Page</a>";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "📝 <strong>Setup needed:</strong> Please add the missing data before using the add bill page.<br>";
                if ($hotelCount == 0) echo "• <a href='hotels/register.php'>Add Hotels</a><br>";
                if ($employeeCount == 0) echo "• <a href='employees/register.php'>Add Employees</a><br>";
                if ($fileCount == 0) echo "• <a href='bills/files.php'>Create Bill Files</a><br>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ <strong>Database Error:</strong> " . $e->getMessage() . "</div>";
            echo "<div class='error'><strong>File:</strong> " . $e->getFile() . "</div>";
            echo "<div class='error'><strong>Line:</strong> " . $e->getLine() . "</div>";
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>