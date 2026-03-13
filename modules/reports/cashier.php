<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

$today = date('Y-m-d');
$user_id = (int) $_SESSION['user_id'];

// Today's sales (all cashiers, visible to cashier too)
$sales_query = $conn->query("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE status='completed' AND DATE(created_at)='$today'");
$today_sales = $sales_query->fetch_assoc()['v'];

$orders_query = $conn->query("SELECT COUNT(*) as v FROM orders WHERE status='completed' AND DATE(created_at)='$today'");
$order_count = $orders_query->fetch_assoc()['v'];
// My orders today
$my_orders_query = $conn->query("SELECT COUNT(*) as v FROM orders WHERE user_id=$user_id AND DATE(created_at)='$today'");
$my_orders = $my_orders_query->fetch_assoc()['v'];

$my_sales_query = $conn->query("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE user_id=$user_id AND status='completed' AND DATE(created_at)='$today'");
$my_sales = $my_sales_query->fetch_assoc()['v'];

// Recent orders today
$recent_orders = $conn->query("
    SELECT o.id, o.total, o.status, o.created_at,
           u.full_name as cashier_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE DATE(o.created_at) = '$today'
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Top products today
$top_products = $conn->query("
    SELECT oi.name, SUM(oi.quantity) as units, SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE status='completed' AND DATE(o.created_at)='$today'
    GROUP BY oi.name
    ORDER BY units DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Reports - Eggsy Cashier</title>
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css" />
    <link rel="stylesheet" href="/pos-system/assets/css/app.css" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
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
                    <h1 class="text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-white">Eggsy</h1>
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Cashier Portal</p>
                </div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/cashier_dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="text-sm font-semibold">Overview</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/modules/pos/index.php">
                    <span class="material-symbols-outlined">shopping_bag</span>
                    <span class="text-sm font-semibold">Orders</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary dark:bg-primary/20 transition-colors"
                    href="/pos-system/modules/reports/cashier.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    <span class="text-sm font-semibold">Reports</span>
                </a>
            </nav>
            <div class="p-4 border-t border-slate-200 dark:border-zinc-800">
                <button onclick="document.getElementById('logout-modal').classList.remove('hidden')"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="text-sm font-medium">Logout</span>
                </button>
            </div>
        </aside>

        <!-- Main -->
        <main class="flex-1 flex flex-col overflow-hidden">

            <!-- Top Header -->
            <header
                class="h-16 flex items-center justify-end px-8 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800">
                <div class="flex items-center gap-4">
                    <div class="h-8 w-px bg-slate-200 dark:bg-zinc-800"></div>
                    <div class="flex items-center gap-3 pl-2">
                        <div class="text-right">
                            <p class="text-sm font-semibold leading-none text-slate-900 dark:text-white">
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                            </p>
                            <p class="text-xs text-slate-500 mt-1">Cashier</p>
                        </div>
                        <div
                            class="size-10 rounded-full bg-primary flex items-center justify-center border-2 border-primary/20">
                            <span class="material-symbols-outlined text-zinc-900">person</span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8 space-y-8">

                <!-- Page Title -->
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Today's Report</h2>
                        <p class="text-slate-500 dark:text-zinc-400 mt-1"><?php echo date('l, F j, Y'); ?></p>
                    </div>
                    <a href="/pos-system/modules/reports/export_cashier.php"
                        class="bg-primary hover:bg-primary/90 text-zinc-900 px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
                        <span class="material-symbols-outlined text-xl">download</span>
                        Export
                    </a>
                </div>

                <!-- Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                        <div
                            class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                            <span class="material-symbols-outlined text-9xl">payments</span>
                        </div>
                        <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2">
                            Today's Sales</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white">
                            ₱<?php echo number_format($today_sales, 2); ?></h3>
                        <p class="text-xs text-slate-400 mt-3"><?php echo $order_count; ?> orders completed today</p>
                    </div>

                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                        <div
                            class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                            <span class="material-symbols-outlined text-9xl">person</span>
                        </div>
                        <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2">My
                            Orders</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white"><?php echo $my_orders; ?></h3>
                        <p class="text-xs text-slate-400 mt-3">Processed by you today</p>
                    </div>
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                        <div
                            class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                            <span class="material-symbols-outlined text-9xl">check_circle</span>
                        </div>
                        <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2">My
                            Sales</p>
                        <h3 class="text-3xl font-black text-emerald-500">
                            ₱<?php echo number_format($my_sales, 2); ?></h3>
                        <p class="text-xs text-slate-400 mt-3">Your revenue today</p>
                    </div>
                </div>

                <!-- Bottom Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-8">

                    <!-- Recent Orders -->
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 dark:border-zinc-800">
                            <h3 class="font-bold text-lg text-slate-900 dark:text-white">Today's Orders</h3>
                            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">All orders placed today</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-zinc-800">
                                        <th class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Order</th>
                                        <th class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Cashier</th>
                                        <th
                                            class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                            Total</th>
                                        <th
                                            class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                            Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                    <?php if (empty($recent_orders)): ?>
                                        <tr>
                                            <td colspan="4" class="px-5 py-10 text-center text-slate-400 text-sm">No orders
                                                today yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800 transition-colors">
                                                <td class="px-5 py-3 font-bold text-slate-900 dark:text-white text-sm">
                                                    #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                <td class="px-5 py-3 text-sm text-slate-600 dark:text-zinc-400">
                                                    <?php echo htmlspecialchars($order['cashier_name'] ?? 'Unknown'); ?>
                                                </td>
                                                <td class="px-5 py-3 text-right font-bold text-primary text-sm">
                                                    ₱<?php echo number_format($order['total'], 2); ?></td>
                                                <td class="px-5 py-3 text-right text-xs text-slate-400">
                                                    <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 dark:border-zinc-800">
                            <h3 class="font-bold text-lg text-slate-900 dark:text-white">Top Products Today</h3>
                            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Best selling items this shift
                            </p>
                        </div>
                        <div class="p-6">
                            <?php if (empty($top_products)): ?>
                                <div class="flex items-center justify-center h-40 text-slate-300 dark:text-zinc-600">
                                    <p class="text-sm font-semibold">No sales data yet today.</p>
                                </div>
                            <?php else: ?>
                                <?php
                                $max_units = max(array_column($top_products, 'units'));
                                $opacities = ['100', '80', '60', '40', '25'];
                                foreach ($top_products as $i => $p):
                                    $pct = $max_units > 0 ? round(($p['units'] / $max_units) * 100) : 0;
                                    $op = $opacities[$i] ?? '20';
                                    ?>
                                    <div class="mb-5">
                                        <div class="flex justify-between text-sm font-bold mb-1.5">
                                            <span
                                                class="text-slate-700 dark:text-slate-300 truncate max-w-[60%]"><?php echo htmlspecialchars($p['name']); ?></span>
                                            <span class="text-slate-500"><?php echo $p['units']; ?> sold</span>
                                        </div>
                                        <div class="w-full bg-slate-100 dark:bg-zinc-800 h-3 rounded-full overflow-hidden">
                                            <div class="bg-primary h-full rounded-full"
                                                style="width: <?php echo $pct; ?>%; opacity: <?php echo $op; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
    <!-- Logout Confirmation Modal -->
    <div id="logout-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-sm p-8 space-y-6">
            <div class="flex flex-col items-center text-center gap-3">
                <div class="size-16 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-500 text-3xl">logout</span>
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Logout</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm">Are you sure you want to log out of your account?
                </p>
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

    <!-- Dark Mode Toggle -->
    <div class="fixed bottom-6 right-6 z-50">
        <button
            class="size-12 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl flex items-center justify-center transition-transform hover:scale-110 active:scale-95"
            onclick="toggleDark()">
            <span class="material-symbols-outlined dark:hidden">dark_mode</span>
            <span class="material-symbols-outlined hidden dark:block">light_mode</span>
        </button>
    </div>

</body>

</html>