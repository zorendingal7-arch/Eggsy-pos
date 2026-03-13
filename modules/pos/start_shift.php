<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

$today = date('Y-m-d');
$user_id = $_SESSION['user_id'];

// Check if already has a session today
$existing = $conn->query("SELECT id FROM cash_sessions WHERE user_id = $user_id AND session_date = '$today' LIMIT 1");
if ($existing->num_rows > 0) {
    header('Location: /pos-system/modules/pos/index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $starting_cash = floatval($_POST['starting_cash']);
    $conn->query("INSERT INTO cash_sessions (user_id, starting_cash, session_date) VALUES ($user_id, $starting_cash, '$today')");
    header('Location: /pos-system/modules/pos/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Start Shift - Eggsy POS</title>
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css" />
    <link rel="stylesheet" href="/pos-system/assets/css/app.css" />
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-10 space-y-8">

        <!-- Header -->
        <div class="flex flex-col items-center text-center gap-3">
            <img src="/pos-system/assets/images/logo.png" alt="Eggsy" class="size-16 rounded-xl object-cover" />
            <div>
                <h1 class="text-2xl font-black text-slate-900">Start of Shift</h1>
                <p class="text-slate-500 text-sm mt-1">Enter the starting cash in the drawer before proceeding.</p>
            </div>
        </div>

        <!-- Cashier Info -->
        <div class="bg-slate-50 rounded-xl px-5 py-4 flex items-center gap-3">
            <div class="size-10 rounded-full bg-primary/20 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary">person</span>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-900">
                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
                <p class="text-xs text-slate-500"><?php echo date('F d, Y'); ?></p>
            </div>
        </div>

        <!-- Form -->
        <form method="POST" class="space-y-6">
            <div class="space-y-2">
                <label class="text-sm font-bold text-slate-700">Starting Cash Amount</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-bold">₱</span>
                    <input type="number" name="starting_cash" min="0" step="0.01" placeholder="0.00" required
                        class="w-full pl-8 pr-4 py-4 text-xl font-bold border-2 border-slate-200 rounded-xl focus:border-primary focus:ring-0 outline-none transition-colors" />
                </div>
            </div>
            <button type="submit"
                class="w-full py-4 bg-primary hover:bg-primary/90 text-zinc-900 font-black text-base rounded-xl transition-all shadow-sm active:scale-95">
                Start Shift
            </button>
        </form>

    </div>

</body>

</html>