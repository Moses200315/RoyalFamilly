<?php

// Database configuration constants
define('DB_HOST', 'mysql-ad07bdc-kaayamus-d33d.f.aivencloud.com');
define('DB_USER', 'avnadmin');
define('DB_PASS', 'AVNS_OBNh_oT5oV-C2a7wVAz');
define('DB_NAME', 'defaultdb');
define('DB_PORT', 10997);

/**
 * Database Connection Function
 * Creates and returns a MySQLi connection object with SSL
 * 
 * @return mysqli Database connection object
 */
function getDbConnection() {
    $conn = mysqli_init();
    
    if (!$conn) {
        die("mysqli_init failed");
    }

    // SSL inahitajika na Aiven Cloud
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

    // Muunganisho unaoangazia Port na SSL
    if (!@$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    
    // Set character set to UTF-8
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

function ensureDeliveryTypeColumn($conn) {
    $column = $conn->query("SHOW COLUMNS FROM service_records LIKE 'delivery_type'");
    if ($column && $column->num_rows === 0) {
        $conn->query("ALTER TABLE service_records ADD COLUMN delivery_type ENUM('normal', 'offer') NOT NULL DEFAULT 'normal' AFTER total_amount");
        $conn->query("UPDATE service_records SET delivery_type = 'normal' WHERE delivery_type IS NULL OR delivery_type = ''");
    }
}

/**
 * Global database connection variable
 */
$mysqli = getDbConnection();
ensureUserLanguageColumn($mysqli);
ensureNextDueOverrideColumn($mysqli);
ensureServiceIntervalDaysColumn($mysqli);
ensureDeliveryTypeColumn($mysqli);

/**
 * Session Configuration
 * Isolated save path avoids shared-host GC deleting sessions after ~24 minutes.
 */
$sessionLifetime = 60 * 60 * 8;
$sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    session_save_path($sessionPath);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/i18n.php';

/**
 * Timezone Configuration
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

function deliveryTypeOf($record) {
    $type = strtolower(trim((string) ($record['delivery_type'] ?? 'normal')));
    return $type === 'offer' ? 'offer' : 'normal';
}

function isOfferDelivery($record) {
    return deliveryTypeOf($record) === 'offer';
}

function paidAmountForRecord($record) {
    return isOfferDelivery($record) ? 0.0 : (float) ($record['total_amount'] ?? 0);
}

function customerDisplayName($record) {
    $name = trim((string) ($record['full_name'] ?? $record['customer_name'] ?? ''));
    return $name !== '' ? $name : 'Unknown customer';
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
    $sql = "SELECT $column FROM $table WHERE $column REGEXP ?";
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
