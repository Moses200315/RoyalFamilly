<?php
/**
 * Excel-compatible sales export.
 */
require_once 'config/config.php';
require_once 'includes/auth_functions.php';

requireLogin();
checkSessionTimeout();

$start_date = trim($_GET['start_date'] ?? date('Y-m-01'));
$end_date = trim($_GET['end_date'] ?? date('Y-m-d'));
$customer_id = (int) ($_GET['customer_id'] ?? 0);

$start = DateTime::createFromFormat('!Y-m-d', $start_date);
$end = DateTime::createFromFormat('!Y-m-d', $end_date);
if (!$start || !$end || $start_date > $end_date) {
    http_response_code(400);
    exit('Invalid date range.');
}

$sql = "
    SELECT
        sr.service_date_time,
        c.customer_code,
        c.full_name AS customer_name,
        sr.gallons_delivered,
        sr.price_per_gallon,
        sr.total_amount,
        COALESCE(sr.delivery_type, 'normal') AS delivery_type,
        s.full_name AS staff_name,
        u.full_name AS recorded_by_name
    FROM service_records sr
    INNER JOIN customers c ON sr.customer_id = c.id
    INNER JOIN staff s ON sr.staff_id = s.id
    INNER JOIN users u ON sr.recorded_by = u.id
    WHERE DATE(sr.service_date_time) BETWEEN ? AND ?
";
$params = [$start_date, $end_date];
$types = 'ss';
if ($customer_id > 0) {
    $sql .= ' AND sr.customer_id = ?';
    $params[] = $customer_id;
    $types .= 'i';
}
$sql .= ' ORDER BY sr.service_date_time ASC';

$stmt = $mysqli->prepare($sql);
if ($customer_id > 0) {
    $stmt->bind_param('ssi', $start_date, $end_date, $customer_id);
} else {
    $stmt->bind_param('ss', $start_date, $end_date);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$total_sales = 0.0;
while ($row = $result->fetch_assoc()) {
    $is_offer = strtolower(trim($row['delivery_type'])) === 'offer';
    $amount = $is_offer ? 0.0 : (float) $row['total_amount'];
    $row['amount_paid'] = $amount;
    $row['payment_status'] = $is_offer ? 'Offer / Free' : 'Paid';
    $total_sales += $amount;
    $rows[] = $row;
}

$filename = 'sales_' . $start_date . '_to_' . $end_date . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Sales Export</title></head>
<body>
<table border="1">
    <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Customer Code</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Quantity (Gallons)</th>
            <th>Amount (<?php echo htmlspecialchars(CURRENCY); ?>)</th>
            <th>Payment Status</th>
            <th>Staff</th>
            <th>Recorded By</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['service_date_time']))); ?></td>
                <td><?php echo htmlspecialchars(date('H:i:s', strtotime($row['service_date_time']))); ?></td>
                <td><?php echo htmlspecialchars($row['customer_code']); ?></td>
                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                <td>Water delivery</td>
                <td><?php echo (int) $row['gallons_delivered']; ?></td>
                <td><?php echo number_format($row['amount_paid'], 2, '.', ''); ?></td>
                <td><?php echo htmlspecialchars($row['payment_status']); ?></td>
                <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                <td><?php echo htmlspecialchars($row['recorded_by_name']); ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="6">Total Sales</th>
            <th><?php echo number_format($total_sales, 2, '.', ''); ?></th>
            <th colspan="3"></th>
        </tr>
    </tbody>
</table>
</body>
</html>
