<?php
$uid = (int)current_user()['id'];
$isAdmin = has_role('admin');
$scope = $isAdmin ? '1=1' : 's.courier_id=' . $uid;     // couriers only ever see their own assignments
$id = (int)($_GET['id'] ?? 0);

if (is_post()) {
    csrf_check();
    $oid = (int)$_POST['order_id'];
    $s = fetch_one("SELECT s.* FROM shipments s WHERE s.order_id=? AND $scope", [$oid]);
    $new = $_POST['status'] ?? '';
    if (!$s) flash('error', 'That delivery is not assigned to you.');
    elseif (!in_array($new, ['Out for Delivery', 'Delivered', 'Failed'], true)) flash('error', 'Invalid status.');
    elseif ($new === 'Failed' && input('remarks') === '') flash('error', 'Add a remark explaining the failed attempt.');
    else {
        q('UPDATE shipments SET status=?, remarks=?, attempts=attempts+?, delivered_at=IF(?="Delivered",NOW(),delivered_at) WHERE order_id=?', [$new, input('remarks') ?: null, $new === 'Failed' ? 1 : 0, $new, $oid]);
        set_order_status($oid, $new, $uid, input('remarks'));
        flash('success', 'Delivery updated.');
    }
    redirect(url('courier', ['id' => $oid]));
}

page_header('Courier');
if ($id && ($o = fetch_one("SELECT o.*, s.status AS ship_status, s.remarks AS ship_remarks, s.attempts FROM orders o JOIN shipments s ON s.order_id=o.id WHERE o.id=? AND $scope", [$id]))):
    $sa = json_decode($o['shipping_address'] ?? '[]', true) ?: [];
    $items = fetch_all('SELECT * FROM order_items WHERE order_id=?', [$id]);
    $pay = fetch_one('SELECT * FROM payments WHERE order_id=?', [$id]); ?>
  <p><a class="link" href="<?= url('courier') ?>">← Assigned deliveries</a></p>
  <h1><?= e($o['order_no']) ?> <?= status_badge($o['status']) ?></h1>
  <div class="two-col">
    <section class="card pad"><h3>Deliver to</h3><p class="muted"><strong><?= e($sa['recipient'] ?? $o['customer_name']) ?></strong><br>
      <a class="link" href="tel:<?= e($sa['phone'] ?? $o['customer_phone']) ?>"><?= e($sa['phone'] ?? $o['customer_phone']) ?></a><br>
      <?= e(($sa['street'] ?? '') . ', ' . ($sa['barangay'] ?? '') . ', ' . ($sa['city'] ?? '') . ' ' . ($sa['province'] ?? '') . ' ' . ($sa['postal_code'] ?? '')) ?>
      <?= !empty($sa['instructions']) ? '<br><em>' . e($sa['instructions']) . '</em>' : '' ?></p>
      <h3>Collect</h3><p class="muted"><?= $pay['method'] === 'cod' ? 'Cash on Delivery: ' . money($o['total']) : 'Already paid online' ?></p>
      <h3>Items</h3><?php foreach ($items as $i): ?><div class="line"><span><?= (int)$i['qty'] ?> × <?= e($i['product_name']) ?></span><span class="muted small"><?= e($i['variant_label']) ?></span></div><?php endforeach; ?></section>
    <form method="post" class="card pad stack"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><h3>Update delivery</h3>
      <p class="muted small">Current: <?= e($o['ship_status']) ?> · Attempts: <?= (int)$o['attempts'] ?></p>
      <label>Delivery remarks<textarea name="remarks" rows="3"><?= e($o['ship_remarks']) ?></textarea></label>
      <button class="btn" name="status" value="Out for Delivery">Out for delivery</button>
      <button class="btn" name="status" value="Delivered">Confirm delivered</button>
      <button class="btn btn-danger" name="status" value="Failed">Record failed attempt</button></form>
  </div>
<?php else:
    $rows = fetch_all("SELECT o.id, o.order_no, o.customer_name, o.status, o.shipping_address, s.status ship_status FROM shipments s JOIN orders o ON o.id=s.order_id WHERE $scope AND s.courier_id IS NOT NULL ORDER BY s.status='Delivered', o.id DESC"); ?>
  <div class="page-title"><h1>Assigned deliveries</h1></div>
  <?php if (!$rows): ?><div class="empty">No deliveries assigned yet.</div><?php else: ?>
  <div class="table-wrap card"><table class="table"><thead><tr><th>Order</th><th>Customer</th><th>Address</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): $a = json_decode($r['shipping_address'], true) ?: []; ?><tr><td><strong><?= e($r['order_no']) ?></strong></td><td><?= e($r['customer_name']) ?></td><td class="muted"><?= e(($a['street'] ?? '') . ', ' . ($a['city'] ?? '')) ?></td><td><?= status_badge($r['ship_status']) ?></td><td><a class="btn btn-sm btn-ghost" href="<?= url('courier', ['id' => $r['id']]) ?>">Details</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php endif; page_footer();
