<?php
if (!defined('PAWFEE_ADMIN_REQUEST')) { http_response_code(403); exit('Forbidden'); }
$cats = fetch_all('SELECT id,name FROM categories ORDER BY sort_order');

/** "Size|Large · 16 oz|30|0" per line  ->  variant rows */
function parse_variants(string $text): array
{
    $rows = []; $n = 0;
    foreach (preg_split('/\R/', trim($text)) as $line) {
        $p = array_map('trim', explode('|', $line));
        if (count($p) < 2 || $p[0] === '' || $p[1] === '') continue;
        $rows[] = [$p[0], $p[1], (float)($p[2] ?? 0), !empty($p[3]) ? 1 : 0, ++$n];
    }
    return $rows;
}

if (is_post()) {
    csrf_check();
    $do = $_POST['do'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $uid = (int)current_user()['id'];

    if ($do === 'archive' || $do === 'restore') {
        q('UPDATE products SET status=? WHERE id=?', [$do === 'archive' ? 'archived' : 'active', $id]);
        flash('success', $do === 'archive' ? 'Product archived (hidden from the shop).' : 'Product restored.');
        redirect(url('admin', ['section' => 'products']));
    }
    if ($do === 'del_image') {
        $img = fetch_one('SELECT * FROM product_images WHERE id=?', [(int)$_POST['image_id']]);
        if ($img) { @unlink(__DIR__ . '/../' . $img['path']); q('DELETE FROM product_images WHERE id=?', [$img['id']]); }
        redirect(url('admin', ['section' => 'products', 'edit' => $id]));
    }
    if ($do === 'save') {
        $name = input('name'); $price = (float)input('price'); $stock = (int)input('stock');
        $slug = slugify(input('slug') ?: $name);
        $sku  = strtoupper(input('sku'));
        $err  = [];
        if ($name === '') $err[] = 'Name is required.';
        if ($sku === '') $err[] = 'SKU is required.';
        if ($price <= 0) $err[] = 'Price must be greater than zero.';
        if ($stock < 0) $err[] = 'Stock cannot be negative.';
        if (fetch_val('SELECT COUNT(*) FROM products WHERE (slug=? OR sku=?) AND id<>?', [$slug, $sku, $id])) $err[] = 'Slug or SKU already exists.';
        if ($err) { foreach ($err as $m) flash('error', $m); redirect(url('admin', ['section' => 'products'] + ($id ? ['edit' => $id] : ['new' => 1]))); }

        $vals = [(int)input('category_id'), $name, $slug, input('tagline'), input('description'), $sku, input('type'), input('badge') ?: null, $price,
                 input('cost') !== '' ? (float)input('cost') : null, max(1, (int)input('low_stock_threshold')), empty($_POST['featured']) ? 0 : 1, input('status') === 'archived' ? 'archived' : 'active'];
        $pdo = db(); $pdo->beginTransaction();
        try {
            if ($id) {
                q('UPDATE products SET category_id=?,name=?,slug=?,tagline=?,description=?,sku=?,type=?,badge=?,price=?,cost=?,low_stock_threshold=?,featured=?,status=? WHERE id=?', array_merge($vals, [$id]));
                $cur = (int)fetch_val('SELECT stock FROM products WHERE id=?', [$id]);
                if ($cur !== $stock) adjust_stock($id, $stock - $cur, 'Manual edit on product form', $uid);
            } else {
                q('INSERT INTO products (category_id,name,slug,tagline,description,sku,type,badge,price,cost,low_stock_threshold,featured,status,stock) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)', $vals);
                $id = (int)$pdo->lastInsertId();
                if ($stock > 0) adjust_stock($id, $stock, 'Opening stock', $uid);
            }
            q('DELETE FROM product_variants WHERE product_id=?', [$id]);
            foreach (parse_variants($_POST['variants'] ?? '') as $v) q('INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order) VALUES (?,?,?,?,?,?)', array_merge([$id], $v));
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); flash('error', $e->getMessage()); redirect(url('admin', ['section' => 'products'])); }

        // Image uploads (multiple)
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $k => $_) {
                $f = ['name' => $_FILES['images']['name'][$k], 'tmp_name' => $_FILES['images']['tmp_name'][$k], 'size' => $_FILES['images']['size'][$k], 'error' => $_FILES['images']['error'][$k]];
                if ($path = upload_image($f, $slug)) {
                    $first = !fetch_val('SELECT COUNT(*) FROM product_images WHERE product_id=?', [$id]);
                    q('INSERT INTO product_images (product_id,path,is_primary) VALUES (?,?,?)', [$id, $path, $first ? 1 : 0]);
                }
            }
        }
        flash('success', 'Product saved.');
        redirect(url('admin', ['section' => 'products']));
    }
}

$edit = null; $variantText = '';
if (isset($_GET['edit']) || isset($_GET['new'])) {
    $edit = isset($_GET['edit']) ? fetch_one('SELECT * FROM products WHERE id=?', [(int)$_GET['edit']]) : ['id' => 0, 'category_id' => $cats[0]['id'] ?? 0, 'name' => '', 'slug' => '', 'tagline' => '', 'description' => '', 'sku' => '', 'type' => 'food', 'badge' => '', 'price' => '', 'cost' => '', 'stock' => 0, 'low_stock_threshold' => LOW_STOCK_DEFAULT, 'featured' => 0, 'status' => 'active'];
    if ($edit && $edit['id']) foreach (fetch_all('SELECT * FROM product_variants WHERE product_id=? ORDER BY id', [$edit['id']]) as $v)
        $variantText .= "{$v['group_name']}|{$v['label']}|" . (float)$v['price_delta'] . '|' . $v['is_default'] . "\n";
}

admin_header($edit ? ($edit['id'] ? 'Edit product' : 'Add product') : 'Products', 'products');
if ($edit): $images = $edit['id'] ? fetch_all('SELECT * FROM product_images WHERE product_id=?', [$edit['id']]) : []; ?>
<a class="link" href="<?= url('admin',['section'=>'products']) ?>">← Back to products</a>
<form method="post" enctype="multipart/form-data" class="admin-product-form" data-validate novalidate>
  <?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <div class="stack"><section class="card pad stack"><h3>Product details</h3><div class="row"><label>Product name<input name="name" required value="<?= e($edit['name']) ?>"></label><label>Slug <small class="muted">(image file name too)</small><input name="slug" value="<?= e($edit['slug']) ?>"></label></div>
  <label>Tagline<input name="tagline" value="<?= e($edit['tagline']) ?>"></label>
  <label>Description<textarea name="description" rows="3"><?= e($edit['description']) ?></textarea></label>
  <div class="row"><label>Category<select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= selected($edit['category_id'], $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
    <label>Type<select name="type"><?php foreach (['drink', 'food', 'merch'] as $t): ?><option <?= selected($edit['type'], $t) ?>><?= $t ?></option><?php endforeach; ?></select></label>
    <label>Badge<input name="badge" value="<?= e($edit['badge']) ?>" placeholder="Bestseller"></label></div>
  </section><section class="card pad stack"><h3>Pricing and inventory</h3><div class="row"><label>SKU<input name="sku" required value="<?= e($edit['sku']) ?>"></label><label>Price<input type="number" step="0.01" min="0" name="price" required value="<?= e($edit['price']) ?>"></label>
    <label>Cost (optional)<input type="number" step="0.01" min="0" name="cost" value="<?= e($edit['cost']) ?>"></label></div>
  <div class="row"><label>Stock<input type="number" min="0" name="stock" value="<?= (int)$edit['stock'] ?>"></label><label>Low-stock threshold<input type="number" min="1" name="low_stock_threshold" value="<?= (int)$edit['low_stock_threshold'] ?>"></label>
    <label>Status<select name="status"><option value="active" <?= selected($edit['status'], 'active') ?>>Active</option><option value="archived" <?= selected($edit['status'], 'archived') ?>>Archived</option></select></label></div>
  <label class="check"><input type="checkbox" name="featured" value="1" <?= $edit['featured'] ? 'checked' : '' ?>> Featured on homepage</label>
  </section><section class="card pad stack"><h3>Product variants</h3><p class="muted small">Options use a price adjustment to the base product price; stock is shared across options.</p><label>Variants <small class="muted">One per line: <code>Group|Label|Price change|Default(1/0)</code></small>
    <textarea name="variants" rows="6" placeholder="Size|Large · 16 oz|30|0"><?= e($variantText) ?></textarea></label>
  </section></div><div class="stack"><section class="card pad stack"><h3>Product images</h3><label>Upload images (JPG/PNG/WEBP, max 2MB each)<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
  <?php if ($images): ?><div class="thumb-row"><?php foreach ($images as $im): ?><div><img src="<?= BASE_URL . '/' . e($im['path']) ?>" alt="">
    <button class="btn btn-sm btn-danger" form="del<?= (int)$im['id'] ?>">Remove</button></div><?php endforeach; ?></div><?php endif; ?>
  </section><section class="card pad stack"><h3>Publishing</h3><p class="muted small">Active products are visible in the shop. Archived products are hidden. Save your changes to update the catalog.</p><div class="row"><button class="btn">Save product</button><a class="btn btn-ghost" href="<?= url('admin', ['section' => 'products']) ?>">Cancel</a></div></section></div>
</form>
<?php foreach ($images as $im): ?><form id="del<?= (int)$im['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="do" value="del_image"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>"></form><?php endforeach; ?>

<?php else:
    $s = trim($_GET['q'] ?? ''); $filter = $_GET['status'] ?? ''; $category=(int)($_GET['category'] ?? 0);
    $w=['1=1']; $params=[];
    if($s!==''){ $w[]='(p.name LIKE ? OR p.sku LIKE ?)'; array_push($params,"%$s%","%$s%"); }
    if(in_array($filter,['active','archived'],true)){ $w[]='p.status=?'; $params[]=$filter; }
    if($category){ $w[]='p.category_id=?'; $params[]=$category; }
    $rows=fetch_all(product_select_sql().' WHERE '.implode(' AND ',$w).' ORDER BY p.status,p.name',$params);
    admin_stats(['Total products'=>fetch_val('SELECT COUNT(*) FROM products'),'Active products'=>fetch_val("SELECT COUNT(*) FROM products WHERE status='active'"),'Archived'=>fetch_val("SELECT COUNT(*) FROM products WHERE status='archived'"),'Low stock'=>fetch_val("SELECT COUNT(*) FROM products WHERE status='active' AND stock<=low_stock_threshold")]); ?>
  <div class="row-between"><form class="row"><input type="hidden" name="page" value="admin"><input type="hidden" name="section" value="products"><input type="search" name="q" value="<?= e($s) ?>" placeholder="Search name or SKU"><select name="status"><option value="">All statuses</option><?php foreach(['active','archived'] as $st): ?><option value="<?= $st ?>" <?= selected($filter,$st) ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select><select name="category"><option value="0">All categories</option><?php foreach($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= selected($category,$c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-ghost">Filter</button></form>
    <a class="btn" href="<?= url('admin', ['section' => 'products', 'new' => 1]) ?>">+ Add product</a></div>
  <div class="table-wrap card"><table class="table"><thead><tr><th></th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $p): ?><tr><td><img class="mini" src="<?= e(product_image($p)) ?>" alt=""></td><td><strong><?= e($p['name']) ?></strong></td><td class="muted"><?= e($p['sku']) ?></td><td><?= e($p['category_name']) ?></td><td><?= money($p['price']) ?></td>
      <td class="<?= $p['stock'] <= $p['low_stock_threshold'] ? 'warn' : '' ?>"><?= (int)$p['stock'] ?></td><td><?= status_badge(ucfirst($p['status'])) ?></td>
      <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= url('admin', ['section' => 'products', 'edit' => $p['id']]) ?>">Edit</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-ghost" name="do" value="<?= $p['status'] === 'active' ? 'archive' : 'restore' ?>"><?= $p['status'] === 'active' ? 'Archive' : 'Restore' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; admin_footer();
