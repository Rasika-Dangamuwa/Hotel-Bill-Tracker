<?php
/**
 * Login Process Handler
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Handles user authentication and session management
 */

// Start session
session_start();

// Set content type for JSON response
header('Content-Type: application/json');

// Allow access to database file
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON data');
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
    
    // Find user by email
    $user = $db->fetchRow(
        "SELECT id, name, email, password, role, is_active FROM users WHERE email = ? AND is_active = 1",
        [$email]
    );
    
    // Check if user exists
    if (!$user) {
        sendResponse(false, 'Invalid email or password');
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        sendResponse(false, 'Invalid email or password');
    }
    
    // Check if user is active
    if (!$user['is_active']) {
        sendResponse(false, 'Your account has been deactivated. Please contact administrator.');
    }
    
    // Login successful - create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Log successful login
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
    // Log error
    error_log("Login error: " . $e->getMessage());
    
    // Send error response
    sendResponse(false, 'An error occurred during login. Please try again.');
}
?>