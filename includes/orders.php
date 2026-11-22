<?php
/** Order, inventory, refund services. All stock changes go through adjust_stock(). */

const ORDER_STATUSES = ['Pending','Confirmed','Processing','Packed','Shipped','Out for Delivery','Delivered','Cancelled','Failed','Returned','Refunded'];

/** Change stock + log it. Caller must already be inside a transaction. */
function adjust_stock(int $productId, int $delta, string $reason, ?int $userId): void
{
    $p = fetch_one('SELECT id,name,stock,low_stock_threshold FROM products WHERE id=? FOR UPDATE', [$productId]);
    if (!$p) throw new RuntimeException('Product not found.');
    $new = $p['stock'] + $delta;
    if ($new < 0) throw new RuntimeException("Not enough stock for {$p['name']}.");
    q('UPDATE products SET stock=? WHERE id=?', [$new, $productId]);
    q('INSERT INTO inventory_transactions (product_id,change_qty,previous_stock,new_stock,reason,user_id) VALUES (?,?,?,?,?,?)',
        [$productId, $delta, $p['stock'], $new, $reason, $userId]);
    if ($delta < 0 && $p['stock'] > $p['low_stock_threshold'] && $new <= $p['low_stock_threshold'])
        notify_staff('low_stock', "Low stock: {$p['name']}", "{$p['name']} is down to $new in stock.");
}

function restock_order(int $orderId, ?int $userId, string $reason): void
{
    foreach (fetch_all('SELECT product_id, qty FROM order_items WHERE order_id=? AND product_id IS NOT NULL', [$orderId]) as $i)
        adjust_stock((int)$i['product_id'], (int)$i['qty'], $reason, $userId);
}

/**
 * Create an order from the current cart inside ONE transaction.
 * $d = customer, billing, shipping, method, coupon, payment_method, payment_status
 */
function place_order(array $user, array $d): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cid = cart_id(false);
        $cart = fetch_all('SELECT * FROM cart_items WHERE cart_id=?', [$cid]);
        if (!$cart) throw new RuntimeException('Your cart is empty.');

        $sub = 0.0; $lines = [];
        foreach ($cart as $ci) {
            $p = fetch_one('SELECT * FROM products WHERE id=? AND status="active" FOR UPDATE', [$ci['product_id']]);
            if (!$p) throw new RuntimeException('An item in your cart is no longer available.');
            if ($p['stock'] < $ci['qty']) throw new RuntimeException("Only {$p['stock']} of {$p['name']} left in stock.");
            [, $label, $price] = resolve_variants($p, array_column(
                array_map(fn($vid) => fetch_one('SELECT group_name, id FROM product_variants WHERE id=? AND product_id=?', [$vid, $p['id']]) ?: ['group_name' => '', 'id' => 0],
                    array_filter(explode(',', $ci['variant_ids']))), 'id', 'group_name'));
            $lines[] = ['p' => $p, 'label' => $label, 'price' => $price, 'qty' => (int)$ci['qty']];
            $sub += $price * $ci['qty'];
        }
        [$coupon, $discount, $err] = coupon_check($d['coupon'] ?: null, $sub);
        if ($err) throw new RuntimeException($err);
        $fee   = $d['method'] === 'standard' ? DELIVERY_FEE : 0.0;
        $total = max(0, $sub - $discount + $fee);
        $no    = 'PF-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        q('INSERT INTO orders (order_no,user_id,customer_name,customer_email,customer_phone,billing_address,shipping_address,delivery_method,subtotal,discount,delivery_fee,total,coupon_code,status)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"Pending")',
            [$no, $user['id'], $d['name'], $d['email'], $d['phone'], json_encode($d['billing']), json_encode($d['shipping']),
             $d['method'], $sub, $discount, $fee, $total, $coupon['code'] ?? null]);
        $oid = (int)$pdo->lastInsertId();

        foreach ($lines as $l) {
            q('INSERT INTO order_items (order_id,product_id,product_name,sku,variant_label,unit_price,qty,line_total) VALUES (?,?,?,?,?,?,?,?)',
                [$oid, $l['p']['id'], $l['p']['name'], $l['p']['sku'], $l['label'], $l['price'], $l['qty'], $l['price'] * $l['qty']]);
            adjust_stock((int)$l['p']['id'], -$l['qty'], "Order $no", (int)$user['id']);
        }
        q('INSERT INTO payments (order_id,method,status,amount,reference) VALUES (?,?,?,?,?)',
            [$oid, $d['payment_method'], $d['payment_status'], $total, $d['payment_method'] === 'mock_online' ? 'MOCK-' . strtoupper(bin2hex(random_bytes(4))) : null]);
        if ($d['method'] === 'standard') q('INSERT INTO shipments (order_id) VALUES (?)', [$oid]);
        q('INSERT INTO order_status_history (order_id,status,remarks,updated_by) VALUES (?,"Pending","Order placed",?)', [$oid, $user['id']]);
        q('DELETE FROM cart_items WHERE cart_id=?', [$cid]);
        q('UPDATE carts SET coupon_code=NULL WHERE id=?', [$cid]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    notify((int)$user['id'], $d['email'], 'order_confirmation', "Order $no received",
        "Hi {$d['name']},\n\nThanks for your order $no. Total: " . money($total) . ".\nWe'll let you know as it progresses.\n\n- Pawfee Shop");
    return $oid;
}

const STATUS_MESSAGES = [
    'Confirmed' => 'order_confirmed', 'Processing' => 'order_processing', 'Packed' => 'order_packed',
    'Shipped' => 'order_shipped', 'Out for Delivery' => 'out_for_delivery', 'Delivered' => 'delivered',
    'Cancelled' => 'cancellation', 'Failed' => 'delivery_failed', 'Returned' => 'returned', 'Refunded' => 'refund_processed',
];

function set_order_status(int $orderId, string $status, ?int $byUser, string $remarks = ''): void
{
    if (!in_array($status, ORDER_STATUSES, true)) throw new RuntimeException('Invalid status.');
    $o = fetch_one('SELECT * FROM orders WHERE id=?', [$orderId]);
    if (!$o || $o['status'] === $status) return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('UPDATE orders SET status=? WHERE id=?', [$status, $orderId]);
        q('INSERT INTO order_status_history (order_id,status,remarks,updated_by) VALUES (?,?,?,?)', [$orderId, $status, $remarks ?: null, $byUser]);
        $dead = ['Cancelled'];   // Failed deliveries can be retried, so only cancellations auto-restock
        if (in_array($status, $dead, true) && !in_array($o['status'], $dead, true))
            restock_order($orderId, $byUser, "Restock: {$o['order_no']} $status");
        $pay = fetch_one('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$orderId]);
        if ($pay) {
            if ($status === 'Delivered' && $pay['method'] === 'cod') q('UPDATE payments SET status="paid" WHERE id=?', [$pay['id']]);
            if ($status === 'Cancelled') {
                if ($pay['status'] === 'paid') {
                    q('INSERT INTO refunds (order_id,amount,reason,processed_by) VALUES (?,?,?,?)', [$orderId, $pay['amount'], 'Order cancelled', $byUser]);
                    q('UPDATE payments SET status="refunded" WHERE id=?', [$pay['id']]);
                } else q('UPDATE payments SET status="cancelled" WHERE id=? AND status="pending"', [$pay['id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

    if (isset(STATUS_MESSAGES[$status]))
        notify((int)$o['user_id'], $o['customer_email'], STATUS_MESSAGES[$status], "Order {$o['order_no']}: $status",
            "Hi {$o['customer_name']},\n\nYour order {$o['order_no']} is now: $status." . ($remarks ? "\nNote: $remarks" : '') . "\n\n- Pawfee Shop");
}

/** Mark a refund against a return request. */
function process_refund(int $requestId, float $amount, bool $restock, int $adminId): void
{
    $r = fetch_one('SELECT rr.*, o.order_no, o.customer_email, o.total FROM return_requests rr JOIN orders o ON o.id=rr.order_id WHERE rr.id=?', [$requestId]);
    if (!$r || $r['status'] === 'Refunded') throw new RuntimeException('Request not refundable.');
    if ($amount <= 0 || $amount > $r['total']) throw new RuntimeException('Refund must be between 0 and the order total.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO refunds (return_request_id,order_id,amount,reason,processed_by) VALUES (?,?,?,?,?)', [$requestId, $r['order_id'], $amount, $r['reason'], $adminId]);
        q('UPDATE return_requests SET status="Refunded" WHERE id=?', [$requestId]);
        q('UPDATE payments SET status="refunded" WHERE order_id=?', [$r['order_id']]);
        if ($restock) restock_order((int)$r['order_id'], $adminId, "Restock: returned items {$r['order_no']}");
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    set_order_status((int)$r['order_id'], 'Refunded', $adminId, 'Refund of ' . money($amount) . ' processed');
    notify((int)$r['user_id'], $r['customer_email'], 'refund_processed', "Refund processed for {$r['order_no']}", 'We refunded ' . money($amount) . '.');
}
