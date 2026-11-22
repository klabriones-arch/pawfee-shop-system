<?php
$cats = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.status="active") AS total
                   FROM categories c WHERE c.status="active" ORDER BY c.sort_order');
page_header('Categories', 'categories');
?>
<div class="page-title"><h1>Categories</h1><p class="muted">Pick a corner of the menu to explore.</p></div>
<div class="cat-grid big">
  <?php foreach ($cats as $c): ?>
    <a class="cat-card" href="<?= url('shop', ['category' => $c['slug']]) ?>">
      <img src="<?= e(category_image($c)) ?>" alt="">
      <span><strong><?= e($c['name']) ?></strong><small><?= (int)$c['total'] ?> items</small></span>
    </a>
  <?php endforeach; ?>
</div>
<?php page_footer();
