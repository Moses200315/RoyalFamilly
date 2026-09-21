<?php
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

requireAdministrator();
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'admin';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!in_array($role, ['admin', 'system_administrator'], true)) {
            $role = 'admin';
        }

        if ($action === 'save' && ($full_name === '' || $username === '')) {
            $message = 'Full name and username are required.';
            $message_type = 'danger';
        } elseif ($action === 'save' && $user_id === 0 && strlen($password) < 6) {
            $message = 'New users require a password of at least 6 characters.';
            $message_type = 'danger';
        } elseif ($action === 'save') {
            $duplicate = $mysqli->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $duplicate->bind_param('si', $username, $user_id);
            $duplicate->execute();
            if ($duplicate->get_result()->num_rows > 0) {
                $message = 'That username is already in use.';
                $message_type = 'danger';
            } elseif ($user_id > 0) {
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $mysqli->prepare('UPDATE users SET full_name = ?, username = ?, password = ?, role = ?, is_active = ? WHERE id = ?');
                    $stmt->bind_param('ssssii', $full_name, $username, $password_hash, $role, $is_active, $user_id);
                } else {
                    $stmt = $mysqli->prepare('UPDATE users SET full_name = ?, username = ?, role = ?, is_active = ? WHERE id = ?');
                    $stmt->bind_param('sssii', $full_name, $username, $role, $is_active, $user_id);
                }
                if ($user_id === (int) $_SESSION['user_id'] && !$is_active) {
                    $message = 'You cannot deactivate your own account.';
                    $message_type = 'danger';
                } elseif ($stmt->execute()) {
                    $message = 'User updated successfully.';
                } else {
                    $message = 'Unable to update user.';
                    $message_type = 'danger';
                }
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare('INSERT INTO users (username, password, full_name, role, is_active) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssi', $username, $password_hash, $full_name, $role, $is_active);
                if ($stmt->execute()) {
                    $message = 'User created successfully.';
                } else {
                    $message = 'Unable to create user.';
                    $message_type = 'danger';
                }
            }
        }
        regenerateCsrfToken();
    }
}

$users = [];
$result = $mysqli->query('SELECT id, username, full_name, role, is_active, created_at, last_login FROM users ORDER BY full_name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="app-page">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-droplet-fill"></i> RoyalFamily</a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="settings.php">Settings</a>
                <a class="nav-link active" href="users.php">Users</a>
            </div>
            <span class="navbar-text me-3"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <main class="container mt-4 pb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><h1 class="h3 mb-1">User Management</h1><p class="text-muted mb-0">Manage access to the delivery system.</p></div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-person-plus me-1"></i>Add User</button>
        </div>
        <?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo $user['role'] === 'system_administrator' ? 'System Administrator' : 'Administrator'; ?></td>
                    <td><span class="badge text-bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td><?php echo $user['last_login'] ? htmlspecialchars(date(DISPLAY_DATE_FORMAT, strtotime($user['last_login']))) : 'Never'; ?></td>
                    <td><button class="btn btn-sm btn-outline-primary edit-user" data-user='<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>'><i class="bi bi-pencil"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div></div>
    </main>

    <div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">User Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="save"><input type="hidden" name="user_id" id="user_id" value="0">
                <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" id="full_name" required></div>
                <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" id="username" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="6"><small class="text-muted">Leave blank when editing to keep the current password.</small></div>
                <div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role" id="role"><option value="admin">Administrator</option><option value="system_administrator">System Administrator</option></select></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked><label class="form-check-label" for="is_active">Active account</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save User</button></div>
        </form>
    </div></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.edit-user').forEach((button) => button.addEventListener('click', () => {
            const user = JSON.parse(button.dataset.user);
            document.getElementById('user_id').value = user.id;
            document.getElementById('full_name').value = user.full_name;
            document.getElementById('username').value = user.username;
            document.getElementById('role').value = user.role;
            document.getElementById('is_active').checked = user.is_active === '1' || user.is_active === 1;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show();
        }));
    </script>
</body>
</html>
