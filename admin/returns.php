<?php
$uid = (int)current_user()['id'];
$id  = (int)($_GET['id'] ?? 0);
if (is_post()) {
    csrf_check();
    $rid = (int)$_POST['id']; $do = $_POST['do'] ?? '';
    $r = fetch_one('SELECT rr.*, o.order_no, o.customer_email, o.status AS order_status FROM return_requests rr JOIN orders o ON o.id=rr.order_id WHERE rr.id=?', [$rid]);
    try {
        if (!$r) throw new RuntimeException('Request not found.');
        if ($do === 'status') {
            $new = input('status'); $remarks = input('remarks');
            if (!in_array($new, ['Under Review', 'Approved', 'Rejected', 'Item Received', 'Closed'], true)) throw new RuntimeException('Invalid status.');
            q('UPDATE return_requests SET status=?, admin_remarks=? WHERE id=?', [$new, $remarks ?: null, $rid]);
            if ($new === 'Approved' && $r['type'] === 'cancellation') set_order_status((int)$r['order_id'], 'Cancelled', $uid, 'Cancellation approved');
            if ($new === 'Approved' && $r['type'] === 'return')       set_order_status((int)$r['order_id'], 'Returned', $uid, 'Return approved');
            if (in_array($new, ['Approved', 'Rejected'], true))
                notify((int)$r['user_id'], $r['customer_email'], 'return_decision', "Your {$r['type']} request was " . strtolower($new), "Order {$r['order_no']}: $new." . ($remarks ? "\nNote: $remarks" : ''));
            flash('success', 'Request updated.');
        } elseif ($do === 'refund') {
            process_refund($rid, (float)$_POST['amount'], !empty($_POST['restock']), $uid);
            flash('success', 'Refund processed.');
        }
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
    redirect(url('admin', ['section' => 'returns', 'id' => $rid]));
}

if ($id && ($r = fetch_one('SELECT rr.*, o.order_no, o.total, o.customer_name, o.status AS order_status FROM return_requests rr JOIN orders o ON o.id=rr.order_id WHERE rr.id=?', [$id]))) {
    $refund = fetch_one('SELECT r.*, u.first_name FROM refunds r LEFT JOIN users u ON u.id=r.processed_by WHERE r.return_request_id=?', [$id]);
    admin_header('Request #' . $id, 'returns'); ?>
  <p><a class="link" href="<?= url('admin', ['section' => 'returns']) ?>">← All requests</a></p>
  <div class="grid-2">
    <section class="card pad"><h3><?= e(ucfirst($r['type'])) ?> · <?= status_badge($r['status']) ?></h3>
      <p class="muted">Order <a class="link" href="<?= url('admin', ['section' => 'orders', 'id' => $r['order_id']]) ?>"><?= e($r['order_no']) ?></a> · <?= e($r['customer_name']) ?> · <?= money($r['total']) ?><br>
      Reason: <?= e($r['reason']) ?><br><?= e($r['description']) ?></p>
      <?php if ($refund): ?><p class="ok">Refunded <?= money($refund['amount']) ?> on <?= e(date('M j, Y', strtotime($refund['created_at']))) ?> by <?= e($refund['first_name'] ?? 'admin') ?>.</p><?php endif; ?></section>
    <section class="card pad stack"><h3>Review</h3>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="status">
        <label>Status<select name="status"><?php foreach (['Under Review', 'Approved', 'Rejected', 'Item Received', 'Closed'] as $s): ?><option <?= selected($r['status'], $s) ?>><?= $s ?></option><?php endforeach; ?></select></label>
        <label>Remarks<input name="remarks" value="<?= e($r['admin_remarks']) ?>"></label><button class="btn">Save</button></form>
      <?php if ($r['type'] === 'return' && in_array($r['status'], ['Approved', 'Item Received'], true)): ?><hr>
      <form method="post" class="stack" data-confirm="Process this refund?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="refund">
        <label>Refund amount<input type="number" step="0.01" min="0.01" max="<?= (float)$r['total'] ?>" name="amount" value="<?= (float)$r['total'] ?>" required></label>
        <label class="check"><input type="checkbox" name="restock" value="1"> Restock returned items</label><button class="btn btn-danger">Process refund</button></form><?php endif; ?></section>
  </div>
<?php admin_footer(); return; }

$rows = fetch_all('SELECT rr.*, o.order_no, o.customer_name FROM return_requests rr JOIN orders o ON o.id=rr.order_id ORDER BY FIELD(rr.status,"Requested","Under Review","Approved","Item Received") DESC, rr.id DESC');
admin_header('Returns & refunds', 'returns'); ?>
<div class="table-wrap card"><table class="table"><thead><tr><th>#</th><th>Order</th><th>Customer</th><th>Type</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['order_no']) ?></td><td><?= e($r['customer_name']) ?></td><td><?= e(ucfirst($r['type'])) ?></td><td><?= e($r['reason']) ?></td><td><?= status_badge($r['status']) ?></td><td><a class="btn btn-sm btn-ghost" href="<?= url('admin', ['section' => 'returns', 'id' => $r['id']]) ?>">Review</a></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="muted">No requests yet.</td></tr>'; ?></tbody></table></div>
<?php admin_footer();
