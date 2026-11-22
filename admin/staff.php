<?php
$me = (int)current_user()['id'];
if (is_post()) {
    csrf_check();
    if (($_POST['do'] ?? '') === 'toggle' && (int)$_POST['id'] !== $me) {
        q('UPDATE users SET status=IF(status="active","disabled","active") WHERE id=?', [(int)$_POST['id']]); flash('success', 'Account status changed.');
    } elseif (($_POST['do'] ?? '') === 'create') {
        $email = strtolower(input('email')); $role = input('role');
        if (input('first_name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['staff', 'courier', 'admin'], true)) flash('error', 'Complete all fields with a valid email.');
        elseif (fetch_val('SELECT COUNT(*) FROM users WHERE email=?', [$email])) flash('error', 'That email is already used.');
        elseif ($m = validate_password($_POST['password'] ?? '')) flash('error', $m);
        else {
            q('INSERT INTO users (first_name,last_name,username,email,phone,password_hash,email_verified) VALUES (?,?,?,?,?,?,1)',
                [input('first_name'), input('last_name'), unique_username($email), $email, input('phone'), password_hash($_POST['password'], PASSWORD_DEFAULT)]);
            q('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name=?', [db()->lastInsertId(), $role]);
            flash('success', ucfirst($role) . ' account created.');
        }
    }
    redirect(url('admin', ['section' => 'staff']));
}
$rows = fetch_all('SELECT u.*, GROUP_CONCAT(r.name) roles FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id GROUP BY u.id HAVING roles <> "customer" ORDER BY u.id');
admin_header('Staff', 'staff'); ?>
<div class="grid-2 wide-left">
  <div class="table-wrap card"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $u): ?><tr><td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['roles']) ?></td><td><?= status_badge(ucfirst($u['status'])) ?></td>
      <td><?php if ($u['id'] != $me): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-ghost"><?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
  <form method="post" class="card pad stack" data-validate novalidate><?= csrf_field() ?><input type="hidden" name="do" value="create"><h3>Add team member</h3>
    <div class="row"><label>First name<input name="first_name" required></label><label>Last name<input name="last_name"></label></div>
    <label>Email<input type="email" name="email" required></label><label>Phone<input name="phone"></label>
    <label>Role<select name="role"><option value="staff">Staff</option><option value="courier">Courier</option><option value="admin">Admin</option></select></label>
    <label>Temporary password<input type="password" name="password" required></label><button class="btn">Create account</button></form>
</div>
<?php admin_footer();
