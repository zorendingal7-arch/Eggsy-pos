<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

$today = date('Y-m-d');

$orders = $conn->query("
    SELECT o.id, o.total, o.status, o.created_at,
           u.full_name as cashier_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE DATE(o.created_at) = '$today'
    ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$top_products = $conn->query("
    SELECT oi.name, SUM(oi.quantity) as units, SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status='completed' AND DATE(o.created_at)='$today'
    GROUP BY oi.name
    ORDER BY units DESC
")->fetch_all(MYSQLI_ASSOC);

$today_sales = $conn->query("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE status='completed' AND DATE(created_at)='$today'")->fetch_assoc()['v'];
$order_count = $conn->query("SELECT COUNT(*) as v FROM orders WHERE status='completed' AND DATE(created_at)='$today'")->fetch_assoc()['v'];

$filename = 'eggsy_report_' . date('Y-m-d') . '.xls';

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
        .summary td { font-weight: bold; font-size: 13px; }
        .label { color: #666; font-size: 11px; }
    </style>
</head>
<body>

<h1>Eggsy POS - Daily Report</h1>
<p class="label">Generated: <?php echo date('F j, Y h:i A'); ?></p>

<h2>Summary</h2>
<table class="summary" style="width: auto;">
    <tr>
        <td style="padding-right: 30px;">Total Sales</td>
        <td>₱<?php echo number_format($today_sales, 2); ?></td>
    </tr>
    <tr>
        <td>Completed Orders</td>
        <td><?php echo $order_count; ?></td>
    </tr>
    <tr>
        <td>Average Order Value</td>
        <td>₱<?php echo $order_count > 0 ? number_format($today_sales / $order_count, 2) : '0.00'; ?></td>
    </tr>
</table>

<h2>Orders Today</h2>
<table>
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Cashier</th>
            <th>Total</th>
            <th>Status</th>
            <th>Time</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($orders)): ?>
        <tr><td colspan="5">No orders today.</td></tr>
        <?php else: ?>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td>#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
            <td><?php echo htmlspecialchars($order['cashier_name'] ?? 'Unknown'); ?></td>
            <td>₱<?php echo number_format($order['total'], 2); ?></td>
            <td><?php echo ucfirst($order['status']); ?></td>
            <td><?php echo date('h:i A', strtotime($order['created_at'])); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<h2>Top Products Today</h2>
<table>
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Units Sold</th>
            <th>Revenue</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($top_products)): ?>
        <tr><td colspan="3">No product data today.</td></tr>
        <?php else: ?>
        <?php foreach ($top_products as $p): ?>
        <tr>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo $p['units']; ?></td>
            <td>₱<?php echo number_format($p['revenue'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>