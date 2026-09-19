# RoyalFamily Water Delivery Customer Monitoring & Sales System

## Overview

RoyalFamily is a comprehensive web-based Water Delivery Customer Monitoring & Sales System designed to help manage customers who receive water gallons, monitor each customer's individual service interval, record deliveries and assigned service staff, automatically calculate sales, identify customers approaching or passing their service deadline, and generate date-based PDF reports.

## Version

- **Version:** 1.0
- **Status:** Draft for Client Review & Approval
- **Application Type:** Web Application
- **Primary User:** Administrator (Admin)
- **Currency:** Tanzanian Shillings (TZS)

## Features

### Core Functionality

- **Customer Management**
  - Register customers with unique customer codes
  - Store customer details: name, phone numbers, email, address
  - Configure individual service intervals in days
  - Set customer status (Active/Inactive)
  - Search and view customer information
  - Edit and delete customer records

- **Staff Management**
  - Register delivery staff members
  - Track staff performance (total deliveries, gallons delivered)
  - Set staff status (Active/Inactive)
  - Edit and delete staff records

- **Delivery Recording**
  - Record water deliveries with customer and staff assignment
  - Automatic calculation of total amount (Gallons × Price per Gallon)
  - Store historical price data for accurate sales tracking
  - Add delivery notes
  - Filter and search delivery records

- **Monitoring & Alerts**
  - **One-Day Reminder:** Alerts shown one day before service deadline
  - **Due Today:** Customers whose due date falls on current calendar date
  - **Overdue Detection:** Customers past their deadline with overdue duration
  - **Real-time Dashboard:** Summary cards showing key metrics
  - **Auto-refresh:** Dashboard refreshes every 5 minutes

- **Reporting**
  - Date-range report generation
  - Served customers report with delivery details
  - Due customers report with due times
  - Next Due report for upcoming deliveries
  - Overdue customers report with overdue duration
  - Sales summary with staff breakdown
  - Staff performance report with detailed metrics
  - PDF export functionality with landscape mode for wide reports
  - Print-friendly reports with proper text wrapping

### Dashboard Summary Cards

- Total Active Customers
- Served Today
- Due Today
- Overdue
- Total Gallons Delivered Today
- Total Sales Today (TZS)

## Technology Stack

### Backend
- **PHP:** Pure procedural PHP (non-object-oriented)
- **MySQL:** Database management
- **PHP Sessions:** Authentication and session management

### Frontend
- **HTML5:** Markup structure
- **CSS3:** Styling with custom CSS
- **Bootstrap 5:** UI framework for responsive design
- **Bootstrap Icons:** Icon library
- **JavaScript:** Client-side interactivity
- **FPDF:** PDF generation library

## System Requirements

### Server Requirements
- PHP 7.0 or higher
- MySQL 5.6 or higher / MariaDB 10.0 or higher
- Apache Web Server (with mod_rewrite enabled) or Nginx
- PHP Extensions:
  - mysqli
  - mbstring
  - session
  - json
  - gd (for image support, optional)

### Browser Requirements
- Modern web browser with JavaScript enabled
- Google Chrome (recommended)
- Mozilla Firefox
- Microsoft Edge
- Safari

## Installation Guide

### Step 1: Extract Files

Extract the project files to your web server's document root:
```
C:\xampp\htdocs\royalfamily\  (for XAMPP)
/var/www/html/royalfamily/    (for Linux)
```

### Step 2: Database Setup

1. Open MySQL command line or phpMyAdmin
2. Create the database by running the schema file:
```bash
mysql -u root -p < database/schema.sql
```

Or manually execute the SQL commands in `database/schema.sql`

3. Verify the database `royalfamily_db` is created with all tables:
   - users
   - customers
   - staff
   - service_records

### Step 3: Configure Database Connection

Edit `config/config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'royalfamily_db');
```

### Step 4: Set File Permissions (Linux Only)

```bash
chmod 755 /var/www/html/royalfamily
chmod 644 /var/www/html/royalfamily/*.php
chmod -R 755 /var/www/html/royalfamily/fpdf
```

### Step 5: Access the Application

Open your web browser and navigate to:
```
http://localhost/royalfamily/
```

### Initial Login

Run `setup.php` and enter the first administrator name, username, and password. No default credentials are created or displayed.

## Directory Structure

```
royalfamily/
├── config/
│   └── config.php              # Database configuration and constants
├── database/
│   └── schema.sql              # Database schema and sample data
├── includes/
│   └── auth_functions.php      # Authentication functions
├── fpdf/
│   ├── fpdf.php                # FPDF library
│   └── font/                   # Font files for PDF generation
├── index.php                   # Login page
├── dashboard.php               # Main dashboard
├── customers.php               # Customer management
├── staff.php                   # Staff management
├── deliveries.php              # Delivery recording
├── reports.php                 # Report generation
├── generate_pdf.php            # PDF generation
├── logout.php                  # Logout handler
└── README.md                   # This file
```

## User Guide

### 1. Login

1. Navigate to the application URL
2. Enter your username and password
3. Click "Sign In"
4. You will be redirected to the dashboard

### 2. Dashboard

The dashboard provides:
- **Summary Cards:** Key metrics at a glance
- **Reminders Panel:** Customers due within one day
- **Due Today Panel:** Customers due today with exact times
- **Overdue Panel:** Overdue customers with duration
- **Quick Actions:** Fast access to common functions

### 3. Managing Customers

#### Add Customer
1. Click "Customers" in navigation
2. Click "Add Customer" button
3. Fill in required fields:
  - Customer ID is generated automatically
   - Full Name
   - Phone 1 (required)
   - Phone 2 (optional)
   - Email (optional)
   - Address (optional)
  - Service Interval in days
   - Status (Active/Inactive)
4. Click "Add Customer"

#### Edit Customer
1. Click the pencil icon next to customer
2. Modify the required fields
3. Click "Update Customer"

#### Delete Customer
1. Click the trash icon next to customer
2. Confirm deletion
3. Note: Customers with delivery records cannot be deleted (deactivate instead)

### 4. Managing Staff

#### Add Staff
1. Click "Staff" in navigation
2. Click "Add Staff" button
3. Fill in required fields:
   - Staff Code (unique)
   - Full Name
   - Phone
   - Email (optional)
   - Status (Active/Inactive)
4. Click "Add Staff"

#### Edit/Delete Staff
Similar to customer management

### 5. Recording Deliveries

1. Click "Deliveries" in navigation
2. Click "Record Delivery" button
3. Fill in the form:
   - Customer (select from dropdown)
   - Staff Member (select from dropdown)
   - Service Date/Time (auto-filled with current date/time)
   - Gallons Delivered
   - Price Per Gallon (TZS)
   - Notes (optional)
4. Total amount is calculated automatically
5. Click "Record Delivery"

**Note:** Recording a delivery automatically:
- Calculates the total amount
- Updates the customer's last service date
- Calculates the next due date based on service interval
- Resets the monitoring cycle

### 6. Generating Reports

1. Click "Reports" in navigation
2. Select date range (start and end dates)
3. Select report type:
   - All Reports (default) - Shows all report sections
   - Served Customers - Customers who received deliveries
   - Due Customers - Customers due for service
   - Next Due - Upcoming due dates
   - Overdue Customers - Customers past their due date
   - Sales Summary - Overall sales statistics with staff breakdown
   - Staff Performance - Detailed staff performance metrics
4. Click "Generate Report"
5. View results on screen
6. Click "Print" to print the report
7. Click "Download PDF" to save as PDF (landscape mode for wide reports)

## Business Rules

1. **Service Interval:** Every customer has an individual service interval measured in days
2. **Date/Time Monitoring:** System uses date and time (not just date) for monitoring
3. **Next Due Calculation:** Next Due = Latest Service + Service Interval
4. **Reminder Time:** Reminder = Next Due - 1 day
5. **Overdue Status:** Customer is overdue when deadline passes without new service
6. **Cycle Reset:** Recording new delivery resets the monitoring cycle
7. **Total Calculation:** Total Amount = Gallons × Price Per Gallon
8. **Historical Prices:** Price per gallon is stored with each record
9. **Inactive Exclusion:** Inactive customers don't generate alerts
10. **Record Preservation:** Historical records are preserved even if customer becomes inactive

## Security Considerations

1. **Password Hashing:** All passwords are hashed using PHP's password_hash()
2. **Session Management:** Sessions expire after 30 minutes of inactivity
3. **SQL Injection Prevention:** All database queries use prepared statements (mysqli prepared statements) to prevent SQL injection
4. **CSRF Protection:** All forms include CSRF tokens to prevent Cross-Site Request Forgery attacks
5. **XSS Prevention:** All output is escaped using htmlspecialchars() to prevent Cross-Site Scripting
6. **Authentication Required:** All protected pages require login
7. **Input Validation:** Required fields are validated before processing
8. **Secure Session Settings:** Session cookies are configured with secure settings

## Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check database credentials in config/config.php
- Ensure database royalfamily_db exists

### Login Not Working
- Verify the administrator credentials entered during setup.
- Check if users table has data
- Clear browser cookies and try again

### PDF Generation Not Working
- Ensure fpdf.php is in the fpdf directory
- Check PHP write permissions in fpdf/font directory
- Verify FPDF library is properly included

### Session Timeout Issues
- Check PHP session configuration in php.ini
- Ensure session.save_path is writable
- Verify cookies are enabled in browser

## Customization

### Change Default Password

1. Access database via phpMyAdmin or MySQL command line
2. Run the following SQL:
```sql
UPDATE users SET password = '$2y$10$YOUR_NEW_HASHED_PASSWORD' WHERE username = 'admin';
```

3. Generate new password hash using:
```php
<?php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
?>
```

### Modify Service Interval Defaults

Edit the service_interval_hours default value in:
- `database/schema.sql`
- Customer management form in `customers.php`

### Change Currency

Edit the CURRENCY constant in `config/config.php`:
```php
define('CURRENCY', 'TZS');  // Change to your preferred currency
```

## Support

For issues, questions, or feature requests, please contact the development team.

## License

This project is proprietary software for RoyalFamily. All rights reserved.

## Version History

### Version 1.0 (Current)
- Initial release
- Customer management with auto-generated codes (CUST000001 format)
- Staff management with auto-generated codes (STF000001 format)
- Delivery recording with integer gallons validation
- Dashboard with monitoring (Reminders, Due Today, Overdue, Delivered Today)
- Report generation (Served, Due, Next Due, Overdue, Sales Summary, Staff Performance)
- PDF export with landscape mode and text wrapping
- Authentication system with secure password hashing
- CSRF protection on all forms
- SQL injection prevention using prepared statements
- XSS prevention using htmlspecialchars() on all output
- Service interval stored in days throughout the system

## Future Enhancements (Out of Scope for v1.0)

- Customer login portal
- Staff login portal
- Customer-facing mobile application
- SMS, email, or WhatsApp notifications
- Payment integration
- Inventory management
- Route optimization for deliveries
- Customer self-service portal
