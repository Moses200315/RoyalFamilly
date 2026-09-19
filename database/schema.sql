-- ============================================
-- RoyalFamily Water Delivery System
-- Database Schema
-- Version: 1.0
-- Description: Complete database structure for customer monitoring and sales system
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS royalfamily_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE royalfamily_db;

-- ============================================
-- Table: users
-- Purpose: Store admin user authentication information
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    language ENUM('en', 'sw') NOT NULL DEFAULT 'en',
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: customers
-- Purpose: Store customer information and service configuration
-- ============================================
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone1 VARCHAR(20) NOT NULL,
    phone2 VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    service_interval_hours INT NOT NULL DEFAULT 72,
    service_interval_days INT UNSIGNED NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_customer_code (customer_code),
    INDEX idx_status (status),
    INDEX idx_full_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: staff
-- Purpose: Store delivery staff information
-- ============================================
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_code VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_code (staff_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: service_records
-- Purpose: Store all delivery/service records with historical data
-- ============================================
CREATE TABLE IF NOT EXISTS service_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT NOT NULL,
    service_date_time DATETIME NOT NULL,
    gallons_delivered INT UNSIGNED NOT NULL,
    price_per_gallon DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    notes TEXT,
    next_due_date_override DATETIME NULL,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT,
    INDEX idx_customer_id (customer_id),
    INDEX idx_staff_id (staff_id),
    INDEX idx_service_date_time (service_date_time),
    INDEX idx_customer_service_date (customer_id, service_date_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Sample data for testing (optional)
-- ============================================
-- Insert sample staff
INSERT INTO staff (staff_code, full_name, phone, email, status) VALUES
('STF001', 'John Mwangi', '+255712345678', 'john@royalfamily.co.tz', 'active'),
('STF002', 'Mary Kileo', '+255712345679', 'mary@royalfamily.co.tz', 'active'),
('STF003', 'David Masanja', '+255712345680', 'david@royalfamily.co.tz', 'active')
ON DUPLICATE KEY UPDATE staff_code=staff_code;

-- Insert sample customers
INSERT INTO customers (customer_code, full_name, phone1, phone2, email, address, service_interval_hours, service_interval_days, status) VALUES
('CUST001', 'Ahmed Hassan', '+255711111111', '+255722222222', 'ahmed@email.com', 'Dar es Salaam, Masaki', 72, 3, 'active'),
('CUST002', 'Grace Mwamba', '+255711111112', '+255722222223', 'grace@email.com', 'Dar es Salaam, Oysterbay', 48, 2, 'active'),
('CUST003', 'Peter Kimaro', '+255711111113', NULL, 'peter@email.com', 'Dar es Salaam, Mikocheni', 96, 4, 'active')
ON DUPLICATE KEY UPDATE customer_code=customer_code;
