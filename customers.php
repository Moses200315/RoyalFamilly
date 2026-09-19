<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Customer Management Page
 * Version: 1.0
 * Description: Customer CRUD operations - Create, Read, Update, Delete customers
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'danger';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        // Add new customer
        if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone1 = trim($_POST['phone1'] ?? '');
        $phone2 = trim($_POST['phone2'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $service_interval_days = filter_input(INPUT_POST, 'service_interval_days', FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }
        
        // Validation
        if (empty($full_name) || empty($phone1) || $service_interval_days === false || $service_interval_days < 1) {
            $message = 'Please fill in all required fields.';
            $messageType = 'danger';
        } else {
            $customer_code = generateNextSequentialCode($mysqli, 'customers', 'customer_code', 'cust', 4, 1, 9999);
            if ($customer_code === null) {
                $message = 'Customer ID limit reached. The system can only generate customer IDs up to cust9999.';
                $messageType = 'danger';
            } else {
                $sql = "INSERT INTO customers (customer_code, full_name, phone1, phone2, email, address, service_interval_days, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('ssssssisi', $customer_code, $full_name, $phone1, $phone2, $email, $address, $service_interval_days, $status, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                        $message = 'Customer added successfully!';
                        $messageType = 'success';
                } else {
                        $message = 'Error adding customer: ' . $mysqli->error;
                        $messageType = 'danger';
                }
            }
        }
    }
    
    // Update customer
    if ($action === 'update') {
        $customer_id = intval($_POST['customer_id']);
        $full_name = trim($_POST['full_name'] ?? '');
        $phone1 = trim($_POST['phone1'] ?? '');
        $phone2 = trim($_POST['phone2'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $service_interval_days = filter_input(INPUT_POST, 'service_interval_days', FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? '';
        
        // Validation
        if (empty($full_name) || empty($phone1) || $service_interval_days === false || $service_interval_days < 1) {
            $message = 'Please fill in all required fields.';
            $messageType = 'danger';
        } else {
            $sql = "UPDATE customers SET
                        full_name = ?,
                        phone1 = ?,
                        phone2 = ?,
                        email = ?,
                        address = ?,
                        service_interval_days = ?,
                        status = ?
                        WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssssis', $full_name, $phone1, $phone2, $email, $address, $service_interval_days, $status, $customer_id);

                if ($stmt->execute()) {
                    $message = 'Customer updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating customer: ' . $mysqli->error;
                    $messageType = 'danger';
                }
        }
    }
    
    // Delete customer
    if ($action === 'delete') {
        $customer_id = intval($_POST['customer_id']);
        
        // Check if customer has service records
        $check_sql = "SELECT id FROM service_records WHERE customer_id = ?";
        $stmt = $mysqli->prepare($check_sql);
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            $message = 'Cannot delete customer with existing service records. Please deactivate instead.';
            $messageType = 'danger';
        } else {
            $sql = "DELETE FROM customers WHERE id = ?";
            $stmt2 = $mysqli->prepare($sql);
            $stmt2->bind_param('i', $customer_id);
            
            if ($stmt2->execute()) {
                $message = 'Customer deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error deleting customer: ' . $mysqli->error;
                $messageType = 'danger';
            }
        }
    }
    regenerateCsrfToken();
    }
}

// Handle search
$searchTerm = '';
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search'] ?? '');
}

// Build query to fetch customers
$sql = "SELECT c.*, 
        (SELECT sr.service_date_time FROM service_records sr 
         WHERE sr.customer_id = c.id 
         ORDER BY sr.service_date_time DESC LIMIT 1) as last_service_date,
        c.service_interval_days,
        c.service_interval_hours
        FROM customers c";

if (!empty($searchTerm)) {
    $sql .= " WHERE c.full_name LIKE ? 
              OR c.customer_code LIKE ? 
              OR c.phone1 LIKE ?
              OR c.phone2 LIKE ?";
    $searchPattern = '%' . $searchTerm . '%';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ssss', $searchPattern, $searchPattern, $searchPattern, $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql .= " ORDER BY c.created_at DESC";
    $result = $mysqli->query($sql);
}

// Fetch all customers
$customers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - <?php echo APP_NAME; ?></title>
    
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
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .customer-address {
            min-width: 220px;
            max-width: 320px;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
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
                        <a class="nav-link active" href="customers.php">Customers</a>
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
        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>-fill me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Customer Management Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-2"></i>Customer Management</span>
                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Customer
                </button>
            </div>
            <div class="card-body">
                <!-- Search Form -->
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="search" placeholder="Search by name, code, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Customers Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Name</th>
                                <th>Phone 1</th>
                                <th>Phone 2</th>
                                <th>Interval</th>
                                <th>Last Service</th>
                                <th>Next Due</th>
                                <th>Due Today</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="mt-2 text-muted">No customers found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($customer['customer_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone1']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone2'] ?: '-'); ?></td>
                                        <td><?php $intervalDays = getServiceIntervalDays($customer); echo $intervalDays ? $intervalDays . ' day' . ($intervalDays === 1 ? '' : 's') : '-'; ?></td>
                                        <td><?php echo $customer['last_service_date'] ? date('d M Y H:i', strtotime($customer['last_service_date'])) : '-'; ?></td>
                                        <?php $nextDue = calculateNextDueDate($customer['last_service_date'], $intervalDays); ?>
                                        <td><?php echo $nextDue ? date('d M Y', strtotime($nextDue)) : '-'; ?></td>
                                        <td><?php echo $nextDue && date('Y-m-d', strtotime($nextDue)) === date('Y-m-d') ? 'Yes' : 'No'; ?></td>
                                        <td class="customer-address"><?php echo htmlspecialchars($customer['address'] ?: '-'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $customer['status']; ?>">
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-info action-btn" data-bs-toggle="modal" data-bs-target="#editCustomerModal<?php echo $customer['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger action-btn" data-bs-toggle="modal" data-bs-target="#deleteCustomerModal<?php echo $customer['id']; ?>">
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

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New Customer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Customer Code *</label>
                                    <div class="form-text">Customer ID is generated automatically.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone 1 *</label>
                                    <input type="text" class="form-control" name="phone1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone 2</label>
                                    <input type="text" class="form-control" name="phone2">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Service Interval (days) *</label>
                                    <input type="number" class="form-control" name="service_interval_days" min="1" step="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modals -->
    <?php foreach ($customers as $customer): ?>
        <div class="modal fade" id="editCustomerModal<?php echo $customer['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Customer</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Customer ID</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($customer['customer_code']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($customer['full_name']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone 1 *</label>
                                        <input type="text" class="form-control" name="phone1" value="<?php echo htmlspecialchars($customer['phone1']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone 2</label>
                                        <input type="text" class="form-control" name="phone2" value="<?php echo htmlspecialchars($customer['phone2']); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Service Interval (days) *</label>
                                        <input type="number" class="form-control" name="service_interval_days" min="1" step="1" value="<?php echo getServiceIntervalDays($customer); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo $customer['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $customer['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Customer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Customer Modal -->
        <div class="modal fade" id="deleteCustomerModal<?php echo $customer['id']; ?>" tabindex="-1">
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
                            <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                            <p>Are you sure you want to delete customer <strong><?php echo htmlspecialchars($customer['full_name']); ?></strong>?</p>
                            <p class="text-muted small">This action cannot be undone.</p>
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
</body>
</html>
