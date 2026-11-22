<?php
/**
 * Notification / email abstraction.
 * MAIL_DRIVER = mock -> saved in `notifications` (preview at ?page=mail)
 * MAIL_DRIVER = smtp -> plug PHPMailer in smtp_send() below.
 */
function notify(?int $userId, string $to, string $type, string $subject, string $body): void
{
    q('INSERT INTO notifications (user_id,recipient_email,type,subject,body) VALUES (?,?,?,?,?)',
        [$userId, $to, $type, $subject, $body]);
    if (MAIL_DRIVER === 'smtp') smtp_send($to, $subject, $body);
}
function smtp_send(string $to, string $subject, string $body): bool
{
    // TODO: configure PHPMailer using SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASS.
    return @mail($to, $subject, $body, 'From: ' . MAIL_FROM);
}
function notify_staff(string $type, string $subject, string $body): void
{
    foreach (fetch_all('SELECT DISTINCT u.id,u.email FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name="admin"') as $a)
        notify((int)$a['id'], $a['email'], $type, $subject, $body);
}
