<?php
/**
* Database Diagnostic Script
* Checks database connection and admin user status
*/

$host = 'mysql-ad07bdc-kaayamus-d33d.f.aivencloud.com';
$user = 'avnadmin';
$pass = 'AVNS_OBNh_oT5oV-C2a7wVAz';
$db   = 'defaultdb';
$port = 10997; // Tarakimu (int) bila quotes

// Anzisha muunganisho kwa kutumia SSL kwa ajili ya Aiven
$conn = mysqli_init();
if (!$conn) {
    die("<h3>mysqli_init failed</h3>");
}

$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

// real_connect(host, user, pass, db, port, socket, flags)
if (!@$conn->real_connect($host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("<h3>Database Connection Failed</h3>
    <p>Error: " . mysqli_connect_error() . "</p>
    <p>The database '$db' may not exist or port is blocked.</p>
    <p><a href='setup.php'>Run Setup Script</a></p>");
}

echo "<h3>✓ Database Connected Successfully</h3>";

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result && $result->num_rows === 0) {
    die("<p>❌ Users table does not exist.</p>
    <p><a href='setup.php'>Run Setup Script</a></p>");
}

echo "<p>✓ Users table exists</p>";

// Check if admin user exists
$result = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($result && $result->num_rows === 0) {
    die("<p>❌ Admin user does not exist.</p>
    <p><a href='setup.php'>Run Setup Script</a></p>");
}

echo "<p>✓ Admin user exists</p>";

echo "<p>✓ Administrator account exists. Password values are intentionally not displayed or tested here.</p>";

$conn->close();
?>
