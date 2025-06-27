<?php
/**
 * Bill Deletion Handler
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Securely delete bills with proper audit logging
 */

// Start session and set headers
session_start();
header('Content-Type: application/json');

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Function to send JSON response
function sendResponse($success, $message = '', $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Check if user is logged in
requireLogin();

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

try {
    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON data');
    }
    
    // Validate required fields
    if (empty($input['bill_id']) || empty($input['confirm_delete'])) {
        sendResponse(false, 'Bill ID and confirmation are required');
    }
    
    $billId = intval($input['bill_id']);
    $confirmDelete = $input['confirm_delete'];
    
    if (!$confirmDelete) {
        sendResponse(false, 'Delete confirmation is required');
    }
    
    // Get database connection
    $db = getDB();
    $currentUser = getCurrentUser();
    
    // Get bill details before deletion for audit log
    $bill = $db->fetchRow(
        "SELECT b.*, h.hotel_name, h.location 
         FROM bills b 
         JOIN hotels h ON b.hotel_id = h.id 
         WHERE b.id = ?",
        [$billId]
    );
    
    if (!$bill) {
        sendResponse(false, 'Bill not found');
    }
    
    // Get employee assignments before deletion for audit log
    $employees = $db->fetchAll(
        "SELECT e.name, be.stay_date 
         FROM bill_employees be 
         JOIN employees e ON be.employee_id = e.id 
         WHERE be.bill_id = ? 
         ORDER BY be.stay_date, e.name",
        [$billId]
    );
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Delete employee assignments first (due to foreign key constraints)
        $deletedAssignments = $db->execute("DELETE FROM bill_employees WHERE bill_id = ?", [$billId]);
        
        // Delete the bill
        $deletedBills = $db->execute("DELETE FROM bills WHERE id = ?", [$billId]);
        
        if ($deletedBills === 0) {
            throw new Exception('Bill not found or already deleted');
        }
        
        // Log the deletion for audit purposes
        $auditData = [
            'deleted_bill' => [
                'id' => $bill['id'],
                'invoice_number' => $bill['invoice_number'],
                'hotel_name' => $bill['hotel_name'],
                'location' => $bill['location'],
                'check_in' => $bill['check_in'],
                'check_out' => $bill['check_out'],
                'total_amount' => $bill['total_amount'],
                'room_count' => $bill['room_count'],
                'total_nights' => $bill['total_nights'],
                'status' => $bill['status']
            ],
            'deleted_employees' => $employees,
            'deleted_assignments_count' => $deletedAssignments,
            'deletion_reason' => 'User requested deletion via edit page',
            'deletion_timestamp' => date('Y-m-d H:i:s')
        ];
        
        $db->query(
            "INSERT INTO audit_log (table_name, record_id, action, old_values, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                'bills',
                $billId,
                'DELETE',
                json_encode($auditData),
                json_encode(['deleted' => true]),
                $currentUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        
        // Commit transaction
        $db->commit();
        
        // Send success response
        sendResponse(true, 'Bill deleted successfully', [
            'deleted_bill_id' => $billId,
            'invoice_number' => $bill['invoice_number'],
            'deleted_employee_assignments' => $deletedAssignments
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    // Log detailed error
    error_log("Bill deletion error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Send error response
    sendResponse(false, 'Failed to delete bill: ' . $e->getMessage());
}
?>