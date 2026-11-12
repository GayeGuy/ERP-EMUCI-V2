<?php
// ============================================================
//  cron_mails.php — envoie par mail les notifications en attente.
//  Appelé toutes les 5 minutes par GitHub Actions
//  (.github/workflows/envoyer-mails.yml) :
//    POST /cron_mails.php   avec l'en-tête  X-Cron-Token: <CRON_TOKEN>
//  Sans CRON_TOKEN défini côté serveur, ou avec un jeton faux : refusé.
//  Sans effet tant que MAIL_ENABLED n'est pas à 1.
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
$attendu = (string)env('CRON_TOKEN', '');
$recu    = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $attendu === '' || strlen($attendu) < 24 || !hash_equals($attendu, $recu)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
if (!mail_actif()) { echo json_encode(['ok' => true, 'actif' => false]); exit; }

// Un seul envoi à la fois (deux appels qui se chevauchent enverraient deux fois).
if (!(bool)db_fetch_value("SELECT pg_try_advisory_lock(7420001)")) { echo json_encode(['ok' => true, 'occupe' => true]); exit; }
try {
    $r = mail_envoyer_notifications(100);
} finally {
    db_query("SELECT pg_advisory_unlock(7420001)");
}
echo json_encode(['ok' => true] + $r);
