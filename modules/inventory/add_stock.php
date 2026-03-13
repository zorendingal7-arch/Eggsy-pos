<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$error = '';
$success = isset($_GET['success']) ? 'Stock updated successfully.' : '';

$items_query = $conn->query("SELECT * FROM inventory ORDER BY name ASC");
$items = $items_query->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int) ($_POST['item_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);

    if ($item_id && $amount > 0) {
        $user_id = (int) $_SESSION['user_id'];
        $notes = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
        $conn->query("UPDATE inventory SET quantity = quantity + $amount WHERE id = $item_id");
        $conn->query("INSERT INTO stock_movements (inventory_id, quantity, type, notes, created_by, created_at)
                      VALUES ($item_id, $amount, 'in', '$notes', $user_id, NOW())");
        header('Location: /pos-system/modules/inventory/add_stock.php?success=1');
        exit;
    } else {
        $error = 'Please select an item and enter a valid amount.';
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Add Stock - Eggsy Admin</title>
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
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary dark:bg-primary/20 transition-colors"
                    href="/pos-system/modules/inventory/index.php">
                    <span class="material-symbols-outlined">inventory_2</span>
                    <span class="text-sm font-semibold">Inventory</span>
                </a>
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

            <div class="flex-1 overflow-y-auto p-8 flex flex-col items-center">
                <div class="w-full max-w-2xl">

                    <!-- Back -->
                    <a class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors mb-6 group"
                        href="/pos-system/modules/inventory/index.php">
                        <span
                            class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
                        <span class="text-sm font-semibold">Back to Inventory</span>
                    </a>

                    <!-- Card -->
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-8 border-b border-slate-100 dark:border-zinc-800 text-center">
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Add Stock</h2>
                            <p class="text-slate-500 dark:text-zinc-400 mt-1">Increase inventory levels for existing
                                ingredients.</p>
                        </div>

                        <?php if ($error): ?>
                            <div
                                class="mx-8 mt-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-600 text-sm font-medium flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">error</span> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div
                                class="mx-8 mt-6 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-600 text-sm font-medium flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">check_circle</span>
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="p-8 space-y-8">
                            <div class="space-y-6">

                                <!-- Select Item -->
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"
                                        for="item_id">Select Ingredient</label>
                                    <select
                                        class="w-full bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl py-3.5 px-4 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all"
                                        id="item_id" name="item_id" required>
                                        <option disabled selected value="">Choose an ingredient...</option>
                                        <?php foreach ($items as $item): ?>
                                            <option value="<?php echo $item['id']; ?>" <?php echo (isset($_POST['item_id']) && $_POST['item_id'] == $item['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($item['name']); ?>
                                                (<?php echo $item['quantity']; ?>     <?php echo $item['unit']; ?> left)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Amount -->
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2"
                                        for="amount">Amount to Add</label>
                                    <input
                                        class="w-full bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl py-3.5 px-4 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all"
                                        id="amount" name="amount" min="0.01" step="0.01" placeholder="0" required
                                        type="number" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" />
                                </div>

                            </div>

                            <div class="pt-4 space-y-4">
                                <button
                                    class="w-full bg-primary hover:bg-primary/90 text-zinc-900 py-4 rounded-xl font-bold text-base flex items-center justify-center gap-2 shadow-sm transition-all"
                                    type="submit">
                                    <span class="material-symbols-outlined font-bold">add_circle</span>
                                    Update Stock
                                </button>
                                <div class="text-center">
                                    <a class="text-sm font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300 transition-colors"
                                        href="/pos-system/modules/inventory/index.php">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Tip -->
                    <div class="mt-8 p-6 bg-primary/5 rounded-2xl border border-primary/10">
                        <div class="flex gap-4">
                            <span class="material-symbols-outlined text-primary">info</span>
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-white">Quick Tip</p>
                                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Inventory updates are logged
                                    immediately and visible in the activity reports. Ensure the quantities match your
                                    delivery note.</p>
                            </div>
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
    <script>
        // Apply saved preference on load
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
        function toggleDark() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark);
        }
    </script>
</body>

</html>