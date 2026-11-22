<?php
require __DIR__ . '/auth_card.php';
$uid = $_SESSION['verify_uid'] ?? 0;
$u = $uid ? fetch_one('SELECT * FROM users WHERE id=?', [$uid]) : null;
if (!$u) redirect(url('login'));
$error = '';
if (is_post()) {
    csrf_check();
    if (isset($_POST['resend'])) {
        $code = send_verification_code($u);
        flash('success', 'A new code is on its way.');
        if (APP_DEBUG) flash('info', "Dev mode: your verification code is $code");
        redirect(url('verify'));
    }
    $row = fetch_one('SELECT * FROM email_verifications WHERE user_id=? AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [$uid]);
    if ($row && password_verify(input('code'), $row['code_hash'])) {
        q('UPDATE users SET email_verified=1 WHERE id=?', [$uid]);
        q('DELETE FROM email_verifications WHERE user_id=?', [$uid]);
        unset($_SESSION['verify_uid']);
        login_user($u);
        flash('success', 'Email verified. Welcome to Pawfee Shop!');
        redirect(url('account'));
    }
    $error = 'That code is incorrect or has expired.';
}
$back = ($_SESSION['verify_from'] ?? 'login') === 'register' ? ['register', 'Sign up'] : ['login', 'Sign in'];
auth_open('Verify your email', 'Enter the 6-digit code we’ve sent to your email address to verify your identity.');
if ($error) echo '<div class="alert alert-error">' . e($error) . '</div>'; ?>
<form method="post" class="stack" data-validate novalidate>
  <?= csrf_field() ?>
  <label>Email verification code<input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit code" autofocus></label>
  <p class="center small muted">Didn’t receive a code? <button class="linklike" name="resend" value="1" formnovalidate>Resend code</button></p>
  <button class="btn btn-block">Verify email</button>
  <p class="center small muted">Back to <a class="link" href="<?= url($back[0]) ?>"><?= $back[1] ?></a></p>
</form>
<?php auth_close();
