<?php
$action = $_GET['action'] ?? '';
$ajax   = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post() && $action) {
    csrf_check();
    $msg = ''; $type = 'success';
    switch ($action) {
        case 'add':
            $err = cart_add((int)($_POST['product_id'] ?? 0), (array)($_POST['variant'] ?? []), (int)($_POST['qty'] ?? 1));
            $msg = $err ?: 'Added to your cart.'; $type = $err ? 'error' : 'success';
            break;
        case 'update':
            $item = fetch_one('SELECT ci.id, p.stock FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.id=? AND ci.cart_id=?', [(int)$_POST['item_id'], cart_id()]);
            if ($item) {
                $qty = (int)($_POST['qty'] ?? 1);
                if ($qty < 1) q('DELETE FROM cart_items WHERE id=?', [$item['id']]);
                else q('UPDATE cart_items SET qty=? WHERE id=?', [min($qty, max(1, (int)$item['stock'])), $item['id']]);
            }
            break;
        case 'remove':
            q('DELETE FROM cart_items WHERE id=? AND cart_id=?', [(int)($_POST['item_id'] ?? 0), cart_id()]);
            $msg = 'Item removed.';
            break;
        case 'coupon':
            if (isset($_POST['clear'])) { q('UPDATE carts SET coupon_code=NULL WHERE id=?', [cart_id()]); break; }
            $t = cart_totals();
            [$c, , $err] = coupon_check(input('code'), $t['subtotal']);
            if ($err) { $msg = $err; $type = 'error'; }
            else { q('UPDATE carts SET coupon_code=? WHERE id=?', [$c['code'], cart_id()]); $msg = 'Coupon applied.'; }
            break;
    }
    if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => $type === 'success', 'message' => $msg, 'count' => cart_count()]); exit; }
    if ($msg) flash($type, $msg);
    redirect(url('cart'));
}

$t = cart_totals();
page_header('Cart', 'cart');
?>
<div class="page-title"><h1>Your cart</h1></div>
<?php if (!$t['items']): ?>
  <div class="empty"><p>Your cart is empty. Let’s fix that.</p><a class="btn" href="<?= url('shop') ?>">Browse the menu</a></div>
<?php else: ?>
<div class="cart-layout">
  <section class="card pad">
    <?php foreach ($t['items'] as $i): ?>
      <div class="cart-row">
        <img src="<?= e(product_image($i)) ?>" alt="">
        <div class="grow"><a href="<?= url('product', ['slug' => $i['slug']]) ?>"><strong><?= e($i['name']) ?></strong></a>
          <div class="muted small"><?= e($i['option_label']) ?></div>
          <?php if ($i['qty'] > $i['stock']): ?><div class="small err">Only <?= (int)$i['stock'] ?> in stock — please adjust.</div><?php endif; ?></div>
        <form method="post" action="<?= url('cart', ['action' => 'update']) ?>" class="qty" data-autosubmit-form>
          <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
          <button type="button" data-qty="-1">−</button><input type="number" name="qty" value="<?= (int)$i['qty'] ?>" min="0" max="<?= max(1, (int)$i['stock']) ?>"><button type="button" data-qty="1">+</button>
        </form>
        <strong class="price"><?= money($i['unit_price'] * $i['qty']) ?></strong>
        <form method="post" action="<?= url('cart', ['action' => 'remove']) ?>"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><button class="icon-btn" aria-label="Remove">✕</button></form>
      </div>
    <?php endforeach; ?>
  </section>
  <aside class="card pad summary">
    <h3>Order summary</h3>
    <div class="line"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div>
    <?php if ($t['coupon']): ?>
      <div class="line"><span>Discount (<?= e($t['coupon']['code']) ?>)</span><span>−<?= money($t['discount']) ?></span></div>
    <?php endif; ?>
    <div class="line"><span>Delivery fee</span><span><?= money($t['delivery_fee']) ?></span></div>
    <div class="line total"><span>Total</span><span><?= money($t['total']) ?></span></div>
    <form method="post" action="<?= url('cart', ['action' => 'coupon']) ?>" class="row coupon">
      <?= csrf_field() ?>
      <input name="code" placeholder="Coupon code" value="<?= e($t['coupon']['code'] ?? '') ?>">
      <button class="btn btn-sm btn-ghost">Apply</button>
      <?php if ($t['coupon']): ?><button class="btn btn-sm btn-ghost" name="clear" value="1">Remove</button><?php endif; ?>
    </form>
    <a class="btn btn-block" href="<?= url('checkout') ?>">Checkout</a>
    <p class="muted small center">Pickup orders skip the delivery fee at checkout.</p>
  </aside>
</div>
<?php endif; page_footer();
