<?php
// ============================================================
//  pages/pdg_overview.php — Vue exécutive PDG (lecteur)
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
$user = current_user();

$page_title  = 'Vue PDG';
$active_page = 'pdg_overview';

// ── Période
$mois  = trim($_GET['mois'] ?? date('Y-m'));
$annee = substr($mois, 0, 4);

// ── OPÉRATIONS ─────────────────────────────────────────────
$ops = db_fetch_one(
    "SELECT
        COUNT(*) AS total_points,
        SUM(CASE WHEN statut='valide' THEN 1 ELSE 0 END) AS points_valides,
        SUM(CASE WHEN statut='en_attente_validation' THEN 1 ELSE 0 END) AS points_attente,
        SUM(CASE WHEN statut='rejete' THEN 1 ELSE 0 END) AS points_rejetes,
        COALESCE(SUM(total_engins),0) AS total_engins,
        COALESCE(SUM(total_plaques),0) AS total_plaques,
        COALESCE(SUM(rivets_utilises),0) AS rivets_utilises,
        COALESCE(ROUND(AVG(NULLIF(moyenne_prod,0)),1),0) AS moy_prod
     FROM op_points_journaliers
     WHERE DATE_FORMAT(date_point,'%Y-%m')=? AND statut != 'brouillon'",
    [$mois]
);

// Productions par site ce mois
$prod_par_site = db_fetch_all(
    "SELECT s.nom, s.type,
            COUNT(p.id) AS nb_points,
            COALESCE(SUM(p.total_engins),0) AS engins,
            COALESCE(SUM(p.total_plaques),0) AS plaques,
            COALESCE(ROUND(AVG(NULLIF(p.moyenne_prod,0)),1),0) AS moy_vh,
            SUM(CASE WHEN p.statut='en_attente_validation' THEN 1 ELSE 0 END) AS en_attente
     FROM sites s
     LEFT JOIN op_points_journaliers p ON p.site_id=s.id AND DATE_FORMAT(p.date_point,'%Y-%m')=? AND p.statut != 'brouillon'
     WHERE s.actif=1
     GROUP BY s.id ORDER BY engins DESC",
    [$mois]
);

// ── STOCK RIVETS ────────────────────────────────────────────
$rivets = db_fetch_all(
    "SELECT s.nom, sr.type_rivet, COALESCE(sr.quantite,0) AS quantite
     FROM sites s
     JOIN op_stock_rivets sr ON sr.site_id=s.id
     WHERE s.actif=1 ORDER BY s.nom, FIELD(sr.type_rivet,'gonflable','eclate')"
);

// ── BOBINES ─────────────────────────────────────────────────
$bobines_stats = db_fetch_one(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN statut='en_cours' THEN 1 ELSE 0 END) AS en_cours,
        SUM(CASE WHEN statut='en_stock' THEN 1 ELSE 0 END) AS en_stock,
        SUM(CASE WHEN statut='termine' AND DATE_FORMAT(updated_at,'%Y-%m')=? THEN 1 ELSE 0 END) AS terminees_mois,
        COALESCE(SUM(films_restants),0) AS films_restants
     FROM op_bobines",
    [$mois]
);

$films_mois = (int)db_fetch_value(
    "SELECT COALESCE(SUM(fu.films_utilises),0)
     FROM op_films_utilises fu
     JOIN op_points_journaliers p ON p.id=fu.point_id
     WHERE DATE_FORMAT(p.date_point,'%Y-%m')=?",
    [$mois]
);

// ── COMMANDES ───────────────────────────────────────────────
$cmd_stats = db_fetch_one(
    "SELECT
        SUM(CASE WHEN statut='en_attente' THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut='en_attente_livraison' THEN 1 ELSE 0 END) AS a_livrer,
        SUM(CASE WHEN statut='en_cours_livraison' THEN 1 ELSE 0 END) AS en_route,
        SUM(CASE WHEN statut='livre' AND DATE_FORMAT(created_at,'%Y-%m')=? THEN 1 ELSE 0 END) AS livrees_mois
     FROM commandes",
    [$mois]
);

// ── ALERTES ─────────────────────────────────────────────────
$alertes_stock = (int)db_fetch_value(
    "SELECT COUNT(*) FROM articles WHERE stock_global <= seuil_alerte AND seuil_alerte > 0"
);
$rivets_bas = (int)db_fetch_value(
    "SELECT COUNT(*) FROM op_stock_rivets WHERE quantite < 200"
);
$points_attente = (int)($ops['points_attente'] ?? 0);
$cmd_en_attente = (int)($cmd_stats['en_attente'] ?? 0);

// ── ÉVOLUTION MENSUELLE (6 derniers mois) ───────────────────
$evol = db_fetch_all(
    "SELECT DATE_FORMAT(date_point,'%Y-%m') AS mois,
            SUM(total_engins) AS engins,
            COUNT(*) AS points
     FROM op_points_journaliers
     WHERE date_point >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND statut != 'brouillon'
     GROUP BY DATE_FORMAT(date_point,'%Y-%m') ORDER BY mois ASC"
);

include __DIR__ . '/../templates/header.php';
?>

<style>
.pdg-kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px}
.pdg-kpi{background:white;border:1px solid var(--border);border-radius:14px;padding:18px 16px;position:relative;overflow:hidden}
.pdg-kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;border-radius:14px 14px 0 0}
.pdg-kpi.blue::before{background:#1b75bc}
.pdg-kpi.green::before{background:#27ae60}
.pdg-kpi.orange::before{background:#e67e22}
.pdg-kpi.red::before{background:#e74c3c}
.pdg-kpi.purple::before{background:#8e44ad}
.pdg-kpi.navy::before{background:#06033a}
.pdg-kpi-val{font-family:'Montserrat',sans-serif;font-size:28px;font-weight:900;color:var(--navy);line-height:1;margin-bottom:4px}
.pdg-kpi-lbl{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.pdg-kpi-sub{font-size:11px;color:var(--muted);margin-top:4px}
.pdg-kpi-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:28px;opacity:.12}

.pdg-section{background:white;border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:20px}
.pdg-section-title{font-family:'Montserrat',sans-serif;font-size:13px;font-weight:800;color:var(--navy);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.pdg-section-title span{font-family:'DM Sans',sans-serif;font-size:11px;font-weight:400;color:var(--muted)}

.alerte-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600}
.alerte-chip.warn{background:#fff3e0;color:#c2410c}
.alerte-chip.ok{background:#f0fdf4;color:#166534}
.alerte-chip.info{background:#eff6ff;color:#1e40af}

.site-row{display:grid;grid-template-columns:180px 1fr 70px 70px 70px 60px 80px;gap:8px;align-items:center;padding:10px 12px;border-radius:8px;font-size:13px}
.site-row:nth-child(even){background:#f8fafc}
.site-row-hdr{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;padding:6px 12px}

.pill{display:inline-block;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700}
.pill-green{background:#d1fae5;color:#065f46}
.pill-orange{background:#fff7ed;color:#c2410c}
.pill-blue{background:#dbeafe;color:#1e40af}
.pill-red{background:#fee2e2;color:#991b1b}

.bar-wrap{background:#f1f5f9;border-radius:4px;height:8px;overflow:hidden;flex:1}
.bar-fill{height:100%;border-radius:4px;background:#1b75bc}

.rivet-site-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}
.rivet-site-card{background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:14px}
.rivet-site-name{font-size:12px;font-weight:700;color:var(--navy);margin-bottom:8px}
.rivet-row{display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:4px}
</style>

<!-- Filtre mois -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <div style="font-family:'Montserrat',sans-serif;font-size:20px;font-weight:900;color:var(--navy)">Vue Exécutive</div>
    <div style="font-size:13px;color:var(--muted)">Synthèse globale de l'activité DigiStock</div>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <?php if($points_attente+$cmd_en_attente+$alertes_stock+$rivets_bas > 0): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if($points_attente>0): ?><span class="alerte-chip warn">⏳ <?= $points_attente ?> point(s) à valider</span><?php endif; ?>
      <?php if($cmd_en_attente>0): ?><span class="alerte-chip warn">📦 <?= $cmd_en_attente ?> commande(s) en attente</span><?php endif; ?>
      <?php if($alertes_stock>0): ?><span class="alerte-chip warn">⚠️ <?= $alertes_stock ?> stock(s) bas</span><?php endif; ?>
      <?php if($rivets_bas>0): ?><span class="alerte-chip warn">🔩 <?= $rivets_bas ?> stock(s) rivets bas</span><?php endif; ?>
    </div>
    <?php else: ?>
    <span class="alerte-chip ok">✅ Aucune alerte</span>
    <?php endif; ?>
    <form method="get" style="display:flex;align-items:center;gap:6px">
      <label style="font-size:12px;color:var(--muted);font-weight:600">Mois</label>
      <input type="month" name="mois" value="<?= h($mois) ?>" class="form-control" style="width:160px;font-size:12px;padding:6px 10px" onchange="this.form.submit()">
    </form>
  </div>
</div>

<!-- KPIs principaux -->
<div class="pdg-kpi-grid">
  <div class="pdg-kpi blue">
    <div class="pdg-kpi-val"><?= fmt_number($ops['total_engins'] ?? 0) ?></div>
    <div class="pdg-kpi-lbl">Engins posés</div>
    <div class="pdg-kpi-sub"><?= h($mois) ?></div>
    <div class="pdg-kpi-icon">🚗</div>
  </div>
  <div class="pdg-kpi navy">
    <div class="pdg-kpi-val"><?= fmt_number($ops['total_plaques'] ?? 0) ?></div>
    <div class="pdg-kpi-lbl">Plaques posées</div>
    <div class="pdg-kpi-sub">ce mois</div>
    <div class="pdg-kpi-icon">🪪</div>
  </div>
  <div class="pdg-kpi green">
    <div class="pdg-kpi-val"><?= $ops['moy_prod'] ?? '0' ?></div>
    <div class="pdg-kpi-lbl">Moy. V/H</div>
    <div class="pdg-kpi-sub">véhicules / heure</div>
    <div class="pdg-kpi-icon">⚡</div>
  </div>
  <div class="pdg-kpi blue">
    <div class="pdg-kpi-val"><?= $ops['points_valides'] ?? 0 ?></div>
    <div class="pdg-kpi-lbl">Points validés</div>
    <div class="pdg-kpi-sub">sur <?= $ops['total_points'] ?? 0 ?> total</div>
    <div class="pdg-kpi-icon">✅</div>
  </div>
  <div class="pdg-kpi orange">
    <div class="pdg-kpi-val"><?= fmt_number($ops['rivets_utilises'] ?? 0) ?></div>
    <div class="pdg-kpi-lbl">Rivets posés</div>
    <div class="pdg-kpi-sub">ce mois</div>
    <div class="pdg-kpi-icon">🔩</div>
  </div>
  <div class="pdg-kpi purple">
    <div class="pdg-kpi-val"><?= fmt_number($films_mois) ?></div>
    <div class="pdg-kpi-lbl">Films utilisés</div>
    <div class="pdg-kpi-sub">ce mois</div>
    <div class="pdg-kpi-icon">🎞️</div>
  </div>
  <div class="pdg-kpi <?= $bobines_stats['en_cours']>0?'blue':'green' ?>">
    <div class="pdg-kpi-val"><?= $bobines_stats['en_cours'] ?? 0 ?></div>
    <div class="pdg-kpi-lbl">Bobines actives</div>
    <div class="pdg-kpi-sub"><?= $bobines_stats['en_stock'] ?? 0 ?> en stock</div>
    <div class="pdg-kpi-icon">🎞️</div>
  </div>
  <div class="pdg-kpi <?= ($cmd_stats['en_attente']??0)>0?'orange':'green' ?>">
    <div class="pdg-kpi-val"><?= ($cmd_stats['en_attente']??0) + ($cmd_stats['a_livrer']??0) + ($cmd_stats['en_route']??0) ?></div>
    <div class="pdg-kpi-lbl">Commandes actives</div>
    <div class="pdg-kpi-sub"><?= $cmd_stats['livrees_mois']??0 ?> livrées ce mois</div>
    <div class="pdg-kpi-icon">📦</div>
  </div>
</div>

<!-- Production par site -->
<div class="pdg-section">
  <div class="pdg-section-title">🏭 Production par site <span>— <?= h($mois) ?></span></div>
  <div class="site-row site-row-hdr">
    <div>Site</div>
    <div>Progression</div>
    <div style="text-align:center">Engins</div>
    <div style="text-align:center">Plaques</div>
    <div style="text-align:center">Moy V/H</div>
    <div style="text-align:center">Points</div>
    <div style="text-align:center">Statut</div>
  </div>
  <?php
  $max_engins = max(1, ...array_column($prod_par_site, 'engins'));
  foreach ($prod_par_site as $s):
    $pct = round($s['engins'] / $max_engins * 100);
  ?>
  <div class="site-row">
    <div>
      <div style="font-weight:700;font-size:13px;color:var(--navy)"><?= h($s['nom']) ?></div>
      <div style="font-size:10px;color:var(--muted)"><?= h($s['type']??'') ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
      <span style="font-size:11px;color:var(--muted);white-space:nowrap"><?= $pct ?>%</span>
    </div>
    <div style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:800;color:var(--navy)"><?= fmt_number($s['engins']) ?></div>
    <div style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:700;color:#1565c0"><?= fmt_number($s['plaques']) ?></div>
    <div style="text-align:center;font-weight:700;color:<?= $s['moy_vh']>=10?'#27ae60':($s['moy_vh']>=5?'#e67e22':'#e74c3c') ?>"><?= $s['moy_vh'] ?></div>
    <div style="text-align:center;color:var(--muted)"><?= $s['nb_points'] ?></div>
    <div style="text-align:center">
      <?php if($s['en_attente']>0): ?>
        <span class="pill pill-orange">⏳ <?= $s['en_attente'] ?> att.</span>
      <?php elseif($s['nb_points']>0): ?>
        <span class="pill pill-green">✅ OK</span>
      <?php else: ?>
        <span class="pill pill-blue">— aucun</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

  <!-- Commandes -->
  <div class="pdg-section" style="margin-bottom:0">
    <div class="pdg-section-title">📦 Commandes articles</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <?php foreach([
        ['⏳','En attente',$cmd_stats['en_attente']??0,'orange'],
        ['🚛','En livraison',($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0),'blue'],
        ['✅','Livrées (mois)',$cmd_stats['livrees_mois']??0,'green'],
      ] as [$ic,$lb,$v,$col]): ?>
      <div style="background:#f8fafc;border-radius:10px;padding:14px;text-align:center;border-left:3px solid <?= $col==='orange'?'#e67e22':($col==='blue'?'#1b75bc':'#27ae60') ?>">
        <div style="font-size:22px"><?= $ic ?></div>
        <div style="font-family:'Montserrat',sans-serif;font-size:22px;font-weight:900;color:var(--navy)"><?= $v ?></div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;font-weight:600"><?= $lb ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Bobines -->
  <div class="pdg-section" style="margin-bottom:0">
    <div class="pdg-section-title">🎞️ Bobines & Films</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <?php foreach([
        ['🟢','En cours',$bobines_stats['en_cours']??0,'green'],
        ['📦','En stock',$bobines_stats['en_stock']??0,'blue'],
        ['🎬','Films restants',fmt_number($bobines_stats['films_restants']??0),'navy'],
        ['✅','Terminées mois',$bobines_stats['terminees_mois']??0,'orange'],
      ] as [$ic,$lb,$v,$col]): ?>
      <div style="background:#f8fafc;border-radius:10px;padding:12px;text-align:center">
        <div style="font-size:18px"><?= $ic ?></div>
        <div style="font-family:'Montserrat',sans-serif;font-size:20px;font-weight:900;color:var(--navy)"><?= $v ?></div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;font-weight:600"><?= $lb ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- Stock Rivets par site -->
<div class="pdg-section">
  <div class="pdg-section-title">🔩 Stock rivets par site</div>
  <div class="rivet-site-grid">
    <?php
    $rivets_par_site = [];
    foreach ($rivets as $r) {
        $rivets_par_site[$r['nom']][$r['type_rivet']] = (int)$r['quantite'];
    }
    foreach ($rivets_par_site as $nom => $types):
      $gonfl  = $types['gonflable'] ?? 0;
      $eclate = $types['eclate']    ?? 0;
      $total  = $gonfl + $eclate;
      $col    = $total < 400 ? '#dc2626' : ($total < 2000 ? '#d97706' : '#16a34a');
    ?>
    <div class="rivet-site-card">
      <div class="rivet-site-name"><?= h($nom) ?></div>
      <div class="rivet-row">
        <span style="color:var(--muted)">🔵 Gonflables</span>
        <span style="font-weight:700;color:#1565c0"><?= fmt_number($gonfl) ?></span>
      </div>
      <div class="rivet-row">
        <span style="color:var(--muted)">🔴 Éclatés</span>
        <span style="font-weight:700;color:#880e4f"><?= fmt_number($eclate) ?></span>
      </div>
      <div style="margin-top:6px;padding-top:6px;border-top:1px solid var(--border);display:flex;justify-content:space-between">
        <span style="font-size:11px;color:var(--muted)">Total</span>
        <span style="font-family:'Montserrat',sans-serif;font-weight:900;font-size:14px;color:<?= $col ?>"><?= fmt_number($total) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Points en attente de validation -->
<?php
$pts_attente = db_fetch_all(
    "SELECT p.date_point, p.type_point, s.nom AS site, CONCAT(u.prenom,' ',u.nom) AS coord
     FROM op_points_journaliers p
     JOIN sites s ON s.id=p.site_id
     LEFT JOIN users u ON u.id=p.created_by
     WHERE p.statut='en_attente_validation'
     ORDER BY p.date_point DESC LIMIT 10"
);
if (!empty($pts_attente)):
?>
<div class="pdg-section">
  <div class="pdg-section-title">⏳ Points en attente de validation</div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr>
      <th style="text-align:left;padding:6px 10px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Site</th>
      <th style="text-align:left;padding:6px 10px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Date</th>
      <th style="text-align:left;padding:6px 10px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Type</th>
      <th style="text-align:left;padding:6px 10px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Coordinateur</th>
    </tr></thead>
    <tbody>
    <?php foreach($pts_attente as $pt): ?>
    <tr>
      <td style="padding:8px 10px;font-weight:600;border-bottom:1px solid var(--border)"><?= h($pt['site']) ?></td>
      <td style="padding:8px 10px;border-bottom:1px solid var(--border)"><?= fmt_date($pt['date_point']) ?></td>
      <td style="padding:8px 10px;border-bottom:1px solid var(--border)">
        <span class="pill pill-orange"><?= ['point_9h'=>'9h','point_13h'=>'13h','point_18h'=>'18h'][$pt['type_point']] ?? $pt['type_point'] ?></span>
      </td>
      <td style="padding:8px 10px;color:var(--muted);border-bottom:1px solid var(--border)"><?= h($pt['coord']??'—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
