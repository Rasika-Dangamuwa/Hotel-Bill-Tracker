<?php
/**
 * Bill Details Page
 * Hotel Bill Tracking System - Nestle Lanka Limited
 * 
 * View complete bill information with employee assignments
 */

// Start session
session_start();

// Set access flag and include required files
define('ALLOW_ACCESS', true);
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in
requireLogin();

// Get bill ID from URL
$billId = intval($_GET['id'] ?? 0);

if (!$billId) {
    header('Location: view.php');
    exit;
}

try {
    $db = getDB();
    
    // Get bill details with hotel and user information
    $bill = $db->fetchRow(
        "SELECT b.*, h.hotel_name, h.location, h.address, h.phone as hotel_phone, h.email as hotel_email,
                hr.rate as room_rate, u.name as submitted_by_name, u.email as submitted_by_email
         FROM bills b 
         JOIN hotels h ON b.hotel_id = h.id 
         JOIN hotel_rates hr ON b.rate_id = hr.id
         JOIN users u ON b.submitted_by = u.id
         WHERE b.id = ?",
        [$billId]
    );
    
    if (!$bill) {
        header('Location: view.php?error=bill_not_found');
        exit;
    }
    
    // Get employee assignments
    $employees = $db->fetchAll(
        "SELECT e.id, e.name, e.nic, e.designation, e.department,
                MIN(be.stay_date) as first_date, MAX(be.stay_date) as last_date,
                COUNT(be.stay_date) as total_nights,
                GROUP_CONCAT(DISTINCT be.room_number ORDER BY be.stay_date) as room_numbers
         FROM bill_employees be 
         JOIN employees e ON be.employee_id = e.id 
         WHERE be.bill_id = ? 
         GROUP BY e.id, e.name, e.nic, e.designation, e.department
         ORDER BY e.name",
        [$billId]
    );
    
    // Get all stay dates for timeline
    $stayDates = $db->fetchAll(
        "SELECT be.stay_date, e.name as employee_name, be.room_number
         FROM bill_employees be 
         JOIN employees e ON be.employee_id = e.id 
         WHERE be.bill_id = ? 
         ORDER BY be.stay_date, e.name",
        [$billId]
    );
    
} catch (Exception $e) {
    error_log("Bill details error: " . $e->getMessage());
    header('Location: view.php?error=database_error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Details - <?php echo htmlspecialchars($bill['invoice_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .btn-edit {
            background: #48bb78;
        }

        .btn-edit:hover {
            background: #38a169;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .bill-summary {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .invoice-info h1 {
            font-size: 2.5rem;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .invoice-meta {
            color: #718096;
            font-size: 1rem;
            line-height: 1.5;
        }

        .status-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .status-pending {
            background: linear-gradient(135deg, #fef5e7, #fed7aa);
            color: #c05621;
        }

        .status-approved {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #2f855a;
        }

        .status-rejected {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #c53030;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .detail-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .detail-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f7fafc;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 120px;
            flex-shrink: 0;
        }

        .detail-value {
            color: #2d3748;
            font-weight: 600;
            text-align: right;
            flex-grow: 1;
        }

        .amount-row {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            padding: 1rem;
            border-radius: 8px;
            margin: 0.75rem 0;
            border-left: 4px solid #667eea;
        }

        .total-row {
            background: linear-gradient(135deg, #2d3748, #4a5568);
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            border-left: 4px solid #48bb78;
        }

        .employees-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .employee-card {
            background: linear-gradient(135deg, #f8fafc, #edf2f7);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .employee-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .employee-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 1rem;
        }

        .employee-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .employee-info h4 {
            margin: 0;
            color: #2d3748;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .employee-meta {
            color: #718096;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .stay-info {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #48bb78;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .timeline {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .timeline-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #f8fafc, #edf2f7);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .timeline-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .timeline-date {
            min-width: 140px;
            font-weight: 700;
            color: #2d3748;
            font-size: 0.95rem;
        }

        .timeline-employees {
            flex: 1;
            color: #4a5568;
            font-weight: 500;
        }

        .no-employees {
            text-align: center;
            color: #718096;
            padding: 3rem;
            font-style: italic;
            background: #f7fafc;
            border-radius: 12px;
            border: 2px dashed #e2e8f0;
        }

        .print-btn {
            background: #4299e1;
            color: white;
        }

        .print-btn:hover {
            background: #3182ce;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .summary-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .details-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }

            .invoice-info h1 {
                font-size: 2rem;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .detail-label {
                min-width: auto;
            }

            .detail-value {
                text-align: left;
            }

            .employee-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .timeline-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .timeline-date {
                min-width: auto;
                font-weight: 700;
                color: #667eea;
            }
        }

        @media print {
            .header-actions, .btn {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .detail-section, .bill-summary, .timeline {
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">Bill Details - <?php echo htmlspecialchars($bill['invoice_number']); ?></h1>
            <div class="header-actions">
                <a href="view.php" class="btn">← Back to Bills</a>
                <button onclick="window.print()" class="btn print-btn">🖨️ Print</button>
                <?php if ($bill['status'] === 'pending'): ?>
                    <a href="edit.php?id=<?php echo $bill['id']; ?>" class="btn btn-edit">✏️ Edit Bill</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Bill Summary -->
        <div class="bill-summary">
            <div class="summary-header">
                <div class="invoice-info">
                    <h1><?php echo htmlspecialchars($bill['invoice_number']); ?></h1>
                    <div class="invoice-meta">
                        Created: <?php echo date('F j, Y \a\t g:i A', strtotime($bill['created_at'])); ?><br>
                        Bill ID: <?php echo $bill['id']; ?>
                    </div>
                </div>
                <div class="status-badge status-<?php echo $bill['status']; ?>">
                    <?php echo ucfirst($bill['status']); ?>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
            <!-- Hotel Information -->
            <div class="detail-section">
                <h3 class="section-title">🏨 Hotel Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Hotel Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bill['hotel_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Location:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bill['location']); ?></span>
                </div>
                <?php if ($bill['address']): ?>
                <div class="detail-row">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bill['address']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($bill['hotel_phone']): ?>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bill['hotel_phone']); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Room Rate:</span>
                    <span class="detail-value">LKR <?php echo number_format($bill['room_rate'], 2); ?> per night</span>
                </div>
            </div>

            <!-- Stay Details -->
            <div class="detail-section">
                <h3 class="section-title">📅 Stay Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Check-in Date:</span>
                    <span class="detail-value"><?php echo date('F j, Y', strtotime($bill['check_in'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-out Date:</span>
                    <span class="detail-value"><?php echo date('F j, Y', strtotime($bill['check_out'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Nights:</span>
                    <span class="detail-value"><?php echo $bill['total_nights']; ?> nights</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Rooms Booked:</span>
                    <span class="detail-value"><?php echo $bill['room_count']; ?> rooms</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Submitted By:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bill['submitted_by_name']); ?></span>
                </div>
            </div>

            <!-- Bill Breakdown -->
            <div class="detail-section">
                <h3 class="section-title">💰 Bill Breakdown</h3>
                <div class="detail-row amount-row">
                    <span class="detail-label">Base Amount:</span>
                    <span class="detail-value">LKR <?php echo number_format($bill['base_amount'], 2); ?></span>
                </div>
                <?php if ($bill['water_charge'] > 0): ?>
                <div class="detail-row">
                    <span class="detail-label">Water Charges:</span>
                    <span class="detail-value">LKR <?php echo number_format($bill['water_charge'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($bill['washing_charge'] > 0): ?>
                <div class="detail-row">
                    <span class="detail-label">Vehicle Washing:</span>
                    <span class="detail-value">LKR <?php echo number_format($bill['washing_charge'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($bill['service_charge'] > 0): ?>
                <div class="detail-row">
                    <span class="detail-label">Service Charge:</span>
                    <span class="detail-value">LKR <?php echo number_format($bill['service_charge'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($bill['misc_charge'] > 0): ?>
                <div class="detail-row">
                    <span class="detail-label">Miscellaneous:</span>
                    <span class="detail-value">LKR <?php echo number_format($bill['misc_charge'], 2); ?></span>
                </div>
                <?php if ($bill['misc_description']): ?>
                <div class="detail-row">
                    <span class="detail-label">Description:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($bill['misc_description']); ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div class="detail-row total-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">LKR <?php echo number_format($bill['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Employee Assignments -->
        <div class="employees-section">
            <h3 class="section-title">👥 Employee Assignments (<?php echo count($employees); ?> employees)</h3>
            <?php if (!empty($employees)): ?>
                <?php foreach ($employees as $employee): ?>
                    <div class="employee-card">
                        <div class="employee-header">
                            <div class="employee-avatar">
                                <?php echo strtoupper(substr($employee['name'], 0, 2)); ?>
                            </div>
                            <div class="employee-info">
                                <h4><?php echo htmlspecialchars($employee['name']); ?></h4>
                                <div class="employee-meta">
                                    NIC: <?php echo htmlspecialchars($employee['nic']); ?>
                                    <?php if ($employee['designation']): ?>
                                        • <?php echo htmlspecialchars($employee['designation']); ?>
                                    <?php endif; ?>
                                    <?php if ($employee['department']): ?>
                                        • <?php echo htmlspecialchars($employee['department']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="stay-info">
                            <strong>Stay Period:</strong> 
                            <?php echo date('M j', strtotime($employee['first_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($employee['last_date'])); ?> 
                            (<?php echo $employee['total_nights']; ?> nights)
                            <?php if ($employee['room_numbers']): ?>
                                <br><strong>Rooms:</strong> <?php echo htmlspecialchars($employee['room_numbers']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-employees">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                    <h4>No Employees Assigned</h4>
                    <p>This bill has no employee assignments</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stay Timeline -->
        <?php if (!empty($stayDates)): ?>
        <div class="timeline">
            <h3 class="section-title">📅 Stay Timeline</h3>
            <?php
            $currentDate = '';
            $dateEmployees = [];
            
            // Group employees by date
            foreach ($stayDates as $stay) {
                $date = $stay['stay_date'];
                if (!isset($dateEmployees[$date])) {
                    $dateEmployees[$date] = [];
                }
                $dateEmployees[$date][] = $stay['employee_name'] . ($stay['room_number'] ? ' (Room ' . $stay['room_number'] . ')' : '');
            }
            ?>
            
            <?php foreach ($dateEmployees as $date => $employees): ?>
                <div class="timeline-item">
                    <div class="timeline-date">
                        <?php echo date('M j, Y', strtotime($date)); ?>
                    </div>
                    <div class="timeline-employees">
                        <?php echo implode(' • ', array_map('htmlspecialchars', $employees)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight current section when scrolling
            const sections = document.querySelectorAll('.detail-section, .timeline');
            
            function highlightSection() {
                const scrollPos = window.scrollY + 100;
                
                sections.forEach(section => {
                    const top = section.offsetTop;
                    const bottom = top + section.offsetHeight;
                    
                    if (scrollPos >= top && scrollPos <= bottom) {
                        section.style.transform = 'scale(1.02)';
                        section.style.transition = 'transform 0.3s ease';
                    } else {
                        section.style.transform = 'scale(1)';
                    }
                });
            }
            
            window.addEventListener('scroll', highlightSection);
            
            // Add click-to-copy functionality for invoice number
            const invoiceTitle = document.querySelector('.invoice-info h1');
            if (invoiceTitle) {
                invoiceTitle.style.cursor = 'pointer';
                invoiceTitle.title = 'Click to copy invoice number';
                
                invoiceTitle.addEventListener('click', function() {
                    navigator.clipboard.writeText(this.textContent).then(function() {
                        const originalText = invoiceTitle.textContent;
                        invoiceTitle.textContent = '✓ Copied!';
                        setTimeout(() => {
                            invoiceTitle.textContent = originalText;
                        }, 1000);
                    });
                });
            }
        });
    </script>
</body>
</html>