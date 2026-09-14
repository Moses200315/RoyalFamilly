<?php
/**
* Database Diagnostic Script
* Checks database connection and admin user status
*/

// Test database connection
$conn = new mysqli('mysql-ad07bdc-kaayamus-d33d.f.aivencloud.com','10997','avnadmin','AVNS_OBNh_oT5oV-C2a7wVAz','defaultdb');

if ($conn->connect_error) {
die("<h3>Database Connection Failed</h3>
<p>Error: " . $conn->connect_error . "</p>
<p>The database 'royalfamily_db' may not exist yet.</p>
<p><a href='setup.php'>Run Setup Script</a></p>");
}

echo "<h3>✓ Database Connected Successfully</h3>";

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows === 0) {
die("<p>❌ Users table does not exist.</p>
<p><a href='setup.php'>Run Setup Script</a></p>");
}

echo "<p>✓ Users table exists</p>";

// Check if admin user exists
$result = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($result->num_rows === 0) {
die("<p>❌ Admin user does not exist.</p>
<p><a href='setup.php'>Run Setup Script</a></p>");
}

echo "<p>✓ Admin user exists</p>";

echo "<p>✓ Administrator account exists. Password values are intentionally not displayed or tested here.</p>";

$conn->close();
?>
