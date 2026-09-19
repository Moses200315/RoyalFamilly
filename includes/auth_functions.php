<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Authentication Functions
 * Version: 1.0
 * Description: Handles user authentication, session management, and access control
 * ============================================
 */

/**
 * Check if user is logged in
 * Redirects to login page if not authenticated
 * 
 * @return void
 */
function requireLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Check if user is logged in (returns boolean)
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Attempt user login
 * Validates credentials and creates session if successful
 * 
 * @param mysqli $conn Database connection
 * @param string $username Username
 * @param string $password Plain text password
 * @return array Result array with success status and message
 */
function login($conn, $username, $password) {
    // Sanitize input
    $username = trim($conn->real_escape_string($username));
    
    // Query to get user by username
    $sql = "SELECT id, username, password, full_name, role, is_active, language FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['is_active'] != 1) {
            return [
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact administrator.'
            ];
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['language'] = in_array($user['language'], ['en', 'sw'], true) ? $user['language'] : 'en';
            $_SESSION['login_time'] = time();
            
            // Update last login timestamp
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
            $conn->query($update_sql);
            
            return [
                'success' => true,
                'message' => 'Login successful!'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'Invalid username or password.'
        ];
    }
}

/**
 * Logout user
 * Destroys session and redirects to login page
 * 
 * @return void
 */
function logout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect to login page
    header('Location: index.php');
    exit();
}

/**
 * Get current user information
 * 
 * @return array|false User data or false if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return false;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Check session timeout
 * Logs out user if session has expired (30 minutes)
 * 
 * @return void
 */
function checkSessionTimeout() {
    if (!isset($_SESSION['login_time'])) {
        return;
    }
    
    $session_duration = 30 * 60; // 30 minutes in seconds
    $elapsed = time() - $_SESSION['login_time'];
    
    if ($elapsed > $session_duration) {
        logout();
    }
}

/**
 * Generate CSRF token
 * Creates and stores a new CSRF token in session
 * 
 * @return string The CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * Checks if the provided token matches the session token
 * 
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate CSRF token
 * Creates a new CSRF token (use after form submission)
 * 
 * @return string The new CSRF token
 */
function regenerateCsrfToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
?>
