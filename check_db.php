<?php

/**
 * Database Diagnostic Script
 */

$host = 'mysql-ad07bdc-kaayamus-d33d.f.aivencloud.com';
$port = 10997;
$user = 'avnadmin';
$password = 'YOUR_NEW_PASSWORD';
$database = 'defaultdb';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    $conn = new mysqli(
        $host,
        $user,
        $password,
        $database,
        $port
    );

    $conn->set_charset('utf8mb4');

    echo "<h3>✅ Database Connected Successfully</h3>";

    // Check users table
    $result = $conn->query("SHOW TABLES LIKE 'users'");

    if ($result->num_rows === 0) {
        die("
            <p>❌ Users table does not exist.</p>
            <p><a href='setup.php'>Run Setup Script</a></p>
        ");
    }

    echo "<p>✅ Users table exists</p>";

    // Check admin
    $stmt = $conn->prepare(
        "SELECT id, username FROM users WHERE username = ? LIMIT 1"
    );

    $username = 'admin';

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("
            <p>❌ Admin user does not exist.</p>
            <p><a href='setup.php'>Run Setup Script</a></p>
        ");
    }

    echo "<p>✅ Admin user exists</p>";
    echo "<p>✅ Database configuration is working correctly.</p>";

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {

    echo "<h3>❌ Database Connection Failed</h3>";

    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";

    echo "<p>
        Check your Aiven Host, Port, Username,
        Password and Database name.
    </p>";
}
?>
