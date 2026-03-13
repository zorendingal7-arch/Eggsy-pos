<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$period = $_GET['period'] ?? 'day';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

if ($period === 'day') {
    $where_date = "DATE(o.created_at) = '" . date('Y-m-d') . "'";
    $prev_where = "DATE(o.created_at) = '" . date('Y-m-d', strtotime('-1 day')) . "'";
    $inv_where_date = "DATE(sm.created_at) = '" . date('Y-m-d') . "'";
} elseif ($period === 'week') {
    $where_date = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $prev_where = "o.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $inv_where_date = "sm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} else {
    $df = $conn->real_escape_string($date_from);
    $dt = $conn->real_escape_string($date_to);
    $where_date = "DATE(o.created_at) BETWEEN '$df' AND '$dt'";
    $prev_where = "1=0";
    $inv_where_date = "DATE(sm.created_at) BETWEEN '$df' AND '$dt'";
}

$full_where = "WHERE o.status='completed' AND $where_date";
$prev_full = "WHERE o.status='completed' AND $prev_where";

$revenue = $conn->query("SELECT COALESCE(SUM(total),0) as v FROM orders o $full_where")->fetch_assoc()['v'];
$prev_revenue = $conn->query("SELECT COALESCE(SUM(total),0) as v FROM orders o $prev_full")->fetch_assoc()['v'];
$order_count = $conn->query("SELECT COUNT(*) as v FROM orders o $full_where")->fetch_assoc()['v'];
$avg_order = $order_count > 0 ? $revenue / $order_count : 0;
$revenue_change = $prev_revenue > 0 ? round((($revenue - $prev_revenue) / $prev_revenue) * 100, 1) : 0;

$top_product_row = $conn->query("
    SELECT oi.name, SUM(oi.quantity) as units
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    $full_where
    GROUP BY oi.name
    ORDER BY units DESC
    LIMIT 1
")->fetch_assoc();
$top_product = $top_product_row['name'] ?? 'N/A';
$top_product_units = $top_product_row['units'] ?? 0;

$trend_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $row = $conn->query("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE status='completed' AND DATE(created_at)='$d'")->fetch_assoc();
    $trend_data[] = ['date' => $d, 'day' => date('D', strtotime($d)), 'total' => (float) $row['v']];
}
$max_trend = max(array_column($trend_data, 'total') ?: [1]);

$categories_sales = $conn->query("
    SELECT c.name, SUM(oi.quantity) as units
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN menu_items m ON oi.menu_item_id = m.id
    JOIN categories c ON m.category_id = c.id
    WHERE o.status='completed' AND $where_date
    GROUP BY c.name
    ORDER BY units DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$max_cat_units = !empty($categories_sales) ? max(array_column($categories_sales, 'units')) : 1;

$top_products = $conn->query("
    SELECT oi.name, SUM(oi.quantity) as units, SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    $full_where
    GROUP BY oi.name
    ORDER BY units DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$orders_list = $conn->query("
    SELECT o.id, o.total, o.created_at, u.full_name as cashier_name,
           COUNT(oi.id) as item_count,
           p.method as payment_method, p.cash_received, p.change_given
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN payments p ON p.order_id = o.id
    $full_where
    GROUP BY o.id, o.total, o.created_at, u.full_name, p.method, p.cash_received, p.change_given
    ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$orders_per_page = 5;
$total_orders = count($orders_list);
$total_pages = max(1, ceil($total_orders / $orders_per_page));
$current_page = max(1, min((int) ($_GET['orders_page'] ?? 1), $total_pages));
$offset = ($current_page - 1) * $orders_per_page;
$paged_orders = array_slice($orders_list, $offset, $orders_per_page);
$showing_from = $total_orders > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $orders_per_page, $total_orders);

$inv_movements = $conn->query("
    SELECT sm.id, i.name as item_name, i.unit, sm.quantity, sm.notes,
           u.full_name as added_by, sm.created_at
    FROM stock_movements sm
    JOIN inventory i ON sm.inventory_id = i.id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE sm.type = 'in' AND $inv_where_date
    ORDER BY sm.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
$inv_total_movements = count($inv_movements);
$inv_total_quantity = array_sum(array_column($inv_movements, 'quantity'));

$current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$inventory_pages = [
    '/pos-system/modules/inventory/index.php',
    '/pos-system/modules/inventory/add.php',
    '/pos-system/modules/inventory/add_stock.php',
    '/pos-system/modules/inventory/add_product.php',
    '/pos-system/modules/inventory/edit.php',
    '/pos-system/modules/inventory/edit_product.php',
    '/pos-system/modules/inventory/products.php',
];
$inv_open = in_array($current, $inventory_pages);
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Reports & Analytics - Eggsy Admin</title>
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css" />
    <link rel="stylesheet" href="/pos-system/assets/css/app.css" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <aside
            class="w-64 flex-shrink-0 bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-zinc-800 flex flex-col">
            <div class="p-6 flex items-center gap-3">
                <img src="/pos-system/assets/images/logo.png" alt="Eggsy" class="size-10 rounded-xl object-cover" />
                <div>
                    <h1 class="text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-white">Eggsy
                        Admin</h1>

                </div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="text-sm font-medium">Overview</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/modules/pos/index.php">
                    <span class="material-symbols-outlined">shopping_bag</span>
                    <span class="text-sm font-medium">Orders</span>
                </a>
                <div>
                    <button id="inv-toggle"
                        class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg <?php echo $inv_open ? 'bg-primary/10 text-primary dark:bg-primary/20' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'; ?> transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">inventory_2</span>
                            <span class="text-sm font-semibold">Inventory</span>
                        </div>
                        <span
                            class="material-symbols-outlined text-sm transition-transform <?php echo $inv_open ? 'rotate-180' : ''; ?>"
                            id="inv-arrow">expand_more</span>
                    </button>
                    <div id="inv-submenu"
                        class="mt-1 ml-4 border-l-2 border-slate-100 dark:border-zinc-700 pl-4 space-y-1 <?php echo $inv_open ? '' : 'hidden'; ?>">
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                            href="/pos-system/modules/inventory/index.php">
                            <span class="material-symbols-outlined text-base">list</span>
                            <span class="text-sm font-medium">All Ingredients</span>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                            href="/pos-system/modules/inventory/products.php">
                            <span class="material-symbols-outlined text-base">lunch_dining</span>
                            <span class="text-sm font-medium">All Products</span>
                        </a>
                    </div>
                </div>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/modules/users/index.php">
                    <span class="material-symbols-outlined">group</span>
                    <span class="text-sm font-medium">Staff</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary dark:bg-primary/20 transition-colors"
                    href="/pos-system/modules/reports/index.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    <span class="text-sm font-semibold">Reports</span>
                </a>
            </nav>
            <div class="p-4 border-t border-slate-200 dark:border-zinc-800">
                <a class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/modules/settings/index.php">
                    <span class="material-symbols-outlined">settings</span>
                    <span class="text-sm font-medium">Settings</span>
                </a>
                <button onclick="document.getElementById('logout-modal').classList.remove('hidden')"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="text-sm font-medium">Logout</span>
                </button>
            </div>
        </aside>

        <!-- Main -->
        <main class="flex-1 flex flex-col overflow-hidden">
            <header
                class="h-16 flex items-center justify-end px-8 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800">
                <div class="flex items-center gap-4">
                    <button class="p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-lg">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                    <div class="h-8 w-px bg-slate-200 dark:bg-zinc-800 mx-2"></div>
                    <div class="flex items-center gap-3 pl-2">
                        <div class="text-right">
                            <p class="text-sm font-semibold leading-none text-slate-900 dark:text-white">
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                            </p>
                            <p class="text-xs text-slate-500 mt-1"><?php echo ucfirst($_SESSION['role']); ?></p>
                        </div>
                        <div
                            class="size-10 rounded-full bg-primary flex items-center justify-center border-2 border-primary/20">
                            <span class="material-symbols-outlined text-zinc-900">person</span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8 space-y-8">

                <!-- Page Title + Period Filter -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Reports & Analytics
                        </h2>
                        <p class="text-slate-500 dark:text-zinc-400 mt-1">Comprehensive performance overview for Eggsy.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="GET" id="period-form">
                            <input type="hidden" name="period" id="period-input" value="<?php echo $period; ?>">
                            <input type="hidden" name="date_from" id="hidden-date-from"
                                value="<?php echo $date_from; ?>">
                            <input type="hidden" name="date_to" id="hidden-date-to" value="<?php echo $date_to; ?>">
                            <div
                                class="flex items-center gap-1 bg-white dark:bg-zinc-900 p-1 border border-slate-200 dark:border-zinc-700 rounded-xl shadow-sm">
                                <button type="button" onclick="submitPeriod('day')"
                                    class="px-4 py-2 text-sm font-bold rounded-lg transition-colors <?php echo $period === 'day' ? 'bg-primary text-zinc-900' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800'; ?>">
                                    Today
                                </button>
                                <button type="button" onclick="submitPeriod('week')"
                                    class="px-4 py-2 text-sm font-bold rounded-lg transition-colors <?php echo $period === 'week' ? 'bg-primary text-zinc-900' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800'; ?>">
                                    This Week
                                </button>
                                <button type="button"
                                    onclick="document.getElementById('calendar-modal').classList.remove('hidden')"
                                    class="flex items-center gap-2 px-4 py-2 text-sm font-bold rounded-lg transition-colors <?php echo $period === 'custom' ? 'bg-primary text-zinc-900' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800'; ?>">
                                    <?php if ($period === 'custom'): ?>
                                        <?php echo date('M d', strtotime($date_from)); ?> -
                                        <?php echo date('M d', strtotime($date_to)); ?>
                                    <?php else: ?>
                                        Custom
                                    <?php endif; ?>
                                    <span class="material-symbols-outlined text-base">calendar_today</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <span
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Total
                                Revenue</span>
                            <?php if ($revenue_change >= 0): ?>
                                <span
                                    class="text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 rounded text-xs font-bold">+<?php echo $revenue_change; ?>%</span>
                            <?php else: ?>
                                <span
                                    class="text-red-500 bg-red-50 dark:bg-red-900/20 px-2 py-0.5 rounded text-xs font-bold"><?php echo $revenue_change; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white">
                            ₱<?php echo number_format($revenue, 2); ?></div>
                        <p class="text-xs text-slate-400 mt-2"><?php echo $order_count; ?> orders total</p>
                    </div>
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <span
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Avg.
                                Order Value</span>
                            <span class="text-primary bg-primary/10 px-2 py-0.5 rounded text-xs font-bold">Per
                                Order</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white">
                            ₱<?php echo number_format($avg_order, 2); ?></div>
                        <p class="text-xs text-slate-400 mt-2"><?php echo $order_count; ?> completed orders</p>
                    </div>
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <span
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Top
                                Product</span>
                            <span
                                class="text-primary bg-primary/10 px-2 py-0.5 rounded text-xs font-bold">Trending</span>
                        </div>
                        <div class="text-xl font-black text-slate-900 dark:text-white leading-tight">
                            <?php echo htmlspecialchars($top_product); ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-2"><?php echo $top_product_units; ?> units sold</p>
                    </div>
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <span
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Total
                                Orders</span>
                            <span
                                class="text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-0.5 rounded text-xs font-bold">Completed</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white"><?php echo $order_count; ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-2">₱<?php echo number_format($revenue, 2); ?> total revenue
                        </p>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div
                        class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Sales Trend</h3>
                                <p class="text-sm text-slate-500 dark:text-zinc-400">Last 7 days revenue</p>
                            </div>
                            <span class="text-xs font-bold text-primary bg-primary/10 px-3 py-1.5 rounded-lg">7-Day
                                View</span>
                        </div>
                        <div class="relative h-48">
                            <?php if ($max_trend > 0): ?>
                                <svg class="w-full h-full" viewBox="0 0 400 150" preserveAspectRatio="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <?php
                                    $points = [];
                                    foreach ($trend_data as $i => $d) {
                                        $x = ($i / 6) * 400;
                                        $y = 150 - (($d['total'] / $max_trend) * 130);
                                        $points[] = "$x,$y";
                                    }
                                    $line = implode(' ', $points);
                                    $area = "0,150 " . $line . " 400,150";
                                    ?>
                                    <defs>
                                        <linearGradient id="trend_grad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#f2c40d" stop-opacity="0.3" />
                                            <stop offset="100%" stop-color="#f2c40d" stop-opacity="0" />
                                        </linearGradient>
                                    </defs>
                                    <polygon points="<?php echo $area; ?>" fill="url(#trend_grad)" />
                                    <polyline points="<?php echo $line; ?>" fill="none" stroke="#f2c40d" stroke-width="3"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <?php foreach ($trend_data as $i => $d): ?>
                                        <?php $x = ($i / 6) * 400;
                                        $y = 150 - (($d['total'] / $max_trend) * 130); ?>
                                        <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="4" fill="#f2c40d"
                                            stroke="white" stroke-width="2" />
                                    <?php endforeach; ?>
                                </svg>
                            <?php else: ?>
                                <div class="flex items-center justify-center h-full text-slate-300 dark:text-zinc-600">
                                    <p class="text-sm font-semibold">No sales data for this period.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex justify-between mt-4">
                            <?php foreach ($trend_data as $d): ?>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase"><?php echo $d['day']; ?></p>
                                    <?php if ($d['total'] > 0): ?>
                                        <p class="text-[9px] text-primary font-bold">
                                            ₱<?php echo number_format($d['total'] / 1000, 1); ?>k</p>
                                    <?php else: ?>
                                        <p class="text-[9px] text-slate-300">—</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Best Selling Categories
                                </h3>
                                <p class="text-sm text-slate-500 dark:text-zinc-400">Order volume by category</p>
                            </div>
                        </div>
                        <?php if (empty($categories_sales)): ?>
                            <div class="flex items-center justify-center h-40 text-slate-300 dark:text-zinc-600">
                                <p class="text-sm font-semibold">No category data for this period.</p>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col gap-6">
                                <?php
                                $opacities = ['100', '80', '60', '40', '25'];
                                foreach ($categories_sales as $i => $cat):
                                    $pct = $max_cat_units > 0 ? round(($cat['units'] / $max_cat_units) * 100) : 0;
                                    $opacity = $opacities[$i] ?? '20';
                                    ?>
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm font-bold">
                                            <span
                                                class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($cat['name']); ?></span>
                                            <span class="text-slate-500"><?php echo $cat['units']; ?> orders</span>
                                        </div>
                                        <div class="w-full bg-slate-100 dark:bg-zinc-800 h-3 rounded-full overflow-hidden">
                                            <div class="bg-primary h-full rounded-full transition-all"
                                                style="width: <?php echo $pct; ?>%; opacity: <?php echo $opacity; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Products Table -->
                <div
                    class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Top Products</h3>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Best performing items this period
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-800 border-b border-slate-100 dark:border-zinc-700">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Rank
                                    </th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Product Name</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Units Sold</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                        Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <?php if (empty($top_products)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-slate-400">No sales data for this
                                            period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_products as $i => $p): ?>
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800 transition-colors">
                                            <td class="px-6 py-4">
                                                <?php if ($i === 0): ?>
                                                    <span
                                                        class="size-7 rounded-full bg-primary flex items-center justify-center text-xs font-black text-zinc-900"><?php echo $i + 1; ?></span>
                                                <?php else: ?>
                                                    <span
                                                        class="size-7 rounded-full bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-xs font-bold text-slate-500"><?php echo $i + 1; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                                <?php echo htmlspecialchars($p['name']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center font-bold text-slate-700 dark:text-slate-300">
                                                <?php echo $p['units']; ?>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-primary">
                                                ₱<?php echo number_format($p['revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Orders Report -->
                <div
                    class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Orders</h3>
                            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">All completed orders for this
                                period</p>
                        </div>
                        <a href="/pos-system/modules/reports/export_orders.php?period=<?php echo $period; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"
                            class="bg-primary hover:bg-primary/90 text-zinc-900 px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
                            <span class="material-symbols-outlined text-base">download</span>
                            Export
                        </a>
                    </div>
                    <div class="grid grid-cols-3 gap-4 p-6 border-b border-slate-100 dark:border-zinc-800">
                        <div class="bg-slate-50 dark:bg-zinc-800 rounded-xl p-4">
                            <p
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">
                                Total Orders</p>
                            <h4 class="text-2xl font-black text-slate-900 dark:text-white"><?php echo $total_orders; ?>
                            </h4>
                        </div>
                        <div class="bg-primary/10 rounded-xl p-4">
                            <p
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">
                                Total Revenue</p>
                            <h4 class="text-2xl font-black text-primary">₱<?php echo number_format($revenue, 2); ?></h4>
                        </div>
                        <div class="bg-slate-50 dark:bg-zinc-800 rounded-xl p-4">
                            <p
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">
                                Avg. Order</p>
                            <h4 class="text-2xl font-black text-slate-900 dark:text-white">
                                ₱<?php echo number_format($avg_order, 2); ?></h4>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-800 border-b border-slate-100 dark:border-zinc-700">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Order #</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Date
                                        & Time</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Cashier</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Items</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Payment</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                        Total</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <?php if (empty($paged_orders)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">No orders for this
                                            period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($paged_orders as $ord): ?>
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800 transition-colors">
                                            <td class="px-6 py-4 font-black text-slate-900 dark:text-white">
                                                #<?php echo str_pad($ord['id'], 5, '0', STR_PAD_LEFT); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-zinc-400">
                                                <?php echo date('M d, Y', strtotime($ord['created_at'])); ?>
                                                <span
                                                    class="block text-xs text-slate-400"><?php echo date('h:i A', strtotime($ord['created_at'])); ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-zinc-300">
                                                <?php echo htmlspecialchars($ord['cashier_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-bold px-2 py-1 rounded-lg">
                                                    <?php echo $ord['item_count']; ?> items
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-zinc-400 capitalize">
                                                <?php echo htmlspecialchars($ord['payment_method'] ?? 'cash'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right font-black text-primary">
                                                ₱<?php echo number_format($ord['total'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <a href="/pos-system/modules/pos/receipt.php?order_id=<?php echo $ord['id']; ?>"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:text-primary/80 bg-primary/10 hover:bg-primary/20 px-3 py-1.5 rounded-lg transition-colors">
                                                    <span class="material-symbols-outlined text-sm">receipt</span>
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div
                        class="px-6 py-4 flex items-center justify-between border-t border-slate-100 dark:border-zinc-800">
                        <p class="text-sm text-slate-500 dark:text-zinc-400">
                            Showing <span
                                class="font-bold text-slate-900 dark:text-white"><?php echo $showing_from; ?>-<?php echo $showing_to; ?></span>
                            of <span
                                class="font-bold text-slate-900 dark:text-white"><?php echo $total_orders; ?></span>
                            orders
                        </p>
                        <div class="flex items-center gap-2">
                            <?php
                            $prev_url = '?' . http_build_query(array_merge($_GET, ['orders_page' => $current_page - 1]));
                            $next_url = '?' . http_build_query(array_merge($_GET, ['orders_page' => $current_page + 1]));
                            ?>
                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo $prev_url; ?>"
                                    class="flex items-center gap-1 px-4 py-2 text-sm font-bold text-slate-600 dark:text-zinc-400 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-base">chevron_left</span>
                                    Previous
                                </a>
                            <?php else: ?>
                                <span
                                    class="flex items-center gap-1 px-4 py-2 text-sm font-bold text-slate-300 dark:text-zinc-600 bg-slate-50 dark:bg-zinc-800/50 rounded-lg cursor-not-allowed">
                                    <span class="material-symbols-outlined text-base">chevron_left</span>
                                    Previous
                                </span>
                            <?php endif; ?>
                            <span
                                class="px-4 py-2 text-sm font-black text-zinc-900 dark:text-white bg-primary rounded-lg">
                                <?php echo $current_page; ?> / <?php echo $total_pages; ?>
                            </span>
                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo $next_url; ?>"
                                    class="flex items-center gap-1 px-4 py-2 text-sm font-bold text-slate-600 dark:text-zinc-400 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-lg transition-colors">
                                    Next
                                    <span class="material-symbols-outlined text-base">chevron_right</span>
                                </a>
                            <?php else: ?>
                                <span
                                    class="flex items-center gap-1 px-4 py-2 text-sm font-bold text-slate-300 dark:text-zinc-600 bg-slate-50 dark:bg-zinc-800/50 rounded-lg cursor-not-allowed">
                                    Next
                                    <span class="material-symbols-outlined text-base">chevron_right</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Inventory Inbound Report -->
                <div
                    class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden pb-8">
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Inventory Inbound</h3>
                            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Stock added during this period
                            </p>
                        </div>
                        <a href="/pos-system/modules/reports/export_inventory.php?period=<?php echo $period; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"
                            class="bg-primary hover:bg-primary/90 text-zinc-900 px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
                            <span class="material-symbols-outlined text-base">download</span>
                            Export
                        </a>
                    </div>
                    <div class="grid grid-cols-2 gap-4 p-6 border-b border-slate-100 dark:border-zinc-800">
                        <div class="bg-slate-50 dark:bg-zinc-800 rounded-xl p-4">
                            <p
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">
                                Total Restock Events</p>
                            <h4 class="text-2xl font-black text-slate-900 dark:text-white">
                                <?php echo $inv_total_movements; ?>
                            </h4>
                        </div>
                        <div class="bg-primary/10 rounded-xl p-4">
                            <p
                                class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">
                                Total Units Added</p>
                            <h4 class="text-2xl font-black text-primary">
                                <?php echo number_format($inv_total_quantity, 2); ?>
                            </h4>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-800 border-b border-slate-100 dark:border-zinc-700">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Item
                                    </th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                        Qty Added</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Notes</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Added By</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Date
                                        & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <?php if (empty($inv_movements)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">No stock movements for
                                            this period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($inv_movements as $m): ?>
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800 transition-colors">
                                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                                <?php echo htmlspecialchars($m['item_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="font-black text-emerald-600">+<?php echo number_format($m['quantity'], 2); ?></span>
                                                <span
                                                    class="text-xs text-slate-400 ml-1"><?php echo htmlspecialchars($m['unit'] ?? ''); ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-zinc-400">
                                                <?php echo htmlspecialchars($m['notes'] ?: '—'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-zinc-400">
                                                <?php echo htmlspecialchars($m['added_by'] ?? 'Unknown'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-zinc-400">
                                                <?php echo date('M d, Y h:i A', strtotime($m['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Calendar Modal -->
    <div id="calendar-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div
            class="bg-white dark:bg-zinc-900 w-full max-w-[840px] rounded-2xl shadow-2xl overflow-hidden flex flex-col border border-slate-100 dark:border-zinc-800">
            <div class="px-8 pt-6 pb-2 flex justify-between items-center">
                <h2 class="text-slate-900 dark:text-white text-2xl font-black tracking-tight">Select Date Range</h2>
                <button onclick="document.getElementById('calendar-modal').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition-colors p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="flex flex-col lg:flex-row gap-8 p-8">
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-4 px-2">
                        <button onclick="prevMonth()"
                            class="p-2 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                            <span
                                class="material-symbols-outlined text-slate-600 dark:text-zinc-300">chevron_left</span>
                        </button>
                        <p id="left-month-label" class="text-slate-900 dark:text-white text-lg font-bold"></p>
                        <div class="w-10 h-10"></div>
                    </div>
                    <div class="calendar-grid text-center mb-2">
                        <?php foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $d): ?>
                            <span
                                class="text-slate-400 dark:text-zinc-500 text-xs font-bold uppercase tracking-widest py-2"><?php echo $d; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div id="left-calendar" class="calendar-grid gap-y-1"></div>
                </div>
                <div class="hidden lg:block w-px bg-slate-100 dark:bg-zinc-800 self-stretch"></div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-4 px-2">
                        <div class="w-10 h-10"></div>
                        <p id="right-month-label" class="text-slate-900 dark:text-white text-lg font-bold"></p>
                        <button onclick="nextMonth()"
                            class="p-2 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                            <span
                                class="material-symbols-outlined text-slate-600 dark:text-zinc-300">chevron_right</span>
                        </button>
                    </div>
                    <div class="calendar-grid text-center mb-2">
                        <?php foreach (['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $d): ?>
                            <span
                                class="text-slate-400 dark:text-zinc-500 text-xs font-bold uppercase tracking-widest py-2"><?php echo $d; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div id="right-calendar" class="calendar-grid gap-y-1"></div>
                </div>
            </div>
            <div class="px-8 py-4 bg-slate-50 dark:bg-zinc-800/50 border-t border-slate-100 dark:border-zinc-800">
                <div class="flex flex-col md:flex-row gap-6 max-w-xl">
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-bold text-slate-700 dark:text-zinc-300 ml-1">Start Date</label>
                        <div class="relative group">
                            <span
                                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">calendar_today</span>
                            <input id="display-date-from" type="text" readonly placeholder="Select start date"
                                class="w-full pl-10 pr-4 py-3 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg font-medium text-slate-800 dark:text-white cursor-pointer" />
                        </div>
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-bold text-slate-700 dark:text-zinc-300 ml-1">End Date</label>
                        <div class="relative group">
                            <span
                                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">calendar_month</span>
                            <input id="display-date-to" type="text" readonly placeholder="Select end date"
                                class="w-full pl-10 pr-4 py-3 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg font-medium text-slate-800 dark:text-white cursor-pointer" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-8 py-5 flex justify-end gap-3 border-t border-slate-100 dark:border-zinc-800">
                <button onclick="document.getElementById('calendar-modal').classList.add('hidden')"
                    class="px-6 py-2.5 text-sm font-bold text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                    Cancel
                </button>
                <button onclick="applyCustomRange()"
                    class="px-8 py-2.5 text-sm font-black bg-primary text-zinc-900 hover:bg-primary/90 rounded-lg transition-all active:scale-95 shadow-sm">
                    Apply Range
                </button>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logout-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-sm p-8 space-y-6">
            <div class="flex flex-col items-center text-center gap-3">
                <div class="size-16 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-500 text-3xl">logout</span>
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Logout</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm">Are you sure you want to log out?</p>
            </div>
            <div class="flex gap-3">
                <button onclick="document.getElementById('logout-modal').classList.add('hidden')"
                    class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-sm font-bold text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">
                    Cancel
                </button>
                <a href="/pos-system/modules/auth/logout.php"
                    class="flex-1 py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white font-bold text-sm text-center transition-colors">
                    Yes, Logout
                </a>
            </div>
        </div>
    </div>


    <div class="fixed bottom-6 right-6 z-50">
        <button
            class="size-12 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl flex items-center justify-center transition-transform hover:scale-110 active:scale-95"
            onclick="toggleDark()">
            <span class="material-symbols-outlined dark:hidden">dark_mode</span>
            <span class="material-symbols-outlined hidden dark:block">light_mode</span>
        </button>
    </div>
    <script>
        // Apply saved preference on load
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
        function toggleDark() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
        }

        function submitPeriod(p) {
            document.getElementById('period-input').value = p;
            document.getElementById('period-form').submit();
        }

        let leftYear, leftMonth, rightYear, rightMonth;
        let startDate = null, endDate = null;
        const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        function initCalendar() {
            const now = new Date();
            leftYear = now.getFullYear();
            leftMonth = now.getMonth();
            rightYear = leftMonth === 11 ? leftYear + 1 : leftYear;
            rightMonth = leftMonth === 11 ? 0 : leftMonth + 1;
            const df = document.getElementById('hidden-date-from').value;
            const dt = document.getElementById('hidden-date-to').value;
            if (df) { startDate = new Date(df); document.getElementById('display-date-from').value = formatDisplay(startDate); }
            if (dt) { endDate = new Date(dt); document.getElementById('display-date-to').value = formatDisplay(endDate); }
            renderCalendars();
        }

        function formatDisplay(d) {
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function formatISO(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }

        function renderCalendars() {
            document.getElementById('left-month-label').textContent = MONTHS[leftMonth] + ' ' + leftYear;
            document.getElementById('right-month-label').textContent = MONTHS[rightMonth] + ' ' + rightYear;
            renderMonth('left-calendar', leftYear, leftMonth);
            renderMonth('right-calendar', rightYear, rightMonth);
        }

        function renderMonth(containerId, year, month) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            for (let i = 0; i < firstDay; i++) {
                const blank = document.createElement('div');
                blank.className = 'h-10';
                container.appendChild(blank);
            }
            for (let d = 1; d <= daysInMonth; d++) {
                const date = new Date(year, month, d);
                const btn = document.createElement('button');
                btn.textContent = d;
                btn.type = 'button';
                btn.className = 'h-10 w-full text-sm font-semibold rounded-lg transition-colors ';
                const isStart = startDate && formatISO(date) === formatISO(startDate);
                const isEnd = endDate && formatISO(date) === formatISO(endDate);
                const inRange = startDate && endDate && date > startDate && date < endDate;
                if (isStart || isEnd) {
                    btn.className += 'bg-primary text-zinc-900 font-black rounded-full';
                } else if (inRange) {
                    btn.className += 'bg-primary/20 dark:bg-primary/10 text-slate-900 dark:text-white';
                } else {
                    btn.className += 'hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-900 dark:text-white';
                }
                btn.addEventListener('click', () => selectDate(date));
                container.appendChild(btn);
            }
        }

        function selectDate(date) {
            if (!startDate || (startDate && endDate)) {
                startDate = date; endDate = null;
                document.getElementById('display-date-from').value = formatDisplay(date);
                document.getElementById('display-date-to').value = '';
            } else {
                if (date < startDate) { endDate = startDate; startDate = date; }
                else { endDate = date; }
                document.getElementById('display-date-from').value = formatDisplay(startDate);
                document.getElementById('display-date-to').value = formatDisplay(endDate);
            }
            renderCalendars();
        }

        function prevMonth() {
            if (leftMonth === 0) { leftMonth = 11; leftYear--; } else leftMonth--;
            rightMonth = leftMonth === 11 ? 0 : leftMonth + 1;
            rightYear = leftMonth === 11 ? leftYear + 1 : leftYear;
            renderCalendars();
        }

        function nextMonth() {
            if (rightMonth === 11) { rightMonth = 0; rightYear++; } else rightMonth++;
            leftMonth = rightMonth === 0 ? 11 : rightMonth - 1;
            leftYear = rightMonth === 0 ? rightYear - 1 : rightYear;
            renderCalendars();
        }

        function applyCustomRange() {
            if (!startDate || !endDate) { alert('Please select both a start and end date.'); return; }
            document.getElementById('hidden-date-from').value = formatISO(startDate);
            document.getElementById('hidden-date-to').value = formatISO(endDate);
            document.getElementById('period-input').value = 'custom';
            document.getElementById('calendar-modal').classList.add('hidden');
            document.getElementById('period-form').submit();
        }

        initCalendar();

        const invToggle = document.getElementById('inv-toggle');
        const invSubmenu = document.getElementById('inv-submenu');
        const invArrow = document.getElementById('inv-arrow');
        invToggle.addEventListener('click', () => {
            invSubmenu.classList.toggle('hidden');
            invArrow.classList.toggle('rotate-180');
        });
    </script>

</body>

</html>