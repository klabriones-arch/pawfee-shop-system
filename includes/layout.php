<?php
/** Shared page chrome: storefront header/footer and admin shell. */

function icon(string $name): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'cart'   => '<path d="M3 4h2l2.4 11h10.2L20 7H6.2"/><circle cx="9" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function page_header(string $title, string $active = '', bool $wide = false): void
{
    $u = current_user();
    $nav = [
        'home' => 'Home', 'shop' => 'Shop', 'categories' => 'Categories',
        'account' => 'My Orders', 'about' => 'About us', 'contact' => 'Contact',
    ];
    if (!$u) unset($nav['account']);
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Pawfee Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= url('home') ?>"><img src="<?= logo_url() ?>" alt="Pawfee Shop"></a>
  <button class="nav-toggle" aria-label="Menu" data-nav-toggle>☰</button>
  <nav class="main-nav" data-nav>
    <?php foreach ($nav as $key => $label): ?>
      <a href="<?= $key === 'account' ? url('account', ['tab' => 'orders']) : url($key) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="header-actions">
    <button class="round-btn" aria-label="Search" data-search-toggle><?= icon('search') ?></button>
    <a class="round-btn" href="<?= url('cart') ?>" aria-label="Cart"><?= icon('cart') ?><span class="cart-badge" data-cart-count <?= cart_count() ? '' : 'hidden' ?>><?= cart_count() ?></span></a>
    <?php if ($u): ?>
      <details class="user-menu">
        <summary class="round-btn" aria-label="Account"><?= icon('user') ?></summary>
        <div class="menu">
          <span class="muted small">Hi, <?= e($u['first_name']) ?></span>
          <a href="<?= home_for($u) ?>"><?= has_role('admin', 'staff', 'courier') ? 'Dashboard' : 'My account' ?></a>
          <a href="<?= url('logout') ?>">Sign out</a>
        </div>
      </details>
    <?php else: ?>
      <a class="round-btn" href="<?= url('login') ?>" aria-label="Sign in"><?= icon('user') ?></a>
    <?php endif; ?>
  </div>
  <form class="search-panel" action="<?= BASE_URL ?>/index.php" method="get" hidden data-search-panel>
    <input type="hidden" name="page" value="shop">
    <input type="search" name="q" placeholder="Search coffee, matcha, treats…" aria-label="Search products">
    <button class="btn btn-sm">Search</button>
  </form>
</header>
<main class="<?= $wide ? 'wide' : 'container' ?>">
<?php foreach (flashes() as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach;
}

function page_footer(bool $custom = false): void
{ ?>
</main>
<?php if (!$custom): ?>
<footer class="site-footer">
  <div class="footer-grid">
    <div><strong>Pawfee Shop</strong><p class="muted">A little pause for you and your paws.</p></div>
    <div><strong>Visit us</strong><p class="muted"><?= e(STORE_ADDRESS) ?><br><?= e(STORE_HOURS) ?></p></div>
    <div><strong>Say hello</strong><p class="muted"><?= e(STORE_PHONE) ?><br><?= e(STORE_EMAIL) ?></p></div>
  </div>
  <div class="footer-bar"><span>© <?= date('Y') ?> Pawfee Shop. All rights reserved.</span><span>Good coffee. Great company. All paws welcome.</span></div>
</footer>
<?php endif; ?>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
<?php }

/* ------------------------- Admin shell ------------------------- */
function admin_header(string $title, string $active): void
{
    $u = current_user();
    $menu = [
        'dashboard' => ['Dashboard', ['admin', 'staff']], 'products' => ['Products', ['admin']],
        'categories' => ['Categories', ['admin']], 'inventory' => ['Inventory', ['admin', 'staff']],
        'orders' => ['Orders', ['admin', 'staff']], 'customers' => ['Customers', ['admin']],
        'returns' => ['Returns', ['admin']], 'reports' => ['Reports', ['admin']],
        'staff' => ['Staff', ['admin']], 'settings' => ['Coupons', ['admin']],
    ];
    ?><!DOCTYPE html>
<html lang="en"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Pawfee Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="admin-body">
<aside class="admin-side">
  <a class="brand" href="<?= url('home') ?>"><img src="<?= logo_url() ?>" alt="Pawfee Shop"></a>
  <nav>
    <?php foreach ($menu as $key => [$label, $roles]): if (!array_intersect($roles, $u['roles'])) continue; ?>
      <a href="<?= url('admin', ['section' => $key]) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('home') ?>">← View shop</a>
    <a href="<?= url('logout') ?>">Sign out</a>
  </nav>
</aside>
<main class="admin-main">
  <h1><?= e($title) ?></h1>
  <?php foreach (flashes() as [$type, $msg]): ?><div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div><?php endforeach;
}
function admin_footer(): void { ?>
</main>
<script src="<?= asset('js/app.js') ?>"></script>
</body></html>
<?php }

/** Small reusable product card used on home + shop. */
function product_card(array $p): void
{ ?>
  <article class="card product-card">
    <a class="thumb" href="<?= url('product', ['slug' => $p['slug']]) ?>">
      <?php if ($p['badge']): ?><span class="pill"><?= e($p['badge']) ?></span><?php endif; ?>
      <img src="<?= e(product_image($p)) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    </a>
    <?php if (!empty($p['category_name'])): ?><span class="eyebrow"><?= e($p['category_name']) ?></span><?php endif; ?>
    <h3><a href="<?= url('product', ['slug' => $p['slug']]) ?>"><?= e($p['name']) ?></a></h3>
    <p class="muted small"><?= e($p['tagline']) ?></p>
    <div class="card-foot">
      <strong class="price"><?= money($p['price']) ?></strong>
      <?php if ($p['stock'] > 0): ?>
        <form method="post" action="<?= BASE_URL ?>/index.php?page=cart&action=add" data-ajax-cart>
          <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="qty" value="1">
          <button class="btn btn-sm">Add to order</button>
        </form>
      <?php else: ?><span class="badge st-cancelled">Sold out</span><?php endif; ?>
    </div>
  </article>
<?php }

function product_select_sql(): string
{
    return 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
            (SELECT path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC,id LIMIT 1) AS image,
            COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.product_id=p.id),0) AS sold
            FROM products p JOIN categories c ON c.id=p.category_id';
}
