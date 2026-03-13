<?php
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

header('Content-Type: application/json');

try {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || empty($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'No items provided.']);
        exit;
    }

    $total         = floatval($data['total']);
    $cash_received = floatval($data['cash_received'] ?? 0);
    $change_given  = $cash_received - $total;
    $user_id       = (int)$_SESSION['user_id'];

    // 1. Check stock availability BEFORE saving anything
    $stock_errors = [];

    foreach ($data['items'] as $item) {
        $menu_id = (int)$item['id'];
        $qty     = (int)$item['qty'];

        $ing_query = $conn->query("
            SELECT pi.inventory_id, pi.quantity as usage_per_unit, i.name as ingredient_name, i.quantity as current_stock, i.unit
            FROM product_ingredients pi
            JOIN inventory i ON i.id = pi.inventory_id
            WHERE pi.product_id = $menu_id
        ");

        if ($ing_query && $ing_query->num_rows > 0) {
            while ($ing = $ing_query->fetch_assoc()) {
                $required = floatval($ing['usage_per_unit']) * $qty;
                $available = floatval($ing['current_stock']);

                if ($available < $required) {
                    $stock_errors[] = "Not enough stock for \"" . $ing['ingredient_name'] . "\". "
                        . "Need " . $required . " " . $ing['unit'] . ", only " . $available . " " . $ing['unit'] . " available.";
                }
            }
        }
    }

    if (!empty($stock_errors)) {
        echo json_encode([
            'success'  => false,
            'message'  => implode(' ', $stock_errors),
            'type'     => 'stock_error'
        ]);
        exit;
    }

    // 2. Insert order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total, status, created_at) VALUES (?, ?, 'completed', NOW())");
    $stmt->bind_param("id", $user_id, $total);
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Failed to create order.']);
        exit;
    }

    // 3. Insert order items
    foreach ($data['items'] as $item) {
        $menu_id  = (int)$item['id'];
        $name     = $item['name'];
        $price    = floatval($item['price']);
        $qty      = (int)$item['qty'];
        $subtotal = $price * $qty;

        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("iisdid", $order_id, $menu_id, $name, $price, $qty, $subtotal);
        $stmt2->execute();
        $stmt2->close();
    }

    // 4. Deduct inventory
    foreach ($data['items'] as $item) {
        $menu_id = (int)$item['id'];
        $qty     = (int)$item['qty'];

        $ing_query = $conn->query("
            SELECT pi.inventory_id, pi.quantity as usage_per_unit
            FROM product_ingredients pi
            WHERE pi.product_id = $menu_id
        ");

        if ($ing_query && $ing_query->num_rows > 0) {
            while ($ing = $ing_query->fetch_assoc()) {
                $inv_id     = (int)$ing['inventory_id'];
                $deduct_qty = floatval($ing['usage_per_unit']) * $qty;

                $conn->query("
                    UPDATE inventory
                    SET quantity = GREATEST(0, quantity - $deduct_qty)
                    WHERE id = $inv_id
                ");
            }
        }
    }

    // 5. Insert payment
    $stmt3 = $conn->prepare("INSERT INTO payments (order_id, method, amount_paid, change_given, cash_received, paid_at) VALUES (?, 'cash', ?, ?, ?, NOW())");
    $stmt3->bind_param("iddd", $order_id, $total, $change_given, $cash_received);
    $stmt3->execute();
    $stmt3->close();

    echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}