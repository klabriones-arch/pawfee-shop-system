<?php
$user = require_login();
if (!has_role('customer')) { flash('error', 'Checkout is for customer accounts.'); redirect(url('home')); }
$t = cart_totals();
if (!$t['items'] && !is_post()) { flash('info', 'Your cart is empty.'); redirect(url('cart')); }

$default = fetch_one('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id LIMIT 1', [$user['id']]) ?: [];
$addrFields = ['street', 'barangay', 'city', 'province', 'postal_code'];
$err = [];

function addr_from_post(string $prefix, array $extra = []): array
{
    $a = [];
    foreach (['street', 'barangay', 'city', 'province', 'postal_code'] as $f) $a[$f] = input("{$prefix}_$f");
    foreach ($extra as $f) $a[$f] = input("{$prefix}_$f");
    return $a;
}

if (is_post()) {
    csrf_check();
    // Duplicate-submission guard: the token is single-use.
    if (!hash_equals($_SESSION['checkout_token'] ?? 'x', $_POST['checkout_token'] ?? '')) {
        flash('info', 'That order was already submitted. Check your order history.');
        redirect(url('account', ['tab' => 'orders']));
    }
    unset($_SESSION['checkout_token']);

    $method = ($_POST['delivery_method'] ?? '') === 'pickup' ? 'pickup' : 'standard';
    $same   = !empty($_POST['same_address']);
    $name = trim(input('first_name').' '.input('last_name')); $email = strtolower(input('email')); $phone = input('phone');
    if (input('first_name') === '' || input('last_name') === '') $err[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) $err[] = 'Enter a valid phone number.';

    $billing  = addr_from_post('bill');
    $shipping = addr_from_post('ship', ['recipient', 'phone', 'instructions']);
    if ($same) $billing = array_intersect_key($shipping, array_flip($addrFields));
    $shipping['recipient'] = $shipping['recipient'] ?: $name;
    $shipping['phone']     = $shipping['phone'] ?: $phone;
    $needAddr = fn(array $a) => array_filter($a, fn($v, $k) => in_array($k, ['street', 'barangay', 'city', 'province', 'postal_code']) && $v === '', ARRAY_FILTER_USE_BOTH);
    if ($needAddr($billing)) $err[] = 'Complete your billing address.';
    if ($method === 'standard') {
        if ($needAddr($shipping) || $shipping['recipient'] === '' || $shipping['phone'] === '') $err[] = 'Complete the shipping address, recipient and phone.';
    } else {
        $shipping = ['recipient' => $name, 'phone' => $phone, 'street' => 'Store pickup', 'barangay' => '', 'city' => STORE_ADDRESS, 'province' => '', 'postal_code' => '', 'instructions' => $shipping['instructions']];
    }
    $payMethod = in_array($_POST['payment_method'] ?? '', ['card','wallet'], true) ? 'mock_online' : 'cod';

    // Optional coupon typed at checkout
    if (input('coupon') !== '') {
        [$c, , $cErr] = coupon_check(input('coupon'), $t['subtotal']);
        if ($cErr) $err[] = $cErr; else q('UPDATE carts SET coupon_code=? WHERE id=?', [$c['code'], cart_id()]);
    }
    if (!$t['items']) $err[]='Your cart is empty.';
    $quantities=[]; foreach ($t['items'] as $i) $quantities[$i['product_id']]=($quantities[$i['product_id']]??0)+(int)$i['qty'];
    foreach ($t['items'] as $i) if ($quantities[$i['product_id']] > $i['stock'] || $i['status'] !== 'active') $err[] = "{$i['name']} is no longer available in that quantity.";

    if (!$err) {
        $t = cart_totals($method);
        $data = ['name' => $name, 'email' => $email, 'phone' => $phone, 'billing' => $billing, 'shipping' => $shipping, 'method' => $method,
                 'coupon' => $t['coupon']['code'] ?? '', 'payment_method' => $payMethod, 'payment_status' => 'pending', 'total'=>$t['total']];
        try {
            if ($payMethod === 'mock_online') {
                $_SESSION['pending_checkout'] = $data + ['total' => $t['total'], 'user_id' => $user['id'], 'cart_signature'=>hash('sha256',json_encode(array_map(fn($i)=>[$i['product_id'],$i['variant_ids'],$i['qty'],$i['unit_price']],$t['items'])))];
                redirect(url('mockpay'));
            }
            $oid = place_order($user, $data);
            $no  = fetch_val('SELECT order_no FROM orders WHERE id=?', [$oid]);
            flash('success', "Order $no placed. Thank you!");
            redirect(url('confirmation', ['no' => $no]));
        } catch (RuntimeException $e) { $err[] = $e->getMessage(); }
    }
}
$_SESSION['checkout_token'] = bin2hex(random_bytes(16));
$t = cart_totals($_POST['delivery_method'] ?? 'standard');
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
page_header('Checkout', 'checkout');
?>
<div class="crumbs">Home / Cart / Checkout</div>
<div class="row-between page-title"><h1>One last step to something good.</h1><small>Cart → Checkout → Confirmation</small></div>
<?php foreach ($err as $m): ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>
<form method="post" class="cart-layout" id="flow-checkout">
<?= csrf_field() ?><input type="hidden" name="checkout_token" value="<?= e($_SESSION['checkout_token']) ?>">
<div class="stack">
<section class="card pad stack"><h3>1. Billing information</h3><div class="row"><label>First name *<input name="first_name" required value="<?= $v('first_name',$user['first_name']) ?>"></label><label>Last name *<input name="last_name" required value="<?= $v('last_name',$user['last_name']) ?>"></label></div><div class="row"><label>Email address *<input type="email" name="email" required value="<?= $v('email',$user['email']) ?>"></label><label>Phone number *<input name="phone" required value="<?= $v('phone',$user['phone']) ?>"></label></div></section>
<section class="card pad stack"><h3>2. Shipping address</h3>
<?php foreach (['ship'=>'Shipping','bill'=>'Billing'] as $prefix=>$label): ?>
<div class="stack" <?= $prefix==='bill'?'id="flow-billing"':'' ?>><?php if($prefix==='bill'): ?><h4>Separate billing address</h4><?php endif; ?>
<div class="row"><label>Street address *<input name="<?= $prefix ?>_street" required value="<?= $v($prefix.'_street',$default['street']??'') ?>"></label><label>Barangay *<input name="<?= $prefix ?>_barangay" required value="<?= $v($prefix.'_barangay',$default['barangay']??'') ?>"></label></div><div class="row"><?php foreach(['city'=>'City','province'=>'Province','postal_code'=>'Postal code'] as $k=>$l): ?><label><?= $l ?> *<input name="<?= $prefix.'_'.$k ?>" required value="<?= $v($prefix.'_'.$k,$default[$k]??'') ?>"></label><?php endforeach; ?><label>Country<input value="Philippines" readonly></label></div></div>
<?php if($prefix==='ship'): ?><label class="check"><input type="checkbox" name="same_address" value="1" <?= !is_post()||!empty($_POST['same_address'])?'checked':'' ?>> Use this shipping address as my billing address</label><?php endif; endforeach; ?>
<label>Delivery instructions (optional)<textarea name="ship_instructions" rows="2"><?= $v('ship_instructions') ?></textarea></label></section>
<section class="card pad stack"><h3>3. Delivery options</h3><div class="flow-options"><?php foreach(['standard'=>'Standard delivery','pickup'=>'Café pickup'] as $k=>$l): ?><label class="option-card"><input type="radio" name="delivery_method" value="<?= $k ?>" data-fee="<?= $k==='standard'?DELIVERY_FEE:0 ?>" <?= ($_POST['delivery_method']??'standard')===$k?'checked':'' ?>><span><strong><?= $l ?></strong><small><?= $k==='standard'?money(DELIVERY_FEE):'Free' ?></small></span></label><?php endforeach; ?></div></section>
<section class="card pad stack"><h3>4. Payment method</h3><div class="flow-options"><?php foreach(['card'=>'Credit / debit card (mock)','wallet'=>'Digital wallet (mock)','cod'=>'Cash on delivery'] as $k=>$l): ?><label class="option-card"><input type="radio" name="payment_method" value="<?= $k ?>" <?= ($_POST['payment_method']??'cod')===$k?'checked':'' ?>><span><?= $l ?></span></label><?php endforeach; ?></div><p class="muted small">Online methods are simulated; no real payment details are collected.</p></section></div>
<aside class="card pad summary flow-sage"><div class="row-between"><h3>Order summary</h3><a href="<?= url('cart') ?>">Edit cart</a></div><small><?= count($t['items']) ?> products · <?= cart_count() ?> items</small>
<?php foreach($t['items'] as $i): ?><div class="flow-item"><img src="<?= e(product_image($i)) ?>" alt=""><div class="grow"><strong><?= e($i['name']) ?></strong><small><?= e($i['option_label']) ?><br><?= (int)$i['qty'] ?> × <?= money($i['unit_price']) ?></small></div><strong><?= money($i['unit_price']*$i['qty']) ?></strong></div><?php endforeach; ?>
<div class="line"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div><div class="line"><span>Discount</span><span>−<?= money($t['discount']) ?></span></div><div class="line"><span>Delivery fee</span><span id="flow-fee"><?= money($t['delivery_fee']) ?></span></div><div class="line total"><span>Total</span><span id="flow-total"><?= money($t['total']) ?></span></div><button class="btn btn-block" data-once id="flow-place">Place order · <?= money($t['total']) ?></button></aside></form>
<script>const cf=document.getElementById('flow-checkout');function syncFlow(){const same=cf.elements.same_address.checked,b=document.getElementById('flow-billing');b.hidden=same;b.querySelectorAll('input').forEach(i=>i.disabled=same);const fee=Number(cf.querySelector('[name=delivery_method]:checked').dataset.fee), total=Math.max(0,<?= json_encode($t['subtotal']-$t['discount']) ?>+fee),fmt=n=><?= json_encode(CURRENCY) ?>+n.toFixed(2);document.getElementById('flow-fee').textContent=fmt(fee);document.getElementById('flow-total').textContent=fmt(total);document.getElementById('flow-place').textContent='Place order · '+fmt(total);}cf.addEventListener('change',syncFlow);syncFlow();</script>
<?php page_footer();
