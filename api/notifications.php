<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/helpers.php';

require_auth();
$user = current_user();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'mark_read':
        $id = (int)($_POST['id'] ?? 0);
        notif_mark_read($id, $user['id']);
        json_response(true, 'Notification lue.');
        break;

    case 'mark_all':
        notif_mark_all_read($user['id']);
        json_response(true, 'Toutes les notifications marquées lues.');
        break;

    case 'get':
        $notifs = notif_get_unread($user['id']);
        json_response(true, '', ['data' => $notifs, 'count' => count($notifs)]);
        break;

    default:
        json_response(false, 'Action inconnue.');
}
