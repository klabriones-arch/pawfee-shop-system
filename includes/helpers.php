<?php
/** General helpers: escaping, URLs, flash messages, CSRF, images, pagination. */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return CURRENCY . number_format((float)$n, 2); }

function url(string $page = 'home', array $params = []): string
{
    if (defined('ADMIN_SESSION') && ADMIN_SESSION && in_array($page, ['login','logout','verify'], true)) $params += ['workspace'=>'admin'];
    $params = ['page' => $page] + $params;
    return BASE_URL . '/index.php?' . http_build_query($params);
}
function asset(string $path): string { return BASE_URL . '/assets/' . ltrim($path, '/'); }

function redirect(string $to): void { header('Location: ' . $to); exit; }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void
{
    if (empty($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(419);
        exit('Session expired. Please go back and try again.');
    }
}
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function old(string $k, $default = ''): string { return e($_POST[$k] ?? $default); }
function input(string $k): string { return trim((string)($_POST[$k] ?? '')); }

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s ?: 'item';
}
function selected($a, $b): string { return (string)$a === (string)$b ? 'selected' : ''; }

/* ---------- Images (easy to replace) ----------
 * Drop files into assets/images/products/<slug>.jpg  (also .png/.webp)
 * Extra gallery images: <slug>-2.jpg, <slug>-3.jpg, <slug>-4.jpg
 * Category images:      assets/images/categories/<slug>.jpg
 * Logo:                 assets/images/logo.png  (falls back to logo.svg)
 * Hero image:           assets/images/hero.jpg
 * Anything missing falls back to assets/images/placeholder.svg
 */
function find_image(string $dir, string $name): ?string
{
    foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $ext) {
        $rel = "images/$dir/$name.$ext";
        if (is_file(__DIR__ . '/../assets/' . $rel)) return asset($rel);
    }
    return null;
}
function placeholder(): string { return asset('images/placeholder.svg'); }

function logo_url(): string
{
    foreach (['logo.png', 'logo.jpg', 'logo.svg'] as $f)
        if (is_file(__DIR__ . '/../assets/images/' . $f)) return asset('images/' . $f);
    return placeholder();
}
function hero_url(): string
{
    foreach (['hero.jpg', 'hero.png', 'hero.webp'] as $f)
        if (is_file(__DIR__ . '/../assets/images/' . $f)) return asset('images/' . $f);
    return asset('images/hero-placeholder.svg');
}
/** Main image: uploaded file (DB) > convention file > placeholder. */
function product_image(array $p): string
{
    if (!empty($p['image']) && is_file(__DIR__ . '/../' . $p['image'])) return BASE_URL . '/' . $p['image'];
    return find_image('products', $p['slug']) ?? placeholder();
}
function product_gallery(array $p): array
{
    $out = [];
    foreach (fetch_all('SELECT path FROM product_images WHERE product_id=? ORDER BY is_primary DESC, id', [$p['id']]) as $r)
        if (is_file(__DIR__ . '/../' . $r['path'])) $out[] = BASE_URL . '/' . $r['path'];
    if (!$out && ($m = find_image('products', $p['slug']))) $out[] = $m;
    foreach ([2, 3, 4] as $n)
        if ($x = find_image('products', $p['slug'] . "-$n")) $out[] = $x;
    return $out ?: [placeholder()];
}
function category_image(array $c): string { return find_image('categories', $c['slug']) ?? placeholder(); }

/** Safe image upload. Returns relative path or null. */
function upload_image(array $file, string $slug): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 2 * 1024 * 1024) { flash('error', 'Image must be 2MB or smaller.'); return null; }
    $info = @getimagesize($file['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) { flash('error', 'Only JPG, PNG or WEBP images are allowed.'); return null; }
    $name = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/products/' . $name;
    return move_uploaded_file($file['tmp_name'], $dest) ? 'uploads/products/' . $name : null;
}

/* ---------- Pagination ---------- */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}
function pager(array $pg, string $page, array $params = []): string
{
    if ($pg['pages'] <= 1) return '';
    $link = fn($n, $label, $cls = '') => '<a class="page-link ' . $cls . '" href="' . e(url($page, $params + ['p' => $n])) . '">' . $label . '</a>';
    $h = '<nav class="pager">';
    if ($pg['page'] > 1) $h .= $link($pg['page'] - 1, '← Previous');
    for ($i = 1; $i <= $pg['pages']; $i++) $h .= $link($i, $i, $i === $pg['page'] ? 'active' : '');
    if ($pg['page'] < $pg['pages']) $h .= $link($pg['page'] + 1, 'Next →');
    return $h . '</nav>';
}

function status_badge(string $s): string
{
    return '<span class="badge st-' . slugify($s) . '">' . e($s) . '</span>';
}
function bar_chart(array $rows, string $label, string $value, bool $money = false): string
{
    if (!$rows) return '<p class="muted">No data yet.</p>';
    $max = max(array_map(fn($r) => (float)$r[$value], $rows)) ?: 1;
    $h = '<div class="bars">';
    foreach ($rows as $r) {
        $pct = round($r[$value] / $max * 100);
        $v   = $money ? money($r[$value]) : $r[$value];
        $h  .= '<div class="bar-row"><span class="bar-label">' . e($r[$label]) . '</span>'
             . '<span class="bar-track"><span class="bar-fill" style="width:' . $pct . '%"></span></span>'
             . '<span class="bar-val">' . e($v) . '</span></div>';
    }
    return $h . '</div>';
}
