<?php
require __DIR__ . '/auth_card.php';
if (is_post()) {
    csrf_check();
    $id = strtolower(input('identity'));
    $u  = fetch_one('SELECT * FROM users WHERE (email=? OR username=?) AND status="active"', [$id, $id]);
    if ($u) {
        $token = bin2hex(random_bytes(24));
        q('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))', [$u['id'], hash('sha256', $token)]);
        $link = rtrim(APP_URL, '/') . '/index.php?' . http_build_query(['page' => 'reset', 'token' => $token]);
        notify((int)$u['id'], $u['email'], 'password_reset', 'Reset your Pawfee password', "Use this link within 1 hour:\n$link");
        if (APP_DEBUG) flash('info', 'Dev mode: open the Mail preview to see the reset link.');
    }
    flash('success', 'If that account exists, a reset email will be attempted. Check your inbox and spam folder.');   // same message either way
    redirect(url('forgot'));
}
auth_open('Forgot Password', 'Enter your username or email address and we’ll send you a link to reset your password.'); ?>
<form method="post" class="stack" data-validate novalidate>
  <?= csrf_field() ?>
  <label>Username or Email Address<input name="identity" required placeholder="Enter your username or email address"></label>
  <button class="btn btn-block">Submit</button>
  <p class="center small muted">Back to <a class="link" href="<?= url('login') ?>">Sign in</a></p>
</form>
<?php auth_close();
