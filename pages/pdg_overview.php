<?php
// ============================================================
//  pages/pdg_overview.php — Vue exécutive PDG
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
$user = current_user();

$page_title  = 'Vue PDG';
$active_page = 'pdg_overview';

$mois  = trim($_GET['mois'] ?? date('Y-m'));
$annee = substr($mois, 0, 4);
$mc = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin',
       '07'=>'Juil','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
$ml = ['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
       '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];
$mois_display = ($ml[substr($mois,5,2)] ?? '') . ' ' . $annee;
$mois_prec    = date('Y-m', strtotime($mois.'-01 -1 month'));
$mois_prec_lbl= ($mc[substr($mois_prec,5,2)] ?? '') . ' ' . substr($mois_prec,0,4);

// ── OPÉRATIONS mois courant
$ops = db_fetch_one(
    "SELECT COUNT(*) AS total_points,
            SUM(CASE WHEN statut='valide' THEN 1 ELSE 0 END) AS points_valides,
            SUM(CASE WHEN statut='en_attente_validation' THEN 1 ELSE 0 END) AS points_attente,
            SUM(CASE WHEN statut='rejete' THEN 1 ELSE 0 END) AS points_rejetes,
            COALESCE(SUM(total_engins),0) AS total_engins,
            COALESCE(SUM(total_plaques),0) AS total_plaques,
            COALESCE(SUM(rivets_utilises),0) AS rivets_utilises,
            COALESCE(ROUND(AVG(NULLIF(moyenne_prod,0)),1),0) AS moy_prod
     FROM op_points_journaliers
     WHERE TO_CHAR(date_point,'YYYY-MM')=? AND statut != 'brouillon'",
    [$mois]
);

// ── OPÉRATIONS mois précédent (tendance)
$ops_prec = db_fetch_one(
    "SELECT COALESCE(SUM(total_engins),0) AS total_engins,
            COALESCE(SUM(total_plaques),0) AS total_plaques
     FROM op_points_journaliers
     WHERE TO_CHAR(date_point,'YYYY-MM')=? AND statut != 'brouillon'",
    [$mois_prec]
);
$engins_curr = (int)($ops['total_engins'] ?? 0);
$engins_prev = (int)($ops_prec['total_engins'] ?? 0);
$engins_delta = $engins_curr - $engins_prev;
$engins_trend = $engins_prev > 0 ? round($engins_delta / $engins_prev * 100, 1) : null;

// ── SITES — performance par site
$prod_par_site = db_fetch_all(
    "SELECT s.nom, s.type,
            COUNT(p.id) AS nb_points,
            COALESCE(SUM(p.total_engins),0) AS engins,
            COALESCE(SUM(p.total_plaques),0) AS plaques,
            COALESCE(ROUND(AVG(NULLIF(p.moyenne_prod,0)),1),0) AS moy_vh,
            SUM(CASE WHEN p.statut='en_attente_validation' THEN 1 ELSE 0 END) AS en_attente
     FROM sites s
     LEFT JOIN op_points_journaliers p ON p.site_id=s.id
                AND TO_CHAR(p.date_point,'YYYY-MM')=? AND p.statut != 'brouillon'
     WHERE s.actif=1
     GROUP BY s.id, s.nom, s.type ORDER BY engins DESC",
    [$mois]
);
$max_engins = empty($prod_par_site) ? 1 : max(1, ...array_column($prod_par_site, 'engins'));
$best_site  = !empty($prod_par_site) ? $prod_par_site[0] : null;

// ── Taux de validation points
$tx_valid = ($ops['total_points']??0) > 0
    ? round(($ops['points_valides']??0) / ($ops['total_points']??1) * 100)
    : 0;

// ── BOBINES
$bobines_stats = db_fetch_one(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN statut='en_cours' THEN 1 ELSE 0 END) AS en_cours,
            SUM(CASE WHEN statut='en_stock' THEN 1 ELSE 0 END) AS en_stock,
            SUM(CASE WHEN statut='epuisee' THEN 1 ELSE 0 END) AS epuisees,
            COALESCE(SUM(films_restants),0) AS films_restants
     FROM op_bobines"
);
$films_mois = (int)db_fetch_value(
    "SELECT COALESCE(SUM(fu.films_utilises),0)
     FROM op_films_utilises fu
     JOIN op_points_journaliers p ON p.id=fu.point_id
     WHERE TO_CHAR(p.date_point,'YYYY-MM')=?",
    [$mois]
);
$films_mois_prec = (int)db_fetch_value(
    "SELECT COALESCE(SUM(fu.films_utilises),0)
     FROM op_films_utilises fu
     JOIN op_points_journaliers p ON p.id=fu.point_id
     WHERE TO_CHAR(p.date_point,'YYYY-MM')=?",
    [$mois_prec]
);
$films_par_site = db_fetch_all(
    "SELECT s.nom, COALESCE(SUM(fu.films_utilises),0) AS films
     FROM sites s
     LEFT JOIN op_points_journaliers p ON p.site_id=s.id AND TO_CHAR(p.date_point,'YYYY-MM')=?
     LEFT JOIN op_films_utilises fu ON fu.point_id=p.id
     WHERE s.actif=1
     GROUP BY s.id, s.nom ORDER BY films DESC",
    [$mois]
);
$films_detail_raw = db_fetch_all(
    "SELECT s.nom AS site, b.type_code, COALESCE(SUM(fu.films_utilises),0) AS films
     FROM sites s
     JOIN op_points_journaliers p ON p.site_id=s.id AND TO_CHAR(p.date_point,'YYYY-MM')=?
     JOIN op_films_utilises fu ON fu.point_id=p.id
     JOIN op_bobines b ON b.id=fu.bobine_id
     WHERE s.actif=1
     GROUP BY s.id, s.nom, b.type_code ORDER BY s.nom, b.type_code",
    [$mois]
);
$films_par_type = [];
$films_by_type  = [];
foreach ($films_detail_raw as $d) {
    $films_par_type[$d['type_code']] = ($films_par_type[$d['type_code']] ?? 0) + (int)$d['films'];
    $films_by_type[$d['type_code']][] = ['site'=>$d['site'],'films'=>(int)$d['films']];
}
arsort($films_par_type);
$js_films_detail = json_encode(array_values(array_map(fn($t)=>$films_by_type[$t]??[], array_keys($films_par_type))));

// ── COMMANDES
$cmd_stats = db_fetch_one(
    "SELECT SUM(CASE WHEN statut='en_attente' THEN 1 ELSE 0 END) AS en_attente,
            SUM(CASE WHEN statut='en_attente_livraison' THEN 1 ELSE 0 END) AS a_livrer,
            SUM(CASE WHEN statut='en_cours_livraison' THEN 1 ELSE 0 END) AS en_route,
            SUM(CASE WHEN statut='livre' AND TO_CHAR(created_at,'YYYY-MM')=? THEN 1 ELSE 0 END) AS livrees_mois
     FROM commandes",
    [$mois]
);

// ── RIVETS
$rivets = db_fetch_all(
    "SELECT s.nom, sr.type_rivet, COALESCE(sr.quantite,0) AS quantite
     FROM sites s JOIN op_stock_rivets sr ON sr.site_id=s.id
     WHERE s.actif=1
     ORDER BY s.nom, array_position(ARRAY['gonflable','eclate']::text[], (sr.type_rivet)::text)"
);
$rps = [];
foreach ($rivets as $r) $rps[$r['nom']][$r['type_rivet']] = (int)$r['quantite'];

// ── PMMA
$pmma_par_type = db_fetch_all(
    "SELECT sp.type_pmma, COALESCE(SUM(sp.quantite),0) AS total,
            COUNT(CASE WHEN sp.quantite < sp.seuil_alerte THEN 1 END) AS nb_bas
     FROM stock_pmma_site sp JOIN sites s ON s.id=sp.site_id
     WHERE s.actif=1
     GROUP BY sp.type_pmma ORDER BY total DESC"
);
$pmma_detail = db_fetch_all(
    "SELECT s.nom, sp.type_pmma, COALESCE(sp.quantite,0) AS quantite, COALESCE(sp.seuil_alerte,10) AS seuil
     FROM sites s JOIN stock_pmma_site sp ON sp.site_id=s.id
     WHERE s.actif=1 ORDER BY sp.type_pmma, s.nom"
);

// ── ALERTES
$alertes_stock  = (int)db_fetch_value("SELECT COUNT(*) FROM articles WHERE stock_global <= seuil_alerte AND seuil_alerte > 0");
$rivets_bas     = (int)db_fetch_value("SELECT COUNT(*) FROM op_stock_rivets WHERE quantite < 200");
$points_attente = (int)($ops['points_attente'] ?? 0);
$cmd_en_attente = (int)($cmd_stats['en_attente'] ?? 0);
$total_alertes  = $points_attente + $cmd_en_attente + $alertes_stock + $rivets_bas;

// ── DEMANDES INTERNES
$di_pending = (int)db_fetch_value("SELECT COUNT(*) FROM di_demandes WHERE statut IN ('en_attente','en_cours')");
$di_mois    = (int)db_fetch_value("SELECT COUNT(*) FROM di_demandes WHERE TO_CHAR(created_at,'YYYY-MM')=? AND statut != 'brouillon'", [$mois]);
$di_approuv = (int)db_fetch_value("SELECT COUNT(*) FROM di_demandes WHERE TO_CHAR(updated_at,'YYYY-MM')=? AND statut IN ('approuve','approuve_traitement')", [$mois]);
$di_tx      = $di_mois > 0 ? round($di_approuv / $di_mois * 100) : 0;
$di_recents = db_fetch_all(
    "SELECT d.numero, dt.label AS type_lbl, d.statut,
            (u.prenom||' '||u.nom) AS demandeur
     FROM di_demandes d
     JOIN di_types dt ON dt.code = d.type_code
     JOIN users u ON u.id = d.demandeur_id
     WHERE d.statut IN ('en_attente','en_cours')
     ORDER BY d.created_at DESC LIMIT 5"
);

// ── ÉVOLUTION 6 MOIS
$evol = db_fetch_all(
    "SELECT TO_CHAR(date_point,'YYYY-MM') AS mois,
            SUM(total_engins) AS engins,
            SUM(total_plaques) AS plaques
     FROM op_points_journaliers
     WHERE date_point >= (CURRENT_DATE - INTERVAL '6 MONTH') AND statut != 'brouillon'
     GROUP BY TO_CHAR(date_point,'YYYY-MM') ORDER BY mois ASC"
);

// ── POINTS EN ATTENTE
$pts_attente = db_fetch_all(
    "SELECT p.date_point, p.type_point, s.nom AS site, CONCAT(u.prenom,' ',u.nom) AS coord
     FROM op_points_journaliers p
     JOIN sites s ON s.id=p.site_id
     LEFT JOIN users u ON u.id=p.created_by
     WHERE p.statut='en_attente_validation'
     ORDER BY p.date_point DESC LIMIT 8"
);

// ── JS DATA
$js_evol_labels  = json_encode(array_map(fn($r) => ($mc[substr($r['mois'],5,2)]??'').' '.substr($r['mois'],2,2), $evol));
$js_evol_engins  = json_encode(array_map(fn($r) => (int)$r['engins'], $evol));
$js_evol_plaques = json_encode(array_map(fn($r) => (int)$r['plaques'], $evol));
$js_statuts      = json_encode([(int)($ops['points_valides']??0),(int)($ops['points_attente']??0),(int)($ops['points_rejetes']??0)]);
$js_films_labels = json_encode(array_keys($films_par_type));
$js_films_values = json_encode(array_values($films_par_type));
$js_bobines      = json_encode([(int)($bobines_stats['en_cours']??0),(int)($bobines_stats['en_stock']??0),(int)($bobines_stats['epuisees']??0)]);
$js_cmds         = json_encode([(int)($cmd_stats['en_attente']??0),(int)(($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0)),(int)($cmd_stats['livrees_mois']??0)]);
$pmma_by_type = [];
foreach ($pmma_detail as $d) $pmma_by_type[$d['type_pmma']][] = ['site'=>$d['nom'],'qty'=>(int)$d['quantite'],'seuil'=>(int)$d['seuil']];
$js_pmma_labels = json_encode(array_map(fn($r)=>$r['type_pmma'], $pmma_par_type));
$js_pmma_totals = json_encode(array_map(fn($r)=>(int)$r['total'], $pmma_par_type));
$js_pmma_bas    = json_encode(array_map(fn($r)=>(int)$r['nb_bas'], $pmma_par_type));
$js_pmma_detail = json_encode(array_values(array_map(fn($r)=>$pmma_by_type[$r['type_pmma']]??[], $pmma_par_type)));
$riv_type_detail = [[], []]; $riv_total_gonfl = 0; $riv_total_eclat = 0;
foreach ($rps as $site => $types) {
    $g = (int)($types['gonflable'] ?? 0); $e = (int)($types['eclate'] ?? 0);
    $riv_total_gonfl += $g; $riv_total_eclat += $e;
    $riv_type_detail[0][] = ['site'=>$site,'qty'=>$g];
    $riv_type_detail[1][] = ['site'=>$site,'qty'=>$e];
}
$js_riv_labels = json_encode(['Gonflable','Éclaté']);
$js_riv_totals = json_encode([$riv_total_gonfl,$riv_total_eclat]);
$js_riv_detail = json_encode($riv_type_detail);
$films_ch_h    = max(160, count($films_par_type) * 46);
$pmma_ch_h     = max(140, count($pmma_par_type) * 46);

// Palette sites (couleurs identifiables par site)
$SC = ['#1B75BC','#3B4FBE','#7c3aed','#0891b2','#16a34a','#d97706','#e11d48'];

// ── DONNÉES MENSUELLES PAR SITE (widget performance)
$site_monthly_raw = db_fetch_all(
    "SELECT s.nom AS site_nom,
            TO_CHAR(p.date_point,'YYYY-MM') AS mois,
            COALESCE(SUM(fu.films_utilises),0) AS films,
            COALESCE(SUM(p.total_engins),0) AS engins,
            COALESCE(ROUND(AVG(NULLIF(p.moyenne_prod,0)),1),0) AS moy_vh
     FROM sites s
     JOIN op_points_journaliers p ON p.site_id=s.id
          AND TO_CHAR(p.date_point,'YYYY')=?
          AND p.statut != 'brouillon'
     LEFT JOIN op_films_utilises fu ON fu.point_id=p.id
     WHERE s.actif=1
     GROUP BY s.id, s.nom, TO_CHAR(p.date_point,'YYYY-MM')
     ORDER BY s.id, mois",
    [$annee]
);
$site_monthly_by_name = [];
foreach ($site_monthly_raw as $r) {
    if ($r['mois']) $site_monthly_by_name[$r['site_nom']][$r['mois']] = [
        'films'  => (int)$r['films'],
        'engins' => (int)$r['engins'],
        'moy_vh' => (float)$r['moy_vh'],
    ];
}
// Films restants par site (si op_bobines a site_id)
$films_rest_by_site = [];
try {
    foreach (db_fetch_all(
        "SELECT s.nom, COALESCE(SUM(b.films_restants),0) AS fr
         FROM sites s LEFT JOIN op_bobines b ON b.site_id=s.id AND b.statut IN ('en_cours','en_stock')
         WHERE s.actif=1 GROUP BY s.id, s.nom"
    ) as $r) $films_rest_by_site[$r['nom']] = (int)$r['fr'];
} catch (Throwable $e) {}
$films_mois_by_site = [];
foreach ($films_par_site as $fs) $films_mois_by_site[$fs['nom']] = (int)$fs['films'];
$pfw_sites_json = [];
foreach ($prod_par_site as $i => $s) {
    $fr = $films_rest_by_site[$s['nom']] ?? 0;
    if (!$fr) $fr = (int)round(($bobines_stats['films_restants']??0) / max(1, count($prod_par_site)));
    $pfw_sites_json[] = [
        'name'       => $s['nom'],
        'color'      => $SC[$i % count($SC)],
        'moy_vh'     => (float)$s['moy_vh'],
        'films_mois' => $films_mois_by_site[$s['nom']] ?? 0,
        'films_rest' => $fr,
        'monthly'    => (object)($site_monthly_by_name[$s['nom']] ?? []),
    ];
}
$js_pfw_sites    = json_encode($pfw_sites_json);
$pfw_quarter_def = max(1, (int)ceil((int)substr($mois, 5, 2) / 3));

include __DIR__ . '/../templates/header.php';
?>
<style>
/* ── RESET */
.pdg{max-width:1200px;margin:0 auto;font-size:14px}
.pdg *{box-sizing:border-box}

/* ── TOP BAR */
.pdg-topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;gap:12px;flex-wrap:wrap}
.pdg-title{font-size:24px;font-weight:900;color:var(--navy,#06033A);letter-spacing:-.5px}
.pdg-sub{font-size:13px;color:var(--muted,#94a3b8);margin-top:2px}
.pdg-controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.alrt-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:20px;font-size:12px;font-weight:700}
.alrt-warn{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.alrt-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.month-inp{padding:7px 11px;border:1.5px solid var(--border,#e2e8f0);border-radius:9px;font-size:13px;
  background:white;outline:none;font-family:inherit;cursor:pointer}

/* ── HERO (top KPI row) */
.hero-row{display:grid;grid-template-columns:1.8fr 1fr 1fr 1fr 1fr;gap:14px;margin-bottom:20px}
@media(max-width:900px){.hero-row{grid-template-columns:1fr 1fr 1fr;}}
@media(max-width:580px){.hero-row{grid-template-columns:1fr 1fr;}}
.hero-main{background:var(--navy,#06033A);border-radius:18px;padding:22px 26px;color:#fff;position:relative;overflow:hidden}
.hero-main::after{content:'';position:absolute;right:-30px;bottom:-30px;width:150px;height:150px;
  background:rgba(255,255,255,.05);border-radius:50%}
.hero-num{font-size:38px;font-weight:900;letter-spacing:-1px;line-height:1;font-family:'Montserrat',sans-serif}
.hero-lbl{font-size:11px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.5px;margin-top:6px;font-weight:700}
.hero-trend{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;margin-top:10px}
.t-up{background:rgba(22,163,74,.2);color:#86efac}
.t-dn{background:rgba(220,38,38,.2);color:#fca5a5}
.t-flat{background:rgba(255,255,255,.1);color:rgba(255,255,255,.7)}
.hero-prev{font-size:11px;color:rgba(255,255,255,.45);margin-top:5px}
.hero-card{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:18px;padding:18px 20px;display:flex;flex-direction:column;justify-content:space-between}
.hc-label{font-size:10px;font-weight:700;color:var(--muted,#94a3b8);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.hc-top{font-size:11px;color:var(--muted,#94a3b8);margin-bottom:4px}
.hc-num{font-size:26px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif;line-height:1}
.hc-name{font-size:12px;font-weight:600;color:var(--muted,#94a3b8);margin-top:3px}
.hc-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;margin-top:8px}
.hc-blue{background:#dbeafe;color:#1e40af}
.hc-green{background:#d1fae5;color:#065f46}
.hc-orange{background:#fff7ed;color:#c2410c}
.hc-purple{background:#f5f3ff;color:#5b21b6}
.hc-red{background:#fee2e2;color:#991b1b}

/* ── SITE PERFORMANCE SECTION */
.perf-wrap{display:grid;grid-template-columns:1fr 1.6fr;gap:16px;margin-bottom:20px}
@media(max-width:820px){.perf-wrap{grid-template-columns:1fr}}
.card{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:18px;padding:20px 22px}
.card-ttl{font-size:13px;font-weight:800;color:var(--navy,#06033A);margin:0 0 4px}
.card-sub{font-size:12px;color:var(--muted,#94a3b8);margin-bottom:18px}

/* Sites distribution bar */
.dist-bar{height:8px;border-radius:4px;overflow:hidden;display:flex;margin-bottom:18px}
.dist-seg{height:100%;transition:.3s}

/* Site row */
.site-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border,#f1f5f9)}
.site-row:last-child{border-bottom:none}
.site-av{width:34px;height:34px;border-radius:50%;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;letter-spacing:0}
.site-name{font-size:13px;font-weight:700;color:var(--navy,#06033A)}
.site-sub{font-size:11px;color:var(--muted,#94a3b8)}
.site-mini-bar{flex:1;min-width:60px}
.site-mini-fill{height:4px;border-radius:2px;background:currentColor}
.site-pct{font-size:12px;font-weight:800;color:var(--navy,#06033A);min-width:38px;text-align:right}

/* Performance table */
.ptbl{width:100%;border-collapse:collapse}
.ptbl th{font-size:10px;font-weight:700;color:var(--muted,#94a3b8);text-transform:uppercase;letter-spacing:.4px;
  padding:9px 10px;border-bottom:2px solid var(--border,#e2e8f0);text-align:right;white-space:nowrap}
.ptbl th:first-child{text-align:left}
.ptbl td{padding:11px 10px;border-bottom:1px solid var(--border,#f1f5f9);font-size:13px;text-align:right;vertical-align:middle}
.ptbl td:first-child{text-align:left}
.ptbl tr:last-child td{border-bottom:none}
.ptbl tr:hover td{background:#fafbff}
.mvh{font-size:12px;font-weight:700;padding:3px 8px;border-radius:8px;display:inline-block}
.mvh-g{background:#d1fae5;color:#065f46}
.mvh-o{background:#fff7ed;color:#c2410c}
.mvh-r{background:#fee2e2;color:#991b1b}
.att-dot{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#c2410c}

/* ── CHARTS ROW */
.charts-row{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:14px;margin-bottom:20px}
@media(max-width:900px){.charts-row{grid-template-columns:1fr 1fr}}
@media(max-width:580px){.charts-row{grid-template-columns:1fr}}
.ch-box{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:18px;padding:20px 22px}
.ch-ttl{font-size:13px;font-weight:800;color:var(--navy,#06033A);margin-bottom:4px}
.ch-sub{font-size:11px;color:var(--muted,#94a3b8);margin-bottom:14px}
.donut-wrap{display:flex;align-items:center;justify-content:center;gap:16px;padding:8px 0}
.leg-list{display:flex;flex-direction:column;gap:7px}
.leg-item{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--muted,#94a3b8)}
.leg-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.leg-val{margin-left:auto;font-weight:700;color:var(--navy,#06033A);font-size:12px}

/* ── BOTTOM ROW */
.bottom-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
@media(max-width:720px){.bottom-row{grid-template-columns:1fr}}

/* ── Demandes table */
.dtbl{width:100%;border-collapse:collapse;font-size:12px}
.dtbl th{font-size:10px;font-weight:700;color:var(--muted,#94a3b8);text-transform:uppercase;padding:7px 8px;
  border-bottom:2px solid var(--border,#e2e8f0);text-align:left;letter-spacing:.3px}
.dtbl td{padding:9px 8px;border-bottom:1px solid #f1f5f9}
.dtbl tr:last-child td{border-bottom:none}
.d-statut{display:inline-block;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700}
.ds-att{background:#fff7ed;color:#c2410c}
.ds-enc{background:#dbeafe;color:#1e40af}
.kpi-mini{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.kpi-m{background:#f8fafc;border-radius:12px;padding:14px 16px}
.kpi-m-val{font-size:24px;font-weight:900;font-family:'Montserrat',sans-serif;line-height:1}
.kpi-m-lbl{font-size:10px;color:var(--muted,#94a3b8);font-weight:700;text-transform:uppercase;margin-top:4px;letter-spacing:.3px}

/* ── Alertes */
.atbl{width:100%;border-collapse:collapse;font-size:12px}
.atbl th{font-size:10px;font-weight:700;color:var(--muted,#94a3b8);text-transform:uppercase;padding:7px 8px;
  border-bottom:2px solid var(--border,#e2e8f0);text-align:left;letter-spacing:.3px}
.atbl td{padding:9px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.atbl tr:last-child td{border-bottom:none}
.type-pill{display:inline-block;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700;background:#fff7ed;color:#c2410c}

/* ── Stock mini row */
.stock-stat-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
.stock-stat-row:last-child{border-bottom:none}
.stock-val{font-weight:800;font-family:'Montserrat',sans-serif;font-size:15px}

/* ── Widget Performance par site */
.pfw-card{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:20px;overflow:hidden;margin-bottom:20px}
.pfw-top{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;gap:10px}
.pfw-site-lbl{font-size:11px;color:#94a3b8;letter-spacing:.3px;margin-bottom:3px;text-transform:uppercase;font-weight:700}
.pfw-site-sel{display:flex;align-items:center;gap:6px;cursor:pointer}
.pfw-site-sel select{font-size:16px;font-weight:900;color:#06033A;background:transparent;border:none;outline:none;cursor:pointer;font-family:inherit;appearance:none;-webkit-appearance:none;padding-right:4px}
.pfw-site-arr{font-size:11px;color:#94a3b8}
.pfw-quarters{display:flex;gap:6px}
.pfw-q{padding:7px 16px;border-radius:20px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:13px;font-weight:700;color:#94a3b8;cursor:pointer;font-family:inherit;transition:.15s;white-space:nowrap}
.pfw-q:hover{border-color:#06033A;color:#06033A}
.pfw-q.active{background:#06033A;color:#fff;border-color:#06033A}
.pfw-body{display:grid;grid-template-columns:200px 1fr;min-height:250px}
@media(max-width:700px){.pfw-body{grid-template-columns:1fr}}
.pfw-left{padding:22px 16px 22px 20px;display:flex;gap:10px;transition:background .35s;border-radius:0 0 0 18px}
.pfw-vert{writing-mode:vertical-rl;transform:rotate(180deg);font-size:10px;font-weight:700;color:rgba(255,255,255,.55);letter-spacing:.8px;text-transform:uppercase;flex-shrink:0;align-self:center}
.pfw-stats{flex:1;display:flex;flex-direction:column;justify-content:center;gap:18px}
.pfw-stat-lbl{font-size:11px;color:rgba(255,255,255,.65);margin-bottom:3px;font-weight:600}
.pfw-stat-val{font-size:21px;font-weight:900;color:#fff;font-family:'Montserrat',sans-serif;line-height:1}
.pfw-right{padding:18px 20px 10px;position:relative;overflow:hidden}
.pfw-empty{display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;font-size:13px}
</style>

<div class="pdg">

<!-- ══════════ TOP BAR ══════════ -->
<div class="pdg-topbar">
  <div>
    <div class="pdg-title">Vue Exécutive</div>
    <div class="pdg-sub">Express Multiservices CI · DigiStock</div>
  </div>
  <div class="pdg-controls">
    <?php if ($total_alertes > 0): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php if($points_attente>0): ?><span class="alrt-pill alrt-warn"><i class="ph-duotone ph-clock"></i> <?= $points_attente ?> point(s) à valider</span><?php endif; ?>
      <?php if($cmd_en_attente>0): ?><span class="alrt-pill alrt-warn"><i class="ph-duotone ph-package"></i> <?= $cmd_en_attente ?> commande(s)</span><?php endif; ?>
      <?php if($alertes_stock>0): ?><span class="alrt-pill alrt-warn"><i class="ph-duotone ph-warning"></i> <?= $alertes_stock ?> stock(s) bas</span><?php endif; ?>
      <?php if($rivets_bas>0): ?><span class="alrt-pill alrt-warn"><i class="ph-duotone ph-nut"></i> <?= $rivets_bas ?> rivets bas</span><?php endif; ?>
    </div>
    <?php else: ?>
    <span class="alrt-pill alrt-ok"><i class="ph-duotone ph-check-circle"></i> Aucune alerte</span>
    <?php endif; ?>
    <form method="get" style="display:flex;align-items:center;gap:6px">
      <input type="month" name="mois" value="<?= h($mois) ?>" class="month-inp" onchange="this.form.submit()">
    </form>
  </div>
</div>

<!-- ══════════ HERO KPI ROW ══════════ -->
<div class="hero-row">
  <!-- Main KPI -->
  <div class="hero-main">
    <div class="hero-lbl">Engins posés</div>
    <div style="display:flex;align-items:flex-end;gap:4px;margin-top:4px">
      <div class="hero-num"><?= number_format($engins_curr, 0, ',', ' ') ?></div>
    </div>
    <?php if ($engins_trend !== null): ?>
    <div class="hero-trend <?= $engins_trend >= 0 ? 't-up' : 't-dn' ?>">
      <?= $engins_trend >= 0 ? '↑' : '↓' ?> <?= abs($engins_trend) ?>%
      &nbsp;<span style="font-weight:400;opacity:.8"><?= $engins_delta >= 0 ? '+' : '' ?><?= number_format($engins_delta, 0, ',', ' ') ?></span>
    </div>
    <?php else: ?>
    <div class="hero-trend t-flat">— Premier mois</div>
    <?php endif; ?>
    <div class="hero-prev">vs <?= h($mois_prec_lbl) ?> (<?= number_format($engins_prev, 0, ',', ' ') ?>)</div>
  </div>

  <!-- Meilleur site -->
  <div class="hero-card">
    <div class="hc-label">Meilleur site</div>
    <?php if ($best_site): ?>
    <div class="hc-top">Production n°1</div>
    <div class="hc-num"><?= number_format((int)$best_site['engins'], 0, ',', ' ') ?></div>
    <div class="hc-name"><?= h($best_site['nom']) ?></div>
    <div><span class="hc-badge hc-blue">Moy <?= $best_site['moy_vh'] ?> v/h</span></div>
    <?php else: ?>
    <div class="hc-num" style="color:#94a3b8">—</div>
    <?php endif; ?>
  </div>

  <!-- Films utilisés -->
  <div class="hero-card">
    <div class="hc-label">Films utilisés</div>
    <div class="hc-top"><?= h($mois_display) ?></div>
    <div class="hc-num"><?= number_format($films_mois, 0, ',', ' ') ?></div>
    <div class="hc-name">films ce mois</div>
    <?php
    $films_delta = $films_mois - $films_mois_prec;
    $films_trend = $films_mois_prec > 0 ? round(abs($films_delta) / $films_mois_prec * 100, 1) : null;
    ?>
    <?php if ($films_trend !== null): ?>
    <div><span class="hc-badge <?= $films_delta >= 0 ? 'hc-green' : 'hc-orange' ?>">
      <?= $films_delta >= 0 ? '↑' : '↓' ?> <?= $films_trend ?>%
    </span></div>
    <?php endif; ?>
  </div>

  <!-- Points validés -->
  <div class="hero-card">
    <div class="hc-label">Points journaliers</div>
    <div class="hc-top">Validés / Total</div>
    <div class="hc-num"><?= ($ops['points_valides']??0) ?><span style="font-size:16px;color:#94a3b8;font-weight:600">/<?= ($ops['total_points']??0) ?></span></div>
    <div class="hc-name">points ce mois</div>
    <?php if ($points_attente > 0): ?>
    <div><span class="hc-badge hc-orange"><i class="ph-duotone ph-clock"></i> <?= $points_attente ?> en attente</span></div>
    <?php else: ?>
    <div><span class="hc-badge hc-green">Tous traités</span></div>
    <?php endif; ?>
  </div>

  <!-- Taux validation -->
  <div class="hero-card">
    <div class="hc-label">Taux validation</div>
    <div class="hc-top">Points approuvés</div>
    <div class="hc-num"><?= $tx_valid ?><span style="font-size:18px;color:#94a3b8;font-weight:600">%</span></div>
    <div style="margin-top:10px">
      <div style="background:#f1f5f9;border-radius:4px;height:6px;overflow:hidden">
        <div style="height:100%;width:<?= $tx_valid ?>%;background:<?= $tx_valid>=80?'#16a34a':($tx_valid>=50?'#d97706':'#dc2626') ?>;border-radius:4px;transition:.6s"></div>
      </div>
    </div>
    <div><span class="hc-badge <?= $tx_valid>=80?'hc-green':($tx_valid>=50?'hc-orange':'hc-red') ?>"><?= $tx_valid>=80?'Excellent':($tx_valid>=50?'Moyen':'Faible') ?></span></div>
  </div>
</div>

<!-- ══════════ WIDGET PERFORMANCE PAR SITE ══════════ -->
<div class="pfw-card">
  <!-- Top : sélecteur site + filtres trimestre -->
  <div class="pfw-top">
    <div>
      <div class="pfw-site-lbl">Performance mensuelle par site</div>
      <div class="pfw-site-sel">
        <select id="pfwSiteSelect" onchange="pfwChangeSite(+this.value)">
          <?php foreach ($pfw_sites_json ? json_decode($js_pfw_sites, true) : [] as $i => $sj): ?>
          <option value="<?= $i ?>"><?= h($sj['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="pfw-site-arr">↓</span>
      </div>
    </div>
    <div class="pfw-quarters">
      <?php foreach ([1=>'T1',2=>'T2',3=>'T3',4=>'T4'] as $q => $ql): ?>
      <button class="pfw-q<?= $q===$pfw_quarter_def?' active':'' ?>" data-q="<?= $q ?>" onclick="pfwChangeQ(this)"><?= $ql ?></button>
      <?php endforeach; ?>
      <button onclick="document.getElementById('modal-ops').style.display='flex'"
        style="padding:7px 13px;border:1.5px solid #e2e8f0;border-radius:20px;background:#f8fafc;font-size:12px;font-weight:700;color:#06033A;cursor:pointer;font-family:inherit;white-space:nowrap;margin-left:6px">
        <i class="ph-duotone ph-arrows-out"></i> Détails
      </button>
    </div>
  </div>

  <!-- Corps : panneau coloré gauche + graphique droit -->
  <div class="pfw-body">
    <!-- Panneau coloré -->
    <div class="pfw-left" id="pfwLeft" style="background:#1B75BC">
      <div class="pfw-vert">Moy. mensuelle</div>
      <div class="pfw-stats">
        <div>
          <div class="pfw-stat-lbl">Moy. production</div>
          <div class="pfw-stat-val" id="pfwMoy">—</div>
        </div>
        <div>
          <div class="pfw-stat-lbl">Films utilisés</div>
          <div class="pfw-stat-val" id="pfwFilms">—</div>
        </div>
        <div>
          <div class="pfw-stat-lbl">Stock films rest.</div>
          <div class="pfw-stat-val" id="pfwStock">—</div>
        </div>
      </div>
    </div>
    <!-- Zone graphique -->
    <div class="pfw-right">
      <?php if (empty($prod_par_site)): ?>
      <div class="pfw-empty">Aucune donnée disponible</div>
      <?php else: ?>
      <canvas id="pfwChart" height="230"></canvas>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════ CHARTS ROW ══════════ -->
<div class="charts-row">
  <!-- Évolution 6 mois -->
  <div class="ch-box">
    <div class="ch-ttl">Évolution engins posés</div>
    <div class="ch-sub">6 derniers mois</div>
    <canvas id="cEvol" height="170"></canvas>
  </div>

  <!-- Bobines donut -->
  <div class="ch-box">
    <div class="ch-ttl">État des bobines</div>
    <div class="ch-sub">Stock total · <?= ($bobines_stats['total']??0) ?> bobines</div>
    <div class="donut-wrap">
      <canvas id="cBobines" width="120" height="120" style="width:120px!important;flex-shrink:0"></canvas>
      <div class="leg-list">
        <div class="leg-item"><div class="leg-dot" style="background:#7c3aed"></div>En cours<span class="leg-val"><?= $bobines_stats['en_cours']??0 ?></span></div>
        <div class="leg-item"><div class="leg-dot" style="background:#1B75BC"></div>En stock<span class="leg-val"><?= $bobines_stats['en_stock']??0 ?></span></div>
        <div class="leg-item"><div class="leg-dot" style="background:#e2e8f0"></div>Épuisées<span class="leg-val"><?= $bobines_stats['epuisees']??0 ?></span></div>
        <div style="margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;font-size:11px;color:#94a3b8">
          Films restants : <strong style="color:#06033A"><?= number_format((int)($bobines_stats['films_restants']??0),0,',',' ') ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Commandes donut -->
  <div class="ch-box">
    <div class="ch-ttl">Commandes articles</div>
    <div class="ch-sub">État en temps réel</div>
    <div class="donut-wrap" style="margin-top:4px">
      <canvas id="cCmds" width="120" height="120" style="width:120px!important;flex-shrink:0"></canvas>
      <div class="leg-list">
        <div class="leg-item"><div class="leg-dot" style="background:#d97706"></div>En attente<span class="leg-val"><?= $cmd_stats['en_attente']??0 ?></span></div>
        <div class="leg-item"><div class="leg-dot" style="background:#1B75BC"></div>En livraison<span class="leg-val"><?= ($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0) ?></span></div>
        <div class="leg-item"><div class="leg-dot" style="background:#16a34a"></div>Livrées ce mois<span class="leg-val"><?= $cmd_stats['livrees_mois']??0 ?></span></div>
        <div style="margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;font-size:11px;color:#94a3b8">
          Rivets posés : <strong style="color:#06033A"><?= number_format((int)($ops['rivets_utilises']??0),0,',',' ') ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════ BOTTOM ROW : ALERTES + DEMANDES ══════════ -->
<div class="bottom-row">

  <!-- Demandes internes -->
  <div class="card">
    <div class="card-ttl">Demandes internes</div>
    <div class="card-sub">Validation administrative en cours</div>
    <div class="kpi-mini">
      <div class="kpi-m">
        <div class="kpi-m-val" style="color:<?= $di_pending>0?'#d97706':'#16a34a' ?>"><?= $di_pending ?></div>
        <div class="kpi-m-lbl">En attente</div>
      </div>
      <div class="kpi-m">
        <div class="kpi-m-val" style="color:#1B75BC"><?= $di_mois ?></div>
        <div class="kpi-m-lbl">Soumises ce mois</div>
      </div>
      <div class="kpi-m">
        <div class="kpi-m-val" style="color:#16a34a"><?= $di_approuv ?></div>
        <div class="kpi-m-lbl">Approuvées</div>
      </div>
      <div class="kpi-m">
        <div class="kpi-m-val" style="color:<?= $di_tx>=75?'#16a34a':($di_tx>=40?'#d97706':'#dc2626') ?>"><?= $di_tx ?>%</div>
        <div class="kpi-m-lbl">Taux approbation</div>
      </div>
    </div>

    <?php if (!empty($di_recents)): ?>
    <table class="dtbl">
      <thead><tr><th>Réf.</th><th>Type</th><th>Demandeur</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($di_recents as $dr): ?>
      <tr onclick="location.href='<?= APP_URL ?>/pages/demandes.php?id=<?= (int)$dr['id'] ?>'" style="cursor:pointer;transition:.15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <td style="font-weight:700;color:#3B4FBE"><?= h($dr['numero'] ?? '—') ?></td>
        <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($dr['type_lbl']) ?></td>
        <td><?= h($dr['demandeur']) ?></td>
        <td><span class="d-statut <?= $dr['statut']==='en_cours'?'ds-enc':'ds-att' ?>"><?= $dr['statut']==='en_cours'?'En cours':'En attente' ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div style="text-align:center;padding:24px;color:#94a3b8;font-size:13px">
      <i class="ph-duotone ph-check-circle" style="font-size:32px;display:block;margin-bottom:8px;color:#bbf7d0"></i>
      Aucune demande en attente
    </div>
    <?php endif; ?>
  </div>

  <!-- Points + Stock alertes -->
  <div class="card">
    <div class="card-ttl">Alertes &amp; Suivi opérationnel</div>
    <div class="card-sub">Points en attente · Stock bas</div>

    <?php if (!empty($pts_attente)): ?>
    <table class="atbl">
      <thead><tr><th>Site</th><th>Date</th><th>Point</th><th>Coordinateur</th></tr></thead>
      <tbody>
      <?php foreach ($pts_attente as $pt): ?>
      <tr>
        <td style="font-weight:700;color:var(--navy,#06033A)"><?= h($pt['site']) ?></td>
        <td style="color:#94a3b8;white-space:nowrap"><?= fmt_date($pt['date_point']) ?></td>
        <td><span class="type-pill"><?= ['point_9h'=>'9h','point_13h'=>'13h','point_18h'=>'18h'][$pt['type_point']] ?? $pt['type_point'] ?></span></td>
        <td style="color:#94a3b8"><?= h($pt['coord']??'—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13px;border-bottom:1px solid #f1f5f9;margin-bottom:14px">
      <i class="ph-duotone ph-check-circle" style="font-size:28px;display:block;margin-bottom:6px;color:#bbf7d0"></i>
      Aucun point en attente de validation
    </div>
    <?php endif; ?>

    <!-- Stock résumé rapide -->
    <div style="margin-top:<?= empty($pts_attente)?'0':'18px' ?>">
      <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px">Résumé stock</div>
      <div class="stock-stat-row">
        <span style="color:#06033A">Bobines actives</span>
        <span class="stock-val" style="color:#7c3aed"><?= $bobines_stats['en_cours']??0 ?></span>
      </div>
      <div class="stock-stat-row">
        <span style="color:#06033A">Films restants (total)</span>
        <span class="stock-val" style="color:<?= ($bobines_stats['films_restants']??0)<500?'#dc2626':'#16a34a' ?>"><?= number_format((int)($bobines_stats['films_restants']??0),0,',',' ') ?></span>
      </div>
      <div class="stock-stat-row">
        <span style="color:#06033A">Articles stock bas</span>
        <span class="stock-val" style="color:<?= $alertes_stock>0?'#dc2626':'#16a34a' ?>"><?= $alertes_stock ?></span>
      </div>
      <div class="stock-stat-row">
        <span style="color:#06033A">Sites rivets sous seuil</span>
        <span class="stock-val" style="color:<?= $rivets_bas>0?'#dc2626':'#16a34a' ?>"><?= $rivets_bas ?></span>
      </div>
      <div class="stock-stat-row">
        <span style="color:#06033A">Rivets gonflable total</span>
        <span class="stock-val" style="color:#0891b2"><?= number_format($riv_total_gonfl,0,',',' ') ?></span>
      </div>
      <div class="stock-stat-row">
        <span style="color:#06033A">Rivets éclaté total</span>
        <span class="stock-val" style="color:#06033A"><?= number_format($riv_total_eclat,0,',',' ') ?></span>
      </div>
    </div>

    <!-- PMMA + bouton films -->
    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
      <button onclick="openFilmsModal()" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 14px;background:#f5f3ff;border:1px solid #ede9fe;border-radius:10px;font-size:12px;font-weight:700;color:#7c3aed;cursor:pointer;white-space:nowrap">
        <i class="ph-duotone ph-chart-bar-horizontal"></i> Films par bobine
      </button>
      <button onclick="openPmmaModal()" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 14px;background:#ecfeff;border:1px solid #cffafe;border-radius:10px;font-size:12px;font-weight:700;color:#0891b2;cursor:pointer;white-space:nowrap">
        <i class="ph-duotone ph-printer"></i> PMMA par site
      </button>
    </div>
  </div>
</div>

</div><!-- /pdg -->

<!-- ════ MODALS ════ -->
<div id="modal-films" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(6,3,58,.5);align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:white;border-radius:18px;width:100%;max-width:680px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <div style="width:36px;height:36px;border-radius:12px;background:#f5f3ff;display:flex;align-items:center;justify-content:center;color:#7c3aed;font-size:18px"><i class="ph-duotone ph-chart-bar-horizontal"></i></div>
      <div>
        <div style="font-weight:800;font-size:14px;color:#06033A">Films utilisés par type de bobine</div>
        <div style="font-size:12px;color:#94a3b8"><?= h($mois_display) ?></div>
      </div>
      <button onclick="document.getElementById('modal-films').style.display='none'" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:24px;line-height:1;padding:4px 8px">&times;</button>
    </div>
    <div style="overflow:auto;padding:22px"><div style="width:100%;position:relative"><canvas id="cFilms" height="<?= $films_ch_h ?>"></canvas></div></div>
  </div>
</div>

<div id="modal-pmma" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(6,3,58,.5);align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:white;border-radius:18px;width:100%;max-width:680px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <div style="width:36px;height:36px;border-radius:12px;background:#ecfeff;display:flex;align-items:center;justify-content:center;color:#0891b2;font-size:18px"><i class="ph-duotone ph-printer"></i></div>
      <div>
        <div style="font-weight:800;font-size:14px;color:#06033A">Stock PMMA par type</div>
        <div style="font-size:12px;color:#94a3b8">Survoler pour le détail par site</div>
      </div>
      <button onclick="document.getElementById('modal-pmma').style.display='none'" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:24px;line-height:1;padding:4px 8px">&times;</button>
    </div>
    <div style="overflow:auto;padding:22px"><div style="width:100%;position:relative"><canvas id="cPmma" height="<?= $pmma_ch_h ?>"></canvas></div></div>
  </div>
</div>

<div id="modal-ops" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(6,3,58,.5);align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:white;border-radius:18px;width:100%;max-width:900px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <div style="width:36px;height:36px;border-radius:12px;background:#eef0f8;display:flex;align-items:center;justify-content:center;color:#06033A;font-size:18px"><i class="ph-duotone ph-table"></i></div>
      <div>
        <div style="font-weight:800;font-size:14px;color:#06033A">Détails par site — Opérations</div>
        <div style="font-size:12px;color:#94a3b8">Points journaliers · <?= h($mois_display) ?></div>
      </div>
      <button onclick="document.getElementById('modal-ops').style.display='none'" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:24px;line-height:1;padding:4px 8px">&times;</button>
    </div>
    <div style="overflow:auto;padding:0 22px 22px">
      <table class="ptbl" style="margin-top:0">
        <thead><tr>
          <th style="text-align:left">Site</th>
          <th style="min-width:110px;text-align:left">Progression</th>
          <th>Engins</th><th>Plaques</th><th>Moy V/H</th><th>Points</th><th>Statut</th>
        </tr></thead>
        <tbody>
        <?php foreach($prod_par_site as $i=>$s):
          $pct = $max_engins > 0 ? round($s['engins'] / $max_engins * 100) : 0;
          $col = $SC[$i % count($SC)];
          $mvh = (float)$s['moy_vh'];
          $mvh_cls = $mvh>=10?'mvh-g':($mvh>=5?'mvh-o':'mvh-r');
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:8px;height:8px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
              <div>
                <div style="font-weight:700;color:#06033A"><?= h($s['nom']) ?></div>
                <div style="font-size:11px;color:#94a3b8"><?= h($s['type']??'') ?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;background:#e2e8f0;border-radius:3px;height:5px;overflow:hidden">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $col ?>;border-radius:3px"></div>
              </div>
              <span style="font-size:11px;color:#94a3b8;min-width:30px"><?= $pct ?>%</span>
            </div>
          </td>
          <td style="font-weight:800;font-family:'Montserrat',sans-serif;color:#06033A"><?= number_format((int)$s['engins'],0,',',' ') ?></td>
          <td style="font-weight:700;color:#1B75BC"><?= number_format((int)$s['plaques'],0,',',' ') ?></td>
          <td><span class="mvh <?= $mvh_cls ?>"><?= $mvh ?></span></td>
          <td style="color:#94a3b8"><?= $s['nb_points'] ?></td>
          <td>
            <?php if($s['en_attente']>0): ?>
              <span class="type-pill"><?= $s['en_attente'] ?> att.</span>
            <?php elseif($s['nb_points']>0): ?>
              <i class="ph-duotone ph-check-circle" style="color:#16a34a;font-size:18px"></i>
            <?php else: ?>
              <span style="color:#94a3b8">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="pdg-tip" style="display:none;position:fixed;z-index:3000;background:white;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;box-shadow:0 8px 24px rgba(0,0,0,.13);pointer-events:none;min-width:180px;font-size:12px;line-height:1.7"></div>

<script>
const evolLabels  = <?= $js_evol_labels ?>;
const evolEngins  = <?= $js_evol_engins ?>;
const evolPlaques = <?= $js_evol_plaques ?>;
const filmsLabels = <?= $js_films_labels ?>;
const filmsValues = <?= $js_films_values ?>;
const filmsDetail = <?= $js_films_detail ?>;
const bobinesData = <?= $js_bobines ?>;
const cmdsData    = <?= $js_cmds ?>;
const pmmaLabels  = <?= $js_pmma_labels ?>;
const pmmaTotals  = <?= $js_pmma_totals ?>;
const pmmaBas     = <?= $js_pmma_bas ?>;
const pmmaDetail  = <?= $js_pmma_detail ?>;
const DPR = window.devicePixelRatio || 1;

function fmtN(n) { return Number(n).toLocaleString('fr-FR'); }

function initCv(id) {
    const el = document.getElementById(id); if (!el) return null;
    const w  = el.parentElement.getBoundingClientRect().width || 400;
    const h  = parseInt(el.getAttribute('height') || 200);
    el.width  = Math.round(w * DPR);
    el.height = Math.round(h * DPR);
    el.style.width  = Math.round(w) + 'px';
    el.style.height = h + 'px';
    const ctx = el.getContext('2d');
    ctx.scale(DPR, DPR);
    return {ctx, w: Math.round(w), h};
}

// ── Line/Bar combo chart (évolution)
function drawEvol() {
    const c = initCv('cEvol'); if (!c) return;
    const {ctx, w, h} = c;
    const pad = {t:20, r:12, b:32, l:40};
    const cW = w - pad.l - pad.r, cH = h - pad.t - pad.b;
    const n  = evolLabels.length;
    if (!n) {
        ctx.fillStyle='#94a3b8'; ctx.font='12px DM Sans,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText('Aucune donnée', w/2, h/2); return;
    }
    const max = Math.max(...evolEngins, 1);
    const step = cW / Math.max(n - 1, 1);

    // Grid lines
    ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = pad.t + cH * (1 - i / 4);
        ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(w - pad.r, y); ctx.stroke();
        ctx.fillStyle = '#94a3b8'; ctx.font = '10px DM Sans,sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        ctx.fillText(Math.round(max * i / 4), pad.l - 5, y);
    }

    // Area fill under line
    ctx.beginPath();
    evolEngins.forEach((v, i) => {
        const x = pad.l + i * step;
        const y = pad.t + cH * (1 - v / max);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.lineTo(pad.l + (n-1) * step, pad.t + cH);
    ctx.lineTo(pad.l, pad.t + cH);
    ctx.closePath();
    const grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + cH);
    grad.addColorStop(0, 'rgba(27,117,188,.18)');
    grad.addColorStop(1, 'rgba(27,117,188,.02)');
    ctx.fillStyle = grad;
    ctx.fill();

    // Line
    ctx.beginPath();
    ctx.strokeStyle = '#1B75BC'; ctx.lineWidth = 2.5; ctx.lineJoin = 'round';
    evolEngins.forEach((v, i) => {
        const x = pad.l + i * step;
        const y = pad.t + cH * (1 - v / max);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.stroke();

    // Dots + values
    evolEngins.forEach((v, i) => {
        const x = pad.l + i * step;
        const y = pad.t + cH * (1 - v / max);
        ctx.beginPath(); ctx.arc(x, y, 4, 0, Math.PI*2);
        ctx.fillStyle = '#fff'; ctx.fill();
        ctx.strokeStyle = '#1B75BC'; ctx.lineWidth = 2; ctx.stroke();
        if (v > 0) {
            ctx.fillStyle = '#06033A'; ctx.font = 'bold 10px DM Sans,sans-serif';
            ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
            ctx.fillText(fmtN(v), x, y - 6);
        }
        ctx.fillStyle = '#94a3b8'; ctx.font = '10px DM Sans,sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        ctx.fillText(evolLabels[i] || '', x, pad.t + cH + 6);
    });
}

// ── Donut
function drawDonut(id, values, colors) {
    const el = document.getElementById(id); if (!el) return;
    const sz = parseInt(el.getAttribute('width') || 120);
    el.width = sz*DPR; el.height = sz*DPR;
    el.style.width = sz+'px'; el.style.height = sz+'px';
    const ctx = el.getContext('2d');
    ctx.scale(DPR, DPR);
    const cx=sz/2, cy=sz/2, R=sz/2-5, r=R*0.58;
    const total = values.reduce((a,b)=>a+b, 0);
    if (!total) {
        ctx.fillStyle='#f1f5f9';
        ctx.beginPath(); ctx.arc(cx,cy,R,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#fff';
        ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#94a3b8'; ctx.font='bold 12px Montserrat,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText('0', cx, cy); return;
    }
    let angle = -Math.PI/2;
    values.forEach((v,i) => {
        if (!v) return;
        const slice = (v/total)*Math.PI*2;
        ctx.fillStyle = colors[i];
        ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,R,angle,angle+slice); ctx.closePath(); ctx.fill();
        angle += slice;
    });
    ctx.fillStyle='#fff';
    ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
    ctx.fillStyle='#06033A'; ctx.font='bold 14px Montserrat,sans-serif';
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(total, cx, cy);
}

// ── Horizontal bar (films + PMMA)
function drawHBar(id, labels, values, color, detail, basList) {
    const c = initCv(id); if (!c) return;
    const {ctx, w, h} = c;
    if (!labels.length) {
        ctx.fillStyle='#94a3b8'; ctx.font='12px DM Sans,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText('Aucune donnée', w/2, h/2); return;
    }
    const lw = 120, rw = 46;
    const bArea = w - lw - rw;
    const rowH = h / labels.length;
    const max = Math.max(...values, 1);
    labels.forEach((lbl, i) => {
        const y   = i * rowH;
        const bh  = Math.min(rowH * 0.42, 20);
        const by  = y + (rowH - bh) / 2;
        const tot = values[i];
        const bw  = Math.max((tot/max)*bArea, tot>0?4:0);
        const low = basList && basList[i] > 0;
        ctx.fillStyle = low ? '#dc2626' : '#06033A';
        ctx.font = '11px DM Sans,sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        const short = lbl.length > 16 ? lbl.substring(0,16)+'…' : lbl;
        ctx.fillText(short, lw - 8, y + rowH/2);
        ctx.fillStyle = low ? '#dc2626' : color;
        if (tot > 0) ctx.fillRect(lw, by, bw, bh);
        ctx.fillStyle = '#64748b'; ctx.font = '10px DM Sans,sans-serif';
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.fillText(fmtN(tot), lw + bw + 4, y + rowH/2);
    });
}

// ── Tooltip
function showTip(e, html) {
    const tip = document.getElementById('pdg-tip');
    tip.innerHTML = html; tip.style.display = 'block';
    const tw=tip.offsetWidth, th=tip.offsetHeight;
    const x = e.clientX+14, y = e.clientY-10;
    tip.style.left = (x+tw>window.innerWidth-8 ? x-tw-28 : x)+'px';
    tip.style.top  = (y+th>window.innerHeight-8 ? y-th+20 : y)+'px';
}
function hideTip() { document.getElementById('pdg-tip').style.display='none'; }

// ══════ WIDGET PERFORMANCE PAR SITE ══════
const pfwSites = <?= $js_pfw_sites ?>;
let pfwSiteIdx  = 0;
let pfwQuarter  = <?= $pfw_quarter_def ?>;
const pfwAnnee  = '<?= $annee ?>';
const pfwQMonths = {1:['01','02','03'],2:['04','05','06'],3:['07','08','09'],4:['10','11','12']};
const pfwMLbls   = {'01':'Jan','02':'Fév','03':'Mar','04':'Avr','05':'Mai','06':'Juin','07':'Juil','08':'Aoû','09':'Sep','10':'Oct','11':'Nov','12':'Déc'};

function pfwChangeSite(idx) { pfwSiteIdx = idx; pfwUpdate(); }
function pfwChangeQ(btn) {
    document.querySelectorAll('.pfw-q').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    pfwQuarter = +btn.dataset.q;
    pfwUpdate();
}

function hexRgba(hex, a) {
    const r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${a})`;
}
function rrect(ctx, x, y, w, h, r) {
    r = Math.min(r, w/2, h/2);
    ctx.beginPath();
    ctx.moveTo(x+r, y);
    ctx.arcTo(x+w, y, x+w, y+h, r);
    ctx.arcTo(x+w, y+h, x, y+h, r);
    ctx.arcTo(x, y+h, x, y, r);
    ctx.arcTo(x, y, x+w, y, r);
    ctx.closePath();
}

function pfwUpdate() {
    if (!pfwSites.length) return;
    const site = pfwSites[pfwSiteIdx];
    // Panneau gauche
    document.getElementById('pfwLeft').style.background = site.color;
    document.getElementById('pfwMoy').textContent   = site.moy_vh ? site.moy_vh + ' v/h' : '—';
    document.getElementById('pfwFilms').textContent = fmtN(site.films_mois);
    document.getElementById('pfwStock').textContent = fmtN(site.films_rest);
    // Graphique
    pfwDrawChart(site);
}

function pfwDrawChart(site) {
    const el = document.getElementById('pfwChart'); if (!el) return;
    const container = el.parentElement;
    const W = container.getBoundingClientRect().width || 500;
    const H = 230;
    el.width  = Math.round(W * DPR);
    el.height = Math.round(H * DPR);
    el.style.width  = Math.round(W) + 'px';
    el.style.height = H + 'px';
    const ctx = el.getContext('2d');
    ctx.scale(DPR, DPR);

    const months = pfwQMonths[pfwQuarter];
    const enginVals = months.map(m => { const k=pfwAnnee+'-'+m; return site.monthly[k]?site.monthly[k].engins:0; });
    const filmVals  = months.map(m => { const k=pfwAnnee+'-'+m; return site.monthly[k]?site.monthly[k].films:0; });

    const pad = {t:48, r:58, b:36, l:18};
    const cW = W - pad.l - pad.r;
    const cH = H - pad.t - pad.b;
    const n  = months.length;
    const slot = cW / n;
    const gap  = 10;
    const bW   = Math.min(slot * 0.55, 80);
    const b1W  = (bW - gap) / 2; // films (hatched)
    const b2W  = (bW - gap) / 2; // engins (solid)
    const col  = site.color;

    const maxE = Math.max(...enginVals, 1);
    const maxF = Math.max(...filmVals,  1);

    // Grille + axe droit (engins)
    const yVals = [0, Math.round(maxE*0.33), Math.round(maxE*0.67), maxE];
    yVals.forEach((v, i) => {
        const y = pad.t + cH * (1 - i / (yVals.length-1));
        ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(W-pad.r+6, y); ctx.stroke();
        ctx.fillStyle = '#cbd5e1'; ctx.font = '10px DM Sans,sans-serif';
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.fillText(fmtN(v), W-pad.r+10, y);
    });

    months.forEach((m, i) => {
        const bx = pad.l + slot*i + (slot - bW) / 2;
        const ev = enginVals[i];
        const fv = filmVals[i];

        // ── Bar films (hatché gauche)
        const fh = Math.max(4, (fv/maxF) * cH);
        const fy = pad.t + cH - fh;
        // fond léger
        ctx.fillStyle = hexRgba(col, 0.12);
        rrect(ctx, bx, fy, b1W, fh, [6,6,0,0]); ctx.fill();
        // hachures diagonales
        ctx.save();
        rrect(ctx, bx, fy, b1W, fh, [6,6,0,0]); ctx.clip();
        ctx.strokeStyle = hexRgba(col, 0.45); ctx.lineWidth = 1.5;
        for (let d = -fh; d < b1W+fh; d += 9) {
            ctx.beginPath(); ctx.moveTo(bx+d, fy+fh); ctx.lineTo(bx+d+fh, fy); ctx.stroke();
        }
        ctx.restore();
        // pill valeur films
        if (fv > 0) {
            const lbl = fmtN(fv);
            ctx.font = 'bold 10px DM Sans,sans-serif';
            const lw = ctx.measureText(lbl).width + 12;
            const lh = 19; const lx = bx + (b1W-lw)/2; const ly = fy-lh-4;
            ctx.fillStyle = hexRgba(col, 0.75);
            rrect(ctx, lx, ly, lw, lh, 5); ctx.fill();
            ctx.fillStyle = '#fff'; ctx.textAlign='center'; ctx.textBaseline='middle';
            ctx.fillText(lbl, bx+b1W/2, ly+lh/2);
        }

        // ── Bar engins (solide droite)
        const ex = bx + b1W + gap;
        const eh = Math.max(4, (ev/maxE) * cH);
        const ey = pad.t + cH - eh;
        ctx.fillStyle = ev > 0 ? col : '#f1f5f9';
        rrect(ctx, ex, ey, b2W, eh, [6,6,0,0]); ctx.fill();
        // pill valeur engins
        if (ev > 0) {
            const lbl = fmtN(ev);
            ctx.font = 'bold 10px DM Sans,sans-serif';
            const lw = ctx.measureText(lbl).width + 12;
            const lh = 19; const lx = ex + (b2W-lw)/2; const ly = ey-lh-4;
            ctx.fillStyle = col;
            rrect(ctx, lx, ly, lw, lh, 5); ctx.fill();
            ctx.fillStyle = '#fff'; ctx.textAlign='center'; ctx.textBaseline='middle';
            ctx.fillText(lbl, ex+b2W/2, ly+lh/2);
        }

        // Label mois centré sous la paire
        ctx.fillStyle = '#94a3b8'; ctx.font = '11px DM Sans,sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        ctx.fillText(pfwMLbls[m], bx+bW/2, pad.t+cH+8);
    });

    // Légende
    const legY = 6;
    [['Films (▨)', hexRgba(col,0.55)],['Engins (■)', col]].forEach(([lbl, c], i) => {
        const lx = pad.l + i*130;
        ctx.fillStyle = c; ctx.fillRect(lx, legY+3, 10, 10);
        ctx.fillStyle = '#64748b'; ctx.font='10px DM Sans,sans-serif';
        ctx.textAlign='left'; ctx.textBaseline='middle';
        ctx.fillText(lbl, lx+14, legY+8);
    });
}

window.addEventListener('load', () => { if (pfwSites.length) setTimeout(pfwUpdate, 80); });
window.addEventListener('resize', () => { clearTimeout(window._prt); window._prt=setTimeout(pfwUpdate,200); });

function openFilmsModal() {
    document.getElementById('modal-films').style.display='flex';
    setTimeout(() => {
        drawHBar('cFilms', filmsLabels, filmsValues, '#7c3aed', filmsDetail, null);
        const ef = document.getElementById('cFilms');
        if (ef && filmsLabels.length) {
            ef.style.cursor='crosshair';
            ef.addEventListener('mousemove', e => {
                const rect=ef.getBoundingClientRect();
                const idx=Math.floor((e.clientY-rect.top)/(rect.height/filmsLabels.length));
                if (idx>=0 && idx<filmsLabels.length) {
                    const d=filmsDetail[idx]||[];
                    let html=`<div style="font-weight:700;color:#06033A;margin-bottom:6px">${filmsLabels[idx]}</div>`;
                    d.forEach(s=>{html+=`<div style="display:flex;justify-content:space-between;gap:16px"><span>${s.site}</span><span style="font-weight:700">${fmtN(s.films)}</span></div>`;});
                    if(!d.length) html+=`<div style="color:#94a3b8">Aucune donnée</div>`;
                    showTip(e, html);
                } else hideTip();
            });
            ef.addEventListener('mouseleave', hideTip);
        }
    }, 40);
}

function openPmmaModal() {
    document.getElementById('modal-pmma').style.display='flex';
    setTimeout(() => {
        drawHBar('cPmma', pmmaLabels, pmmaTotals, '#0891b2', pmmaDetail, pmmaBas);
        const ep = document.getElementById('cPmma');
        if (ep && pmmaLabels.length) {
            ep.style.cursor='crosshair';
            ep.addEventListener('mousemove', e => {
                const rect=ep.getBoundingClientRect();
                const idx=Math.floor((e.clientY-rect.top)/(rect.height/pmmaLabels.length));
                if (idx>=0 && idx<pmmaLabels.length) {
                    const d=pmmaDetail[idx]||[];
                    let html=`<div style="font-weight:700;color:#06033A;margin-bottom:6px">${pmmaLabels[idx]}</div>`;
                    d.forEach(s=>{
                        const low=s.qty<s.seuil;
                        html+=`<div style="display:flex;justify-content:space-between;gap:14px;color:${low?'#dc2626':'#374151'}">
                            <span>${s.site||'—'}</span><span style="font-weight:700">${fmtN(s.qty)}</span></div>`;
                    });
                    if(pmmaBas[idx]>0) html+=`<div style="margin-top:5px;color:#dc2626;font-size:11px;font-weight:600">⚠ ${pmmaBas[idx]} site(s) stock bas</div>`;
                    showTip(e, html);
                } else hideTip();
            });
            ep.addEventListener('mouseleave', hideTip);
        }
    }, 40);
}

function render() {
    drawEvol();
    drawDonut('cBobines', bobinesData, ['#7c3aed','#1B75BC','#e2e8f0']);
    drawDonut('cCmds',    cmdsData,    ['#d97706','#1B75BC','#16a34a']);
}

window.addEventListener('load',   () => { setTimeout(render, 80); });
window.addEventListener('resize', () => { clearTimeout(window._rt); window._rt=setTimeout(render,200); });
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
