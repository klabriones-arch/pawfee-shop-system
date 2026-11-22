<?php
$id = (int)($_GET['id'] ?? 0);
if (is_post()) {
    csrf_check();
    q('UPDATE users SET status=IF(status="active","disabled","active") WHERE id=?', [(int)$_POST['id']]);
    flash('success', 'Account status changed.');
    redirect(url('admin', ['section' => 'customers', 'id' => (int)$_POST['id']]));
}
if ($id && ($c = fetch_one('SELECT * FROM users WHERE id=?', [$id]))) {
    $orders = fetch_all('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC', [$id]);
    admin_header($c['first_name'] . ' ' . $c['last_name'], 'customers'); ?>
  <p><a class="link" href="<?= url('admin', ['section' => 'customers']) ?>">← All customers</a></p>
  <div class="grid-2"><section class="card pad"><h3>Contact</h3><p class="muted"><?= e($c['email']) ?><br><?= e($c['phone']) ?><br>Joined <?= e(date('M j, Y', strtotime($c['created_at']))) ?></p>
    <p>Status: <?= status_badge(ucfirst($c['status'])) ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-sm btn-ghost"><?= $c['status'] === 'active' ? 'Disable account' : 'Enable account' ?></button></form></section>
  <section class="card pad"><h3>Order history</h3><table class="table"><tbody><?php foreach ($orders as $o): ?><tr><td><a class="link" href="<?= url('admin', ['section' => 'orders', 'id' => $o['id']]) ?>"><?= e($o['order_no']) ?></a></td><td><?= status_badge($o['status']) ?></td><td><?= money($o['total']) ?></td></tr><?php endforeach; if (!$orders) echo '<tr><td class="muted">No orders yet.</td></tr>'; ?></tbody></table></section></div>
<?php admin_footer(); return; }

$s = trim($_GET['q'] ?? '');
$rows = fetch_all('SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) orders_n FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name="customer"'
    . ($s !== '' ? ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)' : '') . ' ORDER BY u.id DESC', $s !== '' ? array_fill(0, 4, "%$s%") : []);
admin_header('Customers', 'customers'); ?>
<form class="row"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="customers"><input type="search" name="q" value="<?= e($s) ?>" placeholder="Search name, email or phone"><button class="btn btn-sm btn-ghost">Search</button></form>
<div class="table-wrap card"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $c): ?><tr><td><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td><td><?= e($c['email']) ?></td><td><?= e($c['phone']) ?></td><td><?= (int)$c['orders_n'] ?></td><td><?= status_badge(ucfirst($c['status'])) ?></td><td><a class="btn btn-sm btn-ghost" href="<?= url('admin', ['section' => 'customers', 'id' => $c['id']]) ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_footer();
