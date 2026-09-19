<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Database Configuration File
 * Version: 1.0
 * Description: Establishes MySQL database connection
 * ============================================
 */

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'royalfamily_db');

/**
 * Database Connection Function
 * Creates and returns a MySQLi connection object
 * 
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function getDbConnection() {
    // Create MySQLi connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    
    // Set character set to UTF-8 for proper encoding
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

function ensureUserLanguageColumn($conn) {
    $column = $conn->query("SHOW COLUMNS FROM users LIKE 'language'");
    if ($column && $column->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN language ENUM('en', 'sw') NOT NULL DEFAULT 'en' AFTER is_active");
    }
}

function ensureNextDueOverrideColumn($conn) {
    $column = $conn->query("SHOW COLUMNS FROM service_records LIKE 'next_due_date_override'");
    if ($column && $column->num_rows === 0) {
        $conn->query("ALTER TABLE service_records ADD COLUMN next_due_date_override DATETIME NULL AFTER notes");
    }
}

function ensureServiceIntervalDaysColumn($conn) {
    $column = $conn->query("SHOW COLUMNS FROM customers LIKE 'service_interval_days'");
    if ($column && $column->num_rows === 0) {
        $conn->query("ALTER TABLE customers ADD COLUMN service_interval_days INT UNSIGNED NULL AFTER service_interval_hours");
        $conn->query("UPDATE customers SET service_interval_days = GREATEST(1, CEIL(service_interval_hours / 24)) WHERE service_interval_days IS NULL AND service_interval_hours IS NOT NULL");
    }
}

/**
 * Global database connection variable
 * Used throughout the application
 */
$mysqli = getDbConnection();
ensureUserLanguageColumn($mysqli);
ensureNextDueOverrideColumn($mysqli);
ensureServiceIntervalDaysColumn($mysqli);

/**
 * Session Configuration
 * Sets secure session parameters
 * Must be called before session_start()
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    session_start();
}

require_once __DIR__ . '/../includes/i18n.php';

/**
 * Timezone Configuration
 * Sets default timezone to Africa/Dar_es_Salaam
 */
date_default_timezone_set('Africa/Dar_es_Salaam');

/**
 * Application Constants
 */
define('APP_NAME', 'RoyalFamily Water Delivery System');
define('APP_VERSION', '1.0');
define('CURRENCY', 'TZS');
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd M Y H:i');

function getServiceIntervalDays($customer) {
    if (isset($customer['service_interval_days']) && (int) $customer['service_interval_days'] > 0) {
        return (int) $customer['service_interval_days'];
    }

    if (isset($customer['service_interval_hours']) && (int) $customer['service_interval_hours'] > 0) {
        return max(1, (int) ceil((int) $customer['service_interval_hours'] / 24));
    }

    return 0;
}

function calculateNextDueDate($lastServiceDate, $intervalDays, $override = null) {
    if (!empty($override)) {
        return $override;
    }

    if (empty($lastServiceDate) || (int) $intervalDays <= 0) {
        return null;
    }

    $dueDate = new DateTime($lastServiceDate);
    $dueDate->modify('+' . (int) $intervalDays . ' days');
    return $dueDate->format('Y-m-d H:i:s');
}

function generateNextSequentialCode($mysqli, $table, $column, $prefix, $digits, $minimumValue, $maximumValue) {
    $pattern = '^' . preg_quote($prefix, '/') . '[0-9]+$';
    $sql = "SELECT `$column` FROM `$table` WHERE `$column` REGEXP ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();

    $highestValue = $minimumValue - 1;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $code = strtolower(trim((string) $row[$column]));
            if (preg_match('/^' . preg_quote($prefix, '/') . '([0-9]+)$/', $code, $matches)) {
                $currentValue = (int) $matches[1];
                if ($currentValue > $highestValue) {
                    $highestValue = $currentValue;
                }
            }
        }
    }

    $nextValue = $highestValue + 1;
    if ($nextValue > $maximumValue) {
        return null;
    }

    return sprintf('%s%0' . $digits . 'd', $prefix, $nextValue);
}
?>
