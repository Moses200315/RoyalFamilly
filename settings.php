<?php
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

requireLogin();
checkSessionTimeout();

$user_id = (int) $_SESSION['user_id'];
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $language = $_POST['language'] ?? 'en';

    if ($full_name === '' || $username === '') {
        $message = t('required_account_fields');
        $message_type = 'danger';
    } elseif (!in_array($language, ['en', 'sw'], true)) {
        $message = t('supported_language');
        $message_type = 'danger';
    } elseif ($password !== '' && strlen($password) < 6) {
        $message = t('password_minimum');
        $message_type = 'danger';
    } else {
        $duplicate = $mysqli->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $duplicate->bind_param('si', $username, $user_id);
        $duplicate->execute();
        $duplicate_result = $duplicate->get_result();

        if ($duplicate_result->num_rows > 0) {
            $message = t('username_in_use');
            $message_type = 'danger';
        } else {
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $mysqli->prepare('UPDATE users SET full_name = ?, username = ?, password = ?, language = ? WHERE id = ?');
                $update->bind_param('ssssi', $full_name, $username, $password_hash, $language, $user_id);
            } else {
                $update = $mysqli->prepare('UPDATE users SET full_name = ?, username = ?, language = ? WHERE id = ?');
                $update->bind_param('sssi', $full_name, $username, $language, $user_id);
            }

            if ($update->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['username'] = $username;
                setLanguage($language);
                $message = t('settings_updated');
            } else {
                $message = t('update_failed');
                $message_type = 'danger';
            }
        }
    }
}

$user_query = $mysqli->prepare('SELECT username, full_name, role FROM users WHERE id = ? LIMIT 1');
$user_query->bind_param('i', $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if (!$user) {
    logout();
}

$language = currentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $language === 'sw' ? 'sw' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('settings')); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="app-page">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><img class="navbar-brand-logo" src="assets/images/royal-family-logo.jpg" alt="Royal Family" width="28" height="28" style="display:block;width:28px;height:28px;max-width:28px;max-height:28px;object-fit:cover;"> RoyalFamily</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="customers.php">Customers</a></li>
                    <li class="nav-item"><a class="nav-link" href="staff.php">Staff</a></li>
                    <li class="nav-item"><a class="nav-link" href="deliveries.php">Deliveries</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                </ul>
                <a href="settings.php" class="nav-link active settings-link me-2" aria-label="Settings" title="Settings">
                    <i class="bi bi-gear-fill"></i>
                </a>
                <span class="navbar-text me-3"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['full_name']); ?></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mt-4 pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="mb-4">
                    <p class="text-uppercase text-primary small fw-bold mb-1"><?php echo t('account'); ?></p>
                    <h1 class="h2 mb-1"><i class="bi bi-gear-fill me-2"></i><?php echo t('settings'); ?></h1>
                    <p class="text-muted mb-0"><?php echo t('update_account_language'); ?></p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>-fill me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="full_name" name="full_name" placeholder="<?php echo t('full_name'); ?>" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        <label for="full_name"><?php echo t('full_name'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="username" name="username" placeholder="<?php echo t('username'); ?>" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                        <label for="username"><?php echo t('username'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo t('new_password'); ?>" autocomplete="new-password">
                                        <label for="password"><?php echo t('new_password'); ?></label>
                                    </div>
                                    <small class="text-muted"><?php echo t('leave_blank_password'); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="language" name="language">
                                            <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="sw" <?php echo $language === 'sw' ? 'selected' : ''; ?>>Kiswahili</option>
                                        </select>
                                        <label for="language"><?php echo t('language'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="dashboard.php" class="btn btn-light"><?php echo t('cancel'); ?></a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i><?php echo t('save_changes'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
