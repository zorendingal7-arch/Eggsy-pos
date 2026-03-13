<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$auto_print = isset($_GET['print']) && $_GET['print'] == '1';

if (!$order_id) {
    header('Location: /pos-system/modules/pos/index.php');
    exit;
}

$order_query = $conn->query("
    SELECT o.*, u.full_name as cashier_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = $order_id
");
$order = $order_query->fetch_assoc();

if (!$order) {
    header('Location: /pos-system/modules/pos/index.php');
    exit;
}

$items_query = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
$items = $items_query->fetch_all(MYSQLI_ASSOC);

$payment_query = $conn->query("SELECT * FROM payments WHERE order_id = $order_id LIMIT 1");
$payment = $payment_query->fetch_assoc();

$gross = floatval($order['total']);
$vat = $gross - ($gross / 1.12);
$subtotal = $gross - $vat;
$cash_received = floatval($payment['cash_received'] ?? 0);
$change_given = floatval($payment['change_given'] ?? 0);

$store_name = $conn->query("SELECT setting_value FROM settings WHERE setting_key='store_name'")->fetch_assoc()['setting_value'] ?? 'Eggsy';
$store_address = $conn->query("SELECT setting_value FROM settings WHERE setting_key='store_address'")->fetch_assoc()['setting_value'] ?? '';
$store_contact = $conn->query("SELECT setting_value FROM settings WHERE setting_key='store_contact'")->fetch_assoc()['setting_value'] ?? '';
$store_email = $conn->query("SELECT setting_value FROM settings WHERE setting_key='store_email'")->fetch_assoc()['setting_value'] ?? '';

$tin_number = '332-263-980-00000';

unset($_SESSION['pending_order']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Receipt - Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css" />
    <link rel="stylesheet" href="/pos-system/assets/css/app.css" />
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .zigzag-bottom {
            background-color: white;
            position: relative;
        }

        .zigzag-bottom::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 100%;
            height: 10px;
            background: linear-gradient(-45deg, transparent 5px, white 5px),
                linear-gradient(45deg, transparent 5px, white 5px);
            background-size: 10px 10px;
        }

        @media print {
            @page {
                margin: 0;
                size: 58mm auto;
            }

            .no-print {
                display: none !important;
            }

            * {
                font-size: 9px !important;
                line-height: 1.3 !important;
            }

            body {
                background: white;
                margin: 0;
                padding: 0;
                width: 58mm;
            }

            .print-shadow {
                box-shadow: none !important;
            }

            .receipt-container {
                width: 58mm;
                max-width: 58mm;
                margin: 0 auto;
                padding: 2mm;
                box-shadow: none;
                border: none;
            }

            h2 {
                font-size: 11px !important;
            }

            .text-xl,
            .text-lg,
            .text-base {
                font-size: 10px !important;
            }

            .text-2xl,
            .text-3xl {
                font-size: 12px !important;
            }

            .p-8,
            .p-6,
            .p-4 {
                padding: 4px !important;
            }

            .mb-4,
            .mb-6,
            .mb-8 {
                margin-bottom: 4px !important;
            }

            .py-4,
            .py-6 {
                padding-top: 3px !important;
                padding-bottom: 3px !important;
            }

            img {
                width: 40px !important;
                height: 40px !important;
            }

            #qr-code-img {
                width: 80px !important;
                height: 80px !important;
            }

            .zigzag-bottom::after {
                display: none;
            }

            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body class="bg-background-neutral min-h-screen flex flex-col items-center justify-start py-12 px-4">

    <!-- Success Header -->
    <div class="flex flex-col items-center mb-10 text-center no-print">
        <div class="bg-success-green/10 p-4 rounded-full mb-4">
            <span class="material-symbols-outlined text-success-green text-6xl block">check_circle</span>
        </div>
        <h1 class="text-3xl font-extrabold text-receipt-text-main leading-tight">
            Order #<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?> Completed!
        </h1>
        <p class="text-receipt-text-muted mt-2 font-medium">Transaction successful. Receipt generated below.</p>
    </div>

    <!-- Receipt -->
    <div class="w-full max-w-[440px] mb-12" id="receipt">
        <div class="zigzag-bottom shadow-xl rounded-t-xl overflow-hidden bg-white print-shadow">

            <!-- Store Header -->
            <div class="p-8 pb-4 flex flex-col items-center border-b border-dashed border-gray-200">
                <div class="mb-4">
                    <img src="/pos-system/assets/images/logo-modified.png"
                        alt="<?php echo htmlspecialchars($store_name); ?>"
                        style="width: 100px; height: 100px; object-fit: contain;" />
                </div>
                <h2 class="text-xl font-bold text-receipt-text-main text-center">
                    <?php echo htmlspecialchars($store_name); ?></h2>
                <p class="text-xs text-receipt-text-muted uppercase tracking-widest mt-1">POS System</p>
                <p class="text-[10px] text-receipt-text-muted mt-2">TIN#: <?php echo $tin_number; ?></p>
            </div>

            <!-- Order Meta -->
            <div class="px-8 py-4 flex justify-between text-xs font-medium text-receipt-text-muted">
                <div class="flex flex-col gap-1">
                    <span>DATE: <?php echo strtoupper(date('M d, Y', strtotime($order['created_at']))); ?></span>
                    <span>TIME: <?php echo strtoupper(date('h:i A', strtotime($order['created_at']))); ?></span>
                </div>
                <div class="flex flex-col gap-1 text-right">
                    <span>CASHIER: <?php echo strtoupper(substr($order['cashier_name'] ?? 'N/A', 0, 12)); ?></span>
                    <span>ORDER #<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></span>
                </div>
            </div>

            <!-- Items -->
            <div class="px-8 py-4 space-y-3">
                <?php foreach ($items as $item):
                    $bundle_components = [];
                    $item_cat_query = $conn->query("
                        SELECT c.name as cat_name FROM menu_items m
                        LEFT JOIN categories c ON m.category_id = c.id
                        WHERE m.id = " . intval($item['menu_item_id'])
                    );
                    $item_cat = $item_cat_query ? $item_cat_query->fetch_assoc() : null;
                    if ($item_cat && strtolower($item_cat['cat_name']) === 'bundle') {
                        $comp_query = $conn->query("
                            SELECT m.name FROM product_ingredients pi
                            JOIN menu_items m ON pi.inventory_id = m.id
                            WHERE pi.product_id = " . intval($item['menu_item_id'])
                        );
                        if ($comp_query) {
                            $bundle_components = $comp_query->fetch_all(MYSQLI_ASSOC);
                        }
                    }
                    ?>
                    <div class="flex justify-between items-start">
                        <div class="flex flex-col flex-1 pr-4">
                            <div class="flex items-baseline gap-2">
                                <span class="text-sm font-bold text-receipt-text-main"><?php echo $item['quantity']; ?></span>
                                <span class="text-sm font-bold text-receipt-text-main"><?php echo htmlspecialchars($item['name']); ?></span>
                            </div>
                            <?php if (!empty($bundle_components)): ?>
                                <div class="mt-1 space-y-0.5 pl-4">
                                    <?php foreach ($bundle_components as $index => $comp): ?>
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-[10px] text-receipt-text-muted"><?php echo $index + 1; ?></span>
                                            <span class="text-[10px] text-receipt-text-muted"><?php echo htmlspecialchars($comp['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm font-bold text-receipt-text-main">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Totals -->
            <div class="px-8 py-6 bg-gray-50 border-t border-dashed border-gray-200 space-y-2">
                <div class="flex justify-between text-sm text-receipt-text-muted">
                    <span>Subtotal (VAT excl.)</span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="flex justify-between text-sm text-receipt-text-muted">
                    <span>VAT (12%)</span>
                    <span>₱<?php echo number_format($vat, 2); ?></span>
                </div>
                <div class="flex justify-between text-xl font-extrabold text-receipt-text-main pt-2">
                    <span>TOTAL</span>
                    <span>₱<?php echo number_format($gross, 2); ?></span>
                </div>
            </div>

            <!-- Payment -->
            <div class="px-8 py-6 border-t border-dashed border-gray-200 space-y-1">
                <div class="flex justify-between text-sm font-medium text-receipt-text-main">
                    <span>Cash Received</span>
                    <span>₱<?php echo number_format($cash_received, 2); ?></span>
                </div>
                <div class="flex justify-between text-lg font-bold text-success-green">
                    <span>Change</span>
                    <span>₱<?php echo number_format($change_given, 2); ?></span>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-8 pt-4 pb-8 flex flex-col items-center border-t border-dashed border-gray-200">
                <?php if ($store_address || $store_contact || $store_email): ?>
                    <div class="flex flex-col items-center gap-0.5 mb-4 text-center">
                        <?php if ($store_address): ?>
                            <p class="text-[10px] text-receipt-text-muted"><?php echo htmlspecialchars($store_address); ?></p>
                        <?php endif; ?>
                        <?php if ($store_contact): ?>
                            <p class="text-[10px] text-receipt-text-muted"><?php echo htmlspecialchars($store_contact); ?></p>
                        <?php endif; ?>
                        <?php if ($store_email): ?>
                            <p class="text-[10px] text-receipt-text-muted"><?php echo htmlspecialchars($store_email); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <p class="text-[10px] text-receipt-text-muted font-bold tracking-[0.2em] mb-4">THANK YOU FOR YOUR ORDER!</p>
                <div class="w-full flex flex-col items-center gap-2">
                    <img src="/pos-system/assets/images/facebook-qr.png"
                        alt="Facebook QR Code"
                        id="qr-code-img"
                        style="width:100px;height:100px;"/>
                    <p class="text-[9px] text-receipt-text-muted tracking-widest">SCAN TO VISIT OUR PAGE</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row gap-4 w-full max-w-[440px] no-print">
        <a href="/pos-system/modules/pos/index.php"
            class="flex-1 flex items-center justify-center gap-2 bg-primary hover:bg-primary/90 text-receipt-text-main h-14 rounded-xl font-bold transition-all shadow-lg active:scale-95">
            <span class="material-symbols-outlined">add_shopping_cart</span>
            Start New Order
        </a>
        <button onclick="window.print()"
            class="flex-1 flex items-center justify-center gap-2 bg-white border-2 border-receipt-text-main/10 hover:border-primary text-receipt-text-main h-14 rounded-xl font-bold transition-all shadow-md active:scale-95">
            <span class="material-symbols-outlined">print</span>
            Print Receipt
        </button>
    </div>

</body>

</html>
<?php if ($auto_print): ?>
    <script>window.onload = function () { setTimeout(() => window.print(), 800); }</script>
<?php endif; ?>

