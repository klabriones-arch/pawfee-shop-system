<?php
if (!defined('PAWFEE_ADMIN_REQUEST')) { http_response_code(403); exit('Forbidden'); }
$VALID = "o.status NOT IN ('Cancelled','Failed','Refunded')";
$stats = [
    'Total sales'     => money(fetch_val("SELECT COALESCE(SUM(total),0) FROM orders o WHERE $VALID")),
    "Today's sales"   => money(fetch_val("SELECT COALESCE(SUM(total),0) FROM orders o WHERE $VALID AND DATE(created_at)=CURDATE()")),
    'Total orders'    => (int)fetch_val('SELECT COUNT(*) FROM orders'),
    'Pending orders'  => (int)fetch_val('SELECT COUNT(*) FROM orders WHERE status="Pending"'),
    'Customers'       => (int)fetch_val('SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name="customer"'),
    'Low-stock items' => (int)fetch_val('SELECT COUNT(*) FROM products WHERE status="active" AND stock<=low_stock_threshold'),
];
$salesByDate = fetch_all("SELECT DATE_FORMAT(MIN(created_at),'%b %e') label, SUM(total) v FROM orders o WHERE $VALID AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
$byStatus    = fetch_all('SELECT status label, COUNT(*) v FROM orders GROUP BY status ORDER BY v DESC');
$top         = fetch_all("SELECT oi.product_name label, SUM(oi.qty) v FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $VALID GROUP BY oi.product_name ORDER BY v DESC LIMIT 5");
$byCat       = fetch_all("SELECT c.name label, SUM(oi.line_total) v FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN products p ON p.id=oi.product_id JOIN categories c ON c.id=p.category_id WHERE $VALID GROUP BY c.id, c.name ORDER BY v DESC");
$recent      = fetch_all('SELECT * FROM orders ORDER BY id DESC LIMIT 6');
$low         = fetch_all('SELECT name, stock, low_stock_threshold FROM products WHERE status="active" AND stock<=low_stock_threshold ORDER BY stock LIMIT 8');
admin_header('Dashboard', 'dashboard');
?>
<div class="stat-grid"><?php foreach ($stats as $l => $v): ?><div class="stat"><span class="muted small"><?= e($l) ?></span><strong><?= e($v) ?></strong></div><?php endforeach; ?></div>
<div class="grid-2">
  <section class="card pad"><h3>Sales (last 7 days)</h3><?= bar_chart($salesByDate, 'label', 'v', true) ?></section>
  <section class="card pad"><h3>Orders by status</h3><?= bar_chart($byStatus, 'label', 'v') ?></section>
  <section class="card pad"><h3>Top-selling products</h3><?= bar_chart($top, 'label', 'v') ?></section>
  <section class="card pad"><h3>Revenue by category</h3><?= bar_chart($byCat, 'label', 'v', true) ?></section>
  <section class="card pad"><h3>Recent orders</h3><table class="table"><tbody>
    <?php foreach ($recent as $o): ?><tr><td><a class="link" href="<?= url('admin', ['section' => 'orders', 'id' => $o['id']]) ?>"><?= e($o['order_no']) ?></a></td><td><?= e($o['customer_name']) ?></td><td><?= status_badge($o['status']) ?></td><td><?= money($o['total']) ?></td></tr><?php endforeach; ?></tbody></table></section>
  <section class="card pad"><h3>Low-stock alerts</h3>
    <?php if (!$low) echo '<p class="muted">All stocked up.</p>'; foreach ($low as $p): ?>
      <div class="line"><span><?= e($p['name']) ?></span><span class="<?= $p['stock'] == 0 ? 'err' : 'warn' ?>"><?= $p['stock'] == 0 ? 'Out of stock' : (int)$p['stock'] . ' left' ?></span></div><?php endforeach; ?></section>
</div>
<?php admin_footer();
