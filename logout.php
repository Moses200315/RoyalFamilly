<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * Logout Handler
 * Version: 1.0
 * Description: Handles user logout and session destruction
 * ============================================
 */

// Start session
session_start();

// Include authentication functions
require_once 'includes/auth_functions.php';

// Perform logout
logout();
?>
