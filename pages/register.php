<?php
require __DIR__ . '/auth_card.php';
if (current_user()) redirect(home_for(current_user()));
$err = [];
if (is_post()) {
    csrf_check();
    $f = ['first_name' => input('first_name'), 'last_name' => input('last_name'), 'dob' => input('dob'), 'gender' => input('gender'),
          'email' => strtolower(input('email')), 'phone' => input('phone')];
    if ($f['first_name'] === '' || $f['last_name'] === '') $err[] = 'Please enter your first and last name.';
    if ($f['dob'] !== '' && (!strtotime($f['dob']) || $f['dob'] > date('Y-m-d'))) $err[] = 'Enter a valid date of birth.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    elseif (fetch_val('SELECT COUNT(*) FROM users WHERE email=?', [$f['email']])) $err[] = 'That email is already registered.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $f['phone'])) $err[] = 'Enter a valid phone number.';
    if ($m = validate_password($_POST['password'] ?? '')) $err[] = $m;
    if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) $err[] = 'Passwords do not match.';
    if (empty($_POST['terms'])) $err[] = 'Please agree to the Terms and Privacy Policy.';

    if (!$err) {
        q('INSERT INTO users (first_name,last_name,username,email,phone,dob,gender,password_hash) VALUES (?,?,?,?,?,?,?,?)',
            [$f['first_name'], $f['last_name'], unique_username($f['email']), $f['email'], $f['phone'], $f['dob'] ?: null, $f['gender'] ?: null,
             password_hash($_POST['password'], PASSWORD_DEFAULT)]);
        $uid = (int)db()->lastInsertId();
        q('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name="customer"', [$uid]);
        $u = fetch_one('SELECT * FROM users WHERE id=?', [$uid]);
        notify($uid, $u['email'], 'registration', 'Welcome to Pawfee Shop', "Hi {$u['first_name']}, welcome to Pawfee Shop!");
        $code = send_verification_code($u);
        $_SESSION['verify_uid'] = $uid;
        $_SESSION['verify_from'] = 'register';
        if (APP_DEBUG) flash('info', "Dev mode: your verification code is $code (also visible at the Mail preview).");
        redirect(url('verify'));
    }
}
auth_open('Sign Up');
?>
<div class="steps"><span class="step active" data-step-tab="1">1 Personal information</span><span class="step" data-step-tab="2">2 Account details</span></div>
<?php foreach ($err as $m): ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>
<form method="post" class="stack" data-validate data-steps novalidate>
  <?= csrf_field() ?>
  <section data-step="1">
    <h3>Tell us about yourself</h3>
    <label>First name<input name="first_name" required placeholder="Enter your first name" value="<?= old('first_name') ?>"></label>
    <label>Last name<input name="last_name" required placeholder="Enter your last name" value="<?= old('last_name') ?>"></label>
    <label>Date of birth<input type="date" name="dob" max="<?= date('Y-m-d') ?>" value="<?= old('dob') ?>"></label>
    <label>Gender<select name="gender"><option value="">Prefer not to say</option><?php foreach (['Female', 'Male', 'Non-binary', 'Other'] as $g): ?><option <?= selected($_POST['gender'] ?? '', $g) ?>><?= $g ?></option><?php endforeach; ?></select></label>
    <p class="muted small">Next, add your contact and login details.</p>
    <button type="button" class="btn btn-block" data-next>Continue</button>
    <p class="center small muted">Already have an account? <a class="link" href="<?= url('login') ?>">Log in</a></p>
  </section>
  <section data-step="2" hidden>
    <h3>Set up your account</h3>
    <label>Email address<input type="email" name="email" required placeholder="Enter your email address" value="<?= old('email') ?>"></label>
    <label>Contact number<input type="tel" name="phone" required placeholder="Enter your phone number" value="<?= old('phone') ?>"></label>
    <label>Password<input type="password" name="password" required minlength="8" placeholder="Create a password" autocomplete="new-password"></label>
    <label>Confirm password<input type="password" name="confirm" required placeholder="Re-enter your password" autocomplete="new-password"></label>
    <p class="muted small">Use 8+ characters, including a letter and a number.</p>
    <label class="check"><input type="checkbox" name="terms" required> I agree to the Terms of Agreement and Privacy Policy.</label>
    <button class="btn btn-block">Create account</button>
    <p class="center small"><a class="link" href="#" data-prev>Back to personal information</a></p>
  </section>
</form>
<?php auth_close();
