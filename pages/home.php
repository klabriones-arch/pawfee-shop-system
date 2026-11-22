<?php
$cats  = fetch_all('SELECT * FROM categories WHERE status="active" AND id IN (SELECT category_id FROM products WHERE status="active") ORDER BY sort_order LIMIT 5');
$featured = fetch_all(product_select_sql() . ' WHERE p.status="active" AND c.status="active" AND p.featured=1 AND p.stock>0 ORDER BY sold DESC, p.id LIMIT 4');
page_header('Home', 'home');
?>
<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow">For coffee people &amp; pet people</span>
    <h1>Coffee, cuddles &amp; wagging tails.</h1>
    <p>Your favorite brews, playful bites, and a cozy spot for you and your four-legged bestie.</p>
    <a class="btn" href="<?= url('shop') ?>">Explore the menu →</a>
  </div>
  <div class="hero-img"><img src="<?= hero_url() ?>" alt=""></div>
</section>

<section class="section">
  <div class="section-head"><h2>What are you in the mood for?</h2><a class="link" href="<?= url('categories') ?>">Browse all categories →</a></div>
  <div class="cat-grid">
    <?php foreach ($cats as $c): ?>
      <a class="cat-card" href="<?= url('shop', ['category' => $c['slug']]) ?>">
        <img src="<?= e(category_image($c)) ?>" alt="">
        <span><strong><?= e($c['name']) ?></strong><small>Explore →</small></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="section-head"><h2>Human treats. Pawsitively delicious.</h2><a class="link" href="<?= url('shop') ?>">View the full menu →</a></div>
  <div class="product-grid cols-4"><?php foreach ($featured as $p) product_card($p); ?></div>
</section>

<section class="section promo">
  <h2>Your cup, your way.</h2>
  <p>Use code <strong>PAWS10</strong> for 10% off orders over <?= money(200) ?>. Ask us about oat milk and our plant-based options.</p>
</section>
<?php page_footer();
