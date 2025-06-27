<?php
/**
 * Employee Conflicts Check API - Updated for Edit Mode
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * Real-time API to check for employee assignment conflicts
 * Now supports excluding current bill from conflict detection
 */

// Start session and set headers
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
if (!isLoggedIn()) {
    sendResponse(false, 'Authentication required');
}

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
    if (empty($input['employee_id']) || empty($input['check_in']) || empty($input['check_out'])) {
        sendResponse(false, 'Employee ID, check-in, and check-out dates are required');
    }
    
    $employeeId = intval($input['employee_id']);
    $checkIn = $input['check_in'];
    $checkOut = $input['check_out'];
    $excludeBillId = isset($input['exclude_bill_id']) ? intval($input['exclude_bill_id']) : null;
    
    // Validate dates
    $checkInDate = new DateTime($checkIn);
    $checkOutDate = new DateTime($checkOut);
    
    if ($checkOutDate <= $checkInDate) {
        sendResponse(false, 'Check-out date must be after check-in date');
    }
    
    // Get database connection
    $db = getDB();
    
    // Check if employee exists
    $employee = $db->fetchRow("SELECT name FROM employees WHERE id = ? AND is_active = 1", [$employeeId]);
    if (!$employee) {
        sendResponse(false, 'Employee not found');
    }
    
    // Generate all dates between check-in and check-out (excluding check-out date)
    $conflicts = [];
    $currentDate = clone $checkInDate;
    
    while ($currentDate < $checkOutDate) {
        $stayDate = $currentDate->format('Y-m-d');
        
        // Prepare conflict check SQL with optional bill exclusion
        $conflictSql = "SELECT 
                b.id as bill_id,
                b.invoice_number,
                b.check_in,
                b.check_out,
                b.total_amount,
                b.room_count,
                h.hotel_name,
                h.location,
                u.name as submitted_by
             FROM bill_employees be 
             JOIN bills b ON be.bill_id = b.id 
             JOIN hotels h ON b.hotel_id = h.id 
             JOIN users u ON b.submitted_by = u.id
             WHERE be.employee_id = ? AND be.stay_date = ?";
        
        $conflictParams = [$employeeId, $stayDate];
        
        // Add exclusion for current bill if editing
        if ($excludeBillId) {
            $conflictSql .= " AND b.id != ?";
            $conflictParams[] = $excludeBillId;
        }
        
        $conflictBill = $db->fetchRow($conflictSql, $conflictParams);
        
        if ($conflictBill) {
            $conflicts[] = [
                'date' => $stayDate,
                'bill_id' => $conflictBill['bill_id'],
                'invoice' => $conflictBill['invoice_number'],
                'hotel' => $conflictBill['hotel_name'],
                'location' => $conflictBill['location'],
                'bill_details' => [
                    'check_in' => $conflictBill['check_in'],
                    'check_out' => $conflictBill['check_out'],
                    'total_amount' => $conflictBill['total_amount'],
                    'room_count' => $conflictBill['room_count'],
                    'submitted_by' => $conflictBill['submitted_by']
                ]
            ];
        }
        
        $currentDate->add(new DateInterval('P1D'));
    }
    
    // Send response with conflicts
    sendResponse(true, 'Conflict check completed', [
        'employee_id' => $employeeId,
        'employee_name' => $employee['name'],
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'exclude_bill_id' => $excludeBillId,
        'conflicts' => $conflicts,
        'conflict_count' => count($conflicts),
        'has_conflicts' => !empty($conflicts)
    ]);
    
} catch (Exception $e) {
    error_log("Employee conflicts API error: " . $e->getMessage());
    sendResponse(false, 'Server error occurred while checking conflicts');
}
?>