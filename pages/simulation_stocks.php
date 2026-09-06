<?php
// ============================================================
//  pages/simulation_stocks.php  —  Simulation & projection bobines
//  n° 2.6 du CR de réunion PDG.
//
//  Outil de projection, pas un rapport : il ne lit que l'historique et
//  n'écrit jamais rien. Aucune simulation ne doit pouvoir modifier un
//  stock réel — c'est la règle qui a guidé toute la page.
//
//  Deux cas d'usage demandés au CR :
//   1. « J'ai N bobines, jusqu'à quand puis-je travailler ? »
//   2. « J'ouvre un site de plus, mon stock encaisse-t-il ? Sinon,
//      combien commander pour tenir jusqu'à telle date ? »
//
//  Le stock est suivi en films, les questions se posent en bobines :
//  la conversion passe par films_par_bobine_moyen(), calculé sur le
//  parc réel plutôt que sur une constante.
// ============================================================
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/consommation.php';

require_auth();
require_permission('simulation_stocks', 'can_read');

$user        = current_user();
$page_title  = 'Simulation & projection';
$active_page = 'simulation_stocks';

// ============================================================
//  PARAMÈTRES DE SIMULATION
//  Tout vient de l'URL : la page est rejouable et partageable par lien,
//  et aucune saisie n'a besoin d'être stockée.
// ============================================================
$f_site      = (int)($_GET['site'] ?? 0);
$fenetre     = max(7, min(365, (int)($_GET['fenetre'] ?? 30)));   // historique retenu
$horizon     = (int)($_GET['horizon'] ?? 6);                      // mois projetés
if (!in_array($horizon, [3, 6, 12, 24], true)) $horizon = 6;

$conso_auto  = conso_moy_site($f_site, $fenetre);                 // films/jour observés
$conso_saisie = $_GET['conso'] ?? '';
$conso_jour  = ($conso_saisie !== '' && is_numeric($conso_saisie))
             ? max(0.0, (float)$conso_saisie)
             : $conso_auto;
$conso_manuelle = ($conso_saisie !== '' && is_numeric($conso_saisie));

$films_bobine = max(1, (int)($_GET['films_bobine'] ?? films_par_bobine_moyen($f_site)));

// Stock réellement disponible, proposé par défaut dans le formulaire
$stock_films_reel = (int) db_fetch_value(
    "SELECT COALESCE(SUM(films_restants),0) FROM op_bobines
      WHERE statut IN ('en_stock','en_cours') " . ($f_site ? "AND site_id = ?" : ""),
    $f_site ? [$f_site] : []
);
$bobines_defaut = (int) ceil($stock_films_reel / $films_bobine);

$nb_bobines  = isset($_GET['bobines']) && $_GET['bobines'] !== ''
             ? max(0, (int)$_GET['bobines'])
             : $bobines_defaut;

// ── Cas 2 : nouveaux sites
$nb_sites_new   = max(0, min(20, (int)($_GET['sites_new'] ?? 0)));
$conso_site_new = max(0.0, (float)($_GET['conso_new'] ?? 0));
// Sans estimation saisie, on prend la consommation moyenne d'un site
// existant : plus honnête qu'un zéro qui rendrait l'ajout indolore.
if ($nb_sites_new > 0 && $conso_site_new <= 0) {
    $sites_actifs = (int) db_fetch_value(
        "SELECT COUNT(DISTINCT site_id) FROM consommations_bobines
          WHERE date_conso >= (CURRENT_DATE - (? || ' DAY')::interval)", [$fenetre]);
    $conso_site_new = $sites_actifs > 0 ? conso_moy_site(0, $fenetre) / $sites_actifs : 0.0;
    $conso_new_estimee = true;
} else {
    $conso_new_estimee = false;
}

// ============================================================
//  CALCUL
// ============================================================
$stock_films   = $nb_bobines * $films_bobine;
$conso_totale  = $conso_jour + ($nb_sites_new * $conso_site_new);
$jours_tenus   = $conso_totale > 0 ? (int) floor($stock_films / $conso_totale) : null;
$date_epuis    = $jours_tenus !== null ? date('Y-m-d', strtotime("+$jours_tenus days")) : null;
$jours_cible   = (int) round($horizon * 30.44);          // mois moyen grégorien
$films_requis  = (int) ceil($conso_totale * $jours_cible);
$deficit_films = max(0, $films_requis - $stock_films);
$bobines_a_commander = (int) ceil($deficit_films / $films_bobine);
$couvre_horizon = $jours_tenus !== null && $jours_tenus >= $jours_cible;

// Courbe d'évolution — un point par semaine jusqu'à épuisement ou horizon
$courbe = [];
if ($conso_totale > 0) {
    $bornes = min($jours_cible, max($jours_tenus ?? 0, $jours_cible));
    for ($j = 0; $j <= $bornes; $j += 7) {
        $courbe[] = [
            'date'  => date('Y-m-d', strtotime("+$j days")),
            'films' => max(0, (int) round($stock_films - $conso_totale * $j)),
        ];
    }
}

$sites_list = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");
$site_nom   = '';
foreach ($sites_list as $s) if ((int)$s['id'] === $f_site) $site_nom = $s['nom'];
$conso_sites = conso_moy_par_site($fenetre);

$mois_fr = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',7=>'juillet',
            8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
$epuis_texte = $date_epuis
    ? $mois_fr[(int)date('n', strtotime($date_epuis))] . ' ' . date('Y', strtotime($date_epuis))
    : null;

// ============================================================
//  EXPORTS
// ============================================================
$export = trim($_GET['export'] ?? '');
if ($export !== '' && !can('simulation_stocks', 'can_export')) {
    http_response_code(403); exit('Export non autorisé pour ce profil.');
}

$resume = [
    ['Périmètre',                   $site_nom ?: 'Tous les sites'],
    ['Bobines projetées',           fmt_number($nb_bobines)],
    ['Films par bobine',            fmt_number($films_bobine)],
    ['Stock projeté (films)',       fmt_number($stock_films)],
    ['Consommation retenue',        number_format($conso_jour, 1, ',', ' ') . ' films/jour'
                                    . ($conso_manuelle ? ' (saisie)' : ' (observée)')],
    ['Nouveaux sites simulés',      $nb_sites_new . ($nb_sites_new ? ' × ' . number_format($conso_site_new, 1, ',', ' ') . ' films/jour' : '')],
    ['Consommation totale',         number_format($conso_totale, 1, ',', ' ') . ' films/jour'],
    ['Autonomie',                   $jours_tenus !== null ? $jours_tenus . ' jours' : 'indéterminée'],
    ["Date estimée d'épuisement",   $date_epuis ? fmt_date($date_epuis) : '—'],
    ['Horizon cible',               $horizon . ' mois (' . $jours_cible . ' jours)'],
    ['Couvre l’horizon',            $couvre_horizon ? 'Oui' : 'Non'],
    ['Bobines à commander',         $couvre_horizon ? '0' : fmt_number($bobines_a_commander)],
];

if ($export === 'xlsx') {
    $sp = new Spreadsheet(); $sh = $sp->getActiveSheet()->setTitle('Simulation');
    $sh->setCellValue('A1', 'Paramètre'); $sh->setCellValue('B1', 'Valeur');
    $sh->getStyle('A1:B1')->applyFromArray([
        'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'06033A']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
    $r = 2;
    foreach ($resume as [$k, $v]) { $sh->setCellValue("A$r", $k); $sh->setCellValue("B$r", $v); $r++; }
    $r += 1;
    $sh->setCellValue("A$r", 'Date'); $sh->setCellValue("B$r", 'Films restants');
    $sh->getStyle("A$r:B$r")->getFont()->setBold(true);
    $r++;
    foreach ($courbe as $p) { $sh->setCellValue("A$r", $p['date']); $sh->setCellValue("B$r", $p['films']); $r++; }
    foreach (['A','B'] as $c) $sh->getColumnDimension($c)->setAutoSize(true);
    audit_log($user['id'],'READ','simulation_stocks',0,"Export XLSX — $nb_bobines bobines, horizon $horizon mois");
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="simulation_stocks.xlsx"');
    header('Cache-Control: max-age=0');
    (new XlsxWriter($sp))->save('php://output');
    exit;
}

if ($export === 'pdf') {
    ob_start(); ?>
    <style>
      body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#1a1a2e}
      h1{font-size:16px;color:#06033A;margin:0 0 3px}
      .meta{font-size:9.5px;color:#64748b;margin-bottom:14px}
      table{width:100%;border-collapse:collapse;margin-bottom:16px}
      th{background:#06033A;color:#fff;font-size:9px;padding:6px 8px;text-align:left}
      td{border-bottom:1px solid #e2e8f0;padding:6px 8px}
      td.k{color:#64748b;width:45%}
      .concl{background:#f0f4ff;border-left:3px solid #1B75BC;padding:10px 13px;font-size:11.5px}
    </style>
    <h1>Simulation de couverture — bobines</h1>
    <div class="meta">
      <?= h($site_nom ?: 'Tous les sites') ?> · généré le <?= date('d/m/Y à H:i') ?> · ERP EMUCI
    </div>
    <div class="concl">
      <?php if ($jours_tenus === null): ?>
        Aucune consommation observée sur la période : la projection ne peut pas être calculée.
      <?php else: ?>
        <?= fmt_number($nb_bobines) ?> bobines = utilisation jusqu'en <?= h($epuis_texte) ?>
        au rythme actuel, soit <?= $jours_tenus ?> jours.
        <?php if (!$couvre_horizon): ?>
          Pour tenir <?= $horizon ?> mois, il manque <?= fmt_number($bobines_a_commander) ?> bobine(s).
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <table>
      <thead><tr><th>Paramètre</th><th>Valeur</th></tr></thead>
      <tbody>
      <?php foreach ($resume as [$k,$v]): ?>
        <tr><td class="k"><?= h($k) ?></td><td><?= h($v) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php
    $html = ob_get_clean();
    $opt = new Options(); $opt->set('isHtml5ParserEnabled', true); $opt->set('defaultFont','DejaVu Sans');
    $pdf = new Dompdf($opt); $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4','portrait'); $pdf->render();
    audit_log($user['id'],'READ','simulation_stocks',0,"Export PDF — $nb_bobines bobines");
    $pdf->stream('simulation_stocks.pdf', ['Attachment'=>true]);
    exit;
}

include __DIR__ . '/../templates/header.php';
?>
<style>
.sim-grid{display:grid;grid-template-columns:330px minmax(0,1fr);gap:22px;align-items:start}
@media(max-width:980px){.sim-grid{grid-template-columns:minmax(0,1fr)}}
.sim-form{background:white;border:1px solid var(--border);border-radius:14px;padding:20px;position:sticky;top:88px}
.sim-form h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:14.5px;font-weight:800;color:var(--navy);margin:0 0 4px}
.sim-form .hint{font-size:12px;color:var(--muted);margin:0 0 16px;line-height:1.5}
.sim-sec{font-family:'IBM Plex Mono',monospace;font-size:10.5px;font-weight:700;letter-spacing:.09em;
  text-transform:uppercase;color:var(--muted);margin:18px 0 9px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.sim-sec:first-of-type{margin-top:0}
.sim-f{margin-bottom:12px}
.sim-f label{display:block;font-size:12px;font-weight:700;color:var(--navy);margin-bottom:4px}
.sim-f input,.sim-f select{width:100%;padding:8px 11px;border:1.5px solid var(--border);border-radius:9px;
  font-size:13.5px;outline:none;box-sizing:border-box}
.sim-f .sub{font-size:11.5px;color:var(--muted);margin-top:3px;line-height:1.45}
.sim-verdict{border-radius:16px;padding:22px 24px;margin-bottom:18px;color:white}
.sim-verdict.ok{background:linear-gradient(135deg,#0f6b3f,#1e8449)}
.sim-verdict.ko{background:linear-gradient(135deg,#8c2c22,#c0392b)}
.sim-verdict.na{background:linear-gradient(135deg,#3a4a63,#64748b)}
.sim-verdict .t{font-family:'Plus Jakarta Sans',sans-serif;font-size:21px;font-weight:800;line-height:1.25;margin-bottom:6px}
.sim-verdict .s{font-size:13.5px;opacity:.92;line-height:1.55}
.sim-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(158px,1fr));gap:12px;margin-bottom:18px}
.sim-kpi{background:white;border:1px solid var(--border);border-radius:13px;padding:14px 16px;border-left:4px solid var(--blue)}
.sim-kpi.warn{border-left-color:#f39c12}
.sim-kpi.crit{border-left-color:var(--danger)}
.sim-kpi-v{font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:900;color:var(--navy);line-height:1}
.sim-kpi-l{font-size:11.5px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:5px}
.sim-card{background:white;border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:18px}
.sim-card h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;color:var(--navy);margin:0 0 3px}
.sim-card .sc-sub{font-size:12px;color:var(--muted);margin-bottom:14px}
table.sim-t{width:100%;border-collapse:collapse;font-size:13px}
table.sim-t td{padding:7px 0;border-bottom:1px solid var(--border)}
table.sim-t tr:last-child td{border-bottom:none}
table.sim-t td:first-child{color:var(--muted)}
table.sim-t td:last-child{text-align:right;font-weight:700;color:var(--navy);font-variant-numeric:tabular-nums}
</style>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">
      <i class="ph ph-trend-up" aria-hidden="true"></i> Simulation & projection des stocks
    </h2>
    <p style="font-size:13px;color:var(--muted);margin-top:4px">
      Outil de projection — aucune donnée n'est modifiée, quelle que soit la simulation lancée.
    </p>
  </div>
  <?php if(can('simulation_stocks','can_export')): ?>
  <div style="display:flex;gap:8px">
    <a class="btn btn-secondary btn-sm" href="?<?= h(http_build_query(array_merge($_GET,['export'=>'xlsx']))) ?>">
      <i class="ph-duotone ph-microsoft-excel-logo"></i> Excel</a>
    <a class="btn btn-secondary btn-sm" href="?<?= h(http_build_query(array_merge($_GET,['export'=>'pdf']))) ?>">
      <i class="ph-duotone ph-file-pdf"></i> PDF</a>
  </div>
  <?php endif; ?>
</div>

<div class="sim-grid">

  <!-- ══ PARAMÈTRES ══ -->
  <form method="GET" class="sim-form">
    <h3>Paramètres</h3>
    <p class="hint">Les valeurs par défaut viennent de votre stock et de votre historique réels.</p>

    <div class="sim-sec">Périmètre</div>
    <div class="sim-f">
      <label>Site</label>
      <select name="site" onchange="this.form.submit()">
        <option value="0">Tous les sites</option>
        <?php foreach($sites_list as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $f_site===(int)$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="sim-f">
      <label>Historique retenu</label>
      <select name="fenetre" onchange="this.form.submit()">
        <?php foreach([15=>'15 derniers jours',30=>'30 derniers jours',60=>'60 derniers jours',90=>'90 derniers jours'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $fenetre===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <div class="sub">Période sur laquelle la consommation moyenne est mesurée.</div>
    </div>

    <div class="sim-sec">Cas 1 — stock à projeter</div>
    <div class="sim-f">
      <label>Bobines disponibles</label>
      <input type="number" name="bobines" min="0" value="<?= $nb_bobines ?>">
      <div class="sub">Stock réel actuel : <strong><?= fmt_number($bobines_defaut) ?></strong> bobine(s)
        (<?= fmt_number($stock_films_reel) ?> films).</div>
    </div>
    <div class="sim-f">
      <label>Films par bobine</label>
      <input type="number" name="films_bobine" min="1" value="<?= $films_bobine ?>">
      <div class="sub">Moyenne du parc en service. Le stock est suivi en films.</div>
    </div>
    <div class="sim-f">
      <label>Consommation journalière</label>
      <input type="number" name="conso" step="0.1" min="0" value="<?= $conso_manuelle ? h($conso_saisie) : '' ?>"
             placeholder="<?= number_format($conso_auto, 1, '.', '') ?> (observée)">
      <div class="sub">Laisser vide pour utiliser la moyenne observée
        (<strong><?= number_format($conso_auto, 1, ',', ' ') ?></strong> films/jour).</div>
    </div>
    <div class="sim-f">
      <label>Horizon cible</label>
      <select name="horizon">
        <?php foreach([3=>'3 mois',6=>'6 mois',12=>'1 an',24=>'2 ans'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $horizon===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="sim-sec">Cas 2 — ouverture de sites</div>
    <div class="sim-f">
      <label>Nouveaux sites</label>
      <input type="number" name="sites_new" min="0" max="20" value="<?= $nb_sites_new ?>">
    </div>
    <div class="sim-f">
      <label>Consommation par nouveau site</label>
      <input type="number" name="conso_new" step="0.1" min="0"
             value="<?= $nb_sites_new && !$conso_new_estimee ? h((string)$conso_site_new) : '' ?>">
      <div class="sub">Vide = moyenne d'un site existant
        (<strong><?= number_format($conso_site_new, 1, ',', ' ') ?></strong> films/jour).</div>
    </div>

    <button class="btn btn-primary" style="width:100%;margin-top:6px" type="submit">
      <i class="ph ph-play" aria-hidden="true"></i> Lancer la simulation
    </button>
    <a class="btn btn-secondary" style="width:100%;margin-top:8px;text-align:center" href="simulation_stocks.php">
      Réinitialiser
    </a>
  </form>

  <!-- ══ RÉSULTATS ══ -->
  <div>
    <?php if ($jours_tenus === null): ?>
    <div class="sim-verdict na">
      <div class="t">Projection impossible</div>
      <div class="s">Aucune consommation n'a été enregistrée sur les <?= $fenetre ?> derniers jours
        pour ce périmètre. Saisissez une consommation journalière à la main pour simuler malgré tout.</div>
    </div>
    <?php else: ?>
    <div class="sim-verdict <?= $couvre_horizon ? 'ok' : 'ko' ?>">
      <div class="t">
        <?= fmt_number($nb_bobines) ?> bobines = utilisation jusqu'en <?= h($epuis_texte) ?>
      </div>
      <div class="s">
        Au rythme de <?= number_format($conso_totale, 1, ',', ' ') ?> films/jour, ce stock tient
        <strong><?= fmt_number($jours_tenus) ?> jours</strong>, soit jusqu'au <?= h(fmt_date($date_epuis)) ?>.
        <?php if ($couvre_horizon): ?>
          Il couvre l'horizon de <?= $horizon ?> mois demandé.
        <?php else: ?>
          Il <strong>ne couvre pas</strong> l'horizon de <?= $horizon ?> mois :
          il manque <strong><?= fmt_number($bobines_a_commander) ?> bobine(s)</strong>
          (<?= fmt_number($deficit_films) ?> films) à commander.
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="sim-kpis">
      <div class="sim-kpi">
        <div class="sim-kpi-v"><?= fmt_number($stock_films) ?></div>
        <div class="sim-kpi-l">Films projetés</div>
      </div>
      <div class="sim-kpi">
        <div class="sim-kpi-v"><?= number_format($conso_totale, 1, ',', ' ') ?></div>
        <div class="sim-kpi-l">Films / jour</div>
      </div>
      <div class="sim-kpi <?= $jours_tenus !== null && $jours_tenus < 30 ? 'crit' : ($couvre_horizon ? '' : 'warn') ?>">
        <div class="sim-kpi-v"><?= $jours_tenus !== null ? fmt_number($jours_tenus) : '—' ?></div>
        <div class="sim-kpi-l">Jours d'autonomie</div>
      </div>
      <div class="sim-kpi <?= $couvre_horizon ? '' : 'crit' ?>">
        <div class="sim-kpi-v"><?= $couvre_horizon ? '0' : fmt_number($bobines_a_commander) ?></div>
        <div class="sim-kpi-l">Bobines à commander</div>
      </div>
    </div>

    <?php if (!empty($courbe)): ?>
    <div class="sim-card">
      <h4>Évolution du stock</h4>
      <div class="sc-sub">Projection linéaire au rythme retenu, sur l'horizon de <?= $horizon ?> mois.</div>
      <canvas id="simChart" height="190"></canvas>
    </div>
    <?php endif; ?>

    <div class="sim-card">
      <h4>Détail du calcul</h4>
      <div class="sc-sub">Chaque valeur retenue, pour que le résultat puisse être contesté.</div>
      <table class="sim-t">
        <?php foreach ($resume as [$k,$v]): ?>
        <tr><td><?= h($k) ?></td><td><?= h($v) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if (!$f_site && count($conso_sites) > 1): ?>
    <div class="sim-card">
      <h4>Consommation observée par site</h4>
      <div class="sc-sub">Sur les <?= $fenetre ?> derniers jours — utile pour estimer un nouveau site.</div>
      <table class="sim-t">
        <?php foreach ($conso_sites as $sid => $c): if ($c['conso'] <= 0) continue; ?>
        <tr><td><?= h($c['nom']) ?></td><td><?= number_format($c['conso'], 1, ',', ' ') ?> films/j</td></tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($courbe)): ?>
<script>
(function(){
  const pts = <?= json_encode($courbe) ?>;
  const cv  = document.getElementById('simChart');
  if (!cv || !pts.length) return;
  const dpr = window.devicePixelRatio || 1;
  function dessiner(){
    const w = cv.clientWidth, h = 190;
    cv.width = w * dpr; cv.height = h * dpr;
    const g = cv.getContext('2d'); g.scale(dpr, dpr);
    g.clearRect(0, 0, w, h);

    const padL = 52, padR = 12, padT = 12, padB = 26;
    const cw = w - padL - padR, ch = h - padT - padB;
    const maxY = Math.max(...pts.map(p => p.films), 1);
    const x = i => padL + (cw * i) / Math.max(pts.length - 1, 1);
    const y = v => padT + ch - (ch * v) / maxY;

    // grille + graduations
    g.strokeStyle = '#e2e8f0'; g.fillStyle = '#94a3b8';
    g.font = '10px system-ui'; g.textAlign = 'right'; g.lineWidth = 1;
    for (let i = 0; i <= 4; i++){
      const v = maxY * i / 4, yy = y(v);
      g.beginPath(); g.moveTo(padL, yy); g.lineTo(w - padR, yy); g.stroke();
      g.fillText(Math.round(v).toLocaleString('fr-FR'), padL - 7, yy + 3);
    }
    // aire + courbe
    g.beginPath(); g.moveTo(x(0), y(pts[0].films));
    pts.forEach((p, i) => g.lineTo(x(i), y(p.films)));
    g.lineTo(x(pts.length - 1), y(0)); g.lineTo(x(0), y(0)); g.closePath();
    g.fillStyle = 'rgba(27,117,188,.13)'; g.fill();

    g.beginPath(); g.moveTo(x(0), y(pts[0].films));
    pts.forEach((p, i) => g.lineTo(x(i), y(p.films)));
    g.strokeStyle = '#1B75BC'; g.lineWidth = 2.2; g.stroke();

    // dates aux extrémités et au point de rupture
    g.fillStyle = '#64748b'; g.font = '10.5px system-ui';
    const fmt = d => new Date(d).toLocaleDateString('fr-FR', {day:'2-digit', month:'short'});
    g.textAlign = 'left';   g.fillText(fmt(pts[0].date), padL, h - 8);
    g.textAlign = 'right';  g.fillText(fmt(pts[pts.length-1].date), w - padR, h - 8);

    const rupture = pts.findIndex(p => p.films === 0);
    if (rupture > 0){
      g.strokeStyle = '#c0392b'; g.setLineDash([4,3]); g.lineWidth = 1.4;
      g.beginPath(); g.moveTo(x(rupture), padT); g.lineTo(x(rupture), padT + ch); g.stroke();
      g.setLineDash([]);
      g.fillStyle = '#c0392b'; g.textAlign = 'center';
      g.fillText('rupture', x(rupture), padT + 10);
    }
  }
  dessiner();
  window.addEventListener('resize', dessiner);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
