<?php
$p = fetch_one(product_select_sql() . ' WHERE p.slug=? AND p.status="active" AND c.status="active"', [$_GET['slug'] ?? '']);
if (!$p) { http_response_code(404); page_header('Not found'); echo '<div class="empty"><h1>Product not found</h1><a class="btn" href="' . url('shop') . '">Back to shop</a></div>'; page_footer(); return; }
$groups  = variant_groups((int)$p['id']);
$gallery = product_gallery($p);
[, $defLabel, $defPrice] = resolve_variants($p, []);
page_header($p['name'], 'shop');
?>
<div class="crumbs"><a href="<?= url('home') ?>">Home</a> / <a href="<?= url('shop') ?>">Shop</a> / <a href="<?= url('shop', ['category' => $p['category_slug']]) ?>"><?= e($p['category_name']) ?></a> / <strong><?= e($p['name']) ?></strong>
  <a class="link right" href="<?= url('shop') ?>">← Back to all products</a></div>

<div class="product-detail">
  <div class="gallery">
    <div class="gallery-main"><?php if ($p['badge']): ?><span class="pill"><?= e($p['badge']) ?></span><?php endif; ?>
      <img id="mainImage" src="<?= e($gallery[0]) ?>" alt="<?= e($p['name']) ?>"></div>
    <?php if (count($gallery) > 1): ?>
      <div class="thumbs"><?php foreach ($gallery as $i => $g): ?><button type="button" class="<?= $i ? '' : 'active' ?>" data-thumb="<?= e($g) ?>"><img src="<?= e($g) ?>" alt=""></button><?php endforeach; ?></div>
    <?php endif; ?>
    <p class="note-box"><strong>Brewed fresh. Made just for you.</strong><br><span class="muted small">Your favorite cup, with a little extra love in every sip.</span></p>
  </div>

  <div class="detail-info">
    <span class="eyebrow"><?= e($p['category_name']) ?></span>
    <h1><?= e($p['name']) ?></h1>
    <p class="lead"><?= e($p['tagline']) ?></p>
    <p class="muted"><?= nl2br(e($p['description'])) ?></p>
    <div class="price-row">
      <strong class="big-price" data-price data-base="<?= (float)$p['price'] ?>"><?= money($defPrice) ?></strong>
      <?= $p['stock'] > 0 ? '<span class="badge st-delivered">● ' . ($p['stock'] <= $p['low_stock_threshold'] ? 'Only ' . (int)$p['stock'] . ' left' : 'In stock') . '</span>' : '<span class="badge st-cancelled">Out of stock</span>' ?>
    </div>
    <p class="muted small" data-option-label><?= e($defLabel) ?></p>
    <hr>
    <form method="post" action="<?= BASE_URL ?>/index.php?page=cart&action=add" data-ajax-cart data-product-form>
      <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <?php foreach ($groups as $gname => $options): ?>
        <fieldset class="option-group"><legend><?= e($gname) ?></legend>
          <div class="options">
            <?php foreach ($options as $o): ?>
              <label class="option"><input type="radio" name="variant[<?= e($gname) ?>]" value="<?= (int)$o['id'] ?>" data-delta="<?= (float)$o['price_delta'] ?>" data-label="<?= e($o['label']) ?>" <?= $o['is_default'] ? 'checked' : '' ?>>
                <span><?= e($o['label']) ?><?= $o['price_delta'] != 0 ? ' ' . ($o['price_delta'] > 0 ? '+' : '−') . money(abs($o['price_delta'])) : '' ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <label class="small muted">Quantity</label>
      <div class="buy-row">
        <div class="qty"><button type="button" data-qty="-1">−</button><input type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>" aria-label="Quantity"><button type="button" data-qty="1">+</button></div>
        <button class="btn btn-wide" <?= $p['stock'] > 0 ? '' : 'disabled' ?>>Add to Cart · <span data-total><?= money($defPrice) ?></span></button>
      </div>
    </form>
    <p class="note-box"><strong>Made to order for café pickup.</strong><br><span class="muted small">Contains milk. Choose a plant-based milk to make it your own.</span></p>
  </div>
</div>
<?php page_footer();
