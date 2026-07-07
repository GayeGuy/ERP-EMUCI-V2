<?php
// ============================================================
//  pages/operations/rivets.php  —  Stock & consommation rivets
// ============================================================
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_auth();
$user        = current_user();
$page_title  = 'Stock Rivets';
$active_page = 'rivets';
$role_slug_r = $user['role_slug'] ?? '';
$site_force_r = ($role_slug_r === 'coordinateur_site' && $user['site_id']) ? (int)$user['site_id'] : 0;
$sites_list  = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");

if ($_SERVER['REQUEST_METHOD']==='POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action==='approvisionner') {
        $site_id = (int)($_POST['site_id'] ?? 0);
        $qte     = (int)($_POST['quantite'] ?? 0);
        $notes   = trim($_POST['notes'] ?? '');
        if (!$site_id || $qte <= 0) json_response(false,'Site et quantité obligatoires.');
        $site = db_fetch_one("SELECT nom FROM sites WHERE id=?",[$site_id]);
        db_query("INSERT INTO op_stock_rivets (site_id,quantite) VALUES (?,?)
                  ON DUPLICATE KEY UPDATE quantite=quantite+?",[$site_id,$qte,$qte]);
        audit_log($user['id'],'CREATE','operations',$site_id,"Approvisionnement $qte rivets → {$site['nom']} ($notes)");
        $nouveau = (int)db_fetch_value("SELECT quantite FROM op_stock_rivets WHERE site_id=?",[$site_id]);
        json_response(true,"$qte rivets ajoutés. Stock total : $nouveau.");
    }

    if ($action==='ajuster') {
        $site_id = (int)($_POST['site_id'] ?? 0);
        $new_qte = (int)($_POST['new_qte']  ?? 0);
        $motif   = trim($_POST['motif']      ?? '');
        $old     = (int)db_fetch_value("SELECT COALESCE(quantite,0) FROM op_stock_rivets WHERE site_id=?",[$site_id]);
        db_query("INSERT INTO op_stock_rivets (site_id,quantite) VALUES (?,?) ON DUPLICATE KEY UPDATE quantite=?",
            [$site_id,$new_qte,$new_qte]);
        audit_log($user['id'],'UPDATE','operations',$site_id,"Ajustement rivets site:$site_id : $old → $new_qte ($motif)");
        json_response(true,'Stock ajusté.');
    }

    json_response(false,'Action inconnue.');
}

// ── FILTRES
$f_from = $_GET['from'] ?? date('Y-m-01');
$f_to   = $_GET['to']   ?? date('Y-m-d');
$f_site = $site_force_r ?: (int)($_GET['site'] ?? 0);

// ── STOCK ACTUEL
$stocks = db_fetch_all(
    "SELECT s.id, s.nom, s.type,
            sr.type_rivet,
            COALESCE(sr.quantite,0) AS quantite,
            COALESCE((SELECT SUM(p.rivets_utilises+p.rivets_endommages)
                      FROM op_points_journaliers p
                      WHERE p.site_id=s.id AND DATE_FORMAT(p.date_point,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')),0) AS utilises_mois
     FROM sites s
     JOIN op_stock_rivets sr ON sr.site_id=s.id
     WHERE s.actif=1 " . ($site_force_r ? "AND s.id=$site_force_r" : "") . "
     ORDER BY s.nom, FIELD(sr.type_rivet,'gonflable','eclate')"
);

// ── HISTORIQUE CONSOMMATION (points journaliers)
$ws_r = $f_site ? "AND p.site_id = $f_site" : ($site_force_r ? "AND p.site_id = $site_force_r" : '');
$recap = db_fetch_all(
    "SELECT p.date_point, s.nom AS site, p.total_engins, p.rivets_utilises, p.rivets_endommages,
            COALESCE(p.rivets_gonflables,0) AS rivets_gonflables,
            COALESCE(p.rivets_eclates,0)    AS rivets_eclates,
            p.rivets_utilises+p.rivets_endommages AS total_sortis
     FROM op_points_journaliers p JOIN sites s ON s.id=p.site_id
     WHERE p.date_point BETWEEN ? AND ? $ws_r
     ORDER BY p.date_point DESC",
    [$f_from, $f_to]
);

// ── TOTAUX PÉRIODE
$total_gonfl = array_sum(array_column($recap, 'rivets_gonflables'));
$total_eclat = array_sum(array_column($recap, 'rivets_eclates'));
$total_endom = array_sum(array_column($recap, 'rivets_endommages'));
$total_util  = array_sum(array_column($recap, 'rivets_utilises'));
$grand_total_sortis = array_sum(array_column($recap, 'total_sortis'));

// ── EXPORTS
if (isset($_GET['export'])) {
    $export = $_GET['export'];
    $site_label = $f_site
        ? (db_fetch_value("SELECT nom FROM sites WHERE id=?", [$f_site]) ?: 'Site inconnu')
        : 'Tous les sites';

    if ($export === 'xlsx') {
        $spreadsheet = new Spreadsheet();

        // Feuille 1 : Consommation
        $sh1 = $spreadsheet->getActiveSheet();
        $sh1->setTitle('Consommation');
        $headers1 = ['Date', 'Site', 'Engins', 'Gonflables', 'Éclatés', 'Endommagés', 'Total sorti'];
        foreach ($headers1 as $i => $h) {
            $col = chr(65 + $i);
            $sh1->setCellValue("{$col}1", $h);
            $sh1->getStyle("{$col}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '06033A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $row = 2;
        foreach ($recap as $r) {
            $sh1->setCellValue("A$row", $r['date_point']);
            $sh1->setCellValue("B$row", $r['site']);
            $sh1->setCellValue("C$row", (int)$r['total_engins']);
            $sh1->setCellValue("D$row", (int)$r['rivets_gonflables']);
            $sh1->setCellValue("E$row", (int)$r['rivets_eclates']);
            $sh1->setCellValue("F$row", (int)$r['rivets_endommages']);
            $sh1->setCellValue("G$row", (int)$r['total_sortis']);
            $row++;
        }
        // Ligne total
        $sh1->setCellValue("A$row", 'TOTAL');
        $sh1->setCellValue("D$row", $total_gonfl);
        $sh1->setCellValue("E$row", $total_eclat);
        $sh1->setCellValue("F$row", $total_endom);
        $sh1->setCellValue("G$row", $grand_total_sortis);
        $sh1->getStyle("A$row:G$row")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B75BC']],
        ]);
        foreach (range('A', 'G') as $col) $sh1->getColumnDimension($col)->setAutoSize(true);

        // Feuille 2 : Stock actuel
        $spreadsheet->createSheet();
        $sh2 = $spreadsheet->getSheet(1);
        $sh2->setTitle('Stock actuel');
        $headers2 = ['Site', 'Type', 'Stock dispo', 'Consommés ce mois'];
        foreach ($headers2 as $i => $h) {
            $col = chr(65 + $i);
            $sh2->setCellValue("{$col}1", $h);
            $sh2->getStyle("{$col}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B75BC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $row2 = 2;
        foreach ($stocks as $s) {
            $sh2->setCellValue("A$row2", $s['nom']);
            $sh2->setCellValue("B$row2", $s['type_rivet'] === 'gonflable' ? 'Gonflables' : 'Éclatés');
            $sh2->setCellValue("C$row2", (int)$s['quantite']);
            $sh2->setCellValue("D$row2", (int)$s['utilises_mois']);
            $row2++;
        }
        foreach (range('A', 'D') as $col) $sh2->getColumnDimension($col)->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'suivi_rivets_' . date('Ymd') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new XlsxWriter($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'rivets_');
        $writer->save($tmp);
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    if ($export === 'pdf') {
        $rows_html = '';
        foreach ($recap as $r) {
            $rows_html .= '<tr>
                <td>' . h(fmt_date($r['date_point'])) . '</td>
                <td>' . h($r['site']) . '</td>
                <td style="text-align:center">' . (int)$r['total_engins'] . '</td>
                <td style="text-align:center;color:#1565c0;font-weight:700">' . (int)$r['rivets_gonflables'] . '</td>
                <td style="text-align:center;color:#880e4f;font-weight:700">' . (int)$r['rivets_eclates'] . '</td>
                <td style="text-align:center;color:' . ($r['rivets_endommages'] > 0 ? '#991b1b' : '#64748b') . '">' . (int)$r['rivets_endommages'] . '</td>
                <td style="text-align:center;font-weight:800">' . (int)$r['total_sortis'] . '</td>
            </tr>';
        }
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
        h1{font-size:15px;color:#06033A;margin:0 0 3px 0}
        .sub{font-size:9px;color:#64748b;margin-bottom:14px}
        table{width:100%;border-collapse:collapse}
        th{background:#06033A;color:#fff;padding:7px 8px;font-size:10px;text-align:center}
        td{padding:5px 8px;border-bottom:1px solid #e2e8f0;font-size:10px}
        tr:nth-child(even) td{background:#f8fafc}
        .total-row td{background:#06033A!important;color:#fff!important;font-weight:bold;text-align:center}
        </style></head><body>
        <table width="100%" style="border-collapse:collapse;margin-bottom:10px"><tr>
          <td style="vertical-align:middle;width:85px;padding-right:10px">' . pdf_logo_img('38px') . '</td>
          <td style="vertical-align:middle;padding-left:12px;border-left:3px solid #06033A">
            <div style="font-size:15px;font-weight:bold;color:#06033A">Suivi Consommation Rivets</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px">Express Multiservices CI</div>
          </td>
        </tr></table>
        <div class="sub">Période : ' . h($f_from) . ' → ' . h($f_to) . ' &nbsp;|&nbsp; Site : ' . h($site_label) . ' &nbsp;|&nbsp; Généré le ' . date('d/m/Y H:i') . '</div>
        <table><thead><tr>
            <th>Date</th><th>Site</th><th>Engins</th><th>Gonflables</th><th>Éclatés</th><th>Endommagés</th><th>Total sorti</th>
        </tr></thead><tbody>
        ' . $rows_html . '
        <tr class="total-row"><td colspan="3">TOTAL PÉRIODE</td>
            <td>' . $total_gonfl . '</td>
            <td>' . $total_eclat . '</td>
            <td>' . $total_endom . '</td>
            <td>' . $grand_total_sortis . '</td>
        </tr></tbody></table></body></html>';

        $opts = new Options();
        $opts->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opts);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="suivi_rivets_' . date('Ymd') . '.pdf"');
        echo $pdf->output();
        exit;
    }
}

include __DIR__ . '/../../templates/header.php';
?>
<style>
.fsel{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;cursor:pointer;outline:none;font-family:'DM Sans',sans-serif}
.filter-bar{background:white;border:1px solid var(--border);border-radius:12px;padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:20px}
.filter-bar label{font-size:12px;font-weight:600;color:var(--navy);display:block;margin-bottom:4px}
.filter-bar input,.filter-bar select{padding:8px 11px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:white;outline:none}
.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(10,22,40,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:16px;width:480px;max-width:95vw;max-height:92vh;overflow-y:auto;animation:mIn .25s cubic-bezier(.22,1,.36,1)}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.mhdr{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:10}
.mhdr h3{font-family:'Montserrat',sans-serif;font-size:17px;font-weight:700}
.mclose{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.mbody{padding:24px}
.mfoot{padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;position:sticky;bottom:0;background:white}
</style>

<!-- TOOLBAR -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <div>
    <h2 style="font-family:'Montserrat',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">Suivi Rivets</h2>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">Stock et consommation rivets — Points journaliers</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'xlsx'])) ?>"
       class="btn btn-secondary" style="font-size:13px;display:flex;align-items:center;gap:6px">
      <i class="ph-duotone ph-microsoft-excel-logo" style="font-size:16px"></i> Excel
    </a>
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'pdf'])) ?>"
       class="btn btn-secondary" style="font-size:13px;display:flex;align-items:center;gap:6px">
      <i class="ph-duotone ph-file-pdf" style="font-size:16px"></i> PDF
    </a>
    <?php
    $can_appro = in_array($role_slug_r, ['admin','superadmin','gestionnaire_stock','superviseur_operation']);
    ?>
    <?php if($can_appro): ?>
    <button class="btn btn-primary" onclick="document.getElementById('mAppro').classList.add('open')">+ Approvisionner</button>
    <button class="btn btn-secondary" onclick="document.getElementById('mAjust').classList.add('open')">⚖️ Ajustement</button>
    <?php endif; ?>
  </div>
</div>

<!-- FILTRE BAR -->
<div class="filter-bar">
  <div>
    <label>Du</label>
    <input type="date" id="fFrom" value="<?= h($f_from) ?>">
  </div>
  <div>
    <label>Au</label>
    <input type="date" id="fTo" value="<?= h($f_to) ?>">
  </div>
  <?php if (!$site_force_r): ?>
  <div>
    <label>Site</label>
    <select id="fSite">
      <option value="0">Tous les sites</option>
      <?php foreach ($sites_list as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $f_site == $s['id'] ? 'selected' : '' ?>><?= h($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div style="display:flex;gap:8px;align-items:flex-end">
    <button class="btn btn-primary" style="font-size:13px" onclick="appliquerFiltres()">Appliquer</button>
    <button class="btn btn-secondary" style="font-size:13px" onclick="resetFiltres()">Réinitialiser</button>
  </div>
</div>

<!-- STOCK CARDS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px">
<?php foreach($stocks as $s):
  $pct   = $s['quantite']>0 ? min(100,round($s['quantite']/5000*100)) : 0;
  $color = $s['quantite']<200 ? 'var(--danger)' : ($s['quantite']<1000 ? 'var(--warning)' : 'var(--success)');
  $is_gonfl  = ($s['type_rivet'] === 'gonflable');
  $icon      = $is_gonfl ? '🔵' : '🔴';
  $type_lbl  = $is_gonfl ? 'Gonflables' : 'Éclatés';
  $bg_header = $is_gonfl ? '#e3f2fd' : '#fce4ec';
  $dispo_lbl = $is_gonfl ? 'rivets gonflables disponibles' : 'rivets éclatés disponibles';
?>
<div style="background:white;border:1px solid var(--border);border-radius:14px;overflow:hidden">
  <div style="padding:16px;border-bottom:1px solid var(--border);background:<?= $bg_header ?>;display:flex;align-items:center;gap:12px">
    <div style="font-size:28px"><?= $icon ?></div>
    <div style="flex:1">
      <div style="font-family:'Montserrat',sans-serif;font-size:14px;font-weight:700;color:var(--navy)"><?= h($s['nom']) ?></div>
      <div style="font-size:12px;color:var(--muted);font-weight:600"><?= $type_lbl ?></div>
    </div>
  </div>
  <div style="padding:16px">
    <div style="font-family:'Montserrat',sans-serif;font-size:36px;font-weight:900;color:<?= $color ?>;line-height:1"><?= fmt_number($s['quantite']) ?></div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px"><?= $dispo_lbl ?></div>
    <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-bottom:10px">
      <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
    </div>
    <div style="font-size:12px;color:var(--muted)">Utilisés ce mois : <strong><?= fmt_number($s['utilises_mois']) ?></strong></div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- HISTORIQUE -->
<div class="card">
  <div class="card-header">
    <h3><i class="ph-duotone ph-clipboard-text" style="vertical-align:middle"></i>
      Consommation rivets — Points journaliers
      <span style="font-size:12px;font-weight:400;color:var(--muted);margin-left:8px">
        du <?= h(fmt_date($f_from)) ?> au <?= h(fmt_date($f_to)) ?>
      </span>
    </h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Date</th>
        <?php if(!$site_force_r): ?><th>Site</th><?php endif; ?>
        <th>Engins</th>
        <th style="text-align:center">🔵 Gonfl.</th>
        <th style="text-align:center">🔴 Éclatés</th>
        <th style="text-align:center">Endommagés</th>
        <th style="text-align:center">Total sortis</th>
      </tr></thead>
      <tbody>
      <?php if(empty($recap)): ?>
        <tr><td colspan="<?= $site_force_r ? 6 : 7 ?>" style="text-align:center;padding:30px;color:var(--muted)">Aucun point journalier sur cette période.</td></tr>
      <?php else: foreach($recap as $r): ?>
        <tr>
          <td><?= fmt_date($r['date_point']) ?></td>
          <?php if(!$site_force_r): ?><td><?= h($r['site']) ?></td><?php endif; ?>
          <td style="text-align:center;font-weight:700"><?= $r['total_engins'] ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:700;color:#1565c0"><?= $r['rivets_gonflables'] ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:700;color:#880e4f"><?= $r['rivets_eclates'] ?></td>
          <td style="text-align:center;color:<?= $r['rivets_endommages']>0 ? 'var(--danger)' : 'var(--muted)' ?>;font-weight:600"><?= $r['rivets_endommages']?:0 ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:800;font-size:15px"><?= $r['total_sortis'] ?></td>
        </tr>
      <?php endforeach; ?>
        <tr style="background:#f0f4ff">
          <td colspan="<?= $site_force_r ? 2 : 3 ?>" style="font-weight:700;color:var(--navy)">TOTAL PÉRIODE</td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:900;color:#1565c0"><?= $total_gonfl ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:900;color:#880e4f"><?= $total_eclat ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:900;color:var(--danger)"><?= $total_endom ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:900;font-size:16px;color:var(--navy)"><?= $grand_total_sortis ?></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL APPROVISIONNER -->
<div class="modal-overlay" id="mAppro">
  <div class="modal">
    <div class="mhdr"><h3>+ Approvisionner en rivets</h3><button class="mclose" onclick="document.getElementById('mAppro').classList.remove('open')">✕</button></div>
    <div class="mbody">
      <div id="apAlert"></div>
      <div class="form-group"><label>Site *</label>
        <select class="form-control" id="ap-site">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Quantité de rivets *</label>
        <input type="number" class="form-control" id="ap-qte" min="1" placeholder="Ex: 5000">
      </div>
      <div class="form-group"><label>Notes</label>
        <input type="text" class="form-control" id="ap-notes" placeholder="Bon de livraison, fournisseur…">
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="document.getElementById('mAppro').classList.remove('open')">Annuler</button>
      <button class="btn btn-primary" onclick="saveAppro()">✅ Valider</button>
    </div>
  </div>
</div>

<!-- MODAL AJUSTEMENT -->
<div class="modal-overlay" id="mAjust">
  <div class="modal">
    <div class="mhdr"><h3>⚖️ Ajustement stock rivets</h3><button class="mclose" onclick="document.getElementById('mAjust').classList.remove('open')">✕</button></div>
    <div class="mbody">
      <div id="ajAlert"></div>
      <div class="form-group"><label>Site *</label>
        <select class="form-control" id="aj-site">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Nouveau stock *</label>
        <input type="number" class="form-control" id="aj-qte" min="0">
      </div>
      <div class="form-group"><label>Motif *</label>
        <input type="text" class="form-control" id="aj-motif" placeholder="Inventaire physique…">
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="document.getElementById('mAjust').classList.remove('open')">Annuler</button>
      <button class="btn btn-primary" onclick="saveAjust()">✅ Appliquer</button>
    </div>
  </div>
</div>

<script>
function ap(data){const fd=new FormData();for(const[k,v]of Object.entries(data))if(v!==undefined)fd.append(k,v);return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json());}
function saveAppro(){
  ap({action:'approvisionner',site_id:document.getElementById('ap-site').value,quantite:document.getElementById('ap-qte').value,notes:document.getElementById('ap-notes').value})
    .then(d=>{if(d.success){toast(d.message,'success');document.getElementById('mAppro').classList.remove('open');setTimeout(()=>location.reload(),800);}else document.getElementById('apAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;});
}
function saveAjust(){
  ap({action:'ajuster',site_id:document.getElementById('aj-site').value,new_qte:document.getElementById('aj-qte').value,motif:document.getElementById('aj-motif').value})
    .then(d=>{if(d.success){toast(d.message,'success');document.getElementById('mAjust').classList.remove('open');setTimeout(()=>location.reload(),800);}else document.getElementById('ajAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;});
}
function appliquerFiltres(){
    const p=new URLSearchParams();
    p.set('from',document.getElementById('fFrom').value);
    p.set('to',document.getElementById('fTo').value);
    <?php if(!$site_force_r): ?>
    const site=document.getElementById('fSite').value;
    if(site!=='0') p.set('site',site);
    <?php endif; ?>
    location.href='?'+p.toString();
}
function resetFiltres(){
    const today=new Date(),y=today.getFullYear(),m=String(today.getMonth()+1).padStart(2,'0'),d=String(today.getDate()).padStart(2,'0');
    location.href='?from='+y+'-'+m+'-01&to='+y+'-'+m+'-'+d;
}
['mAppro','mAjust'].forEach(id=>document.getElementById(id).addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');}));
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
