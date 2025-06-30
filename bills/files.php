<?php
/**
 * Bill Files Management Page
 * Hotel Bill Tracking System - Nestle Lanka Limited
 */

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in
requireLogin();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $db = getDB();
        $currentUser = getCurrentUser();
        
        if ($action === 'create_file') {
            $fileNumber = trim($_POST['file_number']);
            $description = trim($_POST['description']);
            $submittedDate = $_POST['submitted_date'];
            
            // Validate required fields
            if (empty($fileNumber) || empty($submittedDate)) {
                throw new Exception('File number and submitted date are required.');
            }
            
            // Check if file number exists
            $existingFile = $db->fetchRow("SELECT id FROM bill_files WHERE file_number = ?", [$fileNumber]);
            if ($existingFile) {
                throw new Exception('File number already exists. Please use a unique file number.');
            }
            
            // Insert bill file
            $fileId = $db->insert(
                "INSERT INTO bill_files (file_number, description, submitted_date, created_by) VALUES (?, ?, ?, ?)",
                [$fileNumber, $description, $submittedDate, $currentUser['id']]
            );
            
            // Log activity
            $db->insert(
                "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    'bill_files',
                    $fileId,
                    'INSERT',
                    json_encode(['file_number' => $fileNumber, 'description' => $description]),
                    $currentUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            
            $message = 'Bill file created successfully! File ID: ' . $fileId;
            $messageType = 'success';
            
            // Clear form data
            $_POST = [];
            
        } elseif ($action === 'submit_file') {
            $fileId = intval($_POST['file_id']);
            
            if (!$fileId) {
                throw new Exception('Invalid file ID.');
            }
            
            // Check if file exists and is pending
            $file = $db->fetchRow("SELECT * FROM bill_files WHERE id = ? AND status = 'pending'", [$fileId]);
            if (!$file) {
                throw new Exception('File not found or already submitted.');
            }
            
            // Update file status
            $db->query(
                "UPDATE bill_files SET status = 'submitted', submitted_to_finance_date = CURDATE() WHERE id = ?",
                [$fileId]
            );
            
            // Log activity
            $db->insert(
                "INSERT INTO audit_log (table_name, record_id, action, new_values, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    'bill_files',
                    $fileId,
                    'UPDATE',
                    json_encode(['status' => 'submitted', 'submitted_to_finance_date' => date('Y-m-d')]),
                    $currentUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
            
            $message = 'File submitted to finance department successfully!';
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

try {
    $db = getDB();
    
    // Get all bill files with stats
    $billFiles = $db->fetchAll(
        "SELECT 
            bf.id,
            bf.file_number,
            bf.description,
            bf.submitted_date,
            bf.status,
            bf.submitted_to_finance_date,
            bf.total_bills,
            bf.total_amount,
            bf.created_at,
            u.name as created_by_name
         FROM bill_files bf
         JOIN users u ON bf.created_by = u.id
         ORDER BY bf.submitted_date DESC, bf.file_number"
    );
    
    // Get summary statistics
    $stats = $db->fetchRow(
        "SELECT 
            COUNT(*) as total_files,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_files,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_files,
            SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN status = 'submitted' THEN total_amount ELSE 0 END) as submitted_amount
         FROM bill_files"
    );
    
} catch (Exception $e) {
    error_log("Bill files page error: " . $e->getMessage());
    $message = 'Database error occurred. Please try again.';
    $messageType = 'error';
    $billFiles = [];
    $stats = [
        'total_files' => 0,
        'pending_files' => 0,
        'submitted_files' => 0,
        'pending_amount' => 0,
        'submitted_amount' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Files Management - Hotel Tracking System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .pending { border-left: 4px solid #f6ad55; }
        .submitted { border-left: 4px solid #68d391; }
        .total { border-left: 4px solid #667eea; }
        
        .files-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .table-header {
            background: #f7fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fef5e7;
            color: #744210;
        }
        
        .status-submitted {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .action-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .action-submit {
            background: #bee3f8;
            color: #2a69ac;
        }
        
        .action-submit:hover {
            background: #90cdf4;
        }
        
        .action-view {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .action-view:hover {
            background: #cbd5e0;
        }
        
        .quick-create {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .quick-form {
            display: grid;
            grid-template-columns: 2fr 3fr 150px 120px;
            gap: 1rem;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .quick-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="page-title">📁 Bill Files Management</h1>
                <p class="page-subtitle">Create and manage bill file batches for finance submission</p>
            </div>
            <div class="header-actions">
                <a href="add.php" class="btn">Add New Bill</a>
                <a href="view.php" class="btn btn-secondary">View Bills</a>
                <a href="../dashboard.php" class="btn btn-outline">Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-number"><?php echo $stats['pending_files']; ?></div>
                <div class="stat-label">Pending Files</div>
                <div style="font-size: 0.8rem; color: #718096; margin-top: 0.25rem;">
                    LKR <?php echo number_format($stats['pending_amount'], 2); ?>
                </div>
            </div>
            
            <div class="stat-card submitted">
                <div class="stat-number"><?php echo $stats['submitted_files']; ?></div>
                <div class="stat-label">Submitted Files</div>
                <div style="font-size: 0.8rem; color: #718096; margin-top: 0.25rem;">
                    LKR <?php echo number_format($stats['submitted_amount'], 2); ?>
                </div>
            </div>
            
            <div class="stat-card total">
                <div class="stat-number"><?php echo $stats['total_files']; ?></div>
                <div class="stat-label">Total Files</div>
                <div style="font-size: 0.8rem; color: #718096; margin-top: 0.25rem;">
                    LKR <?php echo number_format($stats['pending_amount'] + $stats['submitted_amount'], 2); ?>
                </div>
            </div>
        </div>

        <!-- Quick Create File -->
        <div class="quick-create">
            <h3 style="margin-bottom: 1rem;">🆕 Create New Bill File</h3>
            <form method="POST" action="" class="quick-form">
                <input type="hidden" name="action" value="create_file">
                
                <div class="form-group">
                    <label for="file_number">File Number *</label>
                    <input type="text" id="file_number" name="file_number" 
                           placeholder="e.g., RSK/25/B/04" required maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" 
                           placeholder="Optional description">
                </div>
                
                <div class="form-group">
                    <label for="submitted_date">Date</label>
                    <input type="date" id="submitted_date" name="submitted_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <button type="submit" class="btn">Create File</button>
            </form>
        </div>

        <!-- Files Table -->
        <div class="files-table">
            <div class="table-header">
                <h3 style="margin-bottom: 0.5rem;">All Bill Files</h3>
                <p style="color: #718096; margin: 0;">Manage bill file batches and submit to finance department</p>
            </div>
            
            <?php if (!empty($billFiles)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>File Number</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Bills</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billFiles as $file): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($file['file_number']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($file['description'] ?: 'No description'); ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($file['submitted_date'])); ?>
                                    <br><small style="color: #718096;">
                                        by <?php echo htmlspecialchars($file['created_by_name']); ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <strong><?php echo $file['total_bills']; ?></strong> bills
                                </td>
                                <td class="text-right">
                                    <strong>LKR <?php echo number_format($file['total_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $file['status']; ?>">
                                        <?php echo ucfirst($file['status']); ?>
                                    </span>
                                    <?php if ($file['status'] === 'submitted' && $file['submitted_to_finance_date']): ?>
                                        <br><small style="color: #718096;">
                                            <?php echo date('M j, Y', strtotime($file['submitted_to_finance_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <?php if ($file['status'] === 'pending' && $file['total_bills'] > 0): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Submit this file to finance department?')">
                                                <input type="hidden" name="action" value="submit_file">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <button type="submit" class="action-button action-submit">
                                                    Submit to Finance
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="view.php?file_id=<?php echo $file['id']; ?>" 
                                           class="action-button action-view">
                                            View Bills (<?php echo $file['total_bills']; ?>)
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: #718096;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📁</div>
                    <h3>No Bill Files Created</h3>
                    <p>Create your first bill file to start organizing bills for finance submission.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>