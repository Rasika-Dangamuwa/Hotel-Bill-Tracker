<?php
/**
 * Authentication Helper Functions
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Contains functions for session management and authentication checks
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user information
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'login_time' => $_SESSION['login_time'],
        'last_activity' => $_SESSION['last_activity']
    ];
}

/**
 * Check if user has specific role
 * @param string $role Role to check
 * @return bool True if user has role
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

/**
 * Check if user is admin
 * @return bool True if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is account assistant
 * @return bool True if user is account assistant
 */
function isAccountAssistant() {
    return hasRole('account_assistant');
}

/**
 * Require login - redirect to login page if not logged in
 * @param string $redirectTo Page to redirect to after login
 */
function requireLogin($redirectTo = '') {
    if (!isLoggedIn()) {
        $redirect = $redirectTo ? '?redirect=' . urlencode($redirectTo) : '';
        header('Location: index.php' . $redirect);
        exit;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Require specific role
 * @param string $role Required role
 * @param string $errorMessage Error message to show
 */
function requireRole($role, $errorMessage = 'Access denied. Insufficient permissions.') {
    requireLogin();
    
    if (!hasRole($role)) {
        http_response_code(403);
        die($errorMessage);
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireRole('admin', 'Access denied. Admin privileges required.');
}

/**
 * Check session timeout
 * @param int $timeoutMinutes Timeout in minutes (default 120 = 2 hours)
 * @return bool True if session is valid
 */
function checkSessionTimeout($timeoutMinutes = 120) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $timeout = $timeoutMinutes * 60; // Convert to seconds
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    
    if ((time() - $lastActivity) > $timeout) {
        // Session expired
        logout();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Logout user and destroy session
 */
function logout() {
    if (isLoggedIn()) {
        // Log logout activity
        try {
            $db = getDB();
            $db->query(
                "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    'users',
                    $_SESSION['user_id'],
                    'LOGOUT',
                    json_encode(['logout_time' => date('Y-m-d H:i:s')]),
                    $_SESSION['user_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
        } catch (Exception $e) {
            error_log("Logout logging error: " . $e->getMessage());
        }
    }
    
    // Destroy session
    session_destroy();
    session_start();
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token HTML input
 * @return string HTML input field
 */
function getCSRFInput() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Hash password
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Sanitize output for HTML
 * @param string $string String to sanitize
 * @return string Sanitized string
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get user display name
 * @return string User's display name
 */
function getUserDisplayName() {
    $user = getCurrentUser();
    return $user ? $user['name'] : 'Guest';
}

/**
 * Get user role display name
 * @return string User's role display name
 */
function getUserRoleDisplay() {
    $user = getCurrentUser();
    if (!$user) return 'Guest';
    
    switch ($user['role']) {
        case 'admin':
            return 'Administrator';
        case 'account_assistant':
            return 'Account Assistant';
        default:
            return ucfirst($user['role']);
    }
}

/**
 * Check if session is about to expire (within 10 minutes)
 * @return bool True if session expires soon
 */
function sessionExpiresSoon() {
    if (!isLoggedIn()) return false;
    
    $timeout = 120 * 60; // 2 hours in seconds
    $warningTime = 10 * 60; // 10 minutes in seconds
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $timeLeft = $timeout - (time() - $lastActivity);
    
    return $timeLeft <= $warningTime && $timeLeft > 0;
}

/**
 * Get time until session expires
 * @return int Seconds until session expires
 */
function getSessionTimeLeft() {
    if (!isLoggedIn()) return 0;
    
    $timeout = 120 * 60; // 2 hours in seconds
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $timeLeft = $timeout - (time() - $lastActivity);
    
    return max(0, $timeLeft);
}
?>