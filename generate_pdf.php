<?php
/**
 * ============================================
 * RoyalFamily Water Delivery System
 * PDF Report Generator
 * Version: 1.0
 * Description: Generates professional PDF reports using FPDF library
 * ============================================
 */

// Include configuration and authentication
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

// Check if user is logged in
requireLogin();
checkSessionTimeout();

// Include FPDF library
require_once 'fpdf/fpdf.php';

// Get parameters
$start_date = isset($_GET['start_date']) ? trim($mysqli->real_escape_string($_GET['start_date'])) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($mysqli->real_escape_string($_GET['end_date'])) : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Next-due PDFs are forward-looking and must never include dates before today.
if ($report_type === 'next_due') {
    $today = date('Y-m-d');
    if (empty($start_date) || $start_date < $today) {
        $start_date = $today;
    }
    if (empty($end_date) || $end_date < $start_date) {
        $end_date = date('Y-m-d', strtotime($start_date . ' +30 days'));
    }
}

// ============================================
// FETCH REPORT DATA
// ============================================

$served_customers = [];
$due_customers = [];
$overdue_customers = [];
$sales_summary = [];
$overall_totals = [];
$staff_performance = [];

// Served Customers
if ($report_type === 'all' || $report_type === 'served') {
    $served_sql = "
        SELECT 
            sr.service_date_time,
            sr.gallons_delivered,
            sr.price_per_gallon,
            sr.total_amount,
            sr.delivery_type,
            c.customer_code,
            c.full_name as customer_name,
            c.phone1,
            s.staff_code,
            s.full_name as staff_name,
            u.full_name as recorded_by_name
        FROM service_records sr
        INNER JOIN customers c ON sr.customer_id = c.id
        INNER JOIN staff s ON sr.staff_id = s.id
        INNER JOIN users u ON sr.recorded_by = u.id
        WHERE DATE(sr.service_date_time) BETWEEN '$start_date' AND '$end_date'
        ORDER BY sr.service_date_time ASC
    ";
    $served_result = $mysqli->query($served_sql);
    if ($served_result) {
        while ($row = $served_result->fetch_assoc()) {
            $served_customers[] = $row;
        }
    }
    
    // Sales Summary
    $sales_sql = "
        SELECT 
            COUNT(DISTINCT sr.customer_id) as total_customers_served,
            COUNT(sr.id) as total_deliveries,
            SUM(sr.gallons_delivered) as total_gallons,
            SUM(CASE WHEN COALESCE(sr.delivery_type, 'normal') = 'offer' THEN 0 ELSE sr.total_amount END) as total_sales,
            s.staff_code,
            s.full_name as staff_name
        FROM service_records sr
        INNER JOIN staff s ON sr.staff_id = s.id
        WHERE DATE(sr.service_date_time) BETWEEN '$start_date' AND '$end_date'
        GROUP BY s.staff_code, s.full_name
        ORDER BY total_sales DESC
    ";
    $sales_result = $mysqli->query($sales_sql);
    if ($sales_result) {
        while ($row = $sales_result->fetch_assoc()) {
            $sales_summary[] = $row;
        }
    }
    
    // Overall Totals
    $total_sql = "
        SELECT 
            COUNT(DISTINCT customer_id) as total_customers_served,
            COUNT(id) as total_deliveries,
            SUM(gallons_delivered) as total_gallons,
            SUM(CASE WHEN COALESCE(delivery_type, 'normal') = 'offer' THEN 0 ELSE total_amount END) as total_sales
        FROM service_records
        WHERE DATE(service_date_time) BETWEEN '$start_date' AND '$end_date'
    ";
    $total_result = $mysqli->query($total_sql);
    if ($total_result) {
        $overall_totals = $total_result->fetch_assoc();
    }
}

// Staff Performance
if ($report_type === 'all' || $report_type === 'staff_performance') {
    $staff_sql = "
        SELECT 
            s.staff_code,
            s.full_name as staff_name,
            s.phone,
            s.email,
            COUNT(sr.id) as total_deliveries,
            SUM(sr.gallons_delivered) as total_gallons,
            SUM(CASE WHEN COALESCE(sr.delivery_type, 'normal') = 'offer' THEN 0 ELSE sr.total_amount END) as total_sales,
            COUNT(DISTINCT sr.customer_id) as unique_customers
        FROM staff s
        LEFT JOIN service_records sr ON s.id = sr.staff_id 
            AND DATE(sr.service_date_time) BETWEEN '$start_date' AND '$end_date'
        WHERE s.status = 'active'
        GROUP BY s.id, s.staff_code, s.full_name, s.phone, s.email
        ORDER BY total_sales DESC
    ";
    $staff_result = $mysqli->query($staff_sql);
    if ($staff_result) {
        while ($row = $staff_result->fetch_assoc()) {
            $staff_performance[] = $row;
        }
    }
}

// Due Customers
if ($report_type === 'all' || $report_type === 'due' || $report_type === 'next_due') {
    $start_timestamp = strtotime($start_date . ' 00:00:00');
    $end_timestamp = strtotime($end_date . ' 23:59:59');
    $due_sql = "
        SELECT 
            c.customer_code,
            c.full_name,
            c.phone1,
            c.address,
            c.service_interval_days,
            c.service_interval_hours,
            (SELECT sr.service_date_time FROM service_records sr 
             WHERE sr.customer_id = c.id 
             ORDER BY sr.service_date_time DESC LIMIT 1) as last_service_date,
            (SELECT s.full_name FROM staff s 
             WHERE s.id = (SELECT sr.staff_id FROM service_records sr 
                           WHERE sr.customer_id = c.id 
                           ORDER BY sr.service_date_time DESC LIMIT 1)) as last_staff_name
        FROM customers c
        WHERE c.status = 'active'
    ";
    $due_result = $mysqli->query($due_sql);
    if ($due_result) {
        while ($row = $due_result->fetch_assoc()) {
            if ($row['last_service_date']) {
                $row['next_due_date'] = calculateNextDueDate($row['last_service_date'], getServiceIntervalDays($row));
                $next_due_timestamp = strtotime($row['next_due_date']);
                
                if ($next_due_timestamp >= $start_timestamp && $next_due_timestamp <= $end_timestamp) {
                    $due_customers[] = $row;
                }
            }
        }
        if (!empty($due_customers)) {
            usort($due_customers, function ($first, $second) {
                return strcmp($first['next_due_date'], $second['next_due_date']);
            });
        }
    }
}

// Overdue Customers
if ($report_type === 'all' || $report_type === 'overdue') {
    $current_datetime = date('Y-m-d H:i:s');
    $start_timestamp = strtotime($start_date . ' 00:00:00');
    $end_timestamp = strtotime($end_date . ' 23:59:59');
    $overdue_sql = "
        SELECT 
            c.customer_code,
            c.full_name,
            c.phone1,
            c.address,
            c.service_interval_days,
            c.service_interval_hours,
            (SELECT sr.service_date_time FROM service_records sr 
             WHERE sr.customer_id = c.id 
             ORDER BY sr.service_date_time DESC LIMIT 1) as last_service_date,
            (SELECT s.full_name FROM staff s 
             WHERE s.id = (SELECT sr.staff_id FROM service_records sr 
                           WHERE sr.customer_id = c.id 
                           ORDER BY sr.service_date_time DESC LIMIT 1)) as last_staff_name
        FROM customers c
        WHERE c.status = 'active'
    ";
    $overdue_result = $mysqli->query($overdue_sql);
    if ($overdue_result) {
        while ($row = $overdue_result->fetch_assoc()) {
            if ($row['last_service_date']) {
                $next_due = calculateNextDueDate($row['last_service_date'], getServiceIntervalDays($row));
                $next_due_timestamp = strtotime($next_due);
                $current_timestamp = strtotime($current_datetime);
                
                if ($current_timestamp > $next_due_timestamp && $next_due_timestamp >= $start_timestamp && $next_due_timestamp <= $end_timestamp) {
                    $overdue_seconds = $current_timestamp - $next_due_timestamp;
                    $overdue_hours = floor($overdue_seconds / 3600);
                    $overdue_minutes = floor(($overdue_seconds % 3600) / 60);
                    
                    if ($overdue_hours >= 24) {
                        $overdue_days = floor($overdue_hours / 24);
                        $overdue_hours_remaining = $overdue_hours % 24;
                        $overdue_duration = $overdue_days . ' day(s) ' . $overdue_hours_remaining . ' hour(s)';
                    } else {
                        $overdue_duration = $overdue_hours . ' hour(s) ' . $overdue_minutes . ' min(s)';
                    }
                    
                    $row['next_due_date'] = $next_due;
                    $row['overdue_duration'] = $overdue_duration;
                    $overdue_customers[] = $row;
                }
            }
        }
    }
}

// ============================================
// PDF GENERATION
// ============================================

// Disable error reporting to prevent warnings from breaking PDF output
error_reporting(0);
ini_set('display_errors', 0);

class RoyalFamilyPDF extends FPDF {
    public $activeTableHeaders = [];
    public $activeTableWidths = [];

    function TableHeader($headers, $widths) {
        $this->activeTableHeaders = $headers;
        $this->activeTableWidths = $widths;
        $this->SetFont('Arial', 'B', 8);
        $lineHeight = 4;
        $rowHeight = $this->GetRowHeight($headers, $widths, $lineHeight);
        $startX = $this->GetX();
        $startY = $this->GetY();
        foreach ($headers as $index => $header) {
            $x = $this->GetX();
            $this->MultiCell($widths[$index], $lineHeight, (string) $header, 1, 'C');
            $this->SetXY($x + $widths[$index], $startY);
        }
        $this->SetXY($startX, $startY + $rowHeight);
    }

    function WrappedRow($values, $widths, $lineHeight = 5) {
        $rowHeight = $this->GetRowHeight($values, $widths, $lineHeight);
        if ($this->GetY() + $rowHeight > $this->PageBreakTrigger) {
            $this->AddPage('L');
            $this->TableHeader($this->activeTableHeaders, $this->activeTableWidths);
        }
        $startX = $this->GetX();
        $startY = $this->GetY();
        foreach ($values as $index => $value) {
            $x = $this->GetX();
            $this->MultiCell($widths[$index], $lineHeight, (string) $value, 1, $index === 0 ? 'L' : 'C');
            $this->SetXY($x + $widths[$index], $startY);
        }
        $this->SetXY($startX, $startY + $rowHeight);
    }

    function GetRowHeight($values, $widths, $lineHeight) {
        $lineCount = 1;
        foreach ($values as $index => $value) {
            $text = trim((string) $value);
            $availableWidth = max(1, $widths[$index] - 2);
            $lines = 0;
            foreach (explode("\n", $text === '' ? ' ' : $text) as $paragraph) {
                $words = preg_split('/\s+/', trim($paragraph));
                $lineWidth = 0;
                $paragraphLines = 1;
                foreach ($words as $word) {
                    $wordWidth = $this->GetStringWidth($word);
                    if ($wordWidth > $availableWidth) {
                        $paragraphLines += (int) ceil($wordWidth / $availableWidth) - 1;
                        $lineWidth = fmod($wordWidth, $availableWidth);
                        continue;
                    }
                    if ($lineWidth > 0 && $lineWidth + $this->GetStringWidth(' ') + $wordWidth > $availableWidth) {
                        $paragraphLines++;
                        $lineWidth = $wordWidth;
                    } else {
                        $lineWidth += ($lineWidth > 0 ? $this->GetStringWidth(' ') : 0) + $wordWidth;
                    }
                }
                $paragraphLines = max($paragraphLines, (int) ceil($this->GetStringWidth($paragraph) / $availableWidth));
                $lines += $paragraphLines;
            }
            $lineCount = max($lineCount, $lines);
        }
        return $lineCount * $lineHeight;
    }

    // Page header
    function Header() {
        global $start_date, $end_date;
        
        // Logo placeholder (you can add actual logo)
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, APP_NAME, 0, 1, 'C');
        
        // Report title
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'Service and Sales Report', 0, 1, 'C');
        
        // Report period
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)), 0, 1, 'C');
        $this->Cell(0, 5, 'Generated: ' . date('d M Y H:i'), 0, 1, 'C');
        
        // Line
        $this->Ln(5);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Ln(5);
    }
    
    // Page footer
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // Section header
    function SectionHeader($title, $icon = '') {
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(102, 126, 234);
        $this->SetTextColor(255);
        $this->Cell(0, 8, $icon . ' ' . $title, 0, 1, 'L', true);
        $this->SetTextColor(0);
        $this->Ln(3);
    }
    
    // Summary card
    function SummaryCard($label, $value, $x, $y) {
        $this->SetXY($x, $y);
        $this->SetFillColor(102, 126, 234);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(45, 15, $value, 0, 0, 'C', true);
        $this->SetXY($x, $y + 15);
        $this->SetFont('Arial', '', 9);
        $this->Cell(45, 8, $label, 0, 0, 'C');
        $this->SetTextColor(0);
    }
}

if ($customer_id > 0) {
    $customer_stmt = $mysqli->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $customer_stmt->bind_param('i', $customer_id);
    $customer_stmt->execute();
    $customer_profile = $customer_stmt->get_result()->fetch_assoc();
    $customer_history = [];
    if ($customer_profile) {
        $history_stmt = $mysqli->prepare('SELECT sr.*, s.full_name AS staff_name FROM service_records sr INNER JOIN staff s ON sr.staff_id = s.id WHERE sr.customer_id = ? ORDER BY sr.service_date_time DESC');
        $history_stmt->bind_param('i', $customer_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        while ($row = $history_result->fetch_assoc()) {
            $customer_history[] = $row;
        }

        $pdf = new RoyalFamilyPDF();
        $pdf->AliasNbPages();
        $pdf->AddPage('L');
        $pdf->SectionHeader('Customer Report', '');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'Customer: ' . $customer_profile['full_name'], 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Contact: ' . $customer_profile['phone1'] . ($customer_profile['phone2'] ? ' / ' . $customer_profile['phone2'] : ''), 0, 1);
        if (!empty($customer_profile['address'])) {
            $pdf->Cell(0, 6, 'Address: ' . $customer_profile['address'], 0, 1);
        }
        $pdf->Ln(4);
        $widths = [32, 50, 22, 28, 22, 35, 50];
        $pdf->TableHeader(['Date', 'Customer', 'Gallons', 'Price/Gal', 'Type', 'Amount Paid', 'Staff'], $widths);
        $pdf->SetFont('Arial', '', 8);
        foreach ($customer_history as $record) {
            $type = deliveryTypeOf($record);
            $pdf->WrappedRow([
                date('d M Y H:i', strtotime($record['service_date_time'])),
                $customer_profile['full_name'],
                number_format((int) $record['gallons_delivered']),
                number_format((float) $record['price_per_gallon'], 2),
                $type === 'offer' ? 'Offer' : 'Normal',
                $type === 'offer' ? 'Offer / Free' : number_format(paidAmountForRecord($record), 2),
                $record['staff_name']
            ], $widths);
        }
        $pdf_filename = 'customer_report_' . preg_replace('/[^a-z0-9]+/i', '_', $customer_profile['full_name']) . '.pdf';
        $pdf->Output('D', $pdf_filename);
        exit();
    }
}

// Create PDF
$pdf = new RoyalFamilyPDF();
$pdf->AliasNbPages();
$pdf->AddPage('L');
$pdf->SetFont('Arial', '', 10);

// ============================================
// SALES SUMMARY SECTION
// ============================================
if ($report_type === 'all' || $report_type === 'served') {
    if (!empty($overall_totals)) {
        $pdf->SectionHeader('Sales Summary', '*'); // Graph icon
        
        // Summary cards
        $y = $pdf->GetY();
        $pdf->SummaryCard('Customers Served', number_format($overall_totals['total_customers_served']), 10, $y);
        $pdf->SummaryCard('Total Deliveries', number_format($overall_totals['total_deliveries']), 60, $y);
        $pdf->SummaryCard('Total Gallons', number_format((int) $overall_totals['total_gallons']), 110, $y);
        $pdf->SummaryCard('Total Sales (TZS)', number_format($overall_totals['total_sales']), 160, $y);
        
        $pdf->Ln(30);
        
        // Staff breakdown table
        if (!empty($sales_summary)) {
            $summary_widths = [30, 50, 25, 25, 25, 35];
            $summary_headers = ['Staff Code', 'Staff Name', 'Deliveries', 'Customers', 'Gallons', 'Sales (TZS)'];
            $pdf->TableHeader($summary_headers, $summary_widths);
            $pdf->SetFont('Arial', '', 8);
            foreach ($sales_summary as $staff) {
                $pdf->WrappedRow([
                    $staff['staff_code'],
                    $staff['staff_name'],
                    number_format($staff['total_deliveries']),
                    number_format($staff['total_customers_served']),
                    number_format((int) $staff['total_gallons']),
                    number_format($staff['total_sales'])
                ], $summary_widths);
            }
        }
    }
}

// ============================================
// SERVED CUSTOMERS SECTION
// ============================================
if ($report_type === 'all' || $report_type === 'served') {
    if (!empty($served_customers)) {
        $pdf->AddPage('L');
        $pdf->SectionHeader('Served Customers (' . count($served_customers) . ')', 'v'); // Checkmark icon
        
        // Table header
        $served_widths = [32, 50, 40, 20, 25, 30, 40];
        $served_headers = ['Date/Time', 'Customer', 'Staff', 'Gallons', 'Price/Gal', 'Amount Paid', 'Recorded By'];
        $pdf->TableHeader($served_headers, $served_widths);
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($served_customers as $record) {
            $type = deliveryTypeOf($record);
            $pdf->WrappedRow([
                date('d M H:i', strtotime($record['service_date_time'])),
                customerDisplayName($record),
                $record['staff_name'],
                number_format((int) $record['gallons_delivered']),
                number_format($record['price_per_gallon'], 2),
                $type === 'offer' ? 'Offer / Free' : number_format(paidAmountForRecord($record), 2),
                $record['recorded_by_name']
            ], $served_widths);
        }
    }
}

// ============================================
// DUE CUSTOMERS SECTION
// ============================================
if ($report_type === 'all' || $report_type === 'due' || $report_type === 'next_due') {
    if (!empty($due_customers)) {
        $pdf->AddPage('L');
        $pdf->SectionHeader(($report_type === 'next_due' ? 'Next Due' : 'Due Customers') . ' (' . count($due_customers) . ')', '#'); // Calendar icon
        
        // Table header
        $due_widths = [55, 32, 90, 35, 35];
        $due_headers = ['Customer', 'Phone', 'Address', 'Next Due', 'Last Staff'];
        $pdf->TableHeader($due_headers, $due_widths);
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($due_customers as $customer) {
            $pdf->WrappedRow([$customer['full_name'], $customer['phone1'], $customer['address'] ?: '-', date('d M H:i', strtotime($customer['next_due_date'])), $customer['last_staff_name'] ?: '-'], $due_widths);
        }
    }
}

// ============================================
// OVERDUE CUSTOMERS SECTION
// ============================================
if ($report_type === 'all' || $report_type === 'overdue') {
    if (!empty($overdue_customers)) {
        $pdf->AddPage('L');
        $pdf->SectionHeader('Overdue Customers (' . count($overdue_customers) . ')', '!'); // Warning icon
        
        // Table header
        $overdue_widths = [50, 30, 30, 35, 40];
        $overdue_headers = ['Customer', 'Phone', 'Was Due', 'Overdue', 'Last Staff'];
        $pdf->TableHeader($overdue_headers, $overdue_widths);
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($overdue_customers as $customer) {
            $pdf->WrappedRow([
                $customer['full_name'],
                $customer['phone1'],
                date('d M H:i', strtotime($customer['next_due_date'])),
                $customer['overdue_duration'],
                $customer['last_staff_name'] ?: '-'
            ], $overdue_widths);
        }
    }
}

// ============================================
// STAFF PERFORMANCE SECTION
// ============================================
if ($report_type === 'all' || $report_type === 'staff_performance') {
    if (!empty($staff_performance)) {
        $pdf->AddPage('L');
        $pdf->SectionHeader('Staff Performance (' . count($staff_performance) . ')', 'P'); // Person icon
        
        // Table header
        $staff_widths = [25, 45, 30, 35, 30, 30, 35, 30];
        $staff_headers = ['Code', 'Name', 'Phone', 'Email', 'Deliveries', 'Gallons', 'Sales (TZS)', 'Customers'];
        $pdf->TableHeader($staff_headers, $staff_widths);
        
        // Table data
        $pdf->SetFont('Arial', '', 8);
        foreach ($staff_performance as $staff) {
            $pdf->WrappedRow([
                $staff['staff_code'],
                $staff['staff_name'],
                $staff['phone'],
                $staff['email'] ?: '-',
                number_format($staff['total_deliveries']),
                number_format((int) $staff['total_gallons']),
                number_format($staff['total_sales']),
                number_format($staff['unique_customers'])
            ], $staff_widths);
        }
    }
}

// ============================================
// SALES SUMMARY (SEPARATE REPORT)
// ============================================
if ($report_type === 'sales_summary') {
    if (!empty($overall_totals)) {
        $pdf->SectionHeader('Sales Summary', '*');
        
        // Summary cards
        $y = $pdf->GetY();
        $pdf->SummaryCard('Customers Served', number_format($overall_totals['total_customers_served']), 10, $y);
        $pdf->SummaryCard('Total Deliveries', number_format($overall_totals['total_deliveries']), 60, $y);
        $pdf->SummaryCard('Total Gallons', number_format((int) $overall_totals['total_gallons']), 110, $y);
        $pdf->SummaryCard('Total Sales (TZS)', number_format($overall_totals['total_sales']), 160, $y);
        
        $pdf->Ln(30);
        
        // Staff breakdown table
        if (!empty($sales_summary)) {
            $summary_widths = [30, 50, 25, 25, 25, 35];
            $summary_headers = ['Staff Code', 'Staff Name', 'Deliveries', 'Customers', 'Gallons', 'Sales (TZS)'];
            $pdf->TableHeader($summary_headers, $summary_widths);
            $pdf->SetFont('Arial', '', 8);
            foreach ($sales_summary as $staff) {
                $pdf->WrappedRow([
                    $staff['staff_code'],
                    $staff['staff_name'],
                    number_format($staff['total_deliveries']),
                    number_format($staff['total_customers_served']),
                    number_format((int) $staff['total_gallons']),
                    number_format($staff['total_sales'])
                ], $summary_widths);
            }
        }
    }
}

// Output PDF
$pdf_filename = 'royalfamily_report_' . date('Y-m-d_His') . '.pdf';
$pdf->Output('D', $pdf_filename);
?>
