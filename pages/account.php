<?php
$user = require_login();
$tab  = in_array($_GET['tab'] ?? '', ['profile', 'addresses', 'orders', 'returns', 'settings'], true) ? $_GET['tab'] : 'orders';

if (is_post()) {
    csrf_check();
    $do = $_POST['do'] ?? '';
    if ($do === 'profile') {
        if (input('first_name') === '' || input('last_name') === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', input('phone'))) flash('error', 'Please enter a name and a valid phone number.');
        else {
            q('UPDATE users SET first_name=?, last_name=?, phone=?, dob=?, gender=? WHERE id=?',
                [input('first_name'), input('last_name'), input('phone'), input('dob') ?: null, input('gender') ?: null, $user['id']]);
            flash('success', 'Profile updated.');
        }
    } elseif ($do === 'address_add') {
        $f = array_map(fn($k) => input($k), ['label', 'recipient', 'phone', 'street', 'barangay', 'city', 'province', 'postal_code']);
        if (in_array('', array_slice($f, 1), true)) flash('error', 'Please complete every address field.');
        else {
            $first = !fetch_val('SELECT COUNT(*) FROM addresses WHERE user_id=?', [$user['id']]);
            q('INSERT INTO addresses (user_id,label,recipient,phone,street,barangay,city,province,postal_code,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)',
                array_merge([$user['id']], $f ?: [], [$first ? 1 : 0]));
            flash('success', 'Address saved.');
        }
    } elseif ($do === 'address_delete') {
        q('DELETE FROM addresses WHERE id=? AND user_id=?', [(int)$_POST['id'], $user['id']]);
        flash('success', 'Address removed.');
    } elseif ($do === 'address_default') {
        q('UPDATE addresses SET is_default = (id=?) WHERE user_id=?', [(int)$_POST['id'], $user['id']]);
        flash('success', 'Default address updated.');
    } elseif ($do === 'password') {
        if (!password_verify($_POST['current'] ?? '', $user['password_hash'])) flash('error', 'Current password is incorrect.');
        elseif ($m = validate_password($_POST['password'] ?? '')) flash('error', $m);
        elseif ($_POST['password'] !== ($_POST['confirm'] ?? '')) flash('error', 'Passwords do not match.');
        else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $user['id']]); flash('success', 'Password changed.'); }
    }
    redirect(url('account', ['tab' => $tab]));
}

page_header('My account', 'account');
$tabs = ['orders' => 'Order history', 'returns' => 'Returns & refunds', 'profile' => 'Profile', 'addresses' => 'Addresses', 'settings' => 'Account settings'];
?>
<div class="page-title"><h1>Hi, <?= e($user['first_name']) ?></h1></div>
<div class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="<?= url('account', ['tab' => $k]) ?>"><?= $l ?></a><?php endforeach; ?></div>

<?php if ($tab === 'orders'):
    $orders = fetch_all('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC', [$user['id']]); ?>
  <?php if (!$orders): ?><div class="empty"><p>No orders yet.</p><a class="btn" href="<?= url('shop') ?>">Start shopping</a></div>
  <?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><strong><?= e($o['order_no']) ?></strong></td><td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td><td><?= status_badge($o['status']) ?></td><td><?= money($o['total']) ?></td>
      <td><a class="btn btn-sm btn-ghost" href="<?= url('order', ['no' => $o['order_no']]) ?>">View &amp; track</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php elseif ($tab === 'returns'):
    $reqs = fetch_all('SELECT rr.*, o.order_no FROM return_requests rr JOIN orders o ON o.id=rr.order_id WHERE rr.user_id=? ORDER BY rr.id DESC', [$user['id']]);
    $refunds = fetch_all('SELECT r.*, o.order_no FROM refunds r JOIN orders o ON o.id=r.order_id WHERE o.user_id=? ORDER BY r.id DESC', [$user['id']]); ?>
  <h3>Requests</h3>
  <?php if (!$reqs): ?><p class="muted">No cancellation or return requests.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Order</th><th>Type</th><th>Reason</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
    <?php foreach ($reqs as $r): ?><tr><td><a class="link" href="<?= url('order', ['no' => $r['order_no']]) ?>"><?= e($r['order_no']) ?></a></td><td><?= e(ucfirst($r['type'])) ?></td><td><?= e($r['reason']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e($r['admin_remarks']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  <h3>Refunds</h3>
  <?php if (!$refunds): ?><p class="muted">No refunds yet.</p><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>Order</th><th>Amount</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($refunds as $r): ?><tr><td><?= e($r['order_no']) ?></td><td><?= money($r['amount']) ?></td><td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php elseif ($tab === 'profile'): ?>
  <form method="post" class="card pad stack narrow-form" data-validate novalidate><?= csrf_field() ?><input type="hidden" name="do" value="profile">
    <div class="row"><label>First name<input name="first_name" required value="<?= e($user['first_name']) ?>"></label><label>Last name<input name="last_name" required value="<?= e($user['last_name']) ?>"></label></div>
    <label>Email<input value="<?= e($user['email']) ?>" disabled></label>
    <label>Phone<input name="phone" required value="<?= e($user['phone']) ?>"></label>
    <div class="row"><label>Date of birth<input type="date" name="dob" value="<?= e($user['dob']) ?>"></label><label>Gender<input name="gender" value="<?= e($user['gender']) ?>"></label></div>
    <button class="btn">Save profile</button></form>

<?php elseif ($tab === 'addresses'):
    $addrs = fetch_all('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id', [$user['id']]); ?>
  <div class="two-col">
    <div class="stack"><?php foreach ($addrs as $a): ?><div class="card pad"><strong><?= e($a['label']) ?></strong> <?= $a['is_default'] ? '<span class="badge st-delivered">Default</span>' : '' ?>
      <p class="muted small"><?= e($a['recipient']) ?> · <?= e($a['phone']) ?><br><?= e("{$a['street']}, {$a['barangay']}, {$a['city']}, {$a['province']} {$a['postal_code']}") ?></p>
      <div class="row">
        <?php if (!$a['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="address_default"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-ghost">Make default</button></form><?php endif; ?>
        <form method="post" data-confirm="Delete this address?"><?= csrf_field() ?><input type="hidden" name="do" value="address_delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form></div></div>
    <?php endforeach; if (!$addrs) echo '<p class="muted">No saved addresses yet.</p>'; ?></div>
    <form method="post" class="card pad stack" data-validate novalidate><?= csrf_field() ?><input type="hidden" name="do" value="address_add"><h3>Add an address</h3>
      <label>Label<input name="label" value="Home"></label>
      <div class="row"><label>Recipient<input name="recipient" required></label><label>Phone<input name="phone" required></label></div>
      <label>Street<input name="street" required></label>
      <div class="row"><label>Barangay<input name="barangay" required></label><label>City<input name="city" required></label></div>
      <div class="row"><label>Province<input name="province" required></label><label>Postal code<input name="postal_code" required></label></div>
      <button class="btn">Save address</button></form>
  </div>

<?php else: ?>
  <form method="post" class="card pad stack narrow-form" data-validate novalidate><?= csrf_field() ?><input type="hidden" name="do" value="password"><h3>Change password</h3>
    <label>Current password<input type="password" name="current" required></label>
    <label>New password<input type="password" name="password" required minlength="8"></label>
    <label>Confirm new password<input type="password" name="confirm" required></label>
    <p class="muted small">Use 8+ characters, including a letter and a number.</p><button class="btn">Update password</button></form>
<?php endif; page_footer();
