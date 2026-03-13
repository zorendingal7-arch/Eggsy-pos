<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$success = '';
$error = '';

// Load current settings
function getSetting($conn, $key)
{
    $k = $conn->real_escape_string($key);
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$k'");
    return $r && $r->num_rows > 0 ? $r->fetch_assoc()['setting_value'] : '';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'store_info') {
        $store_name = $conn->real_escape_string(trim($_POST['store_name']));
        $store_address = $conn->real_escape_string(trim($_POST['store_address']));
        $store_contact = $conn->real_escape_string(trim($_POST['store_contact']));
        $store_email = $conn->real_escape_string(trim($_POST['store_email']));

        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('store_name','$store_name') ON DUPLICATE KEY UPDATE setting_value='$store_name'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('store_address','$store_address') ON DUPLICATE KEY UPDATE setting_value='$store_address'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('store_contact','$store_contact') ON DUPLICATE KEY UPDATE setting_value='$store_contact'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('store_email','$store_email') ON DUPLICATE KEY UPDATE setting_value='$store_email'");

        $success = 'Store information updated successfully.';
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $user_id = (int) $_SESSION['user_id'];

        $user = $conn->query("SELECT password FROM users WHERE id=$user_id")->fetch_assoc();

        if ($current !== $user['password']) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $new_escaped = $conn->real_escape_string($new);
            $conn->query("UPDATE users SET password='$new_escaped' WHERE id=$user_id");
            $success = 'Password changed successfully.';
        }
    }
}

$store_name = getSetting($conn, 'store_name');
$store_address = getSetting($conn, 'store_address');
$store_contact = getSetting($conn, 'store_contact');
$store_email = getSetting($conn, 'store_email');

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
    <title>Settings - Eggsy Admin</title>
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
                <a class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/10 text-primary dark:bg-primary/20 transition-colors"
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

            <!-- Top Header -->
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

                <!-- Page Title -->
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Settings</h2>
                    <p class="text-slate-500 dark:text-zinc-400 mt-1">Manage your store information and account
                        security.</p>
                </div>

                <!-- Success / Error Banner -->
                <?php if ($success): ?>
                    <div
                        class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-5 py-4 rounded-xl">
                        <span class="material-symbols-outlined">check_circle</span>
                        <p class="text-sm font-bold"><?php echo $success; ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div
                        class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-5 py-4 rounded-xl">
                        <span class="material-symbols-outlined">error</span>
                        <p class="text-sm font-bold"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    <!-- Store Information -->
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined">storefront</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white">Store Information</h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Appears on receipts and
                                    reports.</p>
                            </div>
                        </div>
                        <form method="POST" class="p-6 space-y-4">
                            <input type="hidden" name="action" value="store_info">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Store
                                    Name</label>
                                <input name="store_name" type="text"
                                    value="<?php echo htmlspecialchars($store_name); ?>"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Address</label>
                                <input name="store_address" type="text"
                                    value="<?php echo htmlspecialchars($store_address); ?>"
                                    placeholder="e.g. 123 Main St, Manila"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Contact
                                    Number</label>
                                <input name="store_contact" type="text"
                                    value="<?php echo htmlspecialchars($store_contact); ?>"
                                    placeholder="e.g. 09XX XXX XXXX"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Email</label>
                                <input name="store_email" type="email"
                                    value="<?php echo htmlspecialchars($store_email); ?>"
                                    placeholder="e.g. hello@eggsy.com"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                            </div>
                            <button type="submit"
                                class="w-full py-3 bg-primary hover:bg-primary/90 text-zinc-900 font-black rounded-xl transition-colors text-sm">
                                Save Store Info
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-center gap-3">
                            <div
                                class="size-10 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-500">
                                <span class="material-symbols-outlined">lock</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white">Change Password</h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">Update your account
                                    password.</p>
                            </div>
                        </div>
                        <form method="POST" class="p-6 space-y-4">
                            <input type="hidden" name="action" value="change_password">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Current
                                    Password</label>
                                <div class="relative">
                                    <input name="current_password" id="current_password" type="password" required
                                        class="w-full px-4 py-3 pr-12 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                                    <button type="button" onclick="togglePassword('current_password', this)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                        <span class="material-symbols-outlined text-base">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">New
                                    Password</label>
                                <div class="relative">
                                    <input name="new_password" id="new_password" type="password" required
                                        class="w-full px-4 py-3 pr-12 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                                    <button type="button" onclick="togglePassword('new_password', this)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                        <span class="material-symbols-outlined text-base">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Confirm
                                    New Password</label>
                                <div class="relative">
                                    <input name="confirm_password" id="confirm_password" type="password" required
                                        class="w-full px-4 py-3 pr-12 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                                    <button type="button" onclick="togglePassword('confirm_password', this)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                        <span class="material-symbols-outlined text-base">visibility</span>
                                    </button>
                                </div>
                            </div>
                            <button type="submit"
                                class="w-full py-3 bg-zinc-900 dark:bg-white hover:bg-zinc-800 dark:hover:bg-zinc-100 text-white dark:text-zinc-900 font-black rounded-xl transition-colors text-sm">
                                Update Password
                            </button>
                        </form>
                    </div>
                    <!-- Backup Card -->
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 dark:border-zinc-800">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Database Backup</h3>
                            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Download a full backup of your
                                database as a .sql file.</p>
                        </div>
                        <div class="p-6 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="size-12 rounded-xl bg-primary/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary">database</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">Full Database Export</p>
                                    <p class="text-xs text-slate-400 mt-0.5">Includes all orders, inventory, products,
                                        staff, and settings.</p>
                                </div>
                            </div>
                            <a href="/pos-system/modules/settings/backup.php"
                                class="bg-primary hover:bg-primary/90 text-zinc-900 px-5 py-2.5 rounded-xl font-bold text-sm inline-flex items-center justify-center gap-2 shadow-sm transition-all whitespace-nowrap">
                                <span class="material-symbols-outlined text-base">download</span>
                                Download Backup
                            </a>
                        </div>
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

        function togglePassword(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('.material-symbols-outlined');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }
    </script>

</body>

</html>