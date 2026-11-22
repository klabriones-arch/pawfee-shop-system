<?php
$search   = trim($_GET['q'] ?? '');
$cat      = $_GET['category'] ?? '';
$type     = in_array($_GET['type'] ?? '', ['drink', 'food', 'merch'], true) ? $_GET['type'] : '';
$avail    = ($_GET['avail'] ?? '') === '1';
$min      = ($_GET['min'] ?? '') !== '' ? (float)$_GET['min'] : null;
$max      = ($_GET['max'] ?? '') !== '' ? (float)$_GET['max'] : null;
$sort     = $_GET['sort'] ?? 'popular';
$orders   = ['popular' => 'sold DESC, p.id', 'newest' => 'p.created_at DESC, p.id DESC', 'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC'];

$where = ['p.status="active"', 'c.status="active"']; $args = [];
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.tagline LIKE ?)'; $args[] = "%$search%"; $args[] = "%$search%"; }
if ($cat !== '')    { $where[] = 'c.slug=?'; $args[] = $cat; }
if ($type !== '')   { $where[] = 'p.type=?'; $args[] = $type; }
if ($avail)         { $where[] = 'p.stock>0'; }
if ($min !== null)  { $where[] = 'p.price>=?'; $args[] = $min; }
if ($max !== null)  { $where[] = 'p.price<=?'; $args[] = $max; }
$w = implode(' AND ', $where);

$total = (int)fetch_val("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE $w", $args);
$pg    = paginate($total, ITEMS_PER_PAGE, (int)($_GET['p'] ?? 1));
$products = fetch_all(product_select_sql() . " WHERE $w ORDER BY " . ($orders[$sort] ?? $orders['popular']) . " LIMIT {$pg['per']} OFFSET {$pg['offset']}", $args);
$cats = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.status="active") AS total FROM categories c WHERE c.status="active" ORDER BY sort_order');
$all  = array_sum(array_column($cats, 'total'));
$keep = array_filter(['q' => $search, 'category' => $cat, 'type' => $type, 'avail' => $avail ? 1 : '', 'min' => $min, 'max' => $max, 'sort' => $sort !== 'popular' ? $sort : ''], fn($v) => $v !== '' && $v !== null);

page_header('Shop', 'shop');
?>
<section class="banner">
  <div><span class="eyebrow">Fresh brews. Happy paws.</span><h1>A little something for every craving.</h1>
  <p class="muted">Find your favorite sips, fresh bakes, and pawsitively delicious bites.</p></div>
  <p class="banner-note">Made with love.<br>Served with a wag.</p>
</section>

<form class="toolbar" method="get" action="<?= BASE_URL ?>/index.php">
  <input type="hidden" name="page" value="shop">
  <?php foreach (['category' => $cat, 'type' => $type, 'min' => $min, 'max' => $max] as $k => $v) if ($v !== '' && $v !== null) echo '<input type="hidden" name="' . $k . '" value="' . e($v) . '">'; ?>
  <div class="searchbox"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search coffee, matcha, treats…"><button class="btn btn-sm">Search</button></div>
  <label class="inline">Sort by
    <select name="sort" data-autosubmit>
      <?php foreach (['popular' => 'Most popular', 'newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= selected($sort, $k) ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</form>

<div class="shop-layout">
  <aside class="filters">
    <div class="row-between"><h3>Filters</h3><a class="link small" href="<?= url('shop') ?>">Reset all</a></div>
    <h4>Category</h4>
    <ul class="filter-list">
      <li><a class="<?= $cat === '' ? 'active' : '' ?>" href="<?= url('shop', array_diff_key($keep, ['category' => 1])) ?>">All products <span><?= $all ?></span></a></li>
      <?php foreach ($cats as $c): ?>
        <li><a class="<?= $cat === $c['slug'] ? 'active' : '' ?>" href="<?= url('shop', ['category' => $c['slug']] + $keep) ?>"><?= e($c['name']) ?> <span><?= (int)$c['total'] ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <h4>Type</h4>
    <ul class="filter-list">
      <?php foreach (['' => 'All types', 'drink' => 'Drinks', 'food' => 'Food', 'merch' => 'Merch'] as $k => $l): ?>
        <li><a class="<?= $type === $k ? 'active' : '' ?>" href="<?= url('shop', array_diff_key($keep, ['type' => 1]) + ($k ? ['type' => $k] : [])) ?>"><?= $l ?></a></li>
      <?php endforeach; ?>
    </ul>
    <form method="get" action="<?= BASE_URL ?>/index.php" class="stack">
      <input type="hidden" name="page" value="shop">
      <?php foreach (['q' => $search, 'category' => $cat, 'type' => $type] as $k => $v) if ($v !== '') echo '<input type="hidden" name="' . $k . '" value="' . e($v) . '">'; ?>
      <h4>Price</h4>
      <div class="row"><input type="number" name="min" min="0" placeholder="Min" value="<?= e($min ?? '') ?>"><input type="number" name="max" min="0" placeholder="Max" value="<?= e($max ?? '') ?>"></div>
      <label class="check"><input type="checkbox" name="avail" value="1" <?= $avail ? 'checked' : '' ?>> In stock only</label>
      <button class="btn btn-sm btn-ghost">Apply</button>
    </form>
  </aside>

  <section>
    <div class="row-between"><h2>All products</h2><span class="muted small">Showing <?= $total ? $pg['offset'] + 1 : 0 ?>–<?= min($pg['offset'] + $pg['per'], $total) ?> of <?= $total ?> products</span></div>
    <?php if (!$products): ?>
      <div class="empty"><p>No products match your filters yet.</p><a class="btn" href="<?= url('shop') ?>">Clear filters</a></div>
    <?php else: ?>
      <div class="product-grid cols-3"><?php foreach ($products as $p) product_card($p); ?></div>
      <?= pager($pg, 'shop', $keep) ?>
    <?php endif; ?>
  </section>
</div>
<?php page_footer();
