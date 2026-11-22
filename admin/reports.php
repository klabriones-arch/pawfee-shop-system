<?php
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$to   = $_GET['to']   ?? date('Y-m-d');
$by   = in_array($_GET['by'] ?? '', ['day', 'week', 'month'], true) ? $_GET['by'] : 'day';
$fmt  = ['day' => '%Y-%m-%d', 'week' => '%x-W%v', 'month' => '%Y-%m'][$by];
$V = "o.status NOT IN ('Cancelled','Failed','Refunded') AND DATE(o.created_at) BETWEEN ? AND ?"; $r = [$from, $to];

$sum = fetch_one("SELECT COALESCE(SUM(total),0) revenue, COUNT(*) orders_n FROM orders o WHERE $V", $r);
$series = fetch_all("SELECT DATE_FORMAT(o.created_at,'$fmt') label, SUM(o.total) revenue, COUNT(*) orders_n FROM orders o WHERE $V GROUP BY label ORDER BY label", $r);
$best = fetch_all("SELECT oi.product_name, SUM(oi.qty) qty, SUM(oi.line_total) revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $V GROUP BY oi.product_name ORDER BY qty DESC LIMIT 10", $r);
$cancelled = fetch_all('SELECT order_no, customer_name, total, created_at FROM orders WHERE status="Cancelled" AND DATE(created_at) BETWEEN ? AND ? ORDER BY id DESC', $r);
$refunds   = fetch_all('SELECT r.*, o.order_no FROM refunds r JOIN orders o ON o.id=r.order_id WHERE DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.id DESC', $r);
$inventory = fetch_all('SELECT name, sku, stock, low_stock_threshold FROM products WHERE status="active" ORDER BY stock');
admin_header('Reports', 'reports'); ?>
<form class="filters-bar card pad row" method="get"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="reports">
  <label class="inline">From <input type="date" name="from" value="<?= e($from) ?>"></label><label class="inline">To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <select name="by"><?php foreach (['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'] as $k => $l): ?><option value="<?= $k ?>" <?= selected($by, $k) ?>><?= $l ?> sales</option><?php endforeach; ?></select><button class="btn btn-sm">Run report</button></form>
<div class="stat-grid"><div class="stat"><span class="muted small">Revenue</span><strong><?= money($sum['revenue']) ?></strong></div><div class="stat"><span class="muted small">Orders</span><strong><?= (int)$sum['orders_n'] ?></strong></div>
  <div class="stat"><span class="muted small">Cancelled</span><strong><?= count($cancelled) ?></strong></div><div class="stat"><span class="muted small">Refunded</span><strong><?= money(array_sum(array_column($refunds, 'amount'))) ?></strong></div></div>
<div class="grid-2">
  <section class="card pad"><h3><?= ucfirst($by) ?> sales</h3><table class="table"><thead><tr><th>Period</th><th>Orders</th><th>Revenue</th></tr></thead><tbody><?php foreach ($series as $s): ?><tr><td><?= e($s['label']) ?></td><td><?= (int)$s['orders_n'] ?></td><td><?= money($s['revenue']) ?></td></tr><?php endforeach; if (!$series) echo '<tr><td colspan="3" class="muted">No sales in range.</td></tr>'; ?></tbody></table></section>
  <section class="card pad"><h3>Best-selling products</h3><table class="table"><thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead><tbody><?php foreach ($best as $b): ?><tr><td><?= e($b['product_name']) ?></td><td><?= (int)$b['qty'] ?></td><td><?= money($b['revenue']) ?></td></tr><?php endforeach; ?></tbody></table></section>
  <section class="card pad"><h3>Cancelled orders</h3><table class="table"><tbody><?php foreach ($cancelled as $c): ?><tr><td><?= e($c['order_no']) ?></td><td><?= e($c['customer_name']) ?></td><td><?= money($c['total']) ?></td></tr><?php endforeach; if (!$cancelled) echo '<tr><td class="muted">None.</td></tr>'; ?></tbody></table></section>
  <section class="card pad"><h3>Refunds</h3><table class="table"><tbody><?php foreach ($refunds as $f): ?><tr><td><?= e($f['order_no']) ?></td><td><?= e($f['reason']) ?></td><td><?= money($f['amount']) ?></td></tr><?php endforeach; if (!$refunds) echo '<tr><td class="muted">None.</td></tr>'; ?></tbody></table></section>
</div>
<section class="card pad"><h3>Product inventory</h3><table class="table"><thead><tr><th>Product</th><th>SKU</th><th>Stock</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($inventory as $p): ?><tr><td><?= e($p['name']) ?></td><td class="muted"><?= e($p['sku']) ?></td><td><?= (int)$p['stock'] ?></td><td><?= $p['stock'] == 0 ? '<span class="badge st-cancelled">Out of stock</span>' : ($p['stock'] <= $p['low_stock_threshold'] ? '<span class="badge st-pending">Low stock</span>' : '<span class="badge st-delivered">OK</span>') ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php admin_footer();
