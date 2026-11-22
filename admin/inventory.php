<?php
$uid = (int)current_user()['id'];
if (is_post()) {
    csrf_check();
    $pid = (int)($_POST['product_id'] ?? 0); $qty = (int)($_POST['qty'] ?? 0); $mode = $_POST['mode'] ?? 'in'; $reason = input('reason');
    if (!$pid || $qty <= 0 || $reason === '') flash('error', 'Choose a product, enter a quantity above zero, and give a reason.');
    else {
        $pdo = db(); $pdo->beginTransaction();
        try {
            adjust_stock($pid, $mode === 'out' ? -$qty : $qty, ($mode === 'out' ? 'Stock-out: ' : 'Stock-in: ') . $reason, $uid);
            $pdo->commit(); flash('success', 'Stock updated.');
        } catch (RuntimeException $e) { $pdo->rollBack(); flash('error', $e->getMessage()); }   // blocks negative stock
    }
    redirect(url('admin', ['section' => 'inventory']));
}
$products = fetch_all('SELECT id,name,sku,stock,low_stock_threshold,status FROM products ORDER BY stock ASC, name');
$history  = fetch_all('SELECT t.*, p.name, u.first_name FROM inventory_transactions t JOIN products p ON p.id=t.product_id LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 40');
$alerts   = array_filter($products, fn($p) => $p['status'] === 'active' && $p['stock'] <= $p['low_stock_threshold']);
admin_header('Inventory', 'inventory');
foreach ($alerts as $a): ?><div class="alert alert-<?= $a['stock'] == 0 ? 'error' : 'info' ?>"><?= $a['stock'] == 0 ? 'Out of stock' : 'Low stock' ?>: <strong><?= e($a['name']) ?></strong> (<?= (int)$a['stock'] ?> left)</div><?php endforeach; ?>
<div class="grid-2 wide-left">
  <section class="card pad"><h3>Current stock</h3><div class="table-wrap"><table class="table"><thead><tr><th>Product</th><th>SKU</th><th>Stock</th><th>Alert at</th></tr></thead><tbody>
    <?php foreach ($products as $p): ?><tr><td><?= e($p['name']) ?></td><td class="muted"><?= e($p['sku']) ?></td><td class="<?= $p['stock'] == 0 ? 'err' : ($p['stock'] <= $p['low_stock_threshold'] ? 'warn' : '') ?>"><strong><?= (int)$p['stock'] ?></strong></td><td><?= (int)$p['low_stock_threshold'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
  <form method="post" class="card pad stack" data-validate novalidate><?= csrf_field() ?><h3>Adjust stock</h3>
    <label>Product<select name="product_id" required><option value="">Select…</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= (int)$p['stock'] ?>)</option><?php endforeach; ?></select></label>
    <label>Type<select name="mode"><option value="in">Stock-in</option><option value="out">Stock-out</option></select></label>
    <label>Quantity<input type="number" name="qty" min="1" required></label>
    <label>Reason<input name="reason" required placeholder="Delivery from supplier, spoilage, …"></label>
    <button class="btn">Record adjustment</button></form>
</div>
<section class="card pad"><h3>Transaction history</h3><div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Product</th><th>Change</th><th>Before → After</th><th>Reason</th><th>By</th></tr></thead><tbody>
  <?php foreach ($history as $h): ?><tr><td class="muted"><?= e(date('M j, g:i A', strtotime($h['created_at']))) ?></td><td><?= e($h['name']) ?></td><td class="<?= $h['change_qty'] < 0 ? 'err' : 'ok' ?>"><?= $h['change_qty'] > 0 ? '+' : '' ?><?= (int)$h['change_qty'] ?></td><td><?= (int)$h['previous_stock'] ?> → <?= (int)$h['new_stock'] ?></td><td><?= e($h['reason']) ?></td><td><?= e($h['first_name'] ?? 'System') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_footer();
