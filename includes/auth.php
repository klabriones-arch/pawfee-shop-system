<?php
/** Authentication + role-based access control. */

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['uid'])) return $cache = null;
    $u = fetch_one('SELECT * FROM users WHERE id=? AND status="active"', [$_SESSION['uid']]);
    if ($u) {
        $u['roles'] = array_column(fetch_all(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?', [$u['id']]), 'name');
    }
    return $cache = $u;
}
function has_role(string ...$roles): bool
{
    $u = current_user();
    return $u && array_intersect($roles, $u['roles']);
}
function require_login(): array
{
    $u = current_user();
    if (!$u) { flash('error', 'Please sign in to continue.'); redirect(url('login', ['next' => $_GET['page'] ?? 'home'])); }
    return $u;
}
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!array_intersect($roles, $u['roles'])) { http_response_code(403); exit(render_error(403, 'You do not have access to this page.')); }
    return $u;
}
function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
    q('UPDATE users SET last_login=NOW() WHERE id=?', [$u['id']]);
}
function home_for(array $u): string
{
    if (in_array('admin', $u['roles'], true) || in_array('staff', $u['roles'], true)) return url('admin');
    if (in_array('courier', $u['roles'], true)) return url('courier');
    return url('account');
}
function unique_username(string $email): string
{
    $base = preg_replace('/[^a-z0-9]/', '', strtolower(strstr($email, '@', true))) ?: 'user';
    $name = $base; $i = 1;
    while (fetch_val('SELECT COUNT(*) FROM users WHERE username=?', [$name])) $name = $base . (++$i);
    return $name;
}
function validate_password(string $pw): ?string
{
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw))
        return 'Use 8+ characters, including a letter and a number.';
    return null;
}
function render_error(int $code, string $msg): string
{
    return "<h1 style='font-family:sans-serif'>$code</h1><p style='font-family:sans-serif'>" . e($msg) . "</p>";
}
