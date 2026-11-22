<?php
/** Centered auth card wrapper helpers (used by login/register/verify/forgot/reset). */
function auth_open(string $title, string $sub = ''): void
{
    page_header($title); ?>
    <div class="auth-wrap"><div class="auth-art"><img src="<?= hero_url() ?>" alt=""></div>
    <div class="auth-card"><h1><?= e($title) ?></h1><?= $sub ? '<p class="muted center">' . e($sub) . '</p>' : '' ?>
<?php }
function auth_close(): void { echo '</div></div>'; page_footer(); }

function send_verification_code(array $u): string
{
    $code = (string)random_int(100000, 999999);
    q('DELETE FROM email_verifications WHERE user_id=?', [$u['id']]);
    q('INSERT INTO email_verifications (user_id,code_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 15 MINUTE))', [$u['id'], password_hash($code, PASSWORD_DEFAULT)]);
    notify((int)$u['id'], $u['email'], 'verification', 'Your Pawfee verification code', "Your 6-digit code is $code. It expires in 15 minutes.");
    return $code;
}
