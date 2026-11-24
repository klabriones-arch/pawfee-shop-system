<?php
/**
 * Pawfee Shop - Configuration
 * Edit these values to match your XAMPP setup.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'pawfee_shop');
define('DB_USER', 'root');
define('DB_PASS', '');

// Folder name inside C:\xampp\htdocs  ->  http://localhost/pawfee
define('BASE_URL', PHP_SAPI === 'cli' ? '/pawfee' : rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/pawfee/index.php')), '/.'));

define('APP_DEBUG', false);
define('CURRENCY', '₱');
define('DELIVERY_FEE', 50.00);
define('LOW_STOCK_DEFAULT', 5);
define('ITEMS_PER_PAGE', 6);

// Private SMTP credentials (never commit this file).
$localMail = is_file(__DIR__ . '/includes/mail.local.php')
    ? require __DIR__ . '/includes/mail.local.php' : [];
define('MAIL_DRIVER', 'smtp');
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_USER', $localMail['username'] ?? '');
define('SMTP_PASS', $localMail['password'] ?? '');
define('MAIL_FROM', $localMail['from_email'] ?? 'pawfeeshop123@gmail.com');
define('MAIL_FROM_NAME', $localMail['from_name'] ?? 'Pawfee Shop');
// Trusted full site URL, without trailing slash. Used in password reset links.
define('APP_URL', $localMail['app_url'] ?? 'http://localhost' . BASE_URL);
unset($localMail);

// Store info (shown on Contact / footer)
define('STORE_ADDRESS', 'B6 L7 PH8, Barangay 9, Biringan City, Philippines');
define('STORE_PHONE', '+63 993 184 2108');
define('STORE_EMAIL', 'pawfeeshop123@gmail.com');
define('STORE_HOURS', '9AM – 9PM, Monday – Saturday');
