<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Database Setup Script
 * Version: 1.0
 * Description: Initialize database and create the first administrator user
 * ============================================
 */

// Database configuration
define('DB_HOST', 'mysql-ad07bdc-kaayamus-d33d.f.aivencloud.com');
define('DB_USER', 'avnadmin');
define('DB_PASS', 'AVNS_OBNh_oT5oV-C2a7wVAz');
define('DB_NAME', 'defaultdb');
define('DB_PORT', 10997);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Create database
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select database
        $conn->select_db(DB_NAME);
        
        // Read and execute schema file
        $schema_file = __DIR__ . '/database/schema.sql';
        if (file_exists($schema_file)) {
            $schema = file_get_contents($schema_file);
            
            // Split by semicolon to get individual statements
            $statements = explode(';', $schema);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    // Skip USE statement since we already selected the database
                    if (stripos($statement, 'USE ') === 0) {
                        continue;
                    }
                    if (stripos($statement, 'CREATE DATABASE') === 0) {
                        continue;
                    }
                    if (!$conn->query($statement)) {
                        // Ignore errors for statements that might fail due to existing data
                        if (strpos($conn->error, 'Duplicate entry') === false) {
                            echo "Warning: " . $conn->error . "<br>";
                        }
                    }
                }
            }
        } else {
            throw new Exception("Schema file not found: " . $schema_file);
        }
        
        $setup_username = trim($_POST['username'] ?? '');
        $setup_password = $_POST['password'] ?? '';
        $setup_full_name = trim($_POST['full_name'] ?? '');
        if ($setup_username === '' || $setup_full_name === '' || strlen($setup_password) < 6) {
            throw new Exception('Enter a full name, username, and a password of at least 6 characters.');
        }

        // Create the first administrator only from credentials entered during setup.
        $check_admin = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $check_admin->bind_param('s', $setup_username);
        $check_admin->execute();
        $check_admin_result = $check_admin->get_result();
        if ($check_admin_result->num_rows === 0) {
            $password = password_hash($setup_password, PASSWORD_DEFAULT);
            $insert = $conn->prepare('INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)');
            $role = 'admin';
            $insert->bind_param('ssss', $setup_username, $password, $setup_full_name, $role);
            if (!$insert->execute()) {
                throw new Exception("Error creating admin user: " . $conn->error);
            }
        }
        
        $message = "Database setup completed successfully!";
        $messageType = "success";
        
        $conn->close();
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - RoyalFamily</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div class="card" style="max-width: 500px; width: 100%; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
        <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px 20px 0 0; padding: 30px;">
            <h3 class="mb-0">Database Setup</h3>
            <p class="mb-0 opacity-75">RoyalFamily Water Delivery System</p>
        </div>
        <div class="card-body p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <p class="text-muted mb-4">Initialize the database and create the first administrator account.</p>
            
            <form method="POST">
                <input class="form-control mb-2" name="full_name" placeholder="Administrator full name" required>
                <input class="form-control mb-2" name="username" placeholder="Administrator username" required>
                <input class="form-control mb-3" type="password" name="password" placeholder="Administrator password (6+ characters)" minlength="6" required>
                <button type="submit" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px; border-radius: 10px;">
                    <i class="bi bi-database-fill"></i> Setup Database
                </button>
            </form>
            
            <?php if ($messageType === 'success'): ?>
                <div class="mt-3">
                    <a href="index.php" class="btn btn-success w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Go to Login Page
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html>
