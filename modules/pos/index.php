<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

$categories_query = $conn->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $categories_query->fetch_all(MYSQLI_ASSOC);

$products_query = $conn->query("
    SELECT m.*, c.name as category_name
    FROM menu_items m
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE m.is_available = 1
    ORDER BY c.id ASC, m.subcategory ASC, m.name ASC
");
$products = $products_query->fetch_all(MYSQLI_ASSOC);

$back_url = $_SESSION['role'] === 'admin' ? '/pos-system/dashboard.php' : '/pos-system/cashier_dashboard.php';

// Define subcategories per category name
$subcategories = [
    "Creama's" => ['Mango', 'Avocado', 'New Unlock Series'],
    'Drinks' => ['Original Cold', 'Premium', 'Fruit Tea', 'Original Hot'],
];
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Eggsy POS - Orders</title>
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css" />
    <link rel="stylesheet" href="/pos-system/assets/css/app.css" />
    <style>
        <style>.material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
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
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 transition-colors duration-200 overflow-hidden">
    <div class="flex h-screen">

        <!-- Left: Products -->
        <main class="flex-1 flex flex-col min-w-0">
            <header
                class="h-20 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 flex items-center justify-between px-8">
                <a href="<?php echo $back_url; ?>"
                    class="flex items-center gap-2 text-slate-600 dark:text-zinc-400 hover:text-primary transition-colors font-semibold">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to Dashboard
                </a>
                <div class="flex items-center gap-4">
                    <img src="/pos-system/assets/images/logo.png" alt="Eggsy" class="size-10 rounded-xl object-cover" />
                    <span class="font-bold text-lg dark:text-white">Eggsy POS</span>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">

                <!-- Category Filter -->
                <div class="flex gap-4 mb-8 overflow-x-auto pb-2">
                    <button onclick="filterCategory('all')" data-category="all"
                        class="category-btn whitespace-nowrap px-6 py-3 rounded-xl bg-primary text-zinc-900 font-bold shadow-sm transition-transform active:scale-95">
                        All
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button onclick="filterCategory('<?php echo $cat['id']; ?>')"
                            data-category="<?php echo $cat['id']; ?>"
                            class="category-btn whitespace-nowrap px-6 py-3 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-400 font-bold hover:bg-slate-50 dark:hover:bg-zinc-800 transition-all active:scale-95">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Products Grid -->
                <div id="products-grid">
                    <?php if (empty($products)): ?>
                        <div class="py-20 text-center text-slate-400">
                            <span class="material-symbols-outlined text-5xl mb-3 block">lunch_dining</span>
                            <p class="font-semibold">No products available.</p>
                            <a href="/pos-system/modules/inventory/products.php"
                                class="text-primary text-sm font-bold mt-2 block">Add products in Inventory</a>
                        </div>
                    <?php else: ?>

                        <?php
                        // Group products by category then subcategory
                        $grouped = [];
                        foreach ($products as $product) {
                            $cat_id = $product['category_id'];
                            $cat_name = $product['category_name'];
                            $sub = $product['subcategory'] ?? null;
                            $grouped[$cat_id]['name'] = $cat_name;
                            $grouped[$cat_id]['items'][$sub ?? '__none__'][] = $product;
                        }
                        ?>

                        <?php foreach ($grouped as $cat_id => $cat_data):
                            $cat_name = $cat_data['name'];
                            $has_subs = isset($subcategories[$cat_name]);
                            ?>
                            <div class="category-section mb-10" data-category-section="<?php echo $cat_id; ?>">

                                <?php foreach ($cat_data['items'] as $sub_key => $sub_products):
                                    $sub_label = ($sub_key !== '__none__') ? $sub_key : null;
                                    ?>

                                    <?php if ($sub_label): ?>
                                        <!-- Subcategory Divider -->
                                        <div class="subcategory-divider flex items-center gap-4 mb-4 mt-6"
                                            data-category="<?php echo $cat_id; ?>">
                                            <span
                                                class="text-xs font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 whitespace-nowrap"><?php echo htmlspecialchars($sub_label); ?></span>
                                            <div class="flex-1 h-px bg-slate-200 dark:bg-zinc-700"></div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Products in this sub-section -->
                                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6 mb-4"
                                        data-category="<?php echo $cat_id; ?>">
                                        <?php foreach ($sub_products as $product): ?>
                                            <button onclick="addToOrder(<?php echo htmlspecialchars(json_encode($product)); ?>)"
                                                data-category="<?php echo $product['category_id']; ?>"
                                                class="product-card bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-md flex flex-col overflow-hidden hover:border-primary hover:shadow-lg transition-all group active:scale-95">
                                                <div class="h-32 w-full overflow-hidden p-4 bg-white dark:bg-zinc-900">
                                                    <?php if ($product['image']): ?>
                                                        <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                            class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-300" />
                                                    <?php else: ?>
                                                        <div
                                                            class="w-full h-full flex items-center justify-center text-slate-300 dark:text-zinc-600">
                                                            <span class="material-symbols-outlined text-5xl">lunch_dining</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="p-4 flex flex-col flex-1">
                                                    <h3
                                                        class="font-bold text-slate-900 dark:text-white text-sm leading-tight uppercase tracking-tight mb-2">
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </h3>
                                                    <p class="text-primary font-black text-xl mt-auto">
                                                        ₱<?php echo number_format($product['price'], 2); ?></p>
                                                </div>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>

                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Right: Order Panel -->
        <aside
            class="w-[440px] bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 flex flex-col shadow-2xl relative z-10">
            <div class="p-6 border-b border-slate-200 dark:border-zinc-800">
                <h2 class="text-xl font-bold dark:text-white">Current Order</h2>
                <p class="text-sm text-slate-500 dark:text-zinc-400">Items added to checkout</p>
            </div>

            <!-- Order Items -->
            <div class="flex-1 overflow-y-auto custom-scrollbar" id="order-list">
                <div id="empty-order"
                    class="flex flex-col items-center justify-center h-full py-20 text-slate-300 dark:text-zinc-600">
                    <span class="material-symbols-outlined text-6xl mb-3">shopping_bag</span>
                    <p class="font-semibold text-sm">No items yet</p>
                    <p class="text-xs mt-1">Click a product to add it</p>
                </div>
            </div>

            <!-- Summary -->
            <div class="p-6 bg-slate-50 dark:bg-zinc-800/50 border-t border-slate-200 dark:border-zinc-800 space-y-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500 dark:text-zinc-400">Subtotal</span>
                        <span class="font-bold text-slate-700 dark:text-zinc-300" id="subtotal">₱0.00</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500 dark:text-zinc-400">VAT (12%)</span>
                        <span class="font-bold text-slate-700 dark:text-zinc-300" id="vat">₱0.00</span>
                    </div>
                    <div class="h-px bg-slate-200 dark:bg-zinc-700 my-2"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold dark:text-white">Total</span>
                        <span class="text-2xl font-black text-slate-900 dark:text-white" id="total">₱0.00</span>
                    </div>
                </div>
                <button onclick="checkout()"
                    class="w-full bg-primary hover:bg-primary/90 text-zinc-900 h-16 rounded-2xl font-black text-xl flex items-center justify-center gap-3 shadow-lg shadow-primary/20 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                    id="checkout-btn" disabled>
                    Checkout
                    <span class="material-symbols-outlined font-black">arrow_forward</span>
                </button>
            </div>
        </aside>
    </div>

    <!-- Checkout Modal -->
    <div id="checkout-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6">
            <div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-white">Confirm Order</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm mt-1">Review and process payment.</p>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Subtotal</span>
                    <span class="font-bold dark:text-white" id="modal-subtotal">₱0.00</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">VAT (12%)</span>
                    <span class="font-bold dark:text-white" id="modal-vat">₱0.00</span>
                </div>
                <div class="h-px bg-slate-200 dark:bg-zinc-700"></div>
                <div class="flex justify-between text-lg">
                    <span class="font-bold dark:text-white">Total</span>
                    <span class="font-black text-primary" id="modal-total">₱0.00</span>
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-2">Payment Method</label>
                <div
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 text-sm font-semibold text-slate-700 dark:text-zinc-300 flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">payments</span>
                    Cash
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-2">Amount Received
                    (₱)</label>
                <input type="number" id="cash-received" min="0" step="0.01" placeholder="0.00"
                    class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 text-sm" />
                <p class="text-sm font-bold mt-2 hidden" id="change-display"></p>
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal()"
                    class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-zinc-700 text-sm font-bold text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors">
                    Cancel
                </button>
                <button id="confirm-btn" onclick="confirmOrder()"
                    class="flex-1 py-3 rounded-xl bg-primary font-black text-zinc-900 hover:bg-primary/90 transition-colors">
                    Confirm & Pay
                </button>
            </div>
        </div>
    </div>

    <!-- Stock Error Modal -->
    <div id="stock-error-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md p-8 space-y-6">
            <div class="flex flex-col items-center text-center gap-3">
                <div class="size-16 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-500 text-3xl">inventory_2</span>
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Insufficient Stock</h3>
                <p class="text-slate-500 dark:text-zinc-400 text-sm" id="stock-error-message"></p>
            </div>
            <button onclick="document.getElementById('stock-error-modal').classList.add('hidden')"
                class="w-full py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white font-black text-sm transition-colors">
                Got it
            </button>
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

        let order = {};

        function filterCategory(catId) {
            document.querySelectorAll('.category-btn').forEach(btn => {
                const active = btn.dataset.category == catId;
                btn.classList.toggle('bg-primary', active);
                btn.classList.toggle('text-zinc-900', active);
                btn.classList.toggle('shadow-sm', active);
                btn.classList.toggle('bg-white', !active);
                btn.classList.toggle('dark:bg-zinc-900', !active);
                btn.classList.toggle('border', !active);
                btn.classList.toggle('border-slate-200', !active);
                btn.classList.toggle('text-slate-600', !active);
            });

            document.querySelectorAll('.category-section').forEach(section => {
                if (catId === 'all' || section.dataset.categorySection == catId) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }
            });
        }

        function addToOrder(product) {
            if (order[product.id]) {
                order[product.id].qty += 1;
            } else {
                order[product.id] = { ...product, qty: 1 };
            }
            renderOrder();
        }

        function updateQty(id, delta) {
            if (!order[id]) return;
            order[id].qty += delta;
            if (order[id].qty <= 0) delete order[id];
            renderOrder();
        }

        function removeItem(id) {
            delete order[id];
            renderOrder();
        }

        function renderOrder() {
            const list = document.getElementById('order-list');
            const checkoutBtn = document.getElementById('checkout-btn');
            const items = Object.values(order);

            if (items.length === 0) {
                list.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full py-20 text-slate-300 dark:text-zinc-600">
                    <span class="material-symbols-outlined text-6xl mb-3">shopping_bag</span>
                    <p class="font-semibold text-sm">No items yet</p>
                    <p class="text-xs mt-1">Click a product to add it</p>
                </div>`;
                checkoutBtn.disabled = true;
                updateTotals(0);
                return;
            }

            checkoutBtn.disabled = false;
            let html = '';
            let gross = 0;

            items.forEach(item => {
                const lineTotal = item.price * item.qty;
                gross += lineTotal;
                html += `
                <div class="px-6 py-6 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-start gap-4">
                    <div class="flex-1">
                        <h4 class="font-bold text-base text-slate-900 dark:text-white">${item.name}</h4>
                        <p class="font-bold text-lg text-primary mt-1">₱${lineTotal.toFixed(2)}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1 p-1 bg-slate-50 dark:bg-zinc-800 rounded-xl border border-slate-100 dark:border-zinc-700">
                            <button onclick="updateQty(${item.id}, -1)"
                                class="size-8 flex items-center justify-center rounded-lg bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-400 hover:border-primary hover:text-primary transition-colors active:scale-95">
                                <span class="material-symbols-outlined text-[18px]">remove</span>
                            </button>
                            <div class="w-8 text-center font-bold text-slate-900 dark:text-white text-sm">${item.qty}</div>
                            <button onclick="updateQty(${item.id}, 1)"
                                class="size-8 flex items-center justify-center rounded-lg bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-400 hover:border-primary hover:text-primary transition-colors active:scale-95">
                                <span class="material-symbols-outlined text-[18px]">add</span>
                            </button>
                        </div>
                        <button onclick="removeItem(${item.id})"
                            class="size-10 flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                            <span class="material-symbols-outlined text-xl">delete</span>
                        </button>
                    </div>
                </div>`;
            });

            list.innerHTML = html;
            updateTotals(gross);
        }

        function updateTotals(gross) {
            const vat = gross - (gross / 1.12);
            const subtotal = gross - vat;
            document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('vat').textContent = '₱' + vat.toFixed(2);
            document.getElementById('total').textContent = '₱' + gross.toFixed(2);
        }

        function checkout() {
            const items = Object.values(order);
            if (items.length === 0) return;
            const gross = items.reduce((s, i) => s + i.price * i.qty, 0);
            const vat = gross - (gross / 1.12);
            const subtotal = gross - vat;
            document.getElementById('modal-subtotal').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('modal-vat').textContent = '₱' + vat.toFixed(2);
            document.getElementById('modal-total').textContent = '₱' + gross.toFixed(2);
            document.getElementById('cash-received').value = '';
            document.getElementById('change-display').classList.add('hidden');
            document.getElementById('checkout-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('checkout-modal').classList.add('hidden');
        }

        document.getElementById('cash-received').addEventListener('input', function () {
            const items = Object.values(order);
            const gross = items.reduce((s, i) => s + i.price * i.qty, 0);
            const received = parseFloat(this.value) || 0;
            const change = received - gross;
            const display = document.getElementById('change-display');
            display.classList.remove('hidden');
            if (received > 0 && change >= 0) {
                display.textContent = 'Change: ₱' + change.toFixed(2);
                display.className = 'text-sm font-bold mt-2 text-emerald-600';
            } else if (received > 0) {
                display.textContent = 'Insufficient amount';
                display.className = 'text-sm font-bold mt-2 text-red-500';
            } else {
                display.classList.add('hidden');
            }
        });

        function confirmOrder() {
            const items = Object.values(order);
            if (items.length === 0) return;

            const cashReceived = parseFloat(document.getElementById('cash-received').value) || 0;
            const gross = items.reduce((s, i) => s + i.price * i.qty, 0);

            if (cashReceived < gross) {
                alert('Cash received is less than the total amount.');
                return;
            }

            const vat = gross - (gross / 1.12);
            const subtotal = gross - vat;

            const payload = {
                items: items.map(i => ({ id: i.id, name: i.name, price: i.price, qty: i.qty })),
                subtotal: subtotal.toFixed(2),
                vat: vat.toFixed(2),
                total: gross.toFixed(2),
                payment_method: 'cash',
                cash_received: cashReceived.toFixed(2),
            };

            const confirmBtn = document.getElementById('confirm-btn');
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Processing...';

            fetch('/pos-system/ajax/save_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(res => res.json())
                .then(data => {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Confirm & Pay';
                    if (data.success) {
                        closeModal();
                        order = {};
                        renderOrder();
                        window.location.href = '/pos-system/modules/pos/receipt.php?order_id=' + data.order_id;
                    } else if (data.type === 'stock_error') {
                        closeModal();
                        document.getElementById('stock-error-message').textContent = data.message;
                        document.getElementById('stock-error-modal').classList.remove('hidden');
                    } else {
                        alert('Error: ' + (data.message || 'Could not save order.'));
                    }
                })
                .catch(err => {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Confirm & Pay';
                    alert('Server error: ' + err.message);
                });
        }
    </script>

</body>

</html>