<?php
// Local mail preview (development only).
if (!APP_DEBUG) { http_response_code(404); exit; }
$mails = fetch_all('SELECT * FROM notifications ORDER BY id DESC LIMIT 100');
page_header('Mail preview'); ?>
<div class="page-title"><h1>Mail preview</h1><p class="muted">Emails are stored here while MAIL_DRIVER is "mock".</p></div>
<?php foreach ($mails as $m): ?>
  <article class="card pad"><div class="row-between"><strong><?= e($m['subject']) ?></strong><span class="muted small"><?= e($m['created_at']) ?></span></div>
  <p class="muted small">To: <?= e($m['recipient_email']) ?> · <?= e($m['type']) ?></p><pre><?= e($m['body']) ?></pre></article>
<?php endforeach; if (!$mails) echo '<div class="empty">No emails yet.</div>';
page_footer();
