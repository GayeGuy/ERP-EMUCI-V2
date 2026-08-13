<?php
// ============================================================
//  pages/rapports.php  —  Rapports & analyses
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
require_permission('rapports', 'can_read');

$user        = current_user();
$page_title  = 'Rapports & Analyses';
$active_page = 'rapports';

// ── PÉRIODE
$annee     = (int)($_GET['annee'] ?? date('Y'));
$mois      = (int)($_GET['mois']  ?? 0);   // 0 = toute l'année
$f_site    = (int)($_GET['site']  ?? 0);

// Filtre date
$date_debut = $mois ? sprintf('%04d-%02d-01', $annee, $mois) : "$annee-01-01";
$date_fin   = $mois ? date('Y-m-t', strtotime($date_debut))  : "$annee-12-31";

// ── KPIs GLOBAUX
$kpi = [
    'total_equip'    => (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1"),
    'total_sites'    => (int)db_fetch_value("SELECT COUNT(*) FROM sites WHERE actif=1"),
    'total_users'    => (int)db_fetch_value("SELECT COUNT(*) FROM users WHERE actif=1"),
    'equip_hs'       => (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat='hs'"),
    'equip_neuf'     => (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat='neuf'"),
    'fin_cycle_30j'  => (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND date_fin_cycle BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL '30 DAY')"),
    'fin_cycle_exp'  => (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND date_fin_cycle < CURRENT_DATE"),
    'conso_alertes'  => (int)db_fetch_value("SELECT COUNT(*) FROM consommables WHERE stock_global<=seuil_alerte"),
];

// ── CONSOMMATION PAR PÉRIODE & SITE
$conso_where  = $f_site ? "AND lc.site_id=$f_site" : '';
$conso_global = db_fetch_all(
    "SELECT c.code, c.libelle, c.unite,
            COALESCE(SUM(lc.quantite),0) AS total_periode,
            COALESCE(SUM(CASE WHEN EXTRACT(MONTH FROM lc.date_livraison)=EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM lc.date_livraison)=EXTRACT(YEAR FROM CURRENT_DATE) THEN lc.quantite ELSE 0 END),0) AS total_mois
     FROM consommables c
     LEFT JOIN livraisons_consommables lc ON lc.consommable_id=c.id
         AND lc.date_livraison BETWEEN ? AND ? $conso_where
     GROUP BY c.id ORDER BY total_periode DESC",
    [$date_debut, $date_fin]
);

// ── CONSOMMATION MENSUELLE (12 mois glissants)
$conso_mensuelle_12 = db_fetch_all(
    "SELECT TO_CHAR(date_livraison,'YYYY-MM') AS mois,
            COALESCE(SUM(quantite),0) AS total
     FROM livraisons_consommables
     WHERE date_livraison >= (CURRENT_DATE - INTERVAL '12 MONTH')
     GROUP BY mois ORDER BY mois ASC"
);

// ── CONSOMMATION PAR SITE
$conso_par_site = db_fetch_all(
    "SELECT s.nom AS site_nom, s.type,
            COALESCE(SUM(lc.quantite),0) AS total
     FROM sites s
     LEFT JOIN livraisons_consommables lc ON lc.site_id=s.id
         AND lc.date_livraison BETWEEN ? AND ?
     WHERE s.actif=1 GROUP BY s.id ORDER BY total DESC",
    [$date_debut, $date_fin]
);

// ── CONSOMMATION PAR CONSOMMABLE PAR SITE (matrice)
$matrice_consos = db_fetch_all(
    "SELECT c.libelle AS conso, s.nom AS site, SUM(lc.quantite) AS total
     FROM livraisons_consommables lc
     JOIN consommables c ON c.id=lc.consommable_id
     JOIN sites s ON s.id=lc.site_id
     WHERE lc.date_livraison BETWEEN ? AND ?
     GROUP BY c.id, s.id ORDER BY c.libelle, total DESC",
    [$date_debut, $date_fin]
);

// ── ÉQUIPEMENTS : état par site
$equip_etat_site = db_fetch_all(
    "SELECT s.nom AS site,
            SUM(CASE WHEN e.etat='neuf'    THEN 1 ELSE 0 END) AS neuf,
            SUM(CASE WHEN e.etat='bon'     THEN 1 ELSE 0 END) AS bon,
            SUM(CASE WHEN e.etat='usage'   THEN 1 ELSE 0 END) AS usage_count,
            SUM(CASE WHEN e.etat='hs'      THEN 1 ELSE 0 END) AS hs,
            COUNT(e.id) AS total
     FROM sites s
     LEFT JOIN equipements e ON e.site_id=s.id AND e.actif=1
     WHERE s.actif=1 GROUP BY s.id ORDER BY total DESC"
);

// ── MOUVEMENTS PAR MOIS
$mouvements_mois = db_fetch_all(
    "SELECT TO_CHAR(created_at,'YYYY-MM') AS mois, type, COUNT(*) AS nb
     FROM mouvements_equipements
     WHERE created_at >= (CURRENT_DATE - INTERVAL '12 MONTH')
     GROUP BY mois, type ORDER BY mois ASC"
);

// ── TOP ÉQUIPEMENTS PROCHES FIN DE CYCLE
$fin_cycle_soon = db_fetch_all(
    "SELECT e.numero_serie_interne, COALESCE(n.libelle,'—') AS type, s.nom AS site,
            e.date_fin_cycle,
            ((e.date_fin_cycle)::date - (CURRENT_DATE)::date) AS jours
     FROM equipements e
     LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id
     LEFT JOIN sites s ON s.id=e.site_id
     WHERE e.actif=1 AND e.date_fin_cycle IS NOT NULL
       AND e.date_fin_cycle <= (CURRENT_DATE + INTERVAL '90 DAY')
     ORDER BY e.date_fin_cycle ASC LIMIT 15"
);

// ── ACTIVITÉ PAR UTILISATEUR (audit)
$activite_users = db_fetch_all(
    "SELECT CONCAT(u.prenom,' ',u.nom) AS nom, r.nom AS role,
            COUNT(al.id) AS nb_actions,
            MAX(al.created_at) AS derniere_action
     FROM users u
     LEFT JOIN audit_log al ON al.user_id=u.id
         AND al.created_at BETWEEN ? AND ?
     JOIN roles r ON r.id=u.role_id
     WHERE u.actif=1
     GROUP BY u.id, r.nom ORDER BY nb_actions DESC LIMIT 10",
    [$date_debut.' 00:00:00', $date_fin.' 23:59:59']
);

// ── ANNÉES DISPONIBLES
$annees_dispo = db_fetch_all("SELECT DISTINCT EXTRACT(YEAR FROM date_livraison) AS a FROM livraisons_consommables ORDER BY a DESC");

// ============================================================
//  NOUVELLES REQUÊTES : COÛTS EN FCFA PAR SITE
// ============================================================

// ── SEMAINE EN COURS (lundi→dimanche)
$debut_semaine_courante = date('Y-m-d', strtotime('monday this week'));
$fin_semaine_courante   = date('Y-m-d', strtotime('sunday this week'));

// ── SEMAINE PRÉCÉDENTE
$debut_semaine_prec = date('Y-m-d', strtotime('monday last week'));
$fin_semaine_prec   = date('Y-m-d', strtotime('sunday last week'));

// ── KPI COÛT TOTAL FCFA sur la période sélectionnée (tous sites)
$kpi_cout_periode   = (float)db_fetch_value(
    "SELECT COALESCE(SUM(prix_total),0) FROM livraisons_consommables WHERE date_livraison BETWEEN ? AND ?",
    [$date_debut, $date_fin]
);
$kpi_cout_mois_cur  = (float)db_fetch_value(
    "SELECT COALESCE(SUM(prix_total),0) FROM livraisons_consommables WHERE EXTRACT(YEAR FROM date_livraison)=EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM date_livraison)=EXTRACT(MONTH FROM CURRENT_DATE)"
);
$kpi_cout_semaine   = (float)db_fetch_value(
    "SELECT COALESCE(SUM(prix_total),0) FROM livraisons_consommables WHERE date_livraison BETWEEN ? AND ?",
    [$debut_semaine_courante, $fin_semaine_courante]
);
$kpi_cout_semaine_prec = (float)db_fetch_value(
    "SELECT COALESCE(SUM(prix_total),0) FROM livraisons_consommables WHERE date_livraison BETWEEN ? AND ?",
    [$debut_semaine_prec, $fin_semaine_prec]
);

// ── COÛT PAR SITE — période sélectionnée (avec détail par article)
$csite_where = $f_site ? "AND lc.site_id=$f_site" : '';
$cout_par_site = db_fetch_all(
    "SELECT s.id AS site_id, s.nom AS site_nom, s.type AS site_type,
            COALESCE(SUM(lc.prix_total),0)   AS cout_total,
            COALESCE(SUM(lc.quantite),0)     AS qte_total,
            COUNT(DISTINCT lc.consommable_id) AS nb_articles
     FROM sites s
     LEFT JOIN livraisons_consommables lc ON lc.site_id=s.id
         AND lc.date_livraison BETWEEN ? AND ? $csite_where
         AND lc.prix_total > 0
     WHERE s.actif=1
     GROUP BY s.id ORDER BY cout_total DESC",
    [$date_debut, $date_fin]
);

// ── COÛT PAR SITE CETTE SEMAINE
$cout_hebdo_site = db_fetch_all(
    "SELECT s.nom AS site_nom,
            COALESCE(SUM(lc.prix_total),0) AS cout_semaine,
            COALESCE(SUM(lc.quantite),0)   AS qte_semaine
     FROM sites s
     LEFT JOIN livraisons_consommables lc ON lc.site_id=s.id
         AND lc.date_livraison BETWEEN ? AND ?
         AND lc.prix_total > 0
     WHERE s.actif=1
     GROUP BY s.id ORDER BY cout_semaine DESC",
    [$debut_semaine_courante, $fin_semaine_courante]
);

// ── DÉTAIL ARTICLE×SITE pour la période (top articles par coût)
$detail_article_site = db_fetch_all(
    "SELECT c.code, c.libelle AS article, c.unite, s.nom AS site_nom,
            COALESCE(SUM(lc.quantite),0)   AS qte,
            COALESCE(AVG(lc.prix_unitaire),0) AS prix_unit_moy,
            COALESCE(SUM(lc.prix_total),0) AS cout_total
     FROM livraisons_consommables lc
     JOIN consommables c ON c.id=lc.consommable_id
     JOIN sites s ON s.id=lc.site_id
     WHERE lc.date_livraison BETWEEN ? AND ?
       AND lc.prix_total > 0 $csite_where
     GROUP BY c.id, s.id
     ORDER BY cout_total DESC
     LIMIT 60",
    [$date_debut, $date_fin]
);

// ── ÉVOLUTION MENSUELLE DES COÛTS PAR SITE (12 mois glissants)
$evolution_cout_sites = db_fetch_all(
    "SELECT TO_CHAR(lc.date_livraison,'YYYY-MM') AS mois,
            s.nom AS site_nom,
            COALESCE(SUM(lc.prix_total),0) AS cout
     FROM livraisons_consommables lc
     JOIN sites s ON s.id=lc.site_id
     WHERE lc.date_livraison >= (CURRENT_DATE - INTERVAL '12 MONTH')
       AND lc.prix_total > 0
     GROUP BY mois, s.id
     ORDER BY mois ASC, cout DESC"
);

// ── BILAN FIN DE MOIS — mois en cours, par site, par article
$bilan_mois_cur = db_fetch_all(
    "SELECT s.nom AS site_nom,
            c.libelle AS article, c.code, c.unite,
            COALESCE(SUM(lc.quantite),0)      AS qte,
            COALESCE(AVG(lc.prix_unitaire),0) AS prix_unit_moy,
            COALESCE(SUM(lc.prix_total),0)    AS cout_total
     FROM livraisons_consommables lc
     JOIN sites s ON s.id=lc.site_id
     JOIN consommables c ON c.id=lc.consommable_id
     WHERE EXTRACT(YEAR FROM lc.date_livraison)=EXTRACT(YEAR FROM CURRENT_DATE)
       AND EXTRACT(MONTH FROM lc.date_livraison)=EXTRACT(MONTH FROM CURRENT_DATE)
       AND lc.prix_total > 0
     GROUP BY s.id, c.id
     ORDER BY s.nom, cout_total DESC"
);

// Regrouper le bilan par site (PHP)
$bilan_par_site = [];
foreach ($bilan_mois_cur as $row) {
    $sn = $row['site_nom'];
    if (!isset($bilan_par_site[$sn])) {
        $bilan_par_site[$sn] = ['total' => 0, 'articles' => []];
    }
    $bilan_par_site[$sn]['total'] += $row['cout_total'];
    $bilan_par_site[$sn]['articles'][] = $row;
}
arsort($bilan_par_site); // trier par coût décroissant

// ── PRÉPARER DONNÉES POUR LE GRAPHE ÉVOLUTION COÛTS (JS)
$mois_set  = [];
$sites_set = [];
foreach ($evolution_cout_sites as $r) {
    $mois_set[$r['mois']]      = true;
    $sites_set[$r['site_nom']] = true;
}
$mois_set  = array_keys($mois_set);
$sites_set = array_keys($sites_set);

// Matrice [site][mois] = coût
$cout_matrix = [];
foreach ($sites_set as $sn) $cout_matrix[$sn] = array_fill_keys($mois_set, 0);
foreach ($evolution_cout_sites as $r) $cout_matrix[$r['site_nom']][$r['mois']] = (float)$r['cout'];

$palette_cout = ['#0d1f35','#1a3c5e','#1a56a0','#27ae60','#e74c3c','#8e44ad','#16a085','#f39c12','#2980b9','#d35400'];

// Préparer JSON pour charts
$m12_labels = json_encode(array_column($conso_mensuelle_12,'mois'));
$m12_values = json_encode(array_column($conso_mensuelle_12,'total'));
$site_labels = json_encode(array_column($conso_par_site,'site_nom'));
$site_values = json_encode(array_column($conso_par_site,'total'));

// JSON pour graphe coût par site (période)
$cout_site_labels = json_encode(array_column($cout_par_site,'site_nom'));
$cout_site_values = json_encode(array_map(fn($r)=>(float)$r['cout_total'], $cout_par_site));

// JSON pour graphe évolution coûts multi-sites
$evolution_labels = json_encode($mois_set);
$evolution_datasets = [];
foreach ($sites_set as $i => $sn) {
    $evolution_datasets[] = [
        'label'           => $sn,
        'data'            => array_values($cout_matrix[$sn]),
        'borderColor'     => $palette_cout[$i % count($palette_cout)],
        'backgroundColor' => $palette_cout[$i % count($palette_cout)].'22',
        'borderWidth'     => 2,
        'tension'         => 0.3,
        'fill'            => false,
        'pointRadius'     => 3,
    ];
}
$evolution_datasets_json = json_encode($evolution_datasets);

$sites_list = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");

include __DIR__ . '/../templates/header.php';
?>
<style>
.rapport-kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.rkpi{background:white;border:1px solid var(--border);border-radius:12px;padding:16px 18px;text-align:center}
.rkpi .rv{font-family:'Montserrat',sans-serif;font-size:28px;font-weight:800;color:var(--navy)}
.rkpi .rl{font-size:11.5px;color:var(--muted);margin-top:4px}
.rkpi.danger .rv{color:var(--danger)}
.rkpi.warning .rv{color:var(--warning)}
.rkpi.success .rv{color:var(--success)}

.rapport-grid{display:grid;gap:20px;margin-bottom:20px}
.rapport-grid.c2{grid-template-columns:1fr 1fr}
.rapport-grid.c32{grid-template-columns:2fr 1fr}
.rapport-grid.c23{grid-template-columns:1fr 2fr}

.r-card{background:white;border:1px solid var(--border);border-radius:14px;overflow:hidden}
.r-card-hdr{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.r-card-hdr h3{font-family:'Montserrat',sans-serif;font-size:14px;font-weight:700}
.r-card-body{padding:18px}

/* TABLE MATRICE */
.matrice-table{font-size:12px}
.matrice-table th{background:var(--navy);color:white;padding:7px 10px;font-size:10px;white-space:nowrap}
.matrice-table td{padding:6px 10px;border:1px solid var(--border);text-align:right}
.matrice-table td.site-name{text-align:left;font-weight:500;background:var(--lighter)}

/* FIN CYCLE */
.fc-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:12.5px}
.fc-row:last-child{border-bottom:none}
.fc-row-wide{padding:12px 0;font-size:13.5px}
.fc-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;flex-shrink:0}
.fc-badge.exp{background:#fdf0ef;color:var(--danger)}
.fc-badge.soon{background:#fef9e7;color:var(--warning)}
.fc-badge.ok{background:#eafaf1;color:var(--success)}

/* ── COÛTS FCFA ── */
.cout-kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.cout-kpi{background:white;border:1px solid var(--border);border-radius:12px;padding:14px 16px;position:relative;overflow:hidden}
.cout-kpi::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;background:var(--blue-mid,#1a56a0)}
.cout-kpi.green::before{background:var(--success,#27ae60)}
.cout-kpi.orange::before{background:var(--warning,#f39c12)}
.cout-kpi.red::before{background:var(--danger,#e74c3c)}
.cout-kpi .ck-label{font-size:11px;color:var(--muted);margin-bottom:4px;font-weight:500;text-transform:uppercase;letter-spacing:.4px}
.cout-kpi .ck-val{font-family:'Montserrat',sans-serif;font-size:20px;font-weight:800;color:var(--navy);line-height:1.1}
.cout-kpi .ck-sub{font-size:11px;color:var(--muted);margin-top:4px}

.site-cout-bar{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
.site-cout-bar:last-child{border-bottom:none}
.site-cout-bar .scb-name{min-width:130px;font-weight:600;color:var(--navy)}
.site-cout-bar .scb-bar{flex:1;height:8px;background:var(--border);border-radius:4px;overflow:hidden}
.site-cout-bar .scb-fill{height:100%;border-radius:4px;background:var(--blue-mid,#1a56a0)}
.site-cout-bar .scb-val{min-width:110px;text-align:right;font-family:'Montserrat',sans-serif;font-weight:700;font-size:13px;color:var(--navy)}
.site-cout-bar .scb-pct{min-width:40px;text-align:right;font-size:11px;color:var(--muted)}

.bilan-section{margin-bottom:16px}
.bilan-site-hdr{display:flex;justify-content:space-between;align-items:center;background:var(--navy);color:white;padding:9px 14px;border-radius:8px 8px 0 0;font-size:13px;font-weight:700;cursor:pointer}
.bilan-site-hdr .bsh-total{font-family:'Montserrat',sans-serif;font-size:14px;font-weight:800}
.bilan-site-body{border:1px solid var(--border);border-top:none;border-radius:0 0 8px 8px;overflow:hidden}
.bilan-site-body table{width:100%;border-collapse:collapse;font-size:12.5px}
.bilan-site-body thead tr{background:var(--lighter,#f8fafc)}
.bilan-site-body th{padding:7px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);border-bottom:1px solid var(--border)}
.bilan-site-body td{padding:7px 12px;border-bottom:1px solid var(--border)}
.bilan-site-body tr:last-child td{border-bottom:none}
.bilan-site-body tr:hover{background:var(--lighter,#f8fafc)}
.bilan-tfoot td{background:var(--lighter);font-weight:700;font-family:'Montserrat',sans-serif}

/* FILTRES */
.filter-bar{background:white;border:1px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.filter-bar label{font-size:13px;font-weight:500;color:var(--text)}
.fsel{padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:white;cursor:pointer;outline:none;font-family:'DM Sans',sans-serif}
.fsel:focus{border-color:var(--blue-mid, #1a56a0)}

@media(max-width:1000px){
  .rapport-kpi{grid-template-columns:repeat(2,1fr)}
  .rapport-grid.c2,.rapport-grid.c32,.rapport-grid.c23{grid-template-columns:1fr}
}
</style>

<!-- FILTRES -->
<form method="GET" class="filter-bar">
  <label>Période :</label>
  <select name="annee" class="fsel" onchange="this.form.submit()">
    <?php foreach(array_merge([['a'=>date('Y')],['a'=>date('Y')-1],['a'=>date('Y')-2]],$annees_dispo) as $a):
      $av=(int)$a['a']; if($av<2020)continue; ?>
    <option value="<?= $av ?>" <?= $annee===$av?'selected':'' ?>><?= $av ?></option>
    <?php endforeach; ?>
  </select>
  <select name="mois" class="fsel" onchange="this.form.submit()">
    <option value="0" <?= !$mois?'selected':'' ?>>Toute l'année</option>
    <?php foreach(['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'] as $i=>$m): ?>
    <option value="<?= $i+1 ?>" <?= $mois===$i+1?'selected':'' ?>><?= $m ?></option>
    <?php endforeach; ?>
  </select>
  <select name="site" class="fsel" onchange="this.form.submit()">
    <option value="0">Tous les sites</option>
    <?php foreach($sites_list as $s): ?>
    <option value="<?= $s['id'] ?>" <?= $f_site===$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <span style="margin-left:auto;font-size:12px;color:var(--muted)">
    Période : <?= fmt_date($date_debut) ?> → <?= fmt_date($date_fin) ?>
  </span>
  <?php if(can('rapports','can_export')): ?>
  <div style="display:flex;gap:6px">
    <a href="<?= APP_URL ?>/api/export.php?type=equipements" class="btn btn-secondary btn-sm">📥 Équipements</a>
    <a href="<?= APP_URL ?>/api/export.php?type=livraisons"  class="btn btn-secondary btn-sm">📥 Livraisons</a>
    <a href="<?= APP_URL ?>/api/export.php?type=consommables" class="btn btn-secondary btn-sm">📥 Consommables</a>
    <a href="<?= APP_URL ?>/api/export.php?type=couts_sites&annee=<?= $annee ?>&mois=<?= $mois ?>&site=<?= $f_site ?>" class="btn btn-secondary btn-sm">💰 Coûts sites</a>
    <?php if(can('audit','can_export')): ?>
    <a href="<?= APP_URL ?>/api/export.php?type=audit" class="btn btn-secondary btn-sm">📥 Audit</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</form>

<!-- KPIs -->
<div class="rapport-kpi">
  <div class="rkpi <?= $kpi['equip_hs']>0?'danger':'' ?>">
    <div class="rv"><?= $kpi['total_equip'] ?></div>
    <div class="rl">Équipements actifs<br><?= $kpi['equip_neuf'] ?> neufs · <?= $kpi['equip_hs'] ?> H.S.</div>
  </div>
  <div class="rkpi <?= $kpi['fin_cycle_exp']>0?'danger':($kpi['fin_cycle_30j']>0?'warning':'success') ?>">
    <div class="rv"><?= $kpi['fin_cycle_exp']+$kpi['fin_cycle_30j'] ?></div>
    <div class="rl">Fin de cycle<br><?= $kpi['fin_cycle_exp'] ?> expirés · <?= $kpi['fin_cycle_30j'] ?> dans 30j</div>
  </div>
  <div class="rkpi">
    <div class="rv"><?= $kpi['total_sites'] ?></div>
    <div class="rl">Sites actifs<br><?= $kpi['total_users'] ?> utilisateurs</div>
  </div>
  <div class="rkpi <?= $kpi['conso_alertes']>0?'warning':'' ?>">
    <div class="rv"><?= $kpi['conso_alertes'] ?></div>
    <div class="rl">Alertes stock<br>consommables bas</div>
  </div>
</div>

<!-- CHARTS ROW 1 -->
<div class="rapport-grid c32" style="margin-bottom:20px">
  <div class="r-card">
    <div class="r-card-hdr"><h3>📈 Consommation mensuelle — 12 mois glissants</h3></div>
    <div class="r-card-body"><canvas id="chartM12" height="180"></canvas></div>
  </div>
  <div class="r-card">
    <div class="r-card-hdr"><h3>🏢 Consommation par site (période)</h3></div>
    <div class="r-card-body">
      <?php if(empty($conso_par_site)||!array_sum(array_column($conso_par_site,'total'))): ?>
      <div style="text-align:center;color:var(--muted);padding:40px 0;font-size:13px">Aucune donnée pour la période.</div>
      <?php else: ?>
      <canvas id="chartSites" height="180"></canvas>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- CONSOMMATION PAR CONSOMMABLE -->
<div class="r-card" style="margin-bottom:20px">
  <div class="r-card-hdr">
    <h3>🧴 Consommation par produit (période : <?= fmt_date($date_debut) ?> → <?= fmt_date($date_fin) ?>)</h3>
    <?php if(can('rapports','can_export')): ?>
    <a href="<?= APP_URL ?>/api/export.php?type=livraisons" class="btn btn-secondary btn-sm">📥 Excel</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Code</th><th>Produit</th><th>Unité</th>
        <th style="text-align:right">Période sélectionnée</th>
        <th style="text-align:right">Mois en cours</th>
        <th>Tendance</th>
      </tr></thead>
      <tbody>
      <?php foreach($conso_global as $c): $pct=min(100,($c['total_periode']/max(1,max(array_column($conso_global,'total_periode'))))*100); ?>
        <tr>
          <td><span style="font-family:monospace;font-size:11.5px;font-weight:700;color:var(--navy)"><?= h($c['code']) ?></span></td>
          <td style="font-size:13px"><?= h($c['libelle']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= $c['unite'] ?></td>
          <td style="text-align:right;font-family:'Montserrat',sans-serif;font-size:16px;font-weight:800;color:<?= $c['total_periode']>0?'var(--navy)':'var(--muted)' ?>">
            <?= fmt_number($c['total_periode'],1) ?>
          </td>
          <td style="text-align:right;font-size:13px;color:var(--muted)"><?= fmt_number($c['total_mois'],1) ?></td>
          <td style="width:120px">
            <div style="height:6px;background:var(--border);border-radius:3px">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--blue-mid, #1a56a0);border-radius:3px"></div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ÉTAT ÉQUIPEMENTS PAR SITE -->
<div class="r-card" style="margin-bottom:20px;overflow:hidden">
  <div class="r-card-hdr"><h3>💻 État des équipements par site</h3></div>
  <div class="table-wrap" style="overflow-x:auto">
    <table>
      <thead><tr>
        <th>Site</th>
        <th style="text-align:center">🟢 Neuf</th>
        <th style="text-align:center">🔵 Bon</th>
        <th style="text-align:center">🟡 Usagé</th>
        <th style="text-align:center">🔴 H.S.</th>
        <th style="text-align:center">Total</th>
        <th>Répartition</th>
      </tr></thead>
      <tbody>
      <?php foreach($equip_etat_site as $s):
        $tot=max(1,$s['total']);
        $pct_neuf=round($s['neuf']/$tot*100);
        $pct_bon=round($s['bon']/$tot*100);
        $pct_usage=round($s['usage_count']/$tot*100);
        $pct_hs=round($s['hs']/$tot*100);
      ?>
        <tr>
          <td style="font-size:13px;font-weight:500"><?= h($s['site']) ?></td>
          <td style="text-align:center;color:var(--success);font-weight:700"><?= $s['neuf'] ?></td>
          <td style="text-align:center;color:var(--info);font-weight:700"><?= $s['bon'] ?></td>
          <td style="text-align:center;color:var(--warning);font-weight:700"><?= $s['usage_count'] ?></td>
          <td style="text-align:center;color:var(--danger);font-weight:700"><?= $s['hs'] ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:800;font-size:16px"><?= $s['total'] ?></td>
          <td style="width:150px">
            <div style="display:flex;height:8px;border-radius:4px;overflow:hidden">
              <div style="width:<?= $pct_neuf ?>%;background:var(--success)" title="Neuf: <?= $s['neuf'] ?>"></div>
              <div style="width:<?= $pct_bon  ?>%;background:#2980b9" title="Bon: <?= $s['bon'] ?>"></div>
              <div style="width:<?= $pct_usage?>%;background:var(--warning)" title="Usagé: <?= $s['usage_count'] ?>"></div>
              <div style="width:<?= $pct_hs   ?>%;background:var(--danger)" title="H.S.: <?= $s['hs'] ?>"></div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- FIN DE CYCLE + ACTIVITÉ USERS -->
<?php $show_activite = ($user['role_slug']??'') !== 'gestionnaire_stock'; ?>
<div class="rapport-grid <?= $show_activite ? 'c2' : '' ?>" style="margin-bottom:20px">
  <!-- FIN DE CYCLE -->
  <div class="r-card">
    <div class="r-card-hdr">
      <h3>⚠️ Équipements — Fin de cycle (90j)</h3>
      <span style="font-size:12px;color:var(--muted)"><?= count($fin_cycle_soon) ?> équipement(s)</span>
    </div>
    <div class="r-card-body" style="padding:10px 18px">
      <?php if(empty($fin_cycle_soon)): ?>
      <div style="text-align:center;padding:30px;color:var(--success)">✅ Aucun équipement en fin de cycle dans les 90 prochains jours.</div>
      <?php else: foreach($fin_cycle_soon as $f):
        $j=(int)$f['jours'];
        $cls=$j<0?'exp':($j<=30?'soon':'ok');
        $lbl=$j<0?'Expiré il y a '.abs($j).'j':'J-'.$j;
      ?>
      <div class="fc-row <?= $show_activite ? '' : 'fc-row-wide' ?>">
        <span class="fc-badge <?= $cls ?>"><?= $lbl ?></span>
        <div style="flex:1">
          <div style="font-family:monospace;font-size:12.5px;font-weight:700;color:var(--navy)"><?= h($f['numero_serie_interne']) ?></div>
          <div style="font-size:11.5px;color:var(--muted)"><?= h($f['type']) ?> · <?= h($f['site']??'Non affecté') ?></div>
        </div>
        <div style="font-size:11.5px;color:var(--muted)"><?= fmt_date($f['date_fin_cycle']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- ACTIVITÉ UTILISATEURS — masqué pour gestionnaire_stock -->
  <?php if($show_activite): ?>
  <div class="r-card">
    <div class="r-card-hdr">
      <h3>👥 Activité des utilisateurs (période)</h3>
    </div>
    <div class="r-card-body" style="padding:10px 18px">
      <?php if(empty($activite_users)): ?>
      <div style="text-align:center;padding:30px;color:var(--muted);font-size:13px">Aucune activité.</div>
      <?php else:
        $max_actions = max(array_column($activite_users,'nb_actions'));
        foreach($activite_users as $u):
          $pct = $max_actions>0?round($u['nb_actions']/$max_actions*100):0;
      ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-bottom:4px">
          <div>
            <strong><?= h($u['nom']) ?></strong>
            <span style="font-size:11px;color:var(--muted);margin-left:6px"><?= h($u['role']) ?></span>
          </div>
          <div style="font-family:'Montserrat',sans-serif;font-weight:800;color:var(--navy)"><?= $u['nb_actions'] ?></div>
        </div>
        <div style="height:5px;background:var(--border);border-radius:3px">
          <div style="width:<?= $pct ?>%;height:100%;background:var(--blue-mid, #1a56a0);border-radius:3px"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px">Dernière action : <?= fmt_datetime($u['derniere_action']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     SECTION COÛTS EN FCFA PAR SITE
     ============================================================ -->
<div style="margin:28px 0 10px;display:flex;align-items:center;gap:10px">
  <div style="flex:1;height:2px;background:linear-gradient(90deg,var(--navy),transparent)"></div>
  <h2 style="font-family:'Montserrat',sans-serif;font-size:15px;font-weight:800;color:var(--navy);white-space:nowrap;padding:0 10px">
    💰 Coûts Consommables en FCFA
  </h2>
  <div style="flex:1;height:2px;background:linear-gradient(270deg,var(--navy),transparent)"></div>
</div>

<!-- KPIs COÛTS -->
<div class="cout-kpi-grid">
  <div class="cout-kpi">
    <div class="ck-label">Coût période sélectionnée</div>
    <div class="ck-val"><?= number_format($kpi_cout_periode,0,',',' ') ?> <span style="font-size:12px;font-weight:500">FCFA</span></div>
    <div class="ck-sub"><?= fmt_date($date_debut) ?> → <?= fmt_date($date_fin) ?></div>
  </div>
  <div class="cout-kpi green">
    <div class="ck-label">Coût mois en cours</div>
    <div class="ck-val"><?= number_format($kpi_cout_mois_cur,0,',',' ') ?> <span style="font-size:12px;font-weight:500">FCFA</span></div>
    <div class="ck-sub"><?= date('F Y') ?></div>
  </div>
  <div class="cout-kpi orange">
    <div class="ck-label">Coût semaine en cours</div>
    <div class="ck-val"><?= number_format($kpi_cout_semaine,0,',',' ') ?> <span style="font-size:12px;font-weight:500">FCFA</span></div>
    <div class="ck-sub">Sem. du <?= date('d/m',strtotime($debut_semaine_courante)) ?> au <?= date('d/m',strtotime($fin_semaine_courante)) ?></div>
  </div>
  <div class="cout-kpi <?= $kpi_cout_semaine > $kpi_cout_semaine_prec ? 'red' : 'green' ?>">
    <div class="ck-label">Semaine précédente</div>
    <div class="ck-val"><?= number_format($kpi_cout_semaine_prec,0,',',' ') ?> <span style="font-size:12px;font-weight:500">FCFA</span></div>
    <div class="ck-sub">
      <?php $delta = $kpi_cout_semaine - $kpi_cout_semaine_prec; ?>
      <?= $delta >= 0 ? '▲' : '▼' ?> <?= number_format(abs($delta),0,',',' ') ?> FCFA vs semaine courante
    </div>
  </div>
</div>

<!-- GRAPHE ÉVOLUTION COÛTS + TABLEAU COÛT PAR SITE -->
<div class="rapport-grid c32" style="margin-bottom:20px">
  <div class="r-card">
    <div class="r-card-hdr">
      <h3>📈 Évolution des coûts par site — 12 mois glissants (FCFA)</h3>
    </div>
    <div class="r-card-body"><canvas id="chartCoutEvol" height="200"></canvas></div>
  </div>

  <div class="r-card">
    <div class="r-card-hdr">
      <h3>🏢 Coût par site — période (FCFA)</h3>
      <?php if(can('rapports','can_export')): ?>
      <a href="<?= APP_URL ?>/api/export.php?type=couts_sites&annee=<?= $annee ?>&mois=<?= $mois ?>&site=<?= $f_site ?>" class="btn btn-secondary btn-sm">📥 Excel</a>
      <?php endif; ?>
    </div>
    <div class="r-card-body" style="padding:10px 18px">
      <?php
      $max_cout = max(1, ...array_column($cout_par_site,'cout_total') ?: [1]);
      $total_cout_all = array_sum(array_column($cout_par_site,'cout_total'));
      if ($total_cout_all == 0): ?>
      <div style="text-align:center;color:var(--muted);padding:30px;font-size:13px">
        Aucun coût renseigné pour la période.<br>
        <small>Assurez-vous que les prix unitaires sont saisis dans les livraisons.</small>
      </div>
      <?php else: foreach($cout_par_site as $s):
        $pct = $total_cout_all > 0 ? round($s['cout_total']/$total_cout_all*100,1) : 0;
        $bar_w = round($s['cout_total']/$max_cout*100);
      ?>
      <div class="site-cout-bar">
        <div class="scb-name"><?= h($s['site_nom']) ?></div>
        <div class="scb-bar"><div class="scb-fill" style="width:<?= $bar_w ?>%"></div></div>
        <div class="scb-val"><?= number_format($s['cout_total'],0,',',' ') ?> FCFA</div>
        <div class="scb-pct"><?= $pct ?>%</div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- POINT HEBDOMADAIRE -->
<div class="r-card" style="margin-bottom:20px">
  <div class="r-card-hdr">
    <h3>📅 Point hebdomadaire — Semaine du <?= date('d/m',strtotime($debut_semaine_courante)) ?> au <?= date('d/m',strtotime($fin_semaine_courante)) ?></h3>
    <span style="font-size:12px;color:var(--muted);font-weight:600">
      Total : <?= number_format($kpi_cout_semaine,0,',',' ') ?> FCFA
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Site</th>
        <th style="text-align:right">Quantité livrée</th>
        <th style="text-align:right">Coût semaine (FCFA)</th>
        <th style="width:140px">Part</th>
      </tr></thead>
      <tbody>
      <?php
      $total_hebdo = array_sum(array_column($cout_hebdo_site,'cout_semaine'));
      foreach($cout_hebdo_site as $h): if($h['cout_semaine']==0&&$h['qte_semaine']==0) continue;
        $ph = $total_hebdo>0?round($h['cout_semaine']/$total_hebdo*100,1):0;
      ?>
        <tr>
          <td style="font-weight:500"><?= h($h['site_nom']) ?></td>
          <td style="text-align:right;color:var(--muted)"><?= fmt_number($h['qte_semaine'],1) ?></td>
          <td style="text-align:right;font-family:'Montserrat',sans-serif;font-weight:700;color:<?= $h['cout_semaine']>0?'var(--navy)':'var(--muted)' ?>">
            <?= $h['cout_semaine']>0 ? number_format($h['cout_semaine'],0,',',' ').' FCFA' : '—' ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="flex:1;height:6px;background:var(--border);border-radius:3px">
                <div style="width:<?= $ph ?>%;height:100%;background:var(--blue-mid,#1a56a0);border-radius:3px"></div>
              </div>
              <span style="font-size:11px;color:var(--muted);min-width:30px"><?= $ph ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <?php if($total_hebdo>0): ?>
      <tfoot><tr style="background:var(--lighter);font-weight:700">
        <td>TOTAL</td>
        <td style="text-align:right">—</td>
        <td style="text-align:right;font-family:'Montserrat',sans-serif;color:var(--navy)"><?= number_format($total_hebdo,0,',',' ') ?> FCFA</td>
        <td></td>
      </tr></tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- DÉTAIL ARTICLES PAR SITE (période) -->
<?php if(!empty($detail_article_site)): ?>
<div class="r-card" style="margin-bottom:20px">
  <div class="r-card-hdr">
    <h3>🧴 Détail coûts par article × site (période) — Top 60</h3>
    <?php if(can('rapports','can_export')): ?>
    <a href="<?= APP_URL ?>/api/export.php?type=couts_sites&annee=<?= $annee ?>&mois=<?= $mois ?>&site=<?= $f_site ?>&detail=1" class="btn btn-secondary btn-sm">📥 Excel détail</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Article</th><th>Site</th><th>Unité</th>
        <th style="text-align:right">Qté</th>
        <th style="text-align:right">Prix unit. moy.</th>
        <th style="text-align:right">Coût total (FCFA)</th>
      </tr></thead>
      <tbody>
      <?php foreach($detail_article_site as $d): ?>
        <tr>
          <td>
            <span style="font-family:monospace;font-size:10.5px;color:var(--muted)"><?= h($d['code']) ?></span>
            <span style="margin-left:6px;font-size:13px"><?= h($d['article']) ?></span>
          </td>
          <td style="font-size:12.5px;color:var(--muted)"><?= h($d['site_nom']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= $d['unite'] ?></td>
          <td style="text-align:right"><?= fmt_number($d['qte'],1) ?></td>
          <td style="text-align:right;font-size:12px;color:var(--muted)">
            <?= $d['prix_unit_moy']>0?number_format($d['prix_unit_moy'],0,',',' ').' FCFA':'—' ?>
          </td>
          <td style="text-align:right;font-family:'Montserrat',sans-serif;font-weight:700;color:var(--navy)">
            <?= number_format($d['cout_total'],0,',',' ') ?> FCFA
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- BILAN FIN DE MOIS PAR SITE -->
<div class="r-card" style="margin-bottom:20px">
  <div class="r-card-hdr" style="justify-content:space-between">
    <h3>📊 Bilan mensuel — <?= date('F Y') ?> (coût par site et par article)</h3>
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-size:12px;font-weight:700;color:var(--navy)">
        Total mois : <?= number_format($kpi_cout_mois_cur,0,',',' ') ?> FCFA
      </span>
      <?php if(can('rapports','can_export')): ?>
      <a href="<?= APP_URL ?>/api/export.php?type=bilan_mensuel&annee=<?= date('Y') ?>&mois=<?= date('n') ?>" class="btn btn-secondary btn-sm">📥 Excel bilan</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="r-card-body">
    <?php if(empty($bilan_par_site)): ?>
    <div style="text-align:center;color:var(--muted);padding:30px;font-size:13px">
      Aucune dépense enregistrée ce mois-ci avec prix renseigné.
    </div>
    <?php else: foreach($bilan_par_site as $site_nom => $data): ?>
    <div class="bilan-section">
      <div class="bilan-site-hdr" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
        <span>🏢 <?= h($site_nom) ?></span>
        <span class="bsh-total"><?= number_format($data['total'],0,',',' ') ?> FCFA</span>
      </div>
      <div class="bilan-site-body">
        <table>
          <thead><tr>
            <th>Code</th><th>Article</th><th>Unité</th>
            <th style="text-align:right">Qté consommée</th>
            <th style="text-align:right">Prix unit. moy.</th>
            <th style="text-align:right">Coût (FCFA)</th>
            <th style="text-align:right">Part du site</th>
          </tr></thead>
          <tbody>
          <?php foreach($data['articles'] as $a):
            $part = $data['total']>0?round($a['cout_total']/$data['total']*100,1):0;
          ?>
            <tr>
              <td style="font-family:monospace;font-size:11px;color:var(--muted)"><?= h($a['code']) ?></td>
              <td style="font-weight:500"><?= h($a['article']) ?></td>
              <td style="color:var(--muted);font-size:12px"><?= $a['unite'] ?></td>
              <td style="text-align:right"><?= fmt_number($a['qte'],1) ?></td>
              <td style="text-align:right;color:var(--muted);font-size:12px">
                <?= $a['prix_unit_moy']>0?number_format($a['prix_unit_moy'],0,',',' ').' FCFA':'—' ?>
              </td>
              <td style="text-align:right;font-family:'Montserrat',sans-serif;font-weight:700;color:var(--navy)">
                <?= number_format($a['cout_total'],0,',',' ') ?>
              </td>
              <td style="text-align:right;font-size:12px;color:var(--muted)"><?= $part ?>%</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot class="bilan-tfoot"><tr>
            <td colspan="3">TOTAL <?= h($site_nom) ?></td>
            <td style="text-align:right">—</td>
            <td style="text-align:right">—</td>
            <td style="text-align:right;color:var(--navy)"><?= number_format($data['total'],0,',',' ') ?> FCFA</td>
            <td style="text-align:right">100%</td>
          </tr></tfoot>
        </table>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family="'DM Sans',sans-serif";
Chart.defaults.color='#64748b';

// Chart 12 mois
const m12Labels=<?= $m12_labels ?>, m12Vals=<?= $m12_values ?>;
if(document.getElementById('chartM12')){
  new Chart(document.getElementById('chartM12'),{
    type:'bar',
    data:{labels:m12Labels,datasets:[{
      label:'Quantité livrée',data:m12Vals,
      backgroundColor:m12Vals.map((_,i)=>i===m12Vals.length-1?'rgba(41,128,212,.9)':'rgba(37,99,168,.5)'),
      borderRadius:6
    }]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
      scales:{x:{grid:{display:false},ticks:{font:{size:10}}},y:{grid:{color:'#f0f0f0'},ticks:{font:{size:10}}}}}
  });
}

// Chart sites
const siteLabels=<?= $site_labels ?>, siteVals=<?= $site_values ?>;
if(document.getElementById('chartSites')&&siteVals.some(v=>v>0)){
  new Chart(document.getElementById('chartSites'),{
    type:'doughnut',
    data:{labels:siteLabels,datasets:[{data:siteVals,
      backgroundColor:['#0d1f35','#1a3c5e','#1a56a0','#27ae60','#e74c3c','#8e44ad','#16a085','#f39c12'],
      borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'60%',
      plugins:{legend:{position:'right',labels:{font:{size:11},boxWidth:12}}}}
  });
}

// ── GRAPHE ÉVOLUTION COÛTS FCFA PAR SITE (12 mois glissants)
const evolLabels  = <?= $evolution_labels ?>;
const evolDatasets= <?= $evolution_datasets_json ?>;
if(document.getElementById('chartCoutEvol')&&evolDatasets.length){
  const evolSinglePoint = evolLabels.length <= 1;
  const evolType = evolSinglePoint ? 'bar' : 'line';
  new Chart(document.getElementById('chartCoutEvol'),{
    type: evolType,
    data:{labels:evolLabels, datasets:evolDatasets.map((d,i)=>({
      ...d,
      pointRadius: evolSinglePoint ? 0 : 4,
      pointHoverRadius: evolSinglePoint ? 0 : 6,
      borderWidth: evolSinglePoint ? 0 : 2,
      backgroundColor: evolSinglePoint ? d.borderColor+'CC' : d.backgroundColor,
      borderRadius: evolSinglePoint ? 6 : 0,
      spanGaps: true
    }))},
    options:{
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index',intersect:false},
      plugins:{
        legend:{position:'bottom',labels:{font:{size:10},boxWidth:10,padding:8}},
        tooltip:{
          callbacks:{
            label:ctx=>`${ctx.dataset.label} : ${ctx.parsed.y.toLocaleString('fr-FR')} FCFA`
          }
        },
        title: evolSinglePoint ? {
          display:true,
          text:'⚠️ Données disponibles sur 1 mois — le graphique s\'affichera en courbe dès le 2ème mois',
          font:{size:11}, color:'#94a3b8', padding:{bottom:12}
        } : {display:false}
      },
      scales:{
        x:{grid:{display:false},ticks:{font:{size:10}}},
        y:{grid:{color:'#f0f0f0'},beginAtZero:true,ticks:{font:{size:10},callback:v=>v.toLocaleString('fr-FR')+' F'}}
      }
    }
  });
}

// ── GRAPHE COÛT PAR SITE (période) — doughnut
const coutSiteLabels=<?= $cout_site_labels ?>, coutSiteVals=<?= $cout_site_values ?>;
// (optionnel: on réutilise chartSites pour la quantité; le coût est dans les barres inline)
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
