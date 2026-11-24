<?php
if (!defined('PAWFEE_ADMIN_REQUEST')) { http_response_code(403); exit('Forbidden'); }
if (is_post()) {
    csrf_check();
    $do = $_POST['do'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($do === 'save') {
        $name = input('name');
        if ($name === '') flash('error', 'Category name is required.');
        else {
            $slug = slugify(input('slug') ?: $name);
            $dup  = fetch_val('SELECT COUNT(*) FROM categories WHERE slug=? AND id<>?', [$slug, $id]);
            if ($dup) flash('error', 'That slug is already used.');
            elseif ($id) { q('UPDATE categories SET name=?, slug=?, description=?, sort_order=? WHERE id=?', [$name, $slug, input('description'), (int)input('sort_order'), $id]); flash('success', 'Category updated.'); }
            else { q('INSERT INTO categories (name,slug,description,sort_order) VALUES (?,?,?,?)', [$name, $slug, input('description'), (int)input('sort_order')]); flash('success', 'Category created.'); }
        }
    } elseif ($do === 'archive' || $do === 'restore') {
        q('UPDATE categories SET status=? WHERE id=?', [$do === 'archive' ? 'archived' : 'active', $id]);
        flash('success', 'Category ' . ($do === 'archive' ? 'archived' : 'restored') . '.');
    } elseif ($do === 'assign') {
        foreach ((array)($_POST['product_ids'] ?? []) as $pid) q('UPDATE products SET category_id=? WHERE id=?', [$id, (int)$pid]);
        flash('success', 'Products assigned.');
    }
    redirect(url('admin', ['section' => 'categories']));
}
$cats = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) total FROM categories c ORDER BY sort_order, id');
$edit = isset($_GET['edit']) ? fetch_one('SELECT * FROM categories WHERE id=?', [(int)$_GET['edit']]) : null;
$products = fetch_all('SELECT id,name FROM products ORDER BY name');
if (isset($_GET['edit']) && !$edit) { http_response_code(404); exit(render_error(404, 'Category not found.')); }
$editing = isset($_GET['new']) || $edit;
admin_header($editing ? ($edit ? 'Edit category' : 'Add category') : 'Categories', 'categories');
?>
<?php if (!$editing): ?>
<?php admin_stats(['Total categories'=>count($cats),'Active'=>count(array_filter($cats,fn($c)=>$c['status']==='active')),'Archived'=>count(array_filter($cats,fn($c)=>$c['status']==='archived')),'Products'=>array_sum(array_column($cats,'total'))]); ?>
<div class="row-between"><span class="muted">Catalog collections</span><a class="btn" href="<?= url('admin',['section'=>'categories','new'=>1]) ?>">+ Add category</a></div>

  <section class="card pad"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Slug</th><th>Items</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($cats as $c): ?><tr><td><?= e($c['name']) ?></td><td class="muted"><?= e($c['slug']) ?></td><td><?= (int)$c['total'] ?></td><td><?= status_badge(ucfirst($c['status'])) ?></td>
      <td class="actions"><a class="btn btn-sm btn-ghost" href="<?= url('admin', ['section' => 'categories', 'edit' => $c['id']]) ?>">Edit</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-ghost" name="do" value="<?= $c['status'] === 'active' ? 'archive' : 'restore' ?>"><?= $c['status'] === 'active' ? 'Archive' : 'Restore' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
<a class="link" href="<?= url('admin',['section'=>'categories']) ?>">← Back to categories</a>
<div class="grid-2 wide-left"><div class="stack">
    <form method="post" class="card pad stack" data-validate novalidate><?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <h3><?= $edit ? 'Edit category' : 'New category' ?></h3>
      <label>Name<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
      <label>Slug <small class="muted">(auto if blank — also the image file name)</small><input name="slug" value="<?= e($edit['slug'] ?? '') ?>"></label>
      <label>Description<input name="description" value="<?= e($edit['description'] ?? '') ?>"></label>
      <label>Sort order<input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></label>
      <button class="btn">Save category</button></form>
    <?php if ($edit): ?><form method="post" class="card pad stack"><?= csrf_field() ?><input type="hidden" name="do" value="assign"><h3>Assign products</h3>
      <label>Move to category<select name="id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= selected($edit['id'], $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
      <label>Products<select name="product_ids[]" multiple size="6"><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-ghost">Assign</button></form><?php endif; ?>
  </div>
  <section class="card pad stack"><h3>Visibility</h3><p class="muted">Active categories appear in the shop. Archive a category to hide it from browsing.</p><?php if ($edit): ?><p><?= status_badge(ucfirst($edit['status'])) ?></p><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><button class="btn btn-ghost" name="do" value="<?= $edit['status']==='active'?'archive':'restore' ?>"><?= $edit['status']==='active'?'Archive category':'Restore category' ?></button></form><?php endif; ?><hr><h3>A well-organized shop</h3><p class="muted">Keep category names short, recognizable and consistent. Assign existing products without deleting them.</p></section>
</div><?php endif; ?>
<?php admin_footer();
