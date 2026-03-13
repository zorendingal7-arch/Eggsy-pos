<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : date('Y-m-d');

$store_name = $conn->query("SELECT setting_value FROM settings WHERE setting_key='store_name'")->fetch_assoc()['setting_value'] ?? 'Eggsy';
$store_address = $conn->query("SELECT setting_value FROM settings WHERE setting_key='store_address'")->fetch_assoc()['setting_value'] ?? '';
$store_contact = $conn->query("SELECT setting_value FROM settings WHERE setting_key='store_contact'")->fetch_assoc()['setting_value'] ?? '';

$summary = $conn->query("
    SELECT
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.total), 0) as total_sales,
        COALESCE(AVG(o.total), 0) as avg_order,
        COALESCE(MAX(o.total), 0) as highest_order,
        COALESCE(MIN(o.total), 0) as lowest_order
    FROM orders o
    WHERE o.status = 'completed' AND DATE(o.created_at) = '$date'
")->fetch_assoc();

$gross = floatval($summary['total_sales']);
$vat = $gross - ($gross / 1.12);
$net_sales = $gross / 1.12;

$cash_sales_query = $conn->query("
    SELECT COALESCE(SUM(o.total), 0) as cash_sales
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.status = 'completed' AND DATE(o.created_at) = '$date'
    AND p.method = 'cash'
");
$cash_sales = floatval($cash_sales_query->fetch_assoc()['cash_sales']);

$cash_session_query = $conn->query("
    SELECT COALESCE(SUM(starting_cash), 0) as total_starting
    FROM cash_sessions
    WHERE session_date = '$date'
");
$starting_cash = floatval($cash_session_query->fetch_assoc()['total_starting']);
$expected_cash = $starting_cash + $cash_sales;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actual_cash'])) {
    $actual_cash = floatval($_POST['actual_cash']);
    $check = $conn->query("SELECT id FROM cash_sessions WHERE session_date = '$date' LIMIT 1");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE cash_sessions SET actual_cash = $actual_cash WHERE session_date = '$date'");
    } else {
        $conn->query("INSERT INTO cash_sessions (user_id, starting_cash, actual_cash, session_date) VALUES (0, 0, $actual_cash, '$date')");
    }
    header("Location: /pos-system/modules/reports/end_of_day.php?date=$date");
    exit;
}

$actual_cash_query = $conn->query("
    SELECT actual_cash FROM cash_sessions
    WHERE session_date = '$date' AND actual_cash IS NOT NULL
    LIMIT 1
");
$saved_actual = $actual_cash_query->num_rows > 0 ? floatval($actual_cash_query->fetch_assoc()['actual_cash']) : null;
$difference = $saved_actual !== null ? $saved_actual - $expected_cash : null;

$orders_list = $conn->query("
    SELECT o.id, o.total, o.created_at, u.full_name as cashier_name,
           p.method as payment_method, p.cash_received, p.change_given,
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN payments p ON p.order_id = o.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.status = 'completed' AND DATE(o.created_at) = '$date'
    GROUP BY o.id, o.total, o.created_at, u.full_name, p.method, p.cash_received, p.change_given
    ORDER BY o.created_at ASC
")->fetch_all(MYSQLI_ASSOC);

$generated_at = date('F d, Y h:i A');
$display_date = date('F d, Y', strtotime($date));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>End-of-Day Summary - <?php echo $display_date; ?></title>
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css" />
    <link rel="stylesheet" href="/pos-system/assets/css/app.css" />
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        .no-export {
            display: block;
        }

        .export-hidden {
            display: none;
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen py-10 px-4">

    <!-- Toolbar -->
    <div class="max-w-4xl mx-auto mb-6 flex items-center justify-between no-export">
        <a href="/pos-system/dashboard.php"
            class="flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-slate-900 transition-colors">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Dashboard
        </a>
        <div class="flex items-center gap-3">
            <form method="GET" class="flex items-center gap-2">
                <label class="text-sm font-bold text-slate-600">Date:</label>
                <input type="date" name="date" value="<?php echo $date; ?>" max="<?php echo date('Y-m-d'); ?>"
                    class="px-3 py-2 text-sm border border-slate-200 rounded-lg bg-white font-medium focus:ring-2 focus:ring-primary/50" />
                <button type="submit"
                    class="px-4 py-2 bg-slate-800 text-white text-sm font-bold rounded-lg hover:bg-slate-700 transition-colors">
                    Load
                </button>
            </form>
            <button onclick="exportPDF()"
                class="flex items-center gap-2 px-5 py-2 bg-primary hover:bg-primary/90 text-zinc-900 text-sm font-bold rounded-lg transition-colors shadow-sm">
                <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                Export PDF
            </button>
        </div>
    </div>

    <!-- Report -->
    <div class="max-w-4xl mx-auto space-y-4" id="report">

        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-zinc-900 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <img src="/pos-system/assets/images/logo.png" alt="<?php echo htmlspecialchars($store_name); ?>"
                        class="size-12 object-contain rounded-xl bg-white/10 p-1" />
                    <div>
                        <h1 class="text-white text-lg font-black"><?php echo htmlspecialchars($store_name); ?></h1>
                        <?php if ($store_address): ?>
                            <p class="text-zinc-400 text-xs mt-0.5"><?php echo htmlspecialchars($store_address); ?></p>
                        <?php endif; ?>
                        <?php if ($store_contact): ?>
                            <p class="text-zinc-400 text-xs"><?php echo htmlspecialchars($store_contact); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-primary text-xs font-bold uppercase tracking-widest">End-of-Day Summary</p>
                    <p class="text-white text-base font-black mt-1"><?php echo $display_date; ?></p>
                    <p class="text-zinc-500 text-xs mt-1">Generated: <?php echo $generated_at; ?></p>
                </div>
            </div>
        </div>

        <!-- Sales Overview -->
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">
                Sales Overview
            </h2>
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-zinc-900 rounded-xl p-4">
                    <p class="text-zinc-400 text-xs font-bold uppercase tracking-wider mb-1">Gross Sales</p>
                    <p class="text-primary text-2xl font-black">₱<?php echo number_format($gross, 2); ?></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Net Sales (VAT excl.)</p>
                    <p class="text-slate-900 text-xl font-black">₱<?php echo number_format($net_sales, 2); ?></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">VAT Collected (12%)</p>
                    <p class="text-slate-900 text-xl font-black">₱<?php echo number_format($vat, 2); ?></p>
                </div>
            </div>
            <div class="grid grid-cols-4 gap-3">
                <div class="border border-slate-100 rounded-xl p-3">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Total Orders</p>
                    <p class="text-slate-900 text-xl font-black"><?php echo $summary['total_orders']; ?></p>
                </div>
                <div class="border border-slate-100 rounded-xl p-3">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Avg. Order</p>
                    <p class="text-slate-900 text-xl font-black">₱<?php echo number_format($summary['avg_order'], 2); ?>
                    </p>
                </div>
                <div class="border border-slate-100 rounded-xl p-3">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Highest Order</p>
                    <p class="text-slate-900 text-xl font-black">
                        ₱<?php echo number_format($summary['highest_order'], 2); ?></p>
                </div>
                <div class="border border-slate-100 rounded-xl p-3">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Lowest Order</p>
                    <p class="text-slate-900 text-xl font-black">
                        ₱<?php echo number_format($summary['lowest_order'], 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Cash Drawer Summary -->
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">
                Cash Drawer Summary
            </h2>
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Starting Cash</p>
                    <p class="text-slate-900 text-xl font-black">₱<?php echo number_format($starting_cash, 2); ?></p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Cash Sales</p>
                    <p class="text-slate-900 text-xl font-black">₱<?php echo number_format($cash_sales, 2); ?></p>
                </div>
                <div class="bg-zinc-900 rounded-xl p-4">
                    <p class="text-zinc-400 text-xs font-bold uppercase tracking-wider mb-1">Expected in Drawer</p>
                    <p class="text-primary text-xl font-black">₱<?php echo number_format($expected_cash, 2); ?></p>
                </div>
            </div>

            <?php if ($saved_actual !== null): ?>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="border border-slate-100 rounded-xl p-4">
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1">Actual Cash Count</p>
                        <p class="text-slate-900 text-xl font-black">₱<?php echo number_format($saved_actual, 2); ?></p>
                    </div>
                    <div
                        class="rounded-xl p-4 <?php echo $difference == 0 ? 'bg-emerald-50' : ($difference < 0 ? 'bg-red-50' : 'bg-blue-50'); ?>">
                        <p
                            class="text-xs font-bold uppercase tracking-wider mb-1 <?php echo $difference == 0 ? 'text-emerald-500' : ($difference < 0 ? 'text-red-500' : 'text-blue-500'); ?>">
                            Difference
                        </p>
                        <p
                            class="text-xl font-black <?php echo $difference == 0 ? 'text-emerald-600' : ($difference < 0 ? 'text-red-600' : 'text-blue-600'); ?>">
                            <?php echo ($difference >= 0 ? '+' : '') . '₱' . number_format($difference, 2); ?>
                        </p>
                        <p
                            class="text-xs font-bold mt-1 <?php echo $difference == 0 ? 'text-emerald-500' : ($difference < 0 ? 'text-red-500' : 'text-blue-500'); ?>">
                            <?php echo $difference == 0 ? 'Balanced' : ($difference < 0 ? 'Short' : 'Over'); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actual Cash Input Form - hidden during export -->
            <form method="POST" class="flex items-end gap-3 no-export" id="cash-form">
                <div class="flex-1 space-y-1">
                    <label class="text-sm font-bold text-slate-700">
                        <?php echo $saved_actual !== null ? 'Update Actual Cash Count' : 'Enter Actual Cash Count'; ?>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-bold">₱</span>
                        <input type="number" name="actual_cash" min="0" step="0.01"
                            value="<?php echo $saved_actual !== null ? $saved_actual : ''; ?>" placeholder="0.00"
                            class="w-full pl-8 pr-4 py-3 border-2 border-slate-200 rounded-xl font-bold focus:border-primary focus:ring-0 outline-none transition-colors" />
                    </div>
                </div>
                <button type="submit"
                    class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-xl transition-colors">
                    <?php echo $saved_actual !== null ? 'Update' : 'Save'; ?>
                </button>
            </form>
        </div>

        <!-- Orders Breakdown -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Orders Breakdown</h2>
                <p class="text-slate-400 text-xs mt-0.5"><?php echo count($orders_list); ?> completed orders for
                    <?php echo $display_date; ?></p>
            </div>
            <?php if (empty($orders_list)): ?>
                <div class="px-6 py-12 text-center text-slate-400">
                    <span class="material-symbols-outlined text-5xl block mb-3 text-slate-200">receipt_long</span>
                    No orders recorded for this date.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-5 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Order #</th>
                                <th class="px-5 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Time</th>
                                <th class="px-5 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Cashier</th>
                                <th class="px-5 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider text-center">
                                    Items</th>
                                <th class="px-5 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Payment</th>
                                <th class="px-5 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider text-right">
                                    Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($orders_list as $ord): ?>
                                <tr>
                                    <td class="px-5 py-2 text-sm font-black text-slate-900">
                                        #<?php echo str_pad($ord['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td class="px-5 py-2 text-sm text-slate-500">
                                        <?php echo date('h:i A', strtotime($ord['created_at'])); ?>
                                    </td>
                                    <td class="px-5 py-2 text-sm font-semibold text-slate-700">
                                        <?php echo htmlspecialchars($ord['cashier_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-5 py-2 text-center">
                                        <span class="text-xs font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded-lg">
                                            <?php echo $ord['item_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-2 text-sm text-slate-500 capitalize">
                                        <?php echo htmlspecialchars($ord['payment_method'] ?? 'cash'); ?>
                                    </td>
                                    <td class="px-5 py-2 text-right font-black text-slate-900">
                                        ₱<?php echo number_format($ord['total'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-zinc-900">
                                <td colspan="5" class="px-5 py-3 text-sm font-black text-zinc-400 uppercase tracking-wider">
                                    Total</td>
                                <td class="px-5 py-3 text-right text-lg font-black text-primary">
                                    ₱<?php echo number_format($gross, 2); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="bg-white rounded-2xl shadow-sm px-6 py-4 flex items-center justify-between">
            <p class="text-xs text-slate-400">This is a system-generated report. No signature required.</p>
            <p class="text-xs font-bold text-slate-400"><?php echo htmlspecialchars($store_name); ?> POS System</p>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function exportPDF() {
            document.getElementById('cash-form').style.display = 'none';
            const element = document.getElementById('report');
            const filename = 'End-of-Day-<?php echo $date; ?>.pdf';
            const opt = {
                margin: 8,
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save().then(() => {
                document.getElementById('cash-form').style.display = 'flex';
            });
        }
    </script>

</body>

</html>