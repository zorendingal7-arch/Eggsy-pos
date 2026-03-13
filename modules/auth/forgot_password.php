<?php
require_once '../../config/session.php';
require_once '../../config/db.php';

$step = 1;
$error = '';
$success = '';
$user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int) ($_POST['step'] ?? 1);

    if ($step === 1) {
        $username = trim($conn->real_escape_string($_POST['username']));
        $user_row = $conn->query("SELECT * FROM users WHERE username='$username'")->fetch_assoc();

        if (!$user_row) {
            $error = 'Username not found.';
            $step = 1;
        } elseif (empty($user_row['security_question'])) {
            $error = 'This account has no security question set. Contact your administrator.';
            $step = 1;
        } else {
            $user = $user_row;
            $step = 2;
        }
    } elseif ($step === 2) {
        $username = trim($conn->real_escape_string($_POST['username']));
        $answer = strtolower(trim($_POST['security_answer']));
        $user_row = $conn->query("SELECT * FROM users WHERE username='$username'")->fetch_assoc();

        if (!$user_row) {
            $error = 'Something went wrong. Try again.';
            $step = 1;
        } elseif (strtolower($user_row['security_answer']) !== $answer) {
            $error = 'Incorrect answer. Try again.';
            $user = $user_row;
            $step = 2;
        } else {
            $user = $user_row;
            $step = 3;
        }
    } elseif ($step === 3) {
        $username = trim($conn->real_escape_string($_POST['username']));
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        $user_row = $conn->query("SELECT * FROM users WHERE username='$username'")->fetch_assoc();

        if (strlen($new_pass) < 6) {
            $error = 'Password must be at least 6 characters.';
            $user = $user_row;
            $step = 3;
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Passwords do not match.';
            $user = $user_row;
            $step = 3;
        } else {
            $new_escaped = $conn->real_escape_string($new_pass);
            $conn->query("UPDATE users SET password='$new_escaped' WHERE username='$username'");
            $success = 'Password reset successfully. You can now log in.';
            $step = 4;
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Forgot Password - Eggsy</title>
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

<body class="bg-background-light min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">

        <!-- Logo -->
        <div class="flex items-center justify-center gap-3 mb-8">
            <img src="/pos-system/assets/images/logo.png" alt="Eggsy" class="size-12 rounded-xl object-cover" />
            <div>
                <h1 class="text-xl font-bold leading-tight text-slate-900">Sandwich House of Eggsy</h1>
                <p class="text-xs text-slate-500">POS and Inventory System</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

            <!-- Progress Steps -->
            <div class="flex border-b border-slate-100">
                <?php
                $steps = ['Username', 'Verify', 'New Password'];
                foreach ($steps as $i => $label):
                    $num = $i + 1;
                    $active = $step === $num;
                    $done = $step > $num;
                    ?>
                    <div class="flex-1 flex flex-col items-center py-4 gap-1 <?php echo $done ? 'bg-primary/5' : ''; ?>">
                        <div
                            class="size-7 rounded-full flex items-center justify-center text-xs font-black
                    <?php echo $done ? 'bg-primary text-zinc-900' : ($active ? 'bg-zinc-900 text-white' : 'bg-slate-100 text-slate-400'); ?>">
                            <?php echo $done ? '✓' : $num; ?>
                        </div>
                        <span
                            class="text-[10px] font-bold uppercase tracking-wider <?php echo $active ? 'text-zinc-900' : 'text-slate-400'; ?>">
                            <?php echo $label; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="p-8">

                <?php if ($error): ?>
                    <div
                        class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm font-bold mb-6">
                        <span class="material-symbols-outlined text-base">error</span>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === 4): ?>
                    <!-- Step 4: Success -->
                    <div class="text-center space-y-4">
                        <div class="size-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto">
                            <span class="material-symbols-outlined text-emerald-500 text-3xl">check_circle</span>
                        </div>
                        <h2 class="text-xl font-black text-slate-900">Password Reset!</h2>
                        <p class="text-slate-500 text-sm"><?php echo $success; ?></p>
                        <a href="/pos-system/modules/auth/login.php"
                            class="block w-full py-3 bg-primary hover:bg-primary/90 text-zinc-900 font-black rounded-xl text-sm text-center transition-colors mt-4">
                            Back to Login
                        </a>
                    </div>

                <?php elseif ($step === 1): ?>
                    <!-- Step 1: Enter Username -->
                    <div class="mb-6">
                        <h2 class="text-xl font-black text-slate-900">Forgot Password</h2>
                        <p class="text-slate-500 text-sm mt-1">Enter your username to continue.</p>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="step" value="1">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Username</label>
                            <div class="relative">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">person</span>
                                <input name="username" type="text" required placeholder="Enter your username"
                                    class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-primary/20" />
                            </div>
                        </div>
                        <button type="submit"
                            class="w-full py-3 bg-primary hover:bg-primary/90 text-zinc-900 font-black rounded-xl text-sm transition-colors">
                            Continue
                        </button>
                    </form>

                <?php elseif ($step === 2): ?>
                    <!-- Step 2: Answer Security Question -->
                    <div class="mb-6">
                        <h2 class="text-xl font-black text-slate-900">Security Question</h2>
                        <p class="text-slate-500 text-sm mt-1">Answer your security question to verify your identity.</p>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="step" value="2">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                        <div class="bg-primary/10 rounded-xl px-4 py-3">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Security Question</p>
                            <p class="text-sm font-bold text-slate-900">
                                <?php echo htmlspecialchars($user['security_question']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Your Answer</label>
                            <div class="relative">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                                <input name="security_answer" type="text" required placeholder="Type your answer"
                                    class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-primary/20" />
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Answer is not case sensitive.</p>
                        </div>
                        <button type="submit"
                            class="w-full py-3 bg-primary hover:bg-primary/90 text-zinc-900 font-black rounded-xl text-sm transition-colors">
                            Verify Answer
                        </button>
                    </form>

                <?php elseif ($step === 3): ?>
                    <!-- Step 3: Set New Password -->
                    <div class="mb-6">
                        <h2 class="text-xl font-black text-slate-900">New Password</h2>
                        <p class="text-slate-500 text-sm mt-1">Set a new password for your account.</p>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="step" value="3">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">New Password</label>
                            <div class="relative">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                                <input name="new_password" id="new_password" type="password" required
                                    placeholder="Min. 6 characters"
                                    class="w-full pl-10 pr-12 py-3 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-primary/20" />
                                <button type="button" onclick="togglePass('new_password', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Confirm Password</label>
                            <div class="relative">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                                <input name="confirm_password" id="confirm_password" type="password" required
                                    placeholder="Repeat new password"
                                    class="w-full pl-10 pr-12 py-3 border border-slate-200 rounded-xl text-sm focus:border-primary focus:ring-primary/20" />
                                <button type="button" onclick="togglePass('confirm_password', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </button>
                            </div>
                        </div>
                        <button type="submit"
                            class="w-full py-3 bg-primary hover:bg-primary/90 text-zinc-900 font-black rounded-xl text-sm transition-colors">
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($step < 4): ?>
                    <div class="mt-6 text-center">
                        <a href="/pos-system/modules/auth/login.php"
                            class="text-sm text-slate-400 hover:text-primary font-bold transition-colors">
                            Back to Login
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script>
        function togglePass(id, btn) {
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