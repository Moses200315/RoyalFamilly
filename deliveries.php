<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Delivery/Service Recording Page
 * Version: 1.0
 * Description: Record water deliveries with automatic sales calculation and due date updates
 * ============================================
 */

// Include configuration and authentication
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

// Check if user is logged in
requireLogin();
checkSessionTimeout();

// Initialize variables
$message = '';
$messageType = '';
$searchTerm = '';
$customerFilter = '';
$staffFilter = '';
$dateFilter = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'danger';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        // Add new delivery
        if ($action === 'add') {
        $customer_id = intval($_POST['customer_id']);
        $staff_id = intval($_POST['staff_id']);
        $service_date_time = trim($_POST['service_date_time'] ?? '');
        $gallons_delivered = filter_input(INPUT_POST, 'gallons_delivered', FILTER_VALIDATE_INT);
        $price_per_gallon = floatval($_POST['price_per_gallon'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        if ($customer_id <= 0 || $staff_id <= 0 || empty($service_date_time) || $gallons_delivered === false || $gallons_delivered <= 0 || $price_per_gallon <= 0) {
            $message = 'Please fill in all required fields with valid values.';
            $messageType = 'danger';
        } else {
            // Calculate total amount automatically
            $total_amount = $gallons_delivered * $price_per_gallon;
            
            // Insert new delivery record
            $sql = "INSERT INTO service_records (customer_id, staff_id, service_date_time, gallons_delivered, price_per_gallon, total_amount, notes, recorded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('iisiddsi', $customer_id, $staff_id, $service_date_time, $gallons_delivered, $price_per_gallon, $total_amount, $notes, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $message = 'Delivery recorded successfully! Total amount: ' . number_format($total_amount, 2) . ' ' . CURRENCY;
                $messageType = 'success';
            } else {
                $message = 'Error recording delivery: ' . $mysqli->error;
                $messageType = 'danger';
            }
        }
    }
    
    // Delete delivery
    if ($action === 'delete') {
        $delivery_id = intval($_POST['delivery_id']);
        
        $sql = "DELETE FROM service_records WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $delivery_id);
        
        if ($stmt->execute()) {
            $message = 'Delivery record deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error deleting delivery: ' . $mysqli->error;
            $messageType = 'danger';
        }
    }
    regenerateCsrfToken();
    }
}

// Handle filters
$searchTerm = '';
$customerFilter = 0;
$staffFilter = 0;
$dateFilter = '';
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search'] ?? '');
}
if (isset($_GET['customer'])) {
    $customerFilter = intval($_GET['customer']);
}
if (isset($_GET['staff'])) {
    $staffFilter = intval($_GET['staff']);
}
if (isset($_GET['date'])) {
    $dateFilter = trim($_GET['date'] ?? '');
}

// Build query to fetch deliveries
$sql = "SELECT sr.*, 
        c.customer_code, c.full_name as customer_name, c.service_interval_hours,
        s.staff_code, s.full_name as staff_name,
        u.full_name as recorded_by_name
        FROM service_records sr
        INNER JOIN customers c ON sr.customer_id = c.id
        INNER JOIN staff s ON sr.staff_id = s.id
        INNER JOIN users u ON sr.recorded_by = u.id
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($searchTerm)) {
    $sql .= " AND (c.full_name LIKE ? 
              OR c.customer_code LIKE ? 
              OR s.full_name LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= 'sss';
}

if ($customerFilter > 0) {
    $sql .= " AND sr.customer_id = ?";
    $params[] = $customerFilter;
    $types .= 'i';
}

if ($staffFilter > 0) {
    $sql .= " AND sr.staff_id = ?";
    $params[] = $staffFilter;
    $types .= 'i';
}

if (!empty($dateFilter)) {
    $sql .= " AND DATE(sr.service_date_time) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$sql .= " ORDER BY sr.service_date_time DESC LIMIT 100";

if (!empty($params)) {
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $mysqli->query($sql);
}

// Fetch all deliveries
$deliveries = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }
}

// Fetch all customers for delivery recording and filtering
$customers_sql = "SELECT id, customer_code, full_name, status FROM customers ORDER BY full_name ASC";
$customers_result = $mysqli->query($customers_sql);
$customers_dropdown = [];
if ($customers_result) {
    while ($row = $customers_result->fetch_assoc()) {
        $customers_dropdown[] = $row;
    }
}

// Fetch active staff for dropdown
$staff_sql = "SELECT id, staff_code, full_name FROM staff WHERE status = 'active' ORDER BY full_name ASC";
$staff_result = $mysqli->query($staff_sql);
$staff_dropdown = [];
if ($staff_result) {
    while ($row = $staff_result->fetch_assoc()) {
        $staff_dropdown[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Recording - <?php echo APP_NAME; ?></title>
    
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
        
        .action-btn {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            margin: 2px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .total-amount {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customers.php">Customers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="staff.php">Staff</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="deliveries.php">Deliveries</a>
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
        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>-fill me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Delivery Recording Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-truck-fill me-2"></i>Delivery Recording</span>
                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addDeliveryModal">
                    <i class="bi bi-plus-circle me-1"></i> Record Delivery
                </button>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="customer">
                                <option value="">All Customers</option>
                                <?php foreach ($customers_dropdown as $cust): ?>
                                    <option value="<?php echo $cust['id']; ?>" <?php echo $customerFilter == $cust['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cust['customer_code'] . ' - ' . $cust['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="staff">
                                <option value="">All Staff</option>
                                <?php foreach ($staff_dropdown as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" <?php echo $staffFilter == $staff['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['staff_code'] . ' - ' . $staff['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Deliveries Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Customer</th>
                                <th>Staff</th>
                                <th>Gallons</th>
                                <th>Price/Gallon</th>
                                <th>Total Amount</th>
                                <th>Recorded By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deliveries)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="mt-2 text-muted">No delivery records found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deliveries as $delivery): ?>
                                    <tr>
                                        <td><?php echo date('d M Y H:i', strtotime($delivery['service_date_time'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($delivery['customer_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($delivery['customer_name']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($delivery['staff_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($delivery['staff_name']); ?></small>
                                        </td>
                                        <td><?php echo number_format((int) $delivery['gallons_delivered']); ?></td>
                                        <td><?php echo number_format($delivery['price_per_gallon'], 2); ?></td>
                                        <td><strong><?php echo number_format($delivery['total_amount'], 2); ?> <?php echo CURRENCY; ?></strong></td>
                                        <td><?php echo htmlspecialchars($delivery['recorded_by_name']); ?></td>
                                        <td>
                                            <button class="btn btn-danger action-btn" data-bs-toggle="modal" data-bs-target="#deleteDeliveryModal<?php echo $delivery['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Delivery Modal -->
    <div class="modal fade" id="addDeliveryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck-fill me-2"></i>Record New Delivery</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Customer *</label>
                                    <select class="form-select" name="customer_id" required id="customerSelect">
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers_dropdown as $cust): ?>
                                            <option value="<?php echo $cust['id']; ?>">
                                                <?php echo htmlspecialchars($cust['customer_code'] . ' - ' . $cust['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Staff Member *</label>
                                    <select class="form-select" name="staff_id" required>
                                        <option value="">Select Staff</option>
                                        <?php foreach ($staff_dropdown as $staff): ?>
                                            <option value="<?php echo $staff['id']; ?>">
                                                <?php echo htmlspecialchars($staff['staff_code'] . ' - ' . $staff['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Service Date/Time *</label>
                                    <input type="datetime-local" class="form-control" name="service_date_time" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <input type="text" class="form-control" name="notes" placeholder="Optional notes">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Gallons Delivered *</label>
                                    <input type="number" class="form-control" name="gallons_delivered" step="1" min="1" required id="gallonsInput" oninput="calculateTotal()" onkeydown="return event.key !== '.' && event.key !== ',' && event.key !== 'e' && event.key !== 'E'">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Price Per Gallon (<?php echo CURRENCY; ?>) *</label>
                                    <input type="number" class="form-control" name="price_per_gallon" step="0.01" min="0.01" required id="priceInput" value="5000" oninput="calculateTotal()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Total Amount</label>
                                    <div class="total-amount" id="totalAmount">0.00 <?php echo CURRENCY; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Next Due Date:</strong> Will be calculated automatically based on customer's service interval.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Delivery</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Delivery Modals -->
    <?php foreach ($deliveries as $delivery): ?>
        <div class="modal fade" id="deleteDeliveryModal<?php echo $delivery['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                            <p>Are you sure you want to delete this delivery record?</p>
                            <p class="text-muted small">
                                <strong>Customer:</strong> <?php echo htmlspecialchars($delivery['customer_name']); ?><br>
                                <strong>Date:</strong> <?php echo date('d M Y H:i', strtotime($delivery['service_date_time'])); ?><br>
                                <strong>Amount:</strong> <?php echo number_format($delivery['total_amount'], 2); ?> <?php echo CURRENCY; ?>
                            </p>
                            <p class="text-danger small">This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Bootstrap JS -->
    <script src="assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Calculate total amount automatically
        function calculateTotal() {
            const gallons = parseFloat(document.getElementById('gallonsInput').value) || 0;
            const price = parseFloat(document.getElementById('priceInput').value) || 0;
            const total = gallons * price;
            document.getElementById('totalAmount').textContent = total.toFixed(2) + ' <?php echo CURRENCY; ?>';
        }
        
        // Initialize calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>
</html>
