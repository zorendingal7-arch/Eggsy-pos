<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full_name = trim($conn->real_escape_string($_POST['full_name']));
        $username = trim($conn->real_escape_string($_POST['username']));
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'] === 'admin' ? 'admin' : 'cashier';
        $security_question = $conn->real_escape_string(trim($_POST['security_question'] ?? ''));
        $security_answer = strtolower($conn->real_escape_string(trim($_POST['security_answer'] ?? '')));
        $conn->query("INSERT INTO users (full_name, username, password, role, status, security_question, security_answer, created_at) VALUES ('$full_name', '$username', '$password', '$role', 'active', '$security_question', '$security_answer', NOW())");
    }

    if ($action === 'edit') {
        $id = (int) $_POST['id'];
        $full_name = trim($conn->real_escape_string($_POST['full_name']));
        $username = trim($conn->real_escape_string($_POST['username']));
        $role = $_POST['role'] === 'admin' ? 'admin' : 'cashier';
        $security_question = $conn->real_escape_string(trim($_POST['security_question'] ?? ''));
        $security_answer = strtolower($conn->real_escape_string(trim($_POST['security_answer'] ?? '')));
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET full_name='$full_name', username='$username', password='$password', role='$role' WHERE id=$id");
        } else {
            $conn->query("UPDATE users SET full_name='$full_name', username='$username', role='$role' WHERE id=$id");
        }
    }

    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        if ($id !== (int) $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id=$id");
        }
    }

    if ($action === 'toggle') {
        $id = (int) $_POST['id'];
        if ($id !== (int) $_SESSION['user_id']) {
            $conn->query("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=$id");
        }
    }

    header('Location: /pos-system/modules/users/index.php');
    exit;
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$limit = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
if ($search)
    $where .= " AND (full_name LIKE '%$search%' OR username LIKE '%$search%' OR role LIKE '%$search%')";

$total_users = $conn->query("SELECT COUNT(*) as c FROM users $where")->fetch_assoc()['c'];
$total_admins = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$total_cashiers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='cashier'")->fetch_assoc()['c'];
$total_pages = ceil($total_users / $limit);

$users_query = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$users = $users_query->fetch_all(MYSQLI_ASSOC);

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
    <title>Staff Management - Eggsy Admin</title>
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
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-primary/10 text-primary dark:bg-primary/20 transition-colors"
                    href="/pos-system/modules/users/index.php">
                    <span class="material-symbols-outlined">group</span>
                    <span class="text-sm font-semibold">Staff</span>
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

            <div class="flex-1 overflow-y-auto p-8 space-y-8">

                <!-- Page Title -->
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Staff Management
                        </h2>
                        <p class="text-slate-500 dark:text-zinc-400 mt-1">System accounts for admin and cashier roles.
                        </p>
                    </div>
                    <button onclick="openAddModal()"
                        class="bg-primary hover:bg-primary/90 text-zinc-900 px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition-all">
                        <span class="material-symbols-outlined text-xl">person_add</span>
                        Add New Staff
                    </button>
                </div>

                <!-- Stat Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm flex items-center gap-5">
                        <div
                            class="size-14 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-500">
                            <span class="material-symbols-outlined text-3xl">group</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">
                                Total Accounts</p>
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white"><?php echo $total_users; ?>
                            </h3>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm flex items-center gap-5">
                        <div class="size-14 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined text-3xl">admin_panel_settings</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">
                                Admins</p>
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white"><?php echo $total_admins; ?>
                            </h3>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm flex items-center gap-5">
                        <div
                            class="size-14 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center text-slate-500">
                            <span class="material-symbols-outlined text-3xl">point_of_sale</span>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">
                                Cashiers</p>
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white"><?php echo $total_cashiers; ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div
                    class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800">
                        <form method="GET" class="flex items-center gap-3">
                            <div class="relative flex-1 max-w-md">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                                <input
                                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-zinc-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/50 text-slate-900 dark:text-white placeholder:text-slate-400 transition-all"
                                    name="search" placeholder="Search by name, username or role..." type="text"
                                    value="<?php echo htmlspecialchars($search); ?>" />
                            </div>
                            <button type="submit"
                                class="px-4 py-2.5 bg-primary rounded-lg text-sm font-bold text-zinc-900">Search</button>
                            <?php if ($search): ?>
                                <a href="/pos-system/modules/users/index.php"
                                    class="px-4 py-2.5 bg-slate-100 dark:bg-zinc-800 rounded-lg text-sm font-medium text-slate-600 dark:text-zinc-400">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-800 border-b border-slate-100 dark:border-zinc-700">
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Account</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Username</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Role
                                    </th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Status</th>
                                    <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Created</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">No staff found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user):
                                        $is_me = ($user['id'] == $_SESSION['user_id']);
                                        $initials = strtoupper(substr($user['full_name'], 0, 2));
                                        $colors = ['bg-blue-100 text-blue-700', 'bg-purple-100 text-purple-700', 'bg-green-100 text-green-700', 'bg-rose-100 text-rose-700'];
                                        $color = $colors[$user['id'] % count($colors)];
                                        ?>
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="size-9 rounded-full <?php echo $color; ?> flex items-center justify-center text-xs font-black">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                    <div>
                                                        <p class="font-bold text-slate-900 dark:text-white text-sm">
                                                            <?php echo htmlspecialchars($user['full_name']); ?></p>
                                                        <?php if ($is_me): ?>
                                                            <span
                                                                class="text-[10px] font-bold bg-primary/20 text-primary px-2 py-0.5 rounded-full">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-mono text-slate-600 dark:text-zinc-400">
                                                <?php echo htmlspecialchars($user['username']); ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span
                                                        class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full bg-primary/20 text-primary">Admin</span>
                                                <?php else: ?>
                                                    <span
                                                        class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Cashier</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                                    <span class="flex items-center gap-1.5 text-sm font-semibold text-emerald-600">
                                                        <span class="size-2 rounded-full bg-emerald-500"></span> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-400">
                                                        <span class="size-2 rounded-full bg-slate-300"></span> Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-zinc-400">
                                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <?php if (!$is_me): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="toggle">
                                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit"
                                                                class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 text-slate-400 hover:text-primary transition-colors"
                                                                title="Toggle status">
                                                                <span class="material-symbols-outlined text-base">swap_horiz</span>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button
                                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                        class="p-1.5 rounded-lg hover:bg-primary/10 text-slate-400 hover:text-primary transition-colors">
                                                        <span class="material-symbols-outlined text-base">edit</span>
                                                    </button>
                                                    <?php if (!$is_me): ?>
                                                        <button
                                                            onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['full_name'])); ?>', '<?php echo $user['role']; ?>')"
                                                            class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors">
                                                            <span class="material-symbols-outlined text-base">delete</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-6 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                        <p class="text-sm text-slate-500 dark:text-zinc-400">
                            Showing <span
                                class="font-bold text-slate-900 dark:text-white"><?php echo min($offset + 1, max($total_users, 1)); ?>-<?php echo min($offset + $limit, $total_users); ?></span>
                            of <span class="font-bold text-slate-900 dark:text-white"><?php echo $total_users; ?></span>
                            accounts
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

            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="add-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6">
            <div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Add New Staff</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm mt-1">Create a new system account.</p>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Full Name</label>
                    <input name="full_name" required type="text" placeholder="e.g. Juan Dela Cruz"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Username</label>
                    <input name="username" required type="text" placeholder="e.g. jdelacruz"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Password</label>
                    <input name="password" required type="password" placeholder="Min. 6 characters"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Role</label>
                    <select name="role"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm">
                        <option value="cashier">Cashier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Security
                        Question</label>
                    <input name="security_question" type="text" placeholder="e.g. What is your pet's name?"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Security
                        Answer</label>
                    <input name="security_answer" type="text" placeholder="Answer (not case sensitive)"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeAddModal()"
                        class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-sm font-bold text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">Cancel</button>
                    <button type="submit"
                        class="flex-1 py-3 rounded-xl bg-primary font-black text-zinc-900 hover:bg-primary/90 transition-colors">Add
                        Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6">
            <div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Edit Staff</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm mt-1">Update account details.</p>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Full Name</label>
                    <input name="full_name" id="edit-full-name" required type="text"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Username</label>
                    <input name="username" id="edit-username" required type="text"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">New Password <span
                            class="text-slate-400 font-normal">(leave blank to keep current)</span></label>
                    <input name="password" type="password" placeholder="Leave blank to keep current"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Role</label>
                    <select name="role" id="edit-role"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm">
                        <option value="cashier">Cashier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Security
                        Question</label>
                    <input name="security_question" type="text" placeholder="e.g. What is your pet's name?"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-1">Security
                        Answer</label>
                    <input name="security_answer" type="text" placeholder="Answer (not case sensitive)"
                        class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-sm font-bold text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">Cancel</button>
                    <button type="submit"
                        class="flex-1 py-3 rounded-xl bg-primary font-black text-zinc-900 hover:bg-primary/90 transition-colors">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Warning Modal -->
    <div id="delete-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-sm p-8 space-y-6">
            <div class="flex flex-col items-center text-center gap-3">
                <div class="size-16 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-500 text-3xl">warning</span>
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Delete Account</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm" id="delete-modal-message">
                    Are you sure you want to delete this account?
                </p>
                <div id="delete-cashier-warning"
                    class="hidden w-full bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3">
                    <p class="text-xs font-bold text-red-600 dark:text-red-400 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">error</span>
                        This cashier account will be permanently removed. All order history tied to this account will
                        remain but the account cannot be recovered.
                    </p>
                </div>
            </div>
            <form method="POST" id="delete-form">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete-id">
                <div class="flex gap-3">
                    <button type="button" onclick="closeDeleteModal()"
                        class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-sm font-bold text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white font-black text-sm transition-colors">
                        Yes, Delete
                    </button>
                </div>
            </form>
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
    <div class="fixed bottom-6 right-6 z-10">
        <button
            class="size-12 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl flex items-center justify-center transition-transform hover:scale-110 active:scale-95"
            onclick="toggleDark()">
            <span class="material-symbols-outlined dark:hidden">dark_mode</span>
            <span class="material-symbols-outlined hidden dark:block">light_mode</span>
        </button>
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

        function openAddModal() {
            document.getElementById('add-modal').classList.remove('hidden');
        }
        function closeAddModal() {
            document.getElementById('add-modal').classList.add('hidden');
        }

        function openEditModal(user) {
            document.getElementById('edit-id').value = user.id;
            document.getElementById('edit-full-name').value = user.full_name;
            document.getElementById('edit-username').value = user.username;
            document.getElementById('edit-role').value = user.role;
            document.getElementById('edit-modal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
        }

        function openDeleteModal(id, name, role) {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-modal-message').textContent =
                'You are about to delete the account of ' + name + '. This action cannot be undone.';
            const warning = document.getElementById('delete-cashier-warning');
            if (role === 'cashier') {
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
            document.getElementById('delete-modal').classList.remove('hidden');
        }
        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.add('hidden');
        }
    </script>

</body>

</html>