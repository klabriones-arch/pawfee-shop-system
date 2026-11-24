<?php
require __DIR__ . '/auth_card.php';
if (current_user()) redirect(home_for(current_user()));
$error = '';
if (is_post()) {
    csrf_check();
    $_SESSION['tries'] = $_SESSION['tries'] ?? ['n' => 0, 't' => time()];
    if (time() - $_SESSION['tries']['t'] > 300) $_SESSION['tries'] = ['n' => 0, 't' => time()];
    if ($_SESSION['tries']['n'] >= 5) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $id = strtolower(input('identity'));
        $u  = fetch_one('SELECT * FROM users WHERE (email=? OR username=?) AND status="active"', [$id, $id]);
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            $roles = array_column(fetch_all('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?', [$u['id']]), 'name');
            $staff = (bool)array_intersect(['admin','staff'], $roles);
            if ($staff !== ADMIN_SESSION) { flash('info', 'Please sign in using the appropriate account portal.'); redirect(url('login', ['workspace'=>$staff?'admin':'customer'])); }
            $_SESSION['tries'] = ['n' => 0, 't' => time()];
            if (!$u['email_verified']) {
                $_SESSION['verify_uid'] = $u['id']; $_SESSION['verify_from'] = 'login';
                $_SESSION['verify_next'] = $_GET['next'] ?? '';
                $code = '';
                if (!fetch_val('SELECT COUNT(*) FROM email_verifications WHERE user_id=? AND expires_at>DATE_ADD(NOW(), INTERVAL 14 MINUTE)',[$u['id']])) $code=send_verification_code($u);
                if (APP_DEBUG && $code !== '') flash('info', "Dev mode: your verification code is $code");
                redirect(url('verify'));
            }
            login_user($u);
            if (!empty($_POST['remember'])) setcookie(session_name(), session_id(), ['expires' => time() + 2592000, 'path' => BASE_URL . '/', 'httponly' => true, 'samesite' => 'Lax']);
            $roles = array_column(fetch_all('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?', [$u['id']]), 'name');
            $u['roles'] = $roles;
            $next = preg_replace('/[^a-z_]/', '', $_GET['next'] ?? '');
            redirect(in_array('customer', $roles, true) && in_array($next,['checkout','cart','account','orders','returns','shop','menu'],true) ? url($next) : home_for($u));
        }
        $_SESSION['tries']['n']++;
        $error = 'Incorrect username/email or password.';
    }
}
auth_open(ADMIN_SESSION ? 'Administrator Sign In' : 'Sign In');
if ($error) echo '<div class="alert alert-error">' . e($error) . '</div>'; ?>
<form method="post" class="stack" data-validate novalidate>
  <?= csrf_field() ?>
  <label>Username or Email Address<input name="identity" required placeholder="Enter your username or email address" value="<?= old('identity') ?>" autofocus></label>
  <label>Password<input type="password" name="password" required placeholder="Enter your password"></label>
  <div class="row-between small"><label class="check"><input type="checkbox" name="remember"> Remember me</label><a class="link" href="<?= url('forgot') ?>">Forgot Password ?</a></div>
  <button class="btn btn-block">Log in</button>
  <p class="center small"><a class="link" href="<?= url('login',['workspace'=>ADMIN_SESSION?'customer':'admin']) ?>"><?= ADMIN_SESSION?'Customer sign in':'Administrator sign in' ?></a></p>
  <p class="center small muted">Don’t have an account yet? <a class="link" href="<?= url('register') ?>">Sign up</a></p>
</form>
<?php auth_close();
