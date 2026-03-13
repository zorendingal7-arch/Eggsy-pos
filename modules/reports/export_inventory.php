<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

$period    = $_GET['period'] ?? 'day';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

if ($period === 'day') {
    $where_date = "DATE(sm.created_at) = '" . date('Y-m-d') . "'";
} elseif ($period === 'week') {
    $where_date = "sm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} else {
    $df = $conn->real_escape_string($date_from);
    $dt = $conn->real_escape_string($date_to);
    $where_date = "DATE(sm.created_at) BETWEEN '$df' AND '$dt'";
}

$movements = $conn->query("
    SELECT sm.id, i.name as item_name, i.unit, sm.quantity, sm.notes,
           u.full_name as added_by, sm.created_at
    FROM stock_movements sm
    JOIN inventory i ON sm.inventory_id = i.id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE sm.type = 'in' AND $where_date
    ORDER BY sm.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$total_qty = array_sum(array_column($movements, 'quantity'));

$period_label = $period === 'day' ? 'Today (' . date('F j, Y') . ')' :
                ($period === 'week' ? 'This Week' : $date_from . ' to ' . $date_to);

$filename = 'eggsy_inventory_report_' . date('Y-m-d') . '.xls';
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
        .label { color: #666; font-size: 11px; }
        .summary td { font-weight: bold; }
    </style>
</head>
<body>

<h1>Eggsy POS - Inventory Inbound Report</h1>
<p class="label">Period: <?php echo $period_label; ?></p>
<p class="label">Generated: <?php echo date('F j, Y h:i A'); ?></p>

<h2>Summary</h2>
<table class="summary" style="width: auto;">
    <tr>
        <td style="padding-right: 30px;">Total Restock Events</td>
        <td><?php echo count($movements); ?></td>
    </tr>
    <tr>
        <td>Total Units Added</td>
        <td><?php echo number_format($total_qty, 2); ?></td>
    </tr>
</table>

<h2>Stock Movements</h2>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Item Name</th>
            <th>Qty Added</th>
            <th>Unit</th>
            <th>Notes</th>
            <th>Added By</th>
            <th>Date & Time</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($movements)): ?>
        <tr><td colspan="7">No stock movements for this period.</td></tr>
        <?php else: ?>
        <?php foreach ($movements as $i => $m): ?>
        <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo htmlspecialchars($m['item_name']); ?></td>
            <td>+<?php echo number_format($m['quantity'], 2); ?></td>
            <td><?php echo htmlspecialchars($m['unit'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($m['notes'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($m['added_by'] ?? 'Unknown'); ?></td>
            <td><?php echo date('M d, Y h:i A', strtotime($m['created_at'])); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>