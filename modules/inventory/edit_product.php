<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: /pos-system/modules/inventory/products.php');
    exit;
}

$product_query = $conn->query("
    SELECT m.*, c.name as category_name
    FROM menu_items m
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE m.id = $id
");
$product = $product_query->fetch_assoc();

if (!$product) {
    header('Location: /pos-system/modules/inventory/products.php');
    exit;
}

$existing_ingredients_query = $conn->query("
    SELECT pi.*, i.name as ingredient_name, i.unit
    FROM product_ingredients pi
    JOIN inventory i ON pi.inventory_id = i.id
    WHERE pi.product_id = $id
");
$existing_ingredients = $existing_ingredients_query->fetch_all(MYSQLI_ASSOC);

$subcategory_map = [
    "Creama's" => ['Mango', 'Avocado', 'New Unlock Series'],
    'Drinks'   => ['Original Cold', 'Premium', 'Fruit Tea', 'Original Hot'],
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($conn->real_escape_string($_POST['name'] ?? ''));
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $price        = floatval($_POST['price'] ?? 0);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $subcategory  = trim($conn->real_escape_string($_POST['subcategory'] ?? ''));
    $ingredients  = $_POST['ingredient_id'] ?? [];
    $quantities   = $_POST['quantity'] ?? [];

    if (!$name) $errors[] = 'Product name is required.';
    if (!$category_id) $errors[] = 'Category is required.';
    if ($price <= 0) $errors[] = 'Base price must be greater than 0.';

    $image_path = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024;
        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = 'Image must be JPG, PNG, or WEBP.';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'Image must be under 5MB.';
        } else {
            $ext        = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename   = uniqid('product_') . '.' . $ext;
            $upload_dir = '../../assets/images/products/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename);
            $image_path = '/pos-system/assets/images/products/' . $filename;
        }
    }

    if (empty($errors)) {
        $img_sql = $image_path ? "'$image_path'" : "NULL";
        $sub_sql = $subcategory ? "'$subcategory'" : "NULL";
        $conn->query("UPDATE menu_items SET
            name = '$name',
            category_id = $category_id,
            price = $price,
            image = $img_sql,
            subcategory = $sub_sql,
            is_available = $is_available
            WHERE id = $id");

        $conn->query("DELETE FROM product_ingredients WHERE product_id = $id");
        foreach ($ingredients as $i => $ing_id) {
            $ing_id = (int)$ing_id;
            $qty    = floatval($quantities[$i] ?? 1);
            if ($ing_id > 0) {
                $conn->query("INSERT INTO product_ingredients (product_id, inventory_id, quantity)
                    VALUES ($id, $ing_id, $qty)");
            }
        }

        header('Location: /pos-system/modules/inventory/products.php?updated=1');
        exit;
    }
}

$categories_query = $conn->query("SELECT * FROM categories ORDER BY id ASC");
$categories       = $categories_query->fetch_all(MYSQLI_ASSOC);

$inventory_query = $conn->query("SELECT * FROM inventory ORDER BY name ASC");
$inventory_items = $inventory_query->fetch_all(MYSQLI_ASSOC);

// Fetch existing products for bundle selection (exclude self)
$products_query = $conn->query("SELECT m.id, m.name, c.name as category_name FROM menu_items m LEFT JOIN categories c ON m.category_id = c.id WHERE m.is_available = 1 AND m.id != $id ORDER BY m.name ASC");
$all_products   = $products_query->fetch_all(MYSQLI_ASSOC);

$current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$inventory_pages = [
    '/pos-system/modules/inventory/index.php',
    '/pos-system/modules/inventory/add.php',
    '/pos-system/modules/inventory/add_stock.php',
    '/pos-system/modules/inventory/add_product.php',
    '/pos-system/modules/inventory/edit_product.php',
    '/pos-system/modules/inventory/edit.php',
    '/pos-system/modules/inventory/products.php',
];
$inv_open = in_array($current, $inventory_pages);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Edit Product - Eggsy Admin</title>
    <link rel="stylesheet" href="/pos-system/assets/css/fonts.css"/>
<link rel="stylesheet" href="/pos-system/assets/css/app.css"/>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 transition-colors duration-200">
<div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 flex-shrink-0 bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-zinc-800 flex flex-col">
        <div class="p-6 flex items-center gap-3">
            <img src="/pos-system/assets/images/logo.png" alt="Eggsy" class="size-10 rounded-xl object-cover"/>
            <div>
                <h1 class="text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-white">Eggsy Admin</h1>
            </div>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-1">
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors" href="/pos-system/dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="text-sm font-medium">Overview</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors" href="/pos-system/modules/pos/index.php">
                <span class="material-symbols-outlined">shopping_bag</span>
                <span class="text-sm font-medium">Orders</span>
            </a>
            <div>
                <button id="inv-toggle" class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg <?php echo $inv_open ? 'bg-primary/10 text-primary dark:bg-primary/20' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'; ?> transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <span class="text-sm font-semibold">Inventory</span>
                    </div>
                    <span class="material-symbols-outlined text-sm transition-transform <?php echo $inv_open ? 'rotate-180' : ''; ?>" id="inv-arrow">expand_more</span>
                </button>
                <div id="inv-submenu" class="mt-1 ml-4 border-l-2 border-slate-100 dark:border-zinc-700 pl-4 space-y-1 <?php echo $inv_open ? '' : 'hidden'; ?>">
                    <a class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo $current === '/pos-system/modules/inventory/index.php' ? 'text-primary bg-primary/10 dark:bg-primary/20' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'; ?> transition-colors" href="/pos-system/modules/inventory/index.php">
                        <span class="material-symbols-outlined text-base">list</span>
                        <span class="text-sm font-medium">All Ingredients</span>
                    </a>
                    <a class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo $current === '/pos-system/modules/inventory/products.php' ? 'text-primary bg-primary/10 dark:bg-primary/20' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800'; ?> transition-colors" href="/pos-system/modules/inventory/products.php">
                        <span class="material-symbols-outlined text-base">lunch_dining</span>
                        <span class="text-sm font-medium">All Products</span>
                    </a>
                </div>
            </div>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors" href="/pos-system/modules/users/index.php">
                <span class="material-symbols-outlined">group</span>
                <span class="text-sm font-medium">Staff</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors" href="/pos-system/modules/reports/index.php">
                <span class="material-symbols-outlined">bar_chart</span>
                <span class="text-sm font-medium">Reports</span>
            </a>
        </nav>
        <div class="p-4 border-t border-slate-200 dark:border-zinc-800">
            <a class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors" href="/pos-system/modules/settings/index.php">
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
        <header class="h-16 flex items-center justify-end px-8 bg-white dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800">
            <div class="flex items-center gap-4">
                <div class="h-8 w-px bg-slate-200 dark:bg-zinc-800 mx-2"></div>
                <div class="flex items-center gap-3 pl-2">
                    <div class="text-right">
                        <p class="text-sm font-semibold leading-none text-slate-900 dark:text-white"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
                        <p class="text-xs text-slate-500 mt-1"><?php echo ucfirst($_SESSION['role']); ?></p>
                    </div>
                    <div class="size-10 rounded-full bg-primary flex items-center justify-center border-2 border-primary/20">
                        <span class="material-symbols-outlined text-zinc-900">person</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 bg-background-light dark:bg-background-dark">
            <div class="max-w-3xl mx-auto">

                <a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-primary mb-6 transition-colors" href="/pos-system/modules/inventory/products.php">
                    <span class="material-symbols-outlined text-lg">arrow_back</span>
                    Back to Products
                </a>

                <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <?php foreach ($errors as $e): ?>
                    <p class="text-sm text-red-600 font-medium"><?php echo $e; ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="p-8 border-b border-slate-100 dark:border-zinc-800">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Edit Product</h2>
                        <p class="text-slate-500 dark:text-zinc-400 text-sm mt-1">Update the details for <span class="font-bold text-primary"><?php echo htmlspecialchars($product['name']); ?></span>.</p>
                    </div>

                    <form class="p-8 space-y-8" method="POST" enctype="multipart/form-data">

                        <!-- Image Upload -->
                        <div class="space-y-3">
                            <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300">Product Image</label>
                            <div class="flex flex-col md:flex-row items-center gap-6 p-6 border-2 border-dashed border-slate-200 dark:border-zinc-700 rounded-2xl bg-slate-50/50 dark:bg-zinc-800/50 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors cursor-pointer group"
                                onclick="document.getElementById('image-input').click()">
                                <div class="size-32 rounded-xl bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex flex-col items-center justify-center text-slate-400 overflow-hidden">
                                    <?php if ($product['image']): ?>
                                    <img id="image-preview" src="<?php echo htmlspecialchars($product['image']); ?>" alt="Preview" class="w-full h-full object-cover rounded-xl"/>
                                    <?php else: ?>
                                    <span class="material-symbols-outlined text-4xl mb-1 group-hover:text-primary transition-colors" id="preview-icon">image</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider" id="preview-label">Preview</span>
                                    <img id="image-preview" src="" alt="Preview" class="hidden w-full h-full object-cover rounded-xl"/>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 text-center md:text-left">
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Upload Product Photo</h4>
                                    
                                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-3">
                                        <button class="px-4 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg text-xs font-bold text-slate-700 dark:text-zinc-300 shadow-sm hover:border-primary hover:text-primary transition-all flex items-center gap-2" type="button"
                                            onclick="event.stopPropagation(); document.getElementById('image-input').click()">
                                            <span class="material-symbols-outlined text-sm">upload</span>
                                            Change Image
                                        </button>
                                        <span class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">JPG, PNG, WEBP (Max 5MB)</span>
                                    </div>
                                </div>
                            </div>
                            <input type="file" id="image-input" name="image" accept="image/jpeg,image/png,image/webp" class="hidden"/>
                        </div>

                        <!-- Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-2">Product Name</label>
                                <input class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 transition-all text-sm"
                                    name="name" type="text"
                                    value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name']); ?>"/>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-2">Category</label>
                                <select id="category-select" name="category_id" onchange="updateSubcategories()"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 transition-all text-sm">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                        <?php echo (($_POST['category_id'] ?? $product['category_id']) == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-2">Base Price (₱)</label>
                                <input class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 transition-all text-sm"
                                    name="price" placeholder="0.00" step="0.01" type="number" min="0"
                                    value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price']); ?>"/>
                            </div>

                            <!-- Subcategory -->
                            <div class="col-span-2 hidden" id="subcategory-wrapper">
                                <label class="block text-sm font-bold text-slate-700 dark:text-zinc-300 mb-2">Sub-section</label>
                                <select name="subcategory" id="subcategory-select"
                                    class="w-full px-4 py-3 rounded-xl border-slate-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white focus:border-primary focus:ring-primary/20 transition-all text-sm">
                                    <option value="">No sub-section</option>
                                </select>
                            </div>

                            <div class="col-span-2 flex items-center gap-3">
                                <input type="checkbox" name="is_available" id="is_available" value="1"
                                    class="rounded border-slate-300 text-primary focus:ring-primary"
                                    <?php echo ($product['is_available'] ? 'checked' : ''); ?>>
                                <label for="is_available" class="text-sm font-bold text-slate-700 dark:text-zinc-300">Available for ordering</label>
                            </div>
                        </div>

                        <!-- Ingredients (non-bundle) -->
                        <div class="pt-6 border-t border-slate-100 dark:border-zinc-800" id="ingredients-section">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Ingredients</h3>
                                <button class="text-sm font-bold text-primary hover:text-primary/80 flex items-center gap-1 transition-colors" type="button" id="add-ingredient-btn">
                                    <span class="material-symbols-outlined text-lg">add_circle</span>
                                    Add Ingredient
                                </button>
                            </div>
                            <div class="space-y-3" id="ingredients-container"></div>
                            <p class="text-xs text-slate-400 mt-3 hidden" id="no-ingredients-msg">No ingredients added yet.</p>
                        </div>

                        <!-- Bundle Products (bundle only) -->
                        <div class="pt-6 border-t border-slate-100 dark:border-zinc-800 hidden" id="bundle-section">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Bundle Products</h3>
                                <button class="text-sm font-bold text-primary hover:text-primary/80 flex items-center gap-1 transition-colors" type="button" id="add-bundle-product-btn">
                                    <span class="material-symbols-outlined text-lg">add_circle</span>
                                    Add Product
                                </button>
                            </div>
                            <div class="space-y-3" id="bundle-container"></div>
                            <p class="text-xs text-slate-400 mt-3" id="no-bundle-msg">No products added yet. Click "Add Product" to start.</p>
                        </div>

                        <!-- Actions -->
                        <div class="pt-8 flex flex-col sm:flex-row items-center gap-4">
                            <button class="w-full sm:flex-1 bg-primary hover:bg-primary/90 text-zinc-900 py-4 px-6 rounded-xl font-bold text-base shadow-sm transition-all flex items-center justify-center gap-2" type="submit">
                                <span class="material-symbols-outlined">save</span>
                                Save Changes
                            </button>
                            <a href="/pos-system/modules/inventory/products.php" class="w-full sm:w-auto px-8 py-4 text-slate-500 dark:text-zinc-400 font-bold text-sm hover:bg-slate-50 dark:hover:bg-zinc-800 rounded-xl transition-colors text-center">
                                Cancel
                            </a>
                        </div>

                    </form>
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
            <p class="text-slate-500 dark:text-zinc-400 text-sm">Are you sure you want to log out of your account?</p>
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
    <button class="size-12 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 shadow-xl flex items-center justify-center transition-transform hover:scale-110 active:scale-95"
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

    const invToggle  = document.getElementById('inv-toggle');
    const invSubmenu = document.getElementById('inv-submenu');
    const invArrow   = document.getElementById('inv-arrow');
    invToggle.addEventListener('click', () => {
        invSubmenu.classList.toggle('hidden');
        invArrow.classList.toggle('rotate-180');
    });

    // Image preview
    const imageInput   = document.getElementById('image-input');
    const imagePreview = document.getElementById('image-preview');
    const previewIcon  = document.getElementById('preview-icon');
    const previewLabel = document.getElementById('preview-label');
    imageInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                imagePreview.src = e.target.result;
                imagePreview.classList.remove('hidden');
                if (previewIcon) previewIcon.classList.add('hidden');
                if (previewLabel) previewLabel.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    const subcategoryMap      = <?php echo json_encode($subcategory_map); ?>;
    const savedSubcategory    = <?php echo json_encode($product['subcategory'] ?? ''); ?>;
    const inventoryItems      = <?php echo json_encode($inventory_items); ?>;
    const existingIngredients = <?php echo json_encode($existing_ingredients); ?>;
    const allProducts         = <?php echo json_encode($all_products); ?>;
    const currentCategoryName = <?php echo json_encode($product['category_name'] ?? ''); ?>;

    const ingredientsSection = document.getElementById('ingredients-section');
    const bundleSection      = document.getElementById('bundle-section');
    const container          = document.getElementById('ingredients-container');
    const bundleContainer    = document.getElementById('bundle-container');
    const noMsg              = document.getElementById('no-ingredients-msg');
    const noBundleMsg        = document.getElementById('no-bundle-msg');

    function updateSubcategories() {
        const select    = document.getElementById('category-select');
        const selected  = select.options[select.selectedIndex];
        const catName   = selected ? selected.dataset.name : '';
        const wrapper   = document.getElementById('subcategory-wrapper');
        const subSelect = document.getElementById('subcategory-select');

        if (subcategoryMap[catName]) {
            wrapper.classList.remove('hidden');
            subSelect.innerHTML = '<option value="">No sub-section</option>';
            subcategoryMap[catName].forEach(sub => {
                const sel = sub === savedSubcategory ? 'selected' : '';
                subSelect.innerHTML += `<option value="${sub}" ${sel}>${sub}</option>`;
            });
        } else {
            wrapper.classList.add('hidden');
            subSelect.innerHTML = '<option value="">No sub-section</option>';
        }

        if (catName === 'Bundle') {
            ingredientsSection.classList.add('hidden');
            bundleSection.classList.remove('hidden');
        } else {
            ingredientsSection.classList.remove('hidden');
            bundleSection.classList.add('hidden');
        }
    }

    updateSubcategories();

    // Ingredient rows
    function buildIngredientOptions(selectedId = '') {
        let opts = '<option value="">Select Ingredient</option>';
        inventoryItems.forEach(item => {
            const sel = item.id == selectedId ? 'selected' : '';
            opts += `<option value="${item.id}" ${sel}>${item.name} (${item.unit})</option>`;
        });
        return opts;
    }

    function getUnit(id) {
        const item = inventoryItems.find(i => i.id == id);
        return item ? item.unit : 'pcs';
    }

    function addIngredientRow(ingId = '', qty = 1) {
        noMsg.classList.add('hidden');
        const row = document.createElement('div');
        row.className = 'flex items-center gap-4 p-4 bg-slate-50 dark:bg-zinc-800 rounded-xl border border-slate-100 dark:border-zinc-700';
        row.innerHTML = `
            <div class="flex-1">
                <select name="ingredient_id[]" class="w-full bg-transparent border-none focus:ring-0 text-sm font-medium dark:text-white ingredient-select">
                    ${buildIngredientOptions(ingId)}
                </select>
            </div>
            <div class="w-36">
                <div class="relative">
                    <input name="quantity[]" class="w-full pl-3 pr-12 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg text-sm text-center focus:ring-primary/20 focus:border-primary dark:text-white" type="number" min="0.01" step="0.01" value="${qty}"/>
                    <span class="unit-label absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 uppercase">${getUnit(ingId)}</span>
                </div>
            </div>
            <button class="p-2 text-slate-400 hover:text-red-500 transition-colors remove-row" type="button">
                <span class="material-symbols-outlined">delete</span>
            </button>`;
        row.querySelector('.ingredient-select').addEventListener('change', function () {
            row.querySelector('.unit-label').textContent = getUnit(this.value);
        });
        row.querySelector('.remove-row').addEventListener('click', function () {
            row.remove();
            if (container.children.length === 0) noMsg.classList.remove('hidden');
        });
        container.appendChild(row);
    }

    // Bundle product rows
    function buildProductOptions(selectedId = '') {
        let opts = '<option value="">Select Product</option>';
        allProducts.forEach(p => {
            const sel = p.id == selectedId ? 'selected' : '';
            opts += `<option value="${p.id}" ${sel}>${p.name} (${p.category_name})</option>`;
        });
        return opts;
    }

    function addBundleProductRow(prodId = '', qty = 1) {
        noBundleMsg.classList.add('hidden');
        const row = document.createElement('div');
        row.className = 'flex items-center gap-4 p-4 bg-slate-50 dark:bg-zinc-800 rounded-xl border border-slate-100 dark:border-zinc-700';
        row.innerHTML = `
            <div class="flex-1">
                <select name="ingredient_id[]" class="w-full bg-transparent border-none focus:ring-0 text-sm font-medium dark:text-white">
                    ${buildProductOptions(prodId)}
                </select>
            </div>
            <div class="w-36">
                <div class="relative">
                    <input name="quantity[]" class="w-full pl-3 pr-12 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg text-sm text-center focus:ring-primary/20 focus:border-primary dark:text-white" type="number" min="1" step="1" value="${qty}"/>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 uppercase">pcs</span>
                </div>
            </div>
            <button class="p-2 text-slate-400 hover:text-red-500 transition-colors" type="button" onclick="this.closest('div.flex').remove(); if(bundleContainer.children.length===0) noBundleMsg.classList.remove('hidden')">
                <span class="material-symbols-outlined">delete</span>
            </button>`;
        bundleContainer.appendChild(row);
    }

    // Load existing data based on category
    if (currentCategoryName === 'Bundle') {
        existingIngredients.forEach(ing => addBundleProductRow(ing.inventory_id, ing.quantity));
        if (existingIngredients.length === 0) noBundleMsg.classList.remove('hidden');
    } else {
        if (existingIngredients.length > 0) {
            existingIngredients.forEach(ing => addIngredientRow(ing.inventory_id, ing.quantity));
        } else {
            noMsg.classList.remove('hidden');
        }
    }

    document.getElementById('add-ingredient-btn').addEventListener('click', () => addIngredientRow());
    document.getElementById('add-bundle-product-btn').addEventListener('click', () => addBundleProductRow());
</script>

</body>
</html>