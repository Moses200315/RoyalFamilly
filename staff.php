<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Staff Management Page
 * Version: 1.0
 * Description: Staff CRUD operations - Create, Read, Update, Delete delivery staff
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
        
        // Add new staff
        if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = $_POST['status'] ?? '';
        
        // Validation
        if (empty($full_name) || empty($phone)) {
            $message = 'Please fill in all required fields.';
            $messageType = 'danger';
        } else {
            $staff_code = generateNextSequentialCode($mysqli, 'staff', 'staff_code', 'stf', 2, 0, 99);
            if ($staff_code === null) {
                $message = 'Staff ID limit reached. The system can only generate staff IDs up to stf99.';
                $messageType = 'danger';
            } else {
                $sql = "INSERT INTO staff (staff_code, full_name, phone, email, status)
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('sssss', $staff_code, $full_name, $phone, $email, $status);
                    if ($stmt->execute()) {
                        $message = 'Staff member added successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error adding staff: ' . $mysqli->error;
                        $messageType = 'danger';
                    }
            }
        }
    }
    
    // Update staff
    if ($action === 'update') {
        $staff_id = intval($_POST['staff_id']);
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = $_POST['status'] ?? '';
        
        // Validation
        if (empty($full_name) || empty($phone)) {
            $message = 'Please fill in all required fields.';
            $messageType = 'danger';
        } else {
            $sql = "UPDATE staff SET
                        full_name = ?,
                        phone = ?,
                        email = ?,
                        status = ?
                        WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssi', $full_name, $phone, $email, $status, $staff_id);
                
                if ($stmt->execute()) {
                    $message = 'Staff member updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating staff: ' . $mysqli->error;
                    $messageType = 'danger';
                }
        }
    }
    
    // Delete staff
    if ($action === 'delete') {
        $staff_id = intval($_POST['staff_id']);
        
        // Check if staff has service records
        $check_sql = "SELECT id FROM service_records WHERE staff_id = ?";
        $stmt = $mysqli->prepare($check_sql);
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            $message = 'Cannot delete staff member with existing delivery records. Please deactivate instead.';
            $messageType = 'danger';
        } else {
            $sql = "DELETE FROM staff WHERE id = ?";
            $stmt2 = $mysqli->prepare($sql);
            $stmt2->bind_param('i', $staff_id);
            
            if ($stmt2->execute()) {
                $message = 'Staff member deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error deleting staff: ' . $mysqli->error;
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

// Build query to fetch staff
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM service_records sr WHERE sr.staff_id = s.id) as total_deliveries,
        (SELECT SUM(sr.gallons_delivered) FROM service_records sr WHERE sr.staff_id = s.id) as total_gallons
        FROM staff s";

if (!empty($searchTerm)) {
    $sql .= " WHERE LOWER(s.full_name) LIKE LOWER(?)
              OR LOWER(s.staff_code) LIKE LOWER(?)
              OR LOWER(IFNULL(s.phone, '')) LIKE LOWER(?)
              OR LOWER(IFNULL(s.email, '')) LIKE LOWER(?)";
    $sql .= " ORDER BY s.full_name ASC";
    $searchPattern = '%' . $searchTerm . '%';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ssss', $searchPattern, $searchPattern, $searchPattern, $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql .= " ORDER BY s.created_at DESC";
    $result = $mysqli->query($sql);
}

// Fetch all staff
$staff_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staff_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - <?php echo APP_NAME; ?></title>
    
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
                <img class="navbar-brand-logo" src="assets/images/royal-family-logo.jpg" alt="Royal Family">
                RoyalFamily
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
                        <a class="nav-link active" href="staff.php">Staff</a>
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

        <!-- Staff Management Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge-fill me-2"></i>Staff Management</span>
                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Staff
                </button>
            </div>
            <div class="card-body">
                <!-- Search Form -->
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="search" data-live-search="staff" placeholder="Search by name, code, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>" autocomplete="off">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Staff Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Total Deliveries</th>
                                <th>Total Gallons</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staff_list)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="mt-2 text-muted">No staff members found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staff_list as $staff): ?>
                                    <tr data-search-row="staff" data-search="<?php echo htmlspecialchars(strtolower($staff['full_name'] . ' ' . $staff['staff_code'] . ' ' . $staff['phone'] . ' ' . $staff['email'])); ?>">
                                        <td><strong><?php echo htmlspecialchars($staff['staff_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['email'] ?: '-'); ?></td>
                                        <td><?php echo number_format($staff['total_deliveries'] ?: 0); ?></td>
                                        <td><?php echo number_format((int) ($staff['total_gallons'] ?: 0)); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $staff['status']; ?>">
                                                <?php echo ucfirst($staff['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-info action-btn" data-bs-toggle="modal" data-bs-target="#editStaffModal<?php echo $staff['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger action-btn" data-bs-toggle="modal" data-bs-target="#deleteStaffModal<?php echo $staff['id']; ?>">
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

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Staff ID</label>
                            <div class="form-text">Staff ID is generated automatically.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="text" class="form-control" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
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
                        <button type="submit" class="btn btn-primary">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modals -->
    <?php foreach ($staff_list as $staff): ?>
        <div class="modal fade" id="editStaffModal<?php echo $staff['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Staff</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Staff ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($staff['staff_code']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($staff['phone']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo $staff['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $staff['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Staff</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Staff Modal -->
        <div class="modal fade" id="deleteStaffModal<?php echo $staff['id']; ?>" tabindex="-1">
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
                            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                            <p>Are you sure you want to delete staff member <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>?</p>
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
