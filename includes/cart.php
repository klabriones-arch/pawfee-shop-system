<?php
/** Account-owned database carts; guest carts use a 30-day cookie. */

function cart_id(bool $create = true): ?int
{
    $u = current_user();
    $uid = $u ? (int)$u['id'] : null;
    if ($uid) {
        $row = fetch_one('SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1', [$uid]);
        if ($row) return (int)$row['id'];
    }
    $token = $_COOKIE['pf_cart'] ?? '';
    $guest = preg_match('/^[a-f0-9]{32}$/', $token) ? fetch_one('SELECT id FROM carts WHERE token=? AND user_id IS NULL', [$token]) : null;
    if ($guest && !$uid) return (int)$guest['id'];
    if (!$create) return null;
    $token = bin2hex(random_bytes(16));
    q('INSERT INTO carts (token,user_id) VALUES (?,?)', [$token,$uid]);
    $id = (int)db()->lastInsertId();
    if (!$uid) {
        setcookie('pf_cart',$token,['expires'=>time()+2592000,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
        $_COOKIE['pf_cart']=$token;
    }
    return $id;
}

function attach_guest_cart(int $uid): void
{
    $token = $_COOKIE['pf_cart'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/',$token)) return;
    $guest = fetch_one('SELECT id,coupon_code FROM carts WHERE token=? AND user_id IS NULL',[$token]);
    if (!$guest) return;
    $owned = fetch_one('SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1',[$uid]);
    if (!$owned) { q('UPDATE carts SET user_id=? WHERE id=? AND user_id IS NULL',[$uid,$guest['id']]); return; }
    $pdo=db(); $pdo->beginTransaction();
    try {
        foreach(fetch_all('SELECT ci.*,p.stock,p.status FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE cart_id=?',[$guest['id']]) as $i) {
            if ($i['status']!=='active') continue;
            $have=(int)fetch_val('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_id=? AND product_id=?',[$owned['id'],$i['product_id']]);
            $qty=min((int)$i['qty'],max(0,(int)$i['stock']-$have));
            if ($qty>0) q('INSERT INTO cart_items (cart_id,product_id,variant_ids,option_label,unit_price,qty) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE qty=qty+VALUES(qty)',[$owned['id'],$i['product_id'],$i['variant_ids'],$i['option_label'],$i['unit_price'],$qty]);
        }
        q('DELETE FROM carts WHERE id=? AND user_id IS NULL',[$guest['id']]); $pdo->commit();
    } catch(Throwable $e) { $pdo->rollBack(); throw $e; }
}

function cart_count(): int
{
    $id = cart_id(false);
    return $id ? (int)fetch_val('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_id=?', [$id]) : 0;
}

/** Variant groups for a product: ['Size' => [rows...], 'Milk' => [...]] */
function variant_groups(int $productId): array
{
    $g = [];
    foreach (fetch_all('SELECT * FROM product_variants WHERE product_id=? ORDER BY id', [$productId]) as $v)
        $g[$v['group_name']][] = $v;
    foreach ($g as &$list) usort($list, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
    return $g;
}

/** Turn the posted selection into [ids, label, unit price]. Always server-side priced. */
function resolve_variants(array $product, array $selected): array
{
    $ids = []; $labels = []; $price = (float)$product['price'];
    foreach (variant_groups((int)$product['id']) as $group => $options) {
        $pick = null;
        foreach ($options as $o) if ((int)($selected[$group] ?? 0) === (int)$o['id']) $pick = $o;
        if (!$pick) foreach ($options as $o) if ($o['is_default']) $pick = $o;
        $pick = $pick ?: $options[0];
        $ids[] = (int)$pick['id']; $labels[] = $pick['label']; $price += (float)$pick['price_delta'];
    }
    return [implode(',', $ids), implode(' · ', $labels), $price];
}

function cart_add(int $productId, array $selected, int $qty): string
{
    $qty = max(1, min(20, $qty));
    $p = fetch_one('SELECT p.* FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.status="active" AND c.status="active"', [$productId]);
    if (!$p) return 'This product is no longer available.';
    [$ids, $label, $price] = resolve_variants($p, $selected);
    $cid = cart_id();
    $have = (int)fetch_val('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_id=? AND product_id=?', [$cid, $productId]);
    if ($have + $qty > $p['stock']) return $p['stock'] > 0 ? "Only {$p['stock']} left in stock." : 'Sorry, this item is out of stock.';
    q('INSERT INTO cart_items (cart_id,product_id,variant_ids,option_label,unit_price,qty) VALUES (?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty), unit_price = VALUES(unit_price)', [$cid, $productId, $ids, $label, $price, $qty]);
    return '';
}

function cart_items(): array
{
    $id = cart_id(false);
    if (!$id) return [];
    $items = fetch_all('SELECT ci.*, p.name, p.slug, p.stock, p.status, p.price, c.status AS category_status,
        (SELECT path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC,id LIMIT 1) AS image
        FROM cart_items ci JOIN products p ON p.id=ci.product_id JOIN categories c ON c.id=p.category_id WHERE ci.cart_id=? ORDER BY ci.id', [$id]);
    foreach ($items as &$i) {
        $chosen=[]; foreach(array_filter(explode(',', $i['variant_ids'])) as $vid) { $v=fetch_one('SELECT group_name,id FROM product_variants WHERE id=? AND product_id=?',[$vid,$i['product_id']]); if($v) $chosen[$v['group_name']]=$v['id']; }
        [, $label, $price]=resolve_variants(['id'=>$i['product_id'],'price'=>$i['price']],$chosen);
        $i['unit_price']=$price; $i['option_label']=$label;
        if($i['category_status']!=='active') $i['status']='inactive';
    } unset($i); return $items;
}

/** Validate coupon. Returns [couponRow|null, discount, errorMessage]. */
function coupon_check(?string $code, float $subtotal): array
{
    if (!$code) return [null, 0.0, ''];
    $c = fetch_one('SELECT * FROM coupons WHERE code=? AND active=1', [strtoupper($code)]);
    if (!$c) return [null, 0.0, 'That coupon code is not valid.'];
    if ($c['expires_at'] && $c['expires_at'] < date('Y-m-d')) return [null, 0.0, 'That coupon has expired.'];
    if ($subtotal < $c['min_purchase']) return [null, 0.0, 'Minimum purchase of ' . money($c['min_purchase']) . ' required for this coupon.'];
    $d = $c['type'] === 'percent' ? $subtotal * $c['value'] / 100 : (float)$c['value'];
    return [$c, round(min($d, $subtotal), 2), ''];
}

function cart_totals(string $method = 'standard'): array
{
    $items = cart_items();
    $sub = 0.0;
    foreach ($items as $i) $sub += $i['unit_price'] * $i['qty'];
    $id   = cart_id(false);
    $code = $id ? fetch_val('SELECT coupon_code FROM carts WHERE id=?', [$id]) : null;
    [$coupon, $discount, $err] = coupon_check($code ?: null, $sub);
    if ($err && $id) q('UPDATE carts SET coupon_code=NULL WHERE id=?', [$id]);   // drop invalid coupon
    $fee = ($items && $method === 'standard') ? DELIVERY_FEE : 0.0;
    return ['items' => $items, 'subtotal' => $sub, 'coupon' => $coupon, 'coupon_error' => $err,
            'discount' => $discount, 'delivery_fee' => $fee, 'total' => max(0, $sub - $discount + $fee)];
}
