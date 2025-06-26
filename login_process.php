<?php
/**
 * Login Process Handler - Simplified
 * Hotel Bill Tracking System - Nestle Lanka Limited
 */

// Start session
session_start();

// Set content type for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 0); // Turn off for production
error_reporting(E_ALL);

// Set the access flag
define('ALLOW_ACCESS', true);

// Include database connection
require_once 'includes/db.php';

// Function to send JSON response
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

try {
    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Debug logging
    error_log("Raw input: " . $rawInput);
    error_log("Decoded input: " . print_r($input, true));
    
    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON data: ' . json_last_error_msg());
    }
    
    // Validate required fields
    if (empty($input['email']) || empty($input['password'])) {
        sendResponse(false, 'Email and password are required');
    }
    
    $email = trim($input['email']);
    $password = $input['password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format');
    }
    
    // Get database connection
    $db = getDB();
    
    // Find user by email (simple query)
    $user = $db->fetchRow(
        "SELECT id, name, email, password, role FROM users WHERE email = ?",
        [$email]
    );
    
    // Debug: Log the query result
    error_log("Login attempt for: " . $email);
    error_log("User found: " . ($user ? 'Yes - ID: ' . $user['id'] : 'No'));
    
    // Check if user exists
    if (!$user) {
        sendResponse(false, 'Invalid email or password');
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        error_log("Password verification failed for user: " . $email);
        sendResponse(false, 'Invalid email or password');
    }
    
    // Login successful - create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Log successful login (optional - skip if audit_log table doesn't exist)
    try {
        $db->query(
            "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [
                'users',
                $user['id'],
                'LOGIN',
                json_encode(['login_time' => date('Y-m-d H:i:s')]),
                $user['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        error_log("Audit log entry created for user: " . $email);
    } catch (Exception $e) {
        // Just log the error, don't fail the login
        error_log("Audit log failed: " . $e->getMessage());
    }
    
    // Send success response
    sendResponse(true, 'Login successful', [
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'redirect' => 'dashboard.php'
    ]);
    
} catch (Exception $e) {
    // Log detailed error
    error_log("Login error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Send error response
    sendResponse(false, 'Database connection error. Please try again.');
}
?>