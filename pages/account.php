<?php
$user = require_role('customer');
$tab  = in_array($_GET['tab'] ?? '', ['overview', 'profile', 'addresses', 'orders', 'returns', 'settings'], true) ? $_GET['tab'] : 'overview';
if ($page==='orders') $tab='orders';
if ($page==='returns') $tab='returns';

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

$orders = fetch_all('SELECT o.*, (SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id=o.id) AS items, (SELECT status FROM payments WHERE order_id=o.id ORDER BY id DESC LIMIT 1) AS payment_status FROM orders o WHERE user_id=? ORDER BY id DESC',[$user['id']]);
if ($tab==='returns' && is_post()) {} // request submission is handled by the order page
page_header($tab==='orders'?'Order history':($tab==='returns'?'Returns & cancellations':'My account'),in_array($tab,['orders','returns'])?'orders':'account');
?>
<div class="crumbs">Home / My account<?= $tab==='orders'?' / Order history':($tab==='returns'?' / Returns & cancellations':'') ?></div>
<h1><?= $tab==='orders'?'Your orders, all in one place.':($tab==='returns'?'Returns & cancellations':($tab==='overview'?'Welcome back, '.e($user['first_name']).'.':(['profile'=>'Personal information','addresses'=>'Saved addresses','settings'=>'Account settings'][$tab]??'My account'))) ?></h1>
<div class="flow-account-layout"><?php account_nav($user,$tab,in_array($tab,['orders','returns'])); ?><div class="stack flow-account-content">
<?php if($tab==='overview'): $addrs=fetch_all('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC,id',[$user['id']]); ?>
<div class="two-col"><section class="card pad"><div class="row-between"><h3>Personal information</h3><a href="<?= url('account',['tab'=>'profile']) ?>">Edit details</a></div><small>FULL NAME</small><p><?= e($user['first_name'].' '.$user['last_name']) ?></p><small>PHONE NUMBER</small><p><?= e($user['phone']) ?></p><small>EMAIL ADDRESS</small><p><?= e($user['email']) ?></p></section><section class="card pad"><div class="row-between"><h3>Saved addresses</h3><a href="<?= url('account',['tab'=>'addresses']) ?>">+ Add new</a></div><?php foreach(array_slice($addrs,0,2) as $a): ?><div class="flow-sage pad"><strong><?= e($a['label']) ?></strong><?= $a['is_default']?' · Default':'' ?><p><?= e($a['street'].', '.$a['barangay'].', '.$a['city']) ?></p><a href="<?= url('account',['tab'=>'addresses']) ?>">Manage address</a></div><?php endforeach; if(!$addrs): ?><p>No saved addresses yet.</p><?php endif; ?></section></div>
<section class="card pad"><div class="row-between"><h3>Order history</h3><a href="<?= url('orders') ?>">View all orders →</a></div><?php foreach(array_slice($orders,0,3) as $o): ?><div class="line"><strong><?= e($o['order_no']) ?></strong><?= money($o['total']) ?> <?= status_badge($o['status']) ?><a href="<?= url('order',['no'=>$o['order_no']]) ?>">View order →</a></div><?php endforeach; if(!$orders): ?><p>No orders yet.</p><?php endif; ?></section>
<section class="card pad"><h3>Account settings</h3><p>Password & security</p><a href="<?= url('account',['tab'=>'settings']) ?>">Change password →</a></section>
<?php elseif ($tab === 'orders'): $filter=$_GET['filter']??'all'; $active=['Pending','Confirmed','Processing','Packed','Shipped','Out for Delivery']; $shown=array_values(array_filter($orders,fn($o)=>$filter==='active'?in_array($o['status'],$active,true):($filter==='delivered'?$o['status']==='Delivered':true))); $period=$_GET['period']??'all'; if($period==='30'||$period==='90') $shown=array_values(array_filter($shown,fn($o)=>strtotime($o['created_at'])>=time()-(int)$period*86400)); if(($_GET['sort']??'')==='oldest') $shown=array_reverse($shown); ?>
<div class="row-between"><div class="row"><?php foreach(['all'=>'All orders','active'=>'Active','delivered'=>'Delivered'] as $k=>$label): ?><a class="btn btn-sm <?= $filter===$k?'':'btn-ghost' ?>" href="<?= url('orders',['filter'=>$k,'period'=>$period,'sort'=>$_GET['sort']??'newest']) ?>"><?= $label ?> <?= count(array_filter($orders,fn($o)=>$k==='all'||($k==='active'?in_array($o['status'],$active,true):$o['status']==='Delivered'))) ?></a><?php endforeach; ?></div><form method="get" class="row"><input type="hidden" name="page" value="orders"><input type="hidden" name="filter" value="<?= e($filter) ?>"><select name="period" onchange="this.form.submit()"><option value="all">All time</option><option value="30" <?= selected($period,'30') ?>>Last 30 days</option><option value="90" <?= selected($period,'90') ?>>Last 90 days</option></select><select name="sort" onchange="this.form.submit()"><option value="newest">Newest first</option><option value="oldest" <?= selected($_GET['sort']??'','oldest') ?>>Oldest first</option></select></form></div>
<section class="card pad table-wrap"><table class="table"><thead><tr><th>Order number</th><th>Purchase date</th><th>Items</th><th>Order total</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($shown as $o): ?><tr><td><strong>#<?= e($o['order_no']) ?></strong><br><small><?= e(ucfirst($o['payment_status']??'pending')) ?></small></td><td><?= e(date('d M Y',strtotime($o['created_at']))) ?></td><td><?= (int)$o['items'] ?> items</td><td><?= money($o['total']) ?></td><td><?= status_badge($o['status']) ?></td><td><a class="btn btn-sm btn-ghost" href="<?= url('order',['no'=>$o['order_no']]) ?>">View details →</a></td></tr><?php endforeach; ?></tbody></table><?php if(!$shown): ?><p>No orders to display.</p><?php endif; ?></section><div class="row-between"><small>Showing <?= count($shown) ?> orders</small><a href="<?= url('returns') ?>">Returns & cancellations →</a></div>
<?php elseif ($tab==='returns'): require __DIR__.'/return_view.php'; ?>
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
<?php endif; ?></div></div><?php page_footer();
