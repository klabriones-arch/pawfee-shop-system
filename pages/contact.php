<?php
if (is_post()) {
    csrf_check();
    if (input('name') && filter_var(input('email'), FILTER_VALIDATE_EMAIL) && input('message')) {
        $sent = notify(null, STORE_EMAIL, 'contact', 'Contact form: ' . input('name'), input('message') . "\n\nFrom: " . input('email'));
        flash($sent ? 'success' : 'info', $sent ? 'Thanks! We got your message and will reply soon.' : 'Your message was saved, but email delivery is currently unavailable.');
        redirect(url('contact'));
    }
    flash('error', 'Please fill in your name, a valid email and a message.');
}
page_header('Contact', 'contact');
?>
<div class="page-title"><h1>Contact us</h1></div>
<div class="two-col">
  <div class="card pad"><h3>Visit or call</h3>
    <p class="muted"><?= e(STORE_ADDRESS) ?><br><?= e(STORE_PHONE) ?><br><?= e(STORE_EMAIL) ?></p>
    <p><strong>Hours</strong><br><span class="muted"><?= e(STORE_HOURS) ?></span></p></div>
  <form method="post" class="card pad stack" data-validate novalidate>
    <?= csrf_field() ?>
    <label>Your name<input name="name" required value="<?= old('name') ?>"></label>
    <label>Email<input type="email" name="email" required value="<?= old('email') ?>"></label>
    <label>Message<textarea name="message" rows="4" required><?= old('message') ?></textarea></label>
    <button class="btn">Send message</button>
  </form>
</div>
<?php page_footer();
