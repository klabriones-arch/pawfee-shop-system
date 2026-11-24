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
define('BASE_URL', '/pawfee');

define('APP_DEBUG', true);          // true = show dev helpers (mail preview, verification codes)
define('CURRENCY', '₱');
define('DELIVERY_FEE', 50.00);
define('LOW_STOCK_DEFAULT', 5);
define('ITEMS_PER_PAGE', 6);

// Mail: "mock" stores emails in the database (view at ?page=mail). Switch to "smtp" later.
define('MAIL_DRIVER', 'mock');
define('MAIL_FROM', 'hello@pawfee.shop');
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');

// Store info (shown on Contact / footer)
define('STORE_ADDRESS', 'B6 L7 PH8, Barangay 9, Biringan City, Philippines');
define('STORE_PHONE', '+63 993 184 2108');
define('STORE_EMAIL', 'pawfeeshop@gmail.com');
define('STORE_HOURS', '9AM – 9PM, Monday – Saturday');
