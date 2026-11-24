<?php
if (!defined('PAWFEE_ADMIN_REQUEST')) { http_response_code(403); exit('Forbidden'); }
if (is_post()) {
    csrf_check();
    $do = $_POST['do'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($do === 'create') {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', input('code'))); $type = input('type') === 'fixed' ? 'fixed' : 'percent'; $val = (float)input('value');
        if ($code === '' || $val <= 0 || ($type === 'percent' && $val > 100)) flash('error', 'Enter a code and a valid value.');
        elseif (fetch_val('SELECT COUNT(*) FROM coupons WHERE code=?', [$code])) flash('error', 'That code already exists.');
        else { q('INSERT INTO coupons (code,type,value,min_purchase,expires_at) VALUES (?,?,?,?,?)', [$code, $type, $val, (float)input('min_purchase'), input('expires_at') ?: null]); flash('success', 'Coupon created.'); }
    } elseif ($do === 'toggle') q('UPDATE coupons SET active = 1 - active WHERE id=?', [$id]);
    elseif ($do === 'delete') q('DELETE FROM coupons WHERE id=?', [$id]);
    redirect(url('admin', ['section' => 'settings']));
}
$coupons = fetch_all('SELECT * FROM coupons ORDER BY id DESC');
admin_header('Coupons & settings', 'settings'); ?>
<div class="grid-2 wide-left">
  <div class="table-wrap card"><table class="table"><thead><tr><th>Code</th><th>Discount</th><th>Min. purchase</th><th>Expires</th><th>Active</th><th></th></tr></thead><tbody>
    <?php foreach ($coupons as $c): ?><tr><td><strong><?= e($c['code']) ?></strong></td><td><?= $c['type'] === 'percent' ? (float)$c['value'] . '%' : money($c['value']) ?></td><td><?= money($c['min_purchase']) ?></td><td><?= e($c['expires_at'] ?: '—') ?></td><td><?= $c['active'] ? 'Yes' : 'No' ?></td>
      <td class="actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-ghost" name="do" value="toggle">Toggle</button></form>
        <form method="post" data-confirm="Delete coupon?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-danger" name="do" value="delete">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div>
  <form method="post" class="card pad stack" data-validate novalidate><?= csrf_field() ?><input type="hidden" name="do" value="create"><h3>New coupon</h3>
    <label>Code<input name="code" required></label><div class="row"><label>Type<select name="type"><option value="percent">Percent</option><option value="fixed">Fixed amount</option></select></label><label>Value<input type="number" step="0.01" name="value" required></label></div>
    <label>Minimum purchase<input type="number" step="0.01" name="min_purchase" value="0"></label><label>Expires<input type="date" name="expires_at"></label><button class="btn">Create coupon</button>
    <p class="muted small">Delivery fee, store info and mail settings live in <code>config.php</code>.</p></form>
</div>
<?php admin_footer();
