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
        if (!$cid) throw new RuntimeException('Your cart is empty.');
        q('SELECT id FROM carts WHERE id=? AND user_id=? FOR UPDATE',[$cid,$user['id']]);
        $cart = fetch_all('SELECT * FROM cart_items WHERE cart_id=? ORDER BY product_id,id FOR UPDATE', [$cid]);
        if (!$cart) throw new RuntimeException('Your cart is empty.');

        $sub = 0.0; $lines = []; $quantities=[];
        foreach($cart as $ci) $quantities[$ci['product_id']]=($quantities[$ci['product_id']]??0)+(int)$ci['qty'];
        foreach ($cart as $ci) {
            $p = fetch_one('SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.status="active" AND c.status="active" FOR UPDATE', [$ci['product_id']]);
            if (!$p) throw new RuntimeException('An item in your cart is no longer available.');
            if ($p['stock'] < $quantities[$ci['product_id']]) throw new RuntimeException("Only {$p['stock']} of {$p['name']} left in stock.");
            [$resolvedIds, $label, $price] = resolve_variants($p, array_column(
                array_map(fn($vid) => fetch_one('SELECT group_name, id FROM product_variants WHERE id=? AND product_id=?', [$vid, $p['id']]) ?: ['group_name' => '', 'id' => 0],
                    array_filter(explode(',', $ci['variant_ids']))), 'id', 'group_name'));
            $oldIds=array_filter(explode(',', $ci['variant_ids'])); $newIds=array_filter(explode(',', $resolvedIds)); sort($oldIds); sort($newIds);
            if ($oldIds !== $newIds) throw new RuntimeException('Product options changed. Remove and re-add the item.');
            $lines[] = ['p' => $p, 'label' => $label, 'price' => $price, 'qty' => (int)$ci['qty']];
            $sub += $price * $ci['qty'];
        }
        [$coupon, $discount, $err] = coupon_check($d['coupon'] ?: null, $sub);
        if ($err) throw new RuntimeException($err);
        $fee   = $d['method'] === 'standard' ? DELIVERY_FEE : 0.0;
        $total = max(0, $sub - $discount + $fee);
        if (isset($d['total']) && abs((float)$d['total']-$total)>0.005) throw new RuntimeException('Your cart total changed. Please review checkout again.');
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

function allowed_order_statuses(array $o): array {
    $map=['Pending'=>['Confirmed','Cancelled'],'Confirmed'=>['Processing','Cancelled'],'Processing'=>['Packed','Cancelled'],'Packed'=>$o['delivery_method']==='pickup'?['Delivered','Cancelled']:['Shipped','Cancelled'],'Shipped'=>['Out for Delivery'],'Out for Delivery'=>['Delivered','Failed'],'Failed'=>['Out for Delivery','Cancelled'],'Delivered'=>['Returned'],'Returned'=>['Refunded']];
    return $map[$o['status']]??[];
}

function set_order_status(int $orderId, string $status, ?int $byUser, string $remarks = ''): void
{
    if (!in_array($status, ORDER_STATUSES, true)) throw new RuntimeException('Invalid status.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = fetch_one('SELECT * FROM orders WHERE id=? FOR UPDATE', [$orderId]);
        if (!$o) throw new RuntimeException('Order not found.');
        if ($o['status'] === $status) { $pdo->commit(); return; }
        if (!in_array($status, allowed_order_statuses($o),true)) throw new RuntimeException('Invalid order status transition.');
        q('UPDATE orders SET status=? WHERE id=?', [$status, $orderId]);
        if ($o['delivery_method'] === 'standard' && in_array($status, ['Out for Delivery', 'Delivered', 'Failed'], true)) {
            q('INSERT INTO shipments (order_id,status,attempts,remarks,delivered_at) VALUES (?,?,?, ?, IF(?="Delivered",NOW(),NULL)) ON DUPLICATE KEY UPDATE status=VALUES(status), attempts=attempts+VALUES(attempts), remarks=VALUES(remarks), delivered_at=VALUES(delivered_at)', [$orderId, $status, $status === 'Out for Delivery' ? 1 : 0, $remarks ?: null, $status]);
        }
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
    $pdo=db(); $pdo->beginTransaction();
    try {
        $r=fetch_one('SELECT rr.*,o.order_no,o.customer_email,o.status AS order_status FROM return_requests rr JOIN orders o ON o.id=rr.order_id WHERE rr.id=? FOR UPDATE',[$requestId]);
        if (!$r || $r['type']!=='return' || $r['status']!=='Item Received' || $r['order_status']!=='Returned') throw new RuntimeException('Only received returns can be refunded.');
        $pay=fetch_one('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$r['order_id']]);
        $used=(float)fetch_val('SELECT COALESCE(SUM(amount),0) FROM refunds WHERE order_id=?',[$r['order_id']]);
        if (!$pay || $pay['status']!=='paid' || !is_finite($amount) || $amount<=0 || $amount > (float)$pay['amount']-$used) throw new RuntimeException('Refund exceeds the remaining paid amount.');
        q('INSERT INTO refunds (return_request_id,order_id,amount,reason,processed_by) VALUES (?,?,?,?,?)',[$requestId,$r['order_id'],$amount,$r['reason'],$adminId]);
        q('UPDATE return_requests SET status="Refunded" WHERE id=?',[$requestId]);
        q('UPDATE payments SET status="refunded" WHERE order_id=?',[$r['order_id']]);
        if ($restock) restock_order((int)$r['order_id'],$adminId,'Restock: returned items '.$r['order_no']);
        q('UPDATE orders SET status="Refunded" WHERE id=?',[$r['order_id']]);
        q('INSERT INTO order_status_history (order_id,status,remarks,updated_by) VALUES (?,"Refunded",?,?)',[$r['order_id'],'Refund of '.money($amount).' processed',$adminId]);
        $pdo->commit();
    } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
    notify((int)$r['user_id'],$r['customer_email'],'refund_processed','Refund processed for '.$r['order_no'],'We refunded '.money($amount).'.');
}
