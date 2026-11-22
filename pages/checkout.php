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
    $name = input('name'); $email = strtolower(input('email')); $phone = input('phone');
    if ($name === '') $err[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) $err[] = 'Enter a valid phone number.';

    $billing  = addr_from_post('bill');
    $shipping = addr_from_post('ship', ['recipient', 'phone', 'instructions']);
    if ($same) $shipping = array_merge($shipping, $billing);
    $shipping['recipient'] = $shipping['recipient'] ?: $name;
    $shipping['phone']     = $shipping['phone'] ?: $phone;
    $needAddr = fn(array $a) => array_filter($a, fn($v, $k) => in_array($k, ['street', 'barangay', 'city', 'province', 'postal_code']) && $v === '', ARRAY_FILTER_USE_BOTH);
    if ($needAddr($billing)) $err[] = 'Complete your billing address.';
    if ($method === 'standard') {
        if ($needAddr($shipping) || $shipping['recipient'] === '' || $shipping['phone'] === '') $err[] = 'Complete the shipping address, recipient and phone.';
    } else {
        $shipping = ['recipient' => $name, 'phone' => $phone, 'street' => 'Store pickup', 'barangay' => '', 'city' => STORE_ADDRESS, 'province' => '', 'postal_code' => '', 'instructions' => $shipping['instructions']];
    }
    $payMethod = ($_POST['payment_method'] ?? '') === 'mock_online' ? 'mock_online' : 'cod';

    // Optional coupon typed at checkout
    if (input('coupon') !== '') {
        [$c, , $cErr] = coupon_check(input('coupon'), $t['subtotal']);
        if ($cErr) $err[] = $cErr; else q('UPDATE carts SET coupon_code=? WHERE id=?', [$c['code'], cart_id()]);
    }
    foreach ($t['items'] as $i) if ($i['qty'] > $i['stock'] || $i['status'] !== 'active') $err[] = "{$i['name']} is no longer available in that quantity.";

    if (!$err) {
        $t = cart_totals($method);
        $data = ['name' => $name, 'email' => $email, 'phone' => $phone, 'billing' => $billing, 'shipping' => $shipping, 'method' => $method,
                 'coupon' => $t['coupon']['code'] ?? '', 'payment_method' => $payMethod, 'payment_status' => 'pending'];
        try {
            if ($payMethod === 'mock_online') {
                $_SESSION['pending_checkout'] = $data + ['total' => $t['total']];
                redirect(url('mockpay'));
            }
            $oid = place_order($user, $data);
            $no  = fetch_val('SELECT order_no FROM orders WHERE id=?', [$oid]);
            flash('success', "Order $no placed. Thank you!");
            redirect(url('order', ['no' => $no]));
        } catch (RuntimeException $e) { $err[] = $e->getMessage(); }
    }
}
$_SESSION['checkout_token'] = bin2hex(random_bytes(16));
$t = cart_totals($_POST['delivery_method'] ?? 'standard');
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
page_header('Checkout', 'checkout');
?>
<div class="page-title"><h1>Checkout</h1></div>
<?php foreach ($err as $m): ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>
<form method="post" class="cart-layout" data-validate data-checkout novalidate>
  <?= csrf_field() ?><input type="hidden" name="checkout_token" value="<?= $_SESSION['checkout_token'] ?>">
  <div class="stack">
    <section class="card pad stack"><h3>1 · Customer information</h3>
      <label>Full name<input name="name" required value="<?= $v('name', $user['first_name'] . ' ' . $user['last_name']) ?>"></label>
      <div class="row"><label>Email<input type="email" name="email" required value="<?= $v('email', $user['email']) ?>"></label>
      <label>Phone<input name="phone" required value="<?= $v('phone', $user['phone']) ?>"></label></div></section>

    <section class="card pad stack"><h3>2 · Billing address</h3>
      <label>Street<input name="bill_street" required value="<?= $v('bill_street', $default['street'] ?? '') ?>"></label>
      <div class="row"><label>Barangay<input name="bill_barangay" required value="<?= $v('bill_barangay', $default['barangay'] ?? '') ?>"></label>
      <label>City<input name="bill_city" required value="<?= $v('bill_city', $default['city'] ?? '') ?>"></label></div>
      <div class="row"><label>Province<input name="bill_province" required value="<?= $v('bill_province', $default['province'] ?? '') ?>"></label>
      <label>Postal code<input name="bill_postal_code" required value="<?= $v('bill_postal_code', $default['postal_code'] ?? '') ?>"></label></div></section>

    <section class="card pad stack"><h3>3 · Delivery method</h3>
      <label class="option-card"><input type="radio" name="delivery_method" value="standard" data-fee="<?= DELIVERY_FEE ?>" <?= ($_POST['delivery_method'] ?? 'standard') === 'standard' ? 'checked' : '' ?>> <span><strong>Standard delivery</strong><small><?= money(DELIVERY_FEE) ?> · to your address</small></span></label>
      <label class="option-card"><input type="radio" name="delivery_method" value="pickup" data-fee="0" <?= ($_POST['delivery_method'] ?? '') === 'pickup' ? 'checked' : '' ?>> <span><strong>Store pickup</strong><small>Free · <?= e(STORE_ADDRESS) ?></small></span></label>
      <div data-shipping>
        <h4>Shipping address</h4>
        <label class="check"><input type="checkbox" name="same_address" value="1" data-same <?= !is_post() || !empty($_POST['same_address']) ? 'checked' : '' ?>> Same as billing address</label>
        <div class="stack" data-ship-fields>
          <div class="row"><label>Recipient<input name="ship_recipient" value="<?= $v('ship_recipient', $default['recipient'] ?? '') ?>"></label>
          <label>Phone<input name="ship_phone" value="<?= $v('ship_phone', $default['phone'] ?? '') ?>"></label></div>
          <label>Street<input name="ship_street" value="<?= $v('ship_street') ?>"></label>
          <div class="row"><label>Barangay<input name="ship_barangay" value="<?= $v('ship_barangay') ?>"></label><label>City<input name="ship_city" value="<?= $v('ship_city') ?>"></label></div>
          <div class="row"><label>Province<input name="ship_province" value="<?= $v('ship_province') ?>"></label><label>Postal code<input name="ship_postal_code" value="<?= $v('ship_postal_code') ?>"></label></div>
        </div>
        <label>Delivery instructions<textarea name="ship_instructions" rows="2" placeholder="Gate code, landmarks, etc."><?= $v('ship_instructions') ?></textarea></label>
      </div></section>

    <section class="card pad stack"><h3>4 · Payment</h3>
      <label class="option-card"><input type="radio" name="payment_method" value="cod" <?= ($_POST['payment_method'] ?? 'cod') === 'cod' ? 'checked' : '' ?>> <span><strong>Cash on Delivery</strong><small>Pay when you receive or pick up</small></span></label>
      <label class="option-card"><input type="radio" name="payment_method" value="mock_online" <?= ($_POST['payment_method'] ?? '') === 'mock_online' ? 'checked' : '' ?>> <span><strong>Mock Online Payment</strong><small>Demo gateway — no real card details</small></span></label></section>
  </div>

  <aside class="card pad summary">
    <h3>Order summary</h3>
    <?php foreach ($t['items'] as $i): ?>
      <div class="line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?><br><small class="muted"><?= e($i['option_label']) ?></small></span><span><?= money($i['unit_price'] * $i['qty']) ?></span></div>
    <?php endforeach; ?>
    <hr>
    <div class="line"><span>Subtotal</span><span data-sub="<?= $t['subtotal'] ?>"><?= money($t['subtotal']) ?></span></div>
    <div class="line"><span>Discount<?= $t['coupon'] ? ' (' . e($t['coupon']['code']) . ')' : '' ?></span><span data-disc="<?= $t['discount'] ?>">−<?= money($t['discount']) ?></span></div>
    <div class="line"><span>Delivery fee</span><span data-fee-out><?= money($t['delivery_fee']) ?></span></div>
    <div class="line total"><span>Grand total</span><span data-grand><?= money($t['total']) ?></span></div>
    <label>Coupon code<input name="coupon" value="<?= e($_POST['coupon'] ?? ($t['coupon']['code'] ?? '')) ?>" placeholder="e.g. PAWS10"></label>
    <button class="btn btn-block" data-once>Place order</button>
  </aside>
</form>
<script>window.CURRENCY = <?= json_encode(CURRENCY) ?>;</script>
<?php page_footer();
