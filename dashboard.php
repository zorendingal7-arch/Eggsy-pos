<?php
require_once 'config/session.php';
require_once 'config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$today = date('Y-m-d');
$sales_query = $conn->query("SELECT SUM(total) as total_sales FROM orders WHERE DATE(created_at) = '$today' AND status = 'completed'");
$sales_row = $sales_query->fetch_assoc();
$total_sales = $sales_row['total_sales'] ?? 0;

$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_query = $conn->query("SELECT SUM(total) as total_sales FROM orders WHERE DATE(created_at) = '$yesterday' AND status = 'completed'");
$yesterday_row = $yesterday_query->fetch_assoc();
$yesterday_sales = $yesterday_row['total_sales'] ?? 0;

$sales_change = 0;
if ($yesterday_sales > 0) {
    $sales_change = round((($total_sales - $yesterday_sales) / $yesterday_sales) * 100);
}

$active_query = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'open'");
$active_orders = $active_query->fetch_assoc()['count'];

$low_stock_query = $conn->query("SELECT * FROM inventory WHERE quantity <= low_stock_threshold ORDER BY quantity ASC");
$low_stock_items = $low_stock_query->fetch_all(MYSQLI_ASSOC);
$low_stock_count = count($low_stock_items);

$best_product = $conn->query("
    SELECT oi.name, SUM(oi.quantity) as units
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status='completed' AND DATE(o.created_at) = '$today'
    GROUP BY oi.name
    ORDER BY units DESC
    LIMIT 1
")->fetch_assoc();
$best_product_name = $best_product['name'] ?? 'No sales yet';
$best_product_units = $best_product['units'] ?? 0;

$notif_query = $conn->query("
    SELECT name, quantity, low_stock_threshold
    FROM inventory
    WHERE quantity <= low_stock_threshold
    ORDER BY quantity ASC
    LIMIT 10
");
$notif_items = $notif_query->fetch_all(MYSQLI_ASSOC);
$notif_count = count($notif_items);

$inventory_query = $conn->query("SELECT * FROM inventory ORDER BY quantity ASC LIMIT 4");
$inventory_items = $inventory_query->fetch_all(MYSQLI_ASSOC);

$staff_query = $conn->query("SELECT * FROM users WHERE status = 'active' AND role != 'admin' LIMIT 4");
$staff_list = $staff_query->fetch_all(MYSQLI_ASSOC);
$staff_count = count($staff_list);

$current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$inventory_pages = [
    '/pos-system/modules/inventory/index.php',
    '/pos-system/modules/inventory/add.php',
    '/pos-system/modules/inventory/add_stock.php',
    '/pos-system/modules/inventory/add_product.php',
    '/pos-system/modules/inventory/edit.php',
];
$inv_open = in_array($current, $inventory_pages);
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Eggsy System Dashboard Overview</title>
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
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
                    <h1 class="text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-white">Eggsy
                        Admin</h1>
                </div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-1">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary dark:bg-primary/20 transition-colors"
                    href="/pos-system/dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="text-sm font-semibold">Overview</span>
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
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors"
                    href="/pos-system/modules/reports/index.php">
                    <span class="material-symbols-outlined">bar_chart</span>
                    <span class="text-sm font-medium">Reports</span>
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
                    <div class="relative">
                        <button onclick="document.getElementById('notif-dropdown').classList.toggle('hidden')"
                            class="relative p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-lg">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if ($notif_count > 0): ?>
                                <span
                                    class="absolute top-1.5 right-1.5 size-2.5 bg-red-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
                            <?php endif; ?>
                        </button>
                        <div id="notif-dropdown"
                            class="hidden absolute right-0 mt-3 w-80 bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-zinc-800 z-50 overflow-hidden">
                            <div
                                class="p-4 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                                <h3 class="font-bold text-slate-900 dark:text-white">Notifications</h3>
                                <?php if ($notif_count > 0): ?>
                                    <span
                                        class="text-xs font-bold bg-red-100 text-red-500 px-2 py-0.5 rounded-full"><?php echo $notif_count; ?>
                                        alerts</span>
                                <?php else: ?>
                                    <span
                                        class="text-xs font-bold bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full">All
                                        clear</span>
                                <?php endif; ?>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                <?php if (empty($notif_items)): ?>
                                    <div class="p-6 text-center">
                                        <span
                                            class="material-symbols-outlined text-4xl text-slate-200 dark:text-zinc-700">check_circle</span>
                                        <p class="text-sm font-semibold text-slate-400 mt-2">All inventory levels are good.
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notif_items as $item):
                                        $is_out = $item['quantity'] <= 0;
                                        ?>
                                        <a href="/pos-system/modules/inventory/index.php"
                                            class="flex items-start gap-3 p-4 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors border-b border-slate-50 dark:border-zinc-800 last:border-0">
                                            <div
                                                class="size-10 rounded-xl <?php echo $is_out ? 'bg-red-100 dark:bg-red-900/30' : 'bg-amber-100 dark:bg-amber-900/30'; ?> flex items-center justify-center shrink-0">
                                                <span
                                                    class="material-symbols-outlined <?php echo $is_out ? 'text-red-500' : 'text-amber-500'; ?>">
                                                    <?php echo $is_out ? 'remove_shopping_cart' : 'warning'; ?>
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-bold text-slate-900 dark:text-white truncate">
                                                    <?php echo htmlspecialchars($item['name']); ?></p>
                                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                                                    <?php if ($is_out): ?>
                                                        Out of stock
                                                    <?php else: ?>
                                                        Only <?php echo $item['quantity']; ?>
                                                        <?php echo htmlspecialchars($item['unit'] ?? 'units'); ?> left (min:
                                                        <?php echo $item['low_stock_threshold']; ?>)
                                                    <?php endif; ?>
                                                </p>
                                                <span
                                                    class="text-[10px] font-bold uppercase tracking-wider <?php echo $is_out ? 'text-red-400' : 'text-amber-400'; ?>">
                                                    <?php echo $is_out ? 'Out of Stock' : 'Low Stock'; ?>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <a href="/pos-system/modules/inventory/index.php"
                                class="block w-full py-3 text-center text-xs font-bold text-primary hover:bg-primary/5 transition-colors border-t border-slate-100 dark:border-zinc-800">
                                View All Inventory
                            </a>
                        </div>
                    </div>
                    <div class="h-8 w-px bg-slate-200 dark:bg-zinc-800 mx-2"></div>
                    <div class="flex items-center gap-3 pl-2">
                        <div class="text-right">
                            <p class="text-sm font-semibold leading-none text-slate-900 dark:text-white">
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
                            <p class="text-xs text-slate-500 mt-1"><?php echo ucfirst($_SESSION['role']); ?></p>
                        </div>
                        <div
                            class="size-10 rounded-full bg-primary flex items-center justify-center border-2 border-primary/20">
                            <span class="material-symbols-outlined text-zinc-900">person</span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8 space-y-10">
                <section>
                    <div class="flex items-end justify-between mb-8">
                        <div>
                            <h2 class="text-3xl font-bold dark:text-white">Dashboard Overview</h2>
                            <p class="text-slate-500 dark:text-zinc-400">Manage your daily operations and inventory
                                levels efficiently.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="/pos-system/modules/reports/end_of_day.php"
                                class="flex items-center gap-2 bg-zinc-900 hover:bg-zinc-800 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-white px-5 py-3 rounded-xl font-bold text-sm transition-all shadow-sm">
                                <span class="material-symbols-outlined text-primary text-xl">summarize</span>
                                End-of-Day
                            </a>
                            <a href="modules/pos/index.php"
                                class="bg-primary hover:bg-primary/90 text-zinc-900 px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
                                <span class="material-symbols-outlined text-xl">add</span>
                                Create New Order
                            </a>
                        </div>
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
                                Total Daily Sales</p>
                            <div class="flex items-baseline gap-2">
                                <h3 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                                    ₱<?php echo number_format($total_sales, 2); ?></h3>
                                <?php if ($sales_change >= 0): ?>
                                    <span
                                        class="text-xs font-bold text-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-full flex items-center">
                                        <span class="material-symbols-outlined text-xs">trending_up</span>
                                        <?php echo $sales_change; ?>%
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="text-xs font-bold text-red-500 bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded-full flex items-center">
                                        <span class="material-symbols-outlined text-xs">trending_down</span>
                                        <?php echo abs($sales_change); ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-slate-400 mt-6 font-medium">Compared to
                                ₱<?php echo number_format($yesterday_sales, 2); ?> yesterday</p>
                        </div>

                        <div
                            class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                            <div
                                class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-9xl">star</span>
                            </div>
                            <p
                                class="text-sm font-semibold text-slate-500 dark:text-zinc-400 mb-2 uppercase tracking-wide">
                                Best Seller Today</p>
                            <h3 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white truncate">
                                <?php echo htmlspecialchars($best_product_name); ?></h3>
                            <div class="flex gap-3 mt-6">
                                <span
                                    class="text-xs uppercase font-bold text-primary px-3 py-1 rounded-full bg-primary/10"><?php echo $best_product_units; ?>
                                    units sold</span>
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm relative overflow-hidden group">
                            <div
                                class="absolute -right-4 -top-4 text-primary/10 transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-9xl">warning</span>
                            </div>
                            <p
                                class="text-sm font-semibold text-slate-500 dark:text-zinc-400 mb-2 uppercase tracking-wide">
                                Low Stock Alerts</p>
                            <h3 class="text-4xl font-bold tracking-tight text-red-500"><?php echo $low_stock_count; ?>
                            </h3>
                            <p class="text-sm text-slate-400 mt-6 font-medium">
                                <?php if ($low_stock_count > 0): ?>
                                    Critical items: <span
                                        class="text-red-400"><?php echo implode(', ', array_column(array_slice($low_stock_items, 0, 2), 'name')); ?></span>
                                <?php else: ?>
                                    All stock levels are good.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pb-8">
                    <div
                        class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="font-bold text-xl text-slate-900 dark:text-white">Inventory Levels</h3>
                                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Real-time stock monitoring</p>
                            </div>
                            <a href="modules/inventory/index.php"
                                class="text-xs font-bold text-primary bg-primary/10 px-4 py-2 rounded-lg uppercase tracking-widest hover:bg-primary/20 transition-colors">Manage
                                All</a>
                        </div>
                        <div class="space-y-8">
                            <?php if (empty($inventory_items)): ?>
                                <p class="text-sm text-slate-400">No inventory items found.</p>
                            <?php else: ?>
                                <?php foreach ($inventory_items as $item):
                                    $max = 200;
                                    $percent = min(100, round(($item['quantity'] / $max) * 100));
                                    if ($percent <= 20) {
                                        $bar_color = 'bg-red-500';
                                        $text_color = 'text-red-500';
                                    } elseif ($percent <= 40) {
                                        $bar_color = 'bg-amber-500';
                                        $text_color = 'text-amber-500';
                                    } else {
                                        $bar_color = 'bg-emerald-500';
                                        $text_color = 'text-emerald-500';
                                    }
                                    ?>
                                    <div>
                                        <div class="flex justify-between text-sm font-bold mb-3">
                                            <span
                                                class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($item['name']); ?></span>
                                            <span
                                                class="<?php echo $text_color; ?>"><?php echo ($item['quantity'] == intval($item['quantity'])) ? intval($item['quantity']) : $item['quantity']; ?>
                                                <?php echo $item['unit']; ?> left</span>
                                        </div>
                                        <div class="w-full h-3 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                            <div class="<?php echo $bar_color; ?> h-full rounded-full"
                                                style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="font-bold text-xl text-slate-900 dark:text-white">Staff On-Duty</h3>
                                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Current active staff accounts
                                </p>
                            </div>
                            <div
                                class="flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-900/20 px-3 py-1.5 rounded-lg border border-emerald-100 dark:border-emerald-900/30">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-xs font-bold text-emerald-600"><?php echo $staff_count; ?>
                                    Active</span>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <?php if (empty($staff_list)): ?>
                                <p class="text-sm text-slate-400">No staff found.</p>
                            <?php else: ?>
                                <?php foreach ($staff_list as $staff): ?>
                                    <div
                                        class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors border border-transparent hover:border-slate-100 dark:hover:border-zinc-700">
                                        <div class="flex items-center gap-4">
                                            <div class="relative">
                                                <div
                                                    class="size-12 rounded-full bg-primary/20 flex items-center justify-center border-2 border-primary/20">
                                                    <span class="material-symbols-outlined text-primary">person</span>
                                                </div>
                                                <span
                                                    class="absolute bottom-0 right-0 size-3 bg-emerald-500 border-2 border-white dark:border-zinc-900 rounded-full"></span>
                                            </div>
                                            <div>
                                                <p class="text-base font-bold text-slate-900 dark:text-white">
                                                    <?php echo htmlspecialchars($staff['full_name']); ?></p>
                                                <p
                                                    class="text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-tighter">
                                                    <?php echo ucfirst($staff['role']); ?></p>
                                            </div>
                                        </div>
                                        <span
                                            class="text-xs font-bold text-slate-400 bg-slate-100 dark:bg-zinc-800 px-3 py-1.5 rounded-full">Active</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Dark Mode Toggle -->
    <div class="fixed bottom-6 right-6 z-10">
        <button
            class="size-12 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl flex items-center justify-center transition-transform hover:scale-110 active:scale-95"
            onclick="toggleDark()">
            <span class="material-symbols-outlined dark:hidden">dark_mode</span>
            <span class="material-symbols-outlined hidden dark:block">light_mode</span>
        </button>
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

    <script>
        const invToggle = document.getElementById('inv-toggle');
        const invSubmenu = document.getElementById('inv-submenu');
        const invArrow = document.getElementById('inv-arrow');

        if (invToggle) {
            invToggle.addEventListener('click', function () {
                invSubmenu.classList.toggle('hidden');
                invArrow.classList.toggle('rotate-180');
            });
        }

        function toggleDark() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
        }

        document.addEventListener('click', function (e) {
            const dropdown = document.getElementById('notif-dropdown');
            const btn = e.target.closest('button');
            if (!dropdown.contains(e.target) && (!btn || !btn.onclick)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

</body>

</html>