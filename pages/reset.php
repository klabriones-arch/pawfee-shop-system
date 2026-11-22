<?php
require __DIR__ . '/auth_card.php';
$token = $_GET['token'] ?? '';
$row = fetch_one('SELECT * FROM password_resets WHERE token_hash=? AND used=0 AND expires_at > NOW()', [hash('sha256', $token)]);
if (!$row) { flash('error', 'That reset link is invalid or has expired.'); redirect(url('forgot')); }
$error = '';
if (is_post()) {
    csrf_check();
    if ($m = validate_password($_POST['password'] ?? '')) $error = $m;
    elseif (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) $error = 'Passwords do not match.';
    else {
        q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $row['user_id']]);
        q('UPDATE password_resets SET used=1 WHERE id=?', [$row['id']]);
        flash('success', 'Password updated. You can sign in now.');
        redirect(url('login'));
    }
}
auth_open('Reset password');
if ($error) echo '<div class="alert alert-error">' . e($error) . '</div>'; ?>
<form method="post" class="stack" data-validate novalidate>
  <?= csrf_field() ?>
  <label>New password<input type="password" name="password" required minlength="8"></label>
  <label>Confirm password<input type="password" name="confirm" required></label>
  <p class="muted small">Use 8+ characters, including a letter and a number.</p>
  <button class="btn btn-block">Update password</button>
</form>
<?php auth_close();
