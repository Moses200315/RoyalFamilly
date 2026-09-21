<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Admin Dashboard
 * Version: 1.0
 * Description: Main dashboard with summary cards, monitoring alerts, and customer status tracking
 * ============================================
 */

// Include configuration and authentication
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

// Check if user is logged in
requireLogin();
checkSessionTimeout();

// Get current date and time
$current_datetime = date('Y-m-d H:i:s');
$current_date = date('Y-m-d');

function formatElapsedDuration($seconds) {
    $seconds = max(0, (int) $seconds);
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($days > 0) {
        return $days . ' day(s) ' . $hours . ' hour(s)';
    }

    return $hours . ' hour(s) ' . $minutes . ' min(s)';
}

// ============================================
// DASHBOARD SUMMARY CALCULATIONS
// ============================================

// Total Active Customers
$sql_active_customers = "SELECT COUNT(*) as count FROM customers WHERE status = 'active'";
$result_active = $mysqli->query($sql_active_customers);
$total_active_customers = $result_active ? $result_active->fetch_assoc()['count'] : 0;

// Served Today
$sql_served_today = "SELECT COUNT(DISTINCT customer_id) as count FROM service_records WHERE DATE(service_date_time) = '$current_date'";
$result_served = $mysqli->query($sql_served_today);
$served_today = $result_served ? $result_served->fetch_assoc()['count'] : 0;

// Total Gallons Today
$sql_gallons_today = "SELECT SUM(gallons_delivered) as total FROM service_records WHERE DATE(service_date_time) = '$current_date'";
$result_gallons = $mysqli->query($sql_gallons_today);
$gallons_today = $result_gallons ? ($result_gallons->fetch_assoc()['total'] ?: 0) : 0;

// Total Sales Today
$sql_sales_today = "SELECT SUM(total_amount) as total FROM service_records WHERE DATE(service_date_time) = '$current_date'";
$result_sales = $mysqli->query($sql_sales_today);
$sales_today = $result_sales ? ($result_sales->fetch_assoc()['total'] ?: 0) : 0;

// ============================================
// CUSTOMER MONITORING LOGIC
// ============================================

// Get all active customers with their latest service and calculated due dates
$monitoring_sql = "
    SELECT 
        c.id,
        c.customer_code,
        c.full_name,
        c.phone1,
        c.service_interval_hours,
        c.service_interval_days,
        c.address,
        (SELECT sr.service_date_time FROM service_records sr 
         WHERE sr.customer_id = c.id 
         ORDER BY sr.service_date_time DESC LIMIT 1) as last_service_date,
        (SELECT sr.service_date_time FROM service_records sr 
         WHERE sr.customer_id = c.id 
         ORDER BY sr.service_date_time DESC LIMIT 1 OFFSET 1) as previous_service_date,
        (SELECT sr.next_due_date_override FROM service_records sr 
         WHERE sr.customer_id = c.id 
         ORDER BY sr.service_date_time DESC LIMIT 1) as next_due_date_override,
        (SELECT sr.staff_id FROM service_records sr 
         WHERE sr.customer_id = c.id 
         ORDER BY sr.service_date_time DESC LIMIT 1) as last_staff_id,
        (SELECT s.full_name FROM staff s 
         WHERE s.id = (SELECT sr.staff_id FROM service_records sr 
                       WHERE sr.customer_id = c.id 
                       ORDER BY sr.service_date_time DESC LIMIT 1)) as last_staff_name
    FROM customers c
    WHERE c.status = 'active'
    ORDER BY c.full_name ASC
";

$monitoring_result = $mysqli->query($monitoring_sql);

$reminders = [];
$due_today = [];
$overdue = [];
$upcoming = [];
$delivered_today = [];
$late_deliveries = [];

if ($monitoring_result) {
    while ($customer = $monitoring_result->fetch_assoc()) {
        $last_service = $customer['last_service_date'];
        
        // Calculate next due date from the customer's interval in days.
        if ($last_service) {
            $interval_days = getServiceIntervalDays($customer);
            $next_due = calculateNextDueDate($last_service, $interval_days, $customer['next_due_date_override']);
            if (!$next_due) {
                // No due date set
                $customer['no_due_date'] = true;
                $upcoming[] = $customer;
                continue;
            }
            
            // Remind one calendar day before the scheduled delivery.
            $reminder_time = date('Y-m-d H:i:s', strtotime($next_due . ' -1 day'));
            
            // Determine customer status
            $current_timestamp = strtotime($current_datetime);
            $next_due_timestamp = strtotime($next_due);
            $reminder_timestamp = strtotime($reminder_time);

            // A delivery recorded today is complete and should be visible with its exact time.
            if (date('Y-m-d', strtotime($last_service)) === $current_date) {
                $customer['delivered_at'] = $last_service;

                // Compare this delivery with the previous expected service time when available.
                if (!empty($customer['previous_service_date']) && !empty($customer['service_interval_hours'])) {
                    $expected_delivery_timestamp = strtotime($customer['previous_service_date'] . ' +' . (int) $customer['service_interval_hours'] . ' hours');
                    if (strtotime($last_service) > $expected_delivery_timestamp) {
                        $customer['late_duration'] = formatElapsedDuration(strtotime($last_service) - $expected_delivery_timestamp);
                        $late_deliveries[] = $customer;
                    }
                }

                $delivered_today[] = $customer;
                continue;
            }
            
            // Due today takes priority over the reminder window.
            if (date('Y-m-d', $next_due_timestamp) === $current_date) {
                $customer['next_due_date'] = $next_due;
                $due_today[] = $customer;
            }
            // Check if customer is overdue
            elseif ($current_timestamp > $next_due_timestamp) {
                $overdue_duration = formatElapsedDuration($current_timestamp - $next_due_timestamp);
                $customer['next_due_date'] = $next_due;
                $customer['overdue_duration'] = $overdue_duration;
                $customer['delivery_status'] = 'Overdue - not delivered';
                $overdue[] = $customer;
            }
            // Check if customer is in the one-day reminder window.
            elseif ($current_timestamp >= $reminder_timestamp && $current_timestamp <= $next_due_timestamp) {
                $customer['next_due_date'] = $next_due;
                $reminders[] = $customer;
            }
            // Check if customer is due today
            // Otherwise, upcoming
            else {
                $customer['next_due_date'] = $next_due;
                $upcoming[] = $customer;
            }
        } else {
            // No service recorded yet - treat as upcoming
            $customer['next_due_date'] = null;
            $customer['no_service'] = true;
            $upcoming[] = $customer;
        }
    }
}

// Count due today and overdue
$due_today_count = count($due_today);
$overdue_count = count($overdue);
$next_midnight = new DateTime('tomorrow', new DateTimeZone(date_default_timezone_get()));
$midnight_refresh_delay = max(1000, ($next_midnight->getTimestamp() - time()) * 1000);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    
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
        
        .summary-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            overflow: hidden;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .summary-card .card-body {
            padding: 25px;
        }
        
        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .summary-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .summary-label {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }
        
        .icon-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .icon-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        .icon-orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .icon-red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); color: white; }
        .icon-cyan { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .icon-purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color: white; }
        
        .monitoring-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .monitoring-card .card-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .monitoring-card .card-body {
            padding: 20px;
        }
        
        .header-reminder { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .header-due { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .header-overdue { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); color: white; }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #2c7be5;
            background: rgba(44, 123, 229, 0.08);
            border-radius: 999px;
            padding: 7px 12px;
        }

        .live-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.15);
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        .dashboard-banner {
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.12));
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .dashboard-banner strong {
            display: block;
            color: #1f2937;
            font-size: 16px;
        }

        .dashboard-banner span {
            color: #6c757d;
            font-size: 14px;
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
        }

        .filter-chip {
            border: 1px solid #dfe3e8;
            background: white;
            color: #495057;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .filter-chip.active,
        .filter-chip:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 8px 18px rgba(102, 126, 234, 0.2);
        }
        
        .customer-item {
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        
        .customer-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .customer-item.overdue {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .customer-item.reminder {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
        
        .customer-item.due {
            border-left-color: #0dcaf0;
            background: #f0fcff;
        }

        .customer-item.delivered {
            border-left-color: #198754;
            background: #f2fff7;
        }

        .status-label {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #842029; }
        .status-delivered { background: #d1e7dd; color: #0f5132; }
        .status-late { background: #ffe5d0; color: #984c0c; }
        
        .customer-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .customer-details {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .due-time {
            font-weight: 600;
            color: #dc3545;
        }
        
        .refresh-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            color: white;
            font-weight: 600;
        }
        
        .refresh-btn:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body class="app-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
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
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                </ul>
                <a href="settings.php" class="nav-link settings-link me-2" aria-label="Settings" title="Settings">
                    <i class="bi bi-gear-fill"></i>
                </a>
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Dashboard</h2>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
                <div class="live-indicator mt-2">
                    <span class="live-dot"></span>
                    Live overview
                </div>
            </div>
            <div class="header-actions">
                <span class="text-muted small" id="lastUpdated">Updated just now</span>
                <a href="dashboard.php" class="refresh-btn">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </a>
            </div>
        </div>

        <div class="dashboard-banner">
            <div>
                <strong><?php echo $overdue_count > 0 ? 'Action required' : 'Everything looks on track'; ?></strong>
                <span>
                    <?php echo $overdue_count > 0
                        ? $overdue_count . ' customer(s) need attention before the next route.'
                        : 'No overdue deliveries today. You are operating within schedule.'; ?>
                </span>
            </div>
            <div class="text-end">
                <span class="badge bg-primary rounded-pill px-3 py-2">
                    <?php echo $reminders ? $reminders[0]['customer_code'] . ' next in queue' : 'No reminder queue'; ?>
                </span>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-icon icon-blue">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format($total_active_customers); ?></div>
                        <div class="summary-label">Active Customers</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-icon icon-green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format($served_today); ?></div>
                        <div class="summary-label">Served Today</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-icon icon-cyan">
                            <i class="bi bi-calendar-check-fill"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format($due_today_count); ?></div>
                        <div class="summary-label">Due Today</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-icon icon-red">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format($overdue_count); ?></div>
                        <div class="summary-label">Overdue</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-icon icon-purple">
                            <i class="bi bi-droplet-fill"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format((int) $gallons_today); ?></div>
                        <div class="summary-label">Gallons Today</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card summary-card">
                    <div class="card-body">
                        <div class="summary-icon icon-orange">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div class="summary-value"><?php echo number_format($sales_today); ?></div>
                        <div class="summary-label">Sales (TZS)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-bar" aria-label="Delivery filter controls">
            <button type="button" class="filter-chip active" data-filter="all">All</button>
            <button type="button" class="filter-chip" data-filter="reminder">Reminders</button>
            <button type="button" class="filter-chip" data-filter="due">Due Today</button>
            <button type="button" class="filter-chip" data-filter="overdue">Overdue</button>
            <button type="button" class="filter-chip" data-filter="delivered">Delivered Today</button>
        </div>

        <!-- Monitoring Section -->
        <div class="row">
            <!-- Reminders (one-day window) -->
            <div class="col-lg-4 mb-4">
                <div class="card monitoring-card">
                    <div class="card-header header-reminder d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-alarm-fill me-2"></i>Reminders (Within One Day)</span>
                        <span class="badge bg-light text-dark"><?php echo count($reminders); ?></span>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($reminders)): ?>
                            <div class="empty-state">
                                <i class="bi bi-check-circle"></i>
                                <p>No customers in reminder window</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reminders as $customer): ?>
                                <div class="customer-item reminder" data-status="reminder">
                                    <span class="status-label status-pending">Pending delivery</span>
                                    <div class="customer-name">
                                        <?php echo htmlspecialchars($customer['full_name']); ?>
                                    </div>
                                    <div class="customer-details">
                                        <i class="bi bi-telephone-fill me-1"></i> <?php echo htmlspecialchars($customer['phone1']); ?>
                                    </div>
                                    <div class="customer-details">
                                        <i class="bi bi-clock-fill me-1"></i> Due: <span class="due-time"><?php echo date('H:i', strtotime($customer['next_due_date'])); ?></span>
                                    </div>
                                    <?php if ($customer['last_staff_name']): ?>
                                        <div class="customer-details">
                                            <i class="bi bi-person-fill me-1"></i> Last Staff: <?php echo htmlspecialchars($customer['last_staff_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Due Today -->
            <div class="col-lg-4 mb-4">
                <div class="card monitoring-card">
                    <div class="card-header header-due d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-calendar-event-fill me-2"></i>Due Today</span>
                        <span class="badge bg-light text-dark"><?php echo count($due_today); ?></span>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($due_today)): ?>
                            <div class="empty-state">
                                <i class="bi bi-calendar-check"></i>
                                <p>No customers due today</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($due_today as $customer): ?>
                                <div class="customer-item due" data-status="due">
                                    <span class="status-label status-pending">Pending delivery</span>
                                    <div class="customer-name">
                                        <?php echo htmlspecialchars($customer['full_name']); ?>
                                    </div>
                                    <div class="customer-details">
                                        <i class="bi bi-telephone-fill me-1"></i> <?php echo htmlspecialchars($customer['phone1']); ?>
                                    </div>
                                    <div class="customer-details">
                                        <i class="bi bi-clock-fill me-1"></i> Due Time: <span class="due-time"><?php echo date('H:i', strtotime($customer['next_due_date'])); ?></span>
                                    </div>
                                    <?php if ($customer['last_staff_name']): ?>
                                        <div class="customer-details">
                                            <i class="bi bi-person-fill me-1"></i> Last Staff: <?php echo htmlspecialchars($customer['last_staff_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Overdue -->
            <div class="col-lg-4 mb-4">
                <div class="card monitoring-card">
                    <div class="card-header header-overdue d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-exclamation-circle-fill me-2"></i>Overdue</span>
                        <span class="badge bg-light text-dark"><?php echo count($overdue); ?></span>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($overdue)): ?>
                            <div class="empty-state">
                                <i class="bi bi-check-circle-fill"></i>
                                <p>No overdue customers</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($overdue as $customer): ?>
                                <div class="customer-item overdue" data-status="overdue">
                                    <span class="status-label status-overdue">Overdue - not delivered</span>
                                    <div class="customer-name">
                                        <?php echo htmlspecialchars($customer['full_name']); ?>
                                    </div>
                                    <div class="customer-details">
                                        <i class="bi bi-telephone-fill me-1"></i> <?php echo htmlspecialchars($customer['phone1']); ?>
                                    </div>
                                    <div class="customer-details">
                                        <i class="bi bi-clock-history-fill me-1"></i> Overdue time: <span class="due-time"><?php echo htmlspecialchars($customer['overdue_duration']); ?></span>
                                    </div>
                                    <div class="customer-details">
                                        <i class="bi bi-calendar-x-fill me-1"></i> Was Due: <?php echo date('d M H:i', strtotime($customer['next_due_date'])); ?>
                                    </div>
                                    <?php if ($customer['last_staff_name']): ?>
                                        <div class="customer-details">
                                            <i class="bi bi-person-fill me-1"></i> Last Staff: <?php echo htmlspecialchars($customer['last_staff_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivered Today -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card monitoring-card">
                    <div class="card-header header-due d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="bi bi-check2-circle me-2"></i>Delivered Today</span>
                        <div class="d-flex align-items-center gap-2">
                            <input type="text" class="form-control form-control-sm" data-live-search="delivered" placeholder="Search delivered customers..." autocomplete="off" style="min-width: 220px;">
                            <span class="badge bg-light text-dark"><?php echo count($delivered_today); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($delivered_today)): ?>
                            <div class="empty-state">
                                <i class="bi bi-truck"></i>
                                <p>No deliveries completed today</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($delivered_today as $customer): ?>
                                    <div class="col-lg-4 col-md-6 mb-3" data-search-row="delivered" data-search="<?php echo htmlspecialchars(strtolower($customer['full_name'] . ' ' . $customer['customer_code'] . ' ' . $customer['phone1'] . ' ' . ($customer['last_staff_name'] ?? ''))); ?>">
                                        <div class="customer-item delivered h-100" data-status="delivered">
                                            <span class="status-label status-delivered">Delivered</span>
                                            <?php if (!empty($customer['late_duration'])): ?>
                                                <span class="status-label status-late">Late by <?php echo htmlspecialchars($customer['late_duration']); ?></span>
                                            <?php endif; ?>
                                            <div class="customer-name">
                                                <?php echo htmlspecialchars($customer['full_name']); ?>
                                            </div>
                                            <div class="customer-details">
                                                <i class="bi bi-clock-fill me-1"></i> Delivered at:
                                                <span class="text-success fw-semibold"><?php echo date('d M Y H:i', strtotime($customer['delivered_at'])); ?></span>
                                            </div>
                                            <?php if ($customer['last_staff_name']): ?>
                                                <div class="customer-details">
                                                    <i class="bi bi-person-fill me-1"></i> Staff: <?php echo htmlspecialchars($customer['last_staff_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card monitoring-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <i class="bi bi-lightning-fill me-2"></i>Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="deliveries.php" class="btn btn-primary w-100">
                                    <i class="bi bi-truck-fill me-2"></i>Record Delivery
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="customers.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-person-plus-fill me-2"></i>Add Customer
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="staff.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-person-badge-fill me-2"></i>Add Staff
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="reports.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-file-earmark-text-fill me-2"></i>Generate Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const filterButtons = document.querySelectorAll('.filter-chip');
        const customerItems = document.querySelectorAll('.customer-item');

        filterButtons.forEach(button => {
            button.addEventListener('click', function () {
                const selectedFilter = this.dataset.filter;

                filterButtons.forEach(btn => btn.classList.toggle('active', btn === this));

                customerItems.forEach(item => {
                    const matches = selectedFilter === 'all' || item.dataset.status === selectedFilter;
                    item.style.display = matches ? 'block' : 'none';
                });
            });
        });

        function updateLastUpdated() {
            const now = new Date();
            const label = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            document.getElementById('lastUpdated').textContent = 'Updated at ' + label;
        }

        updateLastUpdated();

        // Refresh at the next midnight in the server-configured timezone.
        setTimeout(function() {
            window.location.reload();
        }, <?php echo (int) $midnight_refresh_delay; ?>);

        // Keep the dashboard responsive to same-day delivery changes as before.
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>
