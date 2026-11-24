<?php
if (!defined('PAWFEE_ADMIN_REQUEST')) { http_response_code(403); exit('Forbidden'); }
$fulfillment = ($_GET['section'] ?? '') === 'fulfillment';
$orderSection = $fulfillment ? 'fulfillment' : 'orders';
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
        else throw new RuntimeException('Invalid order action.');
        flash('success', 'Order updated.');
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
    redirect(url('admin', ['section' => $orderSection, 'id' => $oid]));
}

if ($id && ($o = fetch_one('SELECT * FROM orders WHERE id=?', [$id]))) {
    $items = fetch_all('SELECT * FROM order_items WHERE order_id=?', [$id]);
    $hist  = fetch_all('SELECT h.*, u.first_name FROM order_status_history h LEFT JOIN users u ON u.id=h.updated_by WHERE order_id=? ORDER BY h.id', [$id]);
    $pay   = fetch_one('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$id]);
    $ship  = fetch_one('SELECT s.*, u.first_name, u.last_name FROM shipments s LEFT JOIN users u ON u.id=s.courier_id WHERE s.order_id=?', [$id]);
    $sa    = json_decode($o['shipping_address'] ?? '[]', true) ?: [];
    admin_header(($fulfillment ? 'Fulfillment · ' : 'Order ') . $o['order_no'], $orderSection); ?>
  <p><a class="link" href="<?= url('admin', ['section' => $orderSection]) ?>">← Back to list</a> <?= status_badge($o['status']) ?></p>
  <div class="grid-2">
    <section class="card pad"><h3>Customer</h3><p class="muted"><?= e($o['customer_name']) ?><br><?= e($o['customer_email']) ?><br><?= e($o['customer_phone']) ?></p>
      <h3>Shipping</h3><p class="muted"><?= $o['delivery_method'] === 'pickup' ? 'Store pickup' : e(trim(($sa['recipient'] ?? '') . ' · ' . ($sa['phone'] ?? '') . "\n" . ($sa['street'] ?? '') . ', ' . ($sa['barangay'] ?? '') . ', ' . ($sa['city'] ?? '') . ' ' . ($sa['province'] ?? '') . ' ' . ($sa['postal_code'] ?? ''))) ?>
        <?= !empty($sa['instructions']) ? '<br>Note: ' . e($sa['instructions']) : '' ?></p>
      <h3>Payment</h3><p class="muted"><?= $pay['method'] === 'cod' ? 'Cash on Delivery' : 'Mock online' ?> · <?= status_badge(ucfirst($pay['status'])) ?> · <?= money($pay['amount']) ?></p>
      <?php if ($ship): ?><h3>Delivery</h3><p class="muted">Managed by store · <?= e($o['status']) ?><?= $ship['remarks'] ? '<br>' . e($ship['remarks']) : '' ?></p><?php endif; ?></section>
    <section class="card pad"><h3>Items</h3>
      <?php foreach ($items as $i): ?><div class="line"><span><?= (int)$i['qty'] ?> × <?= e($i['product_name']) ?> <small class="muted"><?= e($i['variant_label']) ?></small></span><span><?= money($i['line_total']) ?></span></div><?php endforeach; ?>
      <hr><div class="line"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div><div class="line"><span>Discount</span><span>−<?= money($o['discount']) ?></span></div>
      <div class="line"><span>Delivery</span><span><?= money($o['delivery_fee']) ?></span></div><div class="line total"><span>Total</span><span><?= money($o['total']) ?></span></div></section>
    <section class="card pad"><h3><?= $fulfillment ? 'Packing, shipping & delivery update' : 'Update order' ?></h3>
      <?php if ($o['status'] === 'Pending'): ?><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><button class="btn" name="do" value="confirm">Confirm order</button></form><hr><?php endif; ?>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="do" value="status">
        <label>Status<select name="status"><?php foreach (ORDER_STATUSES as $s): ?><option <?= selected($o['status'], $s) ?>><?= $s ?></option><?php endforeach; ?></select></label>
        <label>Remarks<input name="remarks" placeholder="Optional note for the timeline"></label><button class="btn">Update status</button></form>
      <?php if (!in_array($o['status'], ['Cancelled', 'Delivered', 'Refunded', 'Failed'], true)): ?><hr>
      <form method="post" class="row" data-confirm="Cancel this order and restock items?"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="do" value="cancel"><input name="remarks" placeholder="Reason"><button class="btn btn-danger">Cancel order</button></form><?php endif; ?></section>
    <section class="card pad"><h3>Timeline</h3><ol class="timeline"><?php foreach ($hist as $h): ?><li><strong><?= e($h['status']) ?></strong><span class="muted small"><?= e(date('M j, Y g:i A', strtotime($h['created_at']))) ?><?= $h['first_name'] ? ' · ' . e($h['first_name']) : '' ?></span><?php if ($h['remarks']): ?><span class="small"><?= e($h['remarks']) ?></span><?php endif; ?></li><?php endforeach; ?></ol></section>
  </div>
<?php admin_footer(); return; }

$s = trim($_GET['q'] ?? ''); $st = $_GET['status'] ?? ''; $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$w = [$fulfillment ? "status IN ('Confirmed','Processing','Packed','Shipped','Out for Delivery','Delivered','Failed')" : '1=1']; $a = [];
if ($s !== '')  { $w[] = '(order_no LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)'; array_push($a, "%$s%", "%$s%", "%$s%"); }
if (in_array($st, ORDER_STATUSES, true)) { $w[] = 'status=?'; $a[] = $st; }
if ($from) { $w[] = 'DATE(created_at)>=?'; $a[] = $from; }
if ($to)   { $w[] = 'DATE(created_at)<=?'; $a[] = $to; }
$cond  = implode(' AND ', $w);
$pg    = paginate((int)fetch_val("SELECT COUNT(*) FROM orders WHERE $cond", $a), 15, (int)($_GET['p'] ?? 1));
$rows  = fetch_all("SELECT * FROM orders WHERE $cond ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $a);
admin_header($fulfillment ? 'Fulfillment & delivery' : 'Orders', $orderSection);
admin_stats(['Total orders'=>fetch_val('SELECT COUNT(*) FROM orders'),'Pending'=>fetch_val("SELECT COUNT(*) FROM orders WHERE status='Pending'"),'Packed / shipped'=>fetch_val("SELECT COUNT(*) FROM orders WHERE status IN ('Packed','Shipped')"),'Delivered'=>fetch_val("SELECT COUNT(*) FROM orders WHERE status='Delivered'")]); ?>
<form class="filters-bar card pad row" method="get"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="<?= e($orderSection) ?>">
  <input type="search" name="q" value="<?= e($s) ?>" placeholder="Order no. or customer">
  <select name="status"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $x): ?><option <?= selected($st, $x) ?>><?= $x ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-sm">Filter</button></form>
<div class="table-wrap card"><table class="table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $o): ?><tr><td><strong><?= e($o['order_no']) ?></strong></td><td><?= e($o['customer_name']) ?></td><td class="muted"><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td><td><?= status_badge($o['status']) ?></td><td><?= money($o['total']) ?></td>
    <td><a class="btn btn-sm btn-ghost" href="<?= url('admin', ['section' => $orderSection, 'id' => $o['id']]) ?>">Open</a></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="muted">No orders found.</td></tr>'; ?></tbody></table></div>
<?= pager($pg, 'admin', ['section' => $orderSection, 'q' => $s, 'status' => $st, 'from' => $from, 'to' => $to]) ?>
<?php admin_footer();
