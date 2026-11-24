<?php
// Command-line only; never exposes credentials or sends mail from a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/config.php';
require __DIR__ . '/includes/mailer.php';
$to = $argv[1] ?? MAIL_FROM;
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Invalid email address.\n"); exit(1); }
if (!smtp_send($to, 'Pawfee Shop Brevo SMTP test', 'Your SMTP connection is working.')) { fwrite(STDERR, "Email failed. Check the error output above.\n"); exit(1); }
echo "SMTP accepted the message. Check the recipient inbox and spam folder.\n";
