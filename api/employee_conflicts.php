<?php
/**
 * Employee Conflicts API
 * Check for booking conflicts when assigning employees to bills
 */

// Start session and include required files
session_start();
define('ALLOW_ACCESS', true);
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_conflicts') {
        $employeeId = intval($_POST['employee_id'] ?? 0);
        $checkIn = $_POST['check_in'] ?? '';
        $checkOut = $_POST['check_out'] ?? '';
        $excludeBillId = intval($_POST['exclude_bill_id'] ?? 0); // For edit mode
        
        if (!$employeeId || !$checkIn || !$checkOut) {
            throw new Exception('Missing required parameters');
        }
        
        // Validate dates
        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);
        
        if ($checkOutDate <= $checkInDate) {
            throw new Exception('Invalid date range');
        }
        
        $db = getDB();
        
        // Check for conflicts - find bills where employee is assigned and dates overlap
        $sql = "SELECT DISTINCT 
                    b.id,
                    b.invoice_number,
                    b.check_in,
                    b.check_out,
                    h.hotel_name,
                    h.location,
                    p.name as propagandist_name,
                    bf.file_number,
                    u.name as submitted_by_name
                FROM bills b
                JOIN bill_employees be ON b.id = be.bill_id
                JOIN hotels h ON b.hotel_id = h.id
                JOIN users u ON b.submitted_by = u.id
                LEFT JOIN employees p ON b.propagandist_id = p.id
                LEFT JOIN bill_files bf ON b.bill_file_id = bf.id
                WHERE be.employee_id = ?
                AND (
                    (b.check_in < ? AND b.check_out > ?) OR  -- Existing booking overlaps new booking
                    (b.check_in >= ? AND b.check_in < ?)     -- New booking overlaps existing booking
                )";
        
        $params = [$employeeId, $checkOut, $checkIn, $checkIn, $checkOut];
        
        // Exclude current bill if editing
        if ($excludeBillId > 0) {
            $sql .= " AND b.id != ?";
            $params[] = $excludeBillId;
        }
        
        $sql .= " ORDER BY b.check_in";
        
        $conflicts = $db->fetchAll($sql, $params);
        
        // Format the response
        $formattedConflicts = [];
        foreach ($conflicts as $conflict) {
            $formattedConflicts[] = [
                'bill_id' => $conflict['id'],
                'invoice_number' => $conflict['invoice_number'],
                'check_in' => date('M j, Y', strtotime($conflict['check_in'])),
                'check_out' => date('M j, Y', strtotime($conflict['check_out'])),
                'hotel_name' => $conflict['hotel_name'],
                'location' => $conflict['location'],
                'propagandist_name' => $conflict['propagandist_name'],
                'file_number' => $conflict['file_number'],
                'submitted_by_name' => $conflict['submitted_by_name']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'conflicts' => $formattedConflicts,
            'has_conflicts' => count($formattedConflicts) > 0
        ]);
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>