<?php
require_once 'config/session.php';
require_once 'config/db.php';
requireLogin();

if ($_SESSION['role'] === 'admin') {
    header('Location: /pos-system/dashboard.php');
    exit;
}

if (!in_array($_SESSION['role'], ['cashier', 'staff'])) {
    header('Location: /pos-system/modules/auth/login.php');
    exit;
}

$today = date('Y-m-d');

$completed_query = $conn->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = '$today' AND status = 'completed'");
$completed_orders = $completed_query->fetch_assoc()['count'];

$sales_query = $conn->query("SELECT SUM(total) as total_sales FROM orders WHERE DATE(created_at) = '$today' AND status = 'completed'");
$total_sales = $sales_query->fetch_assoc()['total_sales'] ?? 0;

$items_query = $conn->query("
    SELECT SUM(oi.quantity) as total_items
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) = '$today' AND o.status = 'completed'
");
$total_items = $items_query->fetch_assoc()['total_items'] ?? 0;

$orders_query = $conn->query("
    SELECT o.*, u.full_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 6
");
$recent_orders = $orders_query->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Eggsy - Cashier Portal</title>
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
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary dark:bg-primary/20 transition-colors"
                    href="/pos-system/cashier_dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="text-sm font-semibold">Overview</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/modules/pos/index.php">
                    <span class="material-symbols-outlined">shopping_bag</span>
                    <span class="text-sm font-medium">Orders</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/modules/reports/cashier.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    <span class="text-sm font-medium">Reports</span>
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

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden">
            <header
                class="h-16 flex items-center justify-end px-8 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800">
                <div class="flex items-center gap-4">
                    <div class="h-8 w-px bg-slate-200 dark:bg-zinc-800 mx-2"></div>
                    <div class="flex items-center gap-3 pl-2">
                        <div class="text-right">
                            <p class="text-sm font-semibold leading-none text-slate-900 dark:text-white">
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                            </p>
                            <p class="text-xs text-slate-500 mt-1"><?php echo ucfirst($_SESSION['role']); ?></p>
                        </div>
                        <div
                            class="size-10 rounded-full bg-primary/20 flex items-center justify-center border-2 border-primary/20">
                            <span class="material-symbols-outlined text-primary">person</span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8 space-y-10">
                <section>
                    <div class="flex items-end justify-between mb-8">
                        <div>
                            <h2 class="text-3xl font-bold dark:text-white">Cashier Overview</h2>
                            <p class="text-slate-500 dark:text-zinc-400">Manage orders and track your shift performance.
                            </p>
                        </div>
                        <a href="/pos-system/modules/pos/index.php"
                            class="bg-primary hover:bg-primary/90 text-zinc-900 px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
                            <span class="material-symbols-outlined text-xl">add</span>
                            New Order
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                        <div
                            class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                            <div
                                class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-9xl">payments</span>
                            </div>
                            <p
                                class="text-sm font-semibold text-slate-500 dark:text-zinc-400 mb-2 uppercase tracking-wide">
                                Today's Sales</p>
                            <h3 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                                ₱<?php echo number_format($total_sales, 2); ?></h3>
                            <p class="text-sm text-slate-400 mt-6 font-medium"><?php echo $completed_orders; ?> orders
                                completed today</p>
                        </div>

                        <div
                            class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                            <div
                                class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-9xl">lunch_dining</span>
                            </div>
                            <p
                                class="text-sm font-semibold text-slate-500 dark:text-zinc-400 mb-2 uppercase tracking-wide">
                                Total Items Sold</p>
                            <h3 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                                <?php echo number_format($total_items); ?>
                            </h3>
                            <p class="text-sm text-slate-400 mt-6 font-medium">Items sold today</p>
                        </div>

                        <div
                            class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                            <div
                                class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-9xl">check_circle</span>
                            </div>
                            <p
                                class="text-sm font-semibold text-slate-500 dark:text-zinc-400 mb-2 uppercase tracking-wide">
                                Completed Today</p>
                            <h3 class="text-4xl font-bold tracking-tight text-emerald-500">
                                <?php echo $completed_orders; ?>
                            </h3>
                            <p class="text-sm text-slate-400 mt-6 font-medium">Orders closed this shift</p>
                        </div>

                    </div>
                </section>

                <!-- Recent Orders Table -->
                <div
                    class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm pb-8">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="font-bold text-xl text-slate-900 dark:text-white">Recent Orders</h3>
                            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Latest completed orders</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-zinc-800">
                                    <th class="text-left pb-4 text-xs font-bold text-slate-400 uppercase tracking-wide">
                                        Order ID</th>
                                    <th class="text-left pb-4 text-xs font-bold text-slate-400 uppercase tracking-wide">
                                        Cashier</th>
                                    <th class="text-left pb-4 text-xs font-bold text-slate-400 uppercase tracking-wide">
                                        Total</th>
                                    <th class="text-left pb-4 text-xs font-bold text-slate-400 uppercase tracking-wide">
                                        Status</th>
                                    <th class="text-left pb-4 text-xs font-bold text-slate-400 uppercase tracking-wide">
                                        Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400 text-sm">No orders yet today.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order):
                                        if ($order['status'] === 'completed') {
                                            $badge = 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600';
                                        } elseif ($order['status'] === 'cancelled') {
                                            $badge = 'bg-red-50 dark:bg-red-900/20 text-red-500';
                                        } else {
                                            $badge = 'bg-primary/10 text-primary';
                                        }
                                        ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">
                                            <td class="py-4 font-bold text-slate-900 dark:text-white">
                                                #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                            <td class="py-4 text-slate-600 dark:text-zinc-400">
                                                <?php echo htmlspecialchars($order['full_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="py-4 font-bold text-slate-900 dark:text-white">
                                                ₱<?php echo number_format($order['total'], 2); ?></td>
                                            <td class="py-4">
                                                <span class="text-xs font-bold px-3 py-1 rounded-full <?php echo $badge; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-4 text-slate-400 text-xs">
                                                <?php echo date('h:i A', strtotime($order['created_at'])); ?>
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

    <!-- Logout Modal -->
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