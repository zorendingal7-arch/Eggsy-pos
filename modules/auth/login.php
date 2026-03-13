<?php
require_once '../../config/session.php';
require_once '../../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'cashier') {
                $today = date('Y-m-d');
                $user_id = $user['id'];
                $existing = $conn->query("SELECT id FROM cash_sessions WHERE user_id = $user_id AND session_date = '$today' LIMIT 1");
                if ($existing->num_rows > 0) {
                    header('Location: /pos-system/loading.php');
                } else {
                    header('Location: /pos-system/modules/pos/start_shift.php');
                }
            } else {
                header('Location: /pos-system/loading.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Eggsy System Portal Login</title>
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
    class="bg-background-light dark:bg-background-dark min-h-screen flex items-center justify-center p-6 transition-colors duration-300">

    <div
        class="w-full max-w-[1000px] flex flex-col md:flex-row bg-white dark:bg-[#2d281a] rounded-xl shadow-2xl overflow-hidden min-h-[600px]">

        <!-- Left Panel -->
        <div class="hidden md:flex md:w-1/2 bg-primary relative overflow-hidden flex-col p-12">
            <div class="z-10">
                <div class="flex items-center gap-2 mb-8">
                    <img src="/pos-system/assets/images/logo.png" alt="Eggsy" class="size-12 rounded-xl object-cover" />
                    <h2 class="font-brand text-2xl font-bold text-background-dark tracking-tight">Sandwich House of
                        Eggsy.</h2>
                </div>
                <h1 class="font-brand text-5xl font-bold text-background-dark leading-tight mb-4">The best breakfast
                    starts between two slices.</h1>
                <p class="text-background-dark/80 text-lg font-medium">System Portal Access for Authorized Personnel and
                    Registered Partners.</p>
            </div>
            <div class="absolute bottom-[-10%] right-[-10%] opacity-20 pointer-events-none">
                <span class="material-symbols-outlined text-[300px] text-background-dark">lunch_dining</span>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="flex-1 p-8 md:p-16 flex flex-col justify-center">

            <!-- Mobile Logo -->
            <div class="md:hidden flex items-center gap-2 mb-8">
                <img src="/pos-system/assets/images/logo.png" alt="Eggsy" class="size-12 rounded-xl object-cover" />
                <h2 class="font-brand text-xl font-bold text-background-dark dark:text-white">Eggsy Portal</h2>
            </div>

            <div class="mb-10 text-center md:text-left">
                <h2 class="font-brand text-3xl font-bold text-slate-900 dark:text-white mb-2">Welcome Back</h2>
                <p class="text-slate-500 dark:text-slate-400 font-medium">Please enter your system credentials.</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div
                    class="mb-6 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm font-medium flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">error</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="space-y-6">

                <!-- Username -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2"
                        for="username">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <span class="material-symbols-outlined text-xl">person</span>
                        </span>
                        <input
                            class="block w-full pl-10 pr-3 py-3 border border-slate-200 dark:border-slate-700 rounded-lg bg-background-light dark:bg-[#1a170c] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
                            id="username" name="username" placeholder="Username" required type="text"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" />
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300"
                            for="password">Password</label>
                        <a class="text-xs font-semibold text-primary-dark hover:text-primary transition-colors"
                            href="/pos-system/modules/auth/forgot_password.php">Forgot?</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <span class="material-symbols-outlined text-xl">lock</span>
                        </span>
                        <input
                            class="block w-full pl-10 pr-10 py-3 border border-slate-200 dark:border-slate-700 rounded-lg bg-background-light dark:bg-[#1a170c] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all"
                            id="password" name="password" placeholder="••••••••" required type="password" />
                        <button
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-primary transition-colors"
                            type="button" onclick="togglePassword()">
                            <span class="material-symbols-outlined text-xl" id="toggle-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <input class="h-4 w-4 text-primary focus:ring-primary border-slate-300 rounded cursor-pointer"
                        id="remember-me" name="remember-me" type="checkbox" <?php echo isset($_POST['remember-me']) ? 'checked' : ''; ?> />
                    <label class="ml-2 block text-sm text-slate-600 dark:text-slate-400 cursor-pointer"
                        for="remember-me">Remember me</label>
                </div>

                <!-- Submit -->
                <button
                    class="w-full flex justify-center py-4 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-background-dark bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all uppercase tracking-widest"
                    type="submit">
                    Log In
                </button>

            </form>
        </div>
    </div>

    <!-- Dark Mode Toggle -->
    <div class="fixed bottom-6 right-6">
        <button
            class="p-3 rounded-full bg-white dark:bg-slate-800 shadow-lg text-slate-800 dark:text-white border border-slate-200 dark:border-slate-700 flex items-center justify-center"
            onclick="document.documentElement.classList.toggle('dark')">
            <span class="material-symbols-outlined dark:hidden">dark_mode</span>
            <span class="material-symbols-outlined hidden dark:block text-primary">light_mode</span>
        </button>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggle-icon');
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