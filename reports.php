<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Reports Page
 * Version: 1.0
 * Description: Generate service and sales reports with date-range selection and PDF export
 * ============================================
 */

// Include configuration and authentication
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

// Check if user is logged in
requireLogin();
checkSessionTimeout();

// Initialize variables
$start_date = '';
$end_date = '';
$report_type = 'all';
$customer_id = 0;
$report_data = [];
$served_customers = [];
$due_customers = [];
$overdue_customers = [];
$next_due_customers = [];
$sales_summary = [];
$staff_performance = [];
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$customer_profile = null;
$customer_history = [];
$customer_totals = [
    'total_deliveries' => 0,
    'total_gallons' => 0,
    'paid_deliveries' => 0,
    'offer_deliveries' => 0,
    'paid_gallons' => 0,
    'offer_gallons' => 0,
    'total_paid' => 0,
];

if ($customer_id > 0) {
    $profile_stmt = $mysqli->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $profile_stmt->bind_param('i', $customer_id);
    $profile_stmt->execute();
    $customer_profile = $profile_stmt->get_result()->fetch_assoc() ?: null;

    if ($customer_profile) {
        $history_sql = "
            SELECT sr.*, s.full_name AS staff_name
            FROM service_records sr
            INNER JOIN staff s ON sr.staff_id = s.id
            WHERE sr.customer_id = ?
            ORDER BY sr.service_date_time DESC
        ";
        $history_stmt = $mysqli->prepare($history_sql);
        $history_stmt->bind_param('i', $customer_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        while ($row = $history_result->fetch_assoc()) {
            $customer_history[] = $row;
            $gallons = (int) $row['gallons_delivered'];
            $customer_totals['total_deliveries']++;
            $customer_totals['total_gallons'] += $gallons;
            if (isOfferDelivery($row)) {
                $customer_totals['offer_deliveries']++;
                $customer_totals['offer_gallons'] += $gallons;
            } else {
                $customer_totals['paid_deliveries']++;
                $customer_totals['paid_gallons'] += $gallons;
                $customer_totals['total_paid'] += paidAmountForRecord($row);
            }
        }
    }
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $report_type = $_POST['report_type'] ?? 'all';
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

    // Next-due reports are forward-looking and must never include dates before today.
    if ($report_type === 'next_due') {
        $today = date('Y-m-d');
        if (empty($start_date) || $start_date < $today) {
            $start_date = $today;
        }
        if (empty($end_date) || $end_date < $start_date) {
            $end_date = date('Y-m-d', strtotime($start_date . ' +30 days'));
        }
    }
    
    // Validate dates
    if (empty($start_date) || empty($end_date)) {
        $error = 'Please select both start and end dates.';
    } else {
        // ============================================
        // SERVED CUSTOMERS REPORT
        // ============================================
        if ($report_type === 'all' || $report_type === 'served') {
            $served_sql = "
                SELECT 
                    sr.id,
                    sr.service_date_time,
                    sr.gallons_delivered,
                    sr.price_per_gallon,
                    sr.total_amount,
                    sr.delivery_type,
                    c.customer_code,
                    c.full_name as customer_name,
                    c.phone1,
                    s.staff_code,
                    s.full_name as staff_name,
                    u.full_name as recorded_by_name
                FROM service_records sr
                INNER JOIN customers c ON sr.customer_id = c.id
                INNER JOIN staff s ON sr.staff_id = s.id
                INNER JOIN users u ON sr.recorded_by = u.id
                WHERE DATE(sr.service_date_time) BETWEEN ? AND ?
                ORDER BY sr.service_date_time ASC
            ";
            $stmt = $mysqli->prepare($served_sql);
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $served_result = $stmt->get_result();
            if ($served_result) {
                while ($row = $served_result->fetch_assoc()) {
                    $served_customers[] = $row;
                }
            }
        }
        
        // ============================================
        // DUE CUSTOMERS REPORT
        // ============================================
        if ($report_type === 'all' || $report_type === 'due' || $report_type === 'next_due') {
            $due_sql = "
                SELECT 
                    c.id,
                    c.customer_code,
                    c.full_name,
                    c.phone1,
                    c.address,
                    c.service_interval_days,
                    c.service_interval_hours,
                    (SELECT sr.service_date_time FROM service_records sr 
                     WHERE sr.customer_id = c.id 
                     ORDER BY sr.service_date_time DESC LIMIT 1) as last_service_date,
                    (SELECT sr.staff_id FROM service_records sr 
                     WHERE sr.customer_id = c.id 
                     ORDER BY sr.service_date_time DESC LIMIT 1) as last_staff_id,
                    (SELECT s.full_name FROM staff s 
                     WHERE s.id = (SELECT sr.staff_id FROM service_records sr 
                                   WHERE sr.customer_id = c.id 
                                   ORDER BY sr.service_date_time DESC LIMIT 1)) as last_staff_name
                FROM customers c
                WHERE c.status = 'active'
            ";
            $due_result = $mysqli->query($due_sql);
            if ($due_result) {
                while ($row = $due_result->fetch_assoc()) {
                    if ($row['last_service_date']) {
                        $row['next_due_date'] = calculateNextDueDate($row['last_service_date'], getServiceIntervalDays($row));
                        $next_due_timestamp = strtotime($row['next_due_date']);
                        $start_timestamp = strtotime($start_date . ' 00:00:00');
                        $end_timestamp = strtotime($end_date . ' 23:59:59');
                        
                        if ($next_due_timestamp >= $start_timestamp && $next_due_timestamp <= $end_timestamp) {
                            if ($report_type === 'next_due') {
                                $next_due_customers[] = $row;
                            } else {
                                $due_customers[] = $row;
                            }
                        }

                    }
                }
                if (!empty($next_due_customers)) {
                    usort($next_due_customers, function ($first, $second) {
                        return strcmp($first['next_due_date'], $second['next_due_date']);
                    });
                }
            }
        }
        
        // ============================================
        // OVERDUE CUSTOMERS REPORT
        // ============================================
        if ($report_type === 'all' || $report_type === 'overdue') {
            $current_datetime = date('Y-m-d H:i:s');
            $start_timestamp = strtotime($start_date . ' 00:00:00');
            $end_timestamp = strtotime($end_date . ' 23:59:59');
            $overdue_sql = "
                SELECT 
                    c.id,
                    c.customer_code,
                    c.full_name,
                    c.phone1,
                    c.address,
                    c.service_interval_days,
                    c.service_interval_hours,
                    (SELECT sr.service_date_time FROM service_records sr 
                     WHERE sr.customer_id = c.id 
                     ORDER BY sr.service_date_time DESC LIMIT 1) as last_service_date,
                    (SELECT s.full_name FROM staff s 
                     WHERE s.id = (SELECT sr.staff_id FROM service_records sr 
                                   WHERE sr.customer_id = c.id 
                                   ORDER BY sr.service_date_time DESC LIMIT 1)) as last_staff_name
                FROM customers c
                WHERE c.status = 'active'
            ";
            $overdue_result = $mysqli->query($overdue_sql);
            if ($overdue_result) {
                while ($row = $overdue_result->fetch_assoc()) {
                    if ($row['last_service_date']) {
                        $next_due = calculateNextDueDate($row['last_service_date'], getServiceIntervalDays($row));
                        $next_due_timestamp = strtotime($next_due);
                        $current_timestamp = strtotime($current_datetime);
                        
                        if ($current_timestamp > $next_due_timestamp && $next_due_timestamp >= $start_timestamp && $next_due_timestamp <= $end_timestamp) {
                            $overdue_seconds = $current_timestamp - $next_due_timestamp;
                            $overdue_hours = floor($overdue_seconds / 3600);
                            $overdue_minutes = floor(($overdue_seconds % 3600) / 60);
                            
                            if ($overdue_hours >= 24) {
                                $overdue_days = floor($overdue_hours / 24);
                                $overdue_hours_remaining = $overdue_hours % 24;
                                $overdue_duration = $overdue_days . ' day(s) ' . $overdue_hours_remaining . ' hour(s)';
                            } else {
                                $overdue_duration = $overdue_hours . ' hour(s) ' . $overdue_minutes . ' min(s)';
                            }
                            
                            $row['next_due_date'] = $next_due;
                            $row['overdue_duration'] = $overdue_duration;
                            $overdue_customers[] = $row;
                        }
                    }
                }
            }
        }
        
        // ============================================
        // SALES SUMMARY REPORT
        // ============================================
        if ($report_type === 'all' || $report_type === 'served' || $report_type === 'sales_summary') {
            $sales_sql = "
                SELECT 
                    COUNT(DISTINCT sr.customer_id) as total_customers_served,
                    COUNT(sr.id) as total_deliveries,
                    COALESCE(SUM(sr.gallons_delivered), 0) as total_gallons,
                    COALESCE(SUM(CASE WHEN COALESCE(sr.delivery_type, 'normal') = 'offer' THEN 0 ELSE sr.total_amount END), 0) as total_sales,
                    s.staff_code,
                    s.full_name as staff_name
                FROM service_records sr
                INNER JOIN staff s ON sr.staff_id = s.id
                WHERE DATE(sr.service_date_time) BETWEEN ? AND ?
                GROUP BY s.staff_code, s.full_name
                ORDER BY total_sales DESC
            ";
            $stmt = $mysqli->prepare($sales_sql);
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $sales_result = $stmt->get_result();
            if ($sales_result) {
                while ($row = $sales_result->fetch_assoc()) {
                    $sales_summary[] = $row;
                }
            }
            
            // Get overall totals
            $total_sql = "
                SELECT 
                    COUNT(DISTINCT customer_id) as total_customers_served,
                    COUNT(id) as total_deliveries,
                    COALESCE(SUM(gallons_delivered), 0) as total_gallons,
                    COALESCE(SUM(CASE WHEN COALESCE(delivery_type, 'normal') = 'offer' THEN 0 ELSE total_amount END), 0) as total_sales,
                    COALESCE(SUM(CASE WHEN COALESCE(delivery_type, 'normal') = 'offer' THEN 0 ELSE gallons_delivered END), 0) as paid_gallons,
                    COALESCE(SUM(CASE WHEN COALESCE(delivery_type, 'normal') = 'offer' THEN gallons_delivered ELSE 0 END), 0) as offer_gallons,
                    SUM(CASE WHEN COALESCE(delivery_type, 'normal') = 'offer' THEN 1 ELSE 0 END) as offer_deliveries,
                    SUM(CASE WHEN COALESCE(delivery_type, 'normal') = 'offer' THEN 0 ELSE 1 END) as paid_deliveries
                FROM service_records
                WHERE DATE(service_date_time) BETWEEN ? AND ?
            ";
            $stmt2 = $mysqli->prepare($total_sql);
            $stmt2->bind_param('ss', $start_date, $end_date);
            $stmt2->execute();
            $total_result = $stmt2->get_result();
            if ($total_result) {
                $overall_totals = $total_result->fetch_assoc();
            }
        }
        
        // ============================================
        // STAFF PERFORMANCE REPORT
        // ============================================
        if ($report_type === 'all' || $report_type === 'staff_performance') {
            $staff_sql = "
                SELECT 
                    s.staff_code,
                    s.full_name as staff_name,
                    s.phone,
                    s.email,
                    COUNT(sr.id) as total_deliveries,
                    COALESCE(SUM(sr.gallons_delivered), 0) as total_gallons,
                    COALESCE(SUM(CASE WHEN COALESCE(sr.delivery_type, 'normal') = 'offer' THEN 0 ELSE sr.total_amount END), 0) as total_sales,
                    COUNT(DISTINCT sr.customer_id) as unique_customers
                FROM staff s
                LEFT JOIN service_records sr ON s.id = sr.staff_id 
                    AND DATE(sr.service_date_time) BETWEEN ? AND ?
                WHERE s.status = 'active'
                GROUP BY s.id, s.staff_code, s.full_name, s.phone, s.email
                ORDER BY total_sales DESC
            ";
            $stmt = $mysqli->prepare($staff_sql);
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $staff_result = $stmt->get_result();
            if ($staff_result) {
                while ($row = $staff_result->fetch_assoc()) {
                    $staff_performance[] = $row;
                }
            }
        }
    }
}

// Set default dates if not set
if ($report_type === 'next_due') {
    if (empty($start_date)) {
        $start_date = date('Y-m-d');
    }
    if (empty($end_date) || $end_date < $start_date) {
        $end_date = date('Y-m-d', strtotime($start_date . ' +30 days'));
    }
} else {
    if (empty($start_date)) {
        $start_date = date('Y-m-01'); // First day of current month
    }
    if (empty($end_date)) {
        $end_date = date('Y-m-d'); // Today
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: #667eea !important;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            border-radius: 10px;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%);
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .report-section {
            margin-bottom: 30px;
        }
        
        .report-section h5 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .summary-value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .summary-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body class="app-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-droplet-fill"></i> RoyalFamily
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customers.php">Customers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="staff.php">Staff</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="deliveries.php">Deliveries</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">Reports</a>
                    </li>
                </ul>
                <a href="settings.php" class="nav-link settings-link me-2" aria-label="Settings" title="Settings">
                    <i class="bi bi-gear-fill"></i>
                </a>
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Report Generation Form -->
        <div class="card no-print">
            <div class="card-header">
                <i class="bi bi-file-earmark-text-fill me-2"></i>Generate Report
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">End Date *</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Report Type</label>
                                <select class="form-select" name="report_type">
                                    <option value="all" <?php echo $report_type === 'all' ? 'selected' : ''; ?>>All Reports</option>
                                    <option value="served" <?php echo $report_type === 'served' ? 'selected' : ''; ?>>Served Customers</option>
                                    <option value="due" <?php echo $report_type === 'due' ? 'selected' : ''; ?>>Due Customers</option>
                                    <option value="next_due" <?php echo $report_type === 'next_due' ? 'selected' : ''; ?>>Next Due</option>
                                    <option value="overdue" <?php echo $report_type === 'overdue' ? 'selected' : ''; ?>>Overdue Customers</option>
                                    <option value="sales_summary" <?php echo $report_type === 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                                    <option value="staff_performance" <?php echo $report_type === 'staff_performance' ? 'selected' : ''; ?>>Staff Performance</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Customer</label>
                                <select class="form-select" name="customer_id">
                                    <option value="">All Customers</option>
                                    <?php
                                    $customer_list = $mysqli->query("SELECT id, full_name FROM customers ORDER BY full_name ASC");
                                    while ($customerRow = $customer_list->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo (int)$customerRow['id']; ?>" <?php echo $customer_id === (int)$customerRow['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($customerRow['full_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-file-earmark-bar-graph-fill me-2"></i>Generate Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($customer_id > 0 && $customer_profile): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-vcard-fill me-2"></i>Customer Individual Report</span>
                    <div class="no-print">
                        <a href="deliveries.php?customer_id=<?php echo (int) $customer_profile['id']; ?>" class="btn btn-light btn-sm">Add Gallons</a>
                        <a href="generate_pdf.php?customer_id=<?php echo (int) $customer_profile['id']; ?>" class="btn btn-success btn-sm">Download PDF</a>
                    </div>
                </div>
                <div class="card-body">
                    <h4><?php echo htmlspecialchars($customer_profile['full_name']); ?></h4>
                    <p class="mb-2"><strong>Customer:</strong> <?php echo htmlspecialchars($customer_profile['full_name']); ?></p>
                    <p class="mb-2"><strong>Contact:</strong> <?php echo htmlspecialchars($customer_profile['phone1'] ?: '-'); ?> <?php echo htmlspecialchars($customer_profile['phone2'] ? ' / ' . $customer_profile['phone2'] : ''); ?></p>
                    <?php if ($customer_profile['email']): ?><p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($customer_profile['email']); ?></p><?php endif; ?>
                    <?php if ($customer_profile['address']): ?><p class="mb-2"><strong>Address:</strong> <?php echo htmlspecialchars($customer_profile['address']); ?></p><?php endif; ?>
                    <div class="row mt-3">
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['total_deliveries']); ?></div><div class="summary-label">Total Deliveries</div></div></div>
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['total_gallons']); ?></div><div class="summary-label">Total Gallons Delivered</div></div></div>
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['paid_deliveries']); ?></div><div class="summary-label">Paid Deliveries</div></div></div>
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['total_paid'], 2); ?></div><div class="summary-label">Total Amount Paid</div></div></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['offer_deliveries']); ?></div><div class="summary-label">Offer Deliveries</div></div></div>
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['paid_gallons']); ?></div><div class="summary-label">Paid Gallons</div></div></div>
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['offer_gallons']); ?></div><div class="summary-label">Offer Gallons</div></div></div>
                        <div class="col-md-3"><div class="summary-card"><div class="summary-value"><?php echo number_format($customer_totals['total_paid'], 2); ?></div><div class="summary-label">Amount Paid (TZS)</div></div></div>
                    </div>
                    <div class="table-responsive mt-4">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Gallons</th>
                                    <th>Price/Gallon</th>
                                    <th>Type</th>
                                    <th>Amount Paid</th>
                                    <th>Staff</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customer_history)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">No deliveries yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($customer_history as $record): ?>
                                        <?php $type = deliveryTypeOf($record); ?>
                                        <tr>
                                            <td><?php echo date('d M Y H:i', strtotime($record['service_date_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($customer_profile['full_name']); ?></td>
                                            <td><?php echo number_format((int) $record['gallons_delivered']); ?></td>
                                            <td><?php echo number_format((float) $record['price_per_gallon'], 2); ?></td>
                                            <td><?php echo $type === 'offer' ? 'Offer' : 'Normal'; ?></td>
                                            <td><?php echo $type === 'offer' ? 'Offer / Free' : number_format(paidAmountForRecord($record), 2) . ' TZS'; ?></td>
                                            <td><?php echo htmlspecialchars($record['staff_name']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Report Results -->
        <?php if (!empty($served_customers) || !empty($due_customers) || !empty($next_due_customers) || !empty($overdue_customers) || !empty($staff_performance) || !empty($sales_summary)): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-text-fill me-2"></i>Report Results</span>
                    <div class="no-print">
                        <button onclick="window.print()" class="btn btn-light btn-sm">
                            <i class="bi bi-printer-fill me-1"></i> Print
                        </button>
                        <a href="generate_pdf.php?start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&report_type=<?php echo htmlspecialchars($report_type); ?>" class="btn btn-success btn-sm ms-2">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Download PDF
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Report Header -->
                    <div class="text-center mb-4">
                        <h3><?php echo APP_NAME; ?></h3>
                        <h5>Service and Sales Report</h5>
                        <p class="text-muted">
                            <strong>Period:</strong> <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?><br>
                            <strong>Generated:</strong> <?php echo date('d M Y H:i'); ?>
                        </p>
                    </div>

                    <!-- Sales Summary -->
                    <?php if ($report_type === 'all' || $report_type === 'served'): ?>
                        <?php if (isset($overall_totals)): ?>
                            <div class="report-section">
                                <h5><i class="bi bi-graph-up-fill me-2"></i>Sales Summary</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((int)($overall_totals['total_customers_served'] ?? 0)); ?></div>
                                            <div class="summary-label">Customers Served</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((int)($overall_totals['total_deliveries'] ?? 0)); ?></div>
                                            <div class="summary-label">Total Deliveries</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((int)($overall_totals['total_gallons'] ?? 0)); ?></div>
                                            <div class="summary-label">Total Gallons</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((float)($overall_totals['total_sales'] ?? 0), 2); ?></div>
                                            <div class="summary-label">Total Sales (TZS)</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Staff Breakdown -->
                                <?php if (!empty($sales_summary)): ?>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Staff Code</th>
                                                    <th>Staff Name</th>
                                                    <th>Deliveries</th>
                                                    <th>Customers Served</th>
                                                    <th>Gallons</th>
                                                    <th>Sales (TZS)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($sales_summary as $staff): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($staff['staff_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                                        <td><?php echo number_format((int)($staff['total_deliveries'] ?? 0)); ?></td>
                                                        <td><?php echo number_format((int)($staff['total_customers_served'] ?? 0)); ?></td>
                                                        <td><?php echo number_format((int)($staff['total_gallons'] ?? 0)); ?></td>
                                                        <td><?php echo number_format((float)($staff['total_sales'] ?? 0), 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Served Customers -->
                    <?php if ($report_type === 'all' || $report_type === 'served'): ?>
                        <?php if (!empty($served_customers)): ?>
                            <div class="report-section">
                                <h5><i class="bi bi-check-circle-fill me-2"></i>Served Customers (<?php echo count($served_customers); ?>)</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Customer</th>
                                                <th>Staff</th>
                                                <th>Gallons</th>
                                                <th>Price/Gallon</th>
                                                <th>Type</th>
                                                <th>Amount Paid</th>
                                                <th>Recorded By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($served_customers as $record): ?>
                                                <?php $type = deliveryTypeOf($record); ?>
                                                <tr>
                                                    <td><?php echo date('d M Y H:i', strtotime($record['service_date_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars(customerDisplayName($record)); ?></td>
                                                    <td><?php echo htmlspecialchars($record['staff_name']); ?></td>
                                                    <td><?php echo number_format((int)($record['gallons_delivered'] ?? 0)); ?></td>
                                                    <td><?php echo number_format((float)($record['price_per_gallon'] ?? 0), 2); ?></td>
                                                    <td><span class="badge <?php echo $type === 'offer' ? 'bg-warning text-dark' : 'bg-success'; ?>"><?php echo $type === 'offer' ? 'Offer' : 'Normal'; ?></span></td>
                                                    <td><strong><?php echo $type === 'offer' ? 'Offer / Free' : number_format(paidAmountForRecord($record), 2); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($record['recorded_by_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Due Customers -->
                    <?php if ($report_type === 'all' || $report_type === 'due' || $report_type === 'next_due'): ?>
                        <?php $due_list = $report_type === 'next_due' ? $next_due_customers : $due_customers; ?>
                        <?php if (!empty($due_list)): ?>
                            <div class="report-section">
                                <h5><i class="bi bi-calendar-event-fill me-2"></i><?php echo $report_type === 'next_due' ? 'Next Due' : 'Due Customers'; ?> (<?php echo count($due_list); ?>)</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Phone</th>
                                                <th>Address</th>
                                                <th>Next Due Date</th>
                                                <th>Last Staff</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($due_list as $customer): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['phone1']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['address'] ?: '-'); ?></td>
                                                    <td><strong><?php echo date('d M Y H:i', strtotime($customer['next_due_date'])); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($customer['last_staff_name'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Overdue Customers -->
                    <?php if ($report_type === 'all' || $report_type === 'overdue'): ?>
                        <?php if (!empty($overdue_customers)): ?>
                            <div class="report-section">
                                <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Overdue Customers (<?php echo count($overdue_customers); ?>)</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Phone</th>
                                                <th>Address</th>
                                                <th>Was Due</th>
                                                <th>Overdue Duration</th>
                                                <th>Last Staff</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($overdue_customers as $customer): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['phone1']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['address'] ?: '-'); ?></td>
                                                    <td><?php echo date('d M Y H:i', strtotime($customer['next_due_date'])); ?></td>
                                                    <td><strong class="text-danger"><?php echo htmlspecialchars($customer['overdue_duration']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($customer['last_staff_name'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Staff Performance -->
                    <?php if ($report_type === 'all' || $report_type === 'staff_performance'): ?>
                        <?php if (!empty($staff_performance)): ?>
                            <div class="report-section">
                                <h5><i class="bi bi-person-badge-fill me-2"></i>Staff Performance (<?php echo count($staff_performance); ?>)</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Staff Code</th>
                                                <th>Staff Name</th>
                                                <th>Phone</th>
                                                <th>Email</th>
                                                <th>Total Deliveries</th>
                                                <th>Total Gallons</th>
                                                <th>Total Sales (TZS)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($staff_performance as $staff): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($staff['staff_code']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($staff['phone']); ?></td>
                                                    <td><?php echo htmlspecialchars($staff['email'] ?: '-'); ?></td>
                                                    <td><?php echo number_format((int)($staff['total_deliveries'] ?? 0)); ?></td>
                                                    <td><?php echo number_format((int)($staff['total_gallons'] ?? 0)); ?></td>
                                                    <td><strong><?php echo number_format((float)($staff['total_sales'] ?? 0), 2); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Sales Summary (Separate Report) -->
                    <?php if ($report_type === 'sales_summary'): ?>
                        <?php if (isset($overall_totals)): ?>
                            <div class="report-section">
                                <h5><i class="bi bi-graph-up-fill me-2"></i>Sales Summary</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((int)($overall_totals['total_customers_served'] ?? 0)); ?></div>
                                            <div class="summary-label">Customers Served</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((int)($overall_totals['total_deliveries'] ?? 0)); ?></div>
                                            <div class="summary-label">Total Deliveries</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((int)($overall_totals['total_gallons'] ?? 0)); ?></div>
                                            <div class="summary-label">Total Gallons</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-card">
                                            <div class="summary-value"><?php echo number_format((float)($overall_totals['total_sales'] ?? 0), 2); ?></div>
                                            <div class="summary-label">Total Sales (TZS)</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Staff Breakdown -->
                                <?php if (!empty($sales_summary)): ?>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Staff Code</th>
                                                    <th>Staff Name</th>
                                                    <th>Deliveries</th>
                                                    <th>Customers Served</th>
                                                    <th>Gallons</th>
                                                    <th>Sales (TZS)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($sales_summary as $sale): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($sale['staff_code']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($sale['staff_name']); ?></td>
                                                        <td><?php echo number_format((int)($sale['total_deliveries'] ?? 0)); ?></td>
                                                        <td><?php echo number_format((int)($sale['total_customers_served'] ?? 0)); ?></td>
                                                        <td><?php echo number_format((int)($sale['total_gallons'] ?? 0)); ?></td>
                                                        <td><strong><?php echo number_format((float)($sale['total_sales'] ?? 0), 2); ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Generate PDF using browser print
        function generatePDF() {
            window.print();
        }
    </script>
</body>
</html>
