<?php
// ============================================================
//  includes/notifications.php — Fonctions notifications in-app
// ============================================================
require_once __DIR__ . '/db.php';

function notif_create(string $type, string $titre, string $message, ?int $user_id = null, string $lien = ''): void {
    db_query(
        "INSERT INTO notifications (user_id, type, titre, message, lien) VALUES (?, ?, ?, ?, ?)",
        [$user_id, $type, $titre, $message, $lien]
    );
}
function notif_get_unread(int $user_id): array {
    return db_fetch_all(
        "SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND lu = 0 ORDER BY created_at DESC LIMIT 20",
        [$user_id]
    );
}
function notif_mark_read(int $notif_id, int $user_id): void {
    db_query("UPDATE notifications SET lu = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)", [$notif_id, $user_id]);
}
function notif_mark_all_read(int $user_id): void {
    db_query("UPDATE notifications SET lu = 1 WHERE user_id = ? OR user_id IS NULL", [$user_id]);
}
function mailer_send($to, string $subject, string $body): bool {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) { error_log('PHPMailer non installé'); return false; }
    require_once $autoload;
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host = defined('MAIL_HOST') ? MAIL_HOST : 'localhost';
        $mail->SMTPAuth = false; $mail->Port = 587;
        $mail->setFrom(defined('MAIL_FROM') ? MAIL_FROM : 'noreply@emuci.ci', APP_NAME);
        foreach ((array)$to as $addr) $mail->addAddress($addr);
        $mail->isHTML(true); $mail->Subject = $subject; $mail->Body = $body;
        $mail->send(); return true;
    } catch (Exception $e) { error_log('Mailer: '.$e->getMessage()); return false; }
}
