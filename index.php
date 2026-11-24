<?php
/**
 * Pawfee Shop - front controller.
 * Every request goes through here: /index.php?page=shop
 */
declare(strict_types=1);
ob_start();
require __DIR__ . '/config.php';
$requestedPage = $_GET['page'] ?? 'home';
define('ADMIN_SESSION', in_array($requestedPage, ['admin','courier'], true) || (in_array($requestedPage, ['login','logout','verify'], true) && ($_GET['workspace'] ?? '') === 'admin'));
session_name(ADMIN_SESSION ? 'PF_ADMIN' : 'PF_CUSTOMER');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['path' => BASE_URL . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('Cache-Control: no-store, private');
foreach (['db', 'helpers', 'auth', 'mailer', 'cart', 'orders', 'layout', 'customer_flow'] as $f) require __DIR__ . "/includes/$f.php";

$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'home');

$routes = [
    'home' => 'landing', 'menu' => 'home', 'shop' => 'shop', 'product' => 'product', 'cart' => 'cart', 'checkout' => 'checkout',
    'mockpay' => 'mockpay', 'login' => 'login', 'register' => 'register', 'verify' => 'verify',
    'forgot' => 'forgot', 'reset' => 'reset', 'logout' => 'logout', 'account' => 'account', 'order' => 'order', 'orders' => 'account', 'returns' => 'account', 'confirmation' => 'confirmation',
    'about' => 'about', 'contact' => 'contact', 'categories' => 'categories', 'mail' => 'mail',
];

if ($page === 'admin') {
    define('PAWFEE_ADMIN_REQUEST', true);
    // Role map per admin section
    $access = [
        'dashboard' => ['admin', 'staff'], 'fulfillment' => ['admin', 'staff'], 'orders' => ['admin', 'staff'], 'inventory' => ['admin', 'staff'],
        'products' => ['admin'], 'categories' => ['admin'], 'customers' => ['admin'], 'returns' => ['admin'],
        'reports' => ['admin'], 'staff' => ['admin'], 'settings' => ['admin'],
    ];
    $section = preg_replace('/[^a-z_]/', '', $_GET['section'] ?? 'dashboard');
    if (!isset($access[$section])) { http_response_code(404); exit(render_error(404, 'Page not found.')); }
    require_role(...$access[$section]);
    require __DIR__ . "/admin/$section.php";
} elseif ($page === 'courier') {
    require_role('admin', 'staff');
    redirect(url('admin', ['section' => 'fulfillment']));
} elseif (isset($routes[$page])) {
    require __DIR__ . '/pages/' . $routes[$page] . '.php';
} else {
    http_response_code(404);
    page_header('Not found');
    echo '<div class="empty"><h1>Page not found</h1><p><a class="btn" href="' . url('home') . '">Back home</a></p></div>';
    page_footer();
}
