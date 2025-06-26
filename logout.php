<?php
/**
 * Logout Handler
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Handles user logout and session cleanup
 */

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Logout user (this will log the activity and destroy session)
logout();

// Redirect to login page with success message
header('Location: index.php?message=logged_out');
exit;
?>