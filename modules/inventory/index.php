<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM inventory WHERE id = $id");
    header('Location: /pos-system/modules/inventory/index.php?deleted=1');
    exit;
}

$limit = 5;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$where = "WHERE 1=1";
if ($search)
    $where .= " AND i.name LIKE '%$search%'";

$total_query = $conn->query("SELECT COUNT(*) as count FROM inventory i $where");
$total_items = $total_query->fetch_assoc()['count'];

$low_stock_query = $conn->query("SELECT COUNT(*) as count FROM inventory i $where AND i.quantity <= i.low_stock_threshold AND i.quantity > 0");
$low_stock_count = $low_stock_query->fetch_assoc()['count'];

$out_of_stock_query = $conn->query("SELECT COUNT(*) as count FROM inventory i $where AND i.quantity = 0");
$out_of_stock_count = $out_of_stock_query->fetch_assoc()['count'];

$inventory_query = $conn->query("SELECT i.* FROM inventory i $where ORDER BY i.quantity ASC LIMIT $limit OFFSET $offset");
$inventory_items = $inventory_query->fetch_all(MYSQLI_ASSOC);

$total_pages = ceil($total_items / $limit);

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
    <title>Eggsy Inventory Management</title>
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
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo $current === '/pos-system/modules/inventory/index.php' ? 'text-primary bg-primary/10 dark:bg-primary/20' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'; ?> transition-colors"
                            href="/pos-system/modules/inventory/index.php">
                            <span class="material-symbols-outlined text-base">list</span>
                            <span class="text-sm font-medium">All Ingredients</span>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo $current === '/pos-system/modules/inventory/products.php' ? 'text-primary bg-primary/10 dark:bg-primary/20' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'; ?> transition-colors"
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
                <section>
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-3xl font-bold dark:text-white">Inventory Management</h2>
                            <p class="text-slate-500 dark:text-zinc-400">Monitor and manage your restaurant supply stock
                                levels.</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <a href="/pos-system/modules/inventory/add_stock.php"
                                class="bg-primary hover:bg-primary/90 text-zinc-900 px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all whitespace-nowrap">
                                <span class="material-symbols-outlined text-xl font-bold">add_box</span>
                                Add Stock
                            </a>
                            <a href="/pos-system/modules/inventory/add.php"
                                class="bg-primary hover:bg-primary/90 text-zinc-900 px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all whitespace-nowrap">
                                <span class="material-symbols-outlined text-xl font-bold">inventory</span>
                                Add Ingredient
                            </a>
                        </div>
                    </div>

                    <!-- Stat Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
                        <div
                            class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm flex items-center gap-5">
                            <div
                                class="size-14 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-500">
                                <span class="material-symbols-outlined text-3xl">inventory</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-500 dark:text-zinc-400">Total Items</p>
                                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                                    <?php echo $total_items; ?>
                                </h3>
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border-2 border-red-500/20 shadow-sm flex items-center gap-5">
                            <div class="size-14 rounded-xl bg-red-500 text-white flex items-center justify-center">
                                <span class="material-symbols-outlined text-3xl">warning</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-red-500 uppercase tracking-tight">Low Stock Items
                                </p>
                                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                                    <?php echo $low_stock_count; ?>
                                </h3>
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm flex items-center gap-5">
                            <div class="size-14 rounded-xl bg-slate-800 text-white flex items-center justify-center">
                                <span class="material-symbols-outlined text-3xl">block</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-500 dark:text-zinc-400">Out of Stock</p>
                                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                                    <?php echo $out_of_stock_count; ?>
                                </h3>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div
                            class="p-6 border-b border-slate-100 dark:border-zinc-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <form method="GET" class="flex flex-col md:flex-row gap-3 w-full">
                                <div class="relative w-full md:w-96">
                                    <span
                                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                                    <input
                                        class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-zinc-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/50 text-slate-900 dark:text-white transition-all"
                                        name="search" placeholder="Search inventory..." type="text"
                                        value="<?php echo htmlspecialchars($search); ?>" />
                                </div>
                                <button type="submit"
                                    class="px-4 py-2 bg-primary rounded-lg text-sm font-bold text-zinc-900">Search</button>
                                <?php if ($search): ?>
                                    <a href="/pos-system/modules/inventory/index.php"
                                        class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 rounded-lg text-sm font-medium text-slate-600 dark:text-zinc-400">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr
                                        class="bg-slate-50 dark:bg-zinc-800 border-b border-slate-100 dark:border-zinc-700">
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Item Name</th>
                                        <th
                                            class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                            Current Stock</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                            Unit</th>
                                        <th
                                            class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                            Min. Level</th>
                                        <th
                                            class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">
                                            Status</th>
                                        <th
                                            class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                    <?php if (empty($inventory_items)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-10 text-center text-slate-400">No inventory items
                                                found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($inventory_items as $item):
                                            if ($item['quantity'] == 0) {
                                                $status_class = 'bg-red-100 text-red-700';
                                                $status_text = 'Out of Stock';
                                                $qty_class = 'text-red-600 font-bold';
                                            } elseif ($item['quantity'] <= $item['low_stock_threshold']) {
                                                $status_class = 'bg-yellow-100 text-yellow-700';
                                                $status_text = 'Low Stock';
                                                $qty_class = 'text-amber-500 font-bold';
                                            } else {
                                                $status_class = 'bg-emerald-50 text-emerald-600';
                                                $status_text = 'In Stock';
                                                $qty_class = 'text-slate-900 dark:text-white font-bold';
                                            }
                                            ?>
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800 transition-colors">
                                                <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span
                                                        class="text-sm <?php echo $qty_class; ?>"><?php echo ($item['quantity'] == intval($item['quantity'])) ? intval($item['quantity']) : $item['quantity']; ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-zinc-400">
                                                    <?php echo htmlspecialchars($item['unit']); ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-sm text-slate-600 dark:text-zinc-400 font-medium text-center">
                                                    <?php echo ($item['low_stock_threshold'] == intval($item['low_stock_threshold'])) ? intval($item['low_stock_threshold']) : $item['low_stock_threshold']; ?>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span
                                                        class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <a href="/pos-system/modules/inventory/edit.php?id=<?php echo $item['id']; ?>"
                                                            class="p-1.5 rounded-lg hover:bg-primary/10 text-slate-500 hover:text-primary transition-colors">
                                                            <span class="material-symbols-outlined text-base">edit</span>
                                                        </a>
                                                        <button
                                                            onclick="openDeleteModal(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'])); ?>')"
                                                            class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-500 transition-colors">
                                                            <span class="material-symbols-outlined text-base">delete</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="p-6 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                            <p class="text-sm text-slate-500 dark:text-zinc-400">
                                Showing <span
                                    class="font-bold text-slate-900 dark:text-white"><?php echo min($offset + 1, $total_items); ?>-<?php echo min($offset + $limit, $total_items); ?></span>
                                of <span
                                    class="font-bold text-slate-900 dark:text-white"><?php echo $total_items; ?></span>
                                items
                            </p>
                            <div class="flex items-center gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"
                                        class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 text-sm font-medium hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">Previous</a>
                                <?php else: ?>
                                    <button disabled
                                        class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 text-sm font-medium opacity-50">Previous</button>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"
                                        class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 text-sm font-medium hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">Next</a>
                                <?php else: ?>
                                    <button disabled
                                        class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 text-sm font-medium opacity-50">Next</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Toast Notifications -->
    <?php if (isset($_GET['updated'])): ?>
        <div id="toast"
            class="fixed top-6 right-6 z-50 flex items-center gap-3 bg-emerald-500 text-white px-5 py-4 rounded-2xl shadow-xl">
            <span class="material-symbols-outlined">check_circle</span>
            <p class="text-sm font-bold">Item updated successfully.</p>
        </div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div id="toast"
            class="fixed top-6 right-6 z-50 flex items-center gap-3 bg-red-500 text-white px-5 py-4 rounded-2xl shadow-xl">
            <span class="material-symbols-outlined">delete</span>
            <p class="text-sm font-bold">Item deleted successfully.</p>
        </div>
    <?php endif; ?>

    <!-- Delete Modal -->
    <div id="delete-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-sm p-8 space-y-6">
            <div class="flex flex-col items-center text-center gap-3">
                <div class="size-16 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-500 text-3xl">delete</span>
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Delete Item</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm">You are about to delete <span id="delete-item-name"
                        class="font-bold text-slate-900 dark:text-white"></span>. This action cannot be undone.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()"
                    class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-sm font-bold text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">
                    Cancel
                </button>
                <a id="delete-confirm-btn" href="#"
                    class="flex-1 py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white font-black text-sm text-center transition-colors">
                    Yes, Delete
                </a>
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
    <script>
        // Apply saved preference on load
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
        function toggleDark() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
        }

        const invToggle = document.getElementById('inv-toggle');
        const invSubmenu = document.getElementById('inv-submenu');
        const invArrow = document.getElementById('inv-arrow');

        invToggle.addEventListener('click', () => {
            invSubmenu.classList.toggle('hidden');
            invArrow.classList.toggle('rotate-180');
        });

        function openDeleteModal(id, name) {
            document.getElementById('delete-item-name').textContent = name;
            document.getElementById('delete-confirm-btn').href = '?delete=' + id;
            document.getElementById('delete-modal').classList.remove('hidden');
        }
        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.add('hidden');
        }

        const toast = document.getElementById('toast');
        if (toast) {
            setTimeout(() => {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }
    </script>

</body>

</html>