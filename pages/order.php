<?php
$user = require_login();
// Ownership check: customers can only ever load their own orders.
$o = fetch_one('SELECT * FROM orders WHERE order_no=? AND user_id=?', [$_GET['no'] ?? '', $user['id']]);
if (!$o) { http_response_code(404); page_header('Order'); echo '<div class="empty"><h1>Order not found</h1><a class="btn" href="' . url('account') . '">My orders</a></div>'; page_footer(); return; }

$activeReq = fetch_one('SELECT * FROM return_requests WHERE order_id=? AND status NOT IN ("Rejected","Closed") ORDER BY id DESC LIMIT 1', [$o['id']]);
$canCancel = in_array($o['status'], ['Pending', 'Confirmed', 'Processing'], true);
$canReturn = $o['status'] === 'Delivered';

if (is_post() && ($canCancel || $canReturn) && !$activeReq) {
    csrf_check();
    $type = $canCancel ? 'cancellation' : 'return';
    $reason = input('reason');
    if ($reason === '') flash('error', 'Please select a reason.');
    else {
        q('INSERT INTO return_requests (order_id,user_id,type,reason,description) VALUES (?,?,?,?,?)', [$o['id'], $user['id'], $type, $reason, input('description') ?: null]);
        notify((int)$user['id'], $user['email'], 'return_request', "We received your $type request", "Order {$o['order_no']}: we'll review your request shortly.");
        notify_staff('return_request', "New $type request: {$o['order_no']}", "Reason: $reason");
        flash('success', 'Request submitted. We’ll review it soon.');
    }
    redirect(url('order', ['no' => $o['order_no']]));
}

$items   = fetch_all('SELECT * FROM order_items WHERE order_id=?', [$o['id']]);
$history = fetch_all('SELECT h.*, u.first_name FROM order_status_history h LEFT JOIN users u ON u.id=h.updated_by WHERE order_id=? ORDER BY h.id', [$o['id']]);
$pay     = fetch_one('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$o['id']]);
$ship    = json_decode($o['shipping_address'] ?? '[]', true) ?: [];
$reqs    = fetch_all('SELECT * FROM return_requests WHERE order_id=? ORDER BY id DESC', [$o['id']]);
page_header('Order ' . $o['order_no'], 'account');
?>
<div class="crumbs"><a href="<?= url('account', ['tab' => 'orders']) ?>">← My orders</a></div>
<div class="row-between"><h1>Order <?= e($o['order_no']) ?></h1><?= status_badge($o['status']) ?></div>
<div class="two-col wide-left">
  <div class="stack">
    <section class="card pad"><h3>Items</h3>
      <?php foreach ($items as $i): ?><div class="line"><span><?= (int)$i['qty'] ?> × <?= e($i['product_name']) ?><br><small class="muted"><?= e($i['variant_label']) ?></small></span><span><?= money($i['line_total']) ?></span></div><?php endforeach; ?>
      <hr><div class="line"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
      <div class="line"><span>Discount</span><span>−<?= money($o['discount']) ?></span></div>
      <div class="line"><span>Delivery fee</span><span><?= money($o['delivery_fee']) ?></span></div>
      <div class="line total"><span>Total</span><span><?= money($o['total']) ?></span></div></section>
    <section class="card pad"><h3>Delivery &amp; payment</h3>
      <p class="muted small"><strong><?= $o['delivery_method'] === 'pickup' ? 'Store pickup' : 'Standard delivery' ?></strong><br>
        <?= e($ship['recipient'] ?? '') ?> · <?= e($ship['phone'] ?? '') ?><br>
        <?= e(trim(($ship['street'] ?? '') . ', ' . ($ship['barangay'] ?? '') . ', ' . ($ship['city'] ?? '') . ' ' . ($ship['province'] ?? '') . ' ' . ($ship['postal_code'] ?? ''), ' ,')) ?>
        <?php if (!empty($ship['instructions'])): ?><br>Note: <?= e($ship['instructions']) ?><?php endif; ?></p>
      <p class="muted small">Payment: <?= $pay['method'] === 'cod' ? 'Cash on Delivery' : 'Mock online payment' ?> · <?= status_badge(ucfirst($pay['status'])) ?></p></section>
  </div>
  <div class="stack">
    <section class="card pad"><h3>Order tracking</h3>
      <ol class="timeline"><?php foreach ($history as $h): ?>
        <li><strong><?= e($h['status']) ?></strong><span class="muted small"><?= e(date('M j, Y g:i A', strtotime($h['created_at']))) ?><?= $h['first_name'] ? ' · ' . e($h['first_name']) : '' ?></span>
          <?php if ($h['remarks']): ?><span class="small"><?= e($h['remarks']) ?></span><?php endif; ?></li>
      <?php endforeach; ?></ol></section>

    <?php foreach ($reqs as $r): ?><section class="card pad"><h3><?= e(ucfirst($r['type'])) ?> request <?= status_badge($r['status']) ?></h3>
      <p class="muted small">Reason: <?= e($r['reason']) ?><?= $r['admin_remarks'] ? '<br>Our note: ' . e($r['admin_remarks']) : '' ?></p></section><?php endforeach; ?>

    <?php if (($canCancel || $canReturn) && !$activeReq): ?>
    <form method="post" class="card pad stack" data-validate novalidate><?= csrf_field() ?>
      <h3><?= $canCancel ? 'Request cancellation' : 'Request a return / refund' ?></h3>
      <label>Reason<select name="reason" required><option value="">Select a reason</option>
        <?php foreach (($canCancel ? ['Ordered by mistake', 'Changed my mind', 'Found a better price', 'Delivery takes too long'] : ['Item arrived damaged', 'Wrong item received', 'Item not as described', 'Quality issue']) as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></label>
      <label>Description<textarea name="description" rows="3" placeholder="Tell us a little more"></textarea></label>
      <button class="btn btn-danger">Submit request</button></form>
    <?php endif; ?>
  </div>
</div>
<?php page_footer();
