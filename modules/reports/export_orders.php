<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$period    = $_GET['period'] ?? 'day';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

if ($period === 'day') {
    $where_date = "DATE(o.created_at) = '" . date('Y-m-d') . "'";
} elseif ($period === 'week') {
    $where_date = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} else {
    $df = $conn->real_escape_string($date_from);
    $dt = $conn->real_escape_string($date_to);
    $where_date = "DATE(o.created_at) BETWEEN '$df' AND '$dt'";
}

$orders = $conn->query("
    SELECT o.id, o.total, o.created_at, u.full_name as cashier_name,
           COUNT(oi.id) as item_count, p.method as payment_method
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.status='completed' AND $where_date
    GROUP BY o.id, o.total, o.created_at, u.full_name, p.method
    ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$total_revenue = array_sum(array_column($orders, 'total'));
$period_label  = $period === 'day' ? 'Today (' . date('F j, Y') . ')' :
                 ($period === 'week' ? 'This Week' : $date_from . ' to ' . $date_to);

$filename = 'eggsy_orders_report_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<html>
<head>
    <meta charset="utf-8"/>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        h2 { font-size: 13px; font-weight: bold; margin-top: 20px; margin-bottom: 6px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        th { background-color: #f2c40d; font-weight: bold; padding: 6px 10px; border: 1px solid #ccc; text-align: left; }
        td { padding: 5px 10px; border: 1px solid #ddd; }
        tr:nth-child(even) td { background-color: #fafafa; }
        .summary td { font-weight: bold; }
    </style>
</head>
<body>
<h1>Eggsy POS - Orders Report</h1>
<p style="color:#666;font-size:11px;">Period: <?php echo $period_label; ?></p>
<p style="color:#666;font-size:11px;">Generated: <?php echo date('F j, Y h:i A'); ?></p>

<h2>Summary</h2>
<table class="summary" style="width:auto;">
    <tr><td style="padding-right:30px;">Total Orders</td><td><?php echo count($orders); ?></td></tr>
    <tr><td>Total Revenue</td><td>₱<?php echo number_format($total_revenue, 2); ?></td></tr>
    <tr><td>Avg. Order Value</td><td>₱<?php echo count($orders) > 0 ? number_format($total_revenue / count($orders), 2) : '0.00'; ?></td></tr>
</table>

<h2>Orders</h2>
<table>
    <thead>
        <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Time</th>
            <th>Cashier</th>
            <th>Items</th>
            <th>Payment</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($orders)): ?>
        <tr><td colspan="7">No orders for this period.</td></tr>
        <?php else: ?>
        <?php foreach ($orders as $ord): ?>
        <tr>
            <td>#<?php echo str_pad($ord['id'], 5, '0', STR_PAD_LEFT); ?></td>
            <td><?php echo date('M d, Y', strtotime($ord['created_at'])); ?></td>
            <td><?php echo date('h:i A', strtotime($ord['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($ord['cashier_name'] ?? 'N/A'); ?></td>
            <td><?php echo $ord['item_count']; ?></td>
            <td><?php echo htmlspecialchars($ord['payment_method'] ?? 'cash'); ?></td>
            <td>₱<?php echo number_format($ord['total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</body>
</html>