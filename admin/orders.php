<?php
$uid = (int)current_user()['id'];
$id  = (int)($_GET['id'] ?? 0);

if (is_post()) {
    csrf_check();
    $oid = (int)($_POST['order_id'] ?? 0);
    $do  = $_POST['do'] ?? '';
    try {
        if ($do === 'status') set_order_status($oid, input('status'), $uid, input('remarks'));
        elseif ($do === 'confirm') set_order_status($oid, 'Confirmed', $uid, 'Order confirmed');
        elseif ($do === 'cancel') set_order_status($oid, 'Cancelled', $uid, input('remarks') ?: 'Cancelled by staff');
        elseif ($do === 'assign') {
            $c = fetch_one('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=? AND r.name="courier"', [(int)$_POST['courier_id']]);
            if (!$c) throw new RuntimeException('Choose a valid courier.');
            q('INSERT INTO shipments (order_id,courier_id) VALUES (?,?) ON DUPLICATE KEY UPDATE courier_id=VALUES(courier_id), status="Assigned"', [$oid, $c['id']]);
            q('INSERT INTO order_status_history (order_id,status,remarks,updated_by) SELECT id,status,"Courier assigned",? FROM orders WHERE id=?', [$uid, $oid]);
        }
        flash('success', 'Order updated.');
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
    redirect(url('admin', ['section' => 'orders', 'id' => $oid]));
}

if ($id && ($o = fetch_one('SELECT * FROM orders WHERE id=?', [$id]))) {
    $items = fetch_all('SELECT * FROM order_items WHERE order_id=?', [$id]);
    $hist  = fetch_all('SELECT h.*, u.first_name FROM order_status_history h LEFT JOIN users u ON u.id=h.updated_by WHERE order_id=? ORDER BY h.id', [$id]);
    $pay   = fetch_one('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$id]);
    $ship  = fetch_one('SELECT s.*, u.first_name, u.last_name FROM shipments s LEFT JOIN users u ON u.id=s.courier_id WHERE s.order_id=?', [$id]);
    $sa    = json_decode($o['shipping_address'] ?? '[]', true) ?: [];
    $couriers = fetch_all('SELECT u.id,u.first_name,u.last_name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name="courier" AND u.status="active"');
    admin_header('Order ' . $o['order_no'], 'orders'); ?>
  <p><a class="link" href="<?= url('admin', ['section' => 'orders']) ?>">← All orders</a> <?= status_badge($o['status']) ?></p>
  <div class="grid-2">
    <section class="card pad"><h3>Customer</h3><p class="muted"><?= e($o['customer_name']) ?><br><?= e($o['customer_email']) ?><br><?= e($o['customer_phone']) ?></p>
      <h3>Shipping</h3><p class="muted"><?= $o['delivery_method'] === 'pickup' ? 'Store pickup' : e(trim(($sa['recipient'] ?? '') . ' · ' . ($sa['phone'] ?? '') . "\n" . ($sa['street'] ?? '') . ', ' . ($sa['barangay'] ?? '') . ', ' . ($sa['city'] ?? '') . ' ' . ($sa['province'] ?? '') . ' ' . ($sa['postal_code'] ?? ''))) ?>
        <?= !empty($sa['instructions']) ? '<br>Note: ' . e($sa['instructions']) : '' ?></p>
      <h3>Payment</h3><p class="muted"><?= $pay['method'] === 'cod' ? 'Cash on Delivery' : 'Mock online' ?> · <?= status_badge(ucfirst($pay['status'])) ?> · <?= money($pay['amount']) ?></p>
      <?php if ($ship): ?><h3>Delivery</h3><p class="muted">Courier: <?= $ship['first_name'] ? e($ship['first_name'] . ' ' . $ship['last_name']) : 'Unassigned' ?> · <?= e($ship['status']) ?><?= $ship['remarks'] ? '<br>' . e($ship['remarks']) : '' ?></p><?php endif; ?></section>
    <section class="card pad"><h3>Items</h3>
      <?php foreach ($items as $i): ?><div class="line"><span><?= (int)$i['qty'] ?> × <?= e($i['product_name']) ?> <small class="muted"><?= e($i['variant_label']) ?></small></span><span><?= money($i['line_total']) ?></span></div><?php endforeach; ?>
      <hr><div class="line"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div><div class="line"><span>Discount</span><span>−<?= money($o['discount']) ?></span></div>
      <div class="line"><span>Delivery</span><span><?= money($o['delivery_fee']) ?></span></div><div class="line total"><span>Total</span><span><?= money($o['total']) ?></span></div></section>
    <section class="card pad"><h3>Update order</h3>
      <?php if ($o['status'] === 'Pending'): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><button class="btn" name="do" value="confirm">Confirm order</button></form><hr><?php endif; ?>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="do" value="status">
        <label>Status<select name="status"><?php foreach (ORDER_STATUSES as $s): ?><option <?= selected($o['status'], $s) ?>><?= $s ?></option><?php endforeach; ?></select></label>
        <label>Remarks<input name="remarks" placeholder="Optional note for the timeline"></label><button class="btn">Update status</button></form>
      <?php if (has_role('admin') && $o['delivery_method'] === 'standard'): ?><hr>
      <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="do" value="assign">
        <select name="courier_id"><option value="">Assign courier…</option><?php foreach ($couriers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option><?php endforeach; ?></select><button class="btn btn-ghost">Assign</button></form><?php endif; ?>
      <?php if (!in_array($o['status'], ['Cancelled', 'Delivered', 'Refunded', 'Failed'], true)): ?><hr>
      <form method="post" class="row" data-confirm="Cancel this order and restock items?"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="do" value="cancel"><input name="remarks" placeholder="Reason"><button class="btn btn-danger">Cancel order</button></form><?php endif; ?></section>
    <section class="card pad"><h3>Timeline</h3><ol class="timeline"><?php foreach ($hist as $h): ?><li><strong><?= e($h['status']) ?></strong><span class="muted small"><?= e(date('M j, Y g:i A', strtotime($h['created_at']))) ?><?= $h['first_name'] ? ' · ' . e($h['first_name']) : '' ?></span><?php if ($h['remarks']): ?><span class="small"><?= e($h['remarks']) ?></span><?php endif; ?></li><?php endforeach; ?></ol></section>
  </div>
<?php admin_footer(); return; }

$s = trim($_GET['q'] ?? ''); $st = $_GET['status'] ?? ''; $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$w = ['1=1']; $a = [];
if ($s !== '')  { $w[] = '(order_no LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)'; array_push($a, "%$s%", "%$s%", "%$s%"); }
if (in_array($st, ORDER_STATUSES, true)) { $w[] = 'status=?'; $a[] = $st; }
if ($from) { $w[] = 'DATE(created_at)>=?'; $a[] = $from; }
if ($to)   { $w[] = 'DATE(created_at)<=?'; $a[] = $to; }
$cond  = implode(' AND ', $w);
$pg    = paginate((int)fetch_val("SELECT COUNT(*) FROM orders WHERE $cond", $a), 15, (int)($_GET['p'] ?? 1));
$rows  = fetch_all("SELECT * FROM orders WHERE $cond ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $a);
admin_header('Orders', 'orders'); ?>
<form class="filters-bar card pad row" method="get"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="orders">
  <input type="search" name="q" value="<?= e($s) ?>" placeholder="Order no. or customer">
  <select name="status"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $x): ?><option <?= selected($st, $x) ?>><?= $x ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-sm">Filter</button></form>
<div class="table-wrap card"><table class="table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $o): ?><tr><td><strong><?= e($o['order_no']) ?></strong></td><td><?= e($o['customer_name']) ?></td><td class="muted"><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td><td><?= status_badge($o['status']) ?></td><td><?= money($o['total']) ?></td>
    <td><a class="btn btn-sm btn-ghost" href="<?= url('admin', ['section' => 'orders', 'id' => $o['id']]) ?>">Open</a></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="muted">No orders found.</td></tr>'; ?></tbody></table></div>
<?= pager($pg, 'admin', ['section' => 'orders', 'q' => $s, 'status' => $st, 'from' => $from, 'to' => $to]) ?>
<?php admin_footer();
