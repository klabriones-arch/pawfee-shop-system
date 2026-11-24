<?php
/** Save notifications and deliver them over authenticated SMTP. */
function notify(?int $userId, string $to, string $type, string $subject, string $body): bool
{
    // Never persist live verification codes or reset links in the notification log.
    $loggedBody = (!APP_DEBUG && in_array($type, ['verification', 'password_reset'], true))
        ? '[Sensitive authentication content omitted]' : $body;
    q('INSERT INTO notifications (user_id,recipient_email,type,subject,body) VALUES (?,?,?,?,?)',
        [$userId, $to, $type, $subject, $loggedBody]);
    return MAIL_DRIVER === 'smtp' && smtp_send($to, $subject, $body);
}

function smtp_send(string $to, string $subject, string $body): bool
{
    try {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) throw new RuntimeException('Run composer install in the project folder.');
        if (SMTP_USER === '' || SMTP_PASS === '') throw new RuntimeException('Configure includes/mail.local.php first.');
        require_once $autoload;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = trim(SMTP_PASS);
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        return $mail->send();
    } catch (Throwable $e) {
        error_log('Pawfee SMTP: ' . $e->getMessage());
        return false;
    }
}

function notify_staff(string $type, string $subject, string $body): void
{
    foreach (fetch_all('SELECT DISTINCT u.id,u.email FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name="admin"') as $a)
        notify((int)$a['id'], $a['email'], $type, $subject, $body);
}
