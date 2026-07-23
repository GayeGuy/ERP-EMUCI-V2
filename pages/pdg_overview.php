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

// ── OPÉRATIONS
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

$films_par_site = db_fetch_all(
    "SELECT s.nom, COALESCE(SUM(fu.films_utilises),0) AS films
     FROM sites s
     LEFT JOIN op_points_journaliers p ON p.site_id=s.id AND TO_CHAR(p.date_point,'YYYY-MM')=?
     LEFT JOIN op_films_utilises fu ON fu.point_id=p.id
     WHERE s.actif=1
     GROUP BY s.id, s.nom ORDER BY films DESC",
    [$mois]
);

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
    "SELECT sp.type_pmma,
            COALESCE(SUM(sp.quantite),0) AS total,
            COUNT(CASE WHEN sp.quantite < sp.seuil_alerte THEN 1 END) AS nb_bas
     FROM stock_pmma_site sp
     JOIN sites s ON s.id=sp.site_id
     WHERE s.actif=1
     GROUP BY sp.type_pmma ORDER BY total DESC"
);
$pmma_detail = db_fetch_all(
    "SELECT s.nom, sp.type_pmma, COALESCE(sp.quantite,0) AS quantite, COALESCE(sp.seuil_alerte,10) AS seuil
     FROM sites s
     JOIN stock_pmma_site sp ON sp.site_id=s.id
     WHERE s.actif=1 ORDER BY sp.type_pmma, s.nom"
);

// ── ALERTES
$alertes_stock  = (int)db_fetch_value("SELECT COUNT(*) FROM articles WHERE stock_global <= seuil_alerte AND seuil_alerte > 0");
$rivets_bas     = (int)db_fetch_value("SELECT COUNT(*) FROM op_stock_rivets WHERE quantite < 200");
$points_attente = (int)($ops['points_attente'] ?? 0);
$cmd_en_attente = (int)($cmd_stats['en_attente'] ?? 0);
$total_alertes  = $points_attente + $cmd_en_attente + $alertes_stock + $rivets_bas;

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
     ORDER BY p.date_point DESC LIMIT 10"
);

// ── JS DATA
$js_evol_labels  = json_encode(array_map(fn($r) => ($mc[substr($r['mois'],5,2)]??'').' '.substr($r['mois'],2,2), $evol));
$js_evol_engins  = json_encode(array_map(fn($r) => (int)$r['engins'], $evol));
$js_evol_plaques = json_encode(array_map(fn($r) => (int)$r['plaques'], $evol));
$js_statuts      = json_encode([(int)($ops['points_valides']??0),(int)($ops['points_attente']??0),(int)($ops['points_rejetes']??0)]);
$js_films_labels = json_encode(array_map(fn($r) => $r['nom'], $films_par_site));
$js_films_values = json_encode(array_map(fn($r) => (int)$r['films'], $films_par_site));
$js_bobines      = json_encode([(int)($bobines_stats['en_cours']??0),(int)($bobines_stats['en_stock']??0),(int)($bobines_stats['epuisees']??0)]);
$js_cmds         = json_encode([(int)($cmd_stats['en_attente']??0),(int)(($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0)),(int)($cmd_stats['livrees_mois']??0)]);
$max_engins      = empty($prod_par_site) ? 1 : max(1, ...array_column($prod_par_site, 'engins'));
// PMMA + Rivets charts
$pmma_by_type = [];
foreach ($pmma_detail as $d) $pmma_by_type[$d['type_pmma']][] = ['site'=>$d['nom'],'qty'=>(int)$d['quantite'],'seuil'=>(int)$d['seuil']];
$js_pmma_labels = json_encode(array_map(fn($r)=>$r['type_pmma'], $pmma_par_type));
$js_pmma_totals = json_encode(array_map(fn($r)=>(int)$r['total'], $pmma_par_type));
$js_pmma_bas    = json_encode(array_map(fn($r)=>(int)$r['nb_bas'], $pmma_par_type));
$js_pmma_detail = json_encode(array_values(array_map(fn($r)=>$pmma_by_type[$r['type_pmma']]??[], $pmma_par_type)));
$riv_type_detail = [[], []];
$riv_total_gonfl = 0; $riv_total_eclat = 0;
foreach ($rps as $site => $types) {
    $g = (int)($types['gonflable'] ?? 0);
    $e = (int)($types['eclate'] ?? 0);
    $riv_total_gonfl += $g; $riv_total_eclat += $e;
    $riv_type_detail[0][] = ['site' => $site, 'qty' => $g];
    $riv_type_detail[1][] = ['site' => $site, 'qty' => $e];
}
$js_riv_labels  = json_encode(['Gonflable', 'Éclaté']);
$js_riv_totals  = json_encode([$riv_total_gonfl, $riv_total_eclat]);
$js_riv_detail  = json_encode($riv_type_detail);
$pmma_ch_h      = max(160, count($pmma_par_type) * 46);
$riv_ch_h       = 120;
$films_ch_h     = max(160, count($films_par_site) * 46);

include __DIR__ . '/../templates/header.php';
?>
<style>
/* ── PAGE */
.pdg-hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.pdg-title{font-family:'Montserrat',sans-serif;font-size:22px;font-weight:900;color:var(--navy)}
.pdg-sub{font-size:13px;color:var(--muted);margin-top:2px}

/* ── ALERTES BADGES */
.bdg{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600}
.bdg-warn{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.bdg-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}

/* ── BLOCS */
.bloc{background:white;border:1px solid var(--border);border-radius:16px;margin-bottom:22px;overflow:hidden}
.bloc-ops{border-top:4px solid #06033A}
.bloc-bob{border-top:4px solid #7c3aed}
.bloc-stock{border-top:4px solid #0891b2}
.bloc-cmd{border-top:4px solid #d97706}
.bloc-alrt{border-top:4px solid #dc2626}
.bloc-hdr{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border)}
.bloc-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.bloc-ico-navy{background:#eef0f8;color:#06033A}
.bloc-ico-purple{background:#f5f3ff;color:#7c3aed}
.bloc-ico-cyan{background:#ecfeff;color:#0891b2}
.bloc-ico-orange{background:#fffbeb;color:#d97706}
.bloc-ico-red{background:#fee2e2;color:#dc2626}
.bloc-ttl{font-family:'Montserrat',sans-serif;font-size:14px;font-weight:800;color:var(--navy)}
.bloc-stl{font-size:12px;color:var(--muted)}
.bloc-body{padding:20px}

/* ── KPI GRID */
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:12px;margin-bottom:20px}
.kc{background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:14px 16px}
.kc-val{font-family:'Montserrat',sans-serif;font-size:26px;font-weight:900;line-height:1;color:var(--navy)}
.kc-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-top:4px}
.kc-sub{font-size:11px;color:var(--muted);margin-top:3px}
.c-blue .kc-val{color:#1B75BC}
.c-green .kc-val{color:#16a34a}
.c-orange .kc-val{color:#d97706}
.c-red .kc-val{color:#dc2626}
.c-purple .kc-val{color:#7c3aed}

/* ── CHARTS */
.ch2{display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-bottom:20px}
@media(max-width:680px){.ch2{grid-template-columns:1fr}}
.ch-box{background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:16px}
.ch-ttl{font-size:12px;font-weight:700;color:var(--navy);margin-bottom:10px}
.ch-wrap{width:100%;position:relative}
.ch-wrap canvas{display:block;width:100%!important}
.donut-row{display:flex;align-items:center;justify-content:center;gap:20px}
.legend{display:flex;flex-direction:column;gap:7px}
.leg-item{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--muted)}
.leg-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}

/* ── SITE TABLE */
.stbl{width:100%;border-collapse:collapse;font-size:13px}
.stbl th{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;padding:7px 10px;border-bottom:2px solid var(--border);text-align:left;white-space:nowrap}
.stbl td{padding:10px;border-bottom:1px solid var(--border)}
.stbl tr:last-child td{border-bottom:none}
.stbl tr:nth-child(even) td{background:#f8fafc}
.pb-wrap{background:#e2e8f0;border-radius:4px;height:6px;overflow:hidden;min-width:60px}
.pb-fill{height:100%;border-radius:4px;background:#1B75BC}
.pill{display:inline-block;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700}
.p-green{background:#d1fae5;color:#065f46}
.p-orange{background:#fff7ed;color:#c2410c}
.p-blue{background:#dbeafe;color:#1e40af}

/* ── STOCK 2 COL */
.s2col{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:680px){.s2col{grid-template-columns:1fr}}
.s-bloc-ttl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px}
.s-card{background:#f8fafc;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:8px}
.s-card-hdr{background:var(--navy);padding:8px 12px;color:white;font-size:12px;font-weight:700;font-family:'Montserrat',sans-serif}
.s-card-body{padding:10px 12px}
.s-row{display:flex;justify-content:space-between;align-items:center;font-size:12px;padding:3px 0}
.s-val{font-weight:800;font-family:'Montserrat',sans-serif;font-size:15px}
.s-alrt{background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;margin-top:5px}

/* ── COMMANDES */
.cmd-wrap{display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center}
@media(max-width:600px){.cmd-wrap{grid-template-columns:1fr}}
.cmd-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.cmd-s{border-radius:10px;padding:16px;text-align:center;background:#f8fafc}
.cmd-s-val{font-family:'Montserrat',sans-serif;font-size:32px;font-weight:900;line-height:1}
.cmd-s-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-top:6px}

/* ── ALERT TABLE */
.atbl{width:100%;border-collapse:collapse;font-size:13px}
.atbl th{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;padding:6px 10px;border-bottom:2px solid var(--border);text-align:left}
.atbl td{padding:9px 10px;border-bottom:1px solid var(--border)}
.atbl tr:last-child td{border-bottom:none}
</style>

<!-- HEADER -->
<div class="pdg-hdr">
  <div>
    <div class="pdg-title">Vue Exécutive</div>
    <div class="pdg-sub">Express Multiservices CI — DigiStock</div>
  </div>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <?php if($total_alertes > 0): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php if($points_attente>0): ?><span class="bdg bdg-warn"><i class="ph-duotone ph-clock"></i> <?= $points_attente ?> pt(s) à valider</span><?php endif; ?>
      <?php if($cmd_en_attente>0): ?><span class="bdg bdg-warn"><i class="ph-duotone ph-package"></i> <?= $cmd_en_attente ?> commande(s)</span><?php endif; ?>
      <?php if($alertes_stock>0): ?><span class="bdg bdg-warn"><i class="ph-duotone ph-warning"></i> <?= $alertes_stock ?> stock(s) bas</span><?php endif; ?>
      <?php if($rivets_bas>0): ?><span class="bdg bdg-warn"><i class="ph-duotone ph-nut"></i> <?= $rivets_bas ?> rivets bas</span><?php endif; ?>
    </div>
    <?php else: ?>
    <span class="bdg bdg-ok"><i class="ph-duotone ph-check-circle"></i> Aucune alerte</span>
    <?php endif; ?>
    <form method="get" style="display:flex;align-items:center;gap:6px">
      <label style="font-size:11px;color:var(--muted);font-weight:700">Mois</label>
      <input type="month" name="mois" value="<?= h($mois) ?>"
             style="padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:white;outline:none"
             onchange="this.form.submit()">
    </form>
  </div>
</div>

<!-- ═══════════════════════════ BLOC OPÉRATIONS -->
<div class="bloc bloc-ops">
  <div class="bloc-hdr">
    <div class="bloc-ico bloc-ico-navy"><i class="ph-duotone ph-lightning"></i></div>
    <div><div class="bloc-ttl">Opérations</div><div class="bloc-stl">Points journaliers — <?= h($mois_display) ?></div></div>
    <button onclick="document.getElementById('modal-ops').style.display='flex'" style="margin-left:auto;display:flex;align-items:center;gap:6px;padding:7px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:700;color:#06033A;cursor:pointer;white-space:nowrap">
      <i class="ph-duotone ph-table"></i> Détails
    </button>
  </div>
  <div class="bloc-body">

    <div class="kpi-row">
      <div class="kc c-blue">
        <div class="kc-val"><?= fmt_number($ops['total_engins'] ?? 0) ?></div>
        <div class="kc-lbl">Engins posés</div>
        <div class="kc-sub">ce mois</div>
      </div>
      <div class="kc">
        <div class="kc-val"><?= fmt_number($ops['total_plaques'] ?? 0) ?></div>
        <div class="kc-lbl">Plaques posées</div>
        <div class="kc-sub">ce mois</div>
      </div>
      <div class="kc c-green">
        <div class="kc-val"><?= $ops['moy_prod'] ?? '0' ?></div>
        <div class="kc-lbl">Moy. V/H</div>
        <div class="kc-sub">véhicules / heure</div>
      </div>
      <div class="kc <?= ($ops['points_attente']??0)>0 ? 'c-orange' : 'c-green' ?>">
        <div class="kc-val"><?= ($ops['points_valides']??0) ?>/<?= ($ops['total_points']??0) ?></div>
        <div class="kc-lbl">Points validés</div>
        <div class="kc-sub"><?= $ops['points_attente']??0 ?> en attente</div>
      </div>
      <div class="kc">
        <div class="kc-val"><?= fmt_number($ops['rivets_utilises'] ?? 0) ?></div>
        <div class="kc-lbl">Rivets posés</div>
        <div class="kc-sub">ce mois</div>
      </div>
    </div>

    <div class="ch2">
      <div class="ch-box">
        <div class="ch-ttl">Évolution engins posés — 6 derniers mois</div>
        <div class="ch-wrap"><canvas id="cEvol" height="190"></canvas></div>
      </div>
      <div class="ch-box">
        <div class="ch-ttl">Statuts des points</div>
        <div class="donut-row" style="margin-top:10px">
          <canvas id="cStatuts" width="140" height="140" style="width:140px!important;flex-shrink:0"></canvas>
          <div class="legend">
            <div class="leg-item"><div class="leg-dot" style="background:#16a34a"></div>Validés (<?= $ops['points_valides']??0 ?>)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#d97706"></div>En attente (<?= $ops['points_attente']??0 ?>)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#dc2626"></div>Rejetés (<?= $ops['points_rejetes']??0 ?>)</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════ BLOC BOBINES & FILMS -->
<div class="bloc bloc-bob">
  <div class="bloc-hdr">
    <div class="bloc-ico bloc-ico-purple"><i class="ph-duotone ph-film-strip"></i></div>
    <div><div class="bloc-ttl">Bobines &amp; Films</div><div class="bloc-stl">État des bobines et consommation films</div></div>
  </div>
  <div class="bloc-body">

    <div class="kpi-row">
      <div class="kc c-purple">
        <div class="kc-val"><?= $bobines_stats['en_cours']??0 ?></div>
        <div class="kc-lbl">Bobines actives</div>
        <div class="kc-sub">en cours d'utilisation</div>
      </div>
      <div class="kc c-blue">
        <div class="kc-val"><?= $bobines_stats['en_stock']??0 ?></div>
        <div class="kc-lbl">En stock</div>
        <div class="kc-sub">prêtes à utiliser</div>
      </div>
      <div class="kc">
        <div class="kc-val"><?= fmt_number($films_mois) ?></div>
        <div class="kc-lbl">Films utilisés</div>
        <div class="kc-sub"><?= h($mois_display) ?></div>
      </div>
      <div class="kc <?= ($bobines_stats['films_restants']??0)<500?'c-red':'' ?>">
        <div class="kc-val"><?= fmt_number($bobines_stats['films_restants']??0) ?></div>
        <div class="kc-lbl">Films restants</div>
        <div class="kc-sub">toutes bobines</div>
      </div>
    </div>

    <div class="ch2">
      <div class="ch-box">
        <div class="ch-ttl">Films utilisés par site — <?= h($mois_display) ?></div>
        <div class="ch-wrap"><canvas id="cFilms" height="<?= $films_ch_h ?>"></canvas></div>
      </div>
      <div class="ch-box">
        <div class="ch-ttl">État des bobines</div>
        <div class="donut-row" style="margin-top:10px">
          <canvas id="cBobines" width="140" height="140" style="width:140px!important;flex-shrink:0"></canvas>
          <div class="legend">
            <div class="leg-item"><div class="leg-dot" style="background:#7c3aed"></div>En cours (<?= $bobines_stats['en_cours']??0 ?>)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#1B75BC"></div>En stock (<?= $bobines_stats['en_stock']??0 ?>)</div>
            <div class="leg-item"><div class="leg-dot" style="background:#94a3b8"></div>Épuisées (<?= $bobines_stats['epuisees']??0 ?>)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════ BLOC STOCK -->
<div class="bloc bloc-stock">
  <div class="bloc-hdr">
    <div class="bloc-ico bloc-ico-cyan"><i class="ph-duotone ph-package"></i></div>
    <div><div class="bloc-ttl">Stock matériel</div><div class="bloc-stl">PMMA et rivets par site</div></div>
  </div>
  <div class="bloc-body">
    <div class="ch2">
      <div class="ch-box">
        <div class="ch-ttl"><i class="ph-duotone ph-printer" style="vertical-align:middle"></i> PMMA par type <span style="font-size:10px;color:var(--muted);font-weight:400">— survoler pour détail par site</span></div>
        <div class="ch-wrap"><canvas id="cPmma" height="<?= $pmma_ch_h ?>"></canvas></div>
      </div>
      <div class="ch-box">
        <div class="ch-ttl"><i class="ph-duotone ph-nut" style="vertical-align:middle"></i> Rivets par type <span style="font-size:10px;color:var(--muted);font-weight:400">— survoler pour détail par site</span></div>
        <div class="ch-wrap"><canvas id="cRivets" height="<?= $riv_ch_h ?>"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════ BLOC COMMANDES -->
<div class="bloc bloc-cmd">
  <div class="bloc-hdr">
    <div class="bloc-ico bloc-ico-orange"><i class="ph-duotone ph-shopping-cart"></i></div>
    <div><div class="bloc-ttl">Commandes articles</div><div class="bloc-stl">État des commandes en cours</div></div>
  </div>
  <div class="bloc-body">
    <div class="cmd-wrap">
      <div class="cmd-stats">
        <div class="cmd-s" style="border-top:3px solid #d97706">
          <div class="cmd-s-val" style="color:#d97706"><?= $cmd_stats['en_attente']??0 ?></div>
          <div class="cmd-s-lbl">En attente</div>
        </div>
        <div class="cmd-s" style="border-top:3px solid #1B75BC">
          <div class="cmd-s-val" style="color:#1B75BC"><?= ($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0) ?></div>
          <div class="cmd-s-lbl">En livraison</div>
        </div>
        <div class="cmd-s" style="border-top:3px solid #16a34a">
          <div class="cmd-s-val" style="color:#16a34a"><?= $cmd_stats['livrees_mois']??0 ?></div>
          <div class="cmd-s-lbl">Livrées ce mois</div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:10px">
        <canvas id="cCmds" width="130" height="130" style="width:130px!important;flex-shrink:0"></canvas>
        <div class="legend">
          <div class="leg-item"><div class="leg-dot" style="background:#d97706"></div>En attente</div>
          <div class="leg-item"><div class="leg-dot" style="background:#1B75BC"></div>En livraison</div>
          <div class="leg-item"><div class="leg-dot" style="background:#16a34a"></div>Livrées</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════ BLOC ALERTES -->
<?php if(!empty($pts_attente)): ?>
<div class="bloc bloc-alrt">
  <div class="bloc-hdr">
    <div class="bloc-ico bloc-ico-red"><i class="ph-duotone ph-clock"></i></div>
    <div><div class="bloc-ttl">Points en attente de validation</div><div class="bloc-stl">Action requise par le superviseur</div></div>
  </div>
  <div class="bloc-body" style="padding-top:0">
    <table class="atbl">
      <thead><tr><th>Site</th><th>Date</th><th>Type</th><th>Coordinateur</th></tr></thead>
      <tbody>
      <?php foreach($pts_attente as $pt): ?>
      <tr>
        <td style="font-weight:600;color:var(--navy)"><?= h($pt['site']) ?></td>
        <td><?= fmt_date($pt['date_point']) ?></td>
        <td><span class="pill p-orange"><?= ['point_9h'=>'9h','point_13h'=>'13h','point_18h'=>'18h'][$pt['type_point']] ?? $pt['type_point'] ?></span></td>
        <td style="color:var(--muted)"><?= h($pt['coord']??'—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ═══════ MODAL DÉTAILS OPÉRATIONS ═══════ -->
<div id="modal-ops" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(6,3,58,.5);align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:white;border-radius:16px;width:100%;max-width:860px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <div style="width:34px;height:34px;border-radius:10px;background:#eef0f8;display:flex;align-items:center;justify-content:center;color:#06033A;font-size:16px">
        <i class="ph-duotone ph-table"></i>
      </div>
      <div>
        <div style="font-family:'Montserrat',sans-serif;font-size:14px;font-weight:800;color:#06033A">Détails par site — Opérations</div>
        <div style="font-size:12px;color:#94a3b8">Points journaliers — <?= h($mois_display) ?></div>
      </div>
      <button onclick="document.getElementById('modal-ops').style.display='none'" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:22px;line-height:1;padding:4px 8px">&times;</button>
    </div>
    <div style="overflow:auto;padding:0 20px 20px">
      <table class="stbl" style="margin-top:0">
        <thead><tr>
          <th>Site</th>
          <th style="min-width:110px">Progression engins</th>
          <th style="text-align:right">Engins</th>
          <th style="text-align:right">Plaques</th>
          <th style="text-align:right">Moy V/H</th>
          <th style="text-align:right">Points</th>
          <th style="text-align:center">Statut</th>
        </tr></thead>
        <tbody>
        <?php foreach($prod_par_site as $s):
          $pct = $max_engins > 0 ? round($s['engins'] / $max_engins * 100) : 0;
        ?>
        <tr>
          <td>
            <div style="font-weight:700;color:var(--navy)"><?= h($s['nom']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= h($s['type']??'') ?></div>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="pb-wrap" style="flex:1"><div class="pb-fill" style="width:<?= $pct ?>%"></div></div>
              <span style="font-size:11px;color:var(--muted);min-width:30px"><?= $pct ?>%</span>
            </div>
          </td>
          <td style="text-align:right;font-family:'Montserrat',sans-serif;font-weight:800;color:var(--navy)"><?= fmt_number($s['engins']) ?></td>
          <td style="text-align:right;font-weight:700;color:#1565c0"><?= fmt_number($s['plaques']) ?></td>
          <td style="text-align:right;font-weight:700;color:<?= $s['moy_vh']>=10?'#16a34a':($s['moy_vh']>=5?'#d97706':'#dc2626') ?>"><?= $s['moy_vh'] ?></td>
          <td style="text-align:right;color:var(--muted)"><?= $s['nb_points'] ?></td>
          <td style="text-align:center">
            <?php if($s['en_attente']>0): ?>
              <span class="pill p-orange"><?= $s['en_attente'] ?> att.</span>
            <?php elseif($s['nb_points']>0): ?>
              <span class="pill p-green">OK</span>
            <?php else: ?>
              <span class="pill p-blue">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="pdg-tip" style="display:none;position:fixed;z-index:1000;background:white;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;box-shadow:0 8px 24px rgba(0,0,0,.13);pointer-events:none;min-width:170px;font-size:12px;line-height:1.7"></div>

<script>
const evolLabels  = <?= $js_evol_labels ?>;
const evolEngins  = <?= $js_evol_engins ?>;
const filmsLabels = <?= $js_films_labels ?>;
const filmsValues = <?= $js_films_values ?>;
const statutsData = <?= $js_statuts ?>;
const bobinesData = <?= $js_bobines ?>;
const cmdsData    = <?= $js_cmds ?>;
const pmmaLabels  = <?= $js_pmma_labels ?>;
const pmmaTotals  = <?= $js_pmma_totals ?>;
const pmmaBas     = <?= $js_pmma_bas ?>;
const pmmaDetail  = <?= $js_pmma_detail ?>;
const rivLabels   = <?= $js_riv_labels ?>;
const rivTotals   = <?= $js_riv_totals ?>;
const rivDetail   = <?= $js_riv_detail ?>;

const DPR = window.devicePixelRatio || 1;

function initCv(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    const w = el.parentElement.getBoundingClientRect().width || 400;
    const h = parseInt(el.getAttribute('height') || 200);
    el.width  = Math.round(w * DPR);
    el.height = Math.round(h * DPR);
    el.style.width  = Math.round(w) + 'px';
    el.style.height = h + 'px';
    const ctx = el.getContext('2d');
    ctx.scale(DPR, DPR);
    return {ctx, w: Math.round(w), h};
}

function drawBar(id, labels, values, color) {
    const c = initCv(id); if (!c) return;
    const {ctx, w, h} = c;
    const pad = {t:22,r:10,b:36,l:42};
    const cW = w-pad.l-pad.r, cH = h-pad.t-pad.b;
    const max = Math.max(...values, 1);
    const n = labels.length || 1;
    const slot = cW/n, bw = Math.max(6, slot*0.55);

    ctx.strokeStyle='#e2e8f0'; ctx.lineWidth=1;
    for (let i=0; i<=4; i++) {
        const y = pad.t + cH*(1-i/4);
        ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(w-pad.r,y); ctx.stroke();
        ctx.fillStyle='#94a3b8'; ctx.font='10px DM Sans,sans-serif';
        ctx.textAlign='right'; ctx.textBaseline='middle';
        ctx.fillText(Math.round(max*i/4), pad.l-4, y);
    }
    if (!labels.length) {
        ctx.fillStyle='#94a3b8'; ctx.font='12px DM Sans,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText('Aucune donnée', w/2, h/2); return;
    }
    values.forEach((v,i) => {
        const bh = Math.max(2,(v/max)*cH);
        const x  = pad.l + slot*i + (slot-bw)/2;
        const y  = pad.t + cH - bh;
        ctx.fillStyle = color;
        ctx.fillRect(x, y, bw, bh);
        if (v>0) {
            ctx.fillStyle='#06033A'; ctx.font='bold 10px DM Sans,sans-serif';
            ctx.textAlign='center'; ctx.textBaseline='bottom';
            ctx.fillText(v, x+bw/2, y-2);
        }
        ctx.fillStyle='#94a3b8'; ctx.font='10px DM Sans,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='top';
        const lbl = (labels[i]||'').length>8 ? (labels[i]||'').substring(0,8) : (labels[i]||'');
        ctx.fillText(lbl, x+bw/2, h-pad.b+6);
    });
}

function drawHBar(id, labels, values, color) {
    const c = initCv(id); if (!c) return;
    const {ctx, w, h} = c;
    if (!labels.length) {
        ctx.fillStyle='#94a3b8'; ctx.font='12px DM Sans,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText('Aucune donnée', w/2, h/2); return;
    }
    const lw = 110, rw = 38;
    const bArea = w - lw - rw;
    const rh = h / labels.length;
    const bh = Math.min(rh * 0.45, 20);
    const max = Math.max(...values, 1);
    labels.forEach((lbl, i) => {
        const y  = i * rh;
        const by = y + (rh - bh) / 2;
        const bw = Math.max((values[i] / max) * bArea, values[i] > 0 ? 4 : 0);
        ctx.fillStyle = '#06033A'; ctx.font = '11px DM Sans,sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        ctx.fillText(lbl.length > 14 ? lbl.substring(0,14)+'…' : lbl, lw - 8, y + rh / 2);
        ctx.fillStyle = color;
        ctx.fillRect(lw, by, bw, bh);
        if (values[i] > 0) {
            ctx.fillStyle = '#64748b'; ctx.font = '10px DM Sans,sans-serif';
            ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
            ctx.fillText(values[i], lw + bw + 4, y + rh / 2);
        }
    });
}

function drawDonut(id, values, colors) {
    const el = document.getElementById(id); if (!el) return;
    const sz = parseInt(el.getAttribute('width')||140);
    el.width = sz*DPR; el.height = sz*DPR;
    el.style.width=sz+'px'; el.style.height=sz+'px';
    const ctx = el.getContext('2d');
    ctx.scale(DPR, DPR);
    const cx=sz/2, cy=sz/2, R=sz/2-6, r=R*0.58;
    const total = values.reduce((a,b)=>a+b,0);
    if (!total) {
        ctx.fillStyle='#e2e8f0';
        ctx.beginPath(); ctx.arc(cx,cy,R,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='white';
        ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#94a3b8'; ctx.font='bold 13px Montserrat,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText('0', cx, cy); return;
    }
    let angle = -Math.PI/2;
    values.forEach((v,i) => {
        if (!v) return;
        const slice=(v/total)*Math.PI*2;
        ctx.fillStyle=colors[i];
        ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,R,angle,angle+slice); ctx.closePath(); ctx.fill();
        angle+=slice;
    });
    ctx.fillStyle='white';
    ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.fill();
    ctx.fillStyle='#06033A'; ctx.font='bold 15px Montserrat,sans-serif';
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(total, cx, cy);
}

// ── TOOLTIP
function showTip(e, html) {
    const tip = document.getElementById('pdg-tip');
    tip.innerHTML = html;
    tip.style.display = 'block';
    const tw = tip.offsetWidth, th = tip.offsetHeight;
    const x  = e.clientX + 14;
    const y  = e.clientY - 10;
    tip.style.left = (x + tw > window.innerWidth - 8 ? x - tw - 28 : x) + 'px';
    tip.style.top  = (y + th > window.innerHeight - 8 ? y - th + 20 : y) + 'px';
}
function hideTip() { document.getElementById('pdg-tip').style.display = 'none'; }

function fmtN(n) { return Number(n).toLocaleString('fr-FR'); }

// ── PMMA CHART
function drawPmma() {
    const c = initCv('cPmma'); if (!c) return;
    const {ctx, w, h} = c;
    const lw = 96, rw = 38;
    const bArea = w - lw - rw;
    const n = pmmaLabels.length || 1;
    const rowH = h / n;
    const max = Math.max(...pmmaTotals, 1);
    pmmaLabels.forEach((lbl, i) => {
        const y  = i * rowH;
        const bh = Math.min(rowH * 0.44, 20);
        const by = y + (rowH - bh) / 2;
        const bw = Math.max((pmmaTotals[i] / max) * bArea, pmmaTotals[i] > 0 ? 4 : 0);
        const low = pmmaBas[i] > 0;
        ctx.fillStyle = '#06033A'; ctx.font = '11px DM Sans,sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        ctx.fillText(lbl.length > 12 ? lbl.substring(0,12)+'…' : lbl, lw - 8, y + rowH / 2);
        ctx.fillStyle = low ? '#dc2626' : '#0891b2';
        ctx.fillRect(lw, by, bw, bh);
        ctx.fillStyle = '#64748b'; ctx.font = '10px DM Sans,sans-serif';
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.fillText(fmtN(pmmaTotals[i]), lw + bw + 4, y + rowH / 2);
        if (low) {
            ctx.fillStyle = '#dc2626'; ctx.font = 'bold 11px DM Sans,sans-serif';
            ctx.textAlign = 'right'; ctx.fillText('⚠', lw - 2, y + rowH / 2);
        }
    });
}

// ── RIVETS CHART (par type)
function drawRivets() {
    const c = initCv('cRivets'); if (!c) return;
    const {ctx, w, h} = c;
    const lw = 96, rw = 38;
    const bArea = w - lw - rw;
    const n = rivLabels.length || 1;
    const rowH = h / n;
    const max = Math.max(...rivTotals, 1);
    const colors = ['#1B75BC', '#06033A'];
    rivLabels.forEach((lbl, i) => {
        const bh  = Math.min(rowH * 0.44, 20);
        const y   = i * rowH;
        const by  = y + (rowH - bh) / 2;
        const bw  = Math.max((rivTotals[i] / max) * bArea, rivTotals[i] > 0 ? 4 : 0);
        const bas = (rivDetail[i] || []).filter(s => s.qty < 200).length;
        ctx.fillStyle = '#06033A'; ctx.font = '11px DM Sans,sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        ctx.fillText(lbl, lw - 8, y + rowH / 2);
        ctx.fillStyle = bas > 0 ? '#dc2626' : colors[i];
        ctx.fillRect(lw, by, bw, bh);
        ctx.fillStyle = '#64748b'; ctx.font = '10px DM Sans,sans-serif';
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.fillText(fmtN(rivTotals[i]), lw + bw + 4, y + rowH / 2);
        if (bas > 0) {
            ctx.fillStyle = '#dc2626'; ctx.font = 'bold 11px DM Sans,sans-serif';
            ctx.textAlign = 'right'; ctx.fillText('⚠', lw - 2, y + rowH / 2);
        }
    });
}

// ── HOVER SETUP
function setupHover() {
    // Films
    const ef = document.getElementById('cFilms');
    if (ef && filmsLabels.length) {
        ef.style.cursor = 'crosshair';
        const filmsTotal = filmsValues.reduce((a, b) => a + b, 0);
        ef.addEventListener('mousemove', e => {
            const rect = ef.getBoundingClientRect();
            const idx  = Math.floor((e.clientY - rect.top) / (rect.height / filmsLabels.length));
            if (idx >= 0 && idx < filmsLabels.length) {
                const pct = filmsTotal > 0 ? Math.round(filmsValues[idx] / filmsTotal * 100) : 0;
                const html = `<div style="font-weight:700;color:#06033A;font-size:13px;margin-bottom:6px">${filmsLabels[idx]}</div>
                    <div style="display:flex;justify-content:space-between;gap:14px;color:#374151">
                        <span>Films utilisés</span>
                        <span style="font-weight:700">${fmtN(filmsValues[idx])} <span style="font-weight:400;color:#94a3b8">/ ${fmtN(filmsTotal)}</span></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:14px;color:#374151;margin-top:3px">
                        <span>Part du total</span>
                        <span style="font-weight:700;color:#7c3aed">${pct}%</span>
                    </div>`;
                showTip(e, html);
            } else hideTip();
        });
        ef.addEventListener('mouseleave', hideTip);
    }
    // PMMA
    const ep = document.getElementById('cPmma');
    if (ep && pmmaLabels.length) {
        ep.style.cursor = 'crosshair';
        ep.addEventListener('mousemove', e => {
            const rect = ep.getBoundingClientRect();
            const idx  = Math.floor((e.clientY - rect.top) / (rect.height / pmmaLabels.length));
            if (idx >= 0 && idx < pmmaLabels.length) {
                const d = pmmaDetail[idx] || [];
                let html = `<div style="font-weight:700;color:#06033A;font-size:13px;margin-bottom:6px">${pmmaLabels[idx]}</div>`;
                d.forEach(s => {
                    const low = s.qty < s.seuil;
                    html += `<div style="display:flex;justify-content:space-between;gap:14px;color:${low?'#dc2626':'#374151'}">
                        <span>${s.site||'—'}</span>
                        <span style="font-weight:700">${fmtN(s.qty)} <span style="font-weight:400;color:#94a3b8">/ ${fmtN(pmmaTotals[idx])}</span></span>
                    </div>`;
                });
                if (!d.length) html += `<div style="color:#94a3b8">Aucun stock</div>`;
                if (pmmaBas[idx] > 0) html += `<div style="margin-top:5px;color:#dc2626;font-size:11px;font-weight:600">⚠ ${pmmaBas[idx]} site(s) en stock bas</div>`;
                showTip(e, html);
            } else hideTip();
        });
        ep.addEventListener('mouseleave', hideTip);
    }
    // Rivets
    const er = document.getElementById('cRivets');
    if (er && rivLabels.length) {
        er.style.cursor = 'crosshair';
        er.addEventListener('mousemove', e => {
            const rect = er.getBoundingClientRect();
            const idx  = Math.floor((e.clientY - rect.top) / (rect.height / rivLabels.length));
            if (idx >= 0 && idx < rivLabels.length) {
                const d = rivDetail[idx] || [];
                let html = `<div style="font-weight:700;color:#06033A;font-size:13px;margin-bottom:6px">${rivLabels[idx]}</div>`;
                d.forEach(s => {
                    const low = s.qty < 200;
                    html += `<div style="display:flex;justify-content:space-between;gap:14px;color:${low?'#dc2626':'#374151'}">
                        <span>${s.site}</span>
                        <span style="font-weight:700">${fmtN(s.qty)} <span style="font-weight:400;color:#94a3b8">/ ${fmtN(rivTotals[idx])}</span></span>
                    </div>`;
                });
                const bas = d.filter(s => s.qty < 200).length;
                if (bas > 0) html += `<div style="margin-top:5px;color:#dc2626;font-size:11px;font-weight:600">⚠ ${bas} site(s) sous seuil (200)</div>`;
                showTip(e, html);
            } else hideTip();
        });
        er.addEventListener('mouseleave', hideTip);
    }
}

function render() {
    drawBar('cEvol',   evolLabels,  evolEngins,  '#1B75BC');
    drawHBar('cFilms', filmsLabels, filmsValues, '#7c3aed');
    drawDonut('cStatuts', statutsData, ['#16a34a','#d97706','#dc2626']);
    drawDonut('cBobines', bobinesData, ['#7c3aed','#1B75BC','#94a3b8']);
    drawDonut('cCmds',    cmdsData,    ['#d97706','#1B75BC','#16a34a']);
    drawPmma();
    drawRivets();
}

window.addEventListener('load', () => { setTimeout(render, 60); setTimeout(setupHover, 120); });
let _rt; window.addEventListener('resize', () => { clearTimeout(_rt); _rt=setTimeout(render,200); });
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
