<?php
/**
 * Add next_due_date_override column to service_records table
 */

$conn = new mysqli('localhost', 'root', '', 'royalfamily_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add the column
$sql = "ALTER TABLE service_records ADD COLUMN next_due_date_override DATETIME NULL AFTER notes";

if ($conn->query($sql)) {
    echo "Column 'next_due_date_override' added successfully to service_records table.";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>
