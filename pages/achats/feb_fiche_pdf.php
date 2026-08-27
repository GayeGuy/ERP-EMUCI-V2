<?php
// ============================================================
//  pages/achats/feb_fiche_pdf.php — Fiche FEB imprimable (Bloc 2, J9).
//  Générée à la demande, à tout moment du cycle de vie — jamais stockée.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/achats.php';
require_once __DIR__ . '/../../includes/pdf_achats.php';

require_auth();
$user = current_user();
require_permission('achats', 'can_read');

$feb_id = (int)($_GET['id'] ?? 0);
$feb_numero = db_fetch_value("SELECT numero FROM feb WHERE id=?", [$feb_id]);
if ($feb_numero === false) { http_response_code(404); echo 'FEB introuvable.'; exit; }

require_once __DIR__ . '/../../vendor/autoload.php';

try {
    $html = ach_html_fiche_imprimable($feb_id);
} catch (AchValidationException $e) {
    http_response_code(404);
    echo h($e->getMessage());
    exit;
}

$options = new \Dompdf\Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'FEB_' . preg_replace('/[^A-Za-z0-9_-]/', '', $feb_numero ?: "feb$feb_id") . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
