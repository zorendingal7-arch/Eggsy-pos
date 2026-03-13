<?php
require_once 'config/session.php';
require_once 'config/db.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css" />
    <link rel="stylesheet" href="/pos-system/assets/css/app.css" />
    <title>Eggsy - Loading</title>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased">
    <div class="relative flex h-screen w-full flex-col items-center justify-center overflow-hidden">

        <div class="flex flex-col max-w-[480px] w-full px-6 items-center text-center justify-center">

            <!-- Logo -->
            <div class="mb-12 flex flex-col items-center">
                <div
                    class="size-28 bg-primary/10 rounded-full flex items-center justify-center mb-6 overflow-hidden border-4 border-primary/20">
                    <img src="/pos-system/assets/images/logo.png" alt="Eggsy Logo" class="size-20 object-contain"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                    <!-- Fallback icon if image is missing -->
                    <span class="material-symbols-outlined text-5xl text-primary" style="display:none;">egg_alt</span>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Sandwich House of Eggsy
                </h2>
            </div>

            <!-- Loading State -->
            <div class="w-full space-y-8">
                <div class="space-y-2">
                    <h3 class="text-xl font-semibold text-slate-800 dark:text-slate-200">
                        Setting up your workspace...
                    </h3>
                    <p class="text-slate-500 dark:text-slate-400 text-sm">
                        Preparing your delicious experience
                    </p>
                </div>

                <!-- Progress Bar -->
                <div class="flex flex-col gap-3 w-full max-w-sm mx-auto">
                    <div class="flex justify-between items-end">
                        <span class="text-slate-600 dark:text-slate-400 text-sm font-medium"
                            id="loading-label">Authenticating...</span>
                        <span class="text-primary font-bold text-sm" id="loading-percent">0%</span>
                    </div>
                    <div class="h-2 w-full bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full transition-all duration-300" id="progress-bar"
                            style="width: 0%;"></div>
                    </div>
                </div>

                <!-- Status -->
                <div class="flex items-center justify-center gap-2 text-primary/80 dark:text-primary/60">
                    <span class="material-symbols-outlined text-sm animate-pulse">sync</span>
                    <span class="text-xs font-medium uppercase tracking-widest">Please wait a moment</span>
                </div>
            </div>
        </div>

        <!-- Top accent bar -->
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary/20 via-primary to-primary/20"></div>

        <!-- Footer -->
        <footer class="absolute bottom-8 text-slate-400 dark:text-slate-600 text-xs tracking-wide">
            © <?php echo date('Y'); ?> Sandwich House of Eggsy. All rights reserved.
        </footer>
    </div>

    <script>
        const steps = [
            { label: 'Authenticating...', percent: 20, delay: 600 },
            { label: 'Loading inventory...', percent: 40, delay: 900 },
            { label: 'Fetching orders...', percent: 60, delay: 700 },
            { label: 'Preparing dashboard...', percent: 80, delay: 900 },
            { label: 'Almost ready...', percent: 95, delay: 600 },
        ];

        const role = <?php echo json_encode($_SESSION['role']); ?>;
        const destination = role === 'admin'
            ? '/pos-system/dashboard.php'
            : '/pos-system/cashier_dashboard.php';

        let currentStep = 0;
        const bar = document.getElementById('progress-bar');
        const label = document.getElementById('loading-label');
        const percent = document.getElementById('loading-percent');

        function runStep() {
            if (currentStep >= steps.length) {
                bar.style.width = '100%';
                percent.textContent = '100%';
                label.textContent = 'Done!';
                setTimeout(() => {
                    window.location.href = destination;
                }, 400);
                return;
            }

            const step = steps[currentStep];
            bar.style.width = step.percent + '%';
            percent.textContent = step.percent + '%';
            label.textContent = step.label;
            currentStep++;

            setTimeout(runStep, step.delay);
        }

        setTimeout(runStep, 200);
    </script>

</body>

</html>