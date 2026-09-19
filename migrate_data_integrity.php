<?php
/**
 * Safe migration for day-based service intervals and integer gallons.
 * Run once from the project directory: php migrate_data_integrity.php
 */

$conn = new mysqli('localhost', 'root', '', 'royalfamily_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error . PHP_EOL);
}
$conn->set_charset('utf8mb4');

$column = $conn->query("SHOW COLUMNS FROM service_records LIKE 'next_due_date_override'");
if ($column && $column->num_rows === 0) {
    if (!$conn->query("ALTER TABLE service_records ADD COLUMN next_due_date_override DATETIME NULL AFTER notes")) {
        die('Could not add next_due_date_override: ' . $conn->error . PHP_EOL);
    }
}

$column = $conn->query("SHOW COLUMNS FROM customers LIKE 'service_interval_days'");
if ($column && $column->num_rows === 0) {
    if (!$conn->query("ALTER TABLE customers ADD COLUMN service_interval_days INT UNSIGNED NULL AFTER service_interval_hours")) {
        die('Could not add service_interval_days: ' . $conn->error . PHP_EOL);
    }
}

$conn->query("UPDATE customers SET service_interval_days = GREATEST(1, CEIL(service_interval_hours / 24)) WHERE service_interval_days IS NULL AND service_interval_hours IS NOT NULL");

$fractional = $conn->query("SELECT COUNT(*) AS count FROM service_records WHERE gallons_delivered <> FLOOR(gallons_delivered)");
if (!$fractional || (int) $fractional->fetch_assoc()['count'] > 0) {
    die('Migration stopped: fractional gallon data exists and needs manual review before changing the column type.' . PHP_EOL);
}

$column = $conn->query("SHOW COLUMNS FROM service_records LIKE 'gallons_delivered'");
$gallons = $column ? $column->fetch_assoc() : null;
if ($gallons && stripos($gallons['Type'], 'int') === false) {
    if (!$conn->query("ALTER TABLE service_records MODIFY gallons_delivered INT UNSIGNED NOT NULL")) {
        die('Could not change gallons_delivered: ' . $conn->error . PHP_EOL);
    }
}

echo "Migration completed. Existing IDs, relationships, and data were preserved." . PHP_EOL;
$conn->close();